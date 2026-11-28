<?php
/*
 * =============================================
 * ARQUIVO: login.php
 * FUNÇÃO: Autenticar o usuário no painel
 * =============================================
 *
 * Fluxo:
 * 1. Já logado → vai para index.php
 * 2. Enviou o formulário (POST) → valida usuário e senha
 * 3. Login ok → salva a sessão e vai para index.php
 * 4. Login falhou → mostra mensagem de erro
 */

session_start();

if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// Mesmo que o banco esteja fora do ar, a tela de login deve aparecer
// (com o formulário) e avisar o problema, em vez de ficar em branco.
define('PERMITE_SEM_BANCO', true);
include('conexao.php');
include('funcoes.php'); // define h() e outras funções usadas na página

$mensagem      = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if ($conn === null) {
        $mensagem      = "Não foi possível conectar ao banco de dados. Confira o conexao.php (porta, usuário e senha) e se o MySQL está ligado.";
        $tipo_mensagem = "error";
    } elseif ($usuario === '' || $senha === '') {
        $mensagem      = "Preencha usuário e senha.";
        $tipo_mensagem = "error";
    } else {
        // Prepared statement: o '?' é preenchido com segurança (evita SQL Injection)
        $stmt = $conn->prepare("SELECT id, usuario, senha FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Mesma mensagem para "usuário não existe" e "senha errada":
        // assim ninguém descobre quais usuários existem.
        if ($user && password_verify($senha, $user['senha'])) {
            session_regenerate_id(true); // novo ID de sessão após o login
            $_SESSION['usuario']    = $user['usuario'];
            $_SESSION['usuario_id'] = $user['id'];
            header('Location: index.php');
            exit();
        }

        $mensagem      = "Usuário ou senha incorretos.";
        $tipo_mensagem = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Arboriza SP</title>
    <link rel="icon" type="image/png" href="assets/logo-peq.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">

    <div class="login-card">

        <div class="login-header">
            <img src="assets/logo-principal.png" alt="Arboriza SP — Serviços de Poda e Arborização" class="login-logo">
            <h1>Painel administrativo</h1>
            <p>Entre com suas credenciais</p>
        </div>

        <?php if ($conn === null && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="message error" role="alert">
                ⚠️ Banco de dados indisponível: <?php echo h($erro_conexao); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem): ?>
            <div class="message <?php echo h($tipo_mensagem); ?>" role="alert">
                ⚠️ <?php echo h($mensagem); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
            <div class="message success" role="status">✅ Você saiu do painel.</div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text"
                    id="usuario"
                    name="usuario"
                    value="<?php echo h($_POST['usuario'] ?? ''); ?>"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Seu usuário">
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password"
                    id="senha"
                    name="senha"
                    required
                    autocomplete="current-password"
                    placeholder="Sua senha">
            </div>

            <button type="submit" class="btn-primary">Entrar</button>
        </form>

        <!-- Dica só para desenvolvimento: remova antes de publicar o site -->
        <div class="login-hint">
            <strong>Credenciais de teste:</strong><br>
            Usuário: <code>admin</code> &nbsp;·&nbsp; Senha: <code>123</code>
        </div>

    </div>

</body>

</html>
