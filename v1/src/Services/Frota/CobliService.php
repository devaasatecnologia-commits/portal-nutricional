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
 * GET /v1/frota/cobli/saude
 *
 * Retorna um resumo operacional da integração Cobli:
 *  - Total de chamadas, sucessos e erros nas últimas 1h
 *  - Latência média (ms)
 *  - Último erro registrado
 *  - 20 erros mais recentes (para diagnóstico)
 *
 * 🔥 NOVO 2026-09-23 (Bloco 7.A.4)
 */
public function saude(Request $request, Response $response): Response
{
    try {
        // ============================================================
        // 1. Resumo das últimas 1 hora
        // ============================================================
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*)                                        AS total,
                COUNT(CASE WHEN sucesso = TRUE  THEN 1 END)     AS sucessos,
                COUNT(CASE WHEN sucesso = FALSE THEN 1 END)     AS erros,
                COALESCE(ROUND(AVG(duracao_ms)::numeric, 0), 0) AS latencia_media_ms,
                COALESCE(MAX(duracao_ms), 0)                    AS latencia_max_ms
            FROM frota_cobli_log_api
            WHERE created_at >= NOW() - INTERVAL '1 hour'
        ");
        $resumo = $stmt->fetch(\PDO::FETCH_ASSOC);

        $total    = (int)$resumo['total'];
        $sucessos = (int)$resumo['sucessos'];
        $erros    = (int)$resumo['erros'];

        $taxaSucesso = $total > 0 ? round(($sucessos / $total) * 100, 1) : 100.0;

        // ============================================================
        // 2. Último erro registrado
        // ============================================================
        $stmt = $this->pdo->query("
            SELECT endpoint, metodo, status_code, duracao_ms, erro, created_at
            FROM frota_cobli_log_api
            WHERE sucesso = FALSE
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $ultimoErro = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        // ============================================================
        // 3. 20 erros mais recentes (para diagnóstico)
        // ============================================================
        $stmt = $this->pdo->query("
            SELECT endpoint, metodo, status_code, duracao_ms, erro, created_at
            FROM frota_cobli_log_api
            WHERE sucesso = FALSE
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $errosRecentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // ============================================================
        // 4. Status geral (para colorir o badge)
        // ============================================================
        $status = 'saudavel'; // verde
        if ($taxaSucesso < 80) {
            $status = 'critico'; // vermelho
        } elseif ($taxaSucesso < 95) {
            $status = 'atencao'; // âmbar
        }

        return $this->json($response, [
            'success' => true,
            'data' => [
                'status'            => $status,
                'taxa_sucesso_1h'   => $taxaSucesso,
                'total_1h'          => $total,
                'sucessos_1h'       => $sucessos,
                'erros_1h'          => $erros,
                'latencia_media_ms' => (int)$resumo['latencia_media_ms'],
                'latencia_max_ms'   => (int)$resumo['latencia_max_ms'],
                'ultimo_erro'       => $ultimoErro,
                'erros_recentes'    => $errosRecentes,
                'timestamp'         => date('Y-m-d H:i:s'),
            ]
        ]);
    } catch (\Exception $e) {
        error_log('[Cobli-saude] Erro: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error'   => 'Erro ao carregar saúde da integração Cobli'
        ], 500);
    }
}
    /**
     * Executa a requisição HTTP contra a API da Cobli.
     */
  private function request(string $method, string $path): array
{
    if (!$this->isConfigurado()) {
        return ['success' => false, 'error' => 'Chave de API da Cobli não configurada', 'status' => 0];
    }

    $inicio = microtime(true);

    $ch = curl_init($this->baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'cobli-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro   = curl_error($ch);
    curl_close($ch);

    $duracaoMs = (int)round((microtime(true) - $inicio) * 1000);

    // ============================================================
    // Falha de rede (curl_error)
    // ============================================================
    if ($erro) {
        $this->registrarLogApi(
            $method,
            $path,
            0,
            $duracaoMs,
            false,
            'Falha de conexão: ' . $erro,
            null
        );
        return ['success' => false, 'error' => 'Falha de conexão com a Cobli: ' . $erro, 'status' => 0];
    }

    $decoded = json_decode($body, true);

    // ============================================================
    // Sucesso HTTP (2xx) — não loga payload (economia + LGPD)
    // ============================================================
    if ($status >= 200 && $status < 300) {
        $this->registrarLogApi(
            $method,
            $path,
            $status,
            $duracaoMs,
            true,
            null,
            null
        );
        return ['success' => true, 'data' => $decoded, 'status' => $status];
    }

    // ============================================================
    // Erro HTTP (4xx/5xx) — loga payload resumido
    // ============================================================
    $mensagemErro  = $decoded['message'] ?? ('Erro HTTP ' . $status . ' ao consultar a Cobli');
    $payloadResumo = $body !== false ? substr((string)$body, 0, 500) : null;

    $this->registrarLogApi(
        $method,
        $path,
        $status,
        $duracaoMs,
        false,
        $mensagemErro,
        $payloadResumo
    );

    return [
        'success' => false,
        'error'   => $mensagemErro,
        'status'  => $status
    ];
}
/**
 * Registra uma chamada à API da Cobli na tabela frota_cobli_log_api.
 *
 * 🔥 NOVO 2026-09-23 (Bloco 7.A.4)
 *    - Silencioso em caso de falha (nunca propaga erro de logging)
 *    - Guarda apenas resumo do payload em caso de erro (max 500 chars)
 *    - Mascara chaves sensíveis do endpoint (querystring de API key)
 */
private function registrarLogApi(
    string $metodo,
    string $endpoint,
    int $statusCode,
    int $duracaoMs,
    bool $sucesso,
    ?string $erro,
    ?string $payloadResumo
): void {
    try {
        // Remove querystring sensível (ex: ?api_key=xxx) do endpoint logado
        $endpointLimpo = preg_replace('/([?&](?:api_key|key|token|secret)=)[^&]*/i', '$1***', $endpoint);
        $endpointLimpo = substr($endpointLimpo, 0, 255);

        $stmt = $this->pdo->prepare("
            INSERT INTO frota_cobli_log_api
                (endpoint, metodo, status_code, duracao_ms, sucesso, erro, payload_resumo, created_at)
            VALUES
                (:endpoint, :metodo, :status_code, :duracao_ms, :sucesso, :erro, :payload_resumo, NOW())
        ");
        $stmt->execute([
            'endpoint'       => $endpointLimpo,
            'metodo'         => $metodo,
            'status_code'    => $statusCode,
            'duracao_ms'     => $duracaoMs,
            'sucesso'        => $sucesso ? '1' : '0',
            'erro'           => $erro !== null ? substr($erro, 0, 1000) : null,
            'payload_resumo' => $payloadResumo,
        ]);
    } catch (\Throwable $e) {
        // Nunca propaga erro de logging — não queremos quebrar a chamada principal
        error_log('[Cobli-log] Falha ao registrar log de API: ' . $e->getMessage());
    }
}
   /**
     * Busca o ranking de condução (score por motorista ou veículo)
     * da frota na Cobli.
     *
     * Endpoint: POST /public/v1/safety/ranking
     *
     * 🔥 ALTERAÇÃO 2026-09-23 (fix do período mensal):
     *   - A assinatura continua a mesma, mas AGORA o chamador é responsável
     *     por passar `$startDate` e `$endDate` em ISO 8601 COMPLETO com offset,
     *     ex.: "2026-09-01T00:00:00-03:00".
     *   - O `CobliController` monta esse período a partir de (mes, ano) ou (dias).
     *   - Nada mais mudou. O endpoint, headers e payload continuam idênticos.
     *
     * @param string $startDate ISO 8601 (ex: "2026-09-01T00:00:00-03:00")
     * @param string $endDate   ISO 8601 (ex: "2026-09-22T23:59:59-03:00")
     * @param string $aggregationType 'DRIVER' | 'VEHICLE'
     * @param array  $filtros   ['driver_ids' => [], 'vehicle_ids' => [], 'size' => 100, ...]
     * @param string $timezone  Default America/Sao_Paulo
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

        $inicio = microtime(true);

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

        $duracaoMs = (int)round((microtime(true) - $inicio) * 1000);

        if ($erro) {
            $this->registrarLogApi(
                'POST',
                '/public/v1/safety/ranking',
                0,
                $duracaoMs,
                false,
                'Falha de conexão: ' . $erro,
                null
            );
            return ['success' => false, 'error' => 'Falha de conexão com a Cobli: ' . $erro, 'status' => 0];
        }

        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $mensagemErro = $decoded['message'] ?? ('Erro HTTP ' . $status . ' ao consultar ranking de condução');
            $payloadResumo = $body !== false ? substr((string)$body, 0, 500) : null;

            $this->registrarLogApi(
                'POST',
                '/public/v1/safety/ranking',
                $status,
                $duracaoMs,
                false,
                $mensagemErro,
                $payloadResumo
            );

            return [
                'success' => false,
                'error'   => $mensagemErro,
                'status'  => $status
            ];
        }

        $this->registrarLogApi(
            'POST',
            '/public/v1/safety/ranking',
            $status,
            $duracaoMs,
            true,
            null,
            null
        );

        return ['success' => true, 'data' => $decoded['data'] ?? $decoded, 'status' => $status];
    }


    /**
     * Lista os motoristas cadastrados na Cobli.
     *
     * Endpoint: GET /public/v1/drivers
     *
     * Cada item retornado tem:
     *   - id (UUID — usado como cobli_driver_id)
     *   - name
     *   - cpf (só dígitos, sem pontuação)
     *   - phone_numbers (array)
     *   - active (bool)
     *   - driver_code
     *   - license { number, category, acquired_at, expire_at }
     *
     * Faz paginação automática até esgotar (limit=100 por página).
     *
     * Retorna:
     *   [
     *     'success' => true,
     *     'data' => [ {...}, {...}, ... ],   // array achatado
     *     'status' => 200
     *   ]
     *
     * 🔥 NOVO 2026-09-22 (Bloco 6.5-fix)
     */
    public function listarMotoristas(int $pageSize = 100): array
    {
        $todos = [];
        $page = 1;
        $maxPaginas = 20; // trava de segurança

        do {
            $resultado = $this->request(
                'GET',
                "/public/v1/drivers?limit={$pageSize}&page={$page}"
            );

            if (!$resultado['success']) {
                return $resultado; // propaga o erro
            }

            $dadosCru = $resultado['data'] ?? [];
            $paginaAtual = $dadosCru['data'] ?? [];
            if (!is_array($paginaAtual)) {
                $paginaAtual = [];
            }

            $todos = array_merge($todos, $paginaAtual);

            $temProxima = !empty($dadosCru['pagination']['next']);
            $page++;
        } while ($temProxima && $page <= $maxPaginas);

        return [
            'success' => true,
            'data'    => $todos,
            'status'  => 200
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
