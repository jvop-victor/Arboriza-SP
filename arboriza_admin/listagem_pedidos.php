<?php
/*
 * =============================================
 * ARQUIVO: listagem_pedidos.php
 * FUNÇÃO: Painel de pedidos — listar, filtrar e avançar no fluxo
 * =============================================
 *
 * Filtros (todos opcionais, combináveis):
 *   - Busca geral (cliente, CPF, endereço ou nº do pedido)
 *   - Cidade, status, tipo de serviço e espécie
 *
 * Ações (todas por POST):
 *   iniciar    → CALL atualizar_para_andamento(id)
 *   finalizar  → CALL atualizar_para_finalizado(id)
 *   cancelar   → CALL cancelar_pedido(id)
 *   excluir    → DELETE (somente pedidos cancelados)
 *
 * As regras do fluxo ficam nas procedures do banco: se a transição não
 * for permitida, o banco devolve a mensagem de erro e ela aparece aqui.
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

$mensagem = null;
$class    = null;

// Link de volta para esta mesma página, mantendo os filtros ativos
$url_atual = 'listagem_pedidos.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

// -----------------------------------------------
// AÇÕES: iniciar / finalizar / cancelar / excluir
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $acao = $_POST['acao'] ?? '';
    $id   = intval($_POST['id'] ?? 0);

    $procedures = [
        'iniciar'   => ['atualizar_para_andamento',  "Pedido #$id iniciado. A equipe está em andamento."],
        'finalizar' => ['atualizar_para_finalizado', "Pedido #$id finalizado!"],
        'cancelar'  => ['cancelar_pedido',           "Pedido #$id cancelado."],
    ];

    if (!csrf_valido()) {
        $mensagem = "Sessão expirada. Recarregue a página e tente de novo.";
        $class    = "error";

    } elseif (isset($procedures[$acao])) {
        try {
            [$nome_proc, $ok] = $procedures[$acao];
            $stmt = $conn->prepare("CALL $nome_proc(?)"); // nome vem da lista acima, nunca do usuário
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            redirecionar($url_atual, $ok);
        } catch (mysqli_sql_exception $e) {
            // Mensagem da procedure, ex.: "Pedido não encontrado ou não está em andamento."
            $mensagem = $e->getMessage();
            $class    = "error";
        }

    } elseif ($acao === 'excluir') {
        try {
            $stmt = $conn->prepare("DELETE FROM pedidos WHERE id_pedido = ? AND status = 'cancelado'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $apagou = $stmt->affected_rows > 0;
            $stmt->close();

            if ($apagou) {
                redirecionar($url_atual, "Pedido #$id excluído!");
            }
            $mensagem = "Só é possível excluir pedidos cancelados. Cancele o pedido primeiro.";
            $class    = "error";
        } catch (mysqli_sql_exception $e) {
            $mensagem = "Erro ao excluir: " . $e->getMessage();
            $class    = "error";
        }
    }
}

// -----------------------------------------------
// MONTAGEM DOS FILTROS DINÂMICOS
// -----------------------------------------------
// Começa com "1=1" (sempre verdadeiro) para poder concatenar ANDs
// sem verificar qual é o primeiro filtro.
$where  = "1=1";
$params = [];
$types  = "";

$f_busca   = trim($_GET['busca'] ?? '');
$f_cidade  = intval($_GET['cidade'] ?? 0);
$f_status  = $_GET['status']  ?? '';
$f_servico = $_GET['servico'] ?? '';
$f_especie = intval($_GET['especie'] ?? 0);

if ($f_busca !== '') {
    $where   .= " AND (c.nome LIKE ? OR c.cpf LIKE ? OR p.endereco_plantio LIKE ? OR p.id_pedido = ?)";
    $like     = "%" . $f_busca . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = intval(ltrim($f_busca, '#')); // permite buscar por "#12" ou "12"
    $types   .= "sssi";
}
if ($f_cidade) {
    $where   .= " AND p.id_cidade = ?";
    $params[] = $f_cidade;
    $types   .= "i";
}
if (array_key_exists($f_status, STATUS)) {
    $where   .= " AND p.status = ?";
    $params[] = $f_status;
    $types   .= "s";
}
if (array_key_exists($f_servico, SERVICOS)) {
    $where   .= " AND p.tipo_servico = ?";
    $params[] = $f_servico;
    $types   .= "s";
}
if ($f_especie) {
    $where   .= " AND p.id_especie = ?";
    $params[] = $f_especie;
    $types   .= "i";
}

$tem_filtro = ($f_busca !== '' || $f_cidade || array_key_exists($f_status, STATUS)
    || array_key_exists($f_servico, SERVICOS) || $f_especie);

// Listas dos selects de filtro
$cidades  = $conn->query("SELECT id_cidade, nome_cidade FROM regioes ORDER BY nome_cidade")->fetch_all(MYSQLI_ASSOC);
$especies = $conn->query("SELECT id_especie, nome_popular FROM especies_arvores ORDER BY nome_popular")->fetch_all(MYSQLI_ASSOC);

// -----------------------------------------------
// CONSULTA PRINCIPAL COM FILTROS
// -----------------------------------------------
$sql = "SELECT p.*, c.nome AS cliente_nome, r.nome_cidade, e.nome_popular AS especie
        FROM pedidos p
        JOIN clientes c ON c.cpf = p.cpf_cliente
        JOIN regioes  r ON r.id_cidade = p.id_cidade
        LEFT JOIN especies_arvores e ON e.id_especie = p.id_especie
        WHERE $where
        ORDER BY p.data_pedido DESC, p.hora_pedido DESC, p.id_pedido DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pedidos = $stmt->get_result();
$stmt->close();

$titulo_pagina = 'Pedidos';
$pagina_ativa  = 'pedidos';
include('cabecalho.php');
?>

<div class="page-header-bar">
    <h2>📋 Painel de pedidos</h2>
    <div class="header-actions">
        <a href="index.php" class="btn-back">⬅ Voltar ao início</a>
        <a href="cadastro_pedido.php" class="btn-primary">🌱 Novo pedido</a>
    </div>
</div>

<!-- FILTROS (method="get": ficam na URL e podem ser compartilhados) -->
<div class="filters">
    <form method="get" class="filter-form">
        <div class="filter-group">
            <div class="filter-item filter-wide">
                <label for="busca">Busca geral</label>
                <input type="text" name="busca" id="busca"
                    value="<?php echo h($f_busca); ?>"
                    placeholder="Cliente, CPF, endereço ou nº do pedido…">
            </div>
            <div class="filter-item">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">Todos</option>
                    <?php foreach (STATUS as $valor => $rotulo): ?>
                        <option value="<?php echo $valor; ?>" <?php echo $f_status === $valor ? 'selected' : ''; ?>>
                            <?php echo $rotulo; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label for="servico">Serviço</label>
                <select name="servico" id="servico">
                    <option value="">Todos</option>
                    <?php foreach (SERVICOS as $valor => $rotulo): ?>
                        <option value="<?php echo $valor; ?>" <?php echo $f_servico === $valor ? 'selected' : ''; ?>>
                            <?php echo $rotulo; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label for="cidade">Cidade</label>
                <select name="cidade" id="cidade">
                    <option value="">Todas</option>
                    <?php foreach ($cidades as $c): ?>
                        <option value="<?php echo (int)$c['id_cidade']; ?>" <?php echo $f_cidade == $c['id_cidade'] ? 'selected' : ''; ?>>
                            <?php echo h($c['nome_cidade']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label for="especie">Espécie</label>
                <select name="especie" id="especie">
                    <option value="">Todas</option>
                    <?php foreach ($especies as $e): ?>
                        <option value="<?php echo (int)$e['id_especie']; ?>" <?php echo $f_especie == $e['id_especie'] ? 'selected' : ''; ?>>
                            <?php echo h($e['nome_popular']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">🔍 Filtrar</button>
            <?php if ($tem_filtro): ?>
                <a href="listagem_pedidos.php" class="btn-back">✖ Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php mostrar_mensagem($mensagem, $class); ?>

<p class="result-count"><?php echo $pedidos->num_rows; ?> pedido(s) <?php echo $tem_filtro ? 'com esses filtros' : 'no total'; ?></p>

<!-- TABELA DE PEDIDOS -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Serviço</th>
                <th>Local</th>
                <th>Status</th>
                <th class="col-acoes">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($pedidos->num_rows > 0): ?>
                <?php while ($row = $pedidos->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?php echo (int)$row['id_pedido']; ?></strong>
                            <small><?php echo data_br($row['data_pedido']) . ' às ' . h(substr($row['hora_pedido'], 0, 5)); ?></small>
                        </td>
                        <td>
                            <?php echo h($row['cliente_nome']); ?>
                            <small><?php echo h($row['cpf_cliente']); ?></small>
                        </td>
                        <td>
                            <?php echo h(SERVICOS[$row['tipo_servico']] ?? $row['tipo_servico']); ?>
                            <small><?php echo $row['especie'] ? h($row['especie']) : 'Sem espécie definida'; ?></small>
                        </td>
                        <td>
                            <?php echo h($row['endereco_plantio']); ?>
                            <small><?php echo h($row['nome_cidade']); ?></small>
                            <!-- rel="noopener noreferrer": a nova aba não acessa esta janela -->
                            <a href="<?php echo h(url_mapa($row['endereco_plantio'], $row['nome_cidade'])); ?>"
                                target="_blank" rel="noopener noreferrer" class="btn-map">📍 Ver no mapa</a>
                        </td>
                        <td>
                            <?php echo badge_status($row['status']); ?>
                            <?php if ($row['data_inicio']): ?>
                                <small>Início: <?php echo datahora_br($row['data_inicio']); ?></small>
                            <?php endif; ?>
                            <?php if ($row['data_finalizacao']): ?>
                                <small>Fim: <?php echo datahora_br($row['data_finalizacao']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($row['status'] === 'requisitado'): ?>
                                    <?php echo botao_post('iniciar', ['id' => $row['id_pedido']], '▶ Iniciar', 'btn-advance', "Iniciar o pedido #{$row['id_pedido']}?"); ?>
                                <?php elseif ($row['status'] === 'em_andamento'): ?>
                                    <?php echo botao_post('finalizar', ['id' => $row['id_pedido']], '✔ Finalizar', 'btn-advance', "Finalizar o pedido #{$row['id_pedido']}?"); ?>
                                <?php endif; ?>

                                <?php if (in_array($row['status'], ['requisitado', 'em_andamento'])): ?>
                                    <a href="cadastro_pedido.php?edit_id=<?php echo (int)$row['id_pedido']; ?>" class="btn-edit">✏️ Editar</a>
                                    <?php echo botao_post('cancelar', ['id' => $row['id_pedido']], '✖ Cancelar', 'btn-cancel', "Cancelar o pedido #{$row['id_pedido']}? Essa ação não pode ser desfeita."); ?>
                                <?php elseif ($row['status'] === 'cancelado'): ?>
                                    <?php echo botao_post('excluir', ['id' => $row['id_pedido']], '🗑️', 'btn-delete', "Excluir o pedido #{$row['id_pedido']} definitivamente?", 'Excluir pedido'); ?>
                                <?php else: ?>
                                    <span class="text-muted">Concluído</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="empty-cell">
                        <?php echo $tem_filtro
                            ? 'Nenhum pedido encontrado com esses filtros.'
                            : 'Nenhum pedido ainda. <a href="cadastro_pedido.php">Abra o primeiro pedido</a>.'; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include('rodape.php'); ?>
