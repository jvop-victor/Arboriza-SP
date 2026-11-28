<?php
/*
 * =============================================
 * ARQUIVO: index.php
 * FUNÇÃO: Página inicial do painel
 * =============================================
 *
 * Primeira página após o login. Mostra:
 *   - quantos pedidos há em cada etapa do fluxo
 *   - os pedidos que esperam atendimento há mais tempo
 *   - o menu com os módulos do sistema
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

// Quantidade de pedidos por status (os que não existirem ficam com 0)
$contagem = array_fill_keys(array_keys(STATUS), 0);
$res = $conn->query("SELECT status, COUNT(*) AS total FROM pedidos GROUP BY status");
while ($l = $res->fetch_assoc()) {
    $contagem[$l['status']] = (int)$l['total'];
}

// Os 5 pedidos requisitados mais antigos (usa a view do banco)
$fila = $conn->query("SELECT * FROM vw_pedidos_requisitados
                      ORDER BY data_pedido, hora_pedido LIMIT 5");

$titulo_pagina = 'Início';
$pagina_ativa  = 'inicio';
include('cabecalho.php');
?>

<div class="page-header">
    <h1>Olá, <?php echo h($_SESSION['usuario']); ?></h1>
    <p>Acompanhe o andamento dos pedidos de arborização.</p>
</div>

<?php mostrar_mensagem(); ?>

<!-- Fluxo dos pedidos: requisitado → em andamento → finalizado -->
<nav class="pipeline" aria-label="Pedidos por etapa">
    <a class="stage stage-requisitado" href="listagem_pedidos.php?status=requisitado">
        <span class="stage-number"><?php echo $contagem['requisitado']; ?></span>
        <span class="stage-name">Requisitados</span>
        <span class="stage-desc">Aguardando início</span>
    </a>
    <a class="stage stage-em_andamento" href="listagem_pedidos.php?status=em_andamento">
        <span class="stage-number"><?php echo $contagem['em_andamento']; ?></span>
        <span class="stage-name">Em andamento</span>
        <span class="stage-desc">Equipe em campo</span>
    </a>
    <a class="stage stage-finalizado" href="listagem_pedidos.php?status=finalizado">
        <span class="stage-number"><?php echo $contagem['finalizado']; ?></span>
        <span class="stage-name">Finalizados</span>
        <span class="stage-desc">Serviço concluído</span>
    </a>
</nav>
<p class="pipeline-note">
    <?php echo $contagem['cancelado']; ?> pedido(s) cancelado(s) ·
    <a href="listagem_pedidos.php?status=cancelado">ver cancelados</a>
</p>

<!-- Fila de espera -->
<h3>Esperando atendimento há mais tempo</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Aberto em</th>
                <th>Serviço</th>
                <th>Endereço</th>
                <th>Cliente</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($fila->num_rows > 0): ?>
                <?php while ($p = $fila->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo (int)$p['id_pedido']; ?></td>
                        <td><?php echo data_br($p['data_pedido']) . ' ' . h(substr($p['hora_pedido'], 0, 5)); ?></td>
                        <td><?php echo h(SERVICOS[$p['tipo_servico']] ?? $p['tipo_servico']); ?></td>
                        <td>
                            <?php echo h($p['endereco_plantio']); ?>
                            <small><?php echo h($p['nome_cidade']); ?></small>
                        </td>
                        <td><?php echo h($p['cliente_nome']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-cell">Nenhum pedido aguardando. <a href="cadastro_pedido.php">Abrir um novo pedido</a></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Módulos -->
<h3>Módulos</h3>
<div class="menu-grid">

    <div class="menu-card">
        <a href="cadastro_pedido.php">
            <div class="menu-card-icon">🌱</div>
            <div class="menu-card-title">Novo pedido</div>
            <div class="menu-card-desc">Plantio, poda, remoção ou vistoria</div>
        </a>
    </div>

    <div class="menu-card">
        <a href="listagem_pedidos.php">
            <div class="menu-card-icon">📋</div>
            <div class="menu-card-title">Painel de pedidos</div>
            <div class="menu-card-desc">Filtre e avance cada pedido no fluxo</div>
        </a>
    </div>

    <div class="menu-card">
        <a href="cadastro_cliente.php">
            <div class="menu-card-icon">👥</div>
            <div class="menu-card-title">Clientes</div>
            <div class="menu-card-desc">Cadastre e edite solicitantes</div>
        </a>
    </div>

    <div class="menu-card">
        <a href="cadastro_cidade.php">
            <div class="menu-card-icon">🏙️</div>
            <div class="menu-card-title">Cidades</div>
            <div class="menu-card-desc">Cidades e regiões atendidas</div>
        </a>
    </div>

    <div class="menu-card">
        <a href="cadastro_especie.php">
            <div class="menu-card-icon">🌳</div>
            <div class="menu-card-title">Espécies de árvores</div>
            <div class="menu-card-desc">Catálogo com foto e porte</div>
        </a>
    </div>

    <div class="menu-card">
        <a href="relatorios.php">
            <div class="menu-card-icon">📊</div>
            <div class="menu-card-title">Relatórios</div>
            <div class="menu-card-desc">Resumo por cidade e por cliente</div>
        </a>
    </div>

</div>

<?php include('rodape.php'); ?>
