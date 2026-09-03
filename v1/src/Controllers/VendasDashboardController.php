<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VendasDashboardController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    private $cache = [];

    public function getKpis(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            $dataInicioAnterior = (new \DateTime($dataInicio))->modify('-1 month')->format('Y-m-d');
            $dataFimAnterior = (new \DateTime($dataFim))->modify('-1 month')->format('Y-m-d');
            
            $filtroFilial = $input['filial'] ?? '';
            $filtroGestor = $input['gestor'] ?? '';
            $filtroRepresentante = $input['representante'] ?? '';
            
            $permissoes = $this->getUserPermissions($uid);
            
        // ✅ HIERARQUIA: Se tem restrições E NÃO selecionou, força filtrar pelos liberados
            $filialEfetiva = $filtroFilial;
            $gestorEfetivo = $filtroGestor;
            
        // Se usuário tem dash_filiais restritas E não selecionou filial → null (filtrar nas queries)
            if (empty($filtroFilial) && !empty($permissoes['filiais']) && $uid !== 4) {
                $filialEfetiva = null;
            }
            
        // Se usuário tem dash_gestores restritos E não selecionou gestor → null (filtrar nas queries)
            if (empty($filtroGestor) && !empty($permissoes['gestores']) && $uid !== 4) {
                $gestorEfetivo = null;
            }
            
        // KPIs essenciais (rápidos)
            $ticketMedio = $this->getTicketMedio($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $ticketMedioAnterior = $this->getTicketMedio($permissoes, $dataInicioAnterior, $dataFimAnterior, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $topProdutos = $this->getTopProdutos($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $topClientes = $this->getTopClientes($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $distribuicaoRegiao = $this->getDistribuicaoRegiao($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $evolucaoMensal = $this->getEvolucaoMensal($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $velocidadeVenda = $this->getVelocidadeVenda($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'ticket_medio' => [
                    'atual' => $ticketMedio,
                    'anterior' => $ticketMedioAnterior,
                    'variacao' => $ticketMedioAnterior > 0 ? round(($ticketMedio - $ticketMedioAnterior) / $ticketMedioAnterior * 100, 2) : 0
                ],
                'top_produtos' => $topProdutos,
                'top_clientes' => $topClientes,
                'produto_por_cliente' => [],
                'distribuicao_regiao' => $distribuicaoRegiao,
                'margem_produtos' => [],
                'velocidade_venda' => $velocidadeVenda ?: ['media_diaria' => 0, 'ticket_medio_diario' => 0],
                'evolucao_mensal' => $evolucaoMensal,
                'matriz_cross_selling' => [],
                'projecao_vendas' => [],
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getKpisDetalhes(Request $request, Response $response): Response
    {
        try {
            set_time_limit(30);
            
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            
            $filtroFilial = $input['filial'] ?? '';
            $filtroGestor = $input['gestor'] ?? '';
            $filtroRepresentante = $input['representante'] ?? '';
            
        // Limita a 90 dias máximo para performance
            $diffDias = (new \DateTime($dataInicio))->diff(new \DateTime($dataFim))->days;
            if ($diffDias > 90) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'margem_produtos' => [],
                    'matriz_cross_selling' => [],
                    'projecao_vendas' => [],
                    'mensagem' => 'Período máximo de 90 dias para dados detalhados.'
                ]);
            }
            
            $permissoes = $this->getUserPermissions($uid);
            $filialEfetiva = !empty($permissoes['filiais']) ? null : $filtroFilial;
            $gestorEfetivo = !empty($permissoes['gestores']) ? null : $filtroGestor;
            
        // Executa as queries (já existentes)
            $margemProdutos = $this->getMargemProdutos($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $matrizCrossSelling = $this->getMatrizCrossSelling($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            $projecaoVendas = $this->getProjecaoVendas($permissoes, $dataInicio, $dataFim, $filialEfetiva, $gestorEfetivo, $filtroRepresentante);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'margem_produtos' => $margemProdutos,
                'matriz_cross_selling' => $matrizCrossSelling,
                'projecao_vendas' => $projecaoVendas
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * POST /v1/vendas/dashboard/produto-detalhes
     */
    public function getProdutoDetalhes(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $idproduto = $input['idproduto'] ?? 0;
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            $permissoes = $this->getUserPermissions($uid);
            
            $sql = "
            SELECT 
            i.descricao as produto,
            i.referencia,
            COALESCE(g.descricao, 'SEM GRUPO') as grupo,
            COALESCE(m.descricao, 'SEM MARCA') as marca,
            SUM(pi.qt) as quantidade,
            SUM(pi.qt * pi.valor) as valor,
            AVG(pi.valor) as preco_medio,
            COUNT(DISTINCT c.idcliforemp) as total_clientes,
            SUM(pi.qt * i.pesobruto) as peso,
            SUM(COALESCE(ni.valor_comissao,0)) as comissao
            FROM item i
            JOIN pedido_item pi ON pi.iditem = i.iditem
            JOIN pedido p ON p.idpedido = pi.idpedido
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            LEFT JOIN nfs_item ni ON ni.iditem = i.iditem AND ni.idnfs IN (SELECT idnfs FROM nfs WHERE dataemissao BETWEEN :data_inicio AND :data_fim)
            WHERE p.status IN (1,4)
            AND p.data BETWEEN :data_inicio AND :data_fim
            AND i.iditem = :idproduto
            GROUP BY i.descricao, i.referencia, g.descricao, m.descricao
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'idproduto' => $idproduto,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Top 10 clientes deste produto
            $sqlClientes = "
            SELECT 
            c.fantasia as cliente,
            SUM(pi.qt) as quantidade,
            SUM(pi.qt * pi.valor) as valor
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            WHERE p.status IN (1,4)
            AND p.data BETWEEN :data_inicio AND :data_fim
            AND pi.iditem = :idproduto
            GROUP BY c.fantasia
            ORDER BY valor DESC
            LIMIT 10
            ";
            
            $stmtClientes = $this->pdo->prepare($sqlClientes);
            $stmtClientes->execute([
                'idproduto' => $idproduto,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            $clientes = $stmtClientes->fetchAll(\PDO::FETCH_ASSOC);
            
            // Evolução mensal do produto
            $sqlEvolucao = "
            SELECT 
            TO_CHAR(p.data, 'YYYY-MM') as periodo,
            SUM(pi.qt) as quantidade,
            SUM(pi.qt * pi.valor) as valor
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            WHERE p.status IN (1,4)
            AND p.data >= :data_inicio
            AND pi.iditem = :idproduto
            GROUP BY TO_CHAR(p.data, 'YYYY-MM')
            ORDER BY periodo
            ";
            
            $stmtEvolucao = $this->pdo->prepare($sqlEvolucao);
            $stmtEvolucao->execute([
                'idproduto' => $idproduto,
                'data_inicio' => date('Y-m-01', strtotime('-6 months'))
            ]);
            $evolucao = $stmtEvolucao->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'produto' => $produto,
                'clientes' => $clientes,
                'evolucao' => $evolucao
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /v1/vendas/dashboard/cliente-detalhes
     */
    public function getClienteDetalhes(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $idcliente = $input['idcliente'] ?? 0;
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            $permissoes = $this->getUserPermissions($uid);
            
            // Resumo do cliente
            $sqlResumo = "
            SELECT 
            c.fantasia as cliente,
            c.cnpj,
            cid.descricao as cidade,
            cid.uf as estado,
            COALESCE(rg.descricao, 'SEM REGIAO') as regiao,
            SUM(pi.qt) as quantidade,
            SUM(pi.qt * pi.valor) as valor,
            COUNT(DISTINCT p.idpedido) as total_pedidos,
            AVG(pi.qt * pi.valor) as ticket_medio,
            SUM(pi.qt * i.pesobruto) as peso
            FROM cliforemp c
            LEFT JOIN pedido p ON p.idcliforemp = c.idcliforemp
            LEFT JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            LEFT JOIN item i ON i.iditem = pi.iditem
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
            LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
            WHERE p.status IN (1,4)
            AND p.data BETWEEN :data_inicio AND :data_fim
            AND c.idcliforemp = :idcliente
            GROUP BY c.fantasia, c.cnpj, cid.descricao, cid.uf, rg.descricao
            ";
            
            $stmtResumo = $this->pdo->prepare($sqlResumo);
            $stmtResumo->execute([
                'idcliente' => $idcliente,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            $cliente = $stmtResumo->fetch(\PDO::FETCH_ASSOC);
            
            // Top produtos comprados
            $sqlProdutos = "
            SELECT 
            i.descricao as produto,
            i.referencia,
            SUM(pi.qt) as quantidade,
            SUM(pi.qt * pi.valor) as valor,
            AVG(pi.valor) as preco_medio
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            JOIN item i ON i.iditem = pi.iditem
            WHERE p.status IN (1,4)
            AND p.data BETWEEN :data_inicio AND :data_fim
            AND p.idcliforemp = :idcliente
            GROUP BY i.descricao, i.referencia
            ORDER BY valor DESC
            LIMIT 10
            ";
            
            $stmtProdutos = $this->pdo->prepare($sqlProdutos);
            $stmtProdutos->execute([
                'idcliente' => $idcliente,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            $produtos = $stmtProdutos->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'cliente' => $cliente,
                'top_produtos' => $produtos
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    public function getInsights(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $user = $request->getAttribute('user');
            $uid = $user['uid'] ?? 0;
            
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            $filtroGestor = $input['gestor'] ?? '';
            $filtroRepresentante = $input['representante'] ?? '';
            
            $permissoes = $this->getUserPermissions($uid);
            
        // 1. Ranking de Representantes
            $rankingReps = $this->getRankingRepresentantes($permissoes, $dataInicio, $dataFim, $filtroGestor, $filtroRepresentante);
            
        // 2. Funil de Pedidos
            $funil = $this->getFunilPedidos($permissoes, $dataInicio, $dataFim, $filtroGestor, $filtroRepresentante);
            
        // 3. Projeção de Fechamento
            $projecaoFechamento = $this->getProjecaoFechamento($permissoes, $dataInicio, $dataFim);
            
        // 4. Clientes em Risco (sem compra há 30+ dias)
            $clientesRisco = $this->getClientesEmRisco($permissoes, $filtroGestor, $filtroRepresentante);
            
        // 5. Frequência de Compra
            $frequencia = $this->getFrequenciaCompra($permissoes, $dataInicio, $dataFim, $filtroGestor, $filtroRepresentante);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'ranking_representantes' => $rankingReps,
                'funil_pedidos' => $funil,
                'projecao_fechamento' => $projecaoFechamento,
                'clientes_risco' => $clientesRisco,
                'frequencia_compra' => $frequencia
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    public function getDetalhesCard(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $tipo = $input['tipo'] ?? '';
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            
            $dados = [];
            
            switch ($tipo) {
                case 'Faturamento':
                $dados = $this->getEvolucaoDiaria($dataInicio, $dataFim);
                break;
                case 'Pedidos':
                $dados = $this->getUltimosPedidos($dataInicio, $dataFim);
                break;
                case 'Ticket Médio':
                $dados = $this->getTicketMedioDiario($dataInicio, $dataFim);
                break;
                case 'Vendas por Dia':
                $dados = $this->getVendasPorDiaSemana($dataInicio, $dataFim);
                break;
                default:
                
                $dados = [['label' => 'Erro: tipo não reconhecido', 'valor' => 0, 'quantidade' => 0, 'tipo_recebido' => $tipo]];
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'dados' => $dados
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

// Evolução diária do faturamento
    private function getEvolucaoDiaria($dataInicio, $dataFim): array
    {
        $sql = "
        SELECT 
        p.data as periodo,
        TO_CHAR(p.data, 'DD/MM') as label,
        SUM(pi.qt * pi.valor) as valor,
        SUM(pi.qt) as quantidade
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.status IN (1,4) AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :inicio AND :fim
        GROUP BY p.data
        ORDER BY p.data DESC
        LIMIT 30
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['inicio' => $dataInicio, 'fim' => $dataFim]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

// Últimos pedidos
    private function getUltimosPedidos($dataInicio, $dataFim): array
    {
        $sql = "
        SELECT 
        p.idpedido as periodo,
        'Pedido #' || p.idpedido as label,
        c.fantasia as cliente,
        SUM(pi.qt * pi.valor) as valor,
        SUM(pi.qt) as quantidade
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
        WHERE p.status IN (1,4) AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :inicio AND :fim
        GROUP BY p.idpedido, c.fantasia
        ORDER BY p.idpedido DESC
        LIMIT 20
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['inicio' => $dataInicio, 'fim' => $dataFim]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

// Ticket médio diário
    private function getTicketMedioDiario($dataInicio, $dataFim): array
    {
        $sql = "
        SELECT 
        sub.data as periodo,
        TO_CHAR(sub.data, 'DD/MM') as label,
        ROUND(AVG(sub.total_pedido), 2) as valor,
        COUNT(sub.idpedido) as quantidade
        FROM (
        SELECT 
        p.data, 
        p.idpedido,
        SUM(pi.qt * pi.valor) as total_pedido
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.status IN (1,4) AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :inicio AND :fim
        GROUP BY p.data, p.idpedido
        ) sub
        GROUP BY sub.data
        ORDER BY sub.data DESC
        LIMIT 30
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['inicio' => $dataInicio, 'fim' => $dataFim]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

// Vendas por dia da semana
    private function getVendasPorDiaSemana($dataInicio, $dataFim): array
    {
        $sql = "
        SELECT 
        CASE EXTRACT(DOW FROM p.data)
        WHEN 0 THEN 'Domingo'
        WHEN 1 THEN 'Segunda'
        WHEN 2 THEN 'Terça'
        WHEN 3 THEN 'Quarta'
        WHEN 4 THEN 'Quinta'
        WHEN 5 THEN 'Sexta'
        WHEN 6 THEN 'Sábado'
        END as label,
        TO_CHAR(p.data, 'Day') as periodo,
        ROUND(AVG(SUM(pi.qt * pi.valor)) OVER (PARTITION BY EXTRACT(DOW FROM p.data)), 2) as valor,
        COUNT(DISTINCT p.data) as quantidade
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.status IN (1,4) AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :inicio AND :fim
        GROUP BY p.data, EXTRACT(DOW FROM p.data)
        ORDER BY EXTRACT(DOW FROM p.data)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['inicio' => $dataInicio, 'fim' => $dataFim]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ======================================================================
    // MÉTODOS PRIVADOS DE CÁLCULO
    // ======================================================================
    // Ranking de Representantes
    private function getRankingRepresentantes($permissoes, $dataInicio, $dataFim, $gestor = null, $representante = null): array
    {
        $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
        
        $sql = "
        SELECT 
        vend.idcliforemp as id,
        vend.fantasia as nome,
        COUNT(DISTINCT p.idpedido) as total_pedidos,
        SUM(pi.qt * pi.valor) as valor,
        COUNT(DISTINCT p.idcliforemp) as clientes_ativos,
        ROUND(SUM(pi.qt * pi.valor) / NULLIF(COUNT(DISTINCT p.idpedido), 0), 2) as ticket_medio
        FROM cliforemp vend
        JOIN pedido p ON p.idvendrepre = vend.idcliforemp
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.status IN (1,4)
        AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :data_inicio AND :data_fim
        ";
        
        if (!empty($permissoes['filiais'])) {
            $in = implode(',', array_map('intval', $permissoes['filiais']));
            $sql .= " AND p.idfilial IN ({$in})";
        }
        if (!empty($gestor)) {
            $sql .= " AND p.idsupervisor = :gestor";
            $params['gestor'] = $gestor;
        }
        
        $sql .= "
        GROUP BY vend.idcliforemp, vend.fantasia
        ORDER BY valor DESC
        LIMIT 5
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

// ======================================================================
// DETALHES DO REPRESENTANTE (Carteira)
// ======================================================================
    public function getDetalhesRepresentante(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $idRepresentante = $input['id'] ?? 0;
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            
        // Clientes do representante
            $sql = "
            SELECT 
            c.idcliforemp as id,
            c.fantasia as nome,
            COUNT(DISTINCT p.idpedido) as total_pedidos,
            SUM(pi.qt * pi.valor) as valor,
            MAX(p.data) as ultimo_pedido,
            (CURRENT_DATE - MAX(p.data)::date) as dias_sem_comprar
            FROM cliforemp c
            JOIN pedido p ON p.idcliforemp = c.idcliforemp
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            WHERE p.idvendrepre = :idrep
            AND p.status IN (1,4)
            AND p.data BETWEEN :inicio AND :fim
            GROUP BY c.idcliforemp, c.fantasia
            ORDER BY valor DESC
            LIMIT 20
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'idrep' => $idRepresentante,
                'inicio' => $dataInicio,
                'fim' => $dataFim
            ]);
            $clientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        // Top produtos do representante
            $sql2 = "
            SELECT 
            i.descricao as produto,
            SUM(pi.qt) as quantidade,
            SUM(pi.qt * pi.valor) as valor
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            JOIN item i ON i.iditem = pi.iditem
            WHERE p.idvendrepre = :idrep
            AND p.status IN (1,4)
            AND p.data BETWEEN :inicio AND :fim
            GROUP BY i.descricao
            ORDER BY valor DESC
            LIMIT 5
            ";
            
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute([
                'idrep' => $idRepresentante,
                'inicio' => $dataInicio,
                'fim' => $dataFim
            ]);
            $produtos = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            
        // Evolução mensal do representante
            $sql3 = "
            SELECT 
            TO_CHAR(DATE_TRUNC('month', p.data), 'YYYY-MM') as periodo,
            TO_CHAR(DATE_TRUNC('month', p.data), 'MM/YYYY') as label,
            SUM(pi.qt * pi.valor) as valor,
            COUNT(DISTINCT p.idpedido) as pedidos
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            WHERE p.idvendrepre = :idrep
            AND p.status IN (1,4)
            AND p.data BETWEEN :inicio AND :fim
            GROUP BY DATE_TRUNC('month', p.data)
            ORDER BY periodo
            ";
            
            $stmt3 = $this->pdo->prepare($sql3);
            $stmt3->execute([
                'idrep' => $idRepresentante,
                'inicio' => $dataInicio,
                'fim' => $dataFim
            ]);
            $evolucao = $stmt3->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'clientes' => $clientes,
                'produtos' => $produtos,
                'evolucao' => $evolucao
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

// ======================================================================
// DETALHES DO FUNIL (Lista de Pedidos por Status)
// ======================================================================
    public function getDetalhesFunil(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $etapa = $input['etapa'] ?? '';
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            $gestor = $input['gestor'] ?? '';
            $representante = $input['representante'] ?? '';
            
            $status = ($etapa === 'Abertos') ? 1 : 4;
            
            $params = ['status' => $status, 'inicio' => $dataInicio, 'fim' => $dataFim];
            
            $sql = "
            SELECT 
            p.idpedido,
            p.data,
            c.fantasia as cliente,
            vend.fantasia as representante,
            SUM(pi.qt * pi.valor) as valor,
            SUM(pi.qt) as quantidade
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
            WHERE p.status = :status
            AND p.idtransacao IN (1,17)
            AND p.data BETWEEN :inicio AND :fim
            ";
            
            if (!empty($gestor)) {
                $sql .= " AND p.idsupervisor = :gestor";
                $params['gestor'] = $gestor;
            }
            if (!empty($representante)) {
                $sql .= " AND p.idvendrepre = :representante";
                $params['representante'] = $representante;
            }
            
            $sql .= "
            GROUP BY p.idpedido, p.data, c.fantasia, vend.fantasia
            ORDER BY p.data DESC
            LIMIT 50
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'pedidos' => $pedidos
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
// Funil de Pedidos
    private function getFunilPedidos($permissoes, $dataInicio, $dataFim, $gestor = null, $representante = null): array
    {
        $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
        
        $sql = "
        SELECT 
        CASE 
        WHEN p.status = 1 THEN 'Abertos'
        WHEN p.status = 4 THEN 'Faturados Parcial'
        ELSE 'Outros'
        END as etapa,
        COUNT(DISTINCT p.idpedido) as quantidade,
        COALESCE(SUM(pi.qt * pi.valor), 0) as valor
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.idtransacao IN (1,17)
        AND p.data BETWEEN :data_inicio AND :data_fim
        ";
        
        if (!empty($permissoes['filiais'])) {
            $in = implode(',', array_map('intval', $permissoes['filiais']));
            $sql .= " AND p.idfilial IN ({$in})";
        }
        if (!empty($gestor)) {
            $sql .= " AND p.idsupervisor = :gestor";
            $params['gestor'] = $gestor;
        }
        if (!empty($representante)) {
            $sql .= " AND p.idvendrepre = :representante";
            $params['representante'] = $representante;
        }
        
        $sql .= " GROUP BY etapa ORDER BY etapa";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

// Projeção de Fechamento do Mês
    private function getProjecaoFechamento($permissoes, $dataInicio, $dataFim): array
    {
        $hoje = date('Y-m-d');
        $ultimoDiaMes = date('Y-m-t');
        $diasRestantes = (new \DateTime($hoje))->diff(new \DateTime($ultimoDiaMes))->days;
        $diasUteisRestantes = $this->calcularDiasUteis($hoje, $ultimoDiaMes);
        
    // Média diária do mês atual
        $mediaDiaria = $this->getVelocidadeVenda($permissoes, date('Y-m-01'), $hoje);
        $mediaValor = floatval($mediaDiaria['media_diaria'] ?? 0);
        $projecao = $mediaValor * $diasUteisRestantes;
        
    // Faturamento atual
        $sql = "
        SELECT COALESCE(SUM(pi.qt * pi.valor), 0)
        FROM pedido p
        JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE p.status IN (1,4) AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :inicio AND :hoje
        ";
        
        if (!empty($permissoes['filiais'])) {
            $in = implode(',', array_map('intval', $permissoes['filiais']));
            $sql .= " AND p.idfilial IN ({$in})";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['inicio' => date('Y-m-01'), 'hoje' => $hoje]);
        $realizado = (float)$stmt->fetchColumn();
        
        return [
            'realizado' => $realizado,
            'projecao' => $realizado + $projecao,
            'projecao_adicional' => $projecao,
            'dias_restantes' => $diasRestantes,
            'dias_uteis_restantes' => $diasUteisRestantes,
            'media_diaria' => $mediaValor
        ];
    }

// Clientes em Risco (sem compra há 30+ dias)
    private function getClientesEmRisco($permissoes, $gestor = null, $representante = null): array
    {
        $params = ['hoje' => date('Y-m-d')];
        
        $sql = "
        SELECT 
        c.idcliforemp as id,
        c.fantasia as nome,
        MAX(p.data) as ultima_compra,
        (DATE(:hoje) - MAX(p.data)) as dias_sem_comprar,
        COALESCE(SUM(pi.qt * pi.valor), 0) as valor_historico
        FROM cliforemp c
        LEFT JOIN pedido p ON p.idcliforemp = c.idcliforemp AND p.status IN (1,4)
        LEFT JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
        WHERE EXISTS (SELECT 1 FROM pedido WHERE idcliforemp = c.idcliforemp)
        ";
        
        if (!empty($permissoes['filiais'])) {
            $in = implode(',', array_map('intval', $permissoes['filiais']));
            $sql .= " AND p.idfilial IN ({$in})";
        }
        if (!empty($gestor)) {
            $sql .= " AND p.idsupervisor = :gestor";
            $params['gestor'] = $gestor;
        }
        if (!empty($representante)) {
            $sql .= " AND p.idvendrepre = :representante";
            $params['representante'] = $representante;
        }
        
        $sql .= "
        GROUP BY c.idcliforemp, c.fantasia
        HAVING MAX(p.data) < (DATE(:hoje) - INTERVAL '30 days')
        ORDER BY dias_sem_comprar DESC
        LIMIT 5
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

// Frequência de Compra
    private function getFrequenciaCompra($permissoes, $dataInicio, $dataFim, $gestor = null, $representante = null): array
    {
        $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
        
        $sql = "
        WITH frequencia_clientes AS (
        SELECT 
        c.idcliforemp,
        c.fantasia,
        COUNT(DISTINCT p.idpedido) as total_compras,
        COUNT(DISTINCT DATE_TRUNC('month', p.data)) as meses_ativos,
        ROUND(COUNT(DISTINCT p.idpedido)::numeric / NULLIF(COUNT(DISTINCT DATE_TRUNC('month', p.data)), 0), 1) as compras_por_mes
        FROM cliforemp c
        JOIN pedido p ON p.idcliforemp = c.idcliforemp
        WHERE p.status IN (1,4)
        AND p.idtransacao IN (1,17)
        AND p.data BETWEEN :data_inicio AND :data_fim
        ";
        
        if (!empty($permissoes['filiais'])) {
            $in = implode(',', array_map('intval', $permissoes['filiais']));
            $sql .= " AND p.idfilial IN ({$in})";
        }
        if (!empty($gestor)) {
            $sql .= " AND p.idsupervisor = :gestor";
            $params['gestor'] = $gestor;
        }
        if (!empty($representante)) {
            $sql .= " AND p.idvendrepre = :representante";
            $params['representante'] = $representante;
        }
        
        $sql .= "
        GROUP BY c.idcliforemp, c.fantasia
        )
        SELECT
        SUM(CASE WHEN compras_por_mes >= 1 THEN 1 ELSE 0 END) as recorrentes,
        SUM(CASE WHEN compras_por_mes < 1 THEN 1 ELSE 0 END) as esporadicos,
        COUNT(*) as total_clientes,
        ROUND(AVG(compras_por_mes), 1) as media_compras_mes
        FROM frequencia_clientes
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

// Calcula dias úteis entre duas datas
    private function calcularDiasUteis($dataInicio, $dataFim): int
    {
        $inicio = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);
        $diasUteis = 0;
        
        while ($inicio <= $fim) {
            $diaSemana = (int)$inicio->format('N');
        if ($diaSemana <= 5) { // Seg-Sex
            $diasUteis++;
        }
        $inicio->modify('+1 day');
    }
    
    return $diasUteis;
}
private function getTicketMedio($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): float
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    SELECT COALESCE(AVG(valor_total), 0) as ticket_medio
    FROM (
    SELECT SUM(pi.qt * pi.valor) as valor_total
    FROM pedido p
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= " GROUP BY p.idpedido
    ) AS pedidos
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return (float)$stmt->fetchColumn();
}

private function getTopProdutos($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    SELECT 
    i.iditem,
    i.descricao as produto,
    i.referencia,
    COALESCE(g.descricao, 'SEM GRUPO') as grupo,
    SUM(pi.qt) as quantidade,
    SUM(pi.qt * pi.valor) as valor,
    ROUND(AVG(pi.valor), 2) as preco_medio,
    ROUND(SUM(pi.qt * COALESCE(ni.valor_comissao,0)) / NULLIF(SUM(pi.qt * pi.valor), 0) * 100, 2) as margem_comissao
    FROM item i
    JOIN pedido_item pi ON pi.iditem = i.iditem
    JOIN pedido p ON p.idpedido = pi.idpedido
    LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
    LEFT JOIN nfs_item ni ON ni.iditem = i.iditem AND ni.idnfs IN (SELECT idnfs FROM nfs WHERE dataemissao BETWEEN :data_inicio AND :data_fim)
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY i.iditem, i.descricao, i.referencia, g.descricao
    ORDER BY valor DESC
    LIMIT 5
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getTopClientes($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    SELECT 
    c.idcliforemp,
    c.fantasia as cliente,
    c.cnpj,
    COALESCE(rg.descricao, 'SEM REGIAO') as regiao,
    COUNT(DISTINCT p.idpedido) as total_pedidos,
    SUM(pi.qt) as quantidade,
    SUM(pi.qt * pi.valor) as valor,
    AVG(pi.qt * pi.valor) as ticket_medio
    FROM cliforemp c
    JOIN pedido p ON p.idcliforemp = c.idcliforemp
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
    LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY c.idcliforemp, c.fantasia, c.cnpj, rg.descricao
    ORDER BY valor DESC
    LIMIT 5
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getProdutoPorCliente($permissoes, $dataInicio, $dataFim): array
{
    $sql = "
    WITH produto_cliente AS (
    SELECT 
    c.fantasia as cliente,
    i.descricao as produto,
    SUM(pi.qt * pi.valor) as valor,
    ROW_NUMBER() OVER (PARTITION BY c.idcliforemp ORDER BY SUM(pi.qt * pi.valor) DESC) as rn
    FROM cliforemp c
    JOIN pedido p ON p.idcliforemp = c.idcliforemp
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    JOIN item i ON i.iditem = pi.iditem
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    }
    
    $sql .= "
    GROUP BY c.idcliforemp, c.fantasia, i.descricao
    )
    SELECT cliente, produto, valor
    FROM produto_cliente
    WHERE rn = 1
    ORDER BY valor DESC
    LIMIT 20
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getDistribuicaoRegiao($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    SELECT 
    COALESCE(rg.descricao, 'SEM REGIAO') as regiao,
    SUM(pi.qt * pi.valor) as valor,
    SUM(pi.qt) as quantidade,
    COUNT(DISTINCT c.idcliforemp) as clientes
    FROM cliforemp c
    JOIN pedido p ON p.idcliforemp = c.idcliforemp
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
    LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY rg.descricao
    ORDER BY valor DESC
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getMargemProdutos($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    SELECT 
    i.iditem,
    i.descricao as produto,
    i.referencia,
    COALESCE(g.descricao, 'SEM GRUPO') as grupo,
    SUM(pi.qt * pi.valor) as receita,
    SUM(COALESCE(ni.valor_comissao,0)) as comissao,
    ROUND(SUM(COALESCE(ni.valor_comissao,0)) / NULLIF(SUM(pi.qt * pi.valor), 0) * 100, 2) as margem_percentual,
    SUM(pi.qt) as quantidade,
    COUNT(DISTINCT c.idcliforemp) as clientes
    FROM item i
    JOIN pedido_item pi ON pi.iditem = i.iditem
    JOIN pedido p ON p.idpedido = pi.idpedido
    JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
    LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
    LEFT JOIN nfs_item ni ON ni.iditem = i.iditem AND ni.idnfs IN (SELECT idnfs FROM nfs WHERE dataemissao BETWEEN :data_inicio AND :data_fim)
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY i.iditem, i.descricao, i.referencia, g.descricao
    HAVING SUM(pi.qt * pi.valor) > 0
    ORDER BY receita DESC
    LIMIT 10
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getVelocidadeVenda($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    WITH vendas_por_dia AS (
    SELECT 
    p.data,
    SUM(pi.qt * pi.valor) as valor_dia
    FROM pedido p
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY p.data
    )
    SELECT 
    AVG(COALESCE(vendas_por_dia.valor_dia, 0)) as media_diaria,
    SUM(COALESCE(vendas_por_dia.valor_dia, 0)) as total_periodo,
    COUNT(DISTINCT p.data) as dias_uteis,
    ROUND(SUM(COALESCE(vendas_por_dia.valor_dia, 0)) / NULLIF(COUNT(DISTINCT p.data), 0), 2) as ticket_medio_diario,
    ROUND(SUM(COALESCE(vendas_por_dia.valor_dia, 0)) / 30, 2) as projecao_mensal
    FROM generate_series(:data_inicio::date, :data_fim::date, '1 day') as p(data)
    LEFT JOIN vendas_por_dia ON vendas_por_dia.data = p.data
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch(\PDO::FETCH_ASSOC);
}

private function getEvolucaoMensal($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    WITH meses AS (
    SELECT generate_series(
    DATE_TRUNC('month', :data_inicio::date),
    DATE_TRUNC('month', :data_fim::date),
    '1 month'::interval
    ) as mes
    ),
    vendas_mes AS (
    SELECT 
    DATE_TRUNC('month', p.data) as mes,
    SUM(pi.qt * pi.valor) as valor,
    SUM(pi.qt) as quantidade,
    COUNT(DISTINCT p.idpedido) as pedidos
    FROM pedido p
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY DATE_TRUNC('month', p.data)
    )
    SELECT 
    TO_CHAR(meses.mes, 'YYYY-MM') as periodo,
    TO_CHAR(meses.mes, 'MM/YYYY') as label,
    COALESCE(vendas_mes.valor, 0) as valor,
    COALESCE(vendas_mes.quantidade, 0) as quantidade,
    COALESCE(vendas_mes.pedidos, 0) as pedidos,
    COALESCE(vendas_mes.valor / NULLIF(vendas_mes.pedidos, 0), 0) as ticket_medio,
    LAG(COALESCE(vendas_mes.valor, 0)) OVER (ORDER BY meses.mes) as valor_anterior,
    CASE 
    WHEN LAG(COALESCE(vendas_mes.valor, 0)) OVER (ORDER BY meses.mes) > 0 
    THEN ROUND((COALESCE(vendas_mes.valor, 0) - LAG(COALESCE(vendas_mes.valor, 0)) OVER (ORDER BY meses.mes)) / LAG(COALESCE(vendas_mes.valor, 0)) OVER (ORDER BY meses.mes) * 100, 2)
    ELSE 0
    END as crescimento
    FROM meses
    LEFT JOIN vendas_mes ON vendas_mes.mes = meses.mes
    ORDER BY meses.mes
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getMatrizCrossSelling($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    SELECT 
    i1.descricao as produto1,
    i2.descricao as produto2,
    COUNT(DISTINCT p.idpedido) as vezes_comprados_juntos,
    SUM(pi1.qt * pi1.valor + pi2.qt * pi2.valor) as valor_total
    FROM pedido p
    JOIN pedido_item pi1 ON pi1.idpedido = p.idpedido AND pi1.ativo = 'S'
    JOIN pedido_item pi2 ON pi2.idpedido = p.idpedido AND pi2.ativo = 'S' AND pi2.iditem > pi1.iditem
    JOIN item i1 ON i1.iditem = pi1.iditem
    JOIN item i2 ON i2.iditem = pi2.iditem
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN :data_inicio AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY i1.descricao, i2.descricao
    ORDER BY vezes_comprados_juntos DESC
    LIMIT 5
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

private function getProjecaoVendas($permissoes, $dataInicio, $dataFim, $filial = null, $gestor = null, $representante = null): array
{
    $params = ['data_inicio' => $dataInicio, 'data_fim' => $dataFim];
    
    $sql = "
    WITH ultimos_meses AS (
    SELECT 
    DATE_TRUNC('month', p.data) as mes,
    SUM(pi.qt * pi.valor) as valor
    FROM pedido p
    JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
    WHERE p.status IN (1,4)
    AND p.idtransacao IN (1,17)
    AND p.data BETWEEN (CAST(:data_inicio AS DATE) - INTERVAL '6 months') AND :data_fim
    ";
    
    if (!empty($permissoes['filiais'])) {
        $in = implode(',', array_map('intval', $permissoes['filiais']));
        $sql .= " AND p.idfilial IN ({$in})";
    } elseif (!empty($filial)) {
        $sql .= " AND p.idfilial = :filial";
        $params['filial'] = $filial;
    }
    
    if (!empty($gestor)) {
        $sql .= " AND p.idsupervisor = :gestor";
        $params['gestor'] = $gestor;
    }
    if (!empty($representante)) {
        $sql .= " AND p.idvendrepre = :representante";
        $params['representante'] = $representante;
    }
    
    $sql .= "
    GROUP BY DATE_TRUNC('month', p.data)
    ),
    media_movel AS (
    SELECT 
    mes,
    valor,
    AVG(valor) OVER (ORDER BY mes ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) as media_3_meses
    FROM ultimos_meses
    )
    SELECT 
    mes,
    valor as realizado,
    media_3_meses as projecao
    FROM media_movel
    ORDER BY mes DESC
    LIMIT 3
    ";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}


private function getUserPermissions(int $uid): array
{
    $stmt = $this->pdo->prepare("SELECT dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid");
    $stmt->execute(['uid' => $uid]);
    $userData = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    return [
        'filiais' => !empty($userData['dash_filiais']) ? explode(',', $userData['dash_filiais']) : [],
        'gestores' => !empty($userData['dash_gestores']) ? explode(',', $userData['dash_gestores']) : []
    ];
}

private function jsonResponse($response, array $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}
}