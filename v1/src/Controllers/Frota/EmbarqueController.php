<?php
// src/Controllers/Frota/EmbarqueController.php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\Frota\OtimizadorRotasService;
use Nutricional\Services\Frota\GeolocalizacaoService;

class EmbarqueController
{
    private $pdo;
    private $otimizadorRotas;
    private $geolocalizacaoService;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->otimizadorRotas = new OtimizadorRotasService($this->pdo);
        $this->geolocalizacaoService = new GeolocalizacaoService($this->pdo);
    }
    
    /**
     * GET /v1/frota/embarques
     * Listar embarques com filtros
     */
    public function listar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        
        $filtros = [];
        $bindParams = [];
        
        if (!empty($params['status'])) {
            $filtros[] = "e.status = :status";
            $bindParams['status'] = $params['status'];
        }
        
        if (!empty($params['motorista_id'])) {
            $filtros[] = "e.motorista_id = :motorista_id";
            $bindParams['motorista_id'] = (int)$params['motorista_id'];
        }
        
        if (!empty($params['veiculo_id'])) {
            $filtros[] = "e.veiculo_id = :veiculo_id";
            $bindParams['veiculo_id'] = (int)$params['veiculo_id'];
        }
        
        if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {
            $filtros[] = "e.data_saida BETWEEN :data_inicio AND :data_fim";
            $bindParams['data_inicio'] = $params['data_inicio'];
            $bindParams['data_fim'] = $params['data_fim'];
        }
        
        if (!empty($params['busca'])) {
            $filtros[] = "(e.numero_embarque ILIKE :busca OR m.nome ILIKE :busca2 OR v.placa ILIKE :busca3)";
            $bindParams['busca'] = "%{$params['busca']}%";
            $bindParams['busca2'] = "%{$params['busca']}%";
            $bindParams['busca3'] = "%{$params['busca']}%";
        }
        
        $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
        
        $limite = (int)($params['limite'] ?? 20);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $sql = "
            SELECT 
                e.*,
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                v.marca as veiculo_marca,
                v.cor as veiculo_cor,
                v.tipo as veiculo_tipo,
                m.nome as motorista_nome,
                m.telefone as motorista_telefone,
                COUNT(DISTINCT ent.id) as total_entregas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue' THEN ent.id END) as entregas_concluidas,
                COUNT(DISTINCT CASE WHEN ent.status = 'pendente' THEN ent.id END) as entregas_pendentes,
                COUNT(DISTINCT CASE WHEN ent.status = 'falha' THEN ent.id END) as entregas_falha,
                COALESCE(SUM(ent.valor), 0) as valor_total_entregas,
                COALESCE(SUM(CASE WHEN ent.status = 'entregue' THEN ent.valor END), 0) as valor_entregue,
                COALESCE(SUM(ent.peso_total), 0) as peso_total_entregas
            FROM frota_embarque e
            LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = e.motorista_id
            LEFT JOIN frota_entrega ent ON ent.embarque_id = e.id
            {$where}
            GROUP BY e.id, v.placa, v.modelo, v.marca, v.cor, v.tipo, m.nome, m.telefone
            ORDER BY e.data_saida DESC, e.id DESC
            LIMIT :limite OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $embarques = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $sqlCount = "SELECT COUNT(*) FROM frota_embarque e {$where}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        foreach ($bindParams as $key => $val) {
            $stmtCount->bindValue($key, $val);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $embarques,
            'pagination' => [
                'total' => $total,
                'pagina' => $pagina,
                'limite' => $limite,
                'total_paginas' => ceil($total / $limite)
            ]
        ]);
    }
    

/**
 * GET /v1/frota/embarques/{id}
 * Buscar embarque específico com entregas, histórico e checklist
 */
public function buscar(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];

    // ================================================================
    // 1. DADOS PRINCIPAIS DO EMBARQUE
    // ================================================================
    $sql = "
        SELECT 
            e.*,
            v.placa as veiculo_placa,
            v.modelo as veiculo_modelo,
            v.marca as veiculo_marca,
            v.cor as veiculo_cor,
            v.tipo as veiculo_tipo,
            m.id as motorista_id,
            m.nome as motorista_nome,
            m.telefone as motorista_telefone,
            m.cpf as motorista_cpf,
            COUNT(DISTINCT ent.id) as total_entregas,
            COUNT(DISTINCT CASE WHEN ent.status = 'entregue' THEN ent.id END) as entregas_concluidas,
            COUNT(DISTINCT CASE WHEN ent.status = 'pendente' THEN ent.id END) as entregas_pendentes,
            COUNT(DISTINCT CASE WHEN ent.status = 'falha' THEN ent.id END) as entregas_falha,
            COALESCE(SUM(ent.valor), 0) as valor_total_entregas,
            COALESCE(SUM(CASE WHEN ent.status = 'entregue' THEN ent.valor END), 0) as valor_entregue
        FROM frota_embarque e
        LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
        LEFT JOIN frota_motorista m ON m.id = e.motorista_id
        LEFT JOIN frota_entrega ent ON ent.embarque_id = e.id
        WHERE e.id = :id
        GROUP BY e.id, v.placa, v.modelo, v.marca, v.cor, v.tipo, m.id, m.nome, m.telefone, m.cpf
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$embarque) {
        return $this->json($response, ['success' => false, 'error' => 'Embarque não encontrado'], 404);
    }

    // ================================================================
    // 2. BUSCAR ENTREGAS
    // ================================================================
    $stmtEntregas = $this->pdo->prepare("
        SELECT 
            e.*,
            c.nome as cliente_nome,
            c.telefone as cliente_telefone,
            c.endereco as cliente_endereco,
            c.cidade as cliente_cidade,
            c.uf as cliente_uf
        FROM frota_entrega e
        LEFT JOIN frota_cliente c ON c.id = e.cliente_id
        WHERE e.embarque_id = :embarque_id
        ORDER BY e.ordem_entrega ASC
    ");
    $stmtEntregas->execute(['embarque_id' => $id]);
    $entregas = $stmtEntregas->fetchAll(\PDO::FETCH_ASSOC);
    $embarque['entregas'] = $entregas;

    // ================================================================
    // 3. BUSCAR CHECKLIST (com fotos dos itens) para cada entrega
    // ================================================================
    if (!empty($entregas)) {
        $entregaIds = array_column($entregas, 'id');
        $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));

        $stmtChecklist = $this->pdo->prepare("
            SELECT 
                entrega_id,
                item_id,
                referencia,
                descricao,
                foto_url,
                quantidade_prevista,
                quantidade_entregue,
                status,
                motivo
            FROM frota_checklist_entrega
            WHERE entrega_id IN ({$placeholders})
        ");
        $stmtChecklist->execute($entregaIds);
        $checklistItems = $stmtChecklist->fetchAll(\PDO::FETCH_ASSOC);

        // Agrupar checklist por entrega
        $checklistPorEntrega = [];
        foreach ($checklistItems as $item) {
            $checklistPorEntrega[$item['entrega_id']][] = $item;
        }

        // Adicionar checklist a cada entrega
        foreach ($embarque['entregas'] as &$entrega) {
            $entrega['checklist'] = $checklistPorEntrega[$entrega['id']] ?? [];
        }
        unset($entrega);
    }

    // ================================================================
    // 4. BUSCAR HISTÓRICO (logs do embarque)
    // ================================================================
    $stmtHistorico = $this->pdo->prepare("       
        SELECT 
            l.*,
            u.username as usuario_nome
        FROM frota_log_embarque l
        LEFT JOIN usuario u ON u.idusuario = l.usuario_id
        WHERE l.embarque_id = :embarque_id
        ORDER BY l.data_hora DESC
    ");
    $stmtHistorico->execute(['embarque_id' => $id]);
    $embarque['historico'] = $stmtHistorico->fetchAll(\PDO::FETCH_ASSOC);

    // ================================================================
    // 5. HISTÓRICO DE POSIÇÕES (opcional)
    // ================================================================
    $stmtPosicoes = $this->pdo->prepare("
        SELECT 
            latitude,
            longitude,
            velocidade,
            data_hora
        FROM frota_historico_posicao
        WHERE embarque_id = :embarque_id
          AND data_hora >= NOW() - INTERVAL '1 hour'
        ORDER BY data_hora ASC
    ");
    $stmtPosicoes->execute(['embarque_id' => $id]);
    $embarque['historico_posicoes'] = $stmtPosicoes->fetchAll(\PDO::FETCH_ASSOC);

    // ================================================================
    // 6. RESPOSTA
    // ================================================================
    return $this->json($response, [
        'success' => true,
        'data' => $embarque
    ]);
}


    
    public function otimizarRota(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        try {
            $resultado = $this->otimizadorRotas->otimizar($id);
            
            if (!$resultado['success']) {
                return $this->json($response, $resultado, 400);
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $resultado['data'],
                'rota' => $resultado['rota'],
                'metricas' => $resultado['metricas']
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
/**
 * POST /v1/frota/embarques/{id}/iniciar
 * Iniciar um embarque
 */
public function iniciar(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;

    try {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare("SELECT * FROM frota_embarque WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$embarque) {
            return $this->json($response, ['success' => false, 'error' => 'Embarque não encontrado'], 404);
        }

        if ($embarque['status'] !== 'planejado') {
            return $this->json($response, [
                'success' => false,
                'error' => 'Embarque não pode ser iniciado. Status atual: ' . $embarque['status']
            ], 400);
        }

        // 🔥 VERIFICAR SE VEÍCULO JÁ ESTÁ EM ROTA
        if (!empty($embarque['veiculo_id'])) {
            $stmt = $this->pdo->prepare("
                SELECT e.id, e.numero_embarque, e.status 
                FROM frota_embarque e
                WHERE e.veiculo_id = :veiculo_id 
                  AND e.status = 'em_andamento'
                  AND e.id != :embarque_id
            ");
            $stmt->execute([
                'veiculo_id' => $embarque['veiculo_id'],
                'embarque_id' => $id
            ]);
            $veiculoEmRota = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($veiculoEmRota) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Veículo já está em rota no embarque #' . $veiculoEmRota['numero_embarque'] . '. Finalize ou cancele antes de iniciar este embarque.'
                ], 400);
            }
        }

        // 🔥 VERIFICAR SE MOTORISTA JÁ ESTÁ EM ROTA
        if (!empty($embarque['motorista_id'])) {
            $stmt = $this->pdo->prepare("
                SELECT e.id, e.numero_embarque, e.status 
                FROM frota_embarque e
                WHERE e.motorista_id = :motorista_id 
                  AND e.status = 'em_andamento'
                  AND e.id != :embarque_id
            ");
            $stmt->execute([
                'motorista_id' => $embarque['motorista_id'],
                'embarque_id' => $id
            ]);
            $motoristaEmRota = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($motoristaEmRota) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Motorista já está em rota no embarque #' . $motoristaEmRota['numero_embarque'] . '. Finalize ou cancele antes de iniciar este embarque.'
                ], 400);
            }
        }

        // Atualizar status do embarque
        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'em_andamento', 
                horario_saida = CURRENT_TIME,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        // Atualizar veículo (se tiver)
        if (!empty($embarque['veiculo_id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE frota_veiculo 
                SET status = 'em_rota',
                    updated_at = NOW()
                WHERE id = :veiculo_id
            ");
            $stmt->execute(['veiculo_id' => $embarque['veiculo_id']]);
        }

        // Atualizar motorista (se tiver)
        if (!empty($embarque['motorista_id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE frota_motorista 
                SET veiculo_atual_id = :veiculo_id,
                    updated_at = NOW()
                WHERE id = :motorista_id
            ");
            $stmt->execute([
                'motorista_id' => $embarque['motorista_id'],
                'veiculo_id' => $embarque['veiculo_id'] ?? null
            ]);
        }

        // Gerar códigos de rastreamento
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega 
            SET codigo_rastreamento = gerar_codigo_rastreamento(),
                status = 'pendente',
                updated_at = NOW()
            WHERE embarque_id = :embarque_id
              AND codigo_rastreamento IS NULL
        ");
        $stmt->execute(['embarque_id' => $id]);

        $this->pdo->commit();

        $this->registrarLog($id, 'iniciar', 'Embarque iniciado', $usuarioId);

        return $this->json($response, [
            'success' => true,
            'message' => 'Embarque iniciado com sucesso',
            'data' => $embarque
        ]);

    } catch (\Exception $e) {
        $this->pdo->rollBack();
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
   /**
 * POST /v1/frota/embarques/{id}/finalizar
 * Finalizar embarque
 */
public function finalizar(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;

    try {
        $this->pdo->beginTransaction();

        // 🔥 CORRIGIDO: Incluir 'entregue_com_problema' como finalizada
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total, 
                COUNT(CASE WHEN status IN ('entregue', 'falha', 'cancelada', 'entregue_com_problema') THEN 1 END) as finalizadas
            FROM frota_entrega
            WHERE embarque_id = :embarque_id
        ");
        $stmt->execute(['embarque_id' => $id]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($stats['total'] > $stats['finalizadas']) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Existem entregas pendentes. Finalize todas as entregas antes de concluir o embarque.',
                'pendentes' => $stats['total'] - $stats['finalizadas']
            ], 400);
        }

        // Atualizar status do embarque
        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'finalizado',
                data_retorno = CURRENT_DATE,
                horario_retorno = CURRENT_TIME,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        // Liberar veículo
        $stmt = $this->pdo->prepare("
            UPDATE frota_veiculo 
            SET status = 'disponivel',
                updated_at = NOW()
            WHERE id = (SELECT veiculo_id FROM frota_embarque WHERE id = :embarque_id)
        ");
        $stmt->execute(['embarque_id' => $id]);

        // Liberar motorista
        $stmt = $this->pdo->prepare("
            UPDATE frota_motorista 
            SET veiculo_atual_id = NULL,
                updated_at = NOW()
            WHERE id = (SELECT motorista_id FROM frota_embarque WHERE id = :embarque_id)
        ");
        $stmt->execute(['embarque_id' => $id]);

        $this->pdo->commit();

        $this->registrarLog($id, 'finalizar', 'Embarque finalizado', $usuarioId);

        return $this->json($response, [
            'success' => true,
            'message' => 'Embarque finalizado com sucesso'
        ]);

    } catch (\Exception $e) {
        $this->pdo->rollBack();
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * POST /v1/frota/embarques/{id}/cancelar
     * Cancelar um embarque
     */
   public function cancelar(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;

    try {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare("SELECT * FROM frota_embarque WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Embarque não encontrado'
            ], 404);
        }

        if ($embarque['status'] === 'finalizado') {
            return $this->json($response, [
                'success' => false,
                'error' => 'Não é possível cancelar um embarque finalizado'
            ], 400);
        }

        $stmt = $this->pdo->prepare("
            UPDATE frota_embarque 
            SET status = 'cancelado', 
                updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        if ($embarque['status'] === 'em_andamento') {
            $stmt = $this->pdo->prepare("
                UPDATE frota_veiculo 
                SET status = 'disponivel', 
                    updated_at = NOW() 
                WHERE id = :veiculo_id
            ");
            $stmt->execute(['veiculo_id' => $embarque['veiculo_id']]);

            if ($embarque['motorista_id']) {
                $stmt = $this->pdo->prepare("
                    UPDATE frota_motorista 
                    SET veiculo_atual_id = NULL, 
                        updated_at = NOW() 
                    WHERE id = :motorista_id
                ");
                $stmt->execute(['motorista_id' => $embarque['motorista_id']]);
            }
        }

        $this->pdo->commit();

        $this->registrarLog($id, 'cancelar', 'Embarque cancelado', $usuarioId);

        return $this->json($response, [
            'success' => true,
            'message' => 'Embarque cancelado com sucesso'
        ]);

    } catch (\Exception $e) {
        $this->pdo->rollBack();
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * PUT /v1/frota/embarques/{id}
 * Atualizar dados do embarque
 */
public function atualizar(Request $request, Response $response, array $args): Response
{
    $id = (int)$args['id'];
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;

    $pdo = $this->pdo;

    // Verificar existência
    $stmt = $pdo->prepare("SELECT id FROM frota_embarque WHERE id = :id");
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        return $this->json($response, ['success' => false, 'error' => 'Embarque não encontrado'], 404);
    }

    $fields = [];
    $params = ['id' => $id];

    if (isset($input['nome_embarque'])) {
        $fields[] = "nome_embarque = :nome";
        $params['nome'] = $input['nome_embarque'];
    }
    if (isset($input['veiculo_id'])) {
        $fields[] = "veiculo_id = :veiculo_id";
        $params['veiculo_id'] = (int)$input['veiculo_id'];
    }
    if (isset($input['motorista_id'])) {
        $fields[] = "motorista_id = :motorista_id";
        $params['motorista_id'] = (int)$input['motorista_id'];
    }
    if (isset($input['data_saida'])) {
        $fields[] = "data_saida = :data_saida";
        $params['data_saida'] = $input['data_saida'];
    }
    if (isset($input['status'])) {
        // 🔥 VALIDAÇÃO DO STATUS
        $statusPermitidos = ['planejado', 'em_andamento', 'finalizado', 'cancelado', 'problema'];
        if (!in_array($input['status'], $statusPermitidos)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Status inválido. Permitidos: ' . implode(', ', $statusPermitidos)
            ], 400);
        }
        $fields[] = "status = :status";
        $params['status'] = $input['status'];
    }

    if (empty($fields)) {
        return $this->json($response, ['success' => false, 'error' => 'Nenhum campo para atualizar'], 400);
    }

    $sql = "UPDATE frota_embarque SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $this->registrarLog($id, 'editar', 'Embarque atualizado', $usuarioId);

    return $this->json($response, ['success' => true, 'message' => 'Embarque atualizado com sucesso']);
}

/**
 * Atualiza o status do embarque com validação
 */
private function atualizarStatusEmbarque($embarqueId, $novoStatus)
{
    $statusPermitidos = ['planejado', 'em_andamento', 'finalizado', 'cancelado', 'problema'];
    
    if (!in_array($novoStatus, $statusPermitidos)) {
        error_log("[Embarque] Status inválido: {$novoStatus}");
        return false;
    }
    
    $stmt = $this->pdo->prepare("
        UPDATE frota_embarque 
        SET status = :status, updated_at = NOW() 
        WHERE id = :id
    ");
    return $stmt->execute(['status' => $novoStatus, 'id' => $embarqueId]);
}

    /**
     * DELETE /v1/frota/embarques/{embarqueId}/entregas/{entregaId}
     * Remover uma entrega do embarque
     */
    public function removerEntrega(Request $request, Response $response, array $args): Response
{
    $embarqueId = (int)$args['embarqueId'];
    $entregaId = (int)$args['entregaId'];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;

    error_log("[Embarque-DELETE] ===== INÍCIO removerEntrega ===== ");
    error_log("[Embarque-DELETE] embarqueId: {$embarqueId}, entregaId: {$entregaId}");

    try {
        $pdo = $this->pdo;

        $stmt = $pdo->prepare("SELECT id, embarque_id FROM frota_entrega WHERE id = :id AND embarque_id = :embarque_id");
        $stmt->execute(['id' => $entregaId, 'embarque_id' => $embarqueId]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entrega) {
            error_log("[Embarque-DELETE] ❌ Entrega #{$entregaId} não encontrada no embarque #{$embarqueId}");
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada no embarque'
            ], 404);
        }

        error_log("[Embarque-DELETE] ✅ Entrega encontrada: " . json_encode($entrega));

        $stmtDelete = $pdo->prepare("DELETE FROM frota_entrega WHERE id = :id");
        $stmtDelete->execute(['id' => $entregaId]);

        $rowsAffected = $stmtDelete->rowCount();
        error_log("[Embarque-DELETE] 🗑️ Linhas afetadas: {$rowsAffected}");

        if ($rowsAffected === 0) {
            error_log("[Embarque-DELETE] ⚠️ Nenhuma linha deletada");
            return $this->json($response, [
                'success' => false,
                'error' => 'Não foi possível remover a entrega'
            ], 500);
        }

        $this->registrarLog($embarqueId, 'remover_entrega', "Entrega #{$entregaId} removida", $usuarioId);

        error_log("[Embarque-DELETE] ✅ Sucesso! Entrega #{$entregaId} removida do embarque #{$embarqueId}");
        error_log("[Embarque-DELETE] ===== FIM removerEntrega ===== ");

        return $this->json($response, [
            'success' => true,
            'message' => 'Entrega removida com sucesso'
        ]);

    } catch (\Exception $e) {
        error_log("[Embarque-DELETE] ❌ ERRO: " . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro interno: ' . $e->getMessage()
        ], 500);
    }
}

   /**
 * POST /v1/frota/embarques/{id}/adicionar-embarque-erp
 * Adiciona todos os pedidos de um embarque ERP a um embarque/grupo existente
 */
public function adicionarEmbarqueERP(Request $request, Response $response, array $args): Response
{
    $embarqueId = (int)$args['id'];
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $erpEmbarqueId = (int)($input['erp_embarque_id'] ?? 0);

    if ($erpEmbarqueId <= 0) {
        return $this->json($response, ['success' => false, 'error' => 'ID do embarque ERP é obrigatório'], 400);
    }

    try {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        // ----------------------------------------------------------------
        // 1. Buscar dados atuais do grupo
        // ----------------------------------------------------------------
        $stmtGrupo = $pdo->prepare("
            SELECT erp_ids_agrupados, total_embarques_agrupados, nome_embarque 
            FROM frota_embarque 
            WHERE id = :id
        ");
        $stmtGrupo->execute(['id' => $embarqueId]);
        $grupoAtual = $stmtGrupo->fetch(\PDO::FETCH_ASSOC);

        if (!$grupoAtual) {
            return $this->json($response, ['success' => false, 'error' => 'Embarque destino não encontrado'], 404);
        }

        // ----------------------------------------------------------------
        // 2. Buscar pedidos do ERP
        // ----------------------------------------------------------------
        $stmt = $pdo->prepare("
            SELECT 
                p.idpedido,
                p.idcliforemp,
                p.valortotalpedido,
                c.fantasia as cliente_nome,
                c.razao as cliente_razao,
                c.endereco,
                c.numero,
                c.bairro,
                c.uf,
                c.cep,
                c.fone as telefone,
                cid.descricao as cidade_nome,
                ce.latitude,
                ce.longitude
            FROM pedido p
            LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN cliforemp_endereco ce ON ce.idcliforemp = c.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            WHERE p.idembarque = :idembarque
              AND p.status IN (4, 5)
        ");
        $stmt->execute(['idembarque' => $erpEmbarqueId]);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($pedidos)) {
            return $this->json($response, ['success' => false, 'error' => 'Nenhum pedido encontrado no embarque ERP'], 400);
        }

        // ----------------------------------------------------------------
        // 3. Agrupar pedidos por cliente
        // ----------------------------------------------------------------
        $grupos = [];
        foreach ($pedidos as $pedido) {
            $clienteId = $pedido['idcliforemp'];
            if (!isset($grupos[$clienteId])) {
                $grupos[$clienteId] = [
                    'cliente' => [
                        'idcliforemp' => $clienteId,
                        'nome' => $pedido['cliente_nome'] ?? $pedido['cliente_razao'] ?? 'Cliente',
                        'endereco' => $pedido['endereco'] ?? '',
                        'numero' => $pedido['numero'] ?? '',
                        'bairro' => $pedido['bairro'] ?? '',
                        'cidade' => $pedido['cidade_nome'] ?? '',
                        'uf' => $pedido['uf'] ?? '',
                        'cep' => $pedido['cep'] ?? '',
                        'telefone' => $pedido['telefone'] ?? '',
                        'latitude' => $pedido['latitude'] ?? null,
                        'longitude' => $pedido['longitude'] ?? null,
                    ],
                    'pedidos' => []
                ];
            }
            $grupos[$clienteId]['pedidos'][] = $pedido;
        }

        // ----------------------------------------------------------------
        // 4. Geolocalizar clientes sem coordenadas
        // ----------------------------------------------------------------
        $geolocalizacao = $this->geolocalizacaoService;
        foreach ($grupos as $clienteId => &$dados) {
            if (empty($dados['cliente']['latitude']) || empty($dados['cliente']['longitude'])) {
                $resultado = $geolocalizacao->buscarNaFrotaCliente($clienteId);
                if ($resultado['success']) {
                    $dados['cliente']['latitude'] = $resultado['latitude'];
                    $dados['cliente']['longitude'] = $resultado['longitude'];
                    continue;
                }

                $enderecoCompleto = trim(
                    ($dados['cliente']['endereco'] ?? '') .
                    (!empty($dados['cliente']['numero']) ? ', ' . $dados['cliente']['numero'] : '') .
                    (!empty($dados['cliente']['bairro']) ? ', ' . $dados['cliente']['bairro'] : '') .
                    ', ' . ($dados['cliente']['cidade'] ?? '') .
                    ', ' . ($dados['cliente']['uf'] ?? '') .
                    (!empty($dados['cliente']['cep']) ? ', CEP: ' . $dados['cliente']['cep'] : '')
                );

                if (!empty($enderecoCompleto)) {
                    $resultado = $geolocalizacao->buscarNoGoogleMaps($enderecoCompleto);
                    if ($resultado['success']) {
                        $dados['cliente']['latitude'] = $resultado['latitude'];
                        $dados['cliente']['longitude'] = $resultado['longitude'];
                    }
                }
            }
        }

        // ----------------------------------------------------------------
        // 5. Criar/atualizar entregas
        // ----------------------------------------------------------------
        $novoErpIdsString = $grupoAtual['erp_ids_agrupados'] 
            ? $grupoAtual['erp_ids_agrupados'] . ',' . $erpEmbarqueId 
            : (string)$erpEmbarqueId;

        foreach ($grupos as $clienteId => $dados) {
            // Verificar se já existe entrega para este cliente
            $stmt = $pdo->prepare("
                SELECT id FROM frota_entrega 
                WHERE embarque_id = :embarque_id AND cliente_id = (
                    SELECT id FROM frota_cliente WHERE erp_id = :erp_id
                )
            ");
            $stmt->execute(['embarque_id' => $embarqueId, 'erp_id' => $clienteId]);
            $entregaExistente = $stmt->fetch(\PDO::FETCH_ASSOC);

            $clienteFrotaId = $this->buscarOuCriarClienteFrota($dados['cliente']);

            if ($entregaExistente) {
                // Atualizar entrega existente
                $entregaId = $entregaExistente['id'];
                $stmt = $pdo->prepare("SELECT pedidos_ids, erp_embarques_ids FROM frota_entrega WHERE id = :id");
                $stmt->execute(['id' => $entregaId]);
                $atual = $stmt->fetch(\PDO::FETCH_ASSOC);
                $pedidosIdsAtuais = $atual ? explode(',', $atual['pedidos_ids']) : [];
                $novosPedidosIds = array_column($dados['pedidos'], 'idpedido');
                $todosPedidosIds = array_merge($pedidosIdsAtuais, $novosPedidosIds);
                $todosPedidosIds = array_unique($todosPedidosIds);

                // Atualizar erp_embarques_ids na entrega
                $erpEmbarquesAtuais = $atual['erp_embarques_ids'] ?? '';
                $erpEmbarquesNovos = $erpEmbarquesAtuais 
                    ? $erpEmbarquesAtuais . ',' . $erpEmbarqueId 
                    : (string)$erpEmbarqueId;

                $novoValor = $this->calcularValorPedidos($todosPedidosIds);
                $novoPeso = $this->calcularPesoPedidos($todosPedidosIds);

                $stmt = $pdo->prepare("
                    UPDATE frota_entrega 
                    SET pedidos_ids = :pedidos_ids,
                        valor_total = :valor,
                        peso_total = :peso,
                        total_pedidos_agrupados = :total_pedidos,
                        latitude = COALESCE(:latitude, latitude),
                        longitude = COALESCE(:longitude, longitude),
                        erp_embarques_ids = :erp_embarques_ids,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'pedidos_ids' => implode(',', $todosPedidosIds),
                    'valor' => $novoValor,
                    'peso' => $novoPeso,
                    'total_pedidos' => count($todosPedidosIds),
                    'latitude' => $dados['cliente']['latitude'],
                    'longitude' => $dados['cliente']['longitude'],
                    'erp_embarques_ids' => $erpEmbarquesNovos,
                    'id' => $entregaId
                ]);
                error_log("[Embarque] Pedidos adicionados à entrega #{$entregaId}");
            } else {
                // Criar nova entrega com o erp_embarques_ids atualizado
                $this->criarEntregaParaCliente($embarqueId, $clienteFrotaId, $dados['pedidos'], $novoErpIdsString);
            }
        }

        // ----------------------------------------------------------------
        // 6. Atualizar os campos do grupo
        // ----------------------------------------------------------------
        $novoTotalEmbarques = (int)$grupoAtual['total_embarques_agrupados'] + 1;

        // (Opcional) Atualizar o nome do grupo para incluir o novo ID
        $novoNome = $grupoAtual['nome_embarque'] . ' + #' . $erpEmbarqueId;

        $stmt = $pdo->prepare("
            UPDATE frota_embarque 
            SET erp_ids_agrupados = :erp_ids,
                total_embarques_agrupados = :total,
                nome_embarque = :nome,
                total_entregas = (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = :embarque_id),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'erp_ids' => $novoErpIdsString,
            'total' => $novoTotalEmbarques,
            'nome' => $novoNome,
            'embarque_id' => $embarqueId,
            'id' => $embarqueId
        ]);

        $pdo->commit();

        // ----------------------------------------------------------------
        // 7. Otimizar rota automaticamente
        // ----------------------------------------------------------------
        $resultadoOtimizacao = $this->otimizadorRotas->otimizar($embarqueId);
        if (!$resultadoOtimizacao['success']) {
            error_log('[Embarque] Otimização automática falhou: ' . ($resultadoOtimizacao['error'] ?? 'Erro desconhecido'));
        }

        return $this->json($response, [
            'success' => true,
            'message' => count($pedidos) . ' pedidos adicionados com sucesso. Embarque #' . $erpEmbarqueId . ' adicionado ao grupo.',
            'otimizacao' => $resultadoOtimizacao['success'] ? 'rota otimizada' : 'rota não otimizada (algumas entregas sem coordenadas)'
        ]);

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Embarque] Erro ao adicionar embarque ERP: ' . $e->getMessage());
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

   /**
 * POST /v1/frota/embarques/{id}/adicionar-pedidos
 * Adiciona uma lista de pedidos (IDs) a um embarque/grupo existente
 */
public function adicionarPedidos(Request $request, Response $response, array $args): Response
{
    $embarqueId = (int)$args['id'];
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $pedidosIds = $input['pedidos_ids'] ?? [];
    $erpEmbarqueId = (int)($input['erp_embarque_id'] ?? 0);

    if (empty($pedidosIds)) {
        return $this->json($response, ['success' => false, 'error' => 'Lista de pedidos vazia'], 400);
    }

    try {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        // ----------------------------------------------------------------
        // 1. Buscar dados atuais do grupo
        // ----------------------------------------------------------------
        $stmtGrupo = $pdo->prepare("
            SELECT erp_ids_agrupados, total_embarques_agrupados, nome_embarque 
            FROM frota_embarque 
            WHERE id = :id
        ");
        $stmtGrupo->execute(['id' => $embarqueId]);
        $grupoAtual = $stmtGrupo->fetch(\PDO::FETCH_ASSOC);

        if (!$grupoAtual) {
            return $this->json($response, ['success' => false, 'error' => 'Embarque destino não encontrado'], 404);
        }

        // ----------------------------------------------------------------
        // 2. Buscar dados dos pedidos
        // ----------------------------------------------------------------
        $placeholders = implode(',', array_fill(0, count($pedidosIds), '?'));
        $stmt = $pdo->prepare("
            SELECT 
                p.idpedido,
                p.idcliforemp,
                p.valortotalpedido,
                c.fantasia as cliente_nome,
                c.razao as cliente_razao,
                c.endereco,
                c.numero,
                c.bairro,
                c.uf,
                c.cep,
                c.fone as telefone,
                cid.descricao as cidade_nome,
                ce.latitude,
                ce.longitude
            FROM pedido p
            LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN cliforemp_endereco ce ON ce.idcliforemp = c.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            WHERE p.idpedido IN ({$placeholders})
              AND p.status IN (4, 5)
        ");
        $stmt->execute($pedidosIds);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($pedidos)) {
            return $this->json($response, ['success' => false, 'error' => 'Nenhum pedido válido encontrado'], 400);
        }

        // ----------------------------------------------------------------
        // 3. Agrupar pedidos por cliente
        // ----------------------------------------------------------------
        $grupos = [];
        foreach ($pedidos as $pedido) {
            $clienteId = $pedido['idcliforemp'];
            if (!isset($grupos[$clienteId])) {
                $grupos[$clienteId] = [
                    'cliente' => [
                        'idcliforemp' => $clienteId,
                        'nome' => $pedido['cliente_nome'] ?? $pedido['cliente_razao'] ?? 'Cliente',
                        'endereco' => $pedido['endereco'] ?? '',
                        'numero' => $pedido['numero'] ?? '',
                        'bairro' => $pedido['bairro'] ?? '',
                        'cidade' => $pedido['cidade_nome'] ?? '',
                        'uf' => $pedido['uf'] ?? '',
                        'cep' => $pedido['cep'] ?? '',
                        'telefone' => $pedido['telefone'] ?? '',
                        'latitude' => $pedido['latitude'] ?? null,
                        'longitude' => $pedido['longitude'] ?? null,
                    ],
                    'pedidos' => []
                ];
            }
            $grupos[$clienteId]['pedidos'][] = $pedido;
        }

        // ----------------------------------------------------------------
        // 4. Geolocalizar clientes sem coordenadas
        // ----------------------------------------------------------------
        $geolocalizacao = $this->geolocalizacaoService;
        foreach ($grupos as $clienteId => &$dados) {
            if (empty($dados['cliente']['latitude']) || empty($dados['cliente']['longitude'])) {
                // NÍVEL 1: Buscar na frota_cliente
                $resultado = $geolocalizacao->buscarNaFrotaCliente($clienteId);
                if ($resultado['success']) {
                    $dados['cliente']['latitude'] = $resultado['latitude'];
                    $dados['cliente']['longitude'] = $resultado['longitude'];
                    continue;
                }

                // NÍVEL 2: Google Maps
                $enderecoCompleto = trim(
                    ($dados['cliente']['endereco'] ?? '') .
                    (!empty($dados['cliente']['numero']) ? ', ' . $dados['cliente']['numero'] : '') .
                    (!empty($dados['cliente']['bairro']) ? ', ' . $dados['cliente']['bairro'] : '') .
                    ', ' . ($dados['cliente']['cidade'] ?? '') .
                    ', ' . ($dados['cliente']['uf'] ?? '') .
                    (!empty($dados['cliente']['cep']) ? ', CEP: ' . $dados['cliente']['cep'] : '')
                );

                if (!empty($enderecoCompleto)) {
                    $resultado = $geolocalizacao->buscarNoGoogleMaps($enderecoCompleto);
                    if ($resultado['success']) {
                        $dados['cliente']['latitude'] = $resultado['latitude'];
                        $dados['cliente']['longitude'] = $resultado['longitude'];
                        continue;
                    }

                    // NÍVEL 3: Busca simplificada
                    $resultado = $geolocalizacao->buscarNoGoogleMapsSimplificado($enderecoCompleto);
                    if ($resultado['success']) {
                        $dados['cliente']['latitude'] = $resultado['latitude'];
                        $dados['cliente']['longitude'] = $resultado['longitude'];
                    }
                }
            }
        }

        // ----------------------------------------------------------------
        // 5. Criar/atualizar entregas
        // ----------------------------------------------------------------
        $erpIdsParaGrupo = $grupoAtual['erp_ids_agrupados'] ?? '';
        if ($erpEmbarqueId > 0) {
            $erpIdsParaGrupo = $erpIdsParaGrupo 
                ? $erpIdsParaGrupo . ',' . $erpEmbarqueId 
                : (string)$erpEmbarqueId;
        }

        foreach ($grupos as $clienteId => $dados) {
            // Verificar se já existe entrega para este cliente
            $stmt = $pdo->prepare("
                SELECT id FROM frota_entrega 
                WHERE embarque_id = :embarque_id AND cliente_id = (
                    SELECT id FROM frota_cliente WHERE erp_id = :erp_id
                )
            ");
            $stmt->execute(['embarque_id' => $embarqueId, 'erp_id' => $clienteId]);
            $entregaExistente = $stmt->fetch(\PDO::FETCH_ASSOC);

            $clienteFrotaId = $this->buscarOuCriarClienteFrota($dados['cliente']);

            if ($entregaExistente) {
                // Atualizar entrega existente
                $entregaId = $entregaExistente['id'];
                $stmt = $pdo->prepare("SELECT pedidos_ids, erp_embarques_ids FROM frota_entrega WHERE id = :id");
                $stmt->execute(['id' => $entregaId]);
                $atual = $stmt->fetch(\PDO::FETCH_ASSOC);
                $pedidosIdsAtuais = $atual ? explode(',', $atual['pedidos_ids']) : [];
                $novosPedidosIds = array_column($dados['pedidos'], 'idpedido');
                $todosPedidosIds = array_merge($pedidosIdsAtuais, $novosPedidosIds);
                $todosPedidosIds = array_unique($todosPedidosIds);

                // Atualizar erp_embarques_ids na entrega (se veio de um novo embarque)
                $erpEmbarquesAtuais = $atual['erp_embarques_ids'] ?? '';
                $erpEmbarquesNovos = $erpEmbarquesAtuais;
                if ($erpEmbarqueId > 0) {
                    $erpEmbarquesNovos = $erpEmbarquesAtuais 
                        ? $erpEmbarquesAtuais . ',' . $erpEmbarqueId 
                        : (string)$erpEmbarqueId;
                }

                $novoValor = $this->calcularValorPedidos($todosPedidosIds);
                $novoPeso = $this->calcularPesoPedidos($todosPedidosIds);

                $stmt = $pdo->prepare("
                    UPDATE frota_entrega 
                    SET pedidos_ids = :pedidos_ids,
                        valor_total = :valor,
                        peso_total = :peso,
                        total_pedidos_agrupados = :total_pedidos,
                        latitude = COALESCE(:latitude, latitude),
                        longitude = COALESCE(:longitude, longitude),
                        erp_embarques_ids = :erp_embarques_ids,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'pedidos_ids' => implode(',', $todosPedidosIds),
                    'valor' => $novoValor,
                    'peso' => $novoPeso,
                    'total_pedidos' => count($todosPedidosIds),
                    'latitude' => $dados['cliente']['latitude'],
                    'longitude' => $dados['cliente']['longitude'],
                    'erp_embarques_ids' => $erpEmbarquesNovos,
                    'id' => $entregaId
                ]);
                error_log("[Embarque] Pedidos adicionados à entrega #{$entregaId}");
            } else {
                // Criar nova entrega
                $this->criarEntregaParaCliente(
                    $embarqueId, 
                    $clienteFrotaId, 
                    $dados['pedidos'], 
                    $erpIdsParaGrupo
                );
            }
        }

        // ----------------------------------------------------------------
        // 6. Atualizar campos do grupo (total_entregas e, se for novo ERP, os agrupados)
        // ----------------------------------------------------------------
        $stmt = $pdo->prepare("
            UPDATE frota_embarque 
            SET total_entregas = (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = :embarque_id),
                updated_at = NOW()
            WHERE id = :embarque_id
        ");
        $stmt->execute(['embarque_id' => $embarqueId]);

        // Se os pedidos vieram de um novo embarque ERP, atualizar também erp_ids_agrupados e total
        if ($erpEmbarqueId > 0) {
            $novoTotalEmbarques = (int)$grupoAtual['total_embarques_agrupados'] + 1;
            $novoNome = $grupoAtual['nome_embarque'] . ' + #' . $erpEmbarqueId;

            $stmt = $pdo->prepare("
                UPDATE frota_embarque 
                SET erp_ids_agrupados = :erp_ids,
                    total_embarques_agrupados = :total,
                    nome_embarque = :nome,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'erp_ids' => $erpIdsParaGrupo,
                'total' => $novoTotalEmbarques,
                'nome' => $novoNome,
                'id' => $embarqueId
            ]);
        }

        $pdo->commit();

        // ----------------------------------------------------------------
        // 7. Otimizar rota automaticamente
        // ----------------------------------------------------------------
        $resultadoOtimizacao = $this->otimizadorRotas->otimizar($embarqueId);
        if (!$resultadoOtimizacao['success']) {
            error_log('[Embarque] Otimização automática falhou: ' . ($resultadoOtimizacao['error'] ?? 'Erro desconhecido'));
        }

        return $this->json($response, [
            'success' => true,
            'message' => count($pedidos) . ' pedidos adicionados com sucesso',
            'otimizacao' => $resultadoOtimizacao['success'] ? 'rota otimizada' : 'rota não otimizada (algumas entregas sem coordenadas)'
        ]);

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Embarque] Erro ao adicionar pedidos: ' . $e->getMessage());
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

    /**
     * Busca ou cria um cliente na frota, atualizando coordenadas se necessário
     */
    private function buscarOuCriarClienteFrota($dadosCliente)
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("SELECT id, latitude, longitude FROM frota_cliente WHERE erp_id = :erp_id");
        $stmt->execute(['erp_id' => $dadosCliente['idcliforemp']]);
        $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);

        $lat = $dadosCliente['latitude'] ?? null;
        $lng = $dadosCliente['longitude'] ?? null;

        if ($cliente) {
            // Atualiza coordenadas se estiverem vazias
            if ((empty($cliente['latitude']) || empty($cliente['longitude'])) && !empty($lat) && !empty($lng)) {
                $stmt = $pdo->prepare("
                    UPDATE frota_cliente 
                    SET latitude = :lat, longitude = :lng, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute(['lat' => $lat, 'lng' => $lng, 'id' => $cliente['id']]);
            }
            return $cliente['id'];
        }

        // Criar novo cliente com as coordenadas (podem ser null)
        $stmt = $pdo->prepare("
            INSERT INTO frota_cliente (
                erp_id, nome, endereco, bairro, cidade, uf, cep, telefone,
                latitude, longitude, created_at, updated_at
            ) VALUES (
                :erp_id, :nome, :endereco, :bairro, :cidade, :uf, :cep, :telefone,
                :latitude, :longitude, NOW(), NOW()
            ) RETURNING id
        ");
        $stmt->execute([
            'erp_id' => $dadosCliente['idcliforemp'],
            'nome' => $dadosCliente['nome'],
            'endereco' => $dadosCliente['endereco'] ?? '',
            'bairro' => $dadosCliente['bairro'] ?? '',
            'cidade' => $dadosCliente['cidade'] ?? '',
            'uf' => $dadosCliente['uf'] ?? '',
            'cep' => $dadosCliente['cep'] ?? '',
            'telefone' => $dadosCliente['telefone'] ?? '',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
        return $stmt->fetchColumn();
    }

  private function criarEntregaParaCliente($embarqueId, $clienteFrotaId, $pedidos, $erpEmbarquesIds = null)
{
    $pdo = $this->pdo;
    $pedidosIds = array_column($pedidos, 'idpedido');
    $valorTotal = array_sum(array_column($pedidos, 'valortotalpedido'));
    $pesoTotal = $this->calcularPesoPedidos($pedidosIds);
    $primeiroPedido = $pedidos[0];

    // Buscar coordenadas atualizadas do cliente
    $stmt = $pdo->prepare("SELECT latitude, longitude FROM frota_cliente WHERE id = :id");
    $stmt->execute(['id' => $clienteFrotaId]);
    $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Se não foi passado erp_embarques_ids, tenta buscar do grupo (para manter consistência)
    if (empty($erpEmbarquesIds)) {
        $stmtGrupo = $pdo->prepare("SELECT erp_ids_agrupados FROM frota_embarque WHERE id = :id");
        $stmtGrupo->execute(['id' => $embarqueId]);
        $grupo = $stmtGrupo->fetch(\PDO::FETCH_ASSOC);
        $erpEmbarquesIds = $grupo['erp_ids_agrupados'] ?? null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO frota_entrega (
            embarque_id, cliente_id, pedido_id, ordem_entrega, codigo_rastreamento,
            endereco, numero, complemento, bairro, cidade, uf, cep, telefone,
            latitude, longitude,
            cliente_nome, cliente_telefone, valor, peso, peso_total, valor_total,
            status, data_prevista, created_at, updated_at,
            pedidos_ids, total_pedidos_agrupados, erp_embarques_ids
        ) VALUES (
            :embarque_id, :cliente_id, :pedido_id, :ordem_entrega, :codigo_rastreamento,
            :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, :telefone,
            :latitude, :longitude,
            :cliente_nome, :cliente_telefone, :valor, :peso, :peso_total, :valor_total,
            'pendente', :data_prevista, NOW(), NOW(),
            :pedidos_ids, :total_pedidos, :erp_embarques_ids
        ) RETURNING id
    ");
    $stmt->execute([
        'embarque_id' => $embarqueId,
        'cliente_id' => $clienteFrotaId,
        'pedido_id' => $pedidosIds[0],
        'ordem_entrega' => 0,
        'codigo_rastreamento' => 'TRK' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'endereco' => $primeiroPedido['endereco'] ?? '',
        'numero' => $primeiroPedido['numero'] ?? '',
        'complemento' => $primeiroPedido['complemento'] ?? '',
        'bairro' => $primeiroPedido['bairro'] ?? '',
        'cidade' => $primeiroPedido['cidade_nome'] ?? '',
        'uf' => $primeiroPedido['uf'] ?? '',
        'cep' => $primeiroPedido['cep'] ?? '',
        'telefone' => $primeiroPedido['telefone'] ?? '',
        'latitude' => $cliente['latitude'] ?? null,
        'longitude' => $cliente['longitude'] ?? null,
        'cliente_nome' => $primeiroPedido['cliente_nome'] ?? 'Cliente',
        'cliente_telefone' => $primeiroPedido['telefone'] ?? '',
        'valor' => $valorTotal,
        'peso' => $pesoTotal,
        'peso_total' => $pesoTotal,
        'valor_total' => $valorTotal,
        'data_prevista' => date('Y-m-d', strtotime('+1 day')),
        'pedidos_ids' => implode(',', $pedidosIds),
        'total_pedidos' => count($pedidosIds),
        'erp_embarques_ids' => $erpEmbarquesIds, // 🔥 NOVO CAMPO
    ]);
    return $stmt->fetchColumn();
}

    /**
     * Calcula o valor total de uma lista de pedidos
     */
    private function calcularValorPedidos($pedidosIds)
    {
        if (empty($pedidosIds)) return 0;
        $pdo = $this->pdo;
        $placeholders = implode(',', array_fill(0, count($pedidosIds), '?'));
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(valortotalpedido), 0) FROM pedido WHERE idpedido IN ({$placeholders})");
        $stmt->execute($pedidosIds);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Calcula o peso total de uma lista de pedidos
     */
    private function calcularPesoPedidos($pedidosIds)
    {
        if (empty($pedidosIds)) return 0;
        $pdo = $this->pdo;
        $placeholders = implode(',', array_fill(0, count($pedidosIds), '?'));
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(pi.qt * i.pesobruto), 0)
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido
            JOIN item i ON i.iditem = pi.iditem
            WHERE p.idpedido IN ({$placeholders}) AND pi.ativo = 'S'
        ");
        $stmt->execute($pedidosIds);
        return (float)$stmt->fetchColumn();
    }

    /**
     * GET /v1/frota/embarques/{id}/rota
     * Buscar rota do embarque (entregas ordenadas)
     */
    public function rota(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.*,
                    c.nome as cliente_nome,
                    c.telefone as cliente_telefone,
                    c.latitude,
                    c.longitude,
                    v.placa,
                    v.modelo
                FROM frota_entrega e
                LEFT JOIN frota_cliente c ON c.id = e.cliente_id
                LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
                LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
                WHERE e.embarque_id = :embarque_id
                ORDER BY e.ordem_entrega ASC
            ");
            $stmt->execute(['embarque_id' => $id]);
            $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $totalDistancia = 0;
            $totalTempo = 0;
            $ultimoPonto = null;
            
            foreach ($entregas as &$entrega) {
                if ($ultimoPonto) {
                    $distancia = $this->calcularDistancia(
                        $ultimoPonto['latitude'],
                        $ultimoPonto['longitude'],
                        $entrega['latitude'],
                        $entrega['longitude']
                    );
                    $totalDistancia += $distancia;
                    $totalTempo += $distancia / 40 * 60;
                    
                    $entrega['distancia_anterior_km'] = round($distancia, 2);
                    $entrega['tempo_anterior_min'] = round($distancia / 40 * 60, 1);
                }
                
                $ultimoPonto = [
                    'latitude' => $entrega['latitude'],
                    'longitude' => $entrega['longitude']
                ];
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => [
                    'entregas' => $entregas,
                    'metricas' => [
                        'total_entregas' => count($entregas),
                        'distancia_total_km' => round($totalDistancia, 2),
                        'tempo_total_min' => round($totalTempo, 1),
                        'tempo_estimado_horas' => round($totalTempo / 60, 1)
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // ========================================================================
    // MÉTODOS AUXILIARES
    // ========================================================================
    
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)
                       ->withHeader('Content-Type', 'application/json; charset=utf-8')
                       ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
    
    private function calcularDistancia($lat1, $lng1, $lat2, $lng2): float
    {
        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) {
            return 0;
        }
        
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
    
private function registrarLog($embarqueId, $acao, $descricao, $usuarioId = 0)
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
            'usuario_id' => $usuarioId
        ]);
    } catch (\Exception $e) {
        error_log('Erro ao registrar log: ' . $e->getMessage());
    }
}
}