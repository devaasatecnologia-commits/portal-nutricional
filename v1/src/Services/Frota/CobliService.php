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
     * Lista os veículos cadastrados na Cobli (com placa, marca, modelo, ano e device_id).
     * Diferente de listarDispositivos(): aqui vem a placa (license_plate) pronta,
     * permitindo casar automaticamente com a placa cadastrada no sistema.
     */
    public function listarVeiculos(int $page = 1, int $pageSize = 2000): array
    {
        return $this->request('GET', "/public/v1/vehicles?limit={$pageSize}&page={$page}");
    }

    /**
     * Busca o odômetro atual do veículo em quilômetros.
     * A Cobli atualiza esse valor quando uma viagem é encerrada.
     */
    public function buscarOdometro(string $vehicleId): array
    {
        return $this->request(
            'GET',
            '/public/v1/vehicles/' . urlencode($vehicleId) . '/odometer?timezone=America%2FSao_Paulo'
        );
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

        /**
     * Busca o ranking de condução (score por motorista ou veículo)
     * da frota na Cobli.
     *
     * Endpoint: POST /public/v1/safety/ranking
     *
     * Retorna:
     *   [
     *     'success' => true,
     *     'data' => [
     *       'count' => 16,
     *       'last_rank_update' => '2026-09-21T20:04:26',
     *       'classified_count' => 16,
     *       'unclassified_count' => 0,
     *       'average_fleet_score' => 88,
     *       'rows' => [ ... ]
     *     ],
     *     'status' => 200
     *   ]
     *
     * @param string $startDate ISO 8601 (ex: 2026-09-01T00:00:00-03:00)
     * @param string $endDate   ISO 8601
     * @param string $aggregationType 'DRIVER' | 'VEHICLE'
     * @param array  $filtros   ['driver_ids' => [], 'vehicle_ids' => [], 'size' => 100, ...]
     * @param string $timezone  Default America/Sao_Paulo
     *
     * 🔥 NOVO 2026-09-22 (Bloco 6.1)
     */
    public function buscarRankingSeguranca(
        string $startDate,
        string $endDate,
        string $aggregationType = 'DRIVER',
        array $filtros = [],
        string $timezone = 'America/Sao_Paulo'
    ): array {
        if (!$this->isConfigurado()) {
            return ['success' => false, 'error' => 'Chave de API da Cobli não configurada', 'status' => 0];
        }

        $aggregationType = strtoupper($aggregationType);
        if (!in_array($aggregationType, ['DRIVER', 'VEHICLE'], true)) {
            return ['success' => false, 'error' => "aggregation_type inválido: {$aggregationType}", 'status' => 0];
        }

        $payload = array_merge([
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'timezone'         => $timezone,
            'aggregation_type' => $aggregationType,
            'size'             => 100,
            'page'             => 0,
            'sort_column'      => 'rank',
            'sort_order'       => 'ASC',
        ], $filtros);

        $ch = curl_init($this->baseUrl . '/public/v1/safety/ranking');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'cobli-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro   = curl_error($ch);
        curl_close($ch);

        if ($erro) {
            return ['success' => false, 'error' => 'Falha de conexão com a Cobli: ' . $erro, 'status' => 0];
        }

        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'error'   => $decoded['message'] ?? ('Erro HTTP ' . $status . ' ao consultar ranking de condução'),
                'status'  => $status
            ];
        }

        return ['success' => true, 'data' => $decoded['data'] ?? $decoded, 'status' => $status];
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
