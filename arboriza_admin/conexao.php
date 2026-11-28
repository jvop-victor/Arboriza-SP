<?php
/*
 * =============================================
 * ARQUIVO: conexao.php
 * FUNÇÃO: Conectar o PHP ao banco de dados MySQL
 * =============================================
 *
 * Incluído em todas as páginas que acessam o banco.
 * Cria a variável $conn usada nas consultas SQL.
 */

// --- Configurações do banco de dados ---
// XAMPP deste projeto: porta 3307, usuário root, sem senha.
// Use 127.0.0.1 (e não "localhost") para o PHP respeitar a porta.
$servername = "127.0.0.1";
$username   = "root";
$password   = "";
$dbname     = "arborizacao_sp";
$port       = 3307;

// --- Modo de erros do mysqli ---
// Qualquer erro de SQL vira uma exceção (mysqli_sql_exception) que
// capturamos com try/catch. Isso também faz as mensagens das procedures
// (SIGNAL SQLSTATE '45000') chegarem até a tela.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn          = null;
$erro_conexao  = null;

try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    $conn->set_charset("utf8mb4"); // acentos, emojis e caracteres especiais
} catch (mysqli_sql_exception $e) {
    $conn         = null;
    $erro_conexao = $e->getMessage();
}

// Falhou a conexão? Na maioria das páginas não há o que fazer sem banco,
// então paramos aqui. A tela de login define PERMITE_SEM_BANCO para ainda
// poder mostrar o formulário junto com o aviso de erro.
if ($conn === null && !defined('PERMITE_SEM_BANCO')) {
    // Em produção, nunca exiba detalhes técnicos para o usuário!
    die("Falha na conexão com o banco: " . htmlspecialchars($erro_conexao));
}

// --- Pasta de imagens (fotos das espécies) ---
if (!file_exists(__DIR__ . "/img")) {
    mkdir(__DIR__ . "/img", 0775, true);
}
