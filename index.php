<?php
/**
 * GATEWAY HÍBRIDO NUTRICIONAL
 * 
 * - /portal/* → Arquivos estáticos do novo portal
 * - /v1/*     → Nova API REST (Slim)
 * - /ping     → Ping público da nova API
 * - /auth/*   → Rotas de autenticação da nova API
 * - Outras    → Sistema Legado (index_legado.php)
 */

// Carregar autoload do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
use Dotenv\Dotenv;
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Configurar timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Sao_Paulo');

// Configurar error reporting baseado no ambiente
$environment = $_ENV['APP_ENV'] ?? 'production';
if ($environment === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$routePath = $_GET['api_route'] ?? (preg_replace('#^/API(?=/|$)#', '', $path) ?: '/');
if (isset($_GET['api_route'])) {
    $_SERVER['REQUEST_URI'] = $routePath;
}

// ------------------------------------------------------------
// 1. SERVE ARQUIVOS ESTÁTICOS DO NOVO PORTAL (/portal/*)
// ------------------------------------------------------------
if (strpos($routePath, '/portal/') === 0) {
    $relativePath = substr($routePath, 8);
    $relativePath = ltrim($relativePath, '/');
    $file = __DIR__ . '/portal/' . $relativePath;

    if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'json'  => 'application/json',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
}

// ------------------------------------------------------------
// 2. ENCAMINHA PARA A NOVA API (Slim)
//    - /v1/*  → API REST protegida
//    - /ping  → Ping público
//    - /auth  → Autenticação
// ------------------------------------------------------------
if (strpos($routePath, '/v1/') === 0 ||
    $routePath === '/ping' ||
    $routePath === '/auth/login' ||
    strpos($routePath, '/auth/') === 0 ||
    $routePath === '/swagger' ||
    $routePath === '/swagger-ui') {
    
    // Definir constantes para a API
    define('BASE_PATH', __DIR__);
    define('APP_ENV', $environment);
    
    require_once __DIR__ . '/v1/public/index.php';
    exit;
}

// ------------------------------------------------------------
// 3. ROTAS DO PORTAL (Front-end)
// ------------------------------------------------------------
if ($routePath === '/' || $routePath === '/portal' || $routePath === '/portal/') {
    require_once __DIR__ . '/portal/index.php';
    exit;
}

if ($routePath === '/login' || $routePath === '/portal/login') {
    require_once __DIR__ . '/portal/login.php';
    exit;
}

// ------------------------------------------------------------
// 4. SISTEMA LEGADO (TODAS AS OUTRAS REQUISIÇÕES)
// ------------------------------------------------------------
require_once __DIR__ . '/index_legado.php';