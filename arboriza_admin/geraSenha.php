<?php
// Gera o hash de uma senha para guardar na tabela "usuarios".
// Rode no terminal:  php geraSenha.php
// Para outra senha:  php geraSenha.php MinhaSenha
$senha = $argv[1] ?? '123';
echo password_hash($senha, PASSWORD_BCRYPT) . PHP_EOL;
