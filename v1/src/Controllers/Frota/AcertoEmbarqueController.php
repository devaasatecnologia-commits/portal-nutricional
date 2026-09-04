<?php
// src/Controllers/Frota/AcertoEmbarqueController.php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\Frota\ERPPedidoService;

class AcertoEmbarqueController
{
    /** @var \PDO */
    private $pdo;
    
    /** @var ERPPedidoService */
    private $erpService;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->erpService = new ERPPedidoService($this->pdo);
    }
    
/**
 * GET /v1/frota/acerto/embarques
 * Lista embarques que têm acerto (em andamento ou finalizados)
 */
public function listarParaAcerto(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    
    $filtros = [];
    $bindParams = [];
    
    // 🔥 CORRIGIDO: Mostrar apenas embarques que têm acerto
    $filtros[] = "EXISTS (
        SELECT 1 FROM frota_acerto_embarque ae 
        WHERE ae.embarque_id = e.id 
    )";
    
    // 🔥 ADICIONADO: Filtrar por status do acerto
    if (!empty($params['status_acerto'])) {
        $filtros[] = "EXISTS (
            SELECT 1 FROM frota_acerto_embarque ae 
            WHERE ae.embarque_id = e.id 
              AND ae.status = :status_acerto
        )";
        $bindParams['status_acerto'] = $params['status_acerto'];
    }
    
    // Filtros opcionais
    if (!empty($params['busca'])) {
        $filtros[] = "(e.numero_embarque ILIKE :busca OR m.nome ILIKE :busca OR v.placa ILIKE :busca OR e.nome_embarque ILIKE :busca)";
        $bindParams['busca'] = "%{$params['busca']}%";
    }
    
    if (!empty($params['data_inicio'])) {
        $filtros[] = "e.data_saida >= :data_inicio";
        $bindParams['data_inicio'] = $params['data_inicio'];
    }
    
    if (!empty($params['data_fim'])) {
        $filtros[] = "e.data_saida <= :data_fim";
        $bindParams['data_fim'] = $params['data_fim'];
    }
    
    $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
    
    $limite = (int)($params['limite'] ?? 20);
    $pagina = (int)($params['pagina'] ?? 1);
    $offset = ($pagina - 1) * $limite;
    
    $sql = "
        SELECT 
            e.id,
            e.numero_embarque,
            e.nome_embarque,
            e.status as embarque_status,
            e.data_saida,
            e.data_retorno,
            e.horario_saida,
            e.horario_retorno,
            e.erp_embarque_id,
            e.erp_ids_agrupados,
            e.veiculo_id,
            e.motorista_id,
            v.placa,
            v.modelo,
            v.tipo as veiculo_tipo,
            m.nome as motorista_nome,
            m.telefone as motorista_telefone,
            ae.id as acerto_id,
            ae.status as acerto_status,
            ae.data_inicio_acerto,
            ae.data_fim_acerto,
            (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id) as total_entregas,
            (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id AND status IN ('entregue', 'entregue_com_problema')) as entregas_concluidas,
            (SELECT COUNT(*) FROM frota_entrega_problema WHERE embarque_id = e.id AND status_problema IN ('pendente', 'em_analise')) as total_problemas
        FROM frota_embarque e
        LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
        LEFT JOIN frota_motorista m ON m.id = e.motorista_id
        INNER JOIN frota_acerto_embarque ae ON ae.embarque_id = e.id
        {$where}
        ORDER BY 
            ae.id DESC,
            ae.status ASC
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
    
    // Total
    $sqlCount = "SELECT COUNT(DISTINCT e.id) FROM frota_embarque e {$where}";
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
 * GET /v1/frota/acerto/{embarqueId}/detalhes
 * Busca todos os detalhes do embarque para acerto
 * 🔥 COMPLETO COM PEDIDOS DE ACERTO
 */
public function getDetalhesAcerto(Request $request, Response $response, array $args): Response
{
    $embarqueId = (int)$args['embarqueId'];
    
    try {
        $pdo = $this->pdo;
        
        // 1. DADOS DO EMBARQUE
        $stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.numero_embarque,
                e.nome_embarque,
                e.status as embarque_status,
                e.data_saida,
                e.data_retorno,
                e.horario_saida,
                e.horario_retorno,
                e.observacoes,
                e.erp_embarque_id,
                e.erp_ids_agrupados,
                v.id as veiculo_id,
                v.placa,
                v.modelo,
                v.tipo as veiculo_tipo,
                m.id as motorista_id,
                m.nome as motorista_nome,
                m.telefone as motorista_telefone,
                m.cpf as motorista_cpf,
                (
                    SELECT COUNT(*) 
                    FROM frota_entrega 
                    WHERE embarque_id = e.id
                ) as total_entregas
            FROM frota_embarque e
            LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = e.motorista_id
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $embarqueId]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Embarque não encontrado'
            ], 404);
        }
        
        // 2. ENTREGAS COM CHECKLIST E FOTOS
        $stmt = $pdo->prepare("
            SELECT 
                ent.id,
                ent.cliente_nome,
                ent.endereco,
                ent.numero,
                ent.bairro,
                ent.cidade,
                ent.uf,
                ent.cep,
                ent.latitude,
                ent.longitude,
                ent.status,
                ent.valor_total,
                ent.peso_total,
                ent.codigo_rastreamento,
                ent.ordem_entrega,
                ent.horario_checkin,
                ent.horario_entrega,
                ent.nome_recebedor,
                ent.foto_romaneio_url,
                ent.foto_checkin_url,
                ent.created_at,
                ent.updated_at,
                ent.pedido_id,
                ent.pedidos_ids,
                ent.erp_embarques_ids,
                ent.total_pedidos_agrupados,
                ent.foto_item_url
            FROM frota_entrega ent
            WHERE ent.embarque_id = :embarque_id
            ORDER BY ent.ordem_entrega ASC
        ");
        $stmt->execute(['embarque_id' => $embarqueId]);
        $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar checklist e fotos para cada entrega
        foreach ($entregas as &$entrega) {
            // Checklist
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    item_id,
                    referencia,
                    descricao,
                    foto_url,
                    quantidade_prevista,
                    quantidade_entregue,
                    status,
                    motivo
                FROM frota_checklist_entrega
                WHERE entrega_id = :entrega_id
                ORDER BY item_id ASC
            ");
            $stmt->execute(['entrega_id' => $entrega['id']]);
            $entrega['checklist'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Fotos
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    tipo_foto,
                    url_foto,
                    descricao,
                    latitude,
                    longitude,
                    created_at
                FROM frota_entrega_foto
                WHERE entrega_id = :entrega_id
                ORDER BY created_at ASC
            ");
            $stmt->execute(['entrega_id' => $entrega['id']]);
            $entrega['fotos'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Problemas
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    tipo_problema,
                    item_id,
                    referencia,
                    descricao_problema,
                    quantidade_afetada,
                    valor_afetado,
                    status_problema,
                    prioridade,
                    solucao,
                    data_resolucao,
                    created_at
                FROM frota_entrega_problema
                WHERE entrega_id = :entrega_id
                ORDER BY created_at DESC
            ");
            $stmt->execute(['entrega_id' => $entrega['id']]);
            $entrega['problemas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($entrega);
        
        $embarque['entregas'] = $entregas;
        
        // 3. TIMELINE
        $stmt = $pdo->prepare("
            SELECT 
                le.id,
                le.acao,
                le.descricao,
                le.usuario_id,
                u.username as usuario_nome,
                le.data_hora
            FROM frota_log_embarque le
            LEFT JOIN usuario u ON u.idusuario = le.usuario_id
            WHERE le.embarque_id = :embarque_id
            ORDER BY le.data_hora DESC
        ");
        $stmt->execute(['embarque_id' => $embarqueId]);
        $embarque['timeline'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // 4. RESUMO DE PROBLEMAS
        $stmt = $pdo->prepare("
            SELECT 
                tipo_problema,
                COUNT(*) as total,
                SUM(quantidade_afetada) as total_quantidade,
                SUM(valor_afetado) as total_valor,
                status_problema
            FROM frota_entrega_problema
            WHERE embarque_id = :embarque_id
            GROUP BY tipo_problema, status_problema
        ");
        $stmt->execute(['embarque_id' => $embarqueId]);
        $embarque['resumo_problemas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // 5. VERIFICAR ACERTO EXISTENTE
        $stmt = $pdo->prepare("
            SELECT 
                id,
                status,
                data_acerto,
                gestor_nome,
                total_pedidos_faltantes,
                total_pedidos_devolvidos
            FROM frota_acerto_embarque
            WHERE embarque_id = :embarque_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['embarque_id' => $embarqueId]);
        $embarque['acerto_existente'] = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // 6. Calcular total de problemas
        $totalProblemas = 0;
        if (!empty($embarque['resumo_problemas'])) {
            foreach ($embarque['resumo_problemas'] as $p) {
                $totalProblemas += (int)$p['total'];
            }
        }
        $embarque['total_problemas'] = $totalProblemas;
        
        // 🔥 7. PEDIDOS DE ACERTO CRIADOS
        $stmt = $pdo->prepare("
            SELECT 
                ap.id,
                ap.acerto_id,
                ap.entrega_id,
                ap.pedido_erp_id,
                ap.numero_pedido,
                ap.cliente_nome,
                ap.tipo_problema,
                ap.itens_afetados,
                ap.motivo,
                ap.observacoes,
                ap.valor_total,
                ap.status,
                ap.created_at,
                ap.updated_at,
                ap.pedido_erp_criado_id,
                ap.numero_pedido_criado,
                ap.data_criacao_erp
            FROM frota_acerto_pedido ap
            WHERE ap.acerto_id = (
                SELECT id 
                FROM frota_acerto_embarque 
                WHERE embarque_id = :embarque_id 
                  AND status != 'cancelado' 
                ORDER BY id DESC 
                LIMIT 1
            )
            ORDER BY ap.created_at DESC
        ");
        $stmt->execute(['embarque_id' => $embarqueId]);
        $pedidosAcerto = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Processar itens_afetados (JSON) para cada pedido
        foreach ($pedidosAcerto as &$pedido) {
            if (!empty($pedido['itens_afetados'])) {
                $pedido['itens_afetados'] = json_decode($pedido['itens_afetados'], true);
                if (!is_array($pedido['itens_afetados'])) {
                    $pedido['itens_afetados'] = [];
                }
            } else {
                $pedido['itens_afetados'] = [];
            }
        }
        unset($pedido);
        
        $embarque['pedidos_acerto'] = $pedidosAcerto;
        
        return $this->json($response, [
            'success' => true,
            'data' => $embarque
        ]);
        
    } catch (\Exception $e) {
        error_log('[Acerto] Erro ao buscar detalhes: ' . $e->getMessage());
        error_log('[Acerto] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    /**
 * GET /v1/frota/acerto/pedido/{id}
 * Busca detalhes de um pedido de acerto específico
 */
public function getPedidoAcerto(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    
    if ($id <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do pedido é obrigatório'
        ], 400);
    }
    
    try {
        $stmt = $this->pdo->prepare("
            SELECT 
                ap.id,
                ap.acerto_id,
                ap.entrega_id,
                ap.pedido_erp_id,
                ap.numero_pedido,
                ap.cliente_nome,
                ap.tipo_problema,
                ap.itens_afetados,
                ap.motivo,
                ap.observacoes,
                ap.valor_total,
                ap.status,
                ap.created_at,
                ap.updated_at,
                ap.pedido_erp_criado_id,
                ap.numero_pedido_criado,
                ap.data_criacao_erp,
                ae.embarque_id
            FROM frota_acerto_pedido ap
            LEFT JOIN frota_acerto_embarque ae ON ae.id = ap.acerto_id
            WHERE ap.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Pedido de acerto não encontrado'
            ], 404);
        }
        
        // Processar itens_afetados (JSON)
        if (!empty($pedido['itens_afetados'])) {
            $pedido['itens_afetados'] = json_decode($pedido['itens_afetados'], true);
            if (!is_array($pedido['itens_afetados'])) {
                $pedido['itens_afetados'] = [];
            }
        } else {
            $pedido['itens_afetados'] = [];
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $pedido
        ]);
        
    } catch (\Exception $e) {
        error_log('[Acerto] Erro ao buscar pedido: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * POST /v1/frota/acerto/iniciar
     * Inicia um acerto de embarque
     */
    public function iniciarAcerto(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        $usuarioNome = $user['username'] ?? $user['nome'] ?? 'Gestor';
        
        $embarqueId = (int)($input['embarque_id'] ?? 0);
        
        if ($embarqueId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do embarque é obrigatório'
            ], 400);
        }
        
        try {
            $pdo = $this->pdo;
            $pdo->beginTransaction();
            
            // Verificar se já existe acerto ativo
            $stmt = $pdo->prepare("
                SELECT id FROM frota_acerto_embarque 
                WHERE embarque_id = :embarque_id AND status IN ('pendente', 'em_andamento')
            ");
            $stmt->execute(['embarque_id' => $embarqueId]);
            if ($stmt->fetch()) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Já existe um acerto em andamento para este embarque'
                ], 400);
            }
            
            // Buscar dados do embarque
            $stmt = $pdo->prepare("
                SELECT 
                    e.motorista_id,
                    e.veiculo_id,
                    e.numero_embarque,
                    COUNT(DISTINCT ent.id) as total_entregas,
                    COUNT(DISTINCT ep.id) as total_problemas,
                    COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(ent.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), ent.valor_total, 0)), 0) as valor_total
                FROM frota_embarque e
                LEFT JOIN frota_entrega ent ON ent.embarque_id = e.id
                LEFT JOIN frota_entrega_problema ep ON ep.entrega_id = ent.id AND ep.status_problema IN ('pendente', 'em_analise')
                WHERE e.id = :id
                GROUP BY e.id, e.motorista_id, e.veiculo_id, e.numero_embarque
            ");
            $stmt->execute(['id' => $embarqueId]);
            $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$embarque) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Embarque não encontrado'
                ], 404);
            }
            
            // Criar acerto
            $stmt = $pdo->prepare("
                INSERT INTO frota_acerto_embarque (
                    embarque_id,
                    motorista_id,
                    veiculo_id,
                    gestor_id,
                    gestor_nome,
                    data_inicio_acerto,
                    status,
                    total_pedidos_originais,
                    valor_total_original,
                    created_at,
                    updated_at
                ) VALUES (
                    :embarque_id,
                    :motorista_id,
                    :veiculo_id,
                    :gestor_id,
                    :gestor_nome,
                    NOW(),
                    'em_andamento',
                    :total_pedidos,
                    :valor_total,
                    NOW(),
                    NOW()
                ) RETURNING id
            ");
            $stmt->execute([
                'embarque_id' => $embarqueId,
                'motorista_id' => $embarque['motorista_id'],
                'veiculo_id' => $embarque['veiculo_id'],
                'gestor_id' => $usuarioId,
                'gestor_nome' => $usuarioNome,
                'total_pedidos' => $embarque['total_entregas'],
                'valor_total' => $embarque['valor_total']
            ]);
            
            $acertoId = $stmt->fetchColumn();
            
            // Registrar log - usando a tabela correta
            $this->registrarLog($embarqueId, 'acerto_iniciado', "Acerto iniciado pelo gestor {$usuarioNome}", $usuarioId);
            
            $pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Acerto iniciado com sucesso',
                'data' => [
                    'acerto_id' => $acertoId,
                    'embarque_id' => $embarqueId
                ]
            ]);
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Acerto] Erro ao iniciar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
 /**
 * POST /v1/frota/acerto/pedido-problema
 * Cria um pedido de acerto a partir de um problema identificado
 * 🔥 CORRIGIDO - ERRO DE PARÂMETROS MISTURADOS
 */
public function criarPedidoProblema(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;
    $usuarioNome = $user['username'] ?? $user['nome'] ?? 'Gestor';
    
    $acertoId = (int)($input['acerto_id'] ?? 0);
    $entregaId = (int)($input['entrega_id'] ?? 0);
    $tipoProblema = $input['tipo_problema'] ?? 'faltante';
    $itens = $input['itens'] ?? [];
    $motivo = $input['motivo'] ?? '';
    $observacoes = $input['observacoes'] ?? '';
    
    // ============================================================
    // VALIDAÇÕES
    // ============================================================
    if ($acertoId <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do acerto é obrigatório'
        ], 400);
    }
    
    if ($entregaId <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID da entrega é obrigatório'
        ], 400);
    }
    
    if (!in_array($tipoProblema, ['faltante', 'devolucao'])) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Tipo de problema inválido. Use "faltante" ou "devolucao"'
        ], 400);
    }
    
    if (empty($itens)) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Pelo menos um item deve ser informado'
        ], 400);
    }
    
    try {
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        
        // ============================================================
        // 1. VERIFICAR ACERTO
        // ============================================================
        $stmt = $pdo->prepare("
            SELECT id, embarque_id, status 
            FROM frota_acerto_embarque 
            WHERE id = :id AND status IN ('em_andamento', 'pendente')
        ");
        $stmt->execute(['id' => $acertoId]);
        $acerto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$acerto) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Acerto não encontrado ou já finalizado'
            ], 404);
        }
        
        $embarqueId = $acerto['embarque_id'];
        
        // ============================================================
        // 2. BUSCAR DADOS DA ENTREGA
        // ============================================================
        $stmt = $pdo->prepare("
            SELECT 
                ent.id,
                ent.cliente_id,
                ent.cliente_nome,
                ent.pedido_id,
                ent.pedidos_ids,
                ent.valor_total,
                ent.embarque_id,
                ent.horario_checkin,
                ent.horario_entrega,
                ent.nome_recebedor,
                ent.status as entrega_status,
                ent.codigo_rastreamento
            FROM frota_entrega ent
            WHERE ent.id = :id AND ent.embarque_id = :embarque_id
        ");
        $stmt->execute(['id' => $entregaId, 'embarque_id' => $embarqueId]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$entrega) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada neste embarque'
            ], 404);
        }
        
        // ============================================================
        // 3. BUSCAR VALOR UNITÁRIO DOS ITENS
        // ============================================================
        $itensFormatados = [];
        $valorTotal = 0;
        $itensIds = array_column($itens, 'iditem');
        $itensMap = [];
        
        if (!empty($itensIds)) {
            // 🔥 CORRIGIDO: Usar placeholders posicionais com array_merge
            $placeholders = implode(',', array_fill(0, count($itensIds), '?'));
            $sql = "
                SELECT 
                    i.iditem,
                    i.referencia,
                    i.descricao,
                    i.idunidadebasica as idunidade,
                    e.valorprecovenda as valor_unitario
                FROM item i
                JOIN estoque_filial e ON e.iditem = i.iditem
                WHERE i.iditem IN ({$placeholders})
                AND e.idfilial = ?
            ";
            
            $stmtItem = $pdo->prepare($sql);
            
            // 🔥 CORRIGIDO: Mesclar parâmetros corretamente
            $params = array_merge($itensIds, [$embarqueId]);
            $stmtItem->execute($params);
            $itensInfo = $stmtItem->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($itensInfo as $info) {
                $itensMap[$info['iditem']] = $info;
            }
        }
        
        // ============================================================
        // 4. MONTAR ITENS COM VALORES
        // ============================================================
        $itensDetalhes = [];
        foreach ($itens as $item) {
            $iditem = (int)($item['iditem'] ?? 0);
            $quantidade = (float)($item['quantidade'] ?? 0);
            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            
            if ($iditem <= 0) {
                $pdo->rollBack();
                return $this->json($response, [
                    'success' => false,
                    'error' => 'ID do item inválido'
                ], 400);
            }
            
            if ($quantidade <= 0) {
                $pdo->rollBack();
                return $this->json($response, [
                    'success' => false,
                    'error' => "Quantidade inválida para o item {$iditem}"
                ], 400);
            }
            
            if ($valorUnitario == 0 && isset($itensMap[$iditem])) {
                $valorUnitario = (float)($itensMap[$iditem]['valor_unitario'] ?? 0);
            }
            
            $totalItem = $quantidade * $valorUnitario;
            $valorTotal += $totalItem;
            
            $itensFormatados[] = [
                'iditem' => $iditem,
                'referencia' => $item['referencia'] ?? ($itensMap[$iditem]['referencia'] ?? ''),
                'descricao' => $item['descricao'] ?? ($itensMap[$iditem]['descricao'] ?? ''),
                'unidade' => $item['unidade'] ?? ($itensMap[$iditem]['idunidade'] ?? 'UN'),
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $totalItem
            ];
            
            $itensDetalhes[] = "{$item['referencia']}: {$quantidade} un";
        }
        
        if (empty($itensFormatados)) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhum item válido para criar o pedido'
            ], 400);
        }
        
        // ============================================================
        // 5. MONTAR OBSERVAÇÃO COMPLETA
        // ============================================================
        $dataHoraEntrega = !empty($entrega['horario_entrega']) 
            ? date('d/m/Y H:i:s', strtotime($entrega['horario_entrega'])) 
            : 'Não registrado';
        
        $dataHoraCheckin = !empty($entrega['horario_checkin']) 
            ? date('d/m/Y H:i:s', strtotime($entrega['horario_checkin'])) 
            : 'Não registrado';
        
        $itensLista = implode('; ', $itensDetalhes);
        
        $observacaoCompleta = sprintf(
            "=== ACERTO DE ENTREGA ===\n" .
            "Embarque: #%d\n" .
            "Código Rastreamento: %s\n" .
            "Cliente: %s\n" .
            "Data Check-in: %s\n" .
            "Data Entrega: %s\n" .
            "Status Entrega: %s\n" .
            "Recebedor: %s\n" .
            "Tipo Problema: %s\n" .
            "Motivo: %s\n" .
            "Itens Afetados: %s\n" .
            "Gestor: %s\n" .
            "Data Criação: %s",
            $embarqueId,
            $entrega['codigo_rastreamento'] ?? 'N/A',
            $entrega['cliente_nome'] ?? 'Cliente não identificado',
            $dataHoraCheckin,
            $dataHoraEntrega,
            $entrega['entrega_status'] ?? 'N/A',
            $entrega['nome_recebedor'] ?? 'Não informado',
            $tipoProblema,
            $motivo ?: 'Não informado',
            $itensLista ?: 'Nenhum item listado',
            $usuarioNome,
            date('d/m/Y H:i:s')
        );
        
        // ============================================================
        // 6. CRIAR PEDIDO DE ACERTO (frota_acerto_pedido)
        // ============================================================
        $pedidoErpId = null;
        if (!empty($entrega['pedidos_ids'])) {
            $ids = explode(',', $entrega['pedidos_ids']);
            $pedidoErpId = (int)$ids[0];
        }
        
        $numeroPedido = $entrega['pedido_id'] ?? 'P' . date('Ymd') . rand(100, 999);
        
        // 🔥 CORRIGIDO: Usar nomes de parâmetros consistentes
        $stmt = $pdo->prepare("
            INSERT INTO frota_acerto_pedido (
                acerto_id,
                entrega_id,
                embarque_id,
                pedido_erp_id,
                numero_pedido,
                cliente_id,
                cliente_nome,
                tipo_problema,
                itens_afetados,
                motivo,
                observacoes,
                valor_total,
                status,
                created_at,
                updated_at
            ) VALUES (
                :acerto_id,
                :entrega_id,
                :embarque_id,
                :pedido_erp_id,
                :numero_pedido,
                :cliente_id,
                :cliente_nome,
                :tipo_problema,
                CAST(:itens_afetados AS jsonb),
                :motivo,
                :observacoes,
                :valor_total,
                'pendente',
                NOW(),
                NOW()
            ) RETURNING id
        ");
        
        $stmt->execute([
            'acerto_id' => $acertoId,
            'entrega_id' => $entregaId,
            'embarque_id' => $embarqueId,
            'pedido_erp_id' => $pedidoErpId ?? 0,
            'numero_pedido' => $numeroPedido,
            'cliente_id' => $entrega['cliente_id'],
            'cliente_nome' => $entrega['cliente_nome'],
            'tipo_problema' => $tipoProblema,
            'itens_afetados' => json_encode($itensFormatados),
            'motivo' => $motivo,
            'observacoes' => $observacaoCompleta,
            'valor_total' => $valorTotal
        ]);
        
        $pedidoAcertoId = $stmt->fetchColumn();
        
        if (!$pedidoAcertoId) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Falha ao criar pedido de acerto'
            ], 500);
        }
        
        // ============================================================
        // 7. CRIAR ITENS DO ACERTO (frota_acerto_item)
        // ============================================================
        $stmtItem = $pdo->prepare("
            INSERT INTO frota_acerto_item (
                acerto_pedido_id,
                item_erp_id,
                referencia,
                descricao,
                unidade,
                quantidade_prevista,
                quantidade_entregue,
                quantidade_faltante,
                quantidade_devolvida,
                valor_unitario,
                valor_total,
                status,
                created_at,
                updated_at
            ) VALUES (
                :acerto_pedido_id,
                :item_erp_id,
                :referencia,
                :descricao,
                :unidade,
                :quantidade_prevista,
                :quantidade_entregue,
                :quantidade_faltante,
                :quantidade_devolvida,
                :valor_unitario,
                :valor_total,
                'pendente',
                NOW(),
                NOW()
            )
        ");
        
        foreach ($itensFormatados as $item) {
            $stmtItem->execute([
                'acerto_pedido_id' => $pedidoAcertoId,
                'item_erp_id' => $item['iditem'],
                'referencia' => $item['referencia'],
                'descricao' => $item['descricao'],
                'unidade' => $item['unidade'],
                'quantidade_prevista' => $item['quantidade'],
                'quantidade_entregue' => $tipoProblema === 'devolucao' ? $item['quantidade'] : 0,
                'quantidade_faltante' => $tipoProblema === 'faltante' ? $item['quantidade'] : 0,
                'quantidade_devolvida' => $tipoProblema === 'devolucao' ? $item['quantidade'] : 0,
                'valor_unitario' => $item['valor_unitario'],
                'valor_total' => $item['valor_total']
            ]);
        }
        
        // ============================================================
        // 8. ATUALIZAR CONTADORES NO ACERTO
        // ============================================================
        $campoContador = $tipoProblema === 'faltante' ? 'total_pedidos_faltantes' : 'total_pedidos_devolvidos';
        $campoValor = $tipoProblema === 'faltante' ? 'valor_total_faltante' : 'valor_total_devolvido';
        
        $stmt = $pdo->prepare("
            UPDATE frota_acerto_embarque 
            SET 
                {$campoContador} = {$campoContador} + 1,
                {$campoValor} = {$campoValor} + :valor,
                updated_at = NOW()
            WHERE id = :acerto_id
        ");
        $stmt->execute([
            'acerto_id' => $acertoId,
            'valor' => $valorTotal
        ]);
        
        // ============================================================
        // 9. REGISTRAR LOG
        // ============================================================
        $this->registrarLog(
            $embarqueId, 
            'pedido_problema_criado', 
            "Pedido de {$tipoProblema} criado para entrega #{$entregaId}", 
            $usuarioId
        );
        
        // ============================================================
        // 10. COMMIT
        // ============================================================
        $pdo->commit();
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Pedido de acerto criado com sucesso',
            'data' => [
                'acerto_pedido_id' => $pedidoAcertoId,
                'tipo_problema' => $tipoProblema,
                'valor_total' => $valorTotal,
                'total_itens' => count($itensFormatados),
                'observacao' => $observacaoCompleta
            ]
        ]);
        
    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Acerto] Erro ao criar pedido problema: ' . $e->getMessage());
        error_log('[Acerto] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro interno: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * POST /v1/frota/acerto/pedido/{id}/criar-erp
 * 🔥 SIMULAÇÃO - Apenas gera SQLs para visualização
 * NUNCA insere no palmtop_pedido ou palmtop_pedido_item
 */
public function criarPedidoERP(Request $request, Response $response, array $args): Response
{
    $pedidoAcertoId = (int)($args['id'] ?? 0);
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $user = $request->getAttribute('user');
    $usuarioId = $user['idusuario'] ?? 0;
    $usuarioNome = $user['username'] ?? $user['nome'] ?? 'SISTEMA';
    
    $idTransacaoSolicitada = (int)($input['id_transacao'] ?? 0);
    $idFilial = (int)($input['id_filial'] ?? 1);
    $sandboxSolicitado = array_key_exists('sandbox', $input) ? (bool)$input['sandbox'] : true;
    
    if ($pedidoAcertoId <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do pedido de acerto é obrigatório'
        ], 400);
    }
    
    if ($idTransacaoSolicitada <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID da transação ERP é obrigatório'
        ], 400);
    }
    
    try {
        $pdo = $this->pdo;
        
        // ============================================================
        // 1. BUSCAR DADOS DO PEDIDO DE ACERTO COM MOTORISTA E VEÍCULO
        // ============================================================
        $stmt = $pdo->prepare("
            SELECT 
                ap.id,
                ap.acerto_id,
                ap.entrega_id,
                ap.embarque_id,
                ap.pedido_erp_id,
                ap.numero_pedido,
                ap.cliente_id,
                ap.cliente_nome,
                ap.tipo_problema,
                ap.itens_afetados,
                ap.motivo,
                ap.observacoes,
                ap.valor_total,
                ap.status,
                ae.embarque_id as acerto_embarque_id,
                ae.motorista_id,
                ae.veiculo_id,
                ent.pedidos_ids as entrega_pedidos_ids,
                ent.pedido_id as entrega_pedido_id,
                ent.codigo_rastreamento,
                ent.horario_checkin,
                ent.horario_entrega,
                ent.nome_recebedor,
                -- Dados do motorista
                m.nome as motorista_nome,
                m.cpf as motorista_cpf,
                m.telefone as motorista_telefone,
                -- Dados do veículo
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                v.marca as veiculo_marca,
                v.cor as veiculo_cor
            FROM frota_acerto_pedido ap
            LEFT JOIN frota_acerto_embarque ae ON ae.id = ap.acerto_id
            LEFT JOIN frota_entrega ent ON ent.id = ap.entrega_id
            LEFT JOIN frota_motorista m ON m.id = ae.motorista_id
            LEFT JOIN frota_veiculo v ON v.id = ae.veiculo_id
            WHERE ap.id = :id AND ap.status IN ('pendente', 'processando')
        ");
        $stmt->execute(['id' => $pedidoAcertoId]);
        $pedidoAcerto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pedidoAcerto) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Pedido de acerto não encontrado ou já processado'
            ], 404);
        }
        
        // ============================================================
        // 2. DEFINIR IDTRANSACAO COM BASE NO TIPO
        // ============================================================
        $tipoProblema = $pedidoAcerto['tipo_problema'];
        $idTransacaoFinal = 0;
        
        $mapTransacao = [
            'faltante' => 19,
            'devolucao' => 20
        ];
        
        if (!isset($mapTransacao[$tipoProblema])) {
            return $this->json($response, ['success' => false, 'error' => 'Tipo de problema sem transação ERP configurada'], 400);
        }
        $idTransacaoFinal = $mapTransacao[$tipoProblema];
        if ($idTransacaoSolicitada !== $idTransacaoFinal) {
            return $this->json($response, ['success' => false, 'error' => "A transação correta para {$tipoProblema} é {$idTransacaoFinal}"], 400);
        }
        
        // ============================================================
        // 3. PROCESSAR ITENS
        // ============================================================
        $itens = json_decode($pedidoAcerto['itens_afetados'], true);
        if (empty($itens)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nenhum item encontrado no pedido de acerto'
            ], 400);
        }
        
        // ============================================================
        // 4. BUSCAR INFORMAÇÕES DOS ITENS NO ERP
        // ============================================================
        $itensIds = array_column($itens, 'iditem');
        $itensInfoMap = [];
        
        if (!empty($itensIds)) {
            $placeholders = implode(',', array_fill(0, count($itensIds), '?'));
            $stmtItem = $pdo->prepare("
                SELECT DISTINCT
                    i.iditem,
                    i.referencia,
                    i.descricao,
                    i.complemento,
                    i.pesobruto,
                    i.pesoliquido,
                    i.idunidadebasica as idunidade,
                    i.perccomissao,
                    e.valorprecovenda as valor_unitario,
                    e.valorcustocontabil,
                    e.valorcustomediounitario,
                    e.percmargem,
                    e.custogerencial,
                    e.idimposto,
                    imp.idsituacaotributaria,
                    imp.perc_ipi
                FROM item i
                JOIN estoque_filial e ON e.iditem = i.iditem
                LEFT JOIN imposto imp ON imp.idimposto = e.idimposto
                WHERE i.iditem IN ({$placeholders})
                AND e.idfilial = :idfilial
            ");
            $params = array_merge($itensIds, ['idfilial' => $idFilial]);
            $stmtItem->execute($params);
            $itensInfo = $stmtItem->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($itensInfo as $info) {
                $itensInfoMap[$info['iditem']] = $info;
            }
        }
        
        // ============================================================
        // 5. MONTAR ITENS PROCESSADOS
        // ============================================================
        $itensProcessados = [];
        $valorTotalItens = 0;
        $pesoBrutoTotal = 0;
        $pesoLiquidoTotal = 0;
        
        foreach ($itens as $item) {
            $iditem = (int)($item['iditem'] ?? 0);
            $quantidade = (float)($item['quantidade'] ?? 1);
            $info = $itensInfoMap[$iditem] ?? [];
            
            $valorUnitario = (float)($info['valor_unitario'] ?? $item['valor_unitario'] ?? 0);
            $valorTotalItem = $quantidade * $valorUnitario;
            
            $itensProcessados[] = [
                'iditem' => $iditem,
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotalItem,
                'peso_bruto' => (float)($info['pesobruto'] ?? 0) * $quantidade,
                'peso_liquido' => (float)($info['pesoliquido'] ?? 0) * $quantidade,
                'idunidade' => (int)($info['idunidade'] ?? $item['unidade'] ?? 1),
                'descricao' => $info['descricao'] ?? $item['descricao'] ?? '',
                'referencia' => $info['referencia'] ?? $item['referencia'] ?? '',
                'complemento' => $info['complemento'] ?? $item['complemento'] ?? '',
                'perc_comissao' => (float)($info['perccomissao'] ?? 0),
                'perc_margem' => (float)($info['percmargem'] ?? 0),
                'valorcustocontabil' => (float)($info['valorcustocontabil'] ?? 0),
                'valorcustogerencial' => (float)($info['custogerencial'] ?? 0),
                'valorcustomedio' => (float)($info['valorcustomediounitario'] ?? 0),
                'idimposto' => (int)($info['idimposto'] ?? 0),
                'idsituacaotributaria' => (int)($info['idsituacaotributaria'] ?? 0),
                'percipi' => (float)($info['perc_ipi'] ?? 0)
            ];
            
            $valorTotalItens += $valorTotalItem;
            $pesoBrutoTotal += (float)($info['pesobruto'] ?? 0) * $quantidade;
            $pesoLiquidoTotal += (float)($info['pesoliquido'] ?? 0) * $quantidade;
        }
        
        // ============================================================
        // 6. BUSCAR CLIENTE
        // ============================================================
        $stmtCliente = $pdo->prepare("
            SELECT 
                idcliforemp,
                fantasia,
                razao,
                uf,
                idvendedor
            FROM cliforemp
            WHERE idcliforemp = :id
        ");
        $stmtCliente->execute(['id' => $pedidoAcerto['cliente_id']]);
        $cliente = $stmtCliente->fetch(\PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Cliente não encontrado no ERP'
            ], 404);
        }
        
        // ============================================================
        // 7. BUSCAR TRANSAÇÃO
        // ============================================================
        $stmtTransacao = $pdo->prepare("
            SELECT idtransacao, idserie, descricao
            FROM pedido_transacao
            WHERE idtransacao = :id
        ");
        $stmtTransacao->execute(['id' => $idTransacaoFinal]);
        $transacao = $stmtTransacao->fetch(\PDO::FETCH_ASSOC);
        
        if (!$transacao) {
            return $this->json($response, [
                'success' => false,
                'error' => "Transação {$idTransacaoFinal} não encontrada"
            ], 404);
        }
        
        // ============================================================
        // 8. GERAR SEQUENCIAIS (SIMULADOS)
        // ============================================================
        $idPedidoPDA = rand(100000, 999999);
        $sequencialPortal = rand(1000, 9999);
        
        // ============================================================
        // 9. MONTAR DADOS COMPLETOS DO PEDIDO
        // ============================================================
        $nomeCliente = $cliente['fantasia'] ?? $cliente['razao'] ?? 'PORTAL[' . $sequencialPortal . ']';
        
        $config = [
            'idempresa' => 1,
            'idcondicao' => 65,
            'idmetodo' => 7,
            'idtabela' => 0,
            'tipofrete' => 0,
            'situacao' => 1,
            'fixavencimento' => 'N',
            'uf_origem' => 'SC',
            'hotsync' => 'S',
            'importado' => 'N',
            'status' => 1,
            'origem' => 1,
            'tipovendrepre' => 1,
            'idorigem' => 0
        ];
        
        // 🔥 MONTAR OBSERVAÇÃO COM TODOS OS DADOS
        $tipoLabel = $tipoProblema === 'faltante' ? 'FALTANTE' : 'DEVOLUÇÃO';
        $tipoEmoji = $tipoProblema === 'faltante' ? '⚠️' : '🔄';
        
        $dataEntrega = !empty($pedidoAcerto['horario_entrega']) 
            ? date('d/m/Y H:i:s', strtotime($pedidoAcerto['horario_entrega'])) 
            : 'N/A';
        
        $dataCheckin = !empty($pedidoAcerto['horario_checkin']) 
            ? date('d/m/Y H:i:s', strtotime($pedidoAcerto['horario_checkin'])) 
            : 'N/A';
        
        $observacaoERP = sprintf(
            "[%s] %s - PEDIDO DE %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📋 ACERTO: #%d | EMBARQUE: #%d | ENTREGA: #%d\n" .
            "🏷️ CÓDIGO: %s\n" .
            "👤 CLIENTE: %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🚚 MOTORISTA: %s | CPF: %s\n" .
            "🚛 VEÍCULO: %s | %s %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📅 CHECK-IN: %s\n" .
            "📅 ENTREGA: %s\n" .
            "👤 RECEBEDOR: %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "📝 MOTIVO: %s\n" .
            "📦 ITENS: %s\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "👤 USUÁRIO: %s | DATA: %s",
            $tipoEmoji,
            $tipoLabel,
            $tipoLabel,
            $pedidoAcerto['acerto_id'],
            $pedidoAcerto['embarque_id'],
            $pedidoAcerto['entrega_id'],
            $pedidoAcerto['codigo_rastreamento'] ?? 'N/A',
            $pedidoAcerto['cliente_nome'],
            $pedidoAcerto['motorista_nome'] ?? 'N/A',
            $pedidoAcerto['motorista_cpf'] ?? 'N/A',
            $pedidoAcerto['veiculo_placa'] ?? 'N/A',
            $pedidoAcerto['veiculo_marca'] ?? '',
            $pedidoAcerto['veiculo_modelo'] ?? '',
            $dataCheckin,
            $dataEntrega,
            $pedidoAcerto['nome_recebedor'] ?? 'N/A',
            $pedidoAcerto['motivo'] ?? 'Não informado',
            implode('; ', array_column($itensProcessados, 'referencia')),
            $usuarioNome,
            date('d/m/Y H:i:s')
        );
        
       $dadosPedido = array_merge($config, [
    'idpedidopda' => $idPedidoPDA,
    'sequencial_portal' => $sequencialPortal,
    'idfilial' => $idFilial,
    'idcliente' => (int)$pedidoAcerto['cliente_id'],
    'idtransacao' => $idTransacaoFinal,
    'idserie' => $transacao['idserie'] ?? '.',
    'idvendrepre' => (int)($cliente['idvendedor'] ?? 0),
    'uf_destino' => $cliente['uf'] ?? 'SC',
    'nomecliente' => $nomeCliente,
    'usuario' => $usuarioNome,
    'dataenvio' => date('Y-m-d'),
    'data' => date('Y-m-d'),
    'dataentrega' => date('Y-m-d', strtotime('+30 days')),
    'datahorapda' => date('Y-m-d H:i:s'),
    'datahora' => date('Y-m-d H:i:s'),
    'observacao' => $observacaoERP,
    'valortotalitens' => $valorTotalItens,
    'valortotalpedido' => $valorTotalItens,
    'pesobruto' => $pesoBrutoTotal,
    'pesoliquido' => $pesoLiquidoTotal,
    'itens' => $itensProcessados,
    // ============================================================
    // 🔥 DADOS ADICIONAIS PARA O SERVIÇO (USADOS NA OBSERVAÇÃO)
    // ============================================================
    'tipo_problema' => $tipoProblema,
    'acerto_id' => $pedidoAcerto['acerto_id'],
    'embarque_id' => $pedidoAcerto['embarque_id'],
    'entrega_id' => $pedidoAcerto['entrega_id'],
    'motorista_nome' => $pedidoAcerto['motorista_nome'] ?? 'N/A',
    'motorista_cpf' => $pedidoAcerto['motorista_cpf'] ?? 'N/A',
    'veiculo_placa' => $pedidoAcerto['veiculo_placa'] ?? 'N/A',
    'veiculo_modelo' => $pedidoAcerto['veiculo_modelo'] ?? '',
    'veiculo_marca' => $pedidoAcerto['veiculo_marca'] ?? '',
    'veiculo_cor' => $pedidoAcerto['veiculo_cor'] ?? '',
    'data_entrega' => $dataEntrega,
    'hora_entrega' => $dataEntrega,
    'data_checkin' => $dataCheckin,
    'cliente_nome' => $pedidoAcerto['cliente_nome'],
    'nome_recebedor' => $pedidoAcerto['nome_recebedor'] ?? 'N/A',
    'motivo' => $pedidoAcerto['motivo'] ?? 'Não informado',
    'pedido_original' => $pedidoAcerto['numero_pedido'] ?? 'N/A',
    'codigo_rastreamento' => $pedidoAcerto['codigo_rastreamento'] ?? 'N/A'
]);
        
        // ============================================================
        // 10. USAR O ERPPedidoService PARA GERAR OS SQLs
        // ============================================================
        $this->erpService->setSandboxMode($sandboxSolicitado);
        
        // Chamar o serviço para gerar os SQLs
        $resultado = $this->erpService->criarPedidoERP($dadosPedido);
        
        if (!$resultado['success']) {
            return $this->json($response, [
                'success' => false,
                'error' => $resultado['message'] ?? 'Erro ao processar pedido'
            ], 500);
        }
        
        // ============================================================
        // 11. RESPOSTA
        // ============================================================
        if (!$sandboxSolicitado) {
            $stmtStatus = $pdo->prepare("UPDATE frota_acerto_pedido SET status = 'processado', updated_at = NOW() WHERE id = :id AND status IN ('pendente', 'processando')");
            $stmtStatus->execute(['id' => $pedidoAcertoId]);
        }

        return $this->json($response, [
            'success' => true,
            'sandbox' => $sandboxSolicitado,
            'message' => $sandboxSolicitado ? 'MODO SANDBOX: pedido validado; nenhuma inserção foi feita.' : 'Pedido criado com sucesso no ERP.',
            'data' => [
                'idpedidopda' => $idPedidoPDA,
                'sequencial_portal' => $sequencialPortal,
                'idcliente' => $dadosPedido['idcliente'],
                'idtransacao' => $idTransacaoFinal,
                'idfilial' => $dadosPedido['idfilial'],
                'tipo_problema' => $tipoProblema,
                'valortotalpedido' => $dadosPedido['valortotalpedido'],
                'total_itens' => count($itensProcessados),
                'pedido_acerto_id' => $pedidoAcertoId
            ],
            'sql' => $resultado['sql'] ?? [],
            'dados_completos' => $dadosPedido
        ]);
        
    } catch (\Exception $e) {
        error_log('[Acerto] Erro ao criar pedido ERP: ' . $e->getMessage());
        error_log('[Acerto] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * Gera SQL para inserir o pedido (APENAS VISUALIZAÇÃO)
 */
private function gerarSQLPedido(array $dados): string
{
    return "
-- ============================================================
-- 🔥 SIMULAÇÃO - PEDIDO DE " . strtoupper($dados['tipo_problema'] ?? 'ACERTO') . "
-- ID TRANSAÇÃO: {$dados['idtransacao']}
-- ============================================================

INSERT INTO public.palmtop_pedido (
    idpedidopda, idempresa, idfilial, idimportacao, idcliente,
    idcondicao, idmetodo, idtabela, idtransacao, idvendrepre,
    tipofrete, situacao, valorfrete, valoritens, valorservico,
    valordesconto, valoripi, valorentrada, valortotal,
    perc_margem, pesoliquido, pesobruto, fixavencimento,
    uf_origem, uf_destino, hotsync, nomecliente, usuario,
    dataenvio, data, dataentrega, datahorapda, datahora,
    importado, idserie, status, origem, numero,
    tipovendrepre, observacao, idorigem
) VALUES (
    {$dados['idpedidopda']}, 
    {$dados['idempresa']}, 
    {$dados['idfilial']}, 
    {$dados['sequencial_portal']}, 
    {$dados['idcliente']},
    {$dados['idcondicao']}, 
    {$dados['idmetodo']}, 
    {$dados['idtabela']}, 
    {$dados['idtransacao']}, 
    {$dados['idvendrepre']},
    {$dados['tipofrete']}, 
    {$dados['situacao']}, 
    0, 
    {$dados['valortotalitens']}, 
    0,
    0, 0, 0, 
    {$dados['valortotalpedido']},
    0, 
    {$dados['pesoliquido']}, 
    {$dados['pesobruto']}, 
    '{$dados['fixavencimento']}',
    '{$dados['uf_origem']}', 
    '{$dados['uf_destino']}', 
    '{$dados['hotsync']}', 
    '" . addslashes($dados['nomecliente']) . "', 
    '" . addslashes($dados['usuario']) . "',
    '{$dados['dataenvio']}', 
    '{$dados['data']}', 
    '{$dados['dataentrega']}', 
    '{$dados['datahorapda']}', 
    '{$dados['datahora']}',
    '{$dados['importado']}', 
    '" . addslashes($dados['idserie']) . "', 
    {$dados['status']}, 
    {$dados['origem']}, 
    '0',
    {$dados['tipovendrepre']}, 
    '" . addslashes($dados['observacao']) . "', 
    {$dados['idorigem']}
);";
}

/**
 * Gera SQL para inserir um item (APENAS VISUALIZAÇÃO)
 * 🔥 CORRIGIDO: idunidade com valor padrão 1
 */
private function gerarSQLItem(array $dados, array $item, int $index): string
{
    $sequencial = $index + 1;
    $idunidade = $item['idunidade'] ?? 1;
    
    return "
INSERT INTO public.palmtop_pedido_item (
    idimportacao, sequencial, idpedidopda, idpedidoitem, iditem,
    idunidade, qt, valor, valoripi, valortotal,
    valorcusto, valordesconto, valorcomissao, valorpauta,
    perc_desconto, perc_ipi, perc_comissao, perc_margem,
    complemento, descricao, codigolido, perc_margem_cm,
    perc_margem_cg, valorcustogerencial, valorcustomedio,
    valorsugerido, idunidade_impressao, quant_impressao,
    valorunitarioimpressao, negritopromocao, valoracrescimo,
    perc_acrescimo, percacrescimopolitica, percdescontopolitica,
    valorprecovendapolitica, valorfrete, iditemcarrinho,
    iditemgarantia, percdescontopoliticavalorunit,
    percacrescimopoliticavalorunit, quant_reserva,
    idtabelapreco, percprice, valorprice,
    valorpromocaocondicaozero, valorpromocaocondicao,
    corpromocao, idpedidocompranf_e, iditempedidocompranf_e
) VALUES (
    {$dados['sequencial_portal']}, 
    {$sequencial}, 
    {$dados['idpedidopda']}, 
    0, 
    {$item['iditem']},
    {$idunidade}, 
    {$item['quantidade']}, 
    {$item['valor_unitario']}, 
    0, 
    {$item['valor_total']},
    {$item['valorcustocontabil']}, 
    0, 
    0, 
    0,
    0, 
    {$item['percipi']}, 
    {$item['perc_comissao']}, 
    {$item['perc_margem']},
    '" . addslashes(substr($item['complemento'] ?? '.', 0, 100)) . "', 
    '" . addslashes(substr($item['descricao'] ?? '.', 0, 80)) . "', 
    '" . addslashes($item['referencia'] ?? '.') . "', 
    0,
    0, 
    {$item['valorcustogerencial']}, 
    {$item['valorcustomedio']},
    {$item['valor_unitario']}, 
    {$idunidade}, 
    {$item['quantidade']},
    {$item['valor_unitario']}, 
    'N', 
    0,
    0, 0, 0,
    0, 0, 0,
    0, 0, 0,
    0, {$item['quantidade']},
    0, 0, 0,
    0, 0,
    '.', '.', 0
);";
}
/**
 * Gera SQL para atualizar totais (APENAS VISUALIZAÇÃO)
 */
private function gerarSQLUpdate(array $dados): string
{
    return "
-- ============================================================
-- 🔥 ATUALIZAR TOTAIS DO PEDIDO #{$dados['idpedidopda']}
-- ============================================================

UPDATE public.palmtop_pedido 
SET valoritens = {$dados['valortotalitens']},
    valortotal = {$dados['valortotalpedido']},
    pesobruto = {$dados['pesobruto']},
    pesoliquido = {$dados['pesoliquido']}
WHERE idpedidopda = {$dados['idpedidopda']};";
}

/**
 * GET /v1/frota/acerto/transacoes
 * Lista as transações disponíveis para criação de pedidos
 */
public function listarTransacoes(Request $request, Response $response): Response
{
    try {
        $stmt = $this->pdo->prepare("
            SELECT 
                idtransacao,
                descricao,
                idserie,
                tipo
            FROM pedido_transacao
            WHERE ativo = 'S' AND idtransacao IN (19, 20)
            ORDER BY descricao ASC
        ");
        $stmt->execute();
        $transacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $transacoes
        ]);
        
    } catch (\Exception $e) {
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
  
    /**
     * POST /v1/frota/acerto/{id}/finalizar
     * Finaliza o acerto
     */
    public function finalizarAcerto(Request $request, Response $response, array $args): Response
    {
        $acertoId = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        $usuarioNome = $user['username'] ?? $user['nome'] ?? 'Gestor';
        
        if ($acertoId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do acerto é obrigatório'
            ], 400);
        }
        
        try {
            $pdo = $this->pdo;
            $pdo->beginTransaction();
            
            // Verificar acerto
            $stmt = $pdo->prepare("
                SELECT id, embarque_id, status 
                FROM frota_acerto_embarque 
                WHERE id = :id AND status = 'em_andamento'
            ");
            $stmt->execute(['id' => $acertoId]);
            $acerto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$acerto) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Acerto não encontrado ou já finalizado'
                ], 404);
            }
            
            // Verificar se há pedidos pendentes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as pendentes 
                FROM frota_acerto_pedido 
                WHERE acerto_id = :acerto_id 
                  AND status IN ('pendente', 'processando')
            ");
            $stmt->execute(['acerto_id' => $acertoId]);
            $pendentes = (int)$stmt->fetchColumn();
            
            if ($pendentes > 0) {
                return $this->json($response, [
                    'success' => false,
                    'error' => "Existem {$pendentes} pedidos pendentes. Processe todos antes de finalizar.",
                    'pendentes' => $pendentes
                ], 400);
            }
            
            // Finalizar acerto
            $stmt = $pdo->prepare("
                UPDATE frota_acerto_embarque 
                SET 
                    status = 'finalizado',
                    data_fim_acerto = NOW(),
                    finalizado_por = :usuario_id,
                    finalizado_em = NOW(),
                    observacoes_gerais = COALESCE(observacoes_gerais, '') || :obs,
                    assinatura_gestor_url = :assinatura_gestor,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $acertoId,
                'usuario_id' => $usuarioId,
                'obs' => "\nFinalizado por: {$usuarioNome} em " . date('Y-m-d H:i:s'),
                'assinatura_gestor' => $input['assinatura_gestor'] ?? null
            ]);
            
            $this->registrarLog($acerto['embarque_id'], 'acerto_finalizado', 
                "Acerto finalizado por {$usuarioNome}", $usuarioId
            );
            
            $pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Acerto finalizado com sucesso',
                'data' => [
                    'acerto_id' => $acertoId,
                    'status' => 'finalizado'
                ]
            ]);
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Acerto] Erro ao finalizar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/acerto/{id}/cancelar
     * Cancela o acerto
     */
    public function cancelarAcerto(Request $request, Response $response, array $args): Response
    {
        $acertoId = (int)($args['id'] ?? 0);
        $user = $request->getAttribute('user');
        $usuarioId = $user['idusuario'] ?? 0;
        $usuarioNome = $user['username'] ?? $user['nome'] ?? 'Gestor';
        
        if ($acertoId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do acerto é obrigatório'
            ], 400);
        }
        
        try {
            $pdo = $this->pdo;
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                SELECT id, embarque_id, status 
                FROM frota_acerto_embarque 
                WHERE id = :id AND status IN ('pendente', 'em_andamento')
            ");
            $stmt->execute(['id' => $acertoId]);
            $acerto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$acerto) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Acerto não encontrado ou já finalizado'
                ], 404);
            }
            
            $stmt = $pdo->prepare("
                UPDATE frota_acerto_embarque 
                SET status = 'cancelado',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $acertoId]);
            
            $this->registrarLog($acerto['embarque_id'], 'acerto_cancelado', 
                "Acerto cancelado por {$usuarioNome}", $usuarioId
            );
            
            $pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Acerto cancelado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Acerto] Erro ao cancelar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    
    /**
     * GET /v1/frota/acerto/itens/buscar
     * Busca itens disponíveis
     */
    public function buscarItens(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $busca = trim($params['q'] ?? '');
        $idFilial = (int)($params['id_filial'] ?? 1);
        $limite = (int)($params['limite'] ?? 20);
        
        if (strlen($busca) < 2) {
            return $this->json($response, [
                'success' => true,
                'data' => [],
                'message' => 'Digite pelo menos 2 caracteres'
            ]);
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT
                    i.iditem,
                    i.referencia,
                    i.descricao,
                    i.pesobruto,
                    i.pesoliquido,
                    i.idunidadebasica as idunidade,
                    e.valorprecovenda as valor_unitario,
                    COALESCE(SUM(lfd.quantidade), 0) as saldo_estoque
                FROM item i
                JOIN estoque_filial e ON e.iditem = i.iditem
                LEFT JOIN lote_filial_deposito lfd ON lfd.iditem = i.iditem AND lfd.idfilial = e.idfilial
                WHERE e.idfilial = :idfilial
                  AND (i.referencia ILIKE :busca OR i.descricao ILIKE :busca)
                  AND i.ativo = 'S'
                GROUP BY i.iditem, e.valorprecovenda
                ORDER BY i.referencia ASC
                LIMIT :limite
            ");
            
            $stmt->execute([
                'idfilial' => $idFilial,
                'busca' => "%{$busca}%",
                'limite' => $limite
            ]);
            
            $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'data' => $itens,
                'total' => count($itens)
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/acerto/testar
     * Endpoint de teste para criação de pedido (sandbox)
     */
    public function testarCriacao(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $dadosTeste = [
            'idcliente' => (int)($input['idcliente'] ?? 1),
            'idtransacao' => (int)($input['idtransacao'] ?? 8),
            'idfilial' => (int)($input['idfilial'] ?? 1),
            'usuario' => 'TESTE_SANDBOX',
            'acerto_id' => 999,
            'embarque_id' => 999,
            'entrega_id' => 999,
            'tipo_problema' => $input['tipo_problema'] ?? 'faltante',
            'pedido_original' => 'TESTE-001',
            'observacao' => 'PEDIDO DE TESTE - MODO SANDBOX',
            'itens' => $input['itens'] ?? [
                ['iditem' => 1, 'quantidade' => 2, 'valor_unitario' => 10.50],
                ['iditem' => 2, 'quantidade' => 1, 'valor_unitario' => 25.00]
            ]
        ];
        
        $this->erpService->setSandboxMode(true);
        $resultado = $this->erpService->criarPedidoERP($dadosTeste);
        
        return $this->json($response, $resultado);
    }
    
    // ========================================================================
    // MÉTODOS AUXILIARES
    // ========================================================================
    
   /**
 * Registrar log na tabela frota_log_embarque
 * 🔥 CORRIGIDO: removido campo usuario_nome
 */
private function registrarLog($embarqueId, $acao, $descricao, $usuarioId = 0)
{
    try {
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_log_embarque (
                embarque_id, 
                acao, 
                descricao, 
                usuario_id, 
                data_hora
            ) VALUES (
                :embarque_id, 
                :acao, 
                :descricao, 
                :usuario_id, 
                NOW()
            )
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
    
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}