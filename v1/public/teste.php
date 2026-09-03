<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../bootstrap/app.php';

try {
    $auth = new \Nutricional\Controllers\AuthController();
    
    // Simula uma requisição de login
    $request = (object)['getBody' => function() {
        return json_encode(['login' => 'alan', 'senha' => 's3nh4p4dr40']);
    }];
    
    $response = new \Slim\Psr7\Response();
    
    // Chama o método login (você precisará adaptar)
    // $result = $auth->login($request, $response);
    
    echo json_encode(['status' => 'Controller carregado', 'success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}