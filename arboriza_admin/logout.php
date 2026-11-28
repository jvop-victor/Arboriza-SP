<?php
/*
 * =============================================
 * ARQUIVO: logout.php
 * FUNÇÃO: Encerrar a sessão do usuário
 * =============================================
 */

session_start();

// 1. Esvazia os dados da sessão
$_SESSION = array();

// 2. Apaga o cookie da sessão no navegador
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// 3. Destrói a sessão no servidor
session_destroy();

// 4. Volta para o login com aviso de saída
header('Location: login.php?logout=success');
exit();
