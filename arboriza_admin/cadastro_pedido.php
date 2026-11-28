<?php
/*
 * =============================================
 * ARQUIVO: cadastro_pedido.php
 * FUNÇÃO: Abrir e editar pedidos de arborização
 * =============================================
 *
 * Tabela: pedidos
 *
 *   C → POST sem id  → CALL novo_pedido_requisitado(...)
 *       (a procedure grava data/hora e status "requisitado" sozinha)
 *   U → POST com id  → UPDATE de serviço, espécie, cidade e endereço
 *       (só enquanto o pedido está requisitado ou em andamento)
 *
 * Mudar o STATUS (iniciar, finalizar, cancelar) é feito em
 * listagem_pedidos.php, que chama as procedures do banco.
 *
 * ?cpf=X abre o formulário já com o cliente escolhido.
 * ?edit_id=X abre o pedido para edição.
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

$mensagem = null;
$class    = null;

$pedido = [
    'id_pedido' => '', 'cpf_cliente' => $_GET['cpf'] ?? '', 'id_cidade' => '',
    'id_especie' => '', 'tipo_servico' => 'plantio', 'endereco_plantio' => '',
    'status' => '', 'data_pedido' => '', 'hora_pedido' => ''
];
$editando = false;

// Ao abrir com ?cpf=X, a cidade do pedido começa igual à cidade do cliente
if ($pedido['cpf_cliente'] !== '') {
    $q = $conn->prepare("SELECT id_cidade FROM clientes WHERE cpf = ?");
    $q->bind_param("s", $pedido['cpf_cliente']);
    $q->execute();
    $pedido['id_cidade'] = $q->get_result()->fetch_assoc()['id_cidade'] ?? '';
    $q->close();
}

// -----------------------------------------------
// READ — carrega o pedido para edição
// -----------------------------------------------
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id_pedido = ?");
    $eid  = intval($_GET['edit_id']);
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $achado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($achado && in_array($achado['status'], ['requisitado', 'em_andamento'])) {
        $pedido   = $achado;
        $editando = true;
    } elseif ($achado) {
        redirecionar('listagem_pedidos.php', 'Pedidos finalizados ou cancelados não podem ser editados.', 'error');
    }
}

// -----------------------------------------------
// CREATE / UPDATE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id         = intval($_POST['id'] ?? 0);
    $cpf        = $id ? ($pedido['cpf_cliente']) : ($_POST['cpf_cliente'] ?? '');
    $id_cidade  = intval($_POST['id_cidade'] ?? 0);
    $id_especie = intval($_POST['id_especie'] ?? 0) ?: null; // vazio = sem espécie definida
    $servico    = $_POST['tipo_servico'] ?? '';
    $endereco   = trim($_POST['endereco_plantio'] ?? '');

    // Mantém o que a pessoa digitou caso dê erro
    $pedido = array_merge($pedido, [
        'cpf_cliente' => $cpf, 'id_cidade' => $id_cidade ?: '', 'id_especie' => $id_especie ?? '',
        'tipo_servico' => $servico, 'endereco_plantio' => $endereco
    ]);

    if (!csrf_valido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente de novo.";
        $class    = "error";
    } elseif ($cpf === '') {
        $mensagem = "Selecione o cliente.";
        $class    = "error";
    } elseif ($id_cidade <= 0) {
        $mensagem = "Selecione a cidade do serviço.";
        $class    = "error";
    } elseif (!array_key_exists($servico, SERVICOS)) {
        $mensagem = "Escolha o tipo de serviço.";
        $class    = "error";
    } elseif ($endereco === '') {
        $mensagem = "Informe o endereço do serviço.";
        $class    = "error";
    } else {
        try {
            if ($id) {
                // UPDATE — a condição de status protege pedidos já encerrados
                $stmt = $conn->prepare("UPDATE pedidos
                    SET id_cidade=?, id_especie=?, tipo_servico=?, endereco_plantio=?
                    WHERE id_pedido=? AND status IN ('requisitado','em_andamento')");
                $stmt->bind_param("iissi", $id_cidade, $id_especie, $servico, $endereco, $id);
                $stmt->execute();
                $stmt->close();
                redirecionar('listagem_pedidos.php', "Pedido #$id atualizado com sucesso!");
            } else {
                // INSERT feito pela procedure do banco (já grava a data, a hora e o status)
                $stmt = $conn->prepare("CALL novo_pedido_requisitado(?,?,?,?,?)");
                $stmt->bind_param("siiss", $cpf, $id_cidade, $id_especie, $servico, $endereco);
                $stmt->execute();
                $stmt->close();

                $novo = $conn->query("SELECT LAST_INSERT_ID() AS id")->fetch_assoc()['id'];
                redirecionar('listagem_pedidos.php?status=requisitado', "Pedido #$novo aberto com sucesso!");
            }
        } catch (mysqli_sql_exception $e) {
            $mensagem = ($e->getCode() == 1452)
                ? "Cliente, cidade ou espécie não encontrados. Recarregue a página e tente de novo."
                : "Erro: " . $e->getMessage();
            $class = "error";
        }
    }
}

// -----------------------------------------------
// READ — listas dos selects
// -----------------------------------------------
$clientes = $conn->query("SELECT cpf, nome, id_cidade FROM clientes ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$cidades  = $conn->query("SELECT id_cidade, nome_cidade FROM regioes ORDER BY nome_cidade")->fetch_all(MYSQLI_ASSOC);
$especies = $conn->query("SELECT id_especie, nome_popular FROM especies_arvores ORDER BY nome_popular")->fetch_all(MYSQLI_ASSOC);

// Na edição, mostra o nome do cliente (que não muda)
$nome_cliente = '';
foreach ($clientes as $c) {
    if ($c['cpf'] === $pedido['cpf_cliente']) $nome_cliente = $c['nome'] . ' — ' . $c['cpf'];
}

$titulo_pagina = $editando ? 'Editar pedido' : 'Novo pedido';
$pagina_ativa  = 'pedidos';
include('cabecalho.php');
?>

<div class="page-header-bar">
    <h2><?php echo $editando ? '✏️ Editar pedido #' . (int)$pedido['id_pedido'] : '🌱 Novo pedido'; ?></h2>
    <div class="header-actions">
        <a href="listagem_pedidos.php" class="btn-back">⬅ Voltar aos pedidos</a>
    </div>
</div>

<?php mostrar_mensagem($mensagem, $class); ?>

<?php if (!$clientes): ?>
    <div class="message error" role="alert">
        ⚠️ Cadastre ao menos um cliente antes de abrir um pedido. <a href="cadastro_cliente.php">Ir para Clientes</a>
    </div>
<?php endif; ?>

<form method="post" action="">
    <?php echo csrf_campo(); ?>
    <input type="hidden" name="id" value="<?php echo h($pedido['id_pedido']); ?>">

    <div class="form-grid">

        <div class="form-group">
            <label for="cpf_cliente">Cliente <span class="req">*</span></label>
            <?php if ($editando): ?>
                <input type="text" id="cpf_cliente" value="<?php echo h($nome_cliente); ?>" readonly>
                <small>O cliente de um pedido não pode ser trocado. Status atual: <?php echo badge_status($pedido['status']); ?></small>
            <?php else: ?>
                <select id="cpf_cliente" name="cpf_cliente" required>
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo h($c['cpf']); ?>"
                            data-cidade="<?php echo (int)$c['id_cidade']; ?>"
                            <?php echo ($pedido['cpf_cliente'] === $c['cpf']) ? 'selected' : ''; ?>>
                            <?php echo h($c['nome'] . ' — ' . $c['cpf']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="id_cidade">Cidade do serviço <span class="req">*</span></label>
            <select id="id_cidade" name="id_cidade" required>
                <option value="">Selecione uma cidade</option>
                <?php foreach ($cidades as $c): ?>
                    <option value="<?php echo (int)$c['id_cidade']; ?>"
                        <?php echo ($pedido['id_cidade'] == $c['id_cidade']) ? 'selected' : ''; ?>>
                        <?php echo h($c['nome_cidade']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="tipo_servico">Tipo de serviço <span class="req">*</span></label>
            <select id="tipo_servico" name="tipo_servico" required>
                <?php foreach (SERVICOS as $valor => $rotulo): ?>
                    <option value="<?php echo $valor; ?>" <?php echo $pedido['tipo_servico'] === $valor ? 'selected' : ''; ?>>
                        <?php echo $rotulo; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="id_especie">Espécie da árvore</label>
            <select id="id_especie" name="id_especie">
                <option value="">Sem espécie definida</option>
                <?php foreach ($especies as $e): ?>
                    <option value="<?php echo (int)$e['id_especie']; ?>"
                        <?php echo ($pedido['id_especie'] == $e['id_especie']) ? 'selected' : ''; ?>>
                        <?php echo h($e['nome_popular']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Opcional. Deixe vazio em vistorias, podas e remoções sem espécie definida.</small>
        </div>

        <div class="form-group full">
            <label for="endereco_plantio">Endereço do serviço <span class="req">*</span></label>
            <input type="text" id="endereco_plantio" name="endereco_plantio" maxlength="200"
                value="<?php echo h($pedido['endereco_plantio']); ?>"
                required placeholder="Ex: Rua Augusta, 1200 - Consolação">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary" <?php echo !$clientes ? 'disabled' : ''; ?>>
                <?php echo $editando ? '💾 Atualizar' : '➕ Abrir pedido'; ?>
            </button>
            <?php if ($editando): ?>
                <a href="listagem_pedidos.php" class="btn-back">✖ Cancelar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
    // Ao escolher o cliente, sugere a cidade dele (ainda dá para trocar)
    var selCliente = document.getElementById('cpf_cliente');
    var selCidade = document.getElementById('id_cidade');
    if (selCliente && selCliente.tagName === 'SELECT') {
        selCliente.addEventListener('change', function() {
            var op = selCliente.options[selCliente.selectedIndex];
            if (op && op.dataset.cidade) selCidade.value = op.dataset.cidade;
        });
    }
</script>

<?php include('rodape.php'); ?>
