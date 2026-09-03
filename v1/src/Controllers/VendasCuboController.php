<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VendasCuboController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/vendas/cubo/config
     */
    public function getConfig(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $uid = $user['uid'] ?? 0;
        
        $dimensions = [
            'data_documento' => ['label' => 'Data', 'type' => 'date', 'icon' => 'fa-calendar'],
            'tipo_documento' => ['label' => 'Tipo (Pedido/NF)', 'type' => 'string', 'icon' => 'fa-file-invoice'],
            'status_descricao' => ['label' => 'Status', 'type' => 'string', 'icon' => 'fa-circle-info'],
            'cliente' => ['label' => 'Cliente', 'type' => 'string', 'icon' => 'fa-user'],
            'representante' => ['label' => 'Representante', 'type' => 'string', 'icon' => 'fa-user-tie'],
            'supervisor' => ['label' => 'Supervisor', 'type' => 'string', 'icon' => 'fa-user-check'],
            'regiao' => ['label' => 'Região', 'type' => 'string', 'icon' => 'fa-map-marker-alt'],
            'estado' => ['label' => 'UF', 'type' => 'string', 'icon' => 'fa-map'],
            'cidade' => ['label' => 'Cidade', 'type' => 'string', 'icon' => 'fa-city'],
            'grupo' => ['label' => 'Grupo', 'type' => 'string', 'icon' => 'fa-tag'],
            'subgrupo' => ['label' => 'Subgrupo', 'type' => 'string', 'icon' => 'fa-tags'],
            'tipo_produto' => ['label' => 'Tipo Produto', 'type' => 'string', 'icon' => 'fa-box'],
            'marca' => ['label' => 'Marca', 'type' => 'string', 'icon' => 'fa-trademark'],
            'produto' => ['label' => 'Produto', 'type' => 'string', 'icon' => 'fa-boxes'],
            'filial' => ['label' => 'Filial', 'type' => 'string', 'icon' => 'fa-store']
        ];
        
        $metrics = [
            'valor_total' => ['label' => 'Valor (R$)', 'format' => 'currency'],
            'quantidade_total' => ['label' => 'Quantidade', 'format' => 'number'],
            'peso_total' => ['label' => 'Peso (kg)', 'format' => 'decimal'],
            'comissao_total' => ['label' => 'Comissão (R$)', 'format' => 'currency'],
            'qtd_documentos' => ['label' => 'Nº Documentos', 'format' => 'number']
        ];
        
        // Busca permissões do usuário
        $permissoes = $this->getUserPermissions($uid);
        $filiaisPermitidas = $this->getFiliaisPermitidas($permissoes['filiais']);
        
        return $this->jsonResponse($response, [
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'permissoes' => $permissoes,
            'filiais_permitidas' => $filiaisPermitidas,
            'default_filters' => [
                'data_inicio' => date('Y-m-01'),
                'data_fim' => date('Y-m-t')
            ],
            'max_periodo_dias' => 365
        ]);
    }
    
    /**
     * POST /v1/vendas/cubo/filiais
     */
    public function getFiliais(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            $permissoes = $this->getUserPermissions($uid);
            $filiais = $this->getFiliaisPermitidas($permissoes['filiais']);
            
            return $this->jsonResponse($response, ['filiais' => $filiais]);
        } catch (\Exception $e) {
            error_log('Erro getFiliais: ' . $e->getMessage());
            return $this->jsonResponse($response, ['filiais' => []]);
        }
    }
    
   /**
 * POST /v1/vendas/cubo/supervisores-por-filial
 */
   public function getSupervisoresPorFilial(Request $request, Response $response): Response
   {
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idfilial = intval($input['idfilial'] ?? 0);
        
        if ($idfilial <= 0) {
            return $this->jsonResponse($response, ['supervisores' => []]);
        }
        
        $sql = "
        SELECT DISTINCT 
        idgestor as id,
        gestor as nome
        FROM vw_gestor_repre
        WHERE idfilial = :idfilial
        AND inativo = 'N'
        ORDER BY nome
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['idfilial' => $idfilial]);
        $supervisores = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("Supervisores filial $idfilial: " . count($supervisores));
        
        return $this->jsonResponse($response, ['supervisores' => $supervisores]);
    } catch (\Exception $e) {
        error_log('Erro getSupervisoresPorFilial: ' . $e->getMessage());
        return $this->jsonResponse($response, ['supervisores' => []]);
    }
}

/**
 * POST /v1/vendas/cubo/representantes-por-supervisor
 */
public function getRepresentantesPorSupervisor(Request $request, Response $response): Response
{
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idsupervisor = intval($input['idsupervisor'] ?? 0);
        $idfilial = intval($input['idfilial'] ?? 0);
        $user = $request->getAttribute('user');
        $uid = $user['uid'] ?? 0;
        $permissoes = $this->getUserPermissions($uid);

        error_log("=== getRepresentantesPorSupervisor ===");
        error_log("uid: $uid, idsupervisor: $idsupervisor, idfilial: $idfilial");

        // ✅ Se não tem gestor selecionado, filtra pelos gestores liberados
        $whereGestor = "";
        $params = ['idfilial' => $idfilial];
        
        if ($idsupervisor > 0) {
            $whereGestor = " AND idgestor = :idsupervisor";
            $params['idsupervisor'] = $idsupervisor;
        } elseif (!empty($permissoes['gestores']) && !$permissoes['is_admin']) {
            $inG = implode(',', array_map('intval', $permissoes['gestores']));
            $whereGestor = " AND idgestor IN ($inG)";
        }

        $sql = "
        SELECT DISTINCT 
        idrepresentante as id,
        representante as nome
        FROM vw_gestor_repre
        WHERE idfilial = :idfilial
        $whereGestor
        AND inativo = 'N'
        ORDER BY nome
        LIMIT 200
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $representantes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("Representantes encontrados: " . count($representantes));
        foreach ($representantes as $r) {
            error_log("  - {$r['id']}: {$r['nome']}");
        }

        return $this->jsonResponse($response, ['representantes' => $representantes]);
        
    } catch (\Exception $e) {
        error_log('❌ ERRO getRepresentantesPorSupervisor: ' . $e->getMessage());
        return $this->jsonResponse($response, ['representantes' => [], 'error' => $e->getMessage()]);
    }
}
    /**
     * POST /v1/vendas/cubo/clientes-por-representante
     */
    public function getClientesPorRepresentante(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $idrepresentante = intval($input['idrepresentante'] ?? 0);
            $idsupervisor = intval($input['idsupervisor'] ?? 0);
            $idfilial = intval($input['idfilial'] ?? 0);
            
            $sql = "
            SELECT DISTINCT 
            p.idcliforemp as id,
            c.fantasia as nome
            FROM pedido p
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            WHERE p.idfilial = :idfilial
            ";
            
            $params = ['idfilial' => $idfilial];
            
            if ($idrepresentante > 0) {
                $sql .= " AND p.idvendrepre = :idrepresentante";
                $params['idrepresentante'] = $idrepresentante;
            }
            if ($idsupervisor > 0) {
                $sql .= " AND p.idsupervisor = :idsupervisor";
                $params['idsupervisor'] = $idsupervisor;
            }
            
            $sql .= " UNION
            SELECT DISTINCT 
            n.idcliforemp as id,
            c.fantasia as nome
            FROM nfs n
            JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            WHERE n.idfilial = :idfilial
            ";
            
            if ($idrepresentante > 0) {
                $sql .= " AND n.idvendrepre = :idrepresentante";
            }
            if ($idsupervisor > 0) {
                $sql .= " AND n.idsupervisor = :idsupervisor";
            }
            
            $sql .= " ORDER BY nome LIMIT 200";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $clientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, ['clientes' => $clientes]);
        } catch (\Exception $e) {
            error_log('Erro getClientesPorRepresentante: ' . $e->getMessage());
            return $this->jsonResponse($response, ['clientes' => []]);
        }
    }
    
 /**
     * POST /v1/vendas/cubo/data - CORRIGIDO
     */
 public function getData(Request $request, Response $response): Response
 {
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $user = $request->getAttribute('user');
        $uid = $user['uid'] ?? 0;

        $rowDimension = $input['row_dimension'] ?? 'data_documento';
        $metrics = $input['metrics'] ?? ['valor_total'];
        $filters = $input['filters'] ?? [];
        $limit = (int)($input['limit'] ?? 500);

        $dataInicio = $filters['data_inicio'] ?? date('Y-m-01');
        $dataFim = $filters['data_fim'] ?? date('Y-m-t');

        $d1 = new \DateTime($dataInicio);
        $d2 = new \DateTime($dataFim);
        $diferenca = $d1->diff($d2)->days;
        $warning = null;

        if ($diferenca > 365) {
            $dataFim = (clone $d1)->modify('+365 days')->format('Y-m-d');
            $warning = 'Período limitado a 365 dias para manter performance';
        }

        $permissoes = $this->getUserPermissions($uid);

// força filtrar apenas pelos gestores liberados
        $whereGestorPedido = "";
        $whereGestorNF = "";
        $whereGestorTotalPedido = "";
        $whereGestorTotalNF = "";
        $whereGestorTime = "";

        if (!empty($filters['supervisor'])) {
    // Usuário selecionou um gestor específico no filtro
            $whereGestorPedido = " AND p.idsupervisor = :supervisor";
            $whereGestorNF = " AND n.idsupervisor = :supervisor";
            $whereGestorTotalPedido = " AND p.idsupervisor = :supervisor";
            $whereGestorTotalNF = " AND n.idsupervisor = :supervisor";
        } elseif (!empty($permissoes['gestores']) && !$permissoes['is_admin']) {
    // Usuário NÃO selecionou gestor, mas tem restrições → filtra pelos liberados
            $inG = implode(',', array_map('intval', $permissoes['gestores']));
            $whereGestorPedido = " AND p.idsupervisor IN ($inG)";
            $whereGestorNF = " AND n.idsupervisor IN ($inG)";
            $whereGestorTotalPedido = " AND p.idsupervisor IN ($inG)";
            $whereGestorTotalNF = " AND n.idsupervisor IN ($inG)";
        }




        $rowDimMap = [
            'data_documento' => 'data_documento', 'data_nf' => 'data_documento',
            'cliente' => 'cliente', 'representante' => 'representante',
            'vendedor' => 'representante', 'supervisor' => 'supervisor',
            'regiao' => 'regiao', 'estado' => 'estado', 'cidade' => 'cidade',
            'grupo' => 'grupo', 'subgrupo' => 'subgrupo',
            'tipo_produto' => 'tipo_produto', 'marca' => 'marca',
            'produto' => 'produto', 'filial' => 'filial',
            'tipo_documento' => 'tipo_documento', 'status_descricao' => 'status_descricao'
        ];
        $dbRowDimension = $rowDimMap[$rowDimension] ?? 'data_documento';

        $metricSelect = [];
        foreach ($metrics as $metric) {
            switch ($metric) {
                case 'valor_total': case 'valor_bruto':
                $metricSelect[] = "SUM(COALESCE(valor_total, 0)) AS valor_total";
                break;
                case 'quantidade_total': case 'quantidade':
                $metricSelect[] = "SUM(COALESCE(quantidade_total, 0)) AS quantidade_total";
                break;
                case 'peso_total': case 'peso':
                $metricSelect[] = "SUM(COALESCE(peso_total, 0)) AS peso_total";
                break;
                case 'comissao_total': case 'comissao':
                $metricSelect[] = "SUM(COALESCE(comissao_total, 0)) AS comissao_total";
                break;
                default:
                $metricSelect[] = "SUM(COALESCE(valor_total, 0)) AS valor_total";
            }
        }

        $sql = "
        SELECT 
        {$dbRowDimension} AS dimensao,
        " . implode(", ", $metricSelect) . "
        FROM (
        -- PEDIDOS
        SELECT 
        p.data as data_documento,
        c.fantasia as cliente,
        vend.fantasia as representante,
        sup.razao as supervisor,
        f.nome as filial,
        SUM(pi.qt * pi.valor) as valor_total,
        SUM(pi.qt) as quantidade_total,
        SUM(pi.qt * i.pesobruto) as peso_total,
        0 as comissao_total,
        'PEDIDO' as tipo_documento,
        CASE p.status WHEN 1 THEN 'ABERTO' WHEN 4 THEN 'FATURADO PARCIAL' ELSE 'OUTROS' END as status_descricao
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        JOIN item i ON i.iditem = pi.iditem
        JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
        JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
        LEFT JOIN cliforemp sup ON sup.idcliforemp = p.idsupervisor
        JOIN filial f ON f.idfilial = p.idfilial
        WHERE p.status IN (1,4)
        AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :data_inicio AND :data_fim
        $whereGestorPedido
        ";

        if (!empty($filters['filial'])) {
            $sql .= " AND p.idfilial = :filial";
        }
        if (!empty($filters['supervisor'])) {
            $sql .= " AND p.idsupervisor = :supervisor";
        }
        if (!empty($filters['representante'])) {
            $sql .= " AND p.idvendrepre = :representante";
        }
        if (!empty($filters['cliente'])) {
            $sql .= " AND c.fantasia = :cliente";
        }

        $sql .= "
        GROUP BY p.data, p.status, c.fantasia, vend.fantasia, sup.razao, f.nome

        UNION ALL

        -- NOTAS FISCAIS
        SELECT 
        n.dataemissao as data_documento,
        c.fantasia as cliente,
        vend.fantasia as representante,
        sup.razao as supervisor,
        f.nome as filial,
        SUM(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor_total,
        SUM(ni.qt) as quantidade_total,
        SUM(ni.pesoliquido * ni.qt) as peso_total,
        SUM(COALESCE(ni.valor_comissao,0)) as comissao_total,
        'NF' as tipo_documento,
        'FATURADO' as status_descricao
        FROM nfs n
        JOIN nfs_item ni ON ni.idnfs = n.idnfs
        JOIN item i ON i.iditem = ni.iditem
        JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
        JOIN cliforemp vend ON vend.idcliforemp = n.idvendrepre
        LEFT JOIN cliforemp sup ON sup.idcliforemp = n.idsupervisor
        JOIN filial f ON f.idfilial = n.idfilial
        WHERE n.status = 2
        AND n.idtransacao = 1
        AND n.dataemissao BETWEEN :data_inicio AND :data_fim
        $whereGestorNF
        ";

        if (!empty($filters['filial'])) {
            $sql .= " AND n.idfilial = :filial";
        }
        if (!empty($filters['supervisor'])) {
            $sql .= " AND n.idsupervisor = :supervisor";
        }
        if (!empty($filters['representante'])) {
            $sql .= " AND n.idvendrepre = :representante";
        }
        if (!empty($filters['cliente'])) {
            $sql .= " AND c.fantasia = :cliente";
        }

        $sql .= "
        GROUP BY n.dataemissao, c.fantasia, vend.fantasia, sup.razao, f.nome
        ) AS dados_unificados
        GROUP BY {$dbRowDimension}
        ORDER BY {$dbRowDimension} DESC
        LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':data_inicio', $dataInicio);
        $stmt->bindValue(':data_fim', $dataFim);

        if (!empty($filters['filial'])) {
            $stmt->bindValue(':filial', $filters['filial']);
        }
        if (!empty($filters['supervisor'])) {
            $stmt->bindValue(':supervisor', (int)$filters['supervisor'], \PDO::PARAM_INT);
        }
        if (!empty($filters['representante'])) {
            $stmt->bindValue(':representante', $filters['representante']);
        }
        if (!empty($filters['cliente'])) {
            $stmt->bindValue(':cliente', $filters['cliente']);
        }

        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Totais
        $totalSql = "
        SELECT 
        SUM(COALESCE(valor_total, 0)) as valor_total,
        SUM(COALESCE(quantidade_total, 0)) as quantidade_total,
        SUM(COALESCE(peso_total, 0)) as peso_total,
        SUM(COALESCE(comissao_total, 0)) as comissao_total
        FROM (
        SELECT 
        SUM(pi.qt * pi.valor) as valor_total,
        SUM(pi.qt) as quantidade_total,
        SUM(pi.qt * i.pesobruto) as peso_total,
        0 as comissao_total
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        JOIN item i ON i.iditem = pi.iditem
        WHERE p.status IN (1,4)
        AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :data_inicio AND :data_fim
        $whereGestorTotalPedido
        ";

        if (!empty($filters['filial'])) {
            $totalSql .= " AND p.idfilial = :filial";
        }
        if (!empty($filters['supervisor'])) {
            $totalSql .= " AND p.idsupervisor = :supervisor";
        }
        if (!empty($filters['representante'])) {
            $totalSql .= " AND p.idvendrepre = :representante";
        }

        $totalSql .= "
        UNION ALL
        SELECT 
        SUM(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor_total,
        SUM(ni.qt) as quantidade_total,
        SUM(ni.pesoliquido * ni.qt) as peso_total,
        SUM(COALESCE(ni.valor_comissao,0)) as comissao_total
        FROM nfs n
        JOIN nfs_item ni ON ni.idnfs = n.idnfs
        JOIN item i ON i.iditem = ni.iditem
        WHERE n.status = 2
        AND n.idtransacao = 1
        AND n.dataemissao BETWEEN :data_inicio AND :data_fim
        $whereGestorTotalNF
        ";

        if (!empty($filters['filial'])) {
            $totalSql .= " AND n.idfilial = :filial";
        }
        if (!empty($filters['supervisor'])) {
            $totalSql .= " AND n.idsupervisor = :supervisor";
        }
        if (!empty($filters['representante'])) {
            $totalSql .= " AND n.idvendrepre = :representante";
        }

        $totalSql .= "
        ) AS totais
        ";

        $totalStmt = $this->pdo->prepare($totalSql);
        $totalStmt->bindValue(':data_inicio', $dataInicio);
        $totalStmt->bindValue(':data_fim', $dataFim);
        if (!empty($filters['filial'])) {
            $totalStmt->bindValue(':filial', $filters['filial']);
        }
        if (!empty($filters['supervisor'])) {
            $totalStmt->bindValue(':supervisor', (int)$filters['supervisor'], \PDO::PARAM_INT);
        }
        if (!empty($filters['representante'])) {
            $totalStmt->bindValue(':representante', $filters['representante']);
        }
        $totalStmt->execute();
        $totals = $totalStmt->fetch(\PDO::FETCH_ASSOC);

            // Time series
        $timeSql = "
        WITH meses AS (
        SELECT generate_series(DATE_TRUNC('month', :data_inicio::date), DATE_TRUNC('month', :data_fim::date), '1 month'::interval) as mes
        ),
        vendas_mes AS (
        SELECT DATE_TRUNC('month', p.data) as mes, SUM(pi.qt * pi.valor) as valor
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.status IN (1,4) AND p.idtransacao IN (1,17) AND p.data BETWEEN :data_inicio AND :data_fim
        ";

        if (!empty($filters['filial'])) {
            $timeSql .= " AND p.idfilial = :filial";
        }

        $timeSql .= "
        GROUP BY DATE_TRUNC('month', p.data)
        UNION ALL
        SELECT DATE_TRUNC('month', n.dataemissao) as mes, SUM(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor
        FROM nfs n
        JOIN nfs_item ni ON ni.idnfs = n.idnfs
        WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :data_inicio AND :data_fim
        ";

        if (!empty($filters['filial'])) {
            $timeSql .= " AND n.idfilial = :filial";
        }

        $timeSql .= "
        GROUP BY DATE_TRUNC('month', n.dataemissao)
        )
        SELECT TO_CHAR(meses.mes, 'YYYY-MM') as periodo, COALESCE(SUM(vendas_mes.valor), 0) as valor
        FROM meses LEFT JOIN vendas_mes ON vendas_mes.mes = meses.mes
        GROUP BY meses.mes ORDER BY meses.mes
        ";

        $timeStmt = $this->pdo->prepare($timeSql);
        $timeStmt->bindValue(':data_inicio', $dataInicio);
        $timeStmt->bindValue(':data_fim', $dataFim);
        if (!empty($filters['filial'])) {
            $timeStmt->bindValue(':filial', $filters['filial']);
        }
        $timeStmt->execute();
        $timeData = $timeStmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalsFormatted = [
            'valor_bruto' => floatval($totals['valor_total'] ?? 0),
            'quantidade' => floatval($totals['quantidade_total'] ?? 0),
            'peso' => floatval($totals['peso_total'] ?? 0),
            'comissao' => floatval($totals['comissao_total'] ?? 0)
        ];

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $data,
            'totals' => $totalsFormatted,
            'time_series' => ['labels' => array_column($timeData, 'periodo'), 'values' => array_column($timeData, 'valor')],
            'row_dimension_label' => $rowDimension,
            'warning' => $warning
        ]);
    } catch (\Exception $e) {
        error_log('Erro getData: ' . $e->getMessage());
        return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

 /**
 * POST /v1/vendas/cubo/ranking - CORRIGIDO COM HIERARQUIA
 */
 public function getRanking(Request $request, Response $response): Response
 {
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $dimension = $input['dimension'] ?? 'cliente';
        $limit = (int)($input['limit'] ?? 10);
        $filters = $input['filters'] ?? [];
        
        // ✅ Obtém permissões do usuário
        $user = $request->getAttribute('user');
        $uid = $user['uid'] ?? 0;
        $permissoes = $this->getUserPermissions($uid);

        $dataInicio = $filters['data_inicio'] ?? date('Y-m-01');
        $dataFim = $filters['data_fim'] ?? date('Y-m-t');

        $dimMap = [
            'cliente' => 'cliente', 
            'representante' => 'representante',
            'vendedor' => 'representante', 
            'supervisor' => 'supervisor',
            'produto' => 'produto', 
            'marca' => 'marca', 
            'regiao' => 'regiao'
        ];
        $dbDimension = $dimMap[$dimension] ?? 'cliente';

        // ✅ Monta filtro de gestores (hierarquia)
        $whereGestorPedido = "";
        $whereGestorNF = "";
        
        if (!empty($filters['supervisor'])) {
            // Usuário selecionou um gestor específico
            $whereGestorPedido = " AND p.idsupervisor = :supervisor";
            $whereGestorNF = " AND n.idsupervisor = :supervisor";
        } elseif (!empty($permissoes['gestores']) && !$permissoes['is_admin']) {
            // Usuário NÃO selecionou gestor, mas tem restrições → filtra pelos liberados
            $inG = implode(',', array_map('intval', $permissoes['gestores']));
            $whereGestorPedido = " AND p.idsupervisor IN ($inG)";
            $whereGestorNF = " AND n.idsupervisor IN ($inG)";
        }
        // Se for admin sem restrições, where fica vazio (vê tudo)

        $sql = "
        SELECT {$dbDimension} as nome, SUM(valor_total) as valor
        FROM (
                -- PEDIDOS
                SELECT 
                c.fantasia as cliente,
                vend.fantasia as representante,
                sup.razao as supervisor,
                i.descricao as produto,
                m.descricao as marca,
                rg.descricao as regiao,
                SUM(pi.qt * pi.valor) as valor_total
                FROM pedido p
                JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
                JOIN item i ON i.iditem = pi.iditem
                JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
                JOIN cliente cli ON cli.idcliforemp = p.idcliforemp
                JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
                LEFT JOIN cliforemp sup ON sup.idcliforemp = p.idsupervisor
                LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
                LEFT JOIN marca m ON m.idmarca = i.idmarca
                WHERE p.status IN (1,4) 
                AND p.idtransacao IN (1,17) 
                AND p.data BETWEEN :data_inicio AND :data_fim
                $whereGestorPedido
                ";

        // Filtro de filial
                if (!empty($filters['filial'])) {
                    $sql .= " AND p.idfilial = :filial";
                }
                
        // Filtro de representante
                if (!empty($filters['representante'])) {
                    $sql .= " AND p.idvendrepre = :representante";
                }

                $sql .= "
                GROUP BY c.fantasia, vend.fantasia, sup.razao, i.descricao, m.descricao, rg.descricao

                UNION ALL

                -- NOTAS FISCAIS
                SELECT 
                c.fantasia,
                vend.fantasia,
                sup.razao,
                i.descricao,
                m.descricao,
                rg.descricao,
                SUM(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor_total
                FROM nfs n
                JOIN nfs_item ni ON ni.idnfs = n.idnfs
                JOIN item i ON i.iditem = ni.iditem
                JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                JOIN cliente cli ON cli.idcliforemp = n.idcliforemp
                JOIN cliforemp vend ON vend.idcliforemp = n.idvendrepre
                LEFT JOIN cliforemp sup ON sup.idcliforemp = n.idsupervisor
                LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
                LEFT JOIN marca m ON m.idmarca = i.idmarca
                WHERE n.status = 2 
                AND n.idtransacao = 1 
                AND n.dataemissao BETWEEN :data_inicio AND :data_fim
                $whereGestorNF
                ";

        // Filtro de filial para NFs
                if (!empty($filters['filial'])) {
                    $sql .= " AND n.idfilial = :filial";
                }
                
        // Filtro de representante para NFs
                if (!empty($filters['representante'])) {
                    $sql .= " AND n.idvendrepre = :representante";
                }

                $sql .= "
                GROUP BY c.fantasia, vend.fantasia, sup.razao, i.descricao, m.descricao, rg.descricao
                ) AS dados
                WHERE {$dbDimension} IS NOT NULL AND {$dbDimension} != ''
                GROUP BY {$dbDimension}
                ORDER BY valor DESC 
                LIMIT {$limit}
                ";

                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':data_inicio', $dataInicio);
                $stmt->bindValue(':data_fim', $dataFim);
                
                if (!empty($filters['filial'])) {
                    $stmt->bindValue(':filial', $filters['filial']);
                }
                if (!empty($filters['supervisor'])) {
                    $stmt->bindValue(':supervisor', (int)$filters['supervisor'], \PDO::PARAM_INT);
                }
                if (!empty($filters['representante'])) {
                    $stmt->bindValue(':representante', $filters['representante']);
                }
                
                $stmt->execute();

                $ranking = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
        // Calcula percentual de cada item
                $total = array_sum(array_column($ranking, 'valor'));
                foreach ($ranking as &$item) {
                    $item['percentual'] = $total > 0 ? round(($item['valor'] / $total) * 100, 2) : 0;
                }

                return $this->jsonResponse($response, [
                    'success' => true, 
                    'ranking' => $ranking, 
                    'total_geral' => $total
                ]);
                
            } catch (\Exception $e) {
                error_log('Erro ranking: ' . $e->getMessage());
                return $this->jsonResponse($response, [
                    'success' => false, 
                    'error' => $e->getMessage()
                ], 500);
            }
        }


    /**
     * POST /v1/vendas/cubo/exportar
     */
    public function exportar(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $rowDimension = $input['row_dimension'] ?? 'data_documento';
            $metrics = $input['metrics'] ?? ['valor_total'];
            $filters = $input['filters'] ?? [];
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            
            $dataInicio = $filters['data_inicio'] ?? date('Y-m-01');
            $dataFim = $filters['data_fim'] ?? date('Y-m-t');
            $permissoes = $this->getUserPermissions($uid);
            
            $rowDimMap = [
                'data_documento' => 'data_documento',
                'data_nf' => 'data_documento',
                'cliente' => 'cliente',
                'representante' => 'representante',
                'filial' => 'filial'
            ];
            
            $dbRowDimension = $rowDimMap[$rowDimension] ?? 'data_documento';
            
            $metricSelect = [];
            foreach ($metrics as $metric) {
                if ($metric === 'valor_total' || $metric === 'valor_bruto') {
                    $metricSelect[] = "SUM(COALESCE(valor_total, 0)) AS valor_total";
                } elseif ($metric === 'quantidade_total' || $metric === 'quantidade') {
                    $metricSelect[] = "SUM(COALESCE(quantidade_total, 0)) AS quantidade_total";
                } elseif ($metric === 'peso_total' || $metric === 'peso') {
                    $metricSelect[] = "SUM(COALESCE(peso_total, 0)) AS peso_total";
                } else {
                    $metricSelect[] = "SUM(COALESCE(valor_total, 0)) AS valor_total";
                }
            }
            
            $sql = "
            SELECT 
            {$dbRowDimension} AS dimensao,
            " . implode(", ", $metricSelect) . "
            FROM (
            SELECT 
            p.data as data_documento,
            c.fantasia as cliente,
            vend.fantasia as representante,
            f.nome as filial,
            SUM(pi.qt * pi.valor) as valor_total,
            SUM(pi.qt) as quantidade_total,
            SUM(pi.qt * i.pesobruto) as peso_total
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            JOIN item i ON i.iditem = pi.iditem
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
            JOIN filial f ON f.idfilial = p.idfilial
            WHERE p.status IN (1,4)
            AND p.idtransacao IN (1,17)
            AND p.data BETWEEN :data_inicio AND :data_fim
            ";
            
            if (!empty($permissoes['filiais'])) {
                $in = implode(',', array_map('intval', $permissoes['filiais']));
                $sql .= " AND p.idfilial IN ({$in})";
            }
            
            $sql .= "
            GROUP BY p.data, c.fantasia, vend.fantasia, f.nome

            UNION ALL

            SELECT 
            n.dataemissao as data_documento,
            c.fantasia,
            vend.fantasia,
            f.nome,
            SUM(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor_total,
            SUM(ni.qt) as quantidade_total,
            SUM(ni.pesoliquido * ni.qt) as peso_total
            FROM nfs n
            JOIN nfs_item ni ON ni.idnfs = n.idnfs
            JOIN item i ON i.iditem = ni.iditem
            JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            JOIN cliforemp vend ON vend.idcliforemp = n.idvendrepre
            JOIN filial f ON f.idfilial = n.idfilial
            WHERE n.status = 2
            AND n.idtransacao = 1
            AND n.dataemissao BETWEEN :data_inicio AND :data_fim
            ";
            
            if (!empty($permissoes['filiais'])) {
                $in = implode(',', array_map('intval', $permissoes['filiais']));
                $sql .= " AND n.idfilial IN ({$in})";
            }
            
            $sql .= "
            GROUP BY n.dataemissao, c.fantasia, vend.fantasia, f.nome
            ) AS dados
            GROUP BY {$dbRowDimension}
            ORDER BY {$dbRowDimension} DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':data_inicio', $dataInicio);
            $stmt->bindValue(':data_fim', $dataFim);
            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $filename = "cubo_vendas_" . date('Ymd_His') . ".csv";
            $output = fopen('php://temp', 'w');
            
            $headers = ['Dimensao'];
            foreach ($metrics as $metric) {
                $headers[] = $metric;
            }
            fputcsv($output, $headers, ';');
            
            foreach ($data as $row) {
                $line = [$row['dimensao']];
                foreach ($metrics as $metric) {
                    $line[] = $row[$metric] ?? 0;
                }
                fputcsv($output, $line, ';');
            }
            
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);
            
            $response->getBody()->write($csvContent);
            return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /v1/vendas/cubo/filtros-contextuais
     */
    public function getFiltrosContextuais(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $idfilial = intval($input['idfilial'] ?? 0);
            $idsupervisor = intval($input['idsupervisor'] ?? 0);
            $idrepresentante = intval($input['idrepresentante'] ?? 0);
            
            $sql = "
            SELECT DISTINCT 
            c.fantasia as cliente,
            vend.fantasia as representante,
            sup.razao as supervisor,
            rg.descricao as regiao,
            cid.uf as estado,
            cid.descricao as cidade,
            g.descricao as grupo,
            sg.descricao as subgrupo,
            i.descricao as produto,
            m.descricao as marca
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            JOIN item i ON i.iditem = pi.iditem
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre and vend.inativo = 'N'
            LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
            LEFT JOIN cliforemp sup ON sup.idcliforemp = p.idsupervisor
            LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            LEFT JOIN grupo sg ON sg.idgrupo = g.idgrupopai
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            WHERE 1=1
            ";
            
            $params = [];
            
            if ($idfilial > 0) {
                $sql .= " AND p.idfilial = :idfilial";
                $params[':idfilial'] = $idfilial;
            }
            if ($idsupervisor > 0) {
                $sql .= " AND p.idsupervisor = :idsupervisor";
                $params[':idsupervisor'] = $idsupervisor;
            }
            if ($idrepresentante > 0) {
                $sql .= " AND p.idvendrepre = :idrepresentante";
                $params[':idrepresentante'] = $idrepresentante;
            }
            
            $sql .= " LIMIT 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $options = [
                'cliente' => [],
                'representante' => [],
                'supervisor' => [],
                'regiao' => [],
                'estado' => [],
                'cidade' => [],
                'grupo' => [],
                'subgrupo' => [],
                'produto' => [],
                'marca' => []
            ];
            
            foreach ($dados as $row) {
                foreach ($options as $key => $value) {
                    if (!empty($row[$key]) && !in_array($row[$key], $options[$key])) {
                        $options[$key][] = $row[$key];
                    }
                }
            }
            
            $formatted = [];
            foreach ($options as $key => $values) {
                $formatted[$key] = array_map(function($v) use ($key) {
                    return ['value' => $v, 'label' => $v];
                }, array_values($values));
            }
            
            return $this->jsonResponse($response, ['success' => true, 'options' => $formatted]);
        } catch (\Exception $e) {
            error_log('Erro getFiltrosContextuais: ' . $e->getMessage());
            return $this->jsonResponse($response, ['success' => false, 'options' => []]);
        }
    }
    
    /**
     * POST /v1/vendas/cubo/detalhes - CORRIGIDO
     */
    public function getDetalhes(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $dimensao = $input['dimensao'] ?? 'data_documento';
            $valor = $input['valor'] ?? '';
            $filters = $input['filters'] ?? [];
            
            $dataInicio = $filters['data_inicio'] ?? date('Y-m-01');
            $dataFim = $filters['data_fim'] ?? date('Y-m-t');
            
            $sql = "
            SELECT 
            data_documento as data_nf,
            numero_documento as numero_nf,
            cliente,
            produto,
            valor_total as valor_bruto,
            quantidade_total as quantidade,
            peso_total as peso,
            comissao_total as comissao,
            tipo_documento
            FROM (
            SELECT 
            p.data as data_documento,
            p.idpedido as numero_documento,
            c.fantasia as cliente,
            i.descricao as produto,
            SUM(pi.qt * pi.valor) as valor_total,
            SUM(pi.qt) as quantidade_total,
            SUM(pi.qt * i.pesobruto) as peso_total,
            0 as comissao_total,
            'PEDIDO' as tipo_documento
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            JOIN item i ON i.iditem = pi.iditem
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            WHERE p.data BETWEEN :data_inicio AND :data_fim
            ";
            
            if ($dimensao === 'data_documento' || $dimensao === 'data_nf') {
                $sql .= " AND p.data = :valor";
            } elseif ($dimensao === 'cliente') {
                $sql .= " AND c.fantasia = :valor";
            } elseif ($dimensao === 'representante' || $dimensao === 'vendedor') {
                $sql .= " AND p.idvendrepre = :valor";
            } elseif ($dimensao === 'supervisor') {
                $sql .= " AND p.idsupervisor = :valor";
            } elseif ($dimensao === 'produto') {
                $sql .= " AND i.descricao = :valor";
            }
            
            // Filtros adicionais
            if (!empty($filters['filial'])) {
                $sql .= " AND p.idfilial = :filial";
            }
            if (!empty($filters['supervisor'])) {
                $sql .= " AND p.idsupervisor = :supervisor";
            }
            if (!empty($filters['representante'])) {
                $sql .= " AND p.idvendrepre = :representante";
            }
            
            $sql .= "
            GROUP BY p.data, p.idpedido, c.fantasia, i.descricao

            UNION ALL

            SELECT 
            n.dataemissao as data_documento,
            n.idnfs as numero_documento,
            c.fantasia as cliente,
            i.descricao as produto,
            SUM(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor_total,
            SUM(ni.qt) as quantidade_total,
            SUM(ni.pesoliquido * ni.qt) as peso_total,
            SUM(COALESCE(ni.valor_comissao,0)) as comissao_total,
            'NF' as tipo_documento
            FROM nfs n
            JOIN nfs_item ni ON ni.idnfs = n.idnfs
            JOIN item i ON i.iditem = ni.iditem
            JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            WHERE n.dataemissao BETWEEN :data_inicio AND :data_fim
            ";
            
            if ($dimensao === 'data_documento' || $dimensao === 'data_nf') {
                $sql .= " AND n.dataemissao = :valor";
            } elseif ($dimensao === 'cliente') {
                $sql .= " AND c.fantasia = :valor";
            } elseif ($dimensao === 'representante' || $dimensao === 'vendedor') {
                $sql .= " AND n.idvendrepre = :valor";
            } elseif ($dimensao === 'supervisor') {
                $sql .= " AND n.idsupervisor = :valor";
            } elseif ($dimensao === 'produto') {
                $sql .= " AND i.descricao = :valor";
            }
            
            if (!empty($filters['filial'])) {
                $sql .= " AND n.idfilial = :filial";
            }
            if (!empty($filters['supervisor'])) {
                $sql .= " AND n.idsupervisor = :supervisor";
            }
            if (!empty($filters['representante'])) {
                $sql .= " AND n.idvendrepre = :representante";
            }
            
            $sql .= "
            GROUP BY n.dataemissao, n.idnfs, c.fantasia, i.descricao
            ) AS detalhes
            ORDER BY data_documento DESC
            LIMIT 500
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':valor', $valor);
            $stmt->bindValue(':data_inicio', $dataInicio);
            $stmt->bindValue(':data_fim', $dataFim);
            
            if (!empty($filters['filial'])) {
                $stmt->bindValue(':filial', $filters['filial']);
            }
            if (!empty($filters['supervisor'])) {
                $stmt->bindValue(':supervisor', (int)$filters['supervisor'], \PDO::PARAM_INT);
            }
            if (!empty($filters['representante'])) {
                $stmt->bindValue(':representante', $filters['representante']);
            }
            
            $stmt->execute();
            $detalhes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $totais = ['valor_bruto' => 0, 'quantidade' => 0, 'peso' => 0, 'comissao' => 0];
            foreach ($detalhes as $item) {
                $totais['valor_bruto'] += floatval($item['valor_bruto'] ?? 0);
                $totais['quantidade'] += floatval($item['quantidade'] ?? 0);
                $totais['peso'] += floatval($item['peso'] ?? 0);
                $totais['comissao'] += floatval($item['comissao'] ?? 0);
            }
            
            return $this->jsonResponse($response, ['success' => true, 'detalhes' => $detalhes, 'totais' => $totais]);
        } catch (\Exception $e) {
            error_log('Erro getDetalhes: ' . $e->getMessage());
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    /**
 * POST /v1/vendas/cubo/itens-documento
 * Retorna os itens de um pedido ou NF específico
 */
    public function getItensDocumento(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $tipo = $input['tipo'] ?? 'PEDIDO'; // PEDIDO ou NF
        $numeroDocumento = intval($input['numero_documento'] ?? 0);
        
        if ($tipo === 'PEDIDO') {
            $sql = "
            SELECT 
            i.descricao as produto,
            pi.qt as quantidade,
            pi.valor as valor_unitario,
            (pi.qt * pi.valor) as valor_total,
            (pi.qt * i.pesobruto) as peso,
            g.descricao as grupo
            FROM pedido_item pi
            JOIN item i ON i.iditem = pi.iditem
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            WHERE pi.idpedido = :numero
            AND pi.ativo = 'S'
            ORDER BY i.descricao
            ";
        } else {
            $sql = "
            SELECT 
            i.descricao as produto,
            ni.qt as quantidade,
            ni.valor_unitario,
            (ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) as valor_total,
            (ni.pesoliquido * ni.qt) as peso,
            g.descricao as grupo,
            COALESCE(ni.valor_comissao,0) as comissao
            FROM nfs_item ni
            JOIN item i ON i.iditem = ni.iditem
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            WHERE ni.idnfs = :numero
            ORDER BY i.descricao
            ";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['numero' => $numeroDocumento]);
        $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $totais = [
            'valor_bruto' => 0,
            'quantidade' => 0,
            'peso' => 0
        ];
        
        foreach ($itens as $item) {
            $totais['valor_bruto'] += floatval($item['valor_total'] ?? 0);
            $totais['quantidade'] += floatval($item['quantidade'] ?? 0);
            $totais['peso'] += floatval($item['peso'] ?? 0);
        }
        
        return $this->jsonResponse($response, [
            'success' => true,
            'itens' => $itens,
            'totais' => $totais
        ]);
    } catch (\Exception $e) {
        error_log('Erro getItensDocumento: ' . $e->getMessage());
        return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

    /**
     * GET /v1/vendas/cubo/filters/{field}
     */
    public function getFilterOptions(Request $request, Response $response, array $args): Response
    {
        try {
            $field = $args['field'];
            
            $allowedFields = ['cliente', 'representante', 'supervisor', 'regiao', 'estado', 'cidade', 'grupo', 'subgrupo', 'tipo_produto', 'marca', 'produto', 'filial'];
            if (!in_array($field, $allowedFields)) {
                return $this->jsonResponse($response, ['options' => []]);
            }
            
            // Mapeia o campo para a tabela correta
            $fieldMap = [
                'cliente' => ['table' => 'c', 'field' => 'fantasia', 'join' => 'JOIN cliforemp c ON c.idcliforemp = p.idcliforemp'],
                'representante' => ['table' => 'vend', 'field' => 'fantasia', 'join' => 'JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre'],
                'supervisor' => ['table' => 'sup', 'field' => 'razao', 'join' => 'LEFT JOIN cliforemp sup ON sup.idcliforemp = p.idsupervisor'],
                'filial' => ['table' => 'f', 'field' => 'nome', 'join' => 'JOIN filial f ON f.idfilial = p.idfilial']
            ];
            
            $map = $fieldMap[$field] ?? ['table' => 'p', 'field' => $field, 'join' => ''];
            
            $sql = "
            SELECT DISTINCT {$map['table']}.{$map['field']} as value, {$map['table']}.{$map['field']} as label
            FROM pedido p
            {$map['join']}
            WHERE {$map['table']}.{$map['field']} IS NOT NULL AND {$map['table']}.{$map['field']} != ''
            ORDER BY {$map['table']}.{$map['field']}
            LIMIT 200
            ";
            
            $stmt = $this->pdo->query($sql);
            $options = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, ['options' => $options]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['options' => []]);
        }
    }

 /**
 * POST /v1/vendas/cubo/gestores
 * Retorna apenas os gestores liberados para o usuário
 */
 public function getGestores(Request $request, Response $response): Response
 {
    try {
        $user = $request->getAttribute('user');
        $uid = $user['uid'] ?? 0;
        $permissoes = $this->getUserPermissions($uid);

        // Se for admin sem restrições, retorna todos
        if ($permissoes['is_admin'] || empty($permissoes['gestores'])) {
            $sql = "SELECT DISTINCT 
            idgestor as id, 
            gestor as nome
            FROM vw_gestor_repre
            WHERE inativo = 'N'
            ORDER BY nome";
            $stmt = $this->pdo->query($sql);
        } else {
            // Filtra apenas os gestores liberados
            $inG = implode(',', array_map('intval', $permissoes['gestores']));
            $sql = "SELECT DISTINCT 
            idgestor as id, 
            gestor as nome
            FROM vw_gestor_repre
            WHERE idgestor IN ($inG)
            AND inativo = 'N'
            ORDER BY nome";
            $stmt = $this->pdo->query($sql);
        }

        $gestores = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("Gestores encontrados: " . count($gestores));

        return $this->jsonResponse($response, ['gestores' => $gestores]);
    } catch (\Exception $e) {
        error_log('Erro getGestores: ' . $e->getMessage());
        return $this->jsonResponse($response, ['gestores' => []]);
    }
}

    // ======================================================================
    // MÉTODOS PRIVADOS
    // ======================================================================

private function getUserPermissions(int $uid): array
{
    $stmt = $this->pdo->prepare("SELECT dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
    $stmt->execute(['uid' => $uid]);
    $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

    $filiais = !empty($userData['dash_filiais']) ? explode(',', $userData['dash_filiais']) : [];
    $gestores = !empty($userData['dash_gestores']) ? explode(',', $userData['dash_gestores']) : [];

    // Se for admin (uid=4), libera tudo
    if ($uid === 4) {
        $filiais = []; // vazio = todas
        $gestores = []; // vazio = todos
    }
    
    return [
        'filiais' => $filiais,
        'gestores' => $gestores,
        'is_admin' => ($uid === 4)
    ];
}

private function getFiliaisPermitidas(array $filiaisPermitidas): array
{
    // Se for admin (array vazio), retorna todas as filiais
    if (empty($filiaisPermitidas)) {
        $stmt = $this->pdo->query("SELECT idfilial, nome FROM filial WHERE inativo = 'N' ORDER BY idfilial");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Filtra apenas as filiais permitidas
    $in = implode(',', array_map('intval', $filiaisPermitidas));
    $stmt = $this->pdo->query("SELECT idfilial, nome FROM filial WHERE idfilial IN ({$in}) AND inativo = 'N' ORDER BY idfilial");
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
private function jsonResponse($response, array $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}
}
