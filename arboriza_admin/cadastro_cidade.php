<?php
/*
 * =============================================
 * ARQUIVO: cadastro_cidade.php
 * FUNÇÃO: Cadastro de cidades/regiões atendidas
 * =============================================
 *
 * Tabela: regioes (id_cidade, nome_cidade, regiao_metropolitana)
 *
 * Operações:
 *   C → POST sem id           → INSERT
 *   R → SELECT ao carregar    → tabela no final da página
 *   U → POST com id           → UPDATE (abre com ?edit_id=X)
 *   D → POST acao=excluir     → DELETE (bloqueado se houver clientes/pedidos)
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

$mensagem = null;
$class    = null;

$cidade = ['id_cidade' => '', 'nome_cidade' => '', 'regiao_metropolitana' => ''];

// -----------------------------------------------
// READ — carrega a cidade para edição
// -----------------------------------------------
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("SELECT * FROM regioes WHERE id_cidade = ?");
    $eid  = intval($_GET['edit_id']);
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $achada = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($achada) $cidade = $achada;
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
            $chk = $conn->prepare("SELECT
                    (SELECT COUNT(*) FROM clientes WHERE id_cidade = ?) AS clientes,
                    (SELECT COUNT(*) FROM pedidos  WHERE id_cidade = ?) AS pedidos");
            $chk->bind_param("ii", $id, $id);
            $chk->execute();
            $uso = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($uso['clientes'] > 0 || $uso['pedidos'] > 0) {
                $mensagem = "Não é possível excluir: a cidade tem {$uso['clientes']} cliente(s) e {$uso['pedidos']} pedido(s).";
                $class    = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM regioes WHERE id_cidade = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                redirecionar('cadastro_cidade.php', 'Cidade excluída!');
            }
        } catch (mysqli_sql_exception $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage();
            $class    = "error";
        }

    } else {
        // ---- CREATE / UPDATE ----
        $id      = intval($_POST['id'] ?? 0);
        $nome    = trim($_POST['nome_cidade'] ?? '');
        $regiao  = trim($_POST['regiao_metropolitana'] ?? '');

        // Mantém o que a pessoa digitou caso dê erro
        $cidade = ['id_cidade' => $id ?: '', 'nome_cidade' => $nome, 'regiao_metropolitana' => $regiao];

        if ($nome === '' || $regiao === '') {
            $mensagem = "Preencha o nome da cidade e a região metropolitana.";
            $class    = "error";
        } else {
            try {
                if ($id) {
                    $stmt = $conn->prepare("UPDATE regioes SET nome_cidade=?, regiao_metropolitana=? WHERE id_cidade=?");
                    $stmt->bind_param("ssi", $nome, $regiao, $id);
                    $ok = "Cidade atualizada com sucesso!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO regioes (nome_cidade, regiao_metropolitana) VALUES (?,?)");
                    $stmt->bind_param("ss", $nome, $regiao);
                    $ok = "Cidade cadastrada com sucesso!";
                }
                $stmt->execute();
                $stmt->close();
                redirecionar('cadastro_cidade.php', $ok);
            } catch (mysqli_sql_exception $e) {
                $mensagem = ($e->getCode() == 1062)
                    ? "Já existe uma cidade chamada \"$nome\"."
                    : "Erro: " . $e->getMessage();
                $class = "error";
            }
        }
    }
}

// -----------------------------------------------
// READ — listagem com contagem de clientes e pedidos
// -----------------------------------------------
$cidades = $conn->query("SELECT r.*,
        (SELECT COUNT(*) FROM clientes c WHERE c.id_cidade = r.id_cidade) AS qtd_clientes,
        (SELECT COUNT(*) FROM pedidos  p WHERE p.id_cidade = r.id_cidade) AS qtd_pedidos
    FROM regioes r
    ORDER BY r.nome_cidade");

$editando      = !empty($cidade['id_cidade']);
$titulo_pagina = $editando ? 'Editar cidade' : 'Cidades';
$pagina_ativa  = 'cidades';
include('cabecalho.php');
?>

<div class="page-header-bar">
    <h2><?php echo $editando ? '✏️ Editar cidade' : '🏙️ Nova cidade'; ?></h2>
    <div class="header-actions">
        <a href="index.php" class="btn-back">⬅ Voltar ao início</a>
    </div>
</div>

<?php mostrar_mensagem($mensagem, $class); ?>

<form method="post" action="">
    <?php echo csrf_campo(); ?>
    <input type="hidden" name="id" value="<?php echo h($cidade['id_cidade']); ?>">

    <div class="form-grid">
        <div class="form-group">
            <label for="nome_cidade">Cidade <span class="req">*</span></label>
            <input type="text" id="nome_cidade" name="nome_cidade" maxlength="100"
                value="<?php echo h($cidade['nome_cidade']); ?>"
                required placeholder="Ex: São Paulo, Campinas…">
        </div>

        <div class="form-group">
            <label for="regiao_metropolitana">Região metropolitana <span class="req">*</span></label>
            <input type="text" id="regiao_metropolitana" name="regiao_metropolitana" maxlength="80"
                value="<?php echo h($cidade['regiao_metropolitana']); ?>"
                required placeholder="Ex: RMSP, RMC, Baixada Santista…">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <?php echo $editando ? '💾 Atualizar' : '➕ Cadastrar'; ?>
            </button>
            <?php if ($editando): ?>
                <a href="cadastro_cidade.php" class="btn-back">✖ Cancelar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<h3>📋 Cidades cadastradas</h3>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Cidade</th>
                <th>Região metropolitana</th>
                <th>Clientes</th>
                <th>Pedidos</th>
                <th class="col-acoes">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($cidades->num_rows > 0): ?>
                <?php while ($row = $cidades->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($row['nome_cidade']); ?></td>
                        <td><?php echo h($row['regiao_metropolitana']); ?></td>
                        <td><?php echo (int)$row['qtd_clientes']; ?></td>
                        <td><?php echo (int)$row['qtd_pedidos']; ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="?edit_id=<?php echo (int)$row['id_cidade']; ?>" class="btn-edit">✏️ Editar</a>
                                <?php echo botao_post(
                                    'excluir',
                                    ['id' => $row['id_cidade']],
                                    '🗑️',
                                    'btn-delete',
                                    'Excluir ' . $row['nome_cidade'] . '?',
                                    'Excluir ' . $row['nome_cidade']
                                ); ?>
                                <a href="cadastro_cliente.php?cidade_id=<?php echo (int)$row['id_cidade']; ?>" class="btn-music">👥 Clientes</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-cell">Nenhuma cidade cadastrada. Use o formulário acima para adicionar a primeira.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include('rodape.php'); ?>
