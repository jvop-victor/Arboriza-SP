<?php
/*
 * =============================================
 * ARQUIVO: funcoes.php
 * FUNÇÃO: Funções usadas por várias páginas
 * =============================================
 *
 * No Sistema Musical a função de imagem era copiada em cada arquivo.
 * Aqui ela (e as outras) ficam num lugar só: se precisar corrigir algo,
 * corrige uma vez e vale para o sistema inteiro.
 *
 * Inclua DEPOIS de valida_sessao.php (precisa da sessão ativa).
 */

// -----------------------------------------------
// LISTAS FIXAS — espelham os ENUMs do banco
// -----------------------------------------------
const STATUS = [
    'requisitado'  => 'Requisitado',
    'em_andamento' => 'Em andamento',
    'finalizado'   => 'Finalizado',
    'cancelado'    => 'Cancelado',
];

const SERVICOS = [
    'plantio'  => 'Plantio',
    'poda'     => 'Poda',
    'remocao'  => 'Remoção',
    'vistoria' => 'Vistoria',
];

const PORTES = [
    'pequeno' => 'Pequeno',
    'medio'   => 'Médio',
    'grande'  => 'Grande',
];

// true  = confere os dígitos verificadores do CPF (uso real)
// false = só exige 11 dígitos (útil para testar com 111.111.111-11)
const VALIDAR_CPF_REAL = true;


// -----------------------------------------------
// ESCAPE DE HTML (proteção contra XSS)
// -----------------------------------------------
function h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}


// -----------------------------------------------
// CSRF: garante que o POST veio do nosso formulário
// -----------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valido(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}


// -----------------------------------------------
// MENSAGENS
// -----------------------------------------------
// Depois de salvar/excluir com sucesso, a página redireciona (para que
// F5 não repita a operação) e a mensagem viaja pela sessão ("flash").
function redirecionar(string $url, ?string $mensagem = null, string $tipo = 'success'): void
{
    if ($mensagem !== null) {
        $_SESSION['flash'] = ['msg' => $mensagem, 'tipo' => $tipo];
    }
    header('Location: ' . $url);
    exit();
}

// Mostra a mensagem recebida; se não houver, mostra a que veio do redirecionamento.
function mostrar_mensagem(?string $mensagem = null, ?string $tipo = null): void
{
    if ($mensagem === null && !empty($_SESSION['flash'])) {
        $mensagem = $_SESSION['flash']['msg'];
        $tipo     = $_SESSION['flash']['tipo'];
    }
    unset($_SESSION['flash']);

    if ($mensagem === null) return;

    $ok = ($tipo === 'success');
    echo '<div class="message ' . ($ok ? 'success' : 'error') . '" role="' . ($ok ? 'status' : 'alert') . '">'
        . ($ok ? '✅ ' : '⚠️ ') . h($mensagem) . '</div>';
}


// -----------------------------------------------
// BOTÃO QUE ENVIA POST (excluir, iniciar, finalizar, cancelar)
// -----------------------------------------------
// Ações que alteram dados NUNCA devem ser links GET: um link pode ser
// pré-carregado pelo navegador ou disparado por outro site. Por isso cada
// botão é um mini-formulário POST com o token CSRF.
// $rotulo é HTML nosso (emoji + texto), por isso não passa pelo h().
function botao_post(string $acao, array $campos, string $rotulo, string $classe, ?string $confirma = null, string $titulo = ''): string
{
    $onsubmit = $confirma
        ? ' onsubmit="return confirm(' . h(json_encode($confirma, JSON_UNESCAPED_UNICODE)) . ')"'
        : '';

    $html  = '<form method="post" action="" class="inline-form"' . $onsubmit . '>';
    $html .= csrf_campo();
    $html .= '<input type="hidden" name="acao" value="' . h($acao) . '">';
    foreach ($campos as $nome => $valor) {
        $html .= '<input type="hidden" name="' . h($nome) . '" value="' . h($valor) . '">';
    }
    $html .= '<button type="submit" class="' . h($classe) . '"'
        . ($titulo ? ' title="' . h($titulo) . '" aria-label="' . h($titulo) . '"' : '')
        . '>' . $rotulo . '</button></form>';
    return $html;
}


// -----------------------------------------------
// IMAGENS
// -----------------------------------------------
// Valida, redimensiona e salva a foto em img/.
// Retorna [caminho, erro]: se deu certo, erro é null; se falhou, caminho é null.
function salvar_imagem(array $arquivo, int $lado_max = 400): array
{
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Não foi possível enviar a imagem (erro ' . $arquivo['error'] . ').'];
    }
    if ($arquivo['size'] > 5000000) {
        return [null, 'Imagem muito grande. Máximo: 5 MB.'];
    }

    // O tipo é descoberto pelo CONTEÚDO do arquivo, não pelo nome
    // (o nome pode ser forjado: "virus.php.png").
    $info = @getimagesize($arquivo['tmp_name']);
    if (!$info) {
        return [null, 'O arquivo enviado não é uma imagem válida.'];
    }
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (!isset($tipos[$info[2]])) {
        return [null, 'Apenas JPG e PNG são aceitos.'];
    }
    $ext = $tipos[$info[2]];

    $origem = $ext === 'png'
        ? @imagecreatefrompng($arquivo['tmp_name'])
        : @imagecreatefromjpeg($arquivo['tmp_name']);
    if (!$origem) {
        return [null, 'Não foi possível ler a imagem. Tente outro arquivo.'];
    }

    [$w0, $h0] = $info;
    $escala = min(1, $lado_max / max($w0, $h0)); // só reduz, nunca amplia
    $nw     = max(1, (int)round($w0 * $escala));
    $nh     = max(1, (int)round($h0 * $escala));

    $nova = imagecreatetruecolor($nw, $nh);
    if ($ext === 'png') { // mantém a transparência do PNG
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
    }
    imagecopyresampled($nova, $origem, 0, 0, 0, 0, $nw, $nh, $w0, $h0);

    $nome    = bin2hex(random_bytes(8)) . '.' . $ext;
    $destino = __DIR__ . '/img/' . $nome;
    $ok = ($ext === 'png') ? imagepng($nova, $destino) : imagejpeg($nova, $destino, 88);

    imagedestroy($origem);
    imagedestroy($nova);

    return $ok ? ['img/' . $nome, null] : [null, 'Não foi possível gravar a imagem no servidor.'];
}

// Apaga o arquivo físico. basename() impede caminhos como "../../algo".
function remover_imagem(?string $caminho): void
{
    if (!$caminho) return;
    $arquivo = __DIR__ . '/img/' . basename($caminho);
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}


// -----------------------------------------------
// CPF
// -----------------------------------------------
function cpf_digitos(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf);
}

// 52998224725 → 529.982.247-25 (formato guardado no banco: CHAR(14))
function cpf_formatar(string $digitos): string
{
    return substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.'
        . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2);
}

function cpf_valido(string $cpf): bool
{
    $d = cpf_digitos($cpf);
    if (strlen($d) !== 11) return false;
    if (!VALIDAR_CPF_REAL) return true;
    if (preg_match('/^(\d)\1{10}$/', $d)) return false; // 111.111.111-11 etc.

    // Dois dígitos verificadores: pesos 10..2 para o 1º e 11..2 para o 2º
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$d[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int)$d[$t] !== $digito) return false;
    }
    return true;
}


// -----------------------------------------------
// FORMATAÇÃO PARA EXIBIÇÃO
// -----------------------------------------------
function data_br(?string $data): string
{
    return $data ? date('d/m/Y', strtotime($data)) : '—';
}

function datahora_br(?string $data): string
{
    return $data ? date('d/m/Y H:i', strtotime($data)) : '—';
}

function badge_status(string $status): string
{
    return '<span class="badge badge-' . h($status) . '">' . h(STATUS[$status] ?? $status) . '</span>';
}

// Link do Google Maps com o endereço (faz o papel do botão "Assistir" do YouTube)
function url_mapa(string $endereco, string $cidade): string
{
    return 'https://www.google.com/maps/search/?api=1&query='
        . rawurlencode($endereco . ', ' . $cidade . ', SP, Brasil');
}
