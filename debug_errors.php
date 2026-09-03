<?php
/**
 * DEBUG - VERIFICAR ENDPOINTS COM ERRO
 */

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
    die("❌ Nao foi possivel obter token\n");
}

echo "✅ Token obtido com sucesso\n\n";

// Testar resumo-geral
$ch = curl_init($baseUrl . '/marketing/resumo-geral');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📌 resumo-geral: HTTP {$httpCode}\n";
if ($httpCode !== 200) {
    echo "   Erro: " . substr($response, 0, 500) . "\n";
}

// Testar metas-progresso
$ch = curl_init($baseUrl . '/marketing/metas-progresso');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\n📌 metas-progresso: HTTP {$httpCode}\n";
if ($httpCode !== 200) {
    echo "   Erro: " . substr($response, 0, 500) . "\n";
}

// Testar gerar-alertas
$ch = curl_init($baseUrl . '/crm/gerar-alertas');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\n📌 gerar-alertas: HTTP {$httpCode}\n";
if ($httpCode !== 200) {
    echo "   Erro: " . substr($response, 0, 500) . "\n";
}