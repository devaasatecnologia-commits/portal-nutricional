<?php
// src/Controllers/Frota/MotoristaController.php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MotoristaController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/frota/motoristas
     * Listar motoristas com filtros
     */
    public function listar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        
        $filtros = [];
        $bindParams = [];
        
        if (!empty($params['status'])) {
            $filtros[] = "m.status = :status";
            $bindParams['status'] = $params['status'];
        }
        
        if (!empty($params['busca'])) {
            $filtros[] = "(m.nome ILIKE :busca OR m.cpf ILIKE :busca2 OR m.telefone ILIKE :busca3)";
            $bindParams['busca'] = "%{$params['busca']}%";
            $bindParams['busca2'] = "%{$params['busca']}%";
            $bindParams['busca3'] = "%{$params['busca']}%";
        }
        
        if (!empty($params['veiculo_id'])) {
            $filtros[] = "m.veiculo_atual_id = :veiculo_id";
            $bindParams['veiculo_id'] = (int)$params['veiculo_id'];
        }
        
        $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
        
        $limite = (int)($params['limite'] ?? 20);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $sql = "
            SELECT 
                m.*,
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                COUNT(DISTINCT e.id) as total_entregas,
                COUNT(DISTINCT CASE WHEN e.status = 'entregue' THEN e.id END) as entregas_concluidas,
                COUNT(DISTINCT CASE WHEN e.status = 'pendente' THEN e.id END) as entregas_pendentes,
                COUNT(DISTINCT CASE WHEN e.status = 'falha' THEN e.id END) as entregas_falha,
                COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)), 0) as valor_total_entregas,
                COALESCE(AVG(CASE WHEN e.status = 'entregue' THEN EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60 END), 0) as tempo_medio_entrega
            FROM frota_motorista m
            LEFT JOIN frota_veiculo v ON v.id = m.veiculo_atual_id
            LEFT JOIN frota_embarque eb ON eb.motorista_id = m.id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            {$where}
            GROUP BY m.id, v.placa, v.modelo
            ORDER BY m.nome ASC
            LIMIT :limite OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $motoristas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $sqlCount = "SELECT COUNT(*) FROM frota_motorista m {$where}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        foreach ($bindParams as $key => $val) {
            $stmtCount->bindValue($key, $val);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $motoristas,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite)
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}
     * Buscar motorista específico com detalhes
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $sql = "
            SELECT 
                m.*,
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                v.tipo as veiculo_tipo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                COUNT(DISTINCT eb.id) as total_embarques,
                COUNT(DISTINCT e.id) as total_entregas,
                COUNT(DISTINCT CASE WHEN e.status = 'entregue' THEN e.id END) as entregas_concluidas,
                COUNT(DISTINCT CASE WHEN e.status = 'pendente' THEN e.id END) as entregas_pendentes,
                COUNT(DISTINCT CASE WHEN e.status = 'falha' THEN e.id END) as entregas_falha,
                COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)), 0) as valor_total,
                COALESCE(SUM(CASE WHEN e.status = 'entregue' THEN COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0) END), 0) as valor_entregue,
                COALESCE(AVG(CASE WHEN e.status = 'entregue' THEN EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60 END), 0) as tempo_medio_entrega,
                (SELECT COUNT(*) FROM frota_notificacao WHERE motorista_id = m.id AND lida = false) as notificacoes_nao_lidas
            FROM frota_motorista m
            LEFT JOIN frota_veiculo v ON v.id = m.veiculo_atual_id
            LEFT JOIN frota_embarque eb ON eb.motorista_id = m.id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE m.id = :id
            GROUP BY m.id, v.placa, v.modelo, v.tipo, v.latitude, v.longitude
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $motorista = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$motorista) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ], 404);
        }
        
        // Buscar últimas entregas
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                eb.numero_embarque,
                v.placa
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            WHERE eb.motorista_id = :motorista_id
            ORDER BY e.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['motorista_id' => $id]);
        $motorista['ultimas_entregas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar estatísticas por dia
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(e.horario_entrega) as data,
                COUNT(*) as total,
                SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)) as valor
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE eb.motorista_id = :motorista_id
              AND e.status = 'entregue'
              AND e.horario_entrega >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(e.horario_entrega)
            ORDER BY data DESC
        ");
        $stmt->execute(['motorista_id' => $id]);
        $motorista['entregas_por_dia'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $motorista
        ]);
    }
    
    public function criar(Request $request, Response $response): Response
{
    $data = json_decode($request->getBody()->getContents(), true) ?? [];
    
    // Verificar se já existe pelo erp_id
    if (!empty($data['erp_id'])) {
        $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE erp_id = :erp_id");
        $stmt->execute(['erp_id' => $data['erp_id']]);
        if ($stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista já cadastrado'
            ], 400);
        }
    }
    
    // 🔥 AGORA COM TODAS AS COLUNAS
    $stmt = $this->pdo->prepare("
        INSERT INTO frota_motorista (
            erp_id, nome, cpf, cnh, telefone, email, 
            endereco, bairro, cidade, uf, cep, complemento, numero,
            status, created_at, updated_at
        ) VALUES (
            :erp_id, :nome, :cpf, :cnh, :telefone, :email,
            :endereco, :bairro, :cidade, :uf, :cep, :complemento, :numero,
            :status, NOW(), NOW()
        ) RETURNING id
    ");
    
    $stmt->execute([
        'erp_id' => $data['erp_id'] ?? null,
        'nome' => $data['nome'],
        'cpf' => $data['cpf'] ?? null,
        'cnh' => $data['cnh'] ?? '',
        'telefone' => $data['telefone'] ?? null,
        'email' => $data['email'] ?? null,
        'endereco' => $data['endereco'] ?? null,
        'bairro' => $data['bairro'] ?? null,
        'cidade' => $data['cidade'] ?? null,
        'uf' => $data['uf'] ?? null,
        'cep' => $data['cep'] ?? null,
        'complemento' => $data['complemento'] ?? null,
        'numero' => $data['numero'] ?? null,
        'status' => $data['status'] ?? 'ativo'
    ]);
    
    $id = $stmt->fetchColumn();
    
    return $this->json($response, [
        'success' => true,
        'message' => 'Motorista cadastrado com sucesso',
        'data' => ['id' => $id]
    ]);
}
    
    /**
     * PUT /v1/frota/motoristas/{id}
     * Atualizar motorista
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        // Verificar se existe
        $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ], 404);
        }
        
        // Construir SET dinâmico
        $camposPermitidos = [
            'nome', 'cpf', 'cnh', 'categoria_cnh', 'data_validade_cnh',
            'telefone', 'telefone_emergencia', 'email', 'data_nascimento',
            'data_admissao', 'endereco', 'status', 'veiculo_atual_id'
        ];
        
        $sets = [];
        $bindParams = ['id' => $id];
        
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $input)) {
                $sets[] = "{$campo} = :{$campo}";
                $bindParams[$campo] = $input[$campo];
            }
        }
        
        if (empty($sets)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhum campo para atualizar'
            ], 400);
        }
        
        $sets[] = "updated_at = NOW()";
        $sql = "UPDATE frota_motorista SET " . implode(', ', $sets) . " WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindParams);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Motorista atualizado com sucesso'
        ]);
    }
    
    /**
     * DELETE /v1/frota/motoristas/{id}
     * Deletar motorista (soft delete - inativa)
     */
    public function deletar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        // Verificar se existe
        $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ], 404);
        }
        
        // Verificar se tem embarques ativos
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM frota_embarque 
            WHERE motorista_id = :id AND status IN ('planejado', 'em_andamento')
        ");
        $stmt->execute(['id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista possui embarques ativos. Não é possível excluir.'
            ], 400);
        }
        
        // Soft delete (inativar)
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET status = 'inativo', updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Motorista inativado com sucesso'
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/entregas
     * Listar entregas do motorista com filtros
     */
    public function entregas(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $params = $request->getQueryParams();
        
        $filtros = ["eb.motorista_id = :motorista_id"];
        $bindParams = ['motorista_id' => $id];
        
        if (!empty($params['status'])) {
            $filtros[] = "e.status = :status";
            $bindParams['status'] = $params['status'];
        }
        
        if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {
            $filtros[] = "DATE(e.created_at) BETWEEN :data_inicio AND :data_fim";
            $bindParams['data_inicio'] = $params['data_inicio'];
            $bindParams['data_fim'] = $params['data_fim'];
        }
        
        $where = 'WHERE ' . implode(' AND ', $filtros);
        
        $limite = (int)($params['limite'] ?? 50);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $sql = "
            SELECT 
                e.*,
                eb.numero_embarque,
                eb.data_saida,
                v.placa,
                v.modelo
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            {$where}
            ORDER BY e.created_at DESC
            LIMIT :limite OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $sqlCount = "SELECT COUNT(*) FROM frota_entrega e LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id {$where}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        foreach ($bindParams as $key => $val) {
            $stmtCount->bindValue($key, $val);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $entregas,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite)
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/entregas/hoje
     * Entregas do motorista para hoje
     */
    public function entregasHoje(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $sql = "
            SELECT 
                e.*,
                eb.numero_embarque,
                eb.data_saida,
                v.placa,
                v.modelo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                eb.id as embarque_id
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            WHERE eb.motorista_id = :motorista_id
              AND (DATE(e.created_at) = CURRENT_DATE OR e.status IN ('pendente', 'em_entrega'))
            ORDER BY e.ordem_entrega ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['motorista_id' => $id]);
        $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular métricas
        $total = count($entregas);
        $pendentes = count(array_filter($entregas, fn($e) => $e['status'] === 'pendente'));
        $emEntrega = count(array_filter($entregas, fn($e) => $e['status'] === 'em_entrega'));
        $entregues = count(array_filter($entregas, fn($e) => $e['status'] === 'entregue'));
        $falhas = count(array_filter($entregas, fn($e) => $e['status'] === 'falha'));
        
        // Buscar próxima entrega
        $proxima = null;
        foreach ($entregas as $e) {
            if ($e['status'] === 'pendente' || $e['status'] === 'em_entrega') {
                $proxima = $e;
                break;
            }
        }
        
        // Buscar rota ativa
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'em_andamento'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $rotaAtiva = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'entregas' => $entregas,
                'rota_ativa' => $rotaAtiva,
                'proxima_entrega' => $proxima,
                'resumo' => [
                    'total' => $total,
                    'pendentes' => $pendentes,
                    'em_entrega' => $emEntrega,
                    'entregues' => $entregues,
                    'falhas' => $falhas,
                    'progresso' => $total > 0 ? round(($entregues / $total) * 100, 1) : 0
                ]
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/rota-ativa
     * Buscar rota ativa do motorista
     */
    public function rotaAtiva(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $sql = "
            SELECT 
                eb.*,
                v.placa,
                v.modelo,
                v.latitude as veiculo_lat,
                v.longitude as veiculo_lng,
                COUNT(e.id) as total_entregas,
                COUNT(CASE WHEN e.status = 'entregue' THEN 1 END) as entregues,
                COUNT(CASE WHEN e.status = 'pendente' THEN 1 END) as pendentes,
                COUNT(CASE WHEN e.status = 'em_entrega' THEN 1 END) as em_andamento,
                COUNT(CASE WHEN e.status = 'falha' THEN 1 END) as falhas
            FROM frota_embarque eb
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            LEFT JOIN frota_entrega e ON e.embarque_id = eb.id
            WHERE eb.motorista_id = :motorista_id
              AND eb.status IN ('planejado', 'em_andamento')
            GROUP BY eb.id, v.placa, v.modelo, v.latitude, v.longitude
            ORDER BY eb.data_saida DESC
            LIMIT 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['motorista_id' => $id]);
        $rota = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$rota) {
            return $this->json($response, [
                'success' => true,
                'data' => null,
                'message' => 'Nenhuma rota ativa encontrada'
            ]);
        }
        
        // Buscar entregas da rota
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                c.nome as cliente_nome_completo,
                c.telefone as cliente_telefone,
                c.latitude as cliente_lat,
                c.longitude as cliente_lng
            FROM frota_entrega e
            LEFT JOIN frota_cliente c ON c.id = e.cliente_id
            WHERE e.embarque_id = :embarque_id
            ORDER BY e.ordem_entrega ASC
        ");
        $stmt->execute(['embarque_id' => $rota['id']]);
        $rota['entregas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $rota
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/rota/iniciar
     * Iniciar rota do motorista
     */
    public function iniciarRota(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        // Buscar próximo embarque planejado
        $stmt = $this->pdo->prepare("
            SELECT id, veiculo_id FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'planejado'
              AND data_saida <= CURRENT_DATE
            ORDER BY data_saida ASC
            LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhum embarque planejado encontrado'
            ], 404);
        }
        
        // Iniciar embarque
        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'em_andamento', 
                horario_saida = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $embarque['id']]);
        
        // Atualizar veículo
        $stmt = $this->pdo->prepare("
            UPDATE frota_veiculo 
            SET status = 'em_rota',
                updated_at = NOW()
            WHERE id = :veiculo_id
        ");
        $stmt->execute(['veiculo_id' => $embarque['veiculo_id']]);
        
        // Atualizar motorista
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET veiculo_atual_id = :veiculo_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'veiculo_id' => $embarque['veiculo_id']
        ]);
        
        // Registrar log
        $this->registrarLog($embarque['id'], 'iniciar_rota', 'Rota iniciada pelo motorista');
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Rota iniciada com sucesso',
            'data' => [
                'embarque_id' => $embarque['id'],
                'status' => 'em_andamento',
                'horario_saida' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/rota/finalizar
     * Finalizar rota do motorista
     */
    public function finalizarRota(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        // Buscar embarque em andamento
        $stmt = $this->pdo->prepare("
            SELECT id, veiculo_id FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'em_andamento'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhuma rota em andamento encontrada'
            ], 404);
        }
        
        // Verificar se todas as entregas foram concluídas
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total, 
                   COUNT(CASE WHEN status IN ('entregue', 'falha', 'cancelada') THEN 1 END) as finalizadas
            FROM frota_entrega
            WHERE embarque_id = :embarque_id
        ");
        $stmt->execute(['embarque_id' => $embarque['id']]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($stats['total'] > $stats['finalizadas']) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Existem entregas pendentes. Finalize todas as entregas primeiro.',
                'pendentes' => $stats['total'] - $stats['finalizadas']
            ], 400);
        }
        
        // Finalizar embarque
        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'finalizado', 
                horario_retorno = NOW(),
                data_retorno = CURRENT_DATE,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $embarque['id']]);
        
        // Atualizar veículo
        $stmt = $this->pdo->prepare("
            UPDATE frota_veiculo 
            SET status = 'disponivel',
                updated_at = NOW()
            WHERE id = :veiculo_id
        ");
        $stmt->execute(['veiculo_id' => $embarque['veiculo_id']]);
        
        // Atualizar motorista
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET veiculo_atual_id = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        
        // Registrar log
        $this->registrarLog($embarque['id'], 'finalizar_rota', 'Rota finalizada pelo motorista');
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Rota finalizada com sucesso',
            'data' => [
                'embarque_id' => $embarque['id'],
                'status' => 'finalizado',
                'horario_retorno' => date('Y-m-d H:i:s'),
                'total_entregas' => $stats['total'],
                'entregas_concluidas' => $stats['finalizadas']
            ]
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/posicao
     * Atualizar posição do motorista (GPS)
     */
    public function atualizarPosicao(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        $velocidade = (float)($input['velocidade'] ?? 0);
        $precisao = (float)($input['precisao'] ?? 0);
        
        if ($lat == 0 || $lng == 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Latitude e longitude são obrigatórios'
            ], 400);
        }
        
        // Atualizar posição do motorista
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET latitude = :lat,
                longitude = :lng,
                ultima_posicao = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'lat' => $lat,
            'lng' => $lng
        ]);
        
        // Atualizar posição do veículo
        $stmt = $this->pdo->prepare("
            UPDATE frota_veiculo 
            SET latitude = :lat,
                longitude = :lng,
                velocidade_atual = :velocidade,
                ultima_posicao = NOW()
            WHERE id = (
                SELECT veiculo_atual_id FROM frota_motorista WHERE id = :motorista_id
            )
        ");
        $stmt->execute([
            'motorista_id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'velocidade' => $velocidade
        ]);
        
        // Registrar histórico de posição
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_historico_posicao 
            (veiculo_id, motorista_id, embarque_id, latitude, longitude, velocidade, precisao, data_hora)
            VALUES (
                (SELECT veiculo_atual_id FROM frota_motorista WHERE id = :motorista_id),
                :motorista_id,
                (SELECT id FROM frota_embarque WHERE motorista_id = :motorista_id AND status = 'em_andamento' ORDER BY id DESC LIMIT 1),
                :lat,
                :lng,
                :velocidade,
                :precisao,
                NOW()
            )
        ");
        $stmt->execute([
            'motorista_id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'velocidade' => $velocidade,
            'precisao' => $precisao
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Posição atualizada com sucesso',
            'data' => [
                'motorista_id' => $id,
                'latitude' => $lat,
                'longitude' => $lng,
                'velocidade' => $velocidade,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/estatisticas
     * Estatísticas detalhadas do motorista
     */
    public function estatisticas(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $dias = (int)($request->getQueryParams()['dias'] ?? 30);
        
        // Usar função PostgreSQL
        $stmt = $this->pdo->prepare("SELECT * FROM calcular_estatisticas_motorista(:motorista_id, :dias)");
        $stmt->execute([
            'motorista_id' => $id,
            'dias' => $dias
        ]);
        $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$estatisticas) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Motorista não encontrado ou sem dados'
            ], 404);
        }
        
        // Buscar entregas por dia (últimos 30 dias)
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(e.horario_entrega) as data,
                COUNT(*) as total,
                SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)) as valor,
                AVG(EXTRACT(EPOCH FROM (e.horario_entrega - e.horario_checkin))/60) as tempo_medio
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            WHERE eb.motorista_id = :motorista_id
              AND e.status = 'entregue'
              AND e.horario_entrega >= NOW() - INTERVAL '30 days'
            GROUP BY DATE(e.horario_entrega)
            ORDER BY data DESC
        ");
        $stmt->execute(['motorista_id' => $id]);
        $entregasPorDia = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'resumo' => $estatisticas,
                'entregas_por_dia' => $entregasPorDia,
                'periodo' => [
                    'dias' => $dias,
                    'data_inicio' => date('Y-m-d', strtotime("-{$dias} days")),
                    'data_fim' => date('Y-m-d')
                ]
            ]
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/ocorrencia
     * Registrar ocorrência do motorista
     */
    public function registrarOcorrencia(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $tipo = $input['tipo'] ?? '';
        $descricao = $input['descricao'] ?? '';
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        
        if (empty($tipo) || empty($descricao)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Tipo e descrição são obrigatórios'
            ], 400);
        }
        
        $tiposValidos = ['desvio_rota', 'endereco_incorreto', 'cliente_ausente', 'transito', 'veiculo_problema', 'acidente', 'outro'];
        if (!in_array($tipo, $tiposValidos)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Tipo inválido. Opções: ' . implode(', ', $tiposValidos)
            ], 400);
        }
        
        // Buscar embarque ativo
        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_embarque 
            WHERE motorista_id = :motorista_id 
              AND status = 'em_andamento'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['motorista_id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $sql = "
            INSERT INTO frota_ocorrencia 
            (embarque_id, motorista_id, tipo, descricao, latitude, longitude, status, created_at)
            VALUES (:embarque_id, :motorista_id, :tipo, :descricao, :lat, :lng, 'aberta', NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'embarque_id' => $embarque['id'] ?? null,
            'motorista_id' => $id,
            'tipo' => $tipo,
            'descricao' => $descricao,
            'lat' => $lat,
            'lng' => $lng
        ]);
        
        $ocorrenciaId = $this->pdo->lastInsertId();
        
        // Notificar gestor via WebSocket
        $this->enviarNotificacaoWS([
            'tipo' => 'ocorrencia',
            'ocorrencia_id' => $ocorrenciaId,
            'motorista_id' => $id,
            'tipo_ocorrencia' => $tipo,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Ocorrência registrada com sucesso',
            'id' => $ocorrenciaId
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/notificacao
     * Enviar notificação para o motorista
     */
    public function enviarNotificacao(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $titulo = $input['titulo'] ?? '';
        $mensagem = $input['mensagem'] ?? '';
        $tipo = $input['tipo'] ?? 'sistema';
        
        if (empty($titulo) || empty($mensagem)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Título e mensagem são obrigatórios'
            ], 400);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_notificacao 
            (motorista_id, tipo, titulo, mensagem, created_at)
            VALUES (:motorista_id, :tipo, :titulo, :mensagem, NOW())
        ");
        $stmt->execute([
            'motorista_id' => $id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem
        ]);
        
        $notificacaoId = $this->pdo->lastInsertId();
        
        // Enviar push notification se tiver device_token
        $this->enviarPushNotification($id, $titulo, $mensagem);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Notificação enviada com sucesso',
            'id' => $notificacaoId
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/notificacoes
     * Listar notificações do motorista
     */
    public function getNotificacoes(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $limite = (int)($request->getQueryParams()['limite'] ?? 20);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_notificacao 
            WHERE motorista_id = :motorista_id
            ORDER BY created_at DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':motorista_id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        
        $notificacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmtCount = $this->pdo->prepare("
            SELECT COUNT(*) FROM frota_notificacao 
            WHERE motorista_id = :motorista_id AND lida = false
        ");
        $stmtCount->execute(['motorista_id' => $id]);
        $naoLidas = (int)$stmtCount->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $notificacoes,
            'nao_lidas' => $naoLidas
        ]);
    }
    
    /**
     * PUT /v1/frota/motoristas/{id}/notificacoes/{notif_id}/ler
     * Marcar notificação como lida
     */
    public function marcarNotificacaoLida(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $notifId = (int)$args['notif_id'];
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_notificacao 
            SET lida = true, lida_em = NOW()
            WHERE id = :id AND motorista_id = :motorista_id
        ");
        $stmt->execute(['id' => $notifId, 'motorista_id' => $id]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/jornada/iniciar
     * Iniciar jornada de trabalho
     */
    public function iniciarJornada(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        
        // Verificar se já tem jornada aberta
        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_jornada 
            WHERE motorista_id = :motorista_id AND data_fim IS NULL
        ");
        $stmt->execute(['motorista_id' => $id]);
        if ($stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Já existe uma jornada em aberto'
            ], 400);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_jornada 
            (motorista_id, data_inicio, horario_inicio, latitude_inicio, longitude_inicio, created_at)
            VALUES (:motorista_id, CURRENT_DATE, NOW(), :lat, :lng, NOW())
        ");
        $stmt->execute([
            'motorista_id' => $id,
            'lat' => $lat,
            'lng' => $lng
        ]);
        
        $jornadaId = $this->pdo->lastInsertId();
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Jornada iniciada com sucesso',
            'id' => $jornadaId,
            'data_inicio' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * POST /v1/frota/motoristas/{id}/jornada/finalizar
     * Finalizar jornada de trabalho
     */
    public function finalizarJornada(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);
        $kmRodados = (float)($input['km_rodados'] ?? 0);
        $observacoes = $input['observacoes'] ?? '';
        
        // Buscar jornada aberta
        $stmt = $this->pdo->prepare("
            SELECT id FROM frota_jornada 
            WHERE motorista_id = :motorista_id AND data_fim IS NULL
        ");
        $stmt->execute(['motorista_id' => $id]);
        $jornada = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$jornada) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhuma jornada em aberto encontrada'
            ], 404);
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_jornada 
            SET data_fim = CURRENT_DATE,
                horario_fim = NOW(),
                latitude_fim = :lat,
                longitude_fim = :lng,
                km_rodados = :km,
                observacoes = :obs,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $jornada['id'],
            'lat' => $lat,
            'lng' => $lng,
            'km' => $kmRodados,
            'obs' => $observacoes
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Jornada finalizada com sucesso',
            'data' => [
                'id' => $jornada['id'],
                'data_fim' => date('Y-m-d H:i:s'),
                'km_rodados' => $kmRodados
            ]
        ]);
    }
    
    /**
     * GET /v1/frota/motoristas/{id}/jornada/historico
     * Histórico de jornadas do motorista
     */
    public function historicoJornada(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $limite = (int)($request->getQueryParams()['limite'] ?? 30);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_jornada 
            WHERE motorista_id = :motorista_id
            ORDER BY data_inicio DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':motorista_id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();
        
        $jornadas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular total de horas e km
        $totalHoras = 0;
        $totalKm = 0;
        foreach ($jornadas as $j) {
            if ($j['horario_inicio'] && $j['horario_fim']) {
                $inicio = new \DateTime($j['horario_inicio']);
                $fim = new \DateTime($j['horario_fim']);
                $totalHoras += ($fim->getTimestamp() - $inicio->getTimestamp()) / 3600;
            }
            $totalKm += (float)$j['km_rodados'];
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $jornadas,
            'resumo' => [
                'total_jornadas' => count($jornadas),
                'total_horas' => round($totalHoras, 1),
                'total_km' => round($totalKm, 1),
                'media_horas_dia' => count($jornadas) > 0 ? round($totalHoras / count($jornadas), 1) : 0
            ]
        ]);
    }
    
    // ========================================================================
    // MÉTODOS AUXILIARES
    // ========================================================================
    
    private function validarCPF($cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) != 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }
    
    private function limparNumeros($valor): string
    {
        return preg_replace('/[^0-9]/', '', $valor);
    }
    
    private function registrarLog($embarqueId, $acao, $descricao)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO frota_log_embarque (embarque_id, acao, descricao, usuario_id, data_hora)
                VALUES (:embarque_id, :acao, :descricao, :usuario_id, NOW())
            ");
            $stmt->execute([
                'embarque_id' => $embarqueId,
                'acao' => $acao,
                'descricao' => $descricao,
                'usuario_id' => $_SESSION['user_id'] ?? 0
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar log: ' . $e->getMessage());
        }
    }
    
    private function enviarNotificacaoWS($dados)
    {
        // TODO: Implementar WebSocket
        error_log('WebSocket: ' . json_encode($dados));
    }
    
    private function enviarPushNotification($motoristaId, $titulo, $mensagem)
    {
        // Buscar device_token do motorista
        $stmt = $this->pdo->prepare("SELECT device_token FROM frota_motorista WHERE id = :id");
        $stmt->execute(['id' => $motoristaId]);
        $motorista = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($motorista && !empty($motorista['device_token'])) {
            // TODO: Implementar FCM/APNS
            error_log("Push Notification para {$motoristaId}: {$titulo} - {$mensagem}");
        }
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