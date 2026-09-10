<?php

namespace Nutricional\Services\Frota;

/**
 * Cliente HTTP para a API REST da Cobli (https://api.cobli.co).
 * Autentica via header `cobli-api-key`, configurado dinamicamente
 * na tabela frota_configuracao (chave: cobli_api_key).
 */
class CobliService
{
    private $pdo;
    private $apiKey;
    private $baseUrl = 'https://api.cobli.co';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->apiKey = $this->getConfig('cobli_api_key', $_ENV['COBLI_API_KEY'] ?? '');
    }

    public function isConfigurado(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Testa a conexão com a API da Cobli listando dispositivos (1 item).
     */
    public function testarConexao(): array
    {
        if (!$this->isConfigurado()) {
            return ['success' => false, 'error' => 'Chave de API da Cobli não configurada'];
        }
        return $this->request('GET', '/public/v1/devices?limit=1&page=1');
    }

    /**
     * Busca um dispositivo (veículo + motorista + última localização) pelo deviceId da Cobli.
     */
    public function buscarDispositivo(string $deviceId): array
    {
        return $this->request('GET', '/herbie-1.1/dash/device/' . urlencode($deviceId));
    }

    /**
     * Lista todos os dispositivos da frota (paginado).
     */
    public function listarDispositivos(int $page = 1, int $pageSize = 50): array
    {
        return $this->request('GET', "/public/v1/devices?limit={$pageSize}&page={$page}");
    }

    /**
     * Eventos de risco de condução (score/comportamento) da frota, em um período.
     */
    public function eventosDeRisco(string $startDate, string $endDate, string $timezone = 'America/Sao_Paulo'): array
    {
        $query = http_build_query([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'timezone' => $timezone
        ]);
        return $this->request('GET', '/public/v1/risk-events?' . $query);
    }

    /**
     * Executa a requisição HTTP contra a API da Cobli.
     */
    private function request(string $method, string $path): array
    {
        if (!$this->isConfigurado()) {
            return ['success' => false, 'error' => 'Chave de API da Cobli não configurada', 'status' => 0];
        }

        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'cobli-api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($erro) {
            return ['success' => false, 'error' => 'Falha de conexão com a Cobli: ' . $erro, 'status' => 0];
        }

        $decoded = json_decode($body, true);

        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'data' => $decoded, 'status' => $status];
        }

        return [
            'success' => false,
            'error' => $decoded['message'] ?? ('Erro HTTP ' . $status . ' ao consultar a Cobli'),
            'status' => $status
        ];
    }

    private function getConfig($chave, $padrao = null)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT valor FROM frota_configuracao WHERE chave = :chave");
            $stmt->execute(['chave' => $chave]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? $result['valor'] : $padrao;
        } catch (\Exception $e) {
            return $padrao;
        }
    }
}
