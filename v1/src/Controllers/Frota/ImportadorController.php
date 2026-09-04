<?php

namespace Nutricional\Controllers\Frota;

use Nutricional\Controllers\BaseController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\Frota\GeolocalizacaoService;

class ImportadorController extends BaseController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
 

 /**
 * GET /v1/frota/embarques/{id}
 * Buscar um embarque específico com suas entregas
 */
public function getEmbarqueById(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    
    if ($id <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do embarque é obrigatório'
        ], 400);
    }
    
    try {
        $pdo = $this->pdo;
        
        $stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.numero_embarque,
                e.erp_embarque_id,
                e.veiculo_id,
                e.motorista_id,
                e.nome_embarque,
                e.status,
                e.data_saida,
                e.data_retorno,
                e.observacoes,
                e.created_at,
                e.updated_at,
                e.erp_ids_agrupados,               
                e.total_embarques_agrupados,       
                COALESCE(v.placa, '') as veiculo_placa,
                COALESCE(v.modelo, '') as veiculo_modelo,
                COALESCE(v.marca, '') as veiculo_marca,
                COALESCE(v.cor, '') as veiculo_cor,
                COALESCE(v.tipo, '') as veiculo_tipo,
                COALESCE(m.nome, '') as motorista_nome,
                COALESCE(m.telefone, '') as motorista_telefone,
                COALESCE(m.cpf, '') as motorista_cpf,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id) as total_entregas,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id AND status IN ('entregue', 'entregue_com_problema')) as entregas_concluidas,
                (SELECT COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(fe.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), fe.valor_total, 0)), 0) FROM frota_entrega fe WHERE fe.embarque_id = e.id) as valor_total_entregas,
                (SELECT COALESCE(SUM(peso_total), 0) FROM frota_entrega WHERE embarque_id = e.id) as peso_total_entregas
            FROM frota_embarque e
            LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = e.motorista_id
            WHERE e.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$embarque) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Embarque não encontrado'
            ], 404);
        }
        
        // ----------------------------------------------------------------
        // 1. BUSCAR ENTREGAS
        // ----------------------------------------------------------------
        $stmt = $pdo->prepare("
            SELECT 
                fe.id,
                fe.embarque_id,
                fe.pedido_id,
                fe.cliente_id,
                fe.ordem_entrega,
                fe.endereco,
                fe.numero,
                fe.complemento,
                fe.bairro,
                fe.cidade,
                fe.uf,
                fe.cep,
                fe.latitude,
                fe.longitude,
                fe.cliente_nome,
                fe.cliente_telefone,
                fe.valor_total,
                fe.peso_total,
                fe.status,
                fe.codigo_rastreamento,
                fe.origem_geolocalizacao,
                fe.status_geolocalizacao,
                fe.mensagem_geolocalizacao,
                fe.created_at,
                fe.updated_at,
                fe.total_pedidos_agrupados,
                fe.pedidos_ids,
                fe.foto_romaneio_url,
                fe.nome_recebedor
            FROM frota_entrega fe
            WHERE fe.embarque_id = :embarque_id
            ORDER BY fe.ordem_entrega ASC
        ");
        $stmt->execute(['embarque_id' => $id]);
        $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // ----------------------------------------------------------------
        // 2. BUSCAR CHECKLIST PARA CADA ENTREGA (COM descricao)
        // ----------------------------------------------------------------
        if (!empty($entregas)) {
            $entregaIds = array_column($entregas, 'id');
            if (!empty($entregaIds)) {
                $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));
                
                $stmtChecklist = $pdo->prepare("
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
                    ORDER BY item_id ASC
                ");
                $stmtChecklist->execute($entregaIds);
                $checklistItems = $stmtChecklist->fetchAll(\PDO::FETCH_ASSOC);
                
                // Agrupar checklist por entrega
                $checklistPorEntrega = [];
                foreach ($checklistItems as $item) {
                    $checklistPorEntrega[$item['entrega_id']][] = $item;
                }
                
                // Adicionar checklist a cada entrega
                foreach ($entregas as &$entrega) {
                    $entrega['checklist'] = $checklistPorEntrega[$entrega['id']] ?? [];
                }
                unset($entrega);
            }
        }
        
        $embarque['entregas'] = $entregas;
        
        // ----------------------------------------------------------------
        // 3. BUSCAR HISTÓRICO
        // ----------------------------------------------------------------
        $stmtHistorico = $pdo->prepare("
            SELECT 
                id,
                embarque_id,
                acao,
                descricao,
                usuario_id,
                usuario_nome,
                data_hora
            FROM frota_historico_embarque
            WHERE embarque_id = :embarque_id
            ORDER BY data_hora DESC
        ");
        $stmtHistorico->execute(['embarque_id' => $id]);
        $embarque['historico'] = $stmtHistorico->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular progresso
        $totalEntregas = (int)$embarque['total_entregas'];
        $entregasConcluidas = (int)$embarque['entregas_concluidas'];
        $embarque['progresso'] = $totalEntregas > 0 ? round(($entregasConcluidas / $totalEntregas) * 100) : 0;
        
        return $this->json($response, [
            'success' => true,
            'data' => $embarque
        ]);
        
    } catch (\Exception $e) {
        error_log('[Embarque] Erro ao buscar: ' . $e->getMessage());
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
    public function iniciarEmbarque(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do embarque é obrigatório'
            ], 400);
        }
        
        try {
            $pdo = $this->pdo;
            
            $stmt = $pdo->prepare("
                UPDATE frota_embarque 
                    SET status = 'em_andamento',
                    updated_at = NOW()
                    WHERE id = :id AND status = 'planejado'
                    RETURNING id
                    ");
            $stmt->execute(['id' => $id]);
            
            if (!$stmt->fetch()) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Embarque não encontrado ou já foi iniciado'
                ], 400);
            }
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Embarque iniciado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log('[Embarque] Erro ao iniciar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
 * POST /v1/frota/embarques/{id}/finalizar
 * Finalizar um embarque
 */
public function finalizarEmbarque(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    
    if ($id <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do embarque é obrigatório'
        ], 400);
    }
    
    try {
        $pdo = $this->pdo;
        
        // 🔥 CORRIGIDO: Incluir 'entregue_com_problema' como concluída
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('entregue', 'falha', 'entregue_com_problema') THEN 1 END) as concluidas
            FROM frota_entrega
            WHERE embarque_id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0 && $result['concluidas'] < $result['total']) {
            $pendentes = $result['total'] - $result['concluidas'];
            return $this->json($response, [
                'success' => false,
                'error' => "Existem {$pendentes} entregas pendentes. Finalize todas as entregas primeiro.",
                'pendentes' => $pendentes
            ], 400);
        }
        
        $stmt = $pdo->prepare("
            UPDATE frota_embarque 
                SET status = 'finalizado',
                data_retorno = NOW(),
                updated_at = NOW()
                WHERE id = :id AND status IN ('planejado', 'em_andamento')
                RETURNING id
        ");
        $stmt->execute(['id' => $id]);
        
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Embarque não encontrado ou não pode ser finalizado'
            ], 400);
        }
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Embarque finalizado com sucesso'
        ]);
        
    } catch (\Exception $e) {
        error_log('[Embarque] Erro ao finalizar: ' . $e->getMessage());
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
    public function cancelarEmbarque(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do embarque é obrigatório'
            ], 400);
        }
        
        try {
            $pdo = $this->pdo;
            
            $stmt = $pdo->prepare("
                UPDATE frota_embarque 
                    SET status = 'cancelado',
                    updated_at = NOW()
                    WHERE id = :id AND status != 'finalizado'
                    RETURNING id
                    ");
            $stmt->execute(['id' => $id]);
            
            if (!$stmt->fetch()) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Embarque não encontrado ou já foi finalizado'
                ], 400);
            }
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Embarque cancelado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log('[Embarque] Erro ao cancelar: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/embarques/{id}/reordenar
     * Reordenar as entregas de um embarque
     */
    public function reordenarEntregas(Request $request, Response $response, array $args): Response
    {
        $embarqueId = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $novaOrdem = $input['ordem'] ?? [];
        
        if ($embarqueId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do embarque é obrigatório'
            ], 400);
        }
        
        if (empty($novaOrdem)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Nova ordem não fornecida'
            ], 400);
        }
        
        try {
            $pdo = $this->pdo;
            $pdo->beginTransaction();
            
            // Atualizar ordem de cada entrega
            foreach ($novaOrdem as $posicao => $entregaId) {
                $stmt = $pdo->prepare("
                    UPDATE frota_entrega 
                        SET ordem_entrega = :ordem,
                        updated_at = NOW()
                        WHERE id = :entrega_id 
                        AND embarque_id = :embarque_id
                        ");
                $stmt->execute([
                    'ordem' => $posicao + 1,
                    'entrega_id' => (int)$entregaId,
                    'embarque_id' => $embarqueId
                ]);
            }
            
            $pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Ordem atualizada com sucesso',
                'data' => [
                    'embarque_id' => $embarqueId,
                    'total_entregas' => count($novaOrdem)
                ]
            ]);
            
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Reordenar] Erro: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * GET /v1/frota/importar/status
     * Status da última importação
     */
    public function status(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("
            SELECT 
            *,
            TO_CHAR(data_execucao, 'DD/MM/YYYY HH24:MI:SS') as data_execucao_formatada
            FROM frota_sincronizacao_log 
            ORDER BY id DESC 
            LIMIT 1
            ");
        $stmt->execute();
        $ultimoLog = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'ultima_sincronizacao' => $ultimoLog,
                'status' => $ultimoLog ? $ultimoLog['status'] : 'nunca_sincronizado'
            ]
        ]);
    }
    
 /**
 * GET /v1/frota/embarques
 * Listar todos os embarques do sistema com paginação
 */
public function getEmbarques(Request $request, Response $response): Response
{
    try {
        $pdo = $this->pdo;
        $params = $request->getQueryParams();
        
        $pagina = (int)($params['pagina'] ?? 1);
        $limite = (int)($params['limite'] ?? 10);
        $offset = ($pagina - 1) * $limite;
        $status = $params['status'] ?? '';
        $busca = $params['busca'] ?? '';
        $dataInicio = $params['data_inicio'] ?? '';
        $dataFim = $params['data_fim'] ?? '';
        
        // Montar filtros
        $filtros = [];
        $paramsBind = [];
        
        if (!empty($status)) {
            $filtros[] = "e.status = :status";
            $paramsBind['status'] = $status;
        }
        
        if (!empty($busca)) {
            $filtros[] = "(e.numero_embarque ILIKE :busca OR m.nome ILIKE :busca OR v.placa ILIKE :busca OR e.nome_embarque ILIKE :busca)";
            $paramsBind['busca'] = '%' . $busca . '%';
        }
        
        if (!empty($dataInicio)) {
            $filtros[] = "e.data_saida >= :data_inicio";
            $paramsBind['data_inicio'] = $dataInicio . ' 00:00:00';
        }
        
        if (!empty($dataFim)) {
            $filtros[] = "e.data_saida <= :data_fim";
            $paramsBind['data_fim'] = $dataFim . ' 23:59:59';
        }
        
        $where = !empty($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';
        
        $sql = "
            SELECT 
                e.id,
                e.numero_embarque,
                e.erp_embarque_id,
                e.veiculo_id,
                e.motorista_id,
                e.nome_embarque,
                e.status,
                e.data_saida,
                e.data_retorno,
                e.observacoes,
                e.created_at,
                e.updated_at,
                e.erp_ids_agrupados,               
                e.total_embarques_agrupados,      
                COALESCE(v.placa, '') as veiculo_placa,
                COALESCE(v.modelo, '') as veiculo_modelo,
                COALESCE(v.marca, '') as veiculo_marca,
                COALESCE(v.cor, '') as veiculo_cor,
                COALESCE(m.nome, '') as motorista_nome,
                COALESCE(m.telefone, '') as motorista_telefone,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id) as total_entregas,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id AND status IN ('entregue', 'entregue_com_problema')) as entregas_concluidas,
                (SELECT COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(fe.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), fe.valor_total, 0)), 0) FROM frota_entrega fe WHERE fe.embarque_id = e.id) as valor_total_entregas,
                (SELECT COALESCE(SUM(peso_total), 0) FROM frota_entrega WHERE embarque_id = e.id) as peso_total_entregas
            FROM frota_embarque e
            LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = e.motorista_id
            {$where}
            ORDER BY e.id DESC
            LIMIT {$limite} OFFSET {$offset}
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsBind);
        $embarques = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar total
        $sqlTotal = "SELECT COUNT(*) FROM frota_embarque e";
        if (!empty($filtros)) {
            $sqlTotal .= " WHERE " . implode(' AND ', $filtros);
        }
        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute($paramsBind);
        $total = (int)$stmtTotal->fetchColumn();
        
        // Buscar entregas, checklist e histórico para cada embarque
        foreach ($embarques as &$emb) {
            // ----------------------------------------------------------------
            // 1. BUSCAR ENTREGAS
            // ----------------------------------------------------------------
            $stmtEntregas = $pdo->prepare("
                SELECT 
                    fe.id,
                    fe.pedido_id,
                    fe.cliente_id,
                    fe.ordem_entrega,
                    fe.endereco,
                    fe.numero,
                    fe.complemento,
                    fe.bairro,
                    fe.cidade,
                    fe.uf,
                    fe.cep,
                    fe.latitude,
                    fe.longitude,
                    fe.cliente_nome,
                    fe.cliente_telefone,
                    fe.valor_total,
                    fe.peso_total,
                    fe.status,
                    fe.codigo_rastreamento,
                    fe.origem_geolocalizacao,
                    fe.status_geolocalizacao,
                    fe.mensagem_geolocalizacao,
                    fe.created_at,
                    fe.updated_at,
                    fe.total_pedidos_agrupados,
                    fe.pedidos_ids,
                    fe.foto_romaneio_url,
                    fe.nome_recebedor
                FROM frota_entrega fe
                WHERE fe.embarque_id = :embarque_id
                ORDER BY fe.ordem_entrega ASC
            ");
            $stmtEntregas->execute(['embarque_id' => $emb['id']]);
            $entregas = $stmtEntregas->fetchAll(\PDO::FETCH_ASSOC);
            $emb['entregas'] = $entregas;
            
            // ----------------------------------------------------------------
            // 2. BUSCAR CHECKLIST PARA CADA ENTREGA (COM descricao)
            // ----------------------------------------------------------------
            if (!empty($entregas)) {
                $entregaIds = array_column($entregas, 'id');
                if (!empty($entregaIds)) {
                    $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));
                    
                    $stmtChecklist = $pdo->prepare("
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
                        ORDER BY item_id ASC
                    ");
                    $stmtChecklist->execute($entregaIds);
                    $checklistItems = $stmtChecklist->fetchAll(\PDO::FETCH_ASSOC);
                    
                    // Agrupar checklist por entrega
                    $checklistPorEntrega = [];
                    foreach ($checklistItems as $item) {
                        $checklistPorEntrega[$item['entrega_id']][] = $item;
                    }
                    
                    // Adicionar checklist a cada entrega
                    foreach ($emb['entregas'] as &$entrega) {
                        $entrega['checklist'] = $checklistPorEntrega[$entrega['id']] ?? [];
                    }
                    unset($entrega);
                }
            }
            
            // ----------------------------------------------------------------
            // 3. BUSCAR HISTÓRICO
            // ----------------------------------------------------------------
            $stmtHistorico = $pdo->prepare("
                SELECT 
                    id,
                    embarque_id,
                    acao,
                    descricao,
                    usuario_id,
                    usuario_nome,
                    data_hora
                FROM frota_historico_embarque
                WHERE embarque_id = :embarque_id
                ORDER BY data_hora DESC
            ");
            $stmtHistorico->execute(['embarque_id' => $emb['id']]);
            $emb['historico'] = $stmtHistorico->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calcular progresso
            $totalEntregas = (int)$emb['total_entregas'];
            $entregasConcluidas = (int)$emb['entregas_concluidas'];
            $emb['progresso'] = $totalEntregas > 0 ? round(($entregasConcluidas / $totalEntregas) * 100) : 0;
        }
        
        return $this->json($response, [
            'success' => true,
            'data' => $embarques,
            'pagination' => [
                'total' => (int)$total,
                'total_paginas' => ceil($total / $limite),
                'pagina' => $pagina,
                'limite' => $limite
            ]
        ]);
        
    } catch (\Exception $e) {
        error_log('[Embarques] Erro ao listar: ' . $e->getMessage());
        error_log('[Embarques] Stack trace: ' . $e->getTraceAsString());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * GET /v1/frota/importar/embarque-detalhes/{id}
     * Buscar detalhes de um embarque específico do ERP com dados do motorista
     */
    public function getEmbarqueDetalhes(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID do embarque é obrigatório'
            ], 400);
        }

        try {
            $pdo = $this->pdo;
            
            $sql = "
            SELECT 
            ep.idembarque,
            ep.observacao as rota,
            ep.placa,
            ep.identregador as idmotorista,
            ep.data,
            ep.idfilial,
            ep.pex_conferido,
            ep.gerou_nf,
            c.idcliforemp as motorista_id,
            c.fantasia as motorista_nome,
            c.razao as motorista_razao,
            c.cpf as motorista_cpf,
            c.fone as motorista_telefone,
            c.email as motorista_email,
            c.endereco as motorista_endereco,
            c.bairro as motorista_bairro,
            (SELECT DISTINCT descricao FROM cidade WHERE idcidade = c.idcidade) as motorista_cidade,
            c.uf as motorista_uf,
            c.cep as motorista_cep,
            c.complemento as motorista_complemento,
            c.numero as motorista_numero,
            c.datacadastro as motorista_data_cadastro
            FROM embarque_pedido ep
            LEFT JOIN cliforemp c ON c.idcliforemp = ep.identregador
            WHERE ep.idembarque = :id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$embarque) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Embarque não encontrado'
                ], 404);
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $embarque
            ]);
            
        } catch (\Exception $e) {
            error_log('[Importador] Erro ao buscar detalhes do embarque: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
/**
 * POST /v1/frota/importar/criar-embarque
 * Criar um embarque no módulo de frotas a partir de um ou mais embarques do ERP
 */
public function criarEmbarqueDoERP(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];

    // ================================================================
    // 1. LER PARÂMETROS DE ENTRADA
    // ================================================================
    $idEmbarqueERP = (int)($input['id_embarque_erp'] ?? 0);
    $idsAgrupados = $input['ids_agrupados'] ?? [];
    if (!is_array($idsAgrupados)) {
        $idsAgrupados = [];
    }

    if ($idEmbarqueERP == 0 && !empty($idsAgrupados)) {
        $idEmbarqueERP = (int)$idsAgrupados[0];
    }

    if ($idEmbarqueERP == 0 && empty($idsAgrupados)) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Informe pelo menos um ID de embarque (id_embarque_erp ou ids_agrupados)'
        ], 400);
    }

    $todosIds = [];
    if ($idEmbarqueERP > 0) {
        $todosIds[] = $idEmbarqueERP;
    }
    foreach ($idsAgrupados as $id) {
        $id = (int)$id;
        if ($id > 0 && !in_array($id, $todosIds)) {
            $todosIds[] = $id;
        }
    }

    if (empty($todosIds)) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Nenhum ID válido fornecido'
        ], 400);
    }

    $veiculoId = (int)($input['veiculo_id'] ?? 0);
    $motoristaId = (int)($input['motorista_id'] ?? 0);
    $dataSaida = $input['data_saida'] ?? date('Y-m-d H:i:s');
    $usuarioId = (int)($input['usuario_id'] ?? 0);
    $nomeEmbarque = $input['nome_embarque'] ?? 'Embarque #' . $idEmbarqueERP;

    // ================================================================
    // 2. PROCESSAMENTO
    // ================================================================
    try {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        // ----------------------------------------------------------------
        // 3.1 VERIFICAR SE OS EMBARQUES JÁ FORAM IMPORTADOS
        // ----------------------------------------------------------------
        $placeholders = implode(',', array_fill(0, count($todosIds), '?'));
        $stmtCheck = $pdo->prepare("
            SELECT erp_embarque_id 
            FROM frota_embarque 
            WHERE erp_embarque_id IN ({$placeholders})
        ");
        $stmtCheck->execute($todosIds);
        $existentes = $stmtCheck->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($existentes)) {
            $jaImportados = implode(', ', $existentes);
            throw new \Exception("Embarques já importados: {$jaImportados}");
        }

        // ----------------------------------------------------------------
        // 3.2 BUSCAR DADOS DE TODOS OS EMBARQUES
        // ----------------------------------------------------------------
        $todosPedidos = [];
        $embarquePrincipal = null;
        $motoristaInfo = null;
        $veiculoInfo = null;
        $totalValor = 0;

        foreach ($todosIds as $erpId) {
            $stmt = $pdo->prepare("
                SELECT 
                    ep.idembarque,
                    ep.observacao as rota,
                    ep.placa,
                    ep.identregador as idmotorista,
                    ep.pex_conferido,
                    c.idcliforemp as motorista_id,
                    c.fantasia as motorista_nome,
                    c.razao as motorista_razao,
                    c.cpf as motorista_cpf,
                    c.fone as motorista_telefone,
                    c.email as motorista_email,
                    c.endereco as motorista_endereco,
                    c.bairro as motorista_bairro,
                    (SELECT DISTINCT descricao FROM cidade WHERE idcidade = c.idcidade) as motorista_cidade,
                    c.uf as motorista_uf,
                    c.cep as motorista_cep,
                    c.complemento as motorista_complemento,
                    c.numero as motorista_numero
                FROM embarque_pedido ep
                LEFT JOIN cliforemp c ON c.idcliforemp = ep.identregador
                WHERE ep.idembarque = :id
                AND ep.pex_conferido = 'S'
            ");
            $stmt->execute(['id' => $erpId]);
            $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$embarque) {
                throw new \Exception("Embarque #{$erpId} não encontrado ou não está separado");
            }

            if (!$embarquePrincipal) {
                $embarquePrincipal = $embarque;
                $motoristaInfo = [
                    'id' => $embarque['motorista_id'],
                    'nome' => $embarque['motorista_nome'] ?? $embarque['motorista_razao'] ?? 'Motorista ERP #' . $embarque['idmotorista'],
                    'cpf' => $embarque['motorista_cpf'] ?? '',
                    'telefone' => $embarque['motorista_telefone'] ?? '',
                    'email' => $embarque['motorista_email'] ?? '',
                    'endereco' => $embarque['motorista_endereco'] ?? '',
                    'bairro' => $embarque['motorista_bairro'] ?? '',
                    'cidade' => $embarque['motorista_cidade'] ?? '',
                    'uf' => $embarque['motorista_uf'] ?? '',
                    'cep' => $embarque['motorista_cep'] ?? '',
                    'complemento' => $embarque['motorista_complemento'] ?? '',
                    'numero' => $embarque['motorista_numero'] ?? ''
                ];
                $veiculoInfo = [
                    'placa' => $embarque['placa'] ?? ''
                ];
            }

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
                    ce.latitude,
                    ce.longitude,
                    cid.descricao as cidade_nome,
                    TRIM(
                        COALESCE(c.endereco, '') || 
                        CASE WHEN c.numero IS NOT NULL AND c.numero != '' THEN ', ' || c.numero ELSE '' END ||
                        CASE WHEN c.bairro IS NOT NULL AND c.bairro != '' THEN ', ' || c.bairro ELSE '' END ||
                        ', ' || COALESCE(cid.descricao, '') ||
                        ', ' || COALESCE(c.uf, '') ||
                        CASE WHEN c.cep IS NOT NULL AND c.cep != '' THEN ', CEP: ' || c.cep ELSE '' END
                    ) as endereco_completo,
                    c.complemento
                FROM pedido p
                LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
                LEFT JOIN cliforemp_endereco ce ON ce.idcliforemp = c.idcliforemp
                LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                WHERE p.idembarque = :idembarque
                AND p.status IN (4, 5)
            ");
            $stmt->execute(['idembarque' => $erpId]);
            $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($pedidos as $pedido) {
                $todosPedidos[] = $pedido;
                $totalValor += (float)$pedido['valortotalpedido'];
            }
        }

        if (empty($todosPedidos)) {
            throw new \Exception('Nenhum pedido encontrado nos embarques selecionados');
        }

        // ----------------------------------------------------------------
        // 3.3 AGRUPAR PEDIDOS POR CLIENTE
        // ----------------------------------------------------------------
        $pedidosAgrupados = [];
        foreach ($todosPedidos as $pedido) {
            $clienteId = $pedido['idcliforemp'];

            if (empty($clienteId)) {
                $cnpjCpf = $pedido['cnpj_cpf'] ?? '';
                if (!empty($cnpjCpf)) {
                    $chave = 'CNPJ_' . preg_replace('/\D/', '', $cnpjCpf);
                } else {
                    $enderecoKey = trim(
                        ($pedido['endereco'] ?? '') . 
                        ($pedido['numero'] ?? '') . 
                        ($pedido['bairro'] ?? '') . 
                        ($pedido['cidade_nome'] ?? '') . 
                        ($pedido['uf'] ?? '')
                    );
                    $chave = 'NOME_' . trim($pedido['cliente_nome'] ?? '') . '_' . md5($enderecoKey);
                }
            } else {
                $chave = 'ID_' . $clienteId;
            }

            if (isset($pedidosAgrupados[$chave])) {
                $pedidosAgrupados[$chave]['pedidos'][] = $pedido;
                $pedidosAgrupados[$chave]['total_pedidos']++;
                $pedidosAgrupados[$chave]['valor_total_agrupado'] += (float)$pedido['valortotalpedido'];
                $pedidosAgrupados[$chave]['pedidos_ids'][] = $pedido['idpedido'];
                if (empty($pedidosAgrupados[$chave]['idcliforemp']) && !empty($pedido['idcliforemp'])) {
                    $pedidosAgrupados[$chave]['idcliforemp'] = $pedido['idcliforemp'];
                }
            } else {
                $pedidosAgrupados[$chave] = [
                    'idcliforemp' => $pedido['idcliforemp'] ?? null,
                    'cliente_nome' => $pedido['cliente_nome'] ?? $pedido['cliente_razao'] ?? 'Cliente',
                    'endereco' => $pedido['endereco'] ?? '',
                    'numero' => $pedido['numero'] ?? '',
                    'bairro' => $pedido['bairro'] ?? '',
                    'uf' => $pedido['uf'] ?? '',
                    'cep' => $pedido['cep'] ?? '',
                    'telefone' => $pedido['telefone'] ?? '',
                    'latitude' => $pedido['latitude'] ?? null,
                    'longitude' => $pedido['longitude'] ?? null,
                    'cidade_nome' => $pedido['cidade_nome'] ?? '',
                    'endereco_completo' => $pedido['endereco_completo'] ?? '',
                    'complemento' => $pedido['complemento'] ?? '',
                    'pedidos' => [$pedido],
                    'total_pedidos' => 1,
                    'valor_total_agrupado' => (float)$pedido['valortotalpedido'],
                    'pedidos_ids' => [$pedido['idpedido']],
                    'cnpj_cpf' => $pedido['cnpj_cpf'] ?? ''
                ];
            }
        }

        $pedidos = array_values($pedidosAgrupados);
        $totalEntregas = count($pedidos);

        // ----------------------------------------------------------------
        // 3.4 GEOLOCALIZAÇÃO
        // ----------------------------------------------------------------
        $geolocalizacaoService = new \Nutricional\Services\Frota\GeolocalizacaoService($pdo);

        foreach ($pedidos as &$grupo) {
            $enderecoCompleto = $grupo['endereco_completo'] ?? '';

            if (empty($enderecoCompleto)) {
                $enderecoCompleto = trim(
                    ($grupo['endereco'] ?? '') . 
                    (!empty($grupo['numero']) ? ', ' . $grupo['numero'] : '') .
                    (!empty($grupo['bairro']) ? ', ' . $grupo['bairro'] : '') .
                    ', ' . ($grupo['cidade_nome'] ?? '') .
                    ', ' . ($grupo['uf'] ?? '') .
                    (!empty($grupo['cep']) ? ', CEP: ' . $grupo['cep'] : '')
                );
            }

            $geoEncontrado = false;
            $resultadoGeo = null;

            // NÍVEL 1: Buscar na frota_cliente
            $resultadoGeo = $geolocalizacaoService->buscarNaFrotaCliente($grupo['idcliforemp']);
            if ($resultadoGeo['success']) {
                $geoEncontrado = true;
            }

            // NÍVEL 2: Google Maps
            if (!$geoEncontrado && !empty($enderecoCompleto)) {
                $resultadoGeo = $geolocalizacaoService->buscarNoGoogleMaps($enderecoCompleto);
                if ($resultadoGeo['success']) {
                    $geoEncontrado = true;
                }
            }

            // NÍVEL 3: Simplificado (verificar se o método existe)
            if (!$geoEncontrado && !empty($enderecoCompleto)) {
                if (method_exists($geolocalizacaoService, 'buscarNoGoogleMapsSimplificado')) {
                    $resultadoGeo = $geolocalizacaoService->buscarNoGoogleMapsSimplificado($enderecoCompleto);
                    if ($resultadoGeo['success']) {
                        $geoEncontrado = true;
                    }
                } else {
                    // Fallback: tentar por CEP
                    if (!empty($grupo['cep'])) {
                        $cepLimpo = preg_replace('/[^0-9]/', '', $grupo['cep']);
                        if (strlen($cepLimpo) === 8) {
                            $resultadoGeo = $geolocalizacaoService->buscarPorCEP($cepLimpo);
                            if ($resultadoGeo['success']) {
                                $geoEncontrado = true;
                            }
                        }
                    }
                }
            }

            if ($geoEncontrado && $resultadoGeo['success']) {
                $grupo['latitude'] = $resultadoGeo['latitude'];
                $grupo['longitude'] = $resultadoGeo['longitude'];
                $grupo['status_geo'] = $resultadoGeo['confiavel'] ? 'valido' : 'pendente_confirmacao';
                $grupo['origem_geo'] = $resultadoGeo['origem'];
                $grupo['mensagem_geo'] = $resultadoGeo['mensagem'];
            } else {
                $grupo['latitude'] = null;
                $grupo['longitude'] = null;
                $grupo['status_geo'] = 'pendente_geolocalizacao';
                $grupo['origem_geo'] = 'nenhuma';
                $grupo['mensagem_geo'] = $resultadoGeo['mensagem'] ?? 'Nenhuma coordenada encontrada';
            }
        }
        unset($grupo);

        // ----------------------------------------------------------------
        // 3.5 CRIAR/VERIFICAR MOTORISTA
        // ----------------------------------------------------------------
        $motoristaFinalId = null;
        $motoristaCriado = false;
        $motoristaNome = '';

        if ($motoristaId == 0 && !empty($motoristaInfo['id'])) {
            $motoristaId = (int)$motoristaInfo['id'];
        }

        if ($motoristaId > 0) {
            $stmtMotorista = $pdo->prepare("
                SELECT id, nome FROM frota_motorista 
                WHERE id = :id OR erp_id = :erp_id
            ");
            $stmtMotorista->execute(['id' => $motoristaId, 'erp_id' => $motoristaId]);
            $motoristaExistente = $stmtMotorista->fetch(\PDO::FETCH_ASSOC);

            if (!$motoristaExistente) {
                $motoristaNome = $motoristaInfo['nome'] ?? 'Motorista ERP #' . $motoristaId;

                $stmtInsert = $pdo->prepare("
                    INSERT INTO frota_motorista (
                        erp_id, nome, cpf, cnh, telefone, email, endereco, status, created_at, updated_at
                    ) VALUES (
                        :erp_id, :nome, :cpf, :cnh, :telefone, :email, :endereco, 'ativo', NOW(), NOW()
                    ) RETURNING id
                ");
                $stmtInsert->execute([
                    'erp_id' => $motoristaId,
                    'nome' => $motoristaNome,
                    'cpf' => $motoristaInfo['cpf'] ?: null,
                    'cnh' => '',
                    'telefone' => $motoristaInfo['telefone'] ?: null,
                    'email' => $motoristaInfo['email'] ?: null,
                    'endereco' => $motoristaInfo['endereco'] ?: null
                ]);
                $motoristaFinalId = $stmtInsert->fetchColumn();
                $motoristaCriado = true;
            } else {
                $motoristaFinalId = $motoristaExistente['id'];
                $motoristaNome = $motoristaExistente['nome'];
            }
        }

        if (!$motoristaFinalId) {
            $motoristaNome = 'Motorista Automático ' . date('YmdHis');
            $stmtInsert = $pdo->prepare("
                INSERT INTO frota_motorista (nome, cnh, status, created_at, updated_at)
                VALUES (:nome, '', 'ativo', NOW(), NOW())
                RETURNING id
            ");
            $stmtInsert->execute(['nome' => $motoristaNome]);
            $motoristaFinalId = $stmtInsert->fetchColumn();
            $motoristaCriado = true;
        }

        // ----------------------------------------------------------------
        // 3.6 CRIAR/VERIFICAR VEÍCULO
        // ----------------------------------------------------------------
        $veiculoFinalId = null;
        $veiculoCriado = false;
        $veiculoPlaca = '';

        if ($veiculoId > 0) {
            $stmt = $pdo->prepare("SELECT id, placa FROM frota_veiculo WHERE id = :id");
            $stmt->execute(['id' => $veiculoId]);
            $veiculo = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($veiculo) {
                $veiculoFinalId = $veiculo['id'];
                $veiculoPlaca = $veiculo['placa'];
                $veiculoCriado = false;
            } else {
                throw new \Exception("Veículo ID {$veiculoId} não encontrado no sistema.");
            }
        } else {
            $placaERP = trim($veiculoInfo['placa'] ?? '');
            if (!empty($placaERP)) {
                $stmtPlaca = $pdo->prepare("SELECT id, placa FROM frota_veiculo WHERE placa = :placa");
                $stmtPlaca->execute(['placa' => $placaERP]);
                $veiculoExistente = $stmtPlaca->fetch(\PDO::FETCH_ASSOC);
                if ($veiculoExistente) {
                    $veiculoFinalId = $veiculoExistente['id'];
                    $veiculoPlaca = $veiculoExistente['placa'];
                    $veiculoCriado = false;
                } else {
                    throw new \Exception("Veículo com placa {$placaERP} não encontrado no sistema.");
                }
            } else {
                throw new \Exception("Nenhum veículo informado para o embarque.");
            }
        }

        // ----------------------------------------------------------------
        // 3.7 CRIAR EMBARQUE ÚNICO
        // ----------------------------------------------------------------
        $numeroEmbarque = 'EMB-' . date('Ymd') . '-' . str_pad($idEmbarqueERP, 4, '0', STR_PAD_LEFT);
        $nomeCompleto = $nomeEmbarque . ' (Grupo ' . count($todosIds) . ' embarques)';
        $erpIdsString = implode(',', $todosIds);

        // 🔥 CORRIGIDO: total_entregas calculado corretamente
        $stmt = $pdo->prepare("
            INSERT INTO frota_embarque (
                numero_embarque, erp_embarque_id, veiculo_id, motorista_id,
                nome_embarque, status, data_saida, observacoes, created_at, updated_at,
                erp_ids_agrupados, total_embarques_agrupados, total_entregas
            ) VALUES (
                :numero, :erp_embarque_id, :veiculo_id, :motorista_id,
                :nome_embarque, 'planejado', :data_saida, :obs, NOW(), NOW(),
                :erp_ids, :total_embarques, :total_entregas
            ) RETURNING id
        ");

        $stmt->execute([
            'numero' => $numeroEmbarque,
            'erp_embarque_id' => $idEmbarqueERP,
            'veiculo_id' => $veiculoFinalId,
            'motorista_id' => $motoristaFinalId,
            'nome_embarque' => $nomeCompleto,
            'data_saida' => $dataSaida,
            'obs' => 'Agrupado - Embarques: ' . $erpIdsString . ' - ' . ($embarquePrincipal['rota'] ?? 'Sem rota'),
            'erp_ids' => $erpIdsString,
            'total_embarques' => count($todosIds),
            'total_entregas' => $totalEntregas  // 🔥 CORRIGIDO: agora com valor correto
        ]);

        $embarqueId = $stmt->fetchColumn();

        // ----------------------------------------------------------------
        // 3.8 CRIAR ENTREGAS
        // ----------------------------------------------------------------
        $ordem = 1;

        foreach ($pedidos as $grupo) {
            $stmt = $pdo->prepare("SELECT id FROM frota_cliente WHERE erp_id = :erp_id");
            $stmt->execute(['erp_id' => $grupo['idcliforemp']]);
            $clienteFrota = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$clienteFrota) {
                $stmt = $pdo->prepare("
                    INSERT INTO frota_cliente (
                        erp_id, nome, endereco, bairro, cidade, uf, cep, telefone, latitude, longitude, created_at, updated_at
                    ) VALUES (
                        :erp_id, :nome, :endereco, :bairro, :cidade, :uf, :cep, :telefone, :latitude, :longitude, NOW(), NOW()
                    ) RETURNING id
                ");
                $stmt->execute([
                    'erp_id' => $grupo['idcliforemp'],
                    'nome' => $grupo['cliente_nome'],
                    'endereco' => $grupo['endereco'] ?? '',
                    'bairro' => $grupo['bairro'] ?? '',
                    'cidade' => $grupo['cidade_nome'] ?? '',
                    'uf' => $grupo['uf'] ?? '',
                    'cep' => $grupo['cep'] ?? '',
                    'telefone' => $grupo['telefone'] ?? '',
                    'latitude' => $grupo['latitude'] ?? null,
                    'longitude' => $grupo['longitude'] ?? null
                ]);
                $clienteFrotaId = $stmt->fetchColumn();
            } else {
                $clienteFrotaId = $clienteFrota['id'];
            }

            // Calcular peso total
            $pesoTotal = 0;
            foreach ($grupo['pedidos'] as $pedido) {
                $stmt = $pdo->prepare("
                    SELECT pi.qt, i.pesobruto
                    FROM pedido_item pi
                    JOIN item i ON i.iditem = pi.iditem
                    WHERE pi.idpedido = :idpedido AND pi.ativo = 'S'
                ");
                $stmt->execute(['idpedido' => $pedido['idpedido']]);
                $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($itens as $item) {
                    $pesoTotal += ($item['qt'] * $item['pesobruto']);
                }
            }

            $primeiroPedidoId = $grupo['pedidos_ids'][0] ?? 0;
            $codigoRastreamento = 'TRK' . strtoupper(substr(md5(uniqid($primeiroPedidoId, true)), 0, 8));

            $stmt = $pdo->prepare("
                INSERT INTO frota_entrega (
                    embarque_id, cliente_id, pedido_id, ordem_entrega, codigo_rastreamento,
                    endereco, numero, complemento, bairro, cidade, uf, cep, telefone,
                    latitude, longitude,
                    status_geolocalizacao, origem_geolocalizacao, mensagem_geolocalizacao,
                    cliente_nome, cliente_telefone, valor, peso, volume,
                    peso_total, valor_total, status, data_prevista, created_at, updated_at,
                    pedidos_ids, total_pedidos_agrupados, erp_embarques_ids
                ) VALUES (
                    :embarque_id, :cliente_id, :pedido_id, :ordem_entrega, :codigo_rastreamento,
                    :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, :telefone,
                    :latitude, :longitude,
                    :status_geo, :origem_geo, :mensagem_geo,
                    :cliente_nome, :cliente_telefone, :valor, :peso, :volume,
                    :peso_total, :valor_total, 'pendente', :data_prevista, NOW(), NOW(),
                    :pedidos_ids, :total_pedidos, :erp_embarques_ids
                ) RETURNING id
            ");

            $stmt->execute([
                'embarque_id' => $embarqueId,
                'cliente_id' => $clienteFrotaId,
                'pedido_id' => $primeiroPedidoId,
                'ordem_entrega' => $ordem,
                'codigo_rastreamento' => $codigoRastreamento,
                'endereco' => $grupo['endereco'] ?? '',
                'numero' => $grupo['numero'] ?? '',
                'complemento' => $grupo['complemento'] ?? '',
                'bairro' => $grupo['bairro'] ?? '',
                'cidade' => $grupo['cidade_nome'] ?? '',
                'uf' => $grupo['uf'] ?? '',
                'cep' => $grupo['cep'] ?? '',
                'telefone' => $grupo['telefone'] ?? '',
                'latitude' => $grupo['latitude'] ?? null,
                'longitude' => $grupo['longitude'] ?? null,
                'status_geo' => $grupo['status_geo'] ?? 'pendente_geolocalizacao',
                'origem_geo' => $grupo['origem_geo'] ?? null,
                'mensagem_geo' => $grupo['mensagem_geo'] ?? null,
                'cliente_nome' => $grupo['cliente_nome'],
                'cliente_telefone' => $grupo['telefone'] ?? '',
                'valor' => (float)$grupo['valor_total_agrupado'],
                'peso' => $pesoTotal,
                'volume' => 0,
                'peso_total' => $pesoTotal,
                'valor_total' => (float)$grupo['valor_total_agrupado'],
                'data_prevista' => date('Y-m-d', strtotime('+1 day')),
                'pedidos_ids' => implode(',', $grupo['pedidos_ids']),
                'total_pedidos' => $grupo['total_pedidos'],
                'erp_embarques_ids' => $erpIdsString
            ]);

            $ordem++;
        }

        // ================================================================
        // 4. COMMIT E RESPOSTA
        // ================================================================
        $pdo->commit();

        error_log('[Importador] Embarque agrupado criado ID ' . $embarqueId . ' com ' . $totalEntregas . ' entregas');

        $resposta = [
            'success' => true,
            'message' => count($todosIds) . ' embarques importados e agrupados com sucesso!',
            'data' => [
                'embarque_id' => $embarqueId,
                'numero_embarque' => $numeroEmbarque,
                'total_entregas' => $totalEntregas,
                'total_embarques_agrupados' => count($todosIds),
                'erp_ids' => $todosIds,
                'clientes' => count($pedidos)
            ]
        ];

        if ($motoristaCriado) {
            $resposta['motorista_criado'] = [
                'nome' => $motoristaNome,
                'id' => $motoristaFinalId,
                'erp_id' => $motoristaId
            ];
        }

        return $this->json($response, $resposta);
        
    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Importador] Erro ao criar embarque agrupado: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    // ======================================================================
    // MÉTODOS DE IMPORTAÇÃO (mantidos)
    // ======================================================================

    /**
     * POST /v1/frota/importar/entregas
     */
    public function importarEntregas(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, [
                'success' => true,
                'message' => 'Importação de entregas iniciada',
                'data' => [
                    'importados' => 0,
                    'atualizados' => 0,
                    'erros' => 0,
                    'detalhes' => []
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/importar/veiculos
     */
    public function importarVeiculos(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, [
                'success' => true,
                'message' => 'Importação de veículos iniciada',
                'data' => [
                    'importados' => 0,
                    'atualizados' => 0,
                    'erros' => 0,
                    'detalhes' => []
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/importar/motoristas
     */
    public function importarMotoristas(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, [
                'success' => true,
                'message' => 'Importação de motoristas iniciada',
                'data' => [
                    'importados' => 0,
                    'atualizados' => 0,
                    'erros' => 0,
                    'detalhes' => []
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/importar/clientes
     */
    public function importarClientes(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, [
                'success' => true,
                'message' => 'Importação de clientes iniciada',
                'data' => [
                    'importados' => 0,
                    'atualizados' => 0,
                    'erros' => 0,
                    'detalhes' => []
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /v1/frota/importar/tudo
     */
    public function importarTudo(Request $request, Response $response): Response
    {
        try {
            return $this->json($response, [
                'success' => true,
                'message' => 'Importação completa iniciada',
                'data' => [
                    'embarques' => 0,
                    'entregas' => 0,
                    'veiculos' => 0,
                    'motoristas' => 0,
                    'clientes' => 0,
                    'erros' => []
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
   /**
 * POST /v1/frota/importar/geocodificar
 * Geocodificar clientes sem coordenadas
 */
public function geocodificar(Request $request, Response $response): Response
{
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $limite = (int)($input['limite'] ?? 100);
        $forcar = (bool)($input['forcar'] ?? false);
        
        $sql = "
            SELECT id, endereco, cidade, uf, cep, latitude, longitude 
            FROM frota_cliente 
        ";
        
        if (!$forcar) {
            $sql .= " WHERE (latitude IS NULL OR longitude IS NULL)";
        }
        
        $sql .= " LIMIT :limite";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['limite' => $limite]);
        $clientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($clientes)) {
            return $this->json($response, [
                'success' => true,
                'message' => 'Nenhum cliente sem coordenadas encontrado',
                'data' => [
                    'total_processados' => 0,
                    'atualizados' => 0,
                    'pendentes' => 0
                ]
            ]);
        }
        
        $geolocalizacaoService = new \Nutricional\Services\Frota\GeolocalizacaoService($this->pdo);
        $atualizados = 0;
        $erros = [];
        
        foreach ($clientes as $cliente) {
            $enderecoCompleto = trim(
                ($cliente['endereco'] ?? '') .
                ', ' . ($cliente['cidade'] ?? '') .
                ', ' . ($cliente['uf'] ?? '') .
                (!empty($cliente['cep']) ? ', CEP: ' . $cliente['cep'] : '')
            );
            
            if (empty($enderecoCompleto) || $enderecoCompleto === ', , ') {
                continue;
            }
            
            // NÍVEL 1: Google Maps
            $resultado = $geolocalizacaoService->buscarNoGoogleMaps($enderecoCompleto);
            
            // NÍVEL 2: Busca simplificada
            if (!$resultado['success']) {
                $resultado = $geolocalizacaoService->buscarNoGoogleMapsSimplificado($enderecoCompleto);
            }
            
            if ($resultado['success']) {
                $stmt = $this->pdo->prepare("
                    UPDATE frota_cliente 
                    SET latitude = :lat, 
                        longitude = :lng,
                        origem_coordenada = :origem,
                        data_atualizacao_coordenada = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'lat' => $resultado['latitude'],
                    'lng' => $resultado['longitude'],
                    'origem' => $resultado['origem'] ?? 'google_maps',
                    'id' => $cliente['id']
                ]);
                $atualizados++;
            } else {
                $erros[] = [
                    'id' => $cliente['id'],
                    'endereco' => $enderecoCompleto,
                    'erro' => $resultado['mensagem'] ?? 'Não foi possível geocodificar'
                ];
            }
            
            // Delay para não exceder limite da API
            usleep(200000); // 200ms
        }
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Geocodificação concluída',
            'data' => [
                'total_processados' => count($clientes),
                'atualizados' => $atualizados,
                'pendentes' => count($clientes) - $atualizados,
                'erros' => $erros
            ]
        ]);
        
    } catch (\Exception $e) {
        error_log('[Geocodificar] Erro: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * GET /v1/frota/importar/buscar-pedidos
 * Buscar pedidos do ERP por número ou cliente (para adicionar a grupos)
 */
public function buscarPedidos(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $q = trim($params['q'] ?? '');

    if (strlen($q) < 2) {
        return $this->json($response, ['success' => true, 'data' => []]);
    }

    try {
        $pdo = $this->pdo;

        $stmt = $pdo->prepare("
            SELECT 
                p.idpedido,
                p.idcliforemp,
                p.valortotalpedido,
                p.data,
                c.fantasia as cliente_nome,
                c.razao as cliente_razao,
                c.cnpj,
                c.uf,
                (SELECT DISTINCT descricao FROM cidade WHERE idcidade = c.idcidade) as cidade,
                COALESCE(
                    (SELECT SUM(pi.qt * i.pesobruto) 
                     FROM pedido_item pi
                     JOIN item i ON i.iditem = pi.iditem
                     WHERE pi.idpedido = p.idpedido AND pi.ativo = 'S'
                    ), 0
                ) as peso_total,
                (SELECT COUNT(*) FROM pedido_item WHERE idpedido = p.idpedido AND ativo = 'S') as total_itens
            FROM pedido p
            LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            WHERE p.status IN (4, 5)
              AND (
                  p.idpedido::text ILIKE :q 
                  OR c.fantasia ILIKE :q 
                  OR c.razao ILIKE :q
                  OR c.idcliforemp::text ILIKE :q
              )
            ORDER BY p.idpedido DESC
            LIMIT 10
        ");
        
        $stmt->execute(['q' => "%{$q}%"]);
        $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json($response, [
            'success' => true,
            'data' => $pedidos
        ]);

    } catch (\Exception $e) {
        error_log('[Importador] Erro ao buscar pedidos: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * POST /v1/frota/importar/itens-pedidos
 * Buscar itens de uma lista de IDs de pedidos
 */
public function getItensPedidos(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $pedidosIds = $input['pedidos_ids'] ?? [];
    
    if (empty($pedidosIds)) {
        return $this->json($response, ['success' => false, 'error' => 'Nenhum pedido ID fornecido'], 400);
    }

    // Garantir que são inteiros
    $ids = array_map('intval', $pedidosIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        SELECT 
            p.idpedido,
            p.idcliforemp,
            p.valortotalpedido,
            c.fantasia as cliente_nome,
            c.razao as cliente_razao,
            pi.iditem,
            i.referencia,
            i.descricao,
            pi.qt as quantidade,
            pi.valortotal as valor_item,
            i.pesobruto as peso_bruto,
            (select sigla from unidade where idunidade = i.idunidadebasica) unidade_medida
        FROM pedido p
        LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
        JOIN pedido_item pi ON pi.idpedido = p.idpedido
        JOIN item i ON i.iditem = pi.iditem
        WHERE p.idpedido IN ({$placeholders})
        AND pi.ativo = 'S'
        ORDER BY p.idpedido ASC, i.referencia ASC
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($ids);
    $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Agrupar por pedido e item
    $itensPorPedido = [];
    foreach ($resultados as $row) {
        $pedidoId = $row['idpedido'];
        
        if (!isset($itensPorPedido[$pedidoId])) {
            $itensPorPedido[$pedidoId] = [
                'idpedido' => $pedidoId,
                'cliente_nome' => $row['cliente_nome'] ?? $row['cliente_razao'] ?? 'Cliente',
                'itens' => []
            ];
        }
        
        $key = $row['iditem'];
        if (!isset($itensPorPedido[$pedidoId]['itens'][$key])) {
            $itensPorPedido[$pedidoId]['itens'][$key] = [
                'iditem' => $row['iditem'],
                'referencia' => $row['referencia'],
                'descricao' => $row['descricao'],
                'quantidade_total' => 0,
                'peso_bruto' => $row['peso_bruto'],
                'unidade_medida' => $row['unidade_medida'] ?? 'un',
                'valor_total_item' => 0
            ];
        }
        
        $itensPorPedido[$pedidoId]['itens'][$key]['quantidade_total'] += (float)$row['quantidade'];
        $itensPorPedido[$pedidoId]['itens'][$key]['valor_total_item'] += (float)$row['valor_item'];
    }

    // Converter para array simples
    $resultado = [];
    foreach ($itensPorPedido as $pedidoId => $dados) {
        $resultado[] = [
            'idpedido' => $dados['idpedido'],
            'cliente_nome' => $dados['cliente_nome'],
            'itens' => array_values($dados['itens'])
        ];
    }

    return $this->json($response, [
        'success' => true,
        'data' => $resultado
    ]);
}
/**
     * GET /v1/frota/importar/embarques-erp
     * Buscar embarques do ERP que já foram separados (pex_conferido = 'S')
     */
    public function getEmbarquesERP(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            // Buscar IDs já importados
            $stmtImportados = $pdo->query("
                SELECT DISTINCT erp_embarque_id 
                FROM frota_embarque 
                WHERE erp_embarque_id IS NOT NULL
                ");
            $importados = $stmtImportados->fetchAll(\PDO::FETCH_COLUMN);
            
            $excluirImportados = '';
            if (!empty($importados)) {
                $ids = implode(',', array_map('intval', $importados));
                $excluirImportados = "AND ep.idembarque NOT IN ($ids)";
            }
            
            // CONSULTA COMPLETA COM DADOS DO MOTORISTA
            $sql = "
            SELECT 
            ep.idembarque,
            ep.observacao as rota,
            ep.placa,
            ep.data,
            ep.idfilial,
            ep.pex_conferido,
            ep.gerou_nf,
            ep.identregador as idmotorista,
            COALESCE(s.status_atual, 'PENDENTE') as status_logistico,
            c.fantasia as motorista_nome,
            c.razao as motorista_razao,
            c.cpf as motorista_cpf,
            c.fone as motorista_telefone,
            c.email as motorista_email,
            c.endereco as motorista_endereco,
            (SELECT DISTINCT descricao FROM cidade WHERE idcidade = c.idcidade) as motorista_cidade,
            c.uf as motorista_uf,
            (SELECT COUNT(DISTINCT p.idpedido)
             FROM pedido p
             WHERE p.idembarque = ep.idembarque
             AND p.status IN (4, 5)) as total_pedidos,
            (SELECT COALESCE(SUM(p.valortotalpedido), 0)
             FROM pedido p
             WHERE p.idembarque = ep.idembarque
             AND p.status IN (4, 5)) as valor_total,
            (SELECT COALESCE(SUM(pi.qt * i.pesobruto), 0)
             FROM pedido p
             JOIN pedido_item pi ON pi.idpedido = p.idpedido
             JOIN item i ON i.iditem = pi.iditem
             WHERE p.idembarque = ep.idembarque
             AND p.status IN (4, 5)
             AND pi.ativo = 'S') as peso_total
            FROM embarque_pedido ep
            LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
            LEFT JOIN cliforemp c ON c.idcliforemp = ep.identregador
            WHERE ep.pex_conferido = 'S'
            AND ep.gerou_nf = 'S'
            {$excluirImportados}
            AND ep.idfilial IN (1, 6)
            ORDER BY ep.idembarque DESC
            LIMIT 20
            ";
            
            error_log('[Importador] SQL: ' . $sql);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $embarques = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($embarques)) {
                return $this->json($response, [
                    'success' => true,
                    'data' => [],
                    'total_embarques' => 0,
                    'mensagem' => 'Nenhum embarque separado aguardando rota'
                ]);
            }
            
            // Processar cada embarque
            $resultado = [];
            foreach ($embarques as $emb) {
                // Buscar clientes com endereço completo
                $stmtClientes = $pdo->prepare("
                    SELECT DISTINCT 
                    c.idcliforemp,
                    c.fantasia as nome,
                    c.razao,
                    c.endereco,
                    c.numero,
                    c.bairro,
                    cid.descricao as cidade,
                    c.uf,
                    c.cep,
                    c.fone as telefone,
                    ce.latitude,
                    ce.longitude,
                    TRIM(
                        COALESCE(c.endereco, '') || 
                        CASE WHEN c.numero IS NOT NULL AND c.numero != '' THEN ', ' || c.numero ELSE '' END ||
                        CASE WHEN c.bairro IS NOT NULL AND c.bairro != '' THEN ', ' || c.bairro ELSE '' END ||
                        ', ' || COALESCE(cid.descricao, '') ||
                        ', ' || COALESCE(c.uf, '') ||
                        CASE WHEN c.cep IS NOT NULL AND c.cep != '' THEN ', CEP: ' || c.cep ELSE '' END
                        ) as endereco_completo
                    FROM pedido p
                    LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
                    LEFT JOIN cliforemp_endereco ce ON ce.idcliforemp = c.idcliforemp
                    LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                    WHERE p.idembarque = :idembarque
                    AND p.status IN (4, 5)
                    ");
                $stmtClientes->execute(['idembarque' => $emb['idembarque']]);
                $clientes = $stmtClientes->fetchAll(\PDO::FETCH_ASSOC);
                
                // Buscar pedidos
                $stmtPedidos = $pdo->prepare("
                    SELECT 
                    p.idpedido,
                    p.idcliforemp,
                    p.data,
                    p.valortotalpedido,
                    c.fantasia as cliente_nome,
                    (SELECT COUNT(*) FROM pedido_item WHERE idpedido = p.idpedido AND ativo = 'S') as total_itens
                    FROM pedido p
                    LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
                    WHERE p.idembarque = :idembarque
                    AND p.status IN (4, 5)
                    ");
                $stmtPedidos->execute(['idembarque' => $emb['idembarque']]);
                $pedidos = $stmtPedidos->fetchAll(\PDO::FETCH_ASSOC);
                
                // Buscar itens de cada pedido
                foreach ($pedidos as &$pedido) {
                    $stmtItens = $pdo->prepare("
                        SELECT 
                        pi.iditem,
                        i.referencia,
                        i.descricao,
                        pi.qt as quantidade,
                        pi.valorunitarioimpressao as valor_unitario,
                        pi.valortotal as valor_total,
                        i.pesobruto as peso_bruto
                        FROM pedido_item pi
                        JOIN item i ON i.iditem = pi.iditem
                        WHERE pi.idpedido = :idpedido
                        AND pi.ativo = 'S'
                        ");
                    $stmtItens->execute(['idpedido' => $pedido['idpedido']]);
                    $pedido['itens'] = $stmtItens->fetchAll(\PDO::FETCH_ASSOC);
                    
                    // Calcular peso do pedido
                    $pedido['peso_total'] = 0;
                    foreach ($pedido['itens'] as $item) {
                        $pedido['peso_total'] += ($item['quantidade'] * $item['peso_bruto']);
                    }
                }
                
                $emb['clientes'] = $clientes;
                $emb['pedidos'] = $pedidos;
                $emb['total_clientes'] = count($clientes);
                $emb['total_pedidos'] = (int)$emb['total_pedidos'];
                $emb['valor_total'] = (float)$emb['valor_total'];
                $emb['peso_total'] = (float)$emb['peso_total'];
                $emb['ja_importado'] = false;
                
                $resultado[] = $emb;
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $resultado,
                'total_embarques' => count($resultado),
                'mensagem' => count($resultado) . ' embarques separados aguardando rota'
            ]);
            
        } catch (\Exception $e) {
            error_log('[Importador] Erro ao buscar embarques do ERP: ' . $e->getMessage());
            error_log('[Importador] Stack trace: ' . $e->getTraceAsString());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resposta JSON
     */
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