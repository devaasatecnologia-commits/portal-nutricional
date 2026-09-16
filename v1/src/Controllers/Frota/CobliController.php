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
     * POST /v1/frota/cobli/configurar
     * Salva a chave de API da Cobli (cobli-api-key).
     * Body: { "api_key": "..." }
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

            // Reinstancia o serviço já com a nova chave para testar na hora
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
     * Lista os dispositivos da frota na Cobli, para o gestor vincular a cada veículo.
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
     * Lista os veículos cadastrados na Cobli, já com placa (license_plate), marca,
     * modelo, ano e device_id — usado para casar automaticamente com a placa do sistema.
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
     * Importa veículos ausentes, atualiza os existentes e vincula os devices por placa.
     * Body opcional: { "dry_run": true } para apenas visualizar as alterações.
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
     * Casa automaticamente os veículos do sistema com os veículos da Cobli
     * comparando a placa (normalizada, sem traço/espaço, case-insensitive).
     * Somente cria vínculos novos — não sobrescreve vínculos já existentes.
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

    private function normalizarPlaca(?string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa ?? ''));
    }

    /**
     * GET /v1/frota/cobli/veiculos-vinculados
     * Lista os vínculos veículo <-> dispositivo Cobli já cadastrados,
     * usado para exibir o status de cada veículo do sistema na tela.
     */
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

    /**
     * POST /v1/frota/cobli/veiculo/{id}/vincular
     * Vincula um veículo do sistema ao deviceId/vehicleId da Cobli.
     * Body: { "cobli_device_id": "...", "cobli_vehicle_id": "..." }
     */
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

    /**
     * DELETE /v1/frota/cobli/veiculo/{id}/vincular
     * Remove o vínculo do veículo com a Cobli (desativa, não apaga o histórico).
     */
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

    /**
     * POST /v1/frota/cobli/motorista/{id}/vincular
     * Vincula um motorista do sistema ao driverId da Cobli (para eventos de risco/score).
     * Body: { "cobli_driver_id": "..." }
     */
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

    /**
     * POST /v1/frota/cobli/veiculo/{id}/sincronizar
     * Sincronização "sob demanda" ERP + Cobli -> tabelas internas da Frota.
     * Somente LEITURA no ERP e na Cobli; grava/atualiza (upsert) apenas em
     * frota_veiculo e frota_motorista. Nunca escreve de volta nas origens.
     */
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

            // Motorista mais recente associado a este veículo (via último embarque)
            $stmtMotoristaAtual = $this->pdo->prepare("
                SELECT motorista_id FROM frota_embarque
                WHERE veiculo_id = :veiculo_id AND motorista_id IS NOT NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmtMotoristaAtual->execute(['veiculo_id' => $veiculoId]);
            $veiculoDados['motorista_id'] = $stmtMotoristaAtual->fetchColumn() ?: null;

            // ------------------------------------------------------------
            // 1) COBLI (leitura): dados reais do veículo/motorista/posição
            // ------------------------------------------------------------
            $vinculoCobli = $this->pdo->prepare("SELECT cobli_device_id FROM frota_cobli_dispositivo WHERE veiculo_id = :id AND ativo = TRUE");
            $vinculoCobli->execute(['id' => $veiculoId]);
            $device = $vinculoCobli->fetch(\PDO::FETCH_ASSOC);

            if ($device) {
                $resultado = $this->cobli->buscarDispositivo($device['cobli_device_id']);
                if ($resultado['success']) {
                    $dados = $resultado['data'] ?? [];
                    $veiculoCobli = $dados['vehicle'] ?? [];
                    $motoristaCobli = $dados['driver'] ?? [];

                    // Upsert de dados do veículo (marca/modelo/ano vêm da Cobli, fonte confiável de cadastro)
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

                    // Se o veículo já tem motorista vinculado no sistema, atualiza o vínculo Cobli dele também
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

            // ------------------------------------------------------------
            // 2) ERP (leitura): dados cadastrais do motorista (cliforemp)
            // ------------------------------------------------------------
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
     * Busca a posição em tempo real do veículo diretamente na Cobli
     * (usa o device vinculado) e já registra no histórico local.
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
            'data' => [
                'veiculo' => $dados['vehicle'] ?? null,
                'motorista' => $dados['driver'] ?? null,
                'localizacao' => $localizacao
            ]
        ]);
    }

    /**
     * GET /v1/frota/cobli/veiculo/{id}/rota-historico
     * Retorna o histórico de posições já capturadas localmente (Leaflet).
     */
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
     * Busca, ao vivo na Cobli, a posição de TODOS os veículos vinculados
     * (para exibição no mapa da tela de Gestão de Cargas).
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

        $veiculos = [];
        foreach ($vinculos as $vinculo) {
            $resultado = $this->cobli->buscarDispositivo($vinculo['cobli_device_id']);
            if (!$resultado['success']) {
                continue;
            }

            $dados = $resultado['data'] ?? [];
            $localizacao = $dados['last_location'] ?? $dados['lastLocation'] ?? null;

            if (!$localizacao || empty($localizacao['latitude']) || empty($localizacao['longitude'])) {
                continue;
            }

            try {
                $stmtIns = $this->pdo->prepare("
                    INSERT INTO frota_cobli_posicao (veiculo_id, latitude, longitude, velocidade, ignicao_ligada, capturado_em)
                    VALUES (:veiculo_id, :lat, :lng, :vel, :ign, :capturado_em)
                ");
                $stmtIns->execute([
                    'veiculo_id' => $vinculo['veiculo_id'],
                    'lat' => $localizacao['latitude'],
                    'lng' => $localizacao['longitude'],
                    'vel' => $localizacao['speed'] ?? null,
                    'ign' => $this->paraBooleanoPg($localizacao['ignition_on'] ?? null),
                    'capturado_em' => !empty($localizacao['time']) ? date('Y-m-d H:i:s', (int)$localizacao['time']) : date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                error_log('Erro ao gravar posição Cobli (frota): ' . $e->getMessage());
            }

            $veiculos[] = [
                'veiculo_id' => (int)$vinculo['veiculo_id'],
                'placa' => $vinculo['placa'],
                'modelo' => $vinculo['modelo'],
                'motorista' => $dados['driver']['name'] ?? null,
                'latitude' => (float)$localizacao['latitude'],
                'longitude' => (float)$localizacao['longitude'],
                'velocidade' => $localizacao['speed'] ?? null,
                'ignicao_ligada' => $localizacao['ignition_on'] ?? null,
                'atualizado_em' => !empty($localizacao['time']) ? date('Y-m-d H:i:s', (int)$localizacao['time']) : null
            ];
        }

        return $this->json($response, ['success' => true, 'data' => $veiculos]);
    }

    /**
     * GET /v1/frota/cobli/motorista/{id}/eventos-risco
     * Busca eventos de risco (score de condução) do motorista no período,
     * já persistidos localmente a partir da sincronização.
     */
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

    /**
     * POST /v1/frota/cobli/sincronizar-eventos-risco
     * Rotina (cron/manual) que busca eventos de risco de todos os motoristas
     * vinculados na Cobli, dentro do período informado, e persiste localmente.
     * Body opcional: { "dias": 7 }
     */
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

  /**
 * POST /v1/frota/cobli/webhook
 * Endpoint público para receber eventos em tempo real da Cobli
 * (posição, ignição, geocerca, eventos de risco/câmera).
 * Requer HTTPS público configurado no painel da Cobli.
 */
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

    // Log leve para auditoria (ajuda a diagnosticar webhook sem casar veículo)
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
        // Sempre 2xx para não gerar retentativas em erro interno já tratado
        return $this->json($response, ['success' => true, 'warning' => 'Evento recebido, mas houve erro ao processar']);
    }
}

   /**
 * Valida a assinatura HMAC-SHA256 enviada pela Cobli no header X-Cobli-Signature,
 * usando o secret configurado em frota_configuracao (chave: cobli_webhook_secret).
 *
 * Em produção (APP_ENV=production), exige o secret configurado — sem exceção.
 * Em desenvolvimento/homologação, aceita sem secret para facilitar setup inicial.
 */
private function assinaturaValida(Request $request, string $rawBody): bool
{
    $secret = $this->getConfigLocal('cobli_webhook_secret', $_ENV['COBLI_WEBHOOK_SECRET'] ?? '');
    $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));

    if (empty($secret)) {
        if ($appEnv === 'production') {
            error_log('[Cobli-webhook] BLOQUEADO: secret não configurado em produção. Configure cobli_webhook_secret ou COBLI_WEBHOOK_SECRET.');
            return false;
        }
        // Somente dev/homologação
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

    // Normaliza dos dois lados para casar independente do formato salvo
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

    /**
     * Converte um valor booleano (ou nulo) para o formato aceito pelo PDO/Postgres,
     * evitando erro "invalid input syntax for type boolean" quando o driver
     * retorna string vazia em vez de NULL/true/false.
     */
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
