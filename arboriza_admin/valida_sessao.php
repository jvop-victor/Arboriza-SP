<?php
/*
 * =============================================
 * ARQUIVO: valida_sessao.php
 * FUNÇÃO: Proteger páginas de usuários não logados
 * =============================================
 *
 * Incluído no topo de cada página que exige login.
 * Se não houver usuário na sessão, redireciona para o login.
 */

session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit(); // obrigatório: sem ele o PHP continuaria executando a página
}
