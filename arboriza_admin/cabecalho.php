<?php
/*
 * =============================================
 * ARQUIVO: cabecalho.php
 * FUNÇÃO: Topo comum das páginas internas (head + barra de navegação)
 * =============================================
 *
 * Antes de incluir, a página define:
 *   $titulo_pagina → texto da aba do navegador
 *   $pagina_ativa  → inicio | pedidos | clientes | cidades | especies | relatorios
 *
 * Fecha com rodape.php.
 */
$titulo_pagina = $titulo_pagina ?? 'Painel';
$pagina_ativa  = $pagina_ativa  ?? '';

$menu = [
    'inicio'     => ['index.php',            'Início'],
    'pedidos'    => ['listagem_pedidos.php', 'Pedidos'],
    'clientes'   => ['cadastro_cliente.php', 'Clientes'],
    'cidades'    => ['cadastro_cidade.php',  'Cidades'],
    'especies'   => ['cadastro_especie.php', 'Espécies'],
    'relatorios' => ['relatorios.php',       'Relatórios'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($titulo_pagina); ?> — Arboriza SP</title>
    <link rel="icon" type="image/png" href="assets/logo-peq.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <header class="topbar">
        <div class="topbar-inner">
            <a href="index.php" class="brand">
                <img src="assets/logo-grande.png" alt="Arboriza SP — Poda e Arborização">
            </a>

            <nav class="mainnav" aria-label="Seções do painel">
                <?php foreach ($menu as $chave => [$link, $rotulo]): ?>
                    <a href="<?php echo $link; ?>" <?php echo $pagina_ativa === $chave ? 'aria-current="page"' : ''; ?>>
                        <?php echo $rotulo; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="topbar-user">
                <span class="user-name"><?php echo h($_SESSION['usuario']); ?></span>
                <a href="logout.php" class="btn-back">Sair</a>
            </div>
        </div>
    </header>

    <main class="container">
