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
    
    // Embarques ativos também aparecem antes do primeiro acerto ser iniciado.
    $filtros[] = "(ae.id IS NOT NULL OR e.status IN ('em_andamento', 'problema', 'finalizado'))";
    
    // 🔥 ADICIONADO: Filtrar por status do acerto
    if (!empty($params['status_acerto'])) {
        $filtros[] = $params['status_acerto'] === 'em_andamento'
            ? "(ae.status = :status_acerto OR (ae.id IS NULL AND e.status = 'em_andamento'))"
            : "ae.status = :status_acerto";
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
            COALESCE(ae.status, 'pendente') as acerto_status,
            ae.data_inicio_acerto,
            ae.data_fim_acerto,
            (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id) as total_entregas,
            (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id AND status IN ('entregue', 'entregue_com_problema')) as entregas_concluidas,
              (SELECT COUNT(DISTINCT value::integer)
               FROM frota_entrega ent,
                   regexp_split_to_table(COALESCE(ent.pedidos_ids, ''), ',') value
               WHERE ent.embarque_id = e.id AND value ~ '^[0-9]+$') as total_pedidos,
              (SELECT COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(ent.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), ent.valor_total, 0)), 0)
               FROM frota_entrega ent WHERE ent.embarque_id = e.id) as valor_total,
              (SELECT COALESCE(SUM(ent.peso_total), 0) FROM frota_entrega ent WHERE ent.embarque_id = e.id) as peso_total,
            (SELECT COUNT(*) FROM frota_entrega_problema WHERE embarque_id = e.id AND status_problema IN ('pendente', 'em_analise')) as total_problemas
        FROM frota_embarque e
        LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
        LEFT JOIN frota_motorista m ON m.id = e.motorista_id
        LEFT JOIN frota_acerto_embarque ae ON ae.embarque_id = e.id
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
    $sqlCount = "SELECT COUNT(DISTINCT e.id) FROM frota_embarque e LEFT JOIN frota_acerto_embarque ae ON ae.embarque_id = e.id {$where}";
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
 *
 * 🔥 MUDANÇA 2026-09-18 (Bloco 4):
 *   - Adiciona LEFT JOIN com frota_problema_tratamento na query de problemas
 *   - Retorna em `problemas[]` os campos do tratamento (Camada 2):
 *     tratamento_id, tratamento_tipo, tratamento_status,
 *     tratamento_numero_comprovante, tratamento_comprovante_emitido_em,
 *     tratamento_acerto_pedido_id
 *   - Permite ao frontend decidir se mostra "Gerar Pedido" ou "Gerar Comprovante"
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

        // Buscar checklist, fotos e problemas para cada entrega
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

            // Problemas (com tratamento vinculado — Bloco 4)
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.tipo_problema,
                    p.item_id,
                    p.referencia,
                    p.descricao_problema,
                    p.quantidade_afetada,
                    p.valor_afetado,
                    p.status_problema,
                    p.prioridade,
                    p.solucao,
                    p.data_resolucao,
                    p.created_at,
                    t.id                     AS tratamento_id,
                    t.tipo_tratamento        AS tratamento_tipo,
                    t.status                 AS tratamento_status,
                    t.numero_comprovante     AS tratamento_numero_comprovante,
                    t.comprovante_emitido_em AS tratamento_comprovante_emitido_em,
                    t.acerto_pedido_id       AS tratamento_acerto_pedido_id
                FROM frota_entrega_problema p
                LEFT JOIN frota_problema_tratamento t ON t.problema_id = p.id
                WHERE p.entrega_id = :entrega_id
                ORDER BY p.created_at DESC
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
                ap.tipo_tratamento,
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
                if ($pdo->inTransaction()) $pdo->rollBack();
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Já existe um acerto em andamento para este embarque'
                ], 400);
            }
            
            // Buscar dados do embarque
            $stmt = $pdo->prepare("
                SELECT 
                    e.status as embarque_status,
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
                if ($pdo->inTransaction()) $pdo->rollBack();
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Embarque não encontrado'
                ], 404);
            }

            if ($embarque['embarque_status'] !== 'finalizado') {
                if ($pdo->inTransaction()) $pdo->rollBack();
                return $this->json($response, [
                    'success' => false,
                    'error' => 'O acerto só pode ser iniciado após a finalização do embarque pelo motorista.'
                ], 400);
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
    public function buscarPedidoERP(Request $request, Response $response): Response
    {
        $numero = trim($request->getQueryParams()['numero'] ?? '');
        if ($numero === '') return $this->json($response, ['success' => false, 'error' => 'Informe o número do pedido'], 400);
        $embarqueId = (int)($request->getQueryParams()['embarque_id'] ?? 0);
        if ($embarqueId <= 0) return $this->json($response, ['success' => false, 'error' => 'Embarque atual não informado'], 400);
        try {
            $digits = preg_replace('/\D+/', '', $numero);
            $stmt = $this->pdo->prepare("SELECT DISTINCT e.id as embarque_id, e.numero_embarque, ent.id as entrega_id, ent.cliente_nome, ent.status as entrega_status, p.idpedido, c.idcliforemp as cliente_erp_id, CASE WHEN TRIM(COALESCE(p.numero, '')) ~ '^[0-9]+$' AND TRIM(p.numero) <> '0' THEN TRIM(p.numero) ELSE p.idpedido::text END as numero_pedido, p.valortotalpedido FROM pedido p LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp JOIN frota_embarque e ON e.id = :embarque_id LEFT JOIN frota_entrega ent ON ent.embarque_id = e.id AND (ent.pedido_id = p.idpedido OR ((',' || COALESCE(ent.pedidos_ids, '') || ',') LIKE ('%,' || p.idpedido::text || ',%'))) WHERE (p.numero::text ILIKE :numero_numero OR p.idpedido::text = :idpedido OR c.fantasia ILIKE :cliente_fantasia OR c.razao ILIKE :cliente_razao OR c.idcliforemp::text = :cliente_id) AND (p.idembarque = e.erp_embarque_id OR ent.id IS NOT NULL) ORDER BY p.idpedido LIMIT 50");
            $like = '%' . $numero . '%';
            $stmt->execute(['embarque_id' => $embarqueId, 'numero_numero' => $like, 'idpedido' => $digits ?: '-1', 'cliente_fantasia' => $like, 'cliente_razao' => $like, 'cliente_id' => $digits ?: '-1']);
            $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!$pedidos) return $this->json($response, ['success' => false, 'error' => 'Pedido ou cliente não encontrado neste embarque'], 404);
            $ids = array_map('intval', array_column($pedidos, 'idpedido'));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtItens = $this->pdo->prepare("SELECT pi.idpedido, pi.iditem, i.referencia, i.descricao, pi.qt as quantidade, pi.valortotal as valor_total FROM pedido_item pi JOIN item i ON i.iditem = pi.iditem WHERE pi.idpedido IN ({$placeholders}) AND pi.ativo = 'S' ORDER BY pi.idpedido, i.referencia");
            $stmtItens->execute($ids);
            $itensPorPedido = [];
            foreach ($stmtItens->fetchAll(\PDO::FETCH_ASSOC) as $item) $itensPorPedido[(int)$item['idpedido']][] = $item;
            foreach ($pedidos as &$pedido) {
                $pedido['itens'] = $itensPorPedido[(int)$pedido['idpedido']] ?? [];
                $pedido['checklist'] = [];
                $pedido['fotos'] = [];
                if (!empty($pedido['entrega_id'])) {
                    $stmtChecklist = $this->pdo->prepare('SELECT referencia, descricao, quantidade_prevista, quantidade_entregue, status, motivo, foto_url FROM frota_checklist_entrega WHERE entrega_id = :id ORDER BY id');
                    $stmtChecklist->execute(['id' => $pedido['entrega_id']]);
                    $pedido['checklist'] = $stmtChecklist->fetchAll(\PDO::FETCH_ASSOC);
                    $stmtFotos = $this->pdo->prepare('SELECT tipo_foto, url_foto, descricao, created_at FROM frota_entrega_foto WHERE entrega_id = :id ORDER BY created_at');
                    $stmtFotos->execute(['id' => $pedido['entrega_id']]);
                    $pedido['fotos'] = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);
                }
            }
            unset($pedido);
            return $this->json($response, ['success' => true, 'data' => ['pedidos' => $pedidos, 'total' => count($pedidos)]]);
        } catch (\Exception $e) {
            error_log('Erro ao buscar pedido ERP no acerto: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível buscar o pedido'], 500);
        }
    }

/**
 * POST /v1/frota/acerto/pedido-problema
 * Cria um pedido de acerto a partir de um problema identificado.
 *
 * 🔥 MUDANÇA 2026-09-17 (Bloco 2, Passo 2.1):
 *   - Aceita o campo `tipo_tratamento` no payload:
 *       • 'faltante_com_estoque'  → transação ERP 19
 *       • 'faltante_sem_estoque'  → transação ERP 20
 *       • 'devolucao_comprovante' → gera comprovante (não cria pedido ERP)
 *   - Grava em 3 camadas: FATO (já existe) + TRATAMENTO (nova) + DOCUMENTO
 *   - Compatibilidade: se `tipo_tratamento` não vier, deriva do `tipo_problema`
 *   - Para DEVOLUÇÃO: NÃO cria pedido de acerto, só registra o tratamento
 *     e retorna `proximo_passo: 'gerar_comprovante'`
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
    $problemaId = (int)($input['problema_id'] ?? 0);

    // ============================================================
    // 🔥 NOVO: Resolver tipo_tratamento
    // ============================================================
    $tipoTratamentoRecebido = trim((string)($input['tipo_tratamento'] ?? ''));

    $tiposTratamentoValidos = [
        'faltante_com_estoque',
        'faltante_sem_estoque',
        'devolucao_comprovante'
    ];

    // Compatibilidade retroativa: se não veio, derivar do tipo_problema
    if ($tipoTratamentoRecebido === '') {
        if ($tipoProblema === 'faltante') {
            $tipoTratamentoRecebido = 'faltante_com_estoque'; // padrão seguro
        } elseif ($tipoProblema === 'devolucao') {
            $tipoTratamentoRecebido = 'devolucao_comprovante';
        }
    }

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

    if (!in_array($tipoTratamentoRecebido, $tiposTratamentoValidos, true)) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Tipo de tratamento inválido. Use: ' . implode(', ', $tiposTratamentoValidos)
        ], 400);
    }

    // Coerência entre tipo_problema e tipo_tratamento
    if ($tipoProblema === 'devolucao' && $tipoTratamentoRecebido !== 'devolucao_comprovante') {
        return $this->json($response, [
            'success' => false,
            'error' => 'Devolução só pode ser tratada como "devolucao_comprovante"'
        ], 400);
    }
    if ($tipoProblema === 'faltante' && $tipoTratamentoRecebido === 'devolucao_comprovante') {
        return $this->json($response, [
            'success' => false,
            'error' => 'Faltante não pode ser tratado como devolução'
        ], 400);
    }

    if (empty($itens)) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Pelo menos um item deve ser informado'
        ], 400);
    }

    // Mapear tratamento → transação ERP
    $idTransacaoErp = null;
    $tipoFaltante = null;

    if ($tipoTratamentoRecebido === 'faltante_com_estoque') {
        $idTransacaoErp = 19;
        $tipoFaltante = 'com_estoque';
    } elseif ($tipoTratamentoRecebido === 'faltante_sem_estoque') {
        $idTransacaoErp = 20;
        $tipoFaltante = 'sem_estoque';
    }
    // devolucao_comprovante → idTransacaoErp permanece NULL

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
                fc.erp_id AS cliente_erp_id,
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
            LEFT JOIN frota_cliente fc ON fc.id = ent.cliente_id
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
        $itensInfoMap = [];

        if (!empty($itensIds)) {
            $placeholders = implode(',', array_fill(0, count($itensIds), '?'));
            $idFilial = (int)($input['id_filial'] ?? 1);
            $stmtItem = $pdo->prepare("
                SELECT DISTINCT
                    i.iditem,
                    i.referencia,
                    i.descricao,
                    i.complemento,
                    i.pesobruto,
                    i.pesoliquido,
                    i.idunidadebasica AS idunidade,
                    i.perccomissao,
                    e.valorprecovenda AS valor_unitario,
                    e.valorcustocontabil,
                    e.valorcustomediounitario,
                    e.percmargem,
                    e.custogerencial,
                    e.percicmscompra,
                    e.idimposto,
                    COALESCE(ie.idsituacaotributaria, 0) AS idsituacaotributaria,
                    COALESCE(ie.perc_ipi, 0) AS perc_ipi
                FROM item i
                JOIN estoque_filial e ON e.iditem = i.iditem
                JOIN filial f ON (f.idempresa = e.idempresa AND f.idfilial = e.idfilial)
                LEFT JOIN imposto ON (imposto.idimposto = e.idimposto)
                LEFT JOIN imposto_estado ie ON (
                    ie.idimposto = imposto.idimposto
                    AND ie.tipo_enquadramento = f.tipoenquadraformapreco
                    AND ie.uf = f.uf
                )
                WHERE i.iditem IN ({$placeholders})
                AND e.idfilial = ?
            ");
            $params = array_merge($itensIds, [$idFilial]);
            $stmtItem->execute($params);
            $itensInfo = $stmtItem->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($itensInfo as $info) {
                $itensInfoMap[$info['iditem']] = $info;
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
            if ($valorUnitario == 0 && isset($itensInfoMap[$iditem])) {
                $valorUnitario = (float)($itensInfoMap[$iditem]['valor_unitario'] ?? 0);
            }

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

            $totalItem = $quantidade * $valorUnitario;
            $valorTotal += $totalItem;

            $itensFormatados[] = [
                'iditem' => $iditem,
                'referencia' => $item['referencia'] ?? ($itensInfoMap[$iditem]['referencia'] ?? ''),
                'descricao' => $item['descricao'] ?? ($itensInfoMap[$iditem]['descricao'] ?? ''),
                'unidade' => $item['unidade'] ?? ($itensInfoMap[$iditem]['idunidade'] ?? 'UN'),
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $totalItem
            ];

            $itensDetalhes[] = ($item['referencia'] ?? $itensInfoMap[$iditem]['referencia'] ?? 'Item') . ": {$quantidade} un";
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
            "Tipo Tratamento: %s\n" .
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
            $tipoTratamentoRecebido,
            $motivo ?: 'Não informado',
            $itensLista ?: 'Nenhum item listado',
            $usuarioNome,
            date('d/m/Y H:i:s')
        );

        // ============================================================
        // 6. RESOLVER CLIENTE ERP
        // ============================================================
        $pedidosErpIds = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string)($entrega['pedidos_ids'] ?? '')))
        )));
        if (empty($pedidosErpIds) && !empty($entrega['pedido_id'])) {
            $pedidosErpIds[] = (int)$entrega['pedido_id'];
        }

        if (empty($pedidosErpIds)) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'A entrega não possui pedido original vinculado no ERP'
            ], 409);
        }

        $placeholdersPedidos = implode(',', array_fill(0, count($pedidosErpIds), '?'));
        $stmtClientePedido = $pdo->prepare("
            SELECT DISTINCT p.idcliforemp, COALESCE(c.fantasia, c.razao) AS cliente_nome
            FROM pedido p
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            WHERE p.idpedido IN ({$placeholdersPedidos})
        ");
        $stmtClientePedido->execute($pedidosErpIds);
        $clientesPedidos = $stmtClientePedido->fetchAll(\PDO::FETCH_ASSOC);

        if (count($clientesPedidos) !== 1) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Os pedidos vinculados à entrega não pertencem a um único cliente ERP'
            ], 409);
        }

        $clienteErpId = (int)$clientesPedidos[0]['idcliforemp'];
        if (!empty($entrega['cliente_erp_id']) && (int)$entrega['cliente_erp_id'] !== $clienteErpId) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'O cliente da entrega diverge do cliente dos pedidos originais'
            ], 409);
        }

        $pedidoErpId = $pedidosErpIds[0];
        $numeroPedido = $entrega['pedido_id'] ?? $pedidoErpId;
        $clienteNome = $clientesPedidos[0]['cliente_nome'] ?: $entrega['cliente_nome'];

        // ============================================================
        // 🆕 7. GRAVAR CAMADA 2 (TRATAMENTO) — frota_problema_tratamento
        // ============================================================
        // Só grava se tivermos um `problema_id` válido vindo do frontend.
        // Se não vier, mantemos compatibilidade (tratamento implícito nos espelhos).
        $tratamentoId = null;

        if ($problemaId > 0) {
            $stmtProblema = $pdo->prepare("
                SELECT id FROM frota_entrega_problema
                WHERE id = :id AND entrega_id = :entrega_id
            ");
            $stmtProblema->execute(['id' => $problemaId, 'entrega_id' => $entregaId]);
            $problemaExiste = $stmtProblema->fetchColumn();

            if ($problemaExiste) {
                // Status inicial:
                //   faltante (com/sem estoque) → 'pendente' (aguardando criar pedido ERP)
                //   devolução                    → 'aguardando_fat' (aguardando comprovante)
                $statusInicial = ($tipoTratamentoRecebido === 'devolucao_comprovante')
                    ? 'aguardando_fat'
                    : 'pendente';

                $stmtTrat = $pdo->prepare("
                    INSERT INTO frota_problema_tratamento (
                        problema_id,
                        tipo_tratamento,
                        id_transacao_erp,
                        id_filial_erp,
                        transacao_descricao_snapshot,
                        valor_afetado,
                        status,
                        decidido_por,
                        decidido_em,
                        observacoes,
                        created_at,
                        updated_at
                    ) VALUES (
                        :problema_id,
                        :tipo_tratamento,
                        :id_transacao_erp,
                        :id_filial_erp,
                        :transacao_descricao,
                        :valor_afetado,
                        :status,
                        :decidido_por,
                        NOW(),
                        :observacoes,
                        NOW(),
                        NOW()
                    ) RETURNING id
                ");

                $stmtTrat->execute([
                    'problema_id' => $problemaId,
                    'tipo_tratamento' => $tipoTratamentoRecebido,
                    'id_transacao_erp' => $idTransacaoErp,
                    'id_filial_erp' => (int)($input['id_filial'] ?? 1),
                    'transacao_descricao' => null, // preenchido no Passo 2.2
                    'valor_afetado' => $valorTotal,
                    'status' => $statusInicial,
                    'decidido_por' => $usuarioId,
                    'observacoes' => $motivo
                ]);

                $tratamentoId = (int)$stmtTrat->fetchColumn();
            }
        }

        // ============================================================
        // 8. DECISÃO DE FLUXO: DEVOLUÇÃO ENCERRA AQUI
        // ============================================================
        // Para DEVOLUÇÃO: NÃO cria pedido de acerto.
        // Só registra o tratamento (Camada 2) com status 'aguardando_fat'.
        // O comprovante será gerado por endpoint separado (Passo 2.4).
        if ($tipoTratamentoRecebido === 'devolucao_comprovante') {
            $this->registrarLog(
                $embarqueId,
                'tratamento_devolucao_registrado',
                "Devolução registrada para entrega #{$entregaId} (tratamento #{$tratamentoId})",
                $usuarioId
            );

            $pdo->commit();

            return $this->json($response, [
                'success' => true,
                'message' => 'Devolução registrada. Gere o comprovante para faturamento.',
                'data' => [
                    'tratamento_id' => $tratamentoId,
                    'tipo_problema' => $tipoProblema,
                    'tipo_tratamento' => $tipoTratamentoRecebido,
                    'valor_total' => $valorTotal,
                    'total_itens' => count($itensFormatados),
                    'proximo_passo' => 'gerar_comprovante',
                    'observacao' => $observacaoCompleta
                ]
            ]);
        }

        // ============================================================
        // 9. FALTANTE (com/sem estoque): CRIAR PEDIDO DE ACERTO
        // ============================================================
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
                tipo_tratamento,
                id_transacao_erp,
                id_filial_erp,
                transacao_descricao_snapshot,
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
                :tipo_tratamento,
                :id_transacao_erp,
                :id_filial_erp,
                :transacao_descricao,
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
            'cliente_id' => $clienteErpId,
            'cliente_nome' => $clienteNome,
            'tipo_problema' => $tipoProblema,
            'itens_afetados' => json_encode($itensFormatados),
            'motivo' => $motivo,
            'observacoes' => $observacaoCompleta,
            'valor_total' => $valorTotal,
            'tipo_tratamento' => $tipoTratamentoRecebido,
            'id_transacao_erp' => $idTransacaoErp,
            'id_filial_erp' => (int)($input['id_filial'] ?? 1),
            'transacao_descricao' => null // preenchido no Passo 2.2
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
        // 10. VINCULAR TRATAMENTO AO PEDIDO (Camada 2 → Camada 3)
        // ============================================================
        if ($tratamentoId) {
            $stmtLink = $pdo->prepare("
                UPDATE frota_problema_tratamento
                SET acerto_pedido_id = :acerto_pedido_id,
                    status = 'pendente',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmtLink->execute([
                'acerto_pedido_id' => $pedidoAcertoId,
                'id' => $tratamentoId
            ]);
        }

        // ============================================================
        // 11. CRIAR ITENS DO ACERTO (frota_acerto_item)
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
                tipo_faltante,
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
                :tipo_faltante,
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
                'quantidade_entregue' => 0,
                'quantidade_faltante' => $item['quantidade'],
                'quantidade_devolvida' => 0,
                'valor_unitario' => $item['valor_unitario'],
                'valor_total' => $item['valor_total'],
                'tipo_faltante' => $tipoFaltante
            ]);
        }

        // ============================================================
        // 12. ATUALIZAR CONTADORES NO ACERTO
        // ============================================================
        $stmt = $pdo->prepare("
            UPDATE frota_acerto_embarque
            SET
                total_pedidos_faltantes = total_pedidos_faltantes + 1,
                valor_total_faltante = valor_total_faltante + :valor,
                updated_at = NOW()
            WHERE id = :acerto_id
        ");
        $stmt->execute([
            'acerto_id' => $acertoId,
            'valor' => $valorTotal
        ]);

        // ============================================================
        // 13. REGISTRAR LOG
        // ============================================================
        $labelTratamento = ($tipoTratamentoRecebido === 'faltante_com_estoque')
            ? 'faltante COM estoque'
            : 'faltante SEM estoque';

        $this->registrarLog(
            $embarqueId,
            'pedido_problema_criado',
            "Pedido de {$labelTratamento} criado para entrega #{$entregaId}",
            $usuarioId
        );

        // ============================================================
        // 14. COMMIT E RESPOSTA
        // ============================================================
        $pdo->commit();

        return $this->json($response, [
            'success' => true,
            'message' => 'Pedido de acerto criado com sucesso',
            'data' => [
                'acerto_pedido_id' => $pedidoAcertoId,
                'tratamento_id' => $tratamentoId,
                'tipo_problema' => $tipoProblema,
                'tipo_tratamento' => $tipoTratamentoRecebido,
                'id_transacao_erp' => $idTransacaoErp,
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
 * POST /v1/frota/acerto/tratamento/{id}/gerar-comprovante
 *
 * 🔥 NOVO 2026-09-18 (Bloco 2, Passo 2.4):
 *   Gera o número de comprovante de devolução (DEV-AAAA-NNNNNN) para um
 *   tratamento com tipo_tratamento = 'devolucao_comprovante'.
 *
 *   Regras:
 *     - 1 comprovante por tratamento (granularidade individual)
 *     - Numeração por filial + ano (UNIQUE id_filial, ano)
 *     - Formato: DEV-AAAA-NNNNNN (6 dígitos zero-padded)
 *     - Itens vêm de frota_checklist_entrega WHERE status = 'devolvido'
 *     - NÃO cria pedido ERP; apenas registra para o faturamento consumir
 *     - Após emitir, o tratamento muda de 'aguardando_fat' → 'comprovante_emitido'
 *
 *   🔥 CORRIGIDO 2026-09-18 (após teste real):
 *     - Removido SELECT das colunas `nome` e `cpf` em `usuario`
 *       (essas colunas NÃO existem na tabela — quebrava a transação)
 *     - Agora busca apenas `username` e usa o nome do JWT como fallback
 *     - `registrarLog()` movido para FORA da transação (não derruba o commit)
 *
 *   Retorno: dados estruturados para o frontend montar o HTML do comprovante.
 */
public function gerarComprovanteDevolucao(Request $request, Response $response, array $args): Response
{
    $tratamentoId = (int)($args['id'] ?? 0);
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $user = $request->getAttribute('user');
    $usuarioId = (int)($user['idusuario'] ?? 0);
    $usuarioNome = $user['username'] ?? $user['nome'] ?? 'Gestor';

    if ($tratamentoId <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do tratamento é obrigatório'
        ], 400);
    }

    // Variáveis que precisam existir depois do try/catch para o log
    $embarqueIdParaLog = 0;
    $numeroComprovanteParaLog = '';
    $emitenteNome = $usuarioNome;

    try {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        // ============================================================
        // 1. BUSCAR TRATAMENTO + CONTEXTO COMPLETO
        // ============================================================
        $stmt = $pdo->prepare("
            SELECT
                t.id                       AS tratamento_id,
                t.problema_id,
                t.tipo_tratamento,
                t.id_filial_erp,
                t.id_transacao_erp,
                t.transacao_descricao_snapshot,
                t.numero_comprovante,
                t.valor_afetado,
                t.status                   AS tratamento_status,
                t.observacoes              AS tratamento_obs,
                t.decidido_em,
                t.comprovante_emitido_em,
                -- Problema (Camada 1)
                p.id                       AS problema_origem_id,
                p.entrega_id,
                p.embarque_id,
                p.tipo_problema            AS problema_tipo,
                p.descricao_problema,
                p.quantidade_afetada,
                p.item_id                  AS problema_item_id,
                p.referencia               AS problema_referencia,
                p.status_problema,
                -- Entrega
                ent.cliente_nome,
                ent.endereco,
                ent.numero                 AS endereco_numero,
                ent.bairro,
                ent.cidade,
                ent.uf,
                ent.cep,
                ent.codigo_rastreamento,
                ent.nome_recebedor,
                ent.horario_checkin,
                ent.horario_entrega,
                -- Embarque
                e.id                       AS embarque_id,
                e.numero_embarque,
                e.nome_embarque,
                -- Acerto
                ae.id                      AS acerto_id,
                ae.gestor_nome,
                ae.data_inicio_acerto,
                -- Motorista
                m.nome                     AS motorista_nome,
                m.cpf                      AS motorista_cpf,
                m.telefone                 AS motorista_telefone,
                -- Veículo
                v.placa                    AS veiculo_placa,
                v.modelo                   AS veiculo_modelo,
                v.marca                    AS veiculo_marca,
                v.cor                      AS veiculo_cor
            FROM frota_problema_tratamento t
            INNER JOIN frota_entrega_problema p ON p.id = t.problema_id
            INNER JOIN frota_entrega ent        ON ent.id = p.entrega_id
            INNER JOIN frota_embarque e         ON e.id = ent.embarque_id
            LEFT JOIN frota_acerto_embarque ae  ON ae.embarque_id = e.id
                                                AND ae.status IN ('em_andamento', 'finalizado')
            LEFT JOIN frota_motorista m         ON m.id = ae.motorista_id
            LEFT JOIN frota_veiculo v           ON v.id = ae.veiculo_id
            WHERE t.id = :id
            ORDER BY ae.id DESC NULLS LAST
            LIMIT 1
        ");
        $stmt->execute(['id' => $tratamentoId]);
        $tratamento = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tratamento) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Tratamento não encontrado'
            ], 404);
        }

        // ============================================================
        // 2. VALIDAÇÕES DE NEGÓCIO
        // ============================================================
        if ($tratamento['tipo_tratamento'] !== 'devolucao_comprovante') {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Só é possível gerar comprovante para tratamentos do tipo "devolucao_comprovante"',
                'code'  => 'TIPO_TRATAMENTO_INVALIDO'
            ], 400);
        }

        if (!empty($tratamento['numero_comprovante'])) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Este tratamento já possui comprovante emitido: ' . $tratamento['numero_comprovante'],
                'code'  => 'COMPROVANTE_JA_EMITIDO',
                'numero_comprovante' => $tratamento['numero_comprovante']
            ], 409);
        }

        if ($tratamento['tratamento_status'] !== 'aguardando_fat') {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => "Status do tratamento é '{$tratamento['tratamento_status']}', esperado 'aguardando_fat'",
                'code'  => 'STATUS_INVALIDO'
            ], 400);
        }

        // ============================================================
        // 3. RESOLVER FILIAL
        // ============================================================
        $idFilial = (int)($tratamento['id_filial_erp'] ?? 0);
        if ($idFilial <= 0) {
            $idFilial = (int)($input['id_filial'] ?? 1);
        }
        $ano = (int)date('Y');

        // ============================================================
        // 4. GERAR NÚMERO SEQUENCIAL (upsert atômico)
        // ============================================================
        $stmtSeq = $pdo->prepare("
            INSERT INTO frota_comprovante_sequencia (id_filial, ano, ultimo_numero, created_at, updated_at)
            VALUES (:id_filial, :ano, 1, NOW(), NOW())
            ON CONFLICT (id_filial, ano)
            DO UPDATE SET
                ultimo_numero = frota_comprovante_sequencia.ultimo_numero + 1,
                updated_at = NOW()
            RETURNING ultimo_numero
        ");
        $stmtSeq->execute(['id_filial' => $idFilial, 'ano' => $ano]);
        $ultimoNumero = (int)$stmtSeq->fetchColumn();

        if ($ultimoNumero <= 0) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Falha ao gerar número sequencial do comprovante'
            ], 500);
        }

        $numeroComprovante = sprintf('DEV-%d-%06d', $ano, $ultimoNumero);

        // ============================================================
        // 5. BUSCAR ITENS DEVOLVIDOS (frota_checklist_entrega)
        //    Filtro: status = 'devolvido'
        // ============================================================
        $stmtItens = $pdo->prepare("
            SELECT
                ce.id,
                ce.item_id,
                ce.referencia,
                ce.descricao,
                ce.quantidade_prevista,
                ce.quantidade_entregue,
                ce.motivo,
                ce.foto_url
            FROM frota_checklist_entrega ce
            WHERE ce.entrega_id = :entrega_id
              AND ce.status = 'devolvido'
            ORDER BY ce.item_id ASC
        ");
        $stmtItens->execute(['entrega_id' => (int)$tratamento['entrega_id']]);
        $itensDevolvidos = $stmtItens->fetchAll(\PDO::FETCH_ASSOC);

        // ============================================================
        // 6. DADOS DO EMITENTE
        // 🔥 CORRIGIDO 2026-09-18: a tabela `usuario` NÃO possui as
        //    colunas `nome` e `cpf`. Buscamos apenas `username`.
        //    Se falhar, mantemos o nome vindo do JWT ($usuarioNome).
        // ============================================================
        if ($usuarioId > 0) {
            try {
                $stmtUser = $pdo->prepare("
                    SELECT username
                    FROM usuario
                    WHERE idusuario = :id
                    LIMIT 1
                ");
                $stmtUser->execute(['id' => $usuarioId]);
                $u = $stmtUser->fetch(\PDO::FETCH_ASSOC);
                if ($u && !empty($u['username'])) {
                    $emitenteNome = $u['username'];
                }
            } catch (\Exception $e) {
                // mantém o nome do JWT
                error_log('[Acerto] Aviso: não foi possível buscar username do emitente: ' . $e->getMessage());
            }
        }

        // ============================================================
        // 7. ATUALIZAR TRATAMENTO
        // ============================================================
        $obsAdicional = trim((string)($input['observacoes'] ?? ''));
        $obsAtual = (string)($tratamento['tratamento_obs'] ?? '');
        if ($obsAdicional !== '') {
            $obsAtual = $obsAtual === ''
                ? $obsAdicional
                : $obsAtual . "\n[Comprovante emitido] " . $obsAdicional;
        }

        $stmtUpd = $pdo->prepare("
            UPDATE frota_problema_tratamento
            SET numero_comprovante     = :numero_comprovante,
                comprovante_emitido_em = NOW(),
                comprovante_emitido_por = :usuario_id,
                status                 = 'comprovante_emitido',
                observacoes            = :observacoes,
                updated_at             = NOW()
            WHERE id = :id
              AND numero_comprovante IS NULL
        ");
        $stmtUpd->execute([
            'id' => $tratamentoId,
            'numero_comprovante' => $numeroComprovante,
            'usuario_id' => $usuarioId,
            'observacoes' => $obsAtual !== '' ? $obsAtual : null
        ]);

        if ($stmtUpd->rowCount() === 0) {
            $pdo->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Não foi possível emitir: o comprovante já foi gerado por outra operação.',
                'code'  => 'CONCORRENCIA'
            ], 409);
        }

        // ============================================================
        // 8. COMMIT PRIMEIRO
        // 🔥 CORRIGIDO 2026-09-18: o log é feito DEPOIS do commit.
        //    Se o log falhar, o comprovante já foi emitido e não
        //    derruba a operação principal.
        // ============================================================
        $pdo->commit();

        $embarqueIdParaLog = (int)$tratamento['embarque_id'];
        $numeroComprovanteParaLog = $numeroComprovante;

        // ============================================================
        // 9. REGISTRAR LOG (fora da transação)
        // ============================================================
        try {
            $this->registrarLog(
                $embarqueIdParaLog,
                'comprovante_devolucao_emitido',
                "Comprovante {$numeroComprovante} emitido por {$emitenteNome} (tratamento #{$tratamentoId})",
                $usuarioId
            );
        } catch (\Exception $e) {
            error_log('[Acerto] Aviso: falha ao registrar log do comprovante: ' . $e->getMessage());
        }

        // ============================================================
        // 10. MONTAR RESPOSTA ESTRUTURADA PARA O FRONT
        // ============================================================
        $itensFormatados = array_map(function ($it) {
            $qtd = (float)($it['quantidade_prevista'] ?? 0)
                 - (float)($it['quantidade_entregue'] ?? 0);
            return [
                'item_id'              => (int)($it['item_id'] ?? 0),
                'referencia'           => $it['referencia'] ?? '',
                'descricao'            => $it['descricao'] ?? '',
                'quantidade_prevista'  => (float)($it['quantidade_prevista'] ?? 0),
                'quantidade_entregue'  => (float)($it['quantidade_entregue'] ?? 0),
                'quantidade_devolvida' => max(0, $qtd),
                'motivo'               => $it['motivo'] ?? null,
                'foto_url'             => $it['foto_url'] ?? null
            ];
        }, $itensDevolvidos);

        return $this->json($response, [
            'success' => true,
            'message' => "Comprovante {$numeroComprovante} emitido com sucesso.",
            'data' => [
                'tratamento_id'      => $tratamentoId,
                'numero_comprovante' => $numeroComprovante,
                'emitido_em'         => date('Y-m-d H:i:s'),
                'emitido_por'        => [
                    'id'   => $usuarioId,
                    'nome' => $emitenteNome
                ],
                'filial' => [
                    'id_filial'  => $idFilial,
                    'ano'        => $ano,
                    'sequencial' => $ultimoNumero
                ],
                'tratamento' => [
                    'id'               => $tratamentoId,
                    'problema_id'      => (int)$tratamento['problema_id'],
                    'tipo_tratamento'  => $tratamento['tipo_tratamento'],
                    'id_transacao_erp' => $tratamento['id_transacao_erp'],
                    'valor_afetado'    => (float)($tratamento['valor_afetado'] ?? 0),
                    'decidido_em'      => $tratamento['decidido_em']
                ],
                'problema' => [
                    'id'                 => (int)$tratamento['problema_origem_id'],
                    'tipo_problema'      => $tratamento['problema_tipo'],
                    'descricao'          => $tratamento['descricao_problema'],
                    'quantidade_afetada' => (float)($tratamento['quantidade_afetada'] ?? 0),
                    'item_id'            => (int)($tratamento['problema_item_id'] ?? 0),
                    'referencia'         => $tratamento['problema_referencia']
                ],
                'entrega' => [
                    'id'                  => (int)$tratamento['entrega_id'],
                    'cliente_nome'        => $tratamento['cliente_nome'],
                    'endereco'            => trim(sprintf('%s %s', $tratamento['endereco'] ?? '', $tratamento['endereco_numero'] ?? '')),
                    'bairro'              => $tratamento['bairro'],
                    'cidade'              => $tratamento['cidade'],
                    'uf'                  => $tratamento['uf'],
                    'cep'                 => $tratamento['cep'],
                    'codigo_rastreamento' => $tratamento['codigo_rastreamento'],
                    'nome_recebedor'      => $tratamento['nome_recebedor'],
                    'horario_checkin'     => $tratamento['horario_checkin'],
                    'horario_entrega'     => $tratamento['horario_entrega']
                ],
                'embarque' => [
                    'id'              => (int)$tratamento['embarque_id'],
                    'numero_embarque' => $tratamento['numero_embarque'],
                    'nome_embarque'   => $tratamento['nome_embarque'],
                    'motorista_nome'  => $tratamento['motorista_nome'],
                    'motorista_cpf'   => $tratamento['motorista_cpf'],
                    'motorista_fone'  => $tratamento['motorista_telefone'],
                    'veiculo_placa'   => $tratamento['veiculo_placa'],
                    'veiculo_modelo'  => $tratamento['veiculo_modelo'],
                    'veiculo_marca'   => $tratamento['veiculo_marca'],
                    'veiculo_cor'     => $tratamento['veiculo_cor']
                ],
                'itens_devolvidos' => $itensFormatados,
                'observacoes'      => $obsAtual
            ]
        ]);

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Acerto] Erro ao gerar comprovante: ' . $e->getMessage());
        error_log('[Acerto] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao gerar comprovante: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * GET /v1/frota/acerto/tratamento/{id}/comprovante
 * Retorna os dados do comprovante de devolução já emitido (para reimpressão).
 * NÃO gera um novo — apenas lê o que já existe.
 */
public function buscarComprovanteDevolucao(Request $request, Response $response, array $args): Response
{
    $tratamentoId = (int)($args['id'] ?? 0);

    if ($tratamentoId <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID de tratamento inválido'
        ], 400);
    }

    try {
        $stmt = $this->pdo->prepare("
            SELECT
                t.id AS tratamento_id,
                t.tipo_tratamento,
                t.status,
                t.numero_comprovante,
                t.comprovante_emitido_em,
                t.comprovante_emitido_por,
                t.valor_afetado,
                t.observacoes,
                t.problema_id,
                t.acerto_pedido_id,
                t.id_transacao_erp,
                t.id_filial_erp,
                t.transacao_descricao_snapshot,
                p.tipo_problema,
                p.referencia,
                p.descricao_problema,
                p.quantidade_afetada,
                p.entrega_id,
                u.username AS emitido_por_username
            FROM frota_problema_tratamento t
            INNER JOIN frota_entrega_problema p ON p.id = t.problema_id
            LEFT JOIN usuario u ON u.idusuario = t.comprovante_emitido_por
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $tratamentoId]);
        $tratamento = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tratamento) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Tratamento não encontrado'
            ], 404);
        }

        if (empty($tratamento['numero_comprovante'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Este tratamento ainda não possui comprovante emitido'
            ], 400);
        }

        // ================================================================
        // Dados complementares (entrega, cliente, embarque, motorista, itens)
        // ================================================================
        $entregaId = (int)$tratamento['entrega_id'];

        $stmt = $this->pdo->prepare("
            SELECT
                e.id AS entrega_id,
                e.cliente_nome,
                e.endereco,
                e.numero AS numero_end,
                e.bairro,
                e.cidade,
                e.uf,
                e.codigo_rastreamento,
                e.embarque_id,
                eb.numero_embarque,
                eb.id AS embarque_id_real,
                m.nome AS motorista_nome,
                m.cpf AS motorista_cpf,
                v.placa AS veiculo_placa,
                v.modelo AS veiculo_modelo
            FROM frota_entrega e
            LEFT JOIN frota_embarque eb ON eb.id = e.embarque_id
            LEFT JOIN frota_motorista m ON m.id = eb.motorista_id
            LEFT JOIN frota_veiculo v ON v.id = eb.veiculo_id
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $entregaId]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Itens devolvidos (checklist onde status != 'entregue')
        $stmt = $this->pdo->prepare("
            SELECT
                referencia,
                descricao,
                quantidade_prevista,
                quantidade_entregue,
                status,
                motivo,
                (quantidade_prevista - quantidade_entregue) AS quantidade_devolvida
            FROM frota_checklist_entrega
            WHERE entrega_id = :id
              AND status = 'devolvido'
            ORDER BY referencia
        ");
        $stmt->execute(['id' => $entregaId]);
        $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fallback: se checklist não tem registro, usa o próprio problema
        if (empty($itens)) {
            $itens = [[
                'referencia'          => $tratamento['referencia'] ?? '—',
                'descricao'           => $tratamento['descricao_problema'] ?? '—',
                'quantidade_prevista' => $tratamento['quantidade_afetada'] ?? 0,
                'quantidade_entregue' => 0,
                'status'              => 'devolvido',
                'motivo'              => $tratamento['observacoes'] ?? '—',
                'quantidade_devolvida'=> $tratamento['quantidade_afetada'] ?? 0,
            ]];
        }

        $payload = [
            'tratamento_id'          => (int)$tratamento['tratamento_id'],
            'numero_comprovante'     => $tratamento['numero_comprovante'],
            'comprovante_emitido_em' => $tratamento['comprovante_emitido_em'],
            'emitente_nome'          => $tratamento['emitido_por_username'] ?? 'Gestor',
            'status'                 => $tratamento['status'],
            'tipo_tratamento'        => $tratamento['tipo_tratamento'],

            'entrega_id'             => $entrega['entrega_id'] ?? null,
            'cliente_nome'           => $entrega['cliente_nome'] ?? '—',
            'endereco'               => $entrega['endereco'] ?? '',
            'numero_end'             => $entrega['numero_end'] ?? '',
            'bairro'                 => $entrega['bairro'] ?? '',
            'cidade'                 => $entrega['cidade'] ?? '',
            'uf'                     => $entrega['uf'] ?? '',
            'codigo_rastreamento'    => $entrega['codigo_rastreamento'] ?? '',

            'embarque_id'            => $entrega['embarque_id_real'] ?? null,
            'numero_embarque'        => $entrega['numero_embarque'] ?? '—',
            'motorista_nome'         => $entrega['motorista_nome'] ?? '—',
            'motorista_cpf'          => $entrega['motorista_cpf'] ?? '',
            'veiculo_placa'          => $entrega['veiculo_placa'] ?? '—',
            'veiculo_modelo'         => $entrega['veiculo_modelo'] ?? '',

            'itens'                  => $itens,
            'valor_total'            => (float)($tratamento['valor_afetado'] ?? 0),
        ];

        return $this->json($response, [
            'success' => true,
            'data' => $payload
        ]);

    } catch (\Exception $e) {
        error_log('[Acerto] Erro ao buscar comprovante: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao buscar comprovante'
        ], 500);
    }
}
/**
 * POST /v1/frota/acerto/pedido/{id}/criar-erp
 * 🔥 SIMULAÇÃO - Apenas gera SQLs para visualização
 * NUNCA insere no palmtop_pedido ou palmtop_pedido_item
 *
 * 🔥 MUDANÇA 2026-09-18 (Bloco 2, Passo 2.2):
 *   - 2.2a: Guard bloqueando devolução (não gera pedido ERP)
 *   - 2.2b: Transação determinada por tipo_tratamento (não tipo_problema)
 *   - Propagação de status para Camada 2 (frota_problema_tratamento)
 *   - Compatibilidade retroativa: se tipo_tratamento for NULL, deriva de tipo_problema
 *
 * 🔥 MUDANÇA 2026-09-18 (Bloco 3, Passo 3.1):
 *   - Envia `tipo_faltante` no payload para o ERPPedidoService
 *     ('com_estoque' | 'sem_estoque' | null), derivado do tipo_tratamento
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
        // 🔥 2.2b: inclui tipo_tratamento, id_transacao_erp, id_filial_erp
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
                ap.tipo_tratamento,
                ap.id_transacao_erp,
                ap.id_filial_erp,
                ap.transacao_descricao_snapshot,
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
                fc.erp_id as entrega_cliente_erp_id,
                pedido_original.idcliforemp as pedido_cliente_erp_id,
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
            LEFT JOIN frota_cliente fc ON fc.id = ent.cliente_id
            LEFT JOIN pedido pedido_original ON pedido_original.idpedido = ap.pedido_erp_id
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
        // 🔥 MUDANÇA 2026-09-18 (Bloco 2, Passo 2.2a):
        // GUARD DE DEVOLUÇÃO
        // Devolução NÃO gera pedido ERP. Só comprovante para faturamento.
        // ============================================================
        if (($pedidoAcerto['tipo_tratamento'] ?? null) === 'devolucao_comprovante'
            || $pedidoAcerto['tipo_problema'] === 'devolucao') {
            return $this->json($response, [
                'success' => false,
                'error' => 'Devoluções não geram pedido ERP. Use a opção "Gerar Comprovante".',
                'code'  => 'DEVOLUCAO_NAO_GERA_ERP'
            ], 400);
        }

        $clienteErpId = (int)($pedidoAcerto['pedido_cliente_erp_id'] ?? $pedidoAcerto['entrega_cliente_erp_id'] ?? 0);
        if ($clienteErpId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Não foi possível identificar o cliente ERP do pedido original'
            ], 409);
        }

        if (!empty($pedidoAcerto['pedido_cliente_erp_id'])
            && !empty($pedidoAcerto['entrega_cliente_erp_id'])
            && (int)$pedidoAcerto['pedido_cliente_erp_id'] !== (int)$pedidoAcerto['entrega_cliente_erp_id']) {
            return $this->json($response, [
                'success' => false,
                'error' => 'O cliente da entrega diverge do cliente do pedido original'
            ], 409);
        }

        // ============================================================
        // 2. DEFINIR IDTRANSACAO COM BASE NO TIPO_TRATAMENTO
        // 🔥 MUDANÇA 2026-09-18 (Bloco 2, Passo 2.2b):
        //    - Usa tipo_tratamento (com/sem estoque), não tipo_problema
        //    - Compatibilidade retroativa: se NULL, deriva de tipo_problema
        //    - Devolução já foi bloqueada pelo guard do 2.2a
        //
        // 🔥 MUDANÇA 2026-09-18 (Bloco 3, Passo 3.1):
        //    - Deriva também `$tipoFaltante` para envio ao ERPPedidoService
        // ============================================================
        $tipoProblema   = $pedidoAcerto['tipo_problema'];
        $tipoTratamento = $pedidoAcerto['tipo_tratamento'] ?? null;

        if ($tipoTratamento === null) {
            // Compatibilidade com pedidos criados antes do Passo 2.1
            $tipoTratamento = ($tipoProblema === 'faltante')
                ? 'faltante_com_estoque'
                : null;
        }

        $mapTransacao = [
            'faltante_com_estoque' => 19,
            'faltante_sem_estoque' => 20
        ];

        $mapTipoFaltante = [
            'faltante_com_estoque' => 'com_estoque',
            'faltante_sem_estoque' => 'sem_estoque'
        ];

        if (!isset($mapTransacao[$tipoTratamento])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Tipo de tratamento inválido para criação de pedido ERP: ' . ($tipoTratamento ?? 'NULL'),
                'code'  => 'TIPO_TRATAMENTO_INVALIDO'
            ], 400);
        }

        $idTransacaoFinal = $mapTransacao[$tipoTratamento];
        $tipoFaltante     = $mapTipoFaltante[$tipoTratamento] ?? null; // 🔥 NOVO (Bloco 3.1)

        if ($idTransacaoSolicitada !== $idTransacaoFinal) {
            return $this->json($response, [
                'success' => false,
                'error' => "A transação correta para '{$tipoTratamento}' é {$idTransacaoFinal}"
            ], 400);
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
                    i.idunidadebasica AS idunidade,
                    i.perccomissao,
                    e.valorprecovenda AS valor_unitario,
                    e.valorcustocontabil,
                    e.valorcustomediounitario,
                    e.percmargem,
                    e.custogerencial,
                    e.percicmscompra,
                    e.idimposto,
                    COALESCE(ie.idsituacaotributaria, 0) AS idsituacaotributaria,
                    COALESCE(ie.perc_ipi, 0) AS perc_ipi
                FROM item i
                JOIN estoque_filial e ON e.iditem = i.iditem
                JOIN filial f ON (f.idempresa = e.idempresa AND f.idfilial = e.idfilial)
                LEFT JOIN imposto ON (imposto.idimposto = e.idimposto)
                LEFT JOIN imposto_estado ie ON (
                    ie.idimposto = imposto.idimposto 
                    AND ie.tipo_enquadramento = f.tipoenquadraformapreco 
                    AND ie.uf = f.uf
                )
                WHERE i.iditem IN ({$placeholders})
                AND e.idfilial = ?
            ");
            $params = array_merge($itensIds, [$idFilial]);
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
        $stmtCliente->execute(['id' => $clienteErpId]);
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
        // 9. MONTAR DADOS COMPLETOS DO PEDIDO
        // ============================================================
        $nomeCliente = $cliente['fantasia'] ?? $cliente['razao'] ?? 'PORTAL';

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
        $tipoLabel = $tipoTratamento === 'faltante_com_estoque'
            ? 'FALTANTE C/ ESTOQUE'
            : 'FALTANTE S/ ESTOQUE';
        $tipoEmoji = '';

        $dataEntrega = !empty($pedidoAcerto['horario_entrega']) 
            ? date('d/m/Y H:i:s', strtotime($pedidoAcerto['horario_entrega'])) 
            : 'N/A';

        $dataCheckin = !empty($pedidoAcerto['horario_checkin']) 
            ? date('d/m/Y H:i:s', strtotime($pedidoAcerto['horario_checkin'])) 
            : 'N/A';

        $observacaoERP = sprintf(
            "[%s] %s - PEDIDO DE %s\n" .
            "----------------------------------------------------------\n" .
            "ACERTO: #%d | EMBARQUE: #%d | ENTREGA: #%d\n" .
            "CODIGO: %s\n" .
            "CLIENTE: %s\n" .
            "----------------------------------------------------------\n" .
            "MOTORISTA: %s | CPF: %s\n" .
            "VEICULO: %s | %s %s\n" .
            "----------------------------------------------------------\n" .
            "CHECK-IN: %s\n" .
            "ENTREGA: %s\n" .
            "RECEBEDOR: %s\n" .
            "----------------------------------------------------------\n" .
            "TRATAMENTO: %s (Transação ERP %d)\n" .
            "MOTIVO: %s\n" .
            "ITENS: %s\n" .
            "----------------------------------------------------------\n" .
            "USUARIO: %s | DATA: %s",
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
            $tipoTratamento,
            $idTransacaoFinal,
            $pedidoAcerto['motivo'] ?? 'Não informado',
            implode('; ', array_column($itensProcessados, 'referencia')),
            $usuarioNome,
            date('d/m/Y H:i:s')
        );

        $dadosPedido = array_merge($config, [
            'idfilial' => $idFilial,
            'idcliente' => $clienteErpId,
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
            // 🔥 DADOS ADICIONAIS PARA O SERVIÇO
            // ============================================================
            'tipo_problema'   => $tipoProblema,
            'tipo_tratamento' => $tipoTratamento,
            'tipo_faltante'   => $tipoFaltante,   // 🔥 NOVO (Bloco 3.1)
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

        $resultado = $this->erpService->criarPedidoERP($dadosPedido);

        if (!$resultado['success']) {
            return $this->json($response, [
                'success' => false,
                'error' => $resultado['message'] ?? 'Erro ao processar pedido'
            ], 500);
        }

        $idPedidoPDA = (int)($resultado['data']['idpedidopda'] ?? 0);
        $sequencialPortal = (int)($resultado['data']['sequencial_portal'] ?? $idPedidoPDA);
        if ($idPedidoPDA <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'O ERP não retornou o identificador do pedido criado'
            ], 500);
        }

        // ============================================================
        // 11. RESPOSTA + PERSISTÊNCIA (se não for sandbox)
        // 🔥 2.2b: também propaga status para a Camada 2
        // ============================================================
        if (!$sandboxSolicitado) {
            $pdo->beginTransaction();
            try {
                // 1) Atualizar Camada 3 (frota_acerto_pedido)
                $stmtStatus = $pdo->prepare("
                    UPDATE frota_acerto_pedido 
                    SET status = 'criado_erp', 
                        pedido_erp_criado_id = :idpedidopda,
                        numero_pedido_criado = :numeropedido,
                        data_criacao_erp = NOW(),
                        updated_at = NOW() 
                    WHERE id = :id 
                      AND status IN ('pendente', 'processando')
                ");
                $stmtStatus->execute([
                    'id' => $pedidoAcertoId,
                    'idpedidopda' => $idPedidoPDA,
                    'numeropedido' => (string)$idPedidoPDA
                ]);

                // 2) 🔥 Atualizar Camada 2 (frota_problema_tratamento)
                $stmtTrat = $pdo->prepare("
                    UPDATE frota_problema_tratamento
                    SET status = 'criado_erp',
                        updated_at = NOW()
                    WHERE acerto_pedido_id = :acerto_pedido_id
                      AND status IN ('pendente', 'processando')
                ");
                $stmtTrat->execute([
                    'acerto_pedido_id' => $pedidoAcertoId
                ]);

                $pdo->commit();
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[Acerto] Falha ao marcar pedido como criado_erp: ' . $e->getMessage());
                throw $e;
            }
        }

        return $this->json($response, [
            'success' => true,
            'sandbox' => $sandboxSolicitado,
            'message' => $sandboxSolicitado 
                ? 'MODO SANDBOX: pedido validado; nenhuma inserção foi feita.' 
                : 'Pedido criado com sucesso no ERP.',
            'data' => [
                'idpedidopda' => $idPedidoPDA,
                'sequencial_portal' => $sequencialPortal,
                'idcliente' => $dadosPedido['idcliente'],
                'idtransacao' => $idTransacaoFinal,
                'idfilial' => $dadosPedido['idfilial'],
                'tipo_problema' => $tipoProblema,
                'tipo_tratamento' => $tipoTratamento,
                'tipo_faltante' => $tipoFaltante,   // 🔥 NOVO (Bloco 3.1)
                'valortotalpedido' => $dadosPedido['valortotalpedido'],
                'total_itens' => count($itensProcessados),
                'pedido_acerto_id' => $pedidoAcertoId
            ],
            'sql' => $resultado['sql'] ?? [],
            'dados_completos' => $dadosPedido
        ]);

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Acerto] Erro ao criar pedido ERP: ' . $e->getMessage());
        error_log('[Acerto] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao criar pedido no ERP: ' . $e->getMessage()
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
 * 🔥 CORRIGIDO: usa coluna `inativo = 'N'` em vez de `ativo = 'S'`
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
            WHERE inativo = 'N' 
              AND idtransacao IN (19, 20)
            ORDER BY descricao ASC
        ");
        $stmt->execute();
        $transacoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log('[Acerto-transacoes] Retornando ' . count($transacoes) . ' transações');
        
        return $this->json($response, [
            'success' => true,
            'data' => $transacoes,
            'total' => count($transacoes)
        ]);
        
    } catch (\Exception $e) {
        error_log('[Acerto-transacoes] ERRO: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function getResumoAcertos(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'pendente') AS pendentes,
                    COUNT(*) FILTER (WHERE status = 'em_andamento') AS em_andamento,
                    COUNT(*) FILTER (WHERE status = 'finalizado') AS finalizados,
                    COUNT(*) FILTER (WHERE status = 'cancelado') AS cancelados,
                    COALESCE(SUM(valor_total_faltante), 0) AS valor_total_faltante,
                    COALESCE(SUM(valor_total_devolvido), 0) AS valor_total_devolvido
                FROM frota_acerto_embarque
            ");

            return $this->json($response, [
                'success' => true,
                'data' => $stmt->fetch(\PDO::FETCH_ASSOC)
            ]);
        } catch (\Exception $e) {
            error_log('[Acerto-resumo] ERRO: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar resumo dos acertos'
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
 *
 * 🔥 MUDANÇA 2026-09-18 (Bloco 3, Passo 3.1):
 *   - Agora repassa `tipo_tratamento` e `tipo_faltante` para o ERPPedidoService
 *   - Permite validar a nova lógica sem tocar no ERP real
 */
public function testarCriacao(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];

    $dadosTeste = [
        'idcliente'       => (int)($input['idcliente'] ?? 1),
        'idtransacao'     => (int)($input['idtransacao'] ?? 8),
        'idfilial'        => (int)($input['idfilial'] ?? 1),
        'usuario'         => 'TESTE_SANDBOX',
        'acerto_id'       => (int)($input['acerto_id'] ?? 999),
        'embarque_id'     => (int)($input['embarque_id'] ?? 999),
        'entrega_id'      => (int)($input['entrega_id'] ?? 999),
        'tipo_problema'   => $input['tipo_problema']   ?? 'faltante',
        'tipo_tratamento' => $input['tipo_tratamento'] ?? null,   // 🔥 NOVO (Bloco 3.1)
        'tipo_faltante'   => $input['tipo_faltante']   ?? null,   // 🔥 NOVO (Bloco 3.1)
        'pedido_original' => 'TESTE-001',
        'observacao'      => 'PEDIDO DE TESTE - MODO SANDBOX',
        'itens'           => $input['itens'] ?? [
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
