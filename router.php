<?php
// Roteador para API e Portal

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// O servidor embutido nao aplica .htaccess: bloqueie arquivos privados aqui.
if (preg_match('#(^|/)(\.env|\.git|logs|erros_log|backup_limpeza_20260603|temp|cache)(/|$)#i', $path)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Recurso privado']);
    return true;
}

// Se for requisi��o da API, encaminha para o index.php da API
if (strpos($path, '/v1/') === 0 || strpos($path, '/v2/') === 0 || $path === '/ping' || strpos($path, '/auth/') === 0) {
    require __DIR__ . '/index.php';
    return;
}

// Se for arquivo est�tico do portal, serve diretamente
$file = __DIR__ . $path;
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon'
    ];
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    readfile($file);
    return;
}

// Sen�o, executa o PHP normalmente
return false;