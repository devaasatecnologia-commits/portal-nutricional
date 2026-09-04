<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap/app.php';

try {
    $auth = new \Nutricional\Controllers\AuthController();
    
    echo json_encode(['status' => 'Controller carregado', 'success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Falha ao carregar o ambiente']);
}