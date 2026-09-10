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

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->cobli = new CobliService($this->pdo);
        $this->garantirTabelas();
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
            error_log('Erro ao processar webhook Cobli: ' . $e->getMessage());
            // Sempre 2xx para não gerar retentativas em erro interno já tratado
            return $this->json($response, ['success' => true, 'warning' => 'Evento recebido, mas houve erro ao processar']);
        }
    }

    /**
     * Valida a assinatura HMAC-SHA256 enviada pela Cobli no header X-Cobli-Signature,
     * usando o secret configurado em frota_configuracao (chave: cobli_webhook_secret).
     */
    private function assinaturaValida(Request $request, string $rawBody): bool
    {
        $secret = $this->getConfigLocal('cobli_webhook_secret', $_ENV['COBLI_WEBHOOK_SECRET'] ?? '');
        if (empty($secret)) {
            // Sem secret configurado ainda: aceita para não bloquear a primeira integração,
            // mas o ideal é configurar o secret assim que o webhook for cadastrado no painel.
            return true;
        }

        $assinaturaRecebida = $request->getHeaderLine('X-Cobli-Signature');
        if (empty($assinaturaRecebida)) {
            return false;
        }

        $esperada = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($esperada, $assinaturaRecebida);
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
    {        if (!$placa) return null;
        $stmt = $this->pdo->prepare("SELECT id FROM frota_veiculo WHERE placa = :placa LIMIT 1");
        $stmt->execute(['placa' => strtoupper(str_replace('-', '', $placa))]);
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
