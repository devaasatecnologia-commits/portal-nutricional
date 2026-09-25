<?php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\Frota\CobliService;

/**
 * Integração com a API da Cobli (rastreamento veicular real).
 * Fase inicial: configuração da chave de API, teste de conexão,
 * vínculo de veículo <-> dispositivo Cobli e sincronização de
 * posição/eventos de risco para uso no mapa (Leaflet) e no score
 * de motoristas.
 *
 * 🔥 ALTERAÇÃO 2026-09-23 (Bloco 7.A.5 + fix do período mensal):
 *   - `rankingSeguranca` reescrito:
 *       • Aceita `?mes=9&ano=2026` (novo) OU `?dias=30` (compat).
 *       • Mês corrente: inicio=YYYY-MM-01, fim=ONTEM (P1=c.1).
 *       • Mês passado: inicio=YYYY-MM-01, fim=YYYY-MM-t (último dia).
 *       • Mês futuro: devolve vazio sem chamar a Cobli.
 *       • Fix do `entity_nome`: para veículos, pega `license_plate`.
 *       • Retorna `last_rank_update`, `classified_count`,
 *         `unclassified_count`, `average_fleet_score` no payload.
 *       • Cache indexado por (aggregation_type, entity_id, periodo_inicio,
 *         periodo_fim) — chave por mês, não acumula lixo.
 */
class CobliController
{
    private $pdo;
    private $cobli;

    /**
     * Guard de processo: garante que os DDLs rodem apenas uma vez
     * por worker PHP-FPM. Como cada worker tem seu próprio estado estático,
     * o ideal em produção é mover para migration (ver item 7.3 do token).
     */
    private static $tabelasGarantidas = false;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->cobli = new CobliService($this->pdo);

        if (!self::$tabelasGarantidas) {
            $this->garantirTabelas();
            self::$tabelasGarantidas = true;
        }
    }

    private function garantirTabelas()
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_cobli_dispositivo (
                    id SERIAL PRIMARY KEY,
                    veiculo_id INTEGER NOT NULL REFERENCES frota_veiculo(id) ON DELETE CASCADE,
                    cobli_device_id VARCHAR(80) NOT NULL,
                    cobli_vehicle_id VARCHAR(80),
                    ativo BOOLEAN NOT NULL DEFAULT TRUE,
                    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
                    UNIQUE (veiculo_id)
                )
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_cobli_posicao (
                    id SERIAL PRIMARY KEY,
                    veiculo_id INTEGER NOT NULL REFERENCES frota_veiculo(id) ON DELETE CASCADE,
                    embarque_id INTEGER,
                    latitude NUMERIC(10,7) NOT NULL,
                    longitude NUMERIC(10,7) NOT NULL,
                    velocidade NUMERIC(6,2),
                    ignicao_ligada BOOLEAN,
                    capturado_em TIMESTAMP NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT NOW()
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cobli_posicao_veiculo ON frota_cobli_posicao(veiculo_id, capturado_em DESC)");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_cobli_evento_risco (
                    id SERIAL PRIMARY KEY,
                    motorista_id INTEGER REFERENCES frota_motorista(id) ON DELETE SET NULL,
                    veiculo_id INTEGER REFERENCES frota_veiculo(id) ON DELETE SET NULL,
                    tipo_evento VARCHAR(60) NOT NULL,
                    latitude NUMERIC(10,7),
                    longitude NUMERIC(10,7),
                    ocorrido_em TIMESTAMP NOT NULL,
                    dados_brutos JSONB,
                    created_at TIMESTAMP NOT NULL DEFAULT NOW()
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cobli_evento_motorista ON frota_cobli_evento_risco(motorista_id, ocorrido_em DESC)");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_cobli_motorista (
                    id SERIAL PRIMARY KEY,
                    motorista_id INTEGER NOT NULL REFERENCES frota_motorista(id) ON DELETE CASCADE,
                    cobli_driver_id VARCHAR(80) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                    UNIQUE (motorista_id),
                    UNIQUE (cobli_driver_id)
                )
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_cobli_score_cache (
                    id SERIAL PRIMARY KEY,
                    aggregation_type VARCHAR(20) NOT NULL,
                    entity_id VARCHAR(80) NOT NULL,
                    entity_nome VARCHAR(255),
                    score NUMERIC(5,2),
                    variacao NUMERIC(5,2),
                    km_rodados NUMERIC(10,2),
                    tempo_minutos INTEGER,
                    kms_por_evento NUMERIC(10,2),
                    total_eventos INTEGER,
                    rank INTEGER,
                    periodo_inicio DATE,
                    periodo_fim DATE,
                    score_detail JSONB,
                    dados_brutos JSONB,
                    atualizado_em TIMESTAMP NOT NULL DEFAULT NOW(),
                    UNIQUE (aggregation_type, entity_id, periodo_inicio, periodo_fim)
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cobli_score_entity ON frota_cobli_score_cache(aggregation_type, entity_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cobli_score_periodo ON frota_cobli_score_cache(periodo_inicio, periodo_fim)");

            // 🔥 NOVO 2026-09-23 (Bloco 7.A.4): log de chamadas à API Cobli
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_cobli_log_api (
                    id SERIAL PRIMARY KEY,
                    endpoint VARCHAR(255) NOT NULL,
                    metodo VARCHAR(10) NOT NULL,
                    status_code INTEGER,
                    duracao_ms INTEGER,
                    sucesso BOOLEAN NOT NULL DEFAULT FALSE,
                    erro TEXT,
                    payload_resumo TEXT,
                    created_at TIMESTAMP NOT NULL DEFAULT NOW()
                )
            ");
            $this->pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_cobli_log_created
                ON frota_cobli_log_api(created_at DESC)
            ");
            $this->pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_cobli_log_sucesso
                ON frota_cobli_log_api(sucesso, created_at DESC)
            ");

        } catch (\Exception $e) {
            error_log('Erro ao garantir tabelas Cobli: ' . $e->getMessage());
        }
    }

    /**
     * GET /v1/frota/cobli/status
     * Verifica se a chave de API está configurada e testa a conexão.
     */
    public function status(Request $request, Response $response): Response
    {
        $configurado = $this->cobli->isConfigurado();
        $teste = $configurado ? $this->cobli->testarConexao() : ['success' => false, 'error' => 'Chave de API não configurada'];

        return $this->json($response, [
            'success' => true,
            'data' => [
                'configurado' => $configurado,
                'conexao_ok' => $teste['success'],
                'detalhe' => $teste['success'] ? 'Conexão com a Cobli estabelecida com sucesso' : ($teste['error'] ?? 'Falha na conexão')
            ]
        ]);
    }

    /**
     * GET /v1/frota/cobli/saude
     *
     * Retorna um resumo operacional da integração Cobli.
     *
     * 🔥 NOVO 2026-09-23 (Bloco 7.A.4)
     */
    public function saude(Request $request, Response $response): Response
    {
        try {
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

            $stmt = $this->pdo->query("
                SELECT endpoint, metodo, status_code, duracao_ms, erro, created_at
                FROM frota_cobli_log_api
                WHERE sucesso = FALSE
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $ultimoErro = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            $stmt = $this->pdo->query("
                SELECT endpoint, metodo, status_code, duracao_ms, erro, created_at
                FROM frota_cobli_log_api
                WHERE sucesso = FALSE
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $errosRecentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $status = 'saudavel';
            if ($taxaSucesso < 80) {
                $status = 'critico';
            } elseif ($taxaSucesso < 95) {
                $status = 'atencao';
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
     * POST /v1/frota/cobli/configurar
     * Salva a chave de API da Cobli (cobli-api-key).
     */
    public function configurar(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $apiKey = trim($body['api_key'] ?? '');

        if ($apiKey === '') {
            return $this->json($response, ['success' => false, 'error' => 'Informe a chave de API da Cobli'], 400);
        }

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS frota_configuracao (
                    chave VARCHAR(120) PRIMARY KEY,
                    valor TEXT,
                    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
                )
            ");
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_configuracao (chave, valor, updated_at)
                VALUES ('cobli_api_key', :valor, NOW())
                ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()
            ");
            $stmt->execute(['valor' => $apiKey]);

            $this->cobli = new CobliService($this->pdo);
            $teste = $this->cobli->testarConexao();

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'salvo' => true,
                    'conexao_ok' => $teste['success'],
                    'detalhe' => $teste['success'] ? 'Chave salva e conexão validada' : ('Chave salva, mas a conexão falhou: ' . ($teste['error'] ?? ''))
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao configurar Cobli: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao salvar configuração'], 500);
        }
    }

    /**
     * GET /v1/frota/cobli/dispositivos
     */
    public function listarDispositivos(Request $request, Response $response): Response
    {
        $resultado = $this->cobli->listarDispositivos();
        if (!$resultado['success']) {
            return $this->json($response, ['success' => false, 'error' => $resultado['error']], 502);
        }
        return $this->json($response, ['success' => true, 'data' => $resultado['data']]);
    }

    /**
     * GET /v1/frota/cobli/veiculos
     */
    public function listarVeiculosCobli(Request $request, Response $response): Response
    {
        $resultado = $this->cobli->listarVeiculos();
        if (!$resultado['success']) {
            return $this->json($response, ['success' => false, 'error' => $resultado['error']], 502);
        }
        return $this->json($response, ['success' => true, 'data' => $resultado['data']]);
    }

    /**
     * POST /v1/frota/cobli/sincronizar-frota
     */
    public function sincronizarFrota(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $dryRun = filter_var($body['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $resultado = $this->cobli->listarVeiculos();

        if (!$resultado['success']) {
            return $this->json($response, ['success' => false, 'error' => $resultado['error']], 502);
        }

        $dadosCobli = $resultado['data'] ?? [];
        $veiculosCobli = $dadosCobli['data'] ?? $dadosCobli;
        if (!is_array($veiculosCobli)) {
            return $this->json($response, ['success' => false, 'error' => 'Resposta de veículos da Cobli inválida'], 502);
        }

        try {
            $veiculosLocais = $this->pdo->query('SELECT id, placa FROM frota_veiculo')->fetchAll(\PDO::FETCH_ASSOC);
            $locaisPorPlaca = [];
            foreach ($veiculosLocais as $veiculoLocal) {
                $placa = $this->normalizarPlaca($veiculoLocal['placa']);
                if ($placa !== '') {
                    $locaisPorPlaca[$placa] = (int)$veiculoLocal['id'];
                }
            }

            $resumo = [
                'total_cobli' => count($veiculosCobli),
                'novos' => 0,
                'atualizados' => 0,
                'vinculados' => 0,
                'odometros_atualizados' => 0,
                'ignorados' => 0,
                'placas_novas' => [],
                'avisos' => []
            ];

            $odometrosPorVeiculo = [];
            if (!$dryRun) {
                foreach ($veiculosCobli as $veiculoCobli) {
                    $placa = $this->normalizarPlaca($veiculoCobli['license_plate'] ?? '');
                    $vehicleId = trim((string)($veiculoCobli['id'] ?? ''));
                    if ($placa === '' || $vehicleId === '') {
                        continue;
                    }

                    $resultadoOdometro = $this->cobli->buscarOdometro($vehicleId);
                    $dadosOdometro = $resultadoOdometro['data']['data'] ?? $resultadoOdometro['data'] ?? [];
                    if ($resultadoOdometro['success'] && is_numeric($dadosOdometro['odometer_in_km'] ?? null)) {
                        $odometrosPorVeiculo[$vehicleId] = (int)floor((float)$dadosOdometro['odometer_in_km']);
                        $resumo['odometros_atualizados']++;
                    } elseif (!$resultadoOdometro['success']) {
                        $resumo['avisos'][] = "{$placa}: odômetro não disponível na Cobli";
                    }
                }
            }

            if (!$dryRun) {
                $this->pdo->beginTransaction();
            }

            foreach ($veiculosCobli as $veiculoCobli) {
                $placa = $this->normalizarPlaca($veiculoCobli['license_plate'] ?? '');
                $deviceId = trim((string)($veiculoCobli['device_id'] ?? ''));
                $vehicleId = trim((string)($veiculoCobli['id'] ?? ''));

                if ($placa === '') {
                    $resumo['ignorados']++;
                    $resumo['avisos'][] = 'Veículo Cobli sem placa: ' . ($vehicleId ?: 'ID não informado');
                    continue;
                }

                $odometroKm = $odometrosPorVeiculo[$vehicleId] ?? null;

                $veiculoId = $locaisPorPlaca[$placa] ?? null;
                if ($veiculoId === null) {
                    $resumo['novos']++;
                    $resumo['placas_novas'][] = $placa;

                    if (!$dryRun) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO frota_veiculo
                                (placa, modelo, marca, tipo, ano, odometro_atual, status, created_at, updated_at)
                            VALUES
                                (:placa, :modelo, :marca, 'bau', :ano, COALESCE(:odometro, 0), 'indisponivel', NOW(), NOW())
                            RETURNING id
                        ");
                        $stmt->execute([
                            'placa' => $placa,
                            'modelo' => trim((string)($veiculoCobli['model'] ?? '')) ?: 'Não informado',
                            'marca' => trim((string)($veiculoCobli['brand'] ?? '')) ?: 'Não informada',
                            'ano' => !empty($veiculoCobli['year']) ? (int)$veiculoCobli['year'] : null,
                            'odometro' => $odometroKm
                        ]);
                        $veiculoId = (int)$stmt->fetchColumn();
                        $locaisPorPlaca[$placa] = $veiculoId;
                    }
                } else {
                    $resumo['atualizados']++;
                    if (!$dryRun) {
                        $stmt = $this->pdo->prepare("
                            UPDATE frota_veiculo SET
                                marca = COALESCE(NULLIF(:marca, ''), marca),
                                modelo = COALESCE(NULLIF(:modelo, ''), modelo),
                                ano = COALESCE(:ano, ano),
                                odometro_atual = CASE
                                    WHEN :tem_odometro = 1 THEN GREATEST(COALESCE(odometro_atual, 0), :odometro)
                                    ELSE odometro_atual
                                END,
                                updated_at = NOW()
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'id' => $veiculoId,
                            'marca' => trim((string)($veiculoCobli['brand'] ?? '')),
                            'modelo' => trim((string)($veiculoCobli['model'] ?? '')),
                            'ano' => !empty($veiculoCobli['year']) ? (int)$veiculoCobli['year'] : null,
                            'tem_odometro' => $odometroKm !== null ? 1 : 0,
                            'odometro' => $odometroKm ?? 0
                        ]);
                    }
                }

                if ($deviceId === '') {
                    $resumo['avisos'][] = "{$placa}: dispositivo Cobli não informado";
                    continue;
                }

                $resumo['vinculados']++;
                if (!$dryRun) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO frota_cobli_dispositivo
                            (veiculo_id, cobli_device_id, cobli_vehicle_id, ativo, updated_at)
                        VALUES (:veiculo_id, :device_id, :vehicle_id, TRUE, NOW())
                        ON CONFLICT (veiculo_id) DO UPDATE SET
                            cobli_device_id = EXCLUDED.cobli_device_id,
                            cobli_vehicle_id = EXCLUDED.cobli_vehicle_id,
                            ativo = TRUE,
                            updated_at = NOW()
                    ");
                    $stmt->execute([
                        'veiculo_id' => $veiculoId,
                        'device_id' => $deviceId,
                        'vehicle_id' => $vehicleId ?: null
                    ]);
                }
            }

            if (!$dryRun) {
                $this->pdo->commit();
            }

            return $this->json($response, [
                'success' => true,
                'dry_run' => $dryRun,
                'data' => $resumo
            ]);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erro ao sincronizar frota Cobli: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao sincronizar a frota com a Cobli'], 500);
        }
    }

    /**
     * POST /v1/frota/cobli/vincular-automatico
     */
    public function vincularAutomatico(Request $request, Response $response): Response
    {
        $resultado = $this->cobli->listarVeiculos();
        if (!$resultado['success']) {
            return $this->json($response, ['success' => false, 'error' => $resultado['error']], 502);
        }

        $veiculosCobli = $resultado['data']['data'] ?? $resultado['data'] ?? [];
        $porPlaca = [];
        foreach ($veiculosCobli as $vc) {
            $placa = $this->normalizarPlaca($vc['license_plate'] ?? '');
            if ($placa !== '') {
                $porPlaca[$placa] = $vc;
            }
        }

        $vinculados = [];
        $naoEncontrados = [];

        try {
            $stmt = $this->pdo->query("
                SELECT v.id, v.placa
                FROM frota_veiculo v
                WHERE NOT EXISTS (
                    SELECT 1 FROM frota_cobli_dispositivo d WHERE d.veiculo_id = v.id AND d.ativo = TRUE
                )
            ");
            $veiculosSemVinculo = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($veiculosSemVinculo as $veiculo) {
                $placaNormalizada = $this->normalizarPlaca($veiculo['placa']);
                if (isset($porPlaca[$placaNormalizada])) {
                    $vc = $porPlaca[$placaNormalizada];
                    $insere = $this->pdo->prepare("
                        INSERT INTO frota_cobli_dispositivo (veiculo_id, cobli_device_id, cobli_vehicle_id, ativo, updated_at)
                        VALUES (:veiculo_id, :device_id, :vehicle_id, TRUE, NOW())
                        ON CONFLICT (veiculo_id) DO UPDATE SET
                            cobli_device_id = EXCLUDED.cobli_device_id,
                            cobli_vehicle_id = EXCLUDED.cobli_vehicle_id,
                            ativo = TRUE,
                            updated_at = NOW()
                    ");
                    $insere->execute([
                        'veiculo_id' => $veiculo['id'],
                        'device_id' => $vc['device_id'] ?? '',
                        'vehicle_id' => $vc['id'] ?? null
                    ]);
                    $vinculados[] = $veiculo['placa'];
                } else {
                    $naoEncontrados[] = $veiculo['placa'];
                }
            }

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'vinculados' => $vinculados,
                    'nao_encontrados_na_cobli' => $naoEncontrados
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao vincular automaticamente veículos Cobli: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao vincular automaticamente'], 500);
        }
    }

    /**
     * GET /v1/frota/cobli/ranking-seguranca
     *
     * Aceita dois modos:
     *   - Por mês:    ?mes=9&ano=2026&tipo=DRIVER
     *   - Por dias:   ?dias=30&tipo=DRIVER        (compat com o frontend antigo)
     *
     * 🔥 ALTERAÇÃO 2026-09-23 (fix do período mensal):
     *   - Período por mês calendário:
     *       • Mês corrente: inicio=YYYY-MM-01, fim=ONTEM (P1=c.1)
     *       • Mês passado:  inicio=YYYY-MM-01, fim=YYYY-MM-t
     *       • Mês futuro:   retorna vazio sem chamar a Cobli
     *   - Fix do `entity_nome`: para veículos, pega `license_plate`.
     *   - Retorna 4 números do header da Cobli no payload.
     *   - Cache chaveado por (aggregation_type, entity_id, periodo_inicio, periodo_fim).
     */
    public function rankingSeguranca(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $tipo   = strtoupper($params['tipo'] ?? 'DRIVER');

        if (!in_array($tipo, ['DRIVER', 'VEHICLE'], true)) {
            return $this->json($response, ['success' => false, 'error' => 'tipo inválido'], 400);
        }

        // ============================================================
        // 1. Resolver período
        // ============================================================
        $periodo = $this->resolverPeriodoRanking($params);

        if ($periodo === null) {
            // Mês futuro — devolve vazio sem chamar a Cobli
            return $this->json($response, [
                'success'             => true,
                'fonte'               => 'periodo_futuro',
                'periodo'             => ['inicio' => null, 'fim' => null],
                'agrupamento'         => $tipo,
                'rows_cruas'          => 0,
                'rows_consolidadas'   => 0,
                'duplicados'          => 0,
                'inseridos'           => 0,
                'pulados'             => 0,
                'erros'               => [],
                'persistidos'         => 0,
                'average_fleet_score' => null,
                'last_rank_update'    => null,
                'classified_count'    => 0,
                'unclassified_count'  => 0,
                'data'                => [],
            ]);
        }

        $periodoInicio = $periodo['inicio'];  // 'YYYY-MM-DD'
        $periodoFim    = $periodo['fim'];     // 'YYYY-MM-DD'

        error_log('[Cobli-ranking] ===== INICIO =====');
        error_log('[Cobli-ranking] tipo=' . $tipo . ' periodo=' . $periodoInicio . ' → ' . $periodoFim . ' (modo=' . $periodo['modo'] . ')');

        // ============================================================
        // 2. Cache hit (por período exato)
        // ============================================================
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM frota_cobli_score_cache
                WHERE aggregation_type = :tipo
                  AND periodo_inicio = :inicio
                  AND periodo_fim = :fim
                  AND atualizado_em >= NOW() - INTERVAL '1 hour'
                ORDER BY rank ASC
            ");
            $stmt->execute(['tipo' => $tipo, 'inicio' => $periodoInicio, 'fim' => $periodoFim]);
            $cache = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($cache)) {
                error_log('[Cobli-ranking] cache hit: ' . count($cache));

                // Recalcula os 4 números do header a partir da cache
                $scores = array_filter(array_map(fn($r) => $r['score'] !== null ? (float)$r['score'] : null, $cache), fn($v) => $v !== null);
                $media  = count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;

                return $this->json($response, [
                    'success'             => true,
                    'fonte'               => 'cache',
                    'periodo'             => ['inicio' => $periodoInicio, 'fim' => $periodoFim],
                    'agrupamento'         => $tipo,
                    'average_fleet_score' => $media,
                    'last_rank_update'    => $cache[0]['atualizado_em'] ?? null,
                    'classified_count'    => count($cache),
                    'unclassified_count'  => 0,
                    'data'                => $this->formatarRankingSeguranca($cache)
                ]);
            }
        } catch (\Exception $e) {
            error_log('[Cobli-ranking] ERRO cache lookup: ' . $e->getMessage());
        }

        // ============================================================
        // 3. Cache miss — chamar a Cobli
        // ============================================================
        $startIso = $periodoInicio . 'T00:00:00-03:00';
        $endIso   = $periodoFim    . 'T23:59:59-03:00';

        $resultado = $this->cobli->buscarRankingSeguranca($startIso, $endIso, $tipo, ['size' => 200]);

        if (!$resultado['success']) {
            error_log('[Cobli-ranking] cobli erro: ' . ($resultado['error'] ?? 'n/a'));
            return $this->json($response, ['success' => false, 'error' => $resultado['error'] ?? 'Erro'], 502);
        }

        $dataCru = $resultado['data'] ?? [];
        $rows = $dataCru['rows'] ?? [];
        if (!is_array($rows)) $rows = [];

        error_log('[Cobli-ranking] rows cruas da Cobli: ' . count($rows));

        // ============================================================
        // 4. Consolidar duplicatas (product_type TELEMETRY > CAM)
        // ============================================================
        $consolidado = [];
        $duplicados  = 0;

        foreach ($rows as $row) {
            $entity = ($tipo === 'DRIVER') ? ($row['driver'] ?? null) : ($row['vehicle'] ?? null);
            $entityId = is_array($entity) ? ($entity['id'] ?? '') : '';
            if ($entityId === '') continue;

            if (isset($consolidado[$entityId])) {
                $duplicados++;
                $productAtual = $consolidado[$entityId]['product_type'] ?? '';
                $productNovo  = $row['product_type'] ?? '';
                if ($this->pesoProductType($productNovo) > $this->pesoProductType($productAtual)) {
                    $consolidado[$entityId] = $row;
                }
                continue;
            }

            $consolidado[$entityId] = $row;
        }

        $rowsFinal = array_values($consolidado);
        error_log('[Cobli-ranking] rows consolidadas: ' . count($rowsFinal) . " (duplicados: {$duplicados})");

        // ============================================================
        // 5. Persistir
        // ============================================================
        $inseridos = 0;
        $pulados   = 0;
        $erros     = [];

        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                DELETE FROM frota_cobli_score_cache
                WHERE aggregation_type = :tipo AND periodo_inicio = :inicio AND periodo_fim = :fim
            ")->execute(['tipo' => $tipo, 'inicio' => $periodoInicio, 'fim' => $periodoFim]);

                     $stmtIns = $this->pdo->prepare("
                INSERT INTO frota_cobli_score_cache (
                    aggregation_type, entity_id, entity_nome,
                    score, variacao, km_rodados, tempo_minutos,
                    kms_por_evento, total_eventos, rank,
                    velocidade_media,
                    periodo_inicio, periodo_fim, score_detail, dados_brutos,
                    atualizado_em
                ) VALUES (
                    :tipo, :entity_id, :entity_nome,
                    :score, :variacao, :km, :tempo,
                    :kms_por_evento, :total_eventos, :rank,
                    :velocidade_media,
                    :inicio, :fim, CAST(:score_detail AS jsonb), CAST(:dados_brutos AS jsonb),
                    NOW()
                )
            ");

            foreach ($rowsFinal as $idx => $row) {
                $entity   = ($tipo === 'DRIVER') ? ($row['driver'] ?? null) : ($row['vehicle'] ?? null);
                $entityId = is_array($entity) ? ($entity['id'] ?? '') : '';
                $entityNm = $this->extrairNomeEntidade($entity, $tipo);

                if ($entityId === '') {
                    $pulados++;
                    continue;
                }

                $scoreDetailJson = json_encode($row['score_detail'] ?? [], JSON_UNESCAPED_UNICODE);
                $dadosBrutosJson = json_encode($row, JSON_UNESCAPED_UNICODE);

                if ($scoreDetailJson === false || $dadosBrutosJson === false) {
                    $erros[] = "#{$idx} ({$entityNm}): json_encode falhou";
                    continue;
                }
                

                             // 🔥 NOVO 2026-09-24 (Bloco 7.A.5b): extrai velocidade
                $velocidade = $row['average_speed_in_kmh']
                    ?? $row['avg_speed_in_kmh']
                    ?? $row['avg_speed']
                    ?? $row['average_speed']
                    ?? null;

                try {
                    $stmtIns->execute([
                        'tipo'             => $tipo,
                        'entity_id'        => $entityId,
                        'entity_nome'      => $entityNm,
                        'score'            => isset($row['score']) ? (float)$row['score'] : null,
                        'variacao'         => isset($row['variation']) ? (float)$row['variation'] : null,
                        'km'               => isset($row['driven_distance_in_km']) ? (float)$row['driven_distance_in_km'] : null,
                        'tempo'            => isset($row['driven_time_in_minutes']) ? (int)$row['driven_time_in_minutes'] : null,
                        'kms_por_evento'   => isset($row['kms_per_event']) ? (float)$row['kms_per_event'] : null,
                        'total_eventos'    => isset($row['total_events_count']) ? (int)$row['total_events_count'] : null,
                        'rank'             => isset($row['rank']) ? (int)$row['rank'] : null,
                        'velocidade_media' => $velocidade !== null ? (float)$velocidade : null,
                        'inicio'           => $periodoInicio,
                        'fim'              => $periodoFim,
                        'score_detail'     => $scoreDetailJson,
                        'dados_brutos'     => $dadosBrutosJson
                    ]);
                    $inseridos++;
                } catch (\PDOException $pdoEx) {
                    error_log("[Cobli-ranking] PDO ERRO #{$idx}: " . $pdoEx->getMessage());
                    $erros[] = "#{$idx} ({$entityNm}): " . $pdoEx->getMessage();
                }
            }

            $this->pdo->commit();
            error_log("[Cobli-ranking] COMMIT OK. Inseridos={$inseridos}, Pulados={$pulados}, Erros=" . count($erros));

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[Cobli-ranking] ERRO GERAL INSERT: ' . $e->getMessage());
        }

        // ============================================================
        // 6. Ler de volta e devolver
        // ============================================================
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_cobli_score_cache
            WHERE aggregation_type = :tipo AND periodo_inicio = :inicio AND periodo_fim = :fim
            ORDER BY rank ASC
        ");
        $stmt->execute(['tipo' => $tipo, 'inicio' => $periodoInicio, 'fim' => $periodoFim]);
        $persistidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json($response, [
            'success'             => true,
            'fonte'               => 'cobli',
            'periodo'             => ['inicio' => $periodoInicio, 'fim' => $periodoFim],
            'agrupamento'         => $tipo,
            'rows_cruas'          => count($rows),
            'rows_consolidadas'   => count($rowsFinal),
            'duplicados'          => $duplicados,
            'inseridos'           => $inseridos,
            'pulados'             => $pulados,
            'erros'               => $erros,
            'persistidos'         => count($persistidos),
            'average_fleet_score' => $dataCru['average_fleet_score'] ?? null,
            'last_rank_update'    => $dataCru['last_rank_update']    ?? null,
            'classified_count'    => $dataCru['classified_count']    ?? null,
            'unclassified_count'  => $dataCru['unclassified_count']  ?? null,
            'data'                => $this->formatarRankingSeguranca($persistidos)
        ]);
    }

       /**
     * Resolve o período do ranking com base nos query params.
     *
     * Regras (P7 = c.2 — bate com Cobli):
     *   - `?mes=&ano=` → mês calendário
     *       • Mês corrente: inicio=YYYY-MM-01, fim=HOJE
     *       • Mês passado:  inicio=YYYY-MM-01, fim=YYYY-MM-t
     *       • Mês futuro:   retorna null (Controller devolve vazio)
     *   - `?dias=` → últimos N dias (compat com o frontend antigo)
     *
     * 🔥 ALTERAÇÃO 2026-09-24 (Bloco 7.A.5b):
     *   - Mês corrente corta em HOJE, não em ONTEM (P7 = c.2)
     *   - Bate 100% com a Cobli em tempo real, ao custo de mudar
     *     durante o dia
     *
     * @return array{inicio:string, fim:string, modo:string}|null
     */
    private function resolverPeriodoRanking(array $params): ?array
    {
        $hoje = new \DateTime('today');

        // Modo 1: por mês/ano
        if (isset($params['mes']) && isset($params['ano'])) {
            $mes = (int)$params['mes'];
            $ano = (int)$params['ano'];

            if ($mes >= 1 && $mes <= 12 && $ano >= 2000 && $ano <= 2100) {
                $inicio = new \DateTime(sprintf('%04d-%02d-01', $ano, $mes));

                // Mês futuro? Devolve null (Controller trata)
                if ($inicio > $hoje) {
                    return null;
                }

                $mesCorrente = ($inicio->format('Y-m') === $hoje->format('Y-m'));

                if ($mesCorrente) {
                    // 🔥 P7 = c.2: fim = HOJE (bate com Cobli)
                    $fim = clone $hoje;
                } else {
                    // Mês passado → último dia do mês
                    $fim = (clone $inicio)->modify('last day of this month');
                }

                return [
                    'inicio' => $inicio->format('Y-m-d'),
                    'fim'    => $fim->format('Y-m-d'),
                    'modo'   => 'mes',
                ];
            }
        }

        // Modo 2: por dias corridos (compat)
        $dias = max(1, min((int)($params['dias'] ?? 30), 90));
        return [
            'inicio' => (clone $hoje)->modify("-{$dias} days")->format('Y-m-d'),
            'fim'    => $hoje->format('Y-m-d'),
            'modo'   => 'dias',
        ];
    }

    /**
     * Extrai o nome da entidade do payload da Cobli.
     *
     * 🔥 FIX 2026-09-23: para veículos, a Cobli NÃO devolve `name`.
     *    Devolve `license_plate` (e opcionalmente `alias`).
     *    Sem esse fallback, `entity_nome` fica vazio em 100% dos veículos.
     */
    private function extrairNomeEntidade($entity, string $tipo): ?string
    {
        if (!is_array($entity)) return null;

        if ($tipo === 'DRIVER') {
            return $entity['name'] ?? null;
        }

        // VEHICLE
        return $entity['license_plate']
            ?? $entity['name']
            ?? $entity['alias']
            ?? null;
    }

    private function pesoProductType(?string $productType): int
    {
        switch (strtoupper($productType ?? '')) {
            case 'TELEMETRY': return 3;
            case 'CAM':       return 2;
            case 'CAM_PRO':   return 2;
            default:          return 1;
        }
    }
    private function formatarRankingSeguranca(array $linhas): array
    {
        return array_map(function ($row) {
            $entityId = $row['entity_id'] ?? null;
            $entityNm = $row['entity_nome'] ?? null;

            if (!$entityId && isset($row['driver']['id'])) {
                $entityId = $row['driver']['id'];
                $entityNm = $row['driver']['name'] ?? null;
            } elseif (!$entityId && isset($row['vehicle']['id'])) {
                $entityId = $row['vehicle']['id'];
                $entityNm = $row['vehicle']['license_plate'] ?? $row['vehicle']['name'] ?? null;
            }

            $detail = $row['score_detail'] ?? [];
            if (is_string($detail)) {
                $detail = json_decode($detail, true) ?: [];
            }

            // ============================================================
            // 🔥 Velocidade média (P8 = b)
            // A Cobli NÃO expõe esse campo no payload do ranking (confirmado
            // em teste 24/09). Calculamos:
            //   velocidade_media = km_rodados / (tempo_minutos / 60)
            // ============================================================
            $kmRodados    = isset($row['km_rodados'])    ? (float)$row['km_rodados']    : (isset($row['driven_distance_in_km'])     ? (float)$row['driven_distance_in_km']     : null);
            $tempoMinutos = isset($row['tempo_minutos']) ? (int)$row['tempo_minutos']   : (isset($row['driven_time_in_minutes'])    ? (int)$row['driven_time_in_minutes']      : null);

            $velocidadeMedia = null;

            // 1º tenta pegar do payload bruto (caso a Cobli passe a expor)
            if (!empty($row['dados_brutos'])) {
                $brutos = is_string($row['dados_brutos'])
                    ? json_decode($row['dados_brutos'], true) ?: []
                    : $row['dados_brutos'];

                $velocidadeMedia = $brutos['average_speed_in_kmh']
                    ?? $brutos['avg_speed_in_kmh']
                    ?? $brutos['avg_speed']
                    ?? $brutos['average_speed']
                    ?? null;

                if ($velocidadeMedia !== null) {
                    $velocidadeMedia = (float)$velocidadeMedia;
                }
            }

            // 2º fallback: calcula a partir de km/tempo
            if ($velocidadeMedia === null && $kmRodados !== null && $tempoMinutos !== null && $tempoMinutos > 0) {
                $horas = $tempoMinutos / 60;
                if ($horas > 0) {
                    $velocidadeMedia = round($kmRodados / $horas, 1);
                }
            }

            return [
                'rank'             => isset($row['rank']) ? (int)$row['rank'] : null,
                'entity_id'        => $entityId,
                'entity_nome'      => $entityNm,
                'score'            => isset($row['score'])          ? (float)$row['score']          : null,
                'variacao'         => isset($row['variacao'])       ? (float)$row['variacao']       : (isset($row['variation']) ? (float)$row['variation'] : null),
                'km_rodados'       => $kmRodados,
                'tempo_minutos'    => $tempoMinutos,
                'kms_por_evento'   => isset($row['kms_por_evento']) ? (float)$row['kms_por_evento'] : (isset($row['kms_per_event']) ? (float)$row['kms_per_event'] : null),
                'total_eventos'    => isset($row['total_eventos'])  ? (int)$row['total_eventos']    : (isset($row['total_events_count']) ? (int)$row['total_events_count'] : null),
                'velocidade_media' => $velocidadeMedia,
                'score_detail'     => $detail,
            ];
        }, $linhas);
    }
    /**
     * POST /v1/frota/cobli/vincular-motoristas-auto
     * 🔥 NOVO 2026-09-22 (Bloco 6.5-fix)
     */
    public function vincularMotoristasAuto(Request $request, Response $response): Response
    {
        try {
            $resultado = $this->cobli->listarMotoristas();

            if (!$resultado['success']) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => $resultado['error'] ?? 'Falha ao buscar motoristas na Cobli'
                ], 502);
            }

            $motoristasCobli = $resultado['data'] ?? [];
            if (!is_array($motoristasCobli)) {
                $motoristasCobli = [];
            }

            error_log('[Cobli-vincular] Total motoristas na Cobli: ' . count($motoristasCobli));

            $porCpf = [];
            foreach ($motoristasCobli as $mc) {
                $ativo = $mc['active'] ?? true;
                if (!$ativo) continue;

                $cpf = preg_replace('/\D/', '', (string)($mc['cpf'] ?? ''));
                $uuid = trim((string)($mc['id'] ?? ''));
                if ($cpf !== '' && $uuid !== '') {
                    $porCpf[$cpf] = [
                        'id'    => $uuid,
                        'nome'  => $mc['name'] ?? null,
                        'email' => null
                    ];
                }
            }

            $stmt = $this->pdo->query("
                SELECT fm.id, fm.nome, fm.cpf, fm.erp_id
                FROM frota_motorista fm
                WHERE NOT EXISTS (
                    SELECT 1 FROM frota_cobli_motorista cm
                    WHERE cm.motorista_id = fm.id
                )
                AND fm.cpf IS NOT NULL
                AND fm.cpf != ''
                AND fm.status = 'ativo'
            ");
            $motoristasLocais = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $vinculados = [];
            $naoEncontrados = [];

            $stmtIns = $this->pdo->prepare("
                INSERT INTO frota_cobli_motorista (motorista_id, cobli_driver_id)
                VALUES (:motorista_id, :cobli_driver_id)
                ON CONFLICT (motorista_id) DO NOTHING
            ");

            foreach ($motoristasLocais as $ml) {
                $cpf = preg_replace('/\D/', '', (string)$ml['cpf']);
                if ($cpf === '') continue;

                if (isset($porCpf[$cpf])) {
                    try {
                        $stmtIns->execute([
                            'motorista_id'    => (int)$ml['id'],
                            'cobli_driver_id' => $porCpf[$cpf]['id']
                        ]);
                        $vinculados[] = [
                            'motorista_id'    => (int)$ml['id'],
                            'motorista_nome'  => $ml['nome'],
                            'cobli_driver_id' => $porCpf[$cpf]['id'],
                            'cobli_nome'      => $porCpf[$cpf]['nome']
                        ];
                    } catch (\Exception $e) {
                        error_log('[Cobli-vincular] Erro ao vincular motorista ' . $ml['id'] . ': ' . $e->getMessage());
                    }
                } else {
                    $naoEncontrados[] = [
                        'motorista_id'   => (int)$ml['id'],
                        'motorista_nome' => $ml['nome'],
                        'cpf'            => substr($cpf, 0, 3) . '*****' . substr($cpf, -2)
                    ];
                }
            }

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'total_cobli'      => count($motoristasCobli),
                    'total_locais'     => count($motoristasLocais),
                    'vinculados'       => $vinculados,
                    'nao_encontrados'  => $naoEncontrados,
                    'totais' => [
                        'vinculados'      => count($vinculados),
                        'nao_encontrados' => count($naoEncontrados)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            error_log('[Cobli-vincular] Erro geral: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error'   => 'Erro ao vincular motoristas: ' . $e->getMessage()
            ], 500);
        }
    }

    private function normalizarPlaca(?string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa ?? ''));
    }

    public function listarVinculos(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->query("
            SELECT d.veiculo_id, d.cobli_device_id, d.cobli_vehicle_id, v.placa, v.modelo
            FROM frota_cobli_dispositivo d
            JOIN frota_veiculo v ON v.id = d.veiculo_id
            WHERE d.ativo = TRUE
            ORDER BY v.placa
        ");
        return $this->json($response, ['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function vincularVeiculo(Request $request, Response $response, array $args): Response
    {
        $veiculoId = (int)$args['id'];
        $body = (array)$request->getParsedBody();
        $deviceId = trim($body['cobli_device_id'] ?? '');
        $vehicleId = trim($body['cobli_vehicle_id'] ?? '');

        if ($deviceId === '') {
            return $this->json($response, ['success' => false, 'error' => 'Informe o cobli_device_id'], 400);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_cobli_dispositivo (veiculo_id, cobli_device_id, cobli_vehicle_id, ativo, updated_at)
                VALUES (:veiculo_id, :device_id, :vehicle_id, TRUE, NOW())
                ON CONFLICT (veiculo_id) DO UPDATE SET
                    cobli_device_id = EXCLUDED.cobli_device_id,
                    cobli_vehicle_id = EXCLUDED.cobli_vehicle_id,
                    ativo = TRUE,
                    updated_at = NOW()
            ");
            $stmt->execute(['veiculo_id' => $veiculoId, 'device_id' => $deviceId, 'vehicle_id' => $vehicleId ?: null]);

            return $this->json($response, ['success' => true, 'message' => 'Veículo vinculado à Cobli com sucesso']);
        } catch (\Exception $e) {
            error_log('Erro ao vincular veículo Cobli: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao vincular veículo'], 500);
        }
    }

    public function desvincularVeiculo(Request $request, Response $response, array $args): Response
    {
        $veiculoId = (int)$args['id'];
        try {
            $stmt = $this->pdo->prepare("UPDATE frota_cobli_dispositivo SET ativo = FALSE, updated_at = NOW() WHERE veiculo_id = :id");
            $stmt->execute(['id' => $veiculoId]);
            return $this->json($response, ['success' => true, 'message' => 'Vínculo removido']);
        } catch (\Exception $e) {
            error_log('Erro ao desvincular veículo Cobli: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao desvincular veículo'], 500);
        }
    }

    public function vincularMotorista(Request $request, Response $response, array $args): Response
    {
        $motoristaId = (int)$args['id'];
        $body = (array)$request->getParsedBody();
        $driverId = trim($body['cobli_driver_id'] ?? '');

        if ($driverId === '') {
            return $this->json($response, ['success' => false, 'error' => 'Informe o cobli_driver_id'], 400);
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_cobli_motorista (motorista_id, cobli_driver_id)
                VALUES (:motorista_id, :driver_id)
                ON CONFLICT (motorista_id) DO UPDATE SET cobli_driver_id = EXCLUDED.cobli_driver_id
            ");
            $stmt->execute(['motorista_id' => $motoristaId, 'driver_id' => $driverId]);

            return $this->json($response, ['success' => true, 'message' => 'Motorista vinculado à Cobli com sucesso']);
        } catch (\Exception $e) {
            error_log('Erro ao vincular motorista Cobli: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao vincular motorista'], 500);
        }
    }

    public function sincronizarVeiculoMotorista(Request $request, Response $response, array $args): Response
    {
        $veiculoId = (int)$args['id'];
        $atualizados = [];
        $avisos = [];

        try {
            $veiculo = $this->pdo->prepare("SELECT id, placa FROM frota_veiculo WHERE id = :id");
            $veiculo->execute(['id' => $veiculoId]);
            $veiculoDados = $veiculo->fetch(\PDO::FETCH_ASSOC);

            if (!$veiculoDados) {
                return $this->json($response, ['success' => false, 'error' => 'Veículo não encontrado'], 404);
            }

            $stmtMotoristaAtual = $this->pdo->prepare("
                SELECT motorista_id FROM frota_embarque
                WHERE veiculo_id = :veiculo_id AND motorista_id IS NOT NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmtMotoristaAtual->execute(['veiculo_id' => $veiculoId]);
            $veiculoDados['motorista_id'] = $stmtMotoristaAtual->fetchColumn() ?: null;

            $vinculoCobli = $this->pdo->prepare("SELECT cobli_device_id FROM frota_cobli_dispositivo WHERE veiculo_id = :id AND ativo = TRUE");
            $vinculoCobli->execute(['id' => $veiculoId]);
            $device = $vinculoCobli->fetch(\PDO::FETCH_ASSOC);

            if ($device) {
                $resultado = $this->cobli->buscarDispositivo($device['cobli_device_id']);
                if ($resultado['success']) {
                    $dados = $resultado['data'] ?? [];
                    $veiculoCobli = $dados['vehicle'] ?? [];
                    $motoristaCobli = $dados['driver'] ?? [];

                    $campos = [];
                    $params = ['id' => $veiculoId];
                    if (!empty($veiculoCobli['brand'])) { $campos[] = 'marca = :marca'; $params['marca'] = $veiculoCobli['brand']; }
                    if (!empty($veiculoCobli['model'])) { $campos[] = 'modelo = :modelo'; $params['modelo'] = $veiculoCobli['model']; }
                    if (!empty($veiculoCobli['year'])) { $campos[] = 'ano = :ano'; $params['ano'] = $veiculoCobli['year']; }

                    if ($campos) {
                        $campos[] = 'updated_at = NOW()';
                        $this->pdo->prepare("UPDATE frota_veiculo SET " . implode(', ', $campos) . " WHERE id = :id")->execute($params);
                        $atualizados[] = 'Veículo atualizado com dados da Cobli (marca/modelo/ano)';
                    }

                    if (!empty($motoristaCobli['id']) && !empty($veiculoDados['motorista_id'])) {
                        $this->pdo->prepare("
                            INSERT INTO frota_cobli_motorista (motorista_id, cobli_driver_id)
                            VALUES (:motorista_id, :driver_id)
                            ON CONFLICT (motorista_id) DO UPDATE SET cobli_driver_id = EXCLUDED.cobli_driver_id
                        ")->execute(['motorista_id' => $veiculoDados['motorista_id'], 'driver_id' => $motoristaCobli['id']]);
                        $atualizados[] = 'Vínculo motorista ↔ Cobli atualizado automaticamente pelo device';
                    }
                } else {
                    $avisos[] = 'Cobli: ' . $resultado['error'];
                }
            } else {
                $avisos[] = 'Veículo sem dispositivo Cobli vinculado — pulei a sincronização de posição/telemetria.';
            }

            if (!empty($veiculoDados['motorista_id'])) {
                $motorista = $this->pdo->prepare("SELECT id, erp_id FROM frota_motorista WHERE id = :id");
                $motorista->execute(['id' => $veiculoDados['motorista_id']]);
                $motoristaDados = $motorista->fetch(\PDO::FETCH_ASSOC);

                if ($motoristaDados && !empty($motoristaDados['erp_id'])) {
                    $stmtErp = $this->pdo->prepare("
                        SELECT fantasia, razao, cpf, fone, email, endereco
                        FROM cliforemp
                        WHERE idcliforemp = :id
                    ");
                    $stmtErp->execute(['id' => $motoristaDados['erp_id']]);
                    $erpMotorista = $stmtErp->fetch(\PDO::FETCH_ASSOC);

                    if ($erpMotorista) {
                        $this->pdo->prepare("
                            UPDATE frota_motorista SET
                                nome = COALESCE(NULLIF(:nome, ''), nome),
                                cpf = COALESCE(NULLIF(:cpf, ''), cpf),
                                telefone = COALESCE(NULLIF(:telefone, ''), telefone),
                                email = COALESCE(NULLIF(:email, ''), email),
                                endereco = COALESCE(NULLIF(:endereco, ''), endereco),
                                updated_at = NOW()
                            WHERE id = :id
                        ")->execute([
                            'id' => $motoristaDados['id'],
                            'nome' => $erpMotorista['fantasia'] ?? $erpMotorista['razao'] ?? '',
                            'cpf' => $erpMotorista['cpf'] ?? '',
                            'telefone' => $erpMotorista['fone'] ?? '',
                            'email' => $erpMotorista['email'] ?? '',
                            'endereco' => $erpMotorista['endereco'] ?? ''
                        ]);
                        $atualizados[] = 'Motorista atualizado com dados cadastrais do ERP';
                    } else {
                        $avisos[] = 'ERP: motorista erp_id ' . $motoristaDados['erp_id'] . ' não encontrado em cliforemp.';
                    }
                } else {
                    $avisos[] = 'Motorista sem erp_id vinculado — pulei a sincronização com o ERP.';
                }
            } else {
                $avisos[] = 'Veículo sem motorista vinculado no sistema.';
            }

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'atualizados' => $atualizados,
                    'avisos' => $avisos
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao sincronizar veículo/motorista (ERP+Cobli): ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao sincronizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/frota/cobli/veiculo/{id}/posicao
     *
     * 🔥 MUDANÇA 2026-09-23 (Bloco 7.A.1):
     *   - Cache de 30s por veículo, usando created_at (mesma regra do posicoesFrota)
     */
    public function posicaoVeiculo(Request $request, Response $response, array $args): Response
    {
        $veiculoId = (int)$args['id'];

        $stmt = $this->pdo->prepare("SELECT cobli_device_id FROM frota_cobli_dispositivo WHERE veiculo_id = :id AND ativo = TRUE");
        $stmt->execute(['id' => $veiculoId]);
        $vinculo = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$vinculo) {
            return $this->json($response, ['success' => false, 'error' => 'Veículo não vinculado a um dispositivo Cobli'], 404);
        }

        $cache = $this->buscarUltimaPosicaoCache($veiculoId, 30);

        if ($cache !== null) {
            return $this->json($response, [
                'success' => true,
                'fonte'   => 'cache',
                'data' => [
                    'localizacao' => [
                        'latitude'       => (float)$cache['latitude'],
                        'longitude'      => (float)$cache['longitude'],
                        'speed'          => $cache['velocidade'],
                        'ignition_on'    => $cache['ignicao_ligada'],
                        'time'           => strtotime($cache['capturado_em']),
                    ],
                ]
            ]);
        }

        $resultado = $this->cobli->buscarDispositivo($vinculo['cobli_device_id']);
        if (!$resultado['success']) {
            return $this->json($response, ['success' => false, 'error' => $resultado['error']], 502);
        }

        $dados = $resultado['data'] ?? [];
        $localizacao = $dados['last_location'] ?? $dados['lastLocation'] ?? null;

        if ($localizacao && !empty($localizacao['latitude']) && !empty($localizacao['longitude'])) {
            try {
                $stmtIns = $this->pdo->prepare("
                    INSERT INTO frota_cobli_posicao (veiculo_id, latitude, longitude, velocidade, ignicao_ligada, capturado_em)
                    VALUES (:veiculo_id, :lat, :lng, :vel, :ign, :capturado_em)
                ");
                $stmtIns->execute([
                    'veiculo_id' => $veiculoId,
                    'lat' => $localizacao['latitude'],
                    'lng' => $localizacao['longitude'],
                    'vel' => $localizacao['speed'] ?? null,
                    'ign' => $this->paraBooleanoPg($localizacao['ignition_on'] ?? null),
                    'capturado_em' => !empty($localizacao['time']) ? date('Y-m-d H:i:s', (int)$localizacao['time']) : date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                error_log('Erro ao gravar posição Cobli: ' . $e->getMessage());
            }
        }

        return $this->json($response, [
            'success' => true,
            'fonte'   => 'cobli',
            'data' => [
                'veiculo' => $dados['vehicle'] ?? null,
                'motorista' => $dados['driver'] ?? null,
                'localizacao' => $localizacao
            ]
        ]);
    }

    /**
     * Busca a última posição gravada para um veículo dentro da janela de cache.
     *
     * 🔥 NOVO 2026-09-23 (Bloco 7.A.1)
     */
    private function buscarUltimaPosicaoCache(int $veiculoId, int $segundosJanela = 30): ?array
    {
        $segundosJanela = max(1, min($segundosJanela, 3600));

        try {
            $stmt = $this->pdo->prepare("
                SELECT latitude, longitude, velocidade, ignicao_ligada, capturado_em, created_at
                FROM frota_cobli_posicao
                WHERE veiculo_id = :veiculo_id
                  AND created_at >= NOW() - make_interval(secs => {$segundosJanela})
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->bindValue(':veiculo_id', $veiculoId, \PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            error_log('[Cobli-cache] ERRO SQL: ' . $e->getMessage());
            return null;
        }
    }

    public function historicoPosicoes(Request $request, Response $response, array $args): Response
    {
        $veiculoId = (int)$args['id'];
        $params = $request->getQueryParams();
        $horas = max(1, min((int)($params['horas'] ?? 12), 72));

        $stmt = $this->pdo->prepare("
            SELECT latitude, longitude, velocidade, ignicao_ligada, capturado_em
            FROM frota_cobli_posicao
            WHERE veiculo_id = :veiculo_id
                AND capturado_em >= NOW() - (:horas || ' hours')::interval
            ORDER BY capturado_em ASC
        ");
        $stmt->execute(['veiculo_id' => $veiculoId, 'horas' => $horas]);

        return $this->json($response, ['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /**
     * GET /v1/frota/cobli/frota/posicoes
     * Busca posição de TODOS os veículos vinculados.
     *
     * 🔥 MUDANÇA 2026-09-23 (Bloco 7.A.1):
     *   - Cache de 30s por veículo, usando frota_cobli_posicao.created_at
     */
    public function posicoesFrota(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->query("
            SELECT d.veiculo_id, d.cobli_device_id, v.placa, v.modelo
            FROM frota_cobli_dispositivo d
            JOIN frota_veiculo v ON v.id = d.veiculo_id
            WHERE d.ativo = TRUE
        ");
        $vinculos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $veiculos   = [];
        $cacheHits  = 0;
        $cacheMiss  = 0;

        foreach ($vinculos as $vinculo) {
            $veiculoId = (int)$vinculo['veiculo_id'];

            $cache = $this->buscarUltimaPosicaoCache($veiculoId, 30);

            if ($cache !== null) {
                $cacheHits++;
                $veiculos[] = [
                    'veiculo_id'     => $veiculoId,
                    'placa'          => $vinculo['placa'],
                    'modelo'         => $vinculo['modelo'],
                    'motorista'      => $cache['motorista'] ?? null,
                    'latitude'       => (float)$cache['latitude'],
                    'longitude'      => (float)$cache['longitude'],
                    'velocidade'     => $cache['velocidade'],
                    'ignicao_ligada' => $cache['ignicao_ligada'],
                    'atualizado_em'  => $cache['capturado_em'],
                    'fonte'          => 'cache',
                ];
                continue;
            }

            $cacheMiss++;

            $resultado = $this->cobli->buscarDispositivo($vinculo['cobli_device_id']);
            if (!$resultado['success']) {
                continue;
            }

            $dados = $resultado['data'] ?? [];
            $localizacao = $dados['last_location'] ?? $dados['lastLocation'] ?? null;

            if (!$localizacao || empty($localizacao['latitude']) || empty($localizacao['longitude'])) {
                continue;
            }

            $capturadoEm = !empty($localizacao['time'])
                ? date('Y-m-d H:i:s', (int)$localizacao['time'])
                : date('Y-m-d H:i:s');

            try {
                $stmtIns = $this->pdo->prepare("
                    INSERT INTO frota_cobli_posicao
                        (veiculo_id, latitude, longitude, velocidade, ignicao_ligada, capturado_em)
                    VALUES
                        (:veiculo_id, :lat, :lng, :vel, :ign, :capturado_em)
                ");
                $stmtIns->execute([
                    'veiculo_id'   => $veiculoId,
                    'lat'          => $localizacao['latitude'],
                    'lng'          => $localizacao['longitude'],
                    'vel'          => $localizacao['speed'] ?? null,
                    'ign'          => $this->paraBooleanoPg($localizacao['ignition_on'] ?? null),
                    'capturado_em' => $capturadoEm,
                ]);
            } catch (\Exception $e) {
                error_log('[Cobli-posicoes] Erro ao gravar posição: ' . $e->getMessage());
            }

            $veiculos[] = [
                'veiculo_id'     => $veiculoId,
                'placa'          => $vinculo['placa'],
                'modelo'         => $vinculo['modelo'],
                'motorista'      => $dados['driver']['name'] ?? null,
                'latitude'       => (float)$localizacao['latitude'],
                'longitude'      => (float)$localizacao['longitude'],
                'velocidade'     => $localizacao['speed'] ?? null,
                'ignicao_ligada' => $localizacao['ignition_on'] ?? null,
                'atualizado_em'  => $capturadoEm,
                'fonte'          => 'cobli',
            ];
        }

        return $this->json($response, [
            'success' => true,
            'data'    => $veiculos,
            'meta'    => [
                'total'      => count($veiculos),
                'cache_hits' => $cacheHits,
                'cache_miss' => $cacheMiss,
            ]
        ]);
    }

    public function eventosRiscoMotorista(Request $request, Response $response, array $args): Response
    {
        $motoristaId = (int)$args['id'];
        $params = $request->getQueryParams();
        $dias = max(1, min((int)($params['dias'] ?? 30), 365));

        $stmt = $this->pdo->prepare("
            SELECT tipo_evento, latitude, longitude, ocorrido_em
            FROM frota_cobli_evento_risco
            WHERE motorista_id = :motorista_id
                AND ocorrido_em >= CURRENT_DATE - (:dias || ' days')::interval
            ORDER BY ocorrido_em DESC
        ");
        $stmt->execute(['motorista_id' => $motoristaId, 'dias' => $dias]);
        $eventos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $porTipo = [];
        foreach ($eventos as $e) {
            $porTipo[$e['tipo_evento']] = ($porTipo[$e['tipo_evento']] ?? 0) + 1;
        }

        return $this->json($response, [
            'success' => true,
            'data' => [
                'total_eventos' => count($eventos),
                'por_tipo' => $porTipo,
                'eventos' => $eventos
            ]
        ]);
    }

    public function sincronizarEventosRisco(Request $request, Response $response): Response
    {
        if (!$this->cobli->isConfigurado()) {
            return $this->json($response, ['success' => false, 'error' => 'Chave de API da Cobli não configurada'], 400);
        }

        $body = (array)$request->getParsedBody();
        $dias = max(1, min((int)($body['dias'] ?? 7), 90));

        $endDate = date('Y-m-d H:i:s');
        $startDate = date('Y-m-d H:i:s', strtotime("-{$dias} days"));

        $resultado = $this->cobli->eventosDeRisco($startDate, $endDate);
        if (!$resultado['success']) {
            return $this->json($response, ['success' => false, 'error' => $resultado['error']], 502);
        }

        $eventos = $resultado['data']['data'] ?? $resultado['data'] ?? [];
        if (!is_array($eventos)) {
            $eventos = [];
        }

        $inseridos = 0;
        foreach ($eventos as $ev) {
            $driverId = $ev['driver_id'] ?? $ev['driverId'] ?? null;
            $motoristaId = null;
            if ($driverId) {
                $motoristaId = $this->resolverMotoristaPorCobliId((string)$driverId);
            }

            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO frota_cobli_evento_risco
                        (motorista_id, veiculo_id, tipo_evento, latitude, longitude, ocorrido_em, dados_brutos)
                    VALUES (:motorista_id, NULL, :tipo, :lat, :lng, :ocorrido_em, :dados)
                ");
                $stmt->execute([
                    'motorista_id' => $motoristaId,
                    'tipo' => $ev['event_type'] ?? $ev['eventType'] ?? 'desconhecido',
                    'lat' => $ev['latitude'] ?? null,
                    'lng' => $ev['longitude'] ?? null,
                    'ocorrido_em' => $ev['date'] ?? $ev['occurred_at'] ?? date('Y-m-d H:i:s'),
                    'dados' => json_encode($ev, JSON_UNESCAPED_UNICODE)
                ]);
                $inseridos++;
            } catch (\Exception $e) {
                error_log('Erro ao inserir evento de risco Cobli: ' . $e->getMessage());
            }
        }

        return $this->json($response, [
            'success' => true,
            'data' => ['total_recebidos' => count($eventos), 'total_inseridos' => $inseridos]
        ]);
    }

    public function webhook(Request $request, Response $response): Response
    {
        $rawBody = (string)$request->getBody();
        if (!$this->assinaturaValida($request, $rawBody)) {
            return $this->json($response, ['success' => false, 'error' => 'Assinatura inválida'], 400);
        }

        $body = json_decode($rawBody, true) ?: (array)$request->getParsedBody();
        $eventType = $body['eventType'] ?? $body['event_type'] ?? null;
        $eventData = $body['eventData'] ?? $body['event_data'] ?? [];

        if (!$eventType) {
            return $this->json($response, ['success' => false, 'error' => 'Evento inválido'], 400);
        }

        $placaLog = $eventData['licensePlate'] ?? $eventData['license_plate'] ?? 'n/a';
        error_log('[Cobli-webhook] Evento recebido: ' . $eventType . ' | placa=' . $placaLog);

        try {
            $veiculoId = $this->resolverVeiculoPorPlaca($eventData['licensePlate'] ?? $eventData['license_plate'] ?? null);

            if ($eventType === 'position' && $veiculoId && !empty($eventData['latitude']) && !empty($eventData['longitude'])) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO frota_cobli_posicao (veiculo_id, latitude, longitude, velocidade, ignicao_ligada, capturado_em)
                    VALUES (:veiculo_id, :lat, :lng, :vel, :ign, NOW())
                ");
                $stmt->execute([
                    'veiculo_id' => $veiculoId,
                    'lat' => $eventData['latitude'],
                    'lng' => $eventData['longitude'],
                    'vel' => $eventData['speed'] ?? null,
                    'ign' => $this->paraBooleanoPg($eventData['ignitionOn'] ?? null)
                ]);
            } elseif (in_array($eventType, ['hard_break', 'fast_acceleration', 'speedy_turn', 'tailgating', 'distracted_driving', 'phone_usage', 'eyes_closed', 'smoking', 'yawn', 'sos', 'alert_driven_over_speed'])) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO frota_cobli_evento_risco (motorista_id, veiculo_id, tipo_evento, latitude, longitude, ocorrido_em, dados_brutos)
                    VALUES (NULL, :veiculo_id, :tipo, :lat, :lng, NOW(), :dados)
                ");
                $stmt->execute([
                    'veiculo_id' => $veiculoId,
                    'tipo' => $eventType,
                    'lat' => $eventData['latitude'] ?? null,
                    'lng' => $eventData['longitude'] ?? null,
                    'dados' => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);
            }

            return $this->json($response, ['success' => true]);
        } catch (\Exception $e) {
            error_log('[Cobli-webhook] Erro ao processar: ' . $e->getMessage());
            return $this->json($response, ['success' => true, 'warning' => 'Evento recebido, mas houve erro ao processar']);
        }
    }

    private function assinaturaValida(Request $request, string $rawBody): bool
    {
        $secret = $this->getConfigLocal('cobli_webhook_secret', $_ENV['COBLI_WEBHOOK_SECRET'] ?? '');
        $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));

        if (empty($secret)) {
            if ($appEnv === 'production') {
                error_log('[Cobli-webhook] BLOQUEADO: secret não configurado em produção. Configure cobli_webhook_secret ou COBLI_WEBHOOK_SECRET.');
                return false;
            }
            error_log('[Cobli-webhook] AVISO: aceitando webhook sem secret (APP_ENV=' . $appEnv . ')');
            return true;
        }

        $assinaturaRecebida = $request->getHeaderLine('X-Cobli-Signature');
        if (empty($assinaturaRecebida)) {
            error_log('[Cobli-webhook] Assinatura ausente no header X-Cobli-Signature');
            return false;
        }

        $esperada = hash_hmac('sha256', $rawBody, $secret);
        $valida = hash_equals($esperada, $assinaturaRecebida);

        if (!$valida) {
            error_log('[Cobli-webhook] Assinatura inválida. Recebida: ' . substr($assinaturaRecebida, 0, 16) . '...');
        }

        return $valida;
    }

    private function getConfigLocal($chave, $padrao = null)
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

    private function resolverVeiculoPorPlaca(?string $placa): ?int
    {
        if (!$placa) return null;

        $placaNormalizada = $this->normalizarPlaca($placa);
        if ($placaNormalizada === '') return null;

        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_veiculo
            WHERE UPPER(REPLACE(REPLACE(placa, '-', ''), ' ', '')) = :placa
            LIMIT 1
        ");
        $stmt->execute(['placa' => $placaNormalizada]);
        $id = $stmt->fetchColumn();

        return $id ? (int)$id : null;
    }

    private function resolverMotoristaPorCobliId(string $cobliDriverId): ?int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT motorista_id FROM frota_cobli_motorista WHERE cobli_driver_id = :id LIMIT 1");
            $stmt->execute(['id' => $cobliDriverId]);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function roadmap(Request $request, Response $response): Response
    {
        try {
            $itens = [];

            $apiKeyConfigurada = $this->cobli->isConfigurado();
            $itens[] = [
                'titulo'    => 'Chave de API configurada',
                'descricao' => $apiKeyConfigurada
                    ? 'A chave cobli_api_key está salva em frota_configuracao.'
                    : 'Configure a chave em frota_configuracao (chave: cobli_api_key).',
                'status'    => $apiKeyConfigurada ? 'ok' : 'pendente'
            ];

            $conexaoOk = false;
            if ($apiKeyConfigurada) {
                $teste = $this->cobli->testarConexao();
                $conexaoOk = $teste['success'] ?? false;
            }
            $itens[] = [
                'titulo'    => 'Conexão com a API validada',
                'descricao' => $conexaoOk
                    ? 'A API da Cobli respondeu com sucesso.'
                    : 'Não foi possível validar a conexão (verifique a chave e a rede).',
                'status'    => $conexaoOk ? 'ok' : ($apiKeyConfigurada ? 'pendente' : 'bloqueado')
            ];

            $totalVeiculos = 0;
            $totalVinculados = 0;
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM frota_veiculo WHERE status != 'inativo'");
                $totalVeiculos = (int)$stmt->fetchColumn();

                $stmt = $this->pdo->query("SELECT COUNT(*) FROM frota_cobli_dispositivo WHERE ativo = TRUE");
                $totalVinculados = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                // ignora
            }

            $itens[] = [
                'titulo'    => 'Veículos vinculados à Cobli',
                'descricao' => "{$totalVinculados} de {$totalVeiculos} veículo(s) com dispositivo ativo.",
                'status'    => ($totalVinculados > 0 && $totalVinculados >= $totalVeiculos) ? 'ok'
                            : ($totalVinculados > 0 ? 'pendente' : 'bloqueado')
            ];

            $webhookSecret = $this->getConfigLocal('cobli_webhook_secret', '');
            $itens[] = [
                'titulo'    => 'Webhook configurado (secret)',
                'descricao' => !empty($webhookSecret)
                    ? 'O secret do webhook está configurado.'
                    : 'Configure cobli_webhook_secret para receber eventos em tempo real.',
                'status'    => !empty($webhookSecret) ? 'ok' : 'pendente'
            ];

            $totalEventos = 0;
            try {
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) FROM frota_cobli_evento_risco
                    WHERE ocorrido_em >= CURRENT_DATE - INTERVAL '30 days'
                ");
                $totalEventos = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                // ignora
            }

            $itens[] = [
                'titulo'    => 'Eventos de risco sincronizados',
                'descricao' => $totalEventos > 0
                    ? "{$totalEventos} evento(s) de risco nos últimos 30 dias."
                    : 'Nenhum evento de risco sincronizado. Rode a sincronização manualmente.',
                'status'    => $totalEventos > 0 ? 'ok' : 'pendente'
            ];

            $totalPosicoes = 0;
            try {
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) FROM frota_cobli_posicao
                    WHERE capturado_em >= CURRENT_DATE - INTERVAL '7 days'
                ");
                $totalPosicoes = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {
                // ignora
            }

            $itens[] = [
                'titulo'    => 'Posições capturadas recentemente',
                'descricao' => $totalPosicoes > 0
                    ? "{$totalPosicoes} posição(ões) nos últimos 7 dias."
                    : 'Nenhuma posição capturada nos últimos 7 dias.',
                'status'    => $totalPosicoes > 0 ? 'ok' : 'pendente'
            ];

            $concluidos = count(array_filter($itens, fn($i) => $i['status'] === 'ok'));

            return $this->json($response, [
                'success' => true,
                'data'    => [
                    'itens'     => $itens,
                    'progresso' => [
                        'concluidos' => $concluidos,
                        'total'      => count($itens)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Erro no roadmap Cobli: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error'   => 'Erro ao carregar roadmap'
            ], 500);
        }
    }

    private function paraBooleanoPg($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return $valor ? '1' : '0';
    }

    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}