<?php
/*
 * =============================================
 * ARQUIVO: cadastro_especie.php
 * FUNÇÃO: Catálogo de espécies de árvores (com foto)
 * =============================================
 *
 * Tabela: especies_arvores
 *   (id_especie, nome_popular, nome_cientifico, porte, nativa_sp, imagem)
 *
 * Precisa da coluna "imagem" criada pelo admin_setup.sql.
 *
 *   C → POST sem id         → INSERT
 *   R → SELECT ao carregar  → tabela no final da página
 *   U → POST com id         → UPDATE (abre com ?edit_id=X)
 *   D → POST acao=excluir   → DELETE (bloqueado se houver pedidos com a espécie)
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

$mensagem = null;
$class    = null;

$especie = [
    'id_especie' => '', 'nome_popular' => '', 'nome_cientifico' => '',
    'porte' => 'medio', 'nativa_sp' => 1, 'imagem' => ''
];

// -----------------------------------------------
// READ — carrega a espécie para edição
// -----------------------------------------------
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("SELECT * FROM especies_arvores WHERE id_especie = ?");
    $eid  = intval($_GET['edit_id']);
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $achada = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($achada) $especie = $achada;
}

// -----------------------------------------------
// CREATE / UPDATE / DELETE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!csrf_valido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente de novo.";
        $class    = "error";

    } elseif (($_POST['acao'] ?? '') === 'excluir') {
        // ---- DELETE ----
        $id = intval($_POST['id'] ?? 0);
        try {
            $chk = $conn->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE id_especie = ?");
            $chk->bind_param("i", $id);
            $chk->execute();
            $total = $chk->get_result()->fetch_assoc()['total'];
            $chk->close();

            if ($total > 0) {
                $mensagem = "Não é possível excluir: a espécie está em $total pedido(s).";
                $class    = "error";
            } else {
                $stmt = $conn->prepare("SELECT imagem FROM especies_arvores WHERE id_especie = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $arq = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM especies_arvores WHERE id_especie = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                if ($arq) remover_imagem($arq['imagem']); // apaga o arquivo da foto
                redirecionar('cadastro_especie.php', 'Espécie excluída!');
            }
        } catch (mysqli_sql_exception $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage();
            $class    = "error";
        }

    } else {
        // ---- CREATE / UPDATE ----
        $id        = intval($_POST['id'] ?? 0);
        $popular   = trim($_POST['nome_popular'] ?? '');
        $cientif   = trim($_POST['nome_cientifico'] ?? '');
        $porte     = $_POST['porte'] ?? '';
        $nativa    = (($_POST['nativa_sp'] ?? '1') === '1') ? 1 : 0;

        // Mantém o que a pessoa digitou caso dê erro (a foto atual continua a mesma)
        $especie = array_merge($especie, [
            'id_especie' => $id ?: '', 'nome_popular' => $popular, 'nome_cientifico' => $cientif,
            'porte' => $porte, 'nativa_sp' => $nativa
        ]);

        if ($popular === '' || $cientif === '') {
            $mensagem = "Preencha o nome popular e o nome científico.";
            $class    = "error";
        } elseif (!array_key_exists($porte, PORTES)) {
            $mensagem = "Escolha o porte da árvore.";
            $class    = "error";
        } else {
            // Foto (opcional)
            $imagem = null;
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$imagem, $erro_img] = salvar_imagem($_FILES['imagem']);
                if ($erro_img) {
                    $mensagem = $erro_img;
                    $class    = "error";
                }
            }

            if ($class !== "error") {
                try {
                    if ($id) {
                        // Guarda a foto antiga para apagar se uma nova foi enviada
                        $q = $conn->prepare("SELECT imagem FROM especies_arvores WHERE id_especie = ?");
                        $q->bind_param("i", $id);
                        $q->execute();
                        $antiga = $q->get_result()->fetch_assoc()['imagem'] ?? null;
                        $q->close();

                        // UPDATE — só troca a imagem se uma nova foi enviada
                        $sql    = "UPDATE especies_arvores SET nome_popular=?, nome_cientifico=?, porte=?, nativa_sp=?";
                        $tipos  = "sssi";
                        $params = [$popular, $cientif, $porte, $nativa];
                        if ($imagem) {
                            $sql .= ", imagem=?";
                            $tipos .= "s";
                            $params[] = $imagem;
                        }
                        $sql   .= " WHERE id_especie=?";
                        $tipos .= "i";
                        $params[] = $id;

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($tipos, ...$params);
                        $stmt->execute();
                        $stmt->close();
                        if ($imagem) remover_imagem($antiga);
                        $ok = "Espécie atualizada com sucesso!";
                    } else {
                        // INSERT
                        $stmt = $conn->prepare("INSERT INTO especies_arvores (nome_popular, nome_cientifico, porte, nativa_sp, imagem) VALUES (?,?,?,?,?)");
                        $stmt->bind_param("sssis", $popular, $cientif, $porte, $nativa, $imagem);
                        $stmt->execute();
                        $stmt->close();
                        $ok = "Espécie cadastrada com sucesso!";
                    }
                    redirecionar('cadastro_especie.php', $ok);
                } catch (mysqli_sql_exception $e) {
                    if ($imagem) remover_imagem($imagem); // não deixa foto órfã
                    $mensagem = ($e->getCode() == 1062)
                        ? "Já existe uma espécie com o nome popular \"$popular\"."
                        : "Erro: " . $e->getMessage();
                    $class = "error";
                }
            }
        }
    }
}

// -----------------------------------------------
// READ — listagem
// -----------------------------------------------
$especies = $conn->query("SELECT e.*,
        (SELECT COUNT(*) FROM pedidos p WHERE p.id_especie = e.id_especie) AS qtd_pedidos
    FROM especies_arvores e
    ORDER BY e.nome_popular");

$editando      = !empty($especie['id_especie']);
$titulo_pagina = $editando ? 'Editar espécie' : 'Espécies';
$pagina_ativa  = 'especies';
include('cabecalho.php');
?>

<div class="page-header-bar">
    <h2><?php echo $editando ? '✏️ Editar espécie' : '🌳 Nova espécie'; ?></h2>
    <div class="header-actions">
        <a href="index.php" class="btn-back">⬅ Voltar ao início</a>
    </div>
</div>

<?php mostrar_mensagem($mensagem, $class); ?>

<!-- enctype="multipart/form-data" é obrigatório para enviar arquivos -->
<form method="post" action="" enctype="multipart/form-data">
    <?php echo csrf_campo(); ?>
    <input type="hidden" name="id" value="<?php echo h($especie['id_especie']); ?>">

    <div class="form-grid">

        <div class="form-group">
            <label for="nome_popular">Nome popular <span class="req">*</span></label>
            <input type="text" id="nome_popular" name="nome_popular" maxlength="80"
                value="<?php echo h($especie['nome_popular']); ?>"
                required placeholder="Ex: Ipê-Amarelo, Sibipiruna…">
        </div>

        <div class="form-group">
            <label for="nome_cientifico">Nome científico <span class="req">*</span></label>
            <input type="text" id="nome_cientifico" name="nome_cientifico" maxlength="120"
                value="<?php echo h($especie['nome_cientifico']); ?>"
                required placeholder="Ex: Handroanthus chrysotrichus">
        </div>

        <div class="form-group">
            <label for="porte">Porte <span class="req">*</span></label>
            <select id="porte" name="porte" required>
                <?php foreach (PORTES as $valor => $rotulo): ?>
                    <option value="<?php echo $valor; ?>" <?php echo $especie['porte'] === $valor ? 'selected' : ''; ?>>
                        <?php echo $rotulo; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="nativa_sp">Origem</label>
            <select id="nativa_sp" name="nativa_sp">
                <option value="1" <?php echo $especie['nativa_sp'] ? 'selected' : ''; ?>>Nativa de São Paulo</option>
                <option value="0" <?php echo !$especie['nativa_sp'] ? 'selected' : ''; ?>>Exótica</option>
            </select>
        </div>

        <div class="form-group full">
            <label for="imagem">Foto da espécie</label>
            <input type="file" id="imagem" name="imagem" accept="image/png,image/jpeg">
            <small>JPG ou PNG · Máximo 5 MB<?php echo $editando ? ' · Deixe vazio para manter a foto atual' : ''; ?></small>

            <?php if (!empty($especie['imagem'])): ?>
                <div class="current-image">
                    <img src="<?php echo h($especie['imagem']); ?>" alt="Foto atual de <?php echo h($especie['nome_popular']); ?>">
                    <small>Foto atual</small>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <?php echo $editando ? '💾 Atualizar' : '➕ Cadastrar'; ?>
            </button>
            <?php if ($editando): ?>
                <a href="cadastro_especie.php" class="btn-back">✖ Cancelar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<h3>📋 Espécies cadastradas</h3>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th class="col-foto">Foto</th>
                <th>Nome popular</th>
                <th>Nome científico</th>
                <th>Porte</th>
                <th>Origem</th>
                <th>Pedidos</th>
                <th class="col-acoes">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($especies->num_rows > 0): ?>
                <?php while ($row = $especies->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['imagem'])): ?>
                                <img src="<?php echo h($row['imagem']); ?>" alt="<?php echo h($row['nome_popular']); ?>"
                                    class="table-thumbnail" loading="lazy">
                            <?php else: ?>
                                <div class="no-image">Sem<br>foto</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($row['nome_popular']); ?></td>
                        <td><em><?php echo h($row['nome_cientifico']); ?></em></td>
                        <td><?php echo h(PORTES[$row['porte']] ?? $row['porte']); ?></td>
                        <td>
                            <?php echo $row['nativa_sp']
                                ? '<span class="badge badge-nativa">Nativa</span>'
                                : '<span class="badge badge-exotica">Exótica</span>'; ?>
                        </td>
                        <td><?php echo (int)$row['qtd_pedidos']; ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="?edit_id=<?php echo (int)$row['id_especie']; ?>" class="btn-edit">✏️ Editar</a>
                                <?php echo botao_post(
                                    'excluir',
                                    ['id' => $row['id_especie']],
                                    '🗑️',
                                    'btn-delete',
                                    'Excluir ' . $row['nome_popular'] . '?',
                                    'Excluir ' . $row['nome_popular']
                                ); ?>
                                <a href="listagem_pedidos.php?especie=<?php echo (int)$row['id_especie']; ?>" class="btn-music">📋 Pedidos</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="empty-cell">Nenhuma espécie cadastrada. Use o formulário acima para adicionar a primeira.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include('rodape.php'); ?>
