<?php
// Roteador para API e Portal

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Se for requisição da API, encaminha para o index.php da API
if (strpos($path, '/v1/') === 0 || $path === '/ping' || strpos($path, '/auth/') === 0) {
    require __DIR__ . '/v1/public/index.php';
    return;
}

// Se for arquivo estático do portal, serve diretamente
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

// Senão, executa o PHP normalmente
return false;