<?php
/*
 * =============================================
 * ARQUIVO: cadastro_cliente.php
 * FUNÇÃO: Cadastro de clientes (solicitantes)
 * =============================================
 *
 * Tabela: clientes (cpf, nome, idade, id_cidade)
 * A chave primária é o CPF (formato 000.000.000-00), por isso:
 *   - a edição abre com ?edit_cpf=...
 *   - ao editar, o CPF não pode ser alterado
 *
 * Cada cliente pertence a uma cidade (como cada música pertencia a um
 * artista). ?cidade_id=X mostra só os clientes daquela cidade.
 *
 *   C → POST sem cpf_original  → INSERT
 *   R → SELECT ao carregar     → tabela no final da página
 *   U → POST com cpf_original  → UPDATE
 *   D → POST acao=excluir      → DELETE (bloqueado se houver pedidos)
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

$mensagem = null;
$class    = null;

$filtro_cidade = isset($_GET['cidade_id']) ? intval($_GET['cidade_id']) : 0;
$qs_filtro     = $filtro_cidade ? '?cidade_id=' . $filtro_cidade : '';
$url_lista     = 'cadastro_cliente.php' . $qs_filtro;

$cliente = ['cpf' => '', 'nome' => '', 'idade' => '', 'id_cidade' => $filtro_cidade ?: ''];
$editando = false;

// -----------------------------------------------
// READ — carrega o cliente para edição
// -----------------------------------------------
if (isset($_GET['edit_cpf'])) {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE cpf = ?");
    $stmt->bind_param("s", $_GET['edit_cpf']);
    $stmt->execute();
    $achado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($achado) {
        $cliente  = $achado;
        $editando = true;
    }
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
        $cpf = $_POST['cpf'] ?? '';
        try {
            $chk = $conn->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE cpf_cliente = ?");
            $chk->bind_param("s", $cpf);
            $chk->execute();
            $total = $chk->get_result()->fetch_assoc()['total'];
            $chk->close();

            if ($total > 0) {
                $mensagem = "Não é possível excluir: o cliente tem $total pedido(s).";
                $class    = "error";
            } else {
                $stmt = $conn->prepare("DELETE FROM clientes WHERE cpf = ?");
                $stmt->bind_param("s", $cpf);
                $stmt->execute();
                $stmt->close();
                redirecionar($url_lista, 'Cliente excluído!');
            }
        } catch (mysqli_sql_exception $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage();
            $class    = "error";
        }

    } else {
        // ---- CREATE / UPDATE ----
        $cpf_original = $_POST['cpf_original'] ?? '';
        $atualizando  = ($cpf_original !== '');
        $digitos      = cpf_digitos($_POST['cpf'] ?? '');
        $nome         = trim($_POST['nome'] ?? '');
        $idade        = intval($_POST['idade'] ?? 0);
        $id_cidade    = intval($_POST['id_cidade'] ?? 0);

        // No cadastro novo o CPF vem do campo; na edição, é sempre o original
        $cpf = $atualizando ? $cpf_original : (strlen($digitos) === 11 ? cpf_formatar($digitos) : $_POST['cpf'] ?? '');

        // Mantém o que a pessoa digitou caso dê erro
        $cliente  = ['cpf' => $cpf, 'nome' => $nome, 'idade' => $idade ?: '', 'id_cidade' => $id_cidade ?: ''];
        $editando = $atualizando;

        if ($nome === '') {
            $mensagem = "Informe o nome do cliente.";
            $class    = "error";
        } elseif (!$atualizando && !cpf_valido($cpf)) {
            $mensagem = "CPF inválido. Confira os números digitados.";
            $class    = "error";
        } elseif ($idade < 18 || $idade > 120) {
            $mensagem = "A idade deve estar entre 18 e 120 anos.";
            $class    = "error";
        } elseif ($id_cidade <= 0) {
            $mensagem = "Selecione a cidade do cliente.";
            $class    = "error";
        } else {
            try {
                if ($atualizando) {
                    $stmt = $conn->prepare("UPDATE clientes SET nome=?, idade=?, id_cidade=? WHERE cpf=?");
                    $stmt->bind_param("siis", $nome, $idade, $id_cidade, $cpf);
                    $ok = "Cliente atualizado com sucesso!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO clientes (cpf, nome, idade, id_cidade) VALUES (?,?,?,?)");
                    $stmt->bind_param("ssii", $cpf, $nome, $idade, $id_cidade);
                    $ok = "Cliente cadastrado com sucesso!";
                }
                $stmt->execute();
                $stmt->close();
                redirecionar($url_lista, $ok);
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $mensagem = "Já existe um cliente com o CPF $cpf.";
                } elseif ($e->getCode() == 1452) {
                    $mensagem = "A cidade escolhida não existe mais. Recarregue a página.";
                } else {
                    $mensagem = "Erro: " . $e->getMessage();
                }
                $class = "error";
            }
        }
    }
}

// -----------------------------------------------
// READ — cidades (para o select) e listagem
// -----------------------------------------------
$cidades = $conn->query("SELECT id_cidade, nome_cidade FROM regioes ORDER BY nome_cidade")
    ->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT c.*, r.nome_cidade,
            (SELECT COUNT(*) FROM pedidos p WHERE p.cpf_cliente = c.cpf) AS qtd_pedidos
        FROM clientes c
        JOIN regioes r ON r.id_cidade = c.id_cidade"
    . ($filtro_cidade ? " WHERE c.id_cidade = ?" : "")
    . " ORDER BY c.nome";
$stmt = $conn->prepare($sql);
if ($filtro_cidade) $stmt->bind_param("i", $filtro_cidade);
$stmt->execute();
$clientes = $stmt->get_result();
$stmt->close();

$titulo_pagina = $editando ? 'Editar cliente' : 'Clientes';
$pagina_ativa  = 'clientes';
include('cabecalho.php');
?>

<div class="page-header-bar">
    <h2><?php echo $editando ? '✏️ Editar cliente' : '👥 Novo cliente'; ?></h2>
    <div class="header-actions">
        <?php if ($filtro_cidade): ?>
            <a href="cadastro_cidade.php" class="btn-back">⬅ Voltar para Cidades</a>
        <?php else: ?>
            <a href="index.php" class="btn-back">⬅ Voltar ao início</a>
        <?php endif; ?>
    </div>
</div>

<?php mostrar_mensagem($mensagem, $class); ?>

<form method="post" action="">
    <?php echo csrf_campo(); ?>
    <input type="hidden" name="cpf_original" value="<?php echo $editando ? h($cliente['cpf']) : ''; ?>">

    <div class="form-grid">

        <div class="form-group">
            <label for="cpf">CPF <span class="req">*</span></label>
            <input type="text" id="cpf" name="cpf" inputmode="numeric" maxlength="14"
                pattern="\d{3}\.\d{3}\.\d{3}-\d{2}" title="Formato: 000.000.000-00"
                value="<?php echo h($cliente['cpf']); ?>"
                <?php echo $editando ? 'readonly' : 'required'; ?>
                placeholder="000.000.000-00">
            <?php if ($editando): ?>
                <small>O CPF identifica o cliente e não pode ser alterado.</small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="nome">Nome completo <span class="req">*</span></label>
            <input type="text" id="nome" name="nome" maxlength="120"
                value="<?php echo h($cliente['nome']); ?>"
                required placeholder="Ex: Marina Albuquerque">
        </div>

        <div class="form-group">
            <label for="idade">Idade <span class="req">*</span></label>
            <input type="number" id="idade" name="idade" min="18" max="120"
                value="<?php echo h($cliente['idade']); ?>"
                required placeholder="18 ou mais">
        </div>

        <div class="form-group">
            <label for="id_cidade">Cidade <span class="req">*</span></label>
            <select id="id_cidade" name="id_cidade" required>
                <option value="">Selecione uma cidade</option>
                <?php foreach ($cidades as $c): ?>
                    <option value="<?php echo (int)$c['id_cidade']; ?>"
                        <?php echo ($cliente['id_cidade'] == $c['id_cidade']) ? 'selected' : ''; ?>>
                        <?php echo h($c['nome_cidade']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <?php echo $editando ? '💾 Atualizar' : '➕ Cadastrar'; ?>
            </button>
            <?php if ($editando): ?>
                <a href="<?php echo h($url_lista); ?>" class="btn-back">✖ Cancelar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<h3>📋 Clientes cadastrados</h3>

<div class="filters">
    <form method="get" class="filter-form">
        <div class="filter-group">
            <div class="filter-item">
                <label for="cidade_id">Filtrar por cidade</label>
                <select name="cidade_id" id="cidade_id">
                    <option value="">Todas as cidades</option>
                    <?php foreach ($cidades as $c): ?>
                        <option value="<?php echo (int)$c['id_cidade']; ?>"
                            <?php echo ($filtro_cidade == $c['id_cidade']) ? 'selected' : ''; ?>>
                            <?php echo h($c['nome_cidade']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">🔍 Filtrar</button>
            <?php if ($filtro_cidade): ?>
                <a href="cadastro_cliente.php" class="btn-back">✖ Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>Idade</th>
                <th>Cidade</th>
                <th>Pedidos</th>
                <th class="col-acoes">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($clientes->num_rows > 0): ?>
                <?php while ($row = $clientes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($row['nome']); ?></td>
                        <td><?php echo h($row['cpf']); ?></td>
                        <td><?php echo (int)$row['idade']; ?></td>
                        <td><?php echo h($row['nome_cidade']); ?></td>
                        <td><?php echo (int)$row['qtd_pedidos']; ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="?edit_cpf=<?php echo urlencode($row['cpf']) . ($filtro_cidade ? '&cidade_id=' . $filtro_cidade : ''); ?>"
                                    class="btn-edit">✏️ Editar</a>
                                <?php echo botao_post(
                                    'excluir',
                                    ['cpf' => $row['cpf']],
                                    '🗑️',
                                    'btn-delete',
                                    'Excluir ' . $row['nome'] . '?',
                                    'Excluir ' . $row['nome']
                                ); ?>
                                <a href="cadastro_pedido.php?cpf=<?php echo urlencode($row['cpf']); ?>"
                                    class="btn-music">🌱 Novo pedido</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="empty-cell">
                        <?php echo $filtro_cidade ? 'Nenhum cliente nesta cidade.' : 'Nenhum cliente cadastrado. Use o formulário acima para adicionar o primeiro.'; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Máscara do CPF: vai formatando 000.000.000-00 enquanto digita
    var campoCpf = document.getElementById('cpf');
    if (campoCpf && !campoCpf.readOnly) {
        campoCpf.addEventListener('input', function(e) {
            var v = e.target.value.replace(/\D/g, '').slice(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = v;
        });
    }
</script>

<?php include('rodape.php'); ?>
