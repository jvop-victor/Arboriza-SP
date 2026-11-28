<?php
/*
 * =============================================
 * ARQUIVO: relatorios.php
 * FUNÇÃO: Relatórios prontos, lidos das views do banco
 * =============================================
 *
 * As três views criadas no script do banco alimentam esta página:
 *   vw_resumo_regioes        → pedidos por cidade
 *   vw_pedidos_requisitados  → fila de pedidos abertos
 *   vw_clientes_regioes      → clientes e suas requisições
 *
 * Só leitura: aqui nada é alterado.
 */
include('valida_sessao.php');
include('conexao.php');
include('funcoes.php');

$resumo = $conn->query("SELECT * FROM vw_resumo_regioes ORDER BY total_pedidos DESC, nome_cidade");
$fila   = $conn->query("SELECT * FROM vw_pedidos_requisitados ORDER BY data_pedido, hora_pedido");
$quem   = $conn->query("SELECT * FROM vw_clientes_regioes ORDER BY nome");

$titulo_pagina = 'Relatórios';
$pagina_ativa  = 'relatorios';
include('cabecalho.php');
?>

<div class="page-header-bar">
    <h2>📊 Relatórios</h2>
    <div class="header-actions">
        <a href="index.php" class="btn-back">⬅ Voltar ao início</a>
    </div>
</div>

<!-- 1. Pedidos por cidade -->
<h3>Pedidos por cidade</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Cidade</th>
                <th>Região</th>
                <th>Pedidos</th>
                <th>Finalizados</th>
                <th>Taxa de conclusão</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resumo->num_rows > 0): ?>
                <?php while ($r = $resumo->fetch_assoc()):
                    $total = (int)$r['total_pedidos'];
                    $fin   = (int)$r['total_finalizados'];
                    $pct   = $total > 0 ? round($fin / $total * 100) : 0;
                ?>
                    <tr>
                        <td><?php echo h($r['nome_cidade']); ?></td>
                        <td><?php echo h($r['regiao_metropolitana']); ?></td>
                        <td><?php echo $total; ?></td>
                        <td><?php echo $fin; ?></td>
                        <td>
                            <?php if ($total > 0): ?>
                                <div class="meter" role="img" aria-label="<?php echo $pct; ?> por cento concluído">
                                    <div class="meter-bar" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                                <small><?php echo $pct; ?>%</small>
                            <?php else: ?>
                                <span class="text-muted">Sem pedidos</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-cell">Nenhuma cidade cadastrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 2. Pedidos aguardando -->
<h3>Pedidos aguardando início</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Aberto em</th>
                <th>Serviço</th>
                <th>Espécie</th>
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
                        <td><?php echo $p['especie'] ? h($p['especie']) : '—'; ?></td>
                        <td>
                            <?php echo h($p['endereco_plantio']); ?>
                            <small><?php echo h($p['nome_cidade']); ?></small>
                        </td>
                        <td>
                            <?php echo h($p['cliente_nome']); ?>
                            <small><?php echo h($p['cliente_cpf']); ?></small>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="empty-cell">Nenhum pedido aguardando início.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 3. Clientes -->
<h3>Clientes e requisições</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Cidade</th>
                <th>Requisitadas</th>
                <th>Em andamento</th>
                <th>Finalizadas</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($quem->num_rows > 0): ?>
                <?php while ($c = $quem->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo h($c['nome']); ?>
                            <small><?php echo h($c['cpf']) . ' · ' . (int)$c['idade'] . ' anos'; ?></small>
                        </td>
                        <td><?php echo h($c['nome_cidade']); ?></td>
                        <td><?php echo (int)$c['qtd_requisicoes_ativas']; ?></td>
                        <td><?php echo (int)$c['qtd_requisicoes_andamento']; ?></td>
                        <td><?php echo (int)$c['qtd_requisicoes_feitas']; ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-cell">Nenhum cliente cadastrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include('rodape.php'); ?>
