<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

// Carrega variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Sao_Paulo');

// ======================================================================
// DEFINA TODAS AS CONSTANTES AQUI (ANTES DE QUALQUER CONTROLLER)
// ======================================================================
if (!defined('CHAVE_SECRETA')) {
    define('CHAVE_SECRETA', $_ENV['CHAVE_SECRETA'] ?? 'alansabe1234567890abcdefghijklmnopqrstuv');
}
if (!defined('TOKEN_EMAIL')) {
    define('TOKEN_EMAIL', $_ENV['TOKEN_EMAIL'] ?? 'TOKEN_PADRAO_REPRE_2026');
}
if (!defined('TOKEN_GESTORES')) {
    define('TOKEN_GESTORES', $_ENV['TOKEN_GESTORES'] ?? 'TOKEN_PADRAO_GEST_2026');
}
if (!defined('API_TOKEN')) {
    define('API_TOKEN', $_ENV['API_TOKEN'] ?? 'xoUM?va.JNG93v)@#i9FyH@B6n0}H4.yst%s8zV8M}xc+ZrFAz5:y6T07HxyYGE~');
}
if (!defined('CRON_TOKEN')) {
    define('CRON_TOKEN', $_ENV['CRON_TOKEN'] ?? 'crn_v9M3xP7kL2wQ8jN4bC1vX5nR6mY0tH9kP2sF8jD3cV7bN1mQ9rT6wK4zL0yA');
}

// Inclui a classe Uteis do sistema legado (se existir)
if (file_exists(__DIR__ . '/../../uteis.php')) {
    require_once __DIR__ . '/../../uteis.php';
}

// Função global para conexão PDO (fallback se não existir em uteis.php)
if (!function_exists('getPDO')) {
    function getPDO() {
        static $pdo = null;
        if ($pdo === null) {
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;options='-c client_encoding=utf8'",
                $_ENV['DB_HOST'],
                $_ENV['DB_PORT'] ?? '5432',
                $_ENV['DB_NAME']
            );
            try {
                $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                
                // 🔥 FORÇAR UTF-8 NA CONEXÃO (adicione estas duas linhas)
                $pdo->exec("SET client_encoding = 'UTF8'");
                $pdo->exec("SET standard_conforming_strings = on");
                
            } catch (PDOException $e) {
                error_log("PDO Error: " . $e->getMessage());
                throw $e;
            }
        }
        return $pdo;
    }
}

// Cria o App Slim
$app = AppFactory::create();

// Middleware de Body Parsing
$app->addBodyParsingMiddleware();
// ======================================================================
// ✅ MIDDLEWARE DE SEGURANÇA (HEADERS) - COLOCAR AQUI!
// ======================================================================
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    
    // Headers de segurança
    return $response
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY')
        ->withHeader('X-XSS-Protection', '1; mode=block')
        ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->withHeader('Permissions-Policy', 'geolocation=(self), microphone=(), camera=(self)');
});
// Middleware de Error Handling (captura erros e retorna JSON)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    $request,
    $exception,
    $displayErrorDetails,
    $logErrors,
    $logErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $statusCode = $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 500;
    
    $payload = json_encode([
        'error' => $exception->getMessage(),
        'code' => $statusCode,
        'file' => $_ENV['APP_ENV'] === 'development' ? $exception->getFile() : null,
        'line' => $_ENV['APP_ENV'] === 'development' ? $exception->getLine() : null,
    ]);
    
    $response->getBody()->write($payload);
    return $response
        ->withStatus($statusCode)
        ->withHeader('Content-Type', 'application/json');
});

// Middleware de CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    
    // Origens permitidas
    $allowedOrigins = [
        'https://portal.nutricionalbr.com',
        'https://api.nutricionalbr.com',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://localhost:80',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8080',
    ];
    
    $origin = $request->getHeaderLine('Origin');
    
    if (in_array($origin, $allowedOrigins) || ($_ENV['APP_ENV'] ?? 'production') === 'development') {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin ?: '*');
    }
    
    return $response
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key, X-Cron-Token')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
        ->withHeader('Access-Control-Allow-Credentials', 'true')
        ->withHeader('Access-Control-Max-Age', '86400');
});

// Middleware de Log (apenas em desenvolvimento ou para erros)
$app->add(function ($request, $handler) {
    $start = microtime(true);
    $response = $handler->handle($request);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    $statusCode = $response->getStatusCode();
    $logDir = __DIR__ . '/../../logs';
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log apenas em desenvolvimento ou para erros
    if (($_ENV['APP_ENV'] ?? 'production') === 'development' || $statusCode >= 400) {
        $log = sprintf(
            "[%s] %s %s - %d (%dms) %s\n",
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getUri()->getPath(),
            $statusCode,
            $duration,
            $statusCode >= 500 ? json_encode(['error' => 'Internal Server Error']) : ''
        );
        @file_put_contents($logDir . '/api_access.log', $log, FILE_APPEND);
    }
    
    return $response;
});

return $app;