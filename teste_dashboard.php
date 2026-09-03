<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseUrl = 'http://localhost:8080/v1';

// Fazer login
$ch = curl_init($baseUrl . '/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user' => 'alan', 'pass' => '252686']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['token'] ?? null;
curl_close($ch);

if (!$token) {
    die("❌ Não foi possível obter token\n");
}

echo "✅ Token obtido\n\n";

// Testar endpoint
$ch = curl_init($baseUrl . '/marketing/dashboard');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: " . $response . "\n";

if ($error) {
    echo "CURL Error: {$error}\n";
}