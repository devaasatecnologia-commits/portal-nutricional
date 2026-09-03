<?php
// src/Controllers/Frota/RastreamentoController.php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RastreamentoController
{
    private $pdo;
    private $webSocketServer;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->webSocketServer = null; // Será injetado depois
    }
    
    /**
     * GET /v1/frota/rastreamento/veiculo/{id}
     * Buscar posição atual do veículo
     */
    public function veiculo(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $sql = "
            SELECT 
                v.id,
                v.placa,
                v.modelo,
                v.tipo,
                v.latitude,
                v.longitude,
                v.velocidade_atual,
                v.ultima_posicao,
                v.status,
                m.nome as motorista_nome,
                m.telefone as motorista_telefone,
                eb.id as embarque_id,
                eb.numero_embarque,
                eb.status as embarque_status,
                COUNT(e.id) as total_entregas,
                COUNT(CASE WHEN e.status = 'entregue' THEN 1 END) as entregues,
                COUNT(CASE WHEN e.status = 'pendente' THEN 1 END) as pendentes
            FROM frota_veiculo v
            LEFT JOIN frota_motorista m ON m.veiculo_atual_id = v.id
            LEFT JOIN frota_embarque eb ON eb.veiculo_id = v.id AND eb.status = 'em_andamento'
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE v.id = :id
            GROUP BY v.id, m.nome, m.telefone, eb.id, eb.numero_embarque, eb.status
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $veiculo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$veiculo) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Veículo não encontrado'
            ], 404);
        }
        
        // Buscar últimas posições (para traçar rota)
        $stmt = $this->pdo->prepare("
            SELECT 
                latitude,
                longitude,
                velocidade,
                data_hora
            FROM frota_historico_posicao
            WHERE veiculo_id = :veiculo_id
              AND data_hora >= NOW() - INTERVAL '1 hour'
            ORDER BY data_hora ASC
        ");
        $stmt->execute(['veiculo_id' => $id]);
        $veiculo['historico_posicoes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar próximas entregas
        if ($veiculo['embarque_id']) {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id,
                    e.cliente_nome,
                    e.endereco,
                    e.latitude,
                    e.longitude,
                    e.status,
                    e.ordem_entrega,
                    calcular_distancia(:veiculo_lat, :veiculo_lng, e.latitude, e.longitude) as distancia_km
                FROM frota_entrega e
                WHERE e.embarque_id = :embarque_id
                  AND e.status IN ('pendente', 'em_entrega')
                ORDER BY e.ordem_entrega ASC
            ");
            $stmt->execute([
                'veiculo_lat' => $veiculo['latitude'],
                'veiculo_lng' => $veiculo['longitude'],
                'embarque_id' => $veiculo['embarque_id']
            ]);
            $veiculo['proximas_entregas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $veiculo
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/veiculo/{id}/historico
     * Histórico de posições do veículo
     */
    public function historicoVeiculo(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $params = $request->getQueryParams();
        
        $dataInicio = $params['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $dataFim = $params['data_fim'] ?? date('Y-m-d');
        $limite = (int)($params['limite'] ?? 1000);
        
        $sql = "
            SELECT 
                latitude,
                longitude,
                velocidade,
                precisao,
                data_hora
            FROM frota_historico_posicao
            WHERE veiculo_id = :veiculo_id
              AND DATE(data_hora) BETWEEN :data_inicio AND :data_fim
            ORDER BY data_hora ASC
            LIMIT :limite
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':veiculo_id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':data_inicio', $dataInicio);
        $stmt->bindValue(':data_fim', $dataFim);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        
        $historico = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular distância percorrida
        $distanciaTotal = 0;
        $ultimoPonto = null;
        foreach ($historico as $ponto) {
            if ($ultimoPonto) {
                $distanciaTotal += $this->calcularDistancia(
                    $ultimoPonto['latitude'],
                    $ultimoPonto['longitude'],
                    $ponto['latitude'],
                    $ponto['longitude']
                );
            }
            $ultimoPonto = $ponto;
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'veiculo_id' => $id,
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ],
                'pontos' => $historico,
                'total_pontos' => count($historico),
                'distancia_total_km' => round($distanciaTotal, 2)
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/veiculo/{id}/rota
     * Rota percorrida pelo veículo (mapa)
     */
    public function rotaVeiculo(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $params = $request->getQueryParams();
        
        $data = $params['data'] ?? date('Y-m-d');
        
        $sql = "
            SELECT 
                latitude,
                longitude,
                velocidade,
                data_hora
            FROM frota_historico_posicao
            WHERE veiculo_id = :veiculo_id
              AND DATE(data_hora) = :data
            ORDER BY data_hora ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'veiculo_id' => $id,
            'data' => $data
        ]);
        
        $pontos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar entregas do dia
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id,
                e.cliente_nome,
                e.endereco,
                e.latitude,
                e.longitude,
                e.status,
                e.horario_checkin,
                e.horario_entrega,
                eb.id as embarque_id
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE eb.veiculo_id = :veiculo_id
              AND DATE(e.created_at) = :data
            ORDER BY e.ordem_entrega ASC
        ");
        $stmt->execute([
            'veiculo_id' => $id,
            'data' => $data
        ]);
        $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular métricas
        $distanciaTotal = 0;
        $ultimoPonto = null;
        foreach ($pontos as $ponto) {
            if ($ultimoPonto) {
                $distanciaTotal += $this->calcularDistancia(
                    $ultimoPonto['latitude'],
                    $ultimoPonto['longitude'],
                    $ponto['latitude'],
                    $ponto['longitude']
                );
            }
            $ultimoPonto = $ponto;
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'veiculo_id' => $id,
                'data' => $data,
                'rota' => $pontos,
                'entregas' => $entregas,
                'resumo' => [
                    'total_pontos' => count($pontos),
                    'distancia_total_km' => round($distanciaTotal, 2),
                    'total_entregas' => count($entregas),
                    'entregas_concluidas' => count(array_filter($entregas, fn($e) => $e['status'] === 'entregue')),
                    'tempo_medio_entrega' => $this->calcularTempoMedio($entregas)
                ]
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/embarque/{id}
     * Rastreamento completo de um embarque
     */
    public function embarque(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $sql = "
            SELECT 
                eb.id,
                eb.numero_embarque,
                eb.data_saida,
                eb.status,
                eb.origem_lat,
                eb.origem_lng,
                v.id as veiculo_id,
                v.placa,
                v.modelo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                v.velocidade_atual,
                v.ultima_posicao,
                m.id as motorista_id,
                m.nome as motorista_nome,
                m.telefone as motorista_telefone,
                COUNT(e.id) as total_entregas,
                COUNT(CASE WHEN e.status = 'entregue' THEN 1 END) as entregues,
                COUNT(CASE WHEN e.status = 'pendente' THEN 1 END) as pendentes,
                COUNT(CASE WHEN e.status = 'em_entrega' THEN 1 END) as em_andamento,
                COUNT(CASE WHEN e.status = 'falha' THEN 1 END) as falhas
            FROM frota_embarque eb
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = eb.motorista_id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE eb.id = :id
            GROUP BY eb.id, v.id, m.id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Embarque não encontrado'
            ], 404);
        }
        
        // Buscar entregas do embarque
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id,
                e.cliente_nome,
                e.endereco,
                e.latitude,
                e.longitude,
                e.status,
                e.ordem_entrega,
                e.horario_checkin,
                e.horario_entrega,
                e.codigo_rastreamento,
                e.foto_entrega_url,
                e.assinatura_entrega_url,
                calcular_distancia(:veiculo_lat, :veiculo_lng, e.latitude, e.longitude) as distancia_km
            FROM frota_entrega e
            WHERE e.embarque_id = :embarque_id
            ORDER BY e.ordem_entrega ASC
        ");
        $stmt->execute([
            'embarque_id' => $id,
            'veiculo_lat' => $embarque['veiculo_lat'],
            'veiculo_lng' => $embarque['veiculo_lng']
        ]);
        $embarque['entregas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar histórico de posições do veículo
        if ($embarque['veiculo_id']) {
            $stmt = $this->pdo->prepare("
                SELECT 
                    latitude,
                    longitude,
                    velocidade,
                    data_hora
                FROM frota_historico_posicao
                WHERE veiculo_id = :veiculo_id
                  AND embarque_id = :embarque_id
                ORDER BY data_hora ASC
            ");
            $stmt->execute([
                'veiculo_id' => $embarque['veiculo_id'],
                'embarque_id' => $id
            ]);
            $embarque['historico_posicoes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $embarque
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/entrega/{id}
     * Rastreamento de uma entrega específica (público)
     */
    public function entrega(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $sql = "
            SELECT 
                e.id,
                e.codigo_rastreamento,
                e.cliente_nome,
                e.endereco,
                e.numero,
                e.bairro,
                e.cidade,
                e.uf,
                e.cep,
                e.status,
                e.horario_checkin,
                e.horario_entrega,
                e.nome_recebedor,
                e.observacoes,
                e.foto_entrega_url,
                e.assinatura_entrega_url,
                eb.numero_embarque,
                eb.data_saida,
                eb.data_prevista_entrega,
                m.nome as motorista_nome,
                m.telefone as motorista_telefone,
                v.placa,
                v.modelo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                v.ultima_posicao,
                calcular_distancia(v.latitude, v.longitude, e.latitude, e.longitude) as distancia_veiculo_km
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_motorista m ON m.id = eb.motorista_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            WHERE e.id = :id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$entrega) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada'
            ], 404);
        }
        
        // Buscar timeline
        $stmt = $this->pdo->prepare("
            SELECT 
                tipo,
                latitude,
                longitude,
                foto_url,
                observacoes,
                data_hora
            FROM frota_checkin
            WHERE entrega_id = :entrega_id
            ORDER BY data_hora ASC
        ");
        $stmt->execute(['entrega_id' => $id]);
        $entrega['timeline'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular previsão
        if ($entrega['status'] === 'pendente' && $entrega['veiculo_lat']) {
            $distancia = $this->calcularDistancia(
                (float)$entrega['veiculo_lat'],
                (float)$entrega['veiculo_lng'],
                (float)$entrega['latitude'],
                (float)$entrega['longitude']
            );
            $tempoEstimado = round(($distancia / 1000) / 40 * 60); // 40km/h
            $entrega['previsao_entrega'] = date('Y-m-d H:i:s', strtotime("+{$tempoEstimado} minutes"));
            $entrega['distancia_estimada_km'] = round($distancia / 1000, 1);
            $entrega['tempo_estimado_min'] = $tempoEstimado;
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $entrega
        ]);
    }
    
    /**
     * POST /v1/frota/rastreamento/posicao
     * Atualizar posição em tempo real (endpoint para dispositivos)
     */
    public function atualizarPosicao(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $veiculoId = (int)($input['veiculo_id'] ?? 0);
        $motoristaId = (int)($input['motorista_id'] ?? 0);
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        $velocidade = (float)($input['velocidade'] ?? 0);
        $precisao = (float)($input['precisao'] ?? 0);
        $embarqueId = (int)($input['embarque_id'] ?? 0);
        
        if ($lat == 0 || $lng == 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Latitude e longitude são obrigatórios'
            ], 400);
        }
        
        // Se não veio veiculo_id, buscar pelo motorista
        if ($veiculoId == 0 && $motoristaId > 0) {
            $stmt = $this->pdo->prepare("SELECT veiculo_atual_id FROM frota_motorista WHERE id = :id");
            $stmt->execute(['id' => $motoristaId]);
            $motorista = $stmt->fetch(\PDO::FETCH_ASSOC);
            $veiculoId = $motorista['veiculo_atual_id'] ?? 0;
        }
        
        // Se não veio embarque_id, buscar pelo veículo
        if ($embarqueId == 0 && $veiculoId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM frota_embarque 
                WHERE veiculo_id = :veiculo_id AND status = 'em_andamento'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute(['veiculo_id' => $veiculoId]);
            $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
            $embarqueId = $embarque['id'] ?? 0;
        }
        
        // Atualizar veículo
        if ($veiculoId > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE frota_veiculo 
                SET latitude = :lat,
                    longitude = :lng,
                    velocidade_atual = :velocidade,
                    ultima_posicao = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $veiculoId,
                'lat' => $lat,
                'lng' => $lng,
                'velocidade' => $velocidade
            ]);
        }
        
        // Atualizar motorista
        if ($motoristaId > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE frota_motorista 
                SET latitude = :lat,
                    longitude = :lng,
                    ultima_posicao = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $motoristaId,
                'lat' => $lat,
                'lng' => $lng
            ]);
        }
        
        // Registrar histórico
        if ($veiculoId > 0 || $motoristaId > 0) {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_historico_posicao 
                (veiculo_id, motorista_id, embarque_id, latitude, longitude, velocidade, precisao, data_hora)
                VALUES (:veiculo_id, :motorista_id, :embarque_id, :lat, :lng, :velocidade, :precisao, NOW())
            ");
            $stmt->execute([
                'veiculo_id' => $veiculoId ?: null,
                'motorista_id' => $motoristaId ?: null,
                'embarque_id' => $embarqueId ?: null,
                'lat' => $lat,
                'lng' => $lng,
                'velocidade' => $velocidade,
                'precisao' => $precisao
            ]);
        }
        
        // Verificar alertas
        $alertas = $this->verificarAlertas($veiculoId, $motoristaId, $lat, $lng, $velocidade);
        
        // Enviar via WebSocket
        $this->enviarNotificacaoWS([
            'tipo' => 'posicao_atualizada',
            'veiculo_id' => $veiculoId,
            'motorista_id' => $motoristaId,
            'embarque_id' => $embarqueId,
            'lat' => $lat,
            'lng' => $lng,
            'velocidade' => $velocidade,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'veiculo_id' => $veiculoId,
                'motorista_id' => $motoristaId,
                'latitude' => $lat,
                'longitude' => $lng,
                'velocidade' => $velocidade,
                'timestamp' => date('Y-m-d H:i:s'),
                'alertas' => $alertas
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/posicao/ultimas
     * Últimas posições de todos os veículos ativos
     */
    public function ultimasPosicoes(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $limite = (int)($params['limite'] ?? 100);
        
        $sql = "
            WITH ultimas_posicoes AS (
                SELECT DISTINCT ON (veiculo_id) 
                    veiculo_id,
                    latitude,
                    longitude,
                    velocidade,
                    data_hora
                FROM frota_historico_posicao
                WHERE data_hora >= NOW() - INTERVAL '1 hour'
                ORDER BY veiculo_id, data_hora DESC
            )
            SELECT 
                v.id,
                v.placa,
                v.modelo,
                v.tipo,
                v.status,
                v.latitude,
                v.longitude,
                v.velocidade_atual,
                v.ultima_posicao,
                m.nome as motorista_nome,
                eb.id as embarque_id,
                eb.numero_embarque,
                eb.status as embarque_status,
                COUNT(e.id) as total_entregas,
                COUNT(CASE WHEN e.status = 'entregue' THEN 1 END) as entregues,
                COUNT(CASE WHEN e.status = 'pendente' THEN 1 END) as pendentes
            FROM frota_veiculo v
            LEFT JOIN frota_motorista m ON m.veiculo_atual_id = v.id
            LEFT JOIN frota_embarque eb ON eb.veiculo_id = v.id AND eb.status = 'em_andamento'
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE v.status IN ('disponivel', 'em_rota')
            GROUP BY v.id, m.nome, eb.id, eb.numero_embarque, eb.status
            ORDER BY v.ultima_posicao DESC NULLS LAST
            LIMIT :limite
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        
        $veiculos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Adicionar últimas posições
        foreach ($veiculos as &$veiculo) {
            $stmt = $this->pdo->prepare("
                SELECT latitude, longitude, data_hora
                FROM frota_historico_posicao
                WHERE veiculo_id = :veiculo_id
                ORDER BY data_hora DESC
                LIMIT 10
            ");
            $stmt->execute(['veiculo_id' => $veiculo['id']]);
            $veiculo['ultimas_posicoes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $veiculos,
            'total' => count($veiculos),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/websocket/token
     * Gerar token para conexão WebSocket
     */
    public function gerarTokenWebSocket(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $userId = $user['idusuario'] ?? 0;
        
        if (!$userId) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
        }
        
        // Gerar token JWT para WebSocket
        $payload = [
            'uid' => $userId,
            'type' => 'websocket',
            'exp' => time() + 3600 // 1 hora
        ];
        
        $token = \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'] ?? 'chave_super_secreta', 'HS256');
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'token' => $token,
                'expires_in' => 3600,
                'websocket_url' => 'wss://' . $_SERVER['HTTP_HOST'] . '/ws/rastreamento'
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/websocket/status
     * Status do WebSocket
     */
    public function statusWebSocket(Request $request, Response $response): Response
    {
        // Verificar se o servidor WebSocket está rodando
        $status = 'online';
        $conexoesAtivas = 0;
        $ultimaMensagem = null;
        
        // TODO: Implementar verificação real do WebSocket
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'status' => $status,
                'conexoes_ativas' => $conexoesAtivas,
                'ultima_mensagem' => $ultimaMensagem,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/rastreamento/alertas
     * Listar alertas ativos
     */
    public function getAlertas(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? 'aberta';
        $tipo = $params['tipo'] ?? null;
        
        $filtros = ["o.status = :status"];
        $bindParams = ['status' => $status];
        
        if ($tipo) {
            $filtros[] = "o.tipo = :tipo";
            $bindParams['tipo'] = $tipo;
        }
        
        $where = 'WHERE ' . implode(' AND ', $filtros);
        
        $sql = "
            SELECT 
                o.id,
                o.tipo,
                o.descricao,
                o.latitude,
                o.longitude,
                o.status,
                o.created_at,
                m.nome as motorista_nome,
                v.placa,
                v.modelo,
                eb.numero_embarque
            FROM frota_ocorrencia o
            LEFT JOIN frota_motorista m ON m.id = o.motorista_id
            LEFT JOIN frota_veiculo v ON v.id = m.veiculo_atual_id
            LEFT JOIN frota_embarque eb ON eb.id = o.embarque_id
            {$where}
            ORDER BY o.created_at DESC
            LIMIT 50
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        
        $alertas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $alertas,
            'total' => count($alertas)
        ]);
    }
    
    /**
     * POST /v1/frota/rastreamento/alertas/{id}/resolver
     * Resolver um alerta
     */
    public function resolverAlerta(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $observacoes = $input['observacoes'] ?? '';
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_ocorrencia 
            SET status = 'resolvida',
                resolvido_por = :resolvido_por,
                resolvido_em = NOW(),
                descricao = COALESCE(descricao, '') || ' | Resolvido: ' || :obs,
                updated_at = NOW()
            WHERE id = :id AND status = 'aberta'
        ");
        $stmt->execute([
            'id' => $id,
            'resolvido_por' => $_SESSION['user_id'] ?? 0,
            'obs' => $observacoes
        ]);
        
        if ($stmt->rowCount() === 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Alerta não encontrado ou já resolvido'
            ], 404);
        }
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Alerta resolvido com sucesso'
        ]);
    }
    
    // ========================================================================
    // MÉTODOS AUXILIARES
    // ========================================================================
    
    private function verificarAlertas($veiculoId, $motoristaId, $lat, $lng, $velocidade): array
    {
        $alertas = [];
        
        // 1. Verificar velocidade máxima
        $velocidadeMaxima = (float)$this->getConfig('velocidade_maxima_kmh', 80);
        if ($velocidade > $velocidadeMaxima) {
            $alertas[] = [
                'tipo' => 'excesso_velocidade',
                'mensagem' => "Velocidade excessiva: {$velocidade} km/h (limite: {$velocidadeMaxima} km/h)",
                'gravidade' => 'alta'
            ];
        }
        
        // 2. Verificar se está na rota
        if ($veiculoId) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM frota_embarque 
                WHERE veiculo_id = :veiculo_id AND status = 'em_andamento'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute(['veiculo_id' => $veiculoId]);
            $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($embarque) {
                // Buscar próximas entregas
                $stmt = $this->pdo->prepare("
                    SELECT latitude, longitude, id
                    FROM frota_entrega
                    WHERE embarque_id = :embarque_id
                      AND status IN ('pendente', 'em_entrega')
                    ORDER BY ordem_entrega ASC
                    LIMIT 1
                ");
                $stmt->execute(['embarque_id' => $embarque['id']]);
                $proxima = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($proxima) {
                    $distancia = $this->calcularDistancia(
                        $lat, $lng,
                        (float)$proxima['latitude'],
                        (float)$proxima['longitude']
                    );
                    
                    // Se estiver longe da rota (> 500m)
                    if ($distancia > 500) {
                        $alertas[] = [
                            'tipo' => 'desvio_rota',
                            'mensagem' => "Veículo desviou da rota. Distância: " . round($distancia) . "m",
                            'gravidade' => 'media'
                        ];
                    }
                }
            }
        }
        
        // 3. Verificar tempo parado
        // TODO: Implementar verificação de tempo parado
        
        // Salvar alertas no banco se forem críticos
        foreach ($alertas as $alerta) {
            if ($alerta['gravidade'] === 'alta') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO frota_ocorrencia 
                    (veiculo_id, motorista_id, tipo, descricao, latitude, longitude, status, created_at)
                    VALUES (:veiculo_id, :motorista_id, :tipo, :descricao, :lat, :lng, 'aberta', NOW())
                ");
                $stmt->execute([
                    'veiculo_id' => $veiculoId,
                    'motorista_id' => $motoristaId,
                    'tipo' => $alerta['tipo'],
                    'descricao' => $alerta['mensagem'],
                    'lat' => $lat,
                    'lng' => $lng
                ]);
            }
        }
        
        return $alertas;
    }
    
    private function calcularDistancia($lat1, $lng1, $lat2, $lng2): float
    {
        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) return 0;
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
    
    private function calcularTempoMedio($entregas): ?float
    {
        $tempos = [];
        foreach ($entregas as $e) {
            if ($e['horario_checkin'] && $e['horario_entrega']) {
                $checkin = new \DateTime($e['horario_checkin']);
                $entrega = new \DateTime($e['horario_entrega']);
                $tempos[] = ($entrega->getTimestamp() - $checkin->getTimestamp()) / 60;
            }
        }
        return count($tempos) > 0 ? round(array_sum($tempos) / count($tempos), 1) : null;
    }
    
    private function getConfig($chave, $padrao = null)
    {
        $stmt = $this->pdo->prepare("SELECT valor FROM frota_configuracao WHERE chave = :chave");
        $stmt->execute(['chave' => $chave]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['valor'] : $padrao;
    }
    
    private function enviarNotificacaoWS($dados)
    {
        // TODO: Implementar WebSocket
        error_log('WebSocket: ' . json_encode($dados));
    }
    
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)
                       ->withHeader('Content-Type', 'application/json; charset=utf-8')
                       ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}