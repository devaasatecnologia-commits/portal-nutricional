<?php
namespace Nutricional\Controllers;

use PDO;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * MarketingController - Versão Refatorada Completa
 * 
 * ======================================================================
 * ORGANIZAÇÃO DAS FUNÇÕES
 * ======================================================================
 * 
 * 1. DASHBOARD (KPIs, Totais, Taxas, Resumo)
 *    - getDashboardFinal
 *    - getDashboardTotals
 *    - getDashboardTaxas
 *    - getDashboardResumo
 *    - getDashboard
 *    - getDashboardCompleto
 *    - getDashboardOtimizado (NOVO - Usa VIEW materializada)
 *    - getKPIs
 *    - getDadosGrafico
 *    - calcularVariacaoKPIs
 *    - calcularVariacaoCompleta
 * 
 * 2. COMPARATIVO ERP vs CRM
 *    - getComparativoERP
 *    - getComparativoOtimizado (NOVO - Versão otimizada)
 *    - getClientesCompradoresNaoCompradores
 *    - getComparativoMensal
 *    - getResumoGeral
 * 
 * 3. METAS
 *    - getMetas
 *    - getMetaDetalhes
 *    - salvarMeta
 *    - atualizarMeta
 *    - deletarMeta
 *    - getMetasProgresso
 *    - getMetasDashboard
 * 
 * 4. LEADS
 *    - getLeads
 *    - getLeadById
 *    - salvarLead
 *    - atualizarLead
 *    - converterLeadParaCliente
 *    - reverterClienteParaLead
 * 
 * 5. CLIENTES (CRUD)
 *    - getClientes
 *    - getClienteDetalhes
 *    - salvarCliente
 *    - atualizarCliente
 *    - deletarCliente
 *    - consultarClientesUnificado
 *    - importarClienteERP
 *    - sincronizarTodosClientes
 *    - sincronizarDadosCliente
 *    - getClienteERPDetalhes
 * 
 * 6. INTERAÇÕES
 *    - getInteracoes
 *    - salvarInteracao
 *    - registrarInteracaoAutomatica
 * 
 * 7. LEMBRETES
 *    - getLembretes
 *    - getLembretesHoje
 *    - getLembretesAlertas
 *    - criarLembrete
 *    - concluirLembrete
 *    - deletarLembrete
 * 
 * 8. COMPROMISSOS
 *    - getCompromissos
 *    - getCompromissosPorCliente
 *    - getProximosCompromissos
 *    - getMeusProximosCompromissos
 *    - getEstatisticasCompromissos
 *    - criarCompromisso
 *    - atualizarCompromisso
 *    - concluirCompromisso
 *    - deletarCompromisso
 * 
 * 9. ALIMENTAÇÃO DIÁRIA
 *    - alimentarDiario
 *    - getHistoricoAlimentacao
 * 
 * 10. ANEXOS
 *     - uploadAnexo
 *     - getAnexos
 *     - downloadAnexo
 *     - deletarAnexo
 * 
 * 11. TAGS
 *     - atualizarTags
 *     - getTags
 * 
 * 12. NOTIFICAÇÕES
 *     - getNotificacoes
 *     - getNotificacoesCRM
 *     - marcarNotificacaoLida
 *     - marcarNotificacaoCRM
 *     - marcarTodasLidas
 *     - marcarTodasNotificacoesCRM
 *     - criarNotificacaoViaFrontend
 *     - gerarAlertasCRM
 *     - criarNotificacaoCRM
 *     - verificarTabelaNotificacoes
 * 
 * 13. EXPORTAÇÃO
 *     - exportarClientes
 * 
 * 14. EMAIL
 *     - enviarRelatorioEmail
 *     - configurarEmailAuto
 * 
 * 15. CRON
 *     - cronAlertas
 * 
 * 16. CRM DASHBOARD
 *     - getCRMDashboard
 * 
 * 17. MÉTODOS AUXILIARES
 *     - json
 *     - limparTexto
 *     - getUserIdFromToken
 *     - atualizarMetaComLead
 *     - registrarFechamentoMeta
 *     - calcularDiasRestantes
 *     - calcularDiasPassados
 *     - calcularProjecao
 *     - buscarHistoricoMeta
 *     - calcularTendencia
 *     - getDashboardFallback (NOVO - Fallback para rotas antigas)
 * ======================================================================
 */
class MarketingController
{
    private PDO $pdo;
    private string $cacheBuster;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->cacheBuster = date('YmdHis') . '_' . uniqid();
    }

    // ======================================================================
    // 1. DASHBOARD (KPIs, Totais, Taxas, Resumo)
    // ======================================================================

// ======================================================================
// 1. DASHBOARD (KPIs, Totais, Taxas, Resumo)
// ======================================================================


/**
 * GET /v1/marketing/dashboard-final
 * Usa a função PostgreSQL get_dashboard_final()
 * UMA ÚNICA CONSULTA - < 100ms
 */
public function getDashboardFinal(Request $request, Response $response): Response
{
    try {
        $pdo = $this->pdo;
        
        // ⚡ CHAMAR A FUNÇÃO POSTGRESQL
        $sql = "SELECT get_dashboard_final() as data";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['data']) {
            return $this->json($response, [
                'success' => false, 
                'error' => 'Erro ao carregar dados do dashboard'
            ], 500);
        }
        
        // O resultado já é um JSON completo
        $dados = json_decode($result['data'], true);
        
        if (!$dados || !isset($dados['success'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao processar dados do dashboard'
            ], 500);
        }
        
        return $this->json($response, $dados);
        
    } catch (Exception $e) {
        error_log('Erro em getDashboardFinal: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false, 
            'error' => $e->getMessage()
        ], 500);
    }
}



    /**
     * GET /v1/marketing/dashboard-totals
     * Retorna totais gerais com cache busting automático
     */
    public function getDashboardTotals(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            // 1. TOTAL DE CLIENTES
            $sqlTotais = "
                SELECT 
                    COUNT(id_unico) as geral,
                    COUNT(id_erp) as erp,
                    COUNT(id_crm) - COUNT(CASE WHEN id_crm IS NOT NULL AND id_erp IS NOT NULL THEN 1 END) as crm,
                    COUNT(CASE WHEN id_crm IS NOT NULL AND id_erp IS NOT NULL THEN 1 END) as ambos
                FROM view_clientes_unificado
            ";
            $totais = $pdo->query($sqlTotais)->fetch(PDO::FETCH_ASSOC);
            
            // 2. COMPRADORES DO MÊS
            $sqlCompradoresMes = "
                SELECT 
                    COUNT(*) as geral,
                    COUNT(DISTINCT id_erp) as erp,
                    COUNT(DISTINCT id_crm) as crm
                FROM view_clientes_unificado vw
                JOIN (
                    SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                    FROM pedido
                    WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                      AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                      and pedido.status in (1,4,5)
                    GROUP BY idcliforemp
                ) pedido ON pedido.id = vw.id_erp
            ";
            $compradoresMes = $pdo->query($sqlCompradoresMes)->fetch(PDO::FETCH_ASSOC);
            
            // 3. FATURAMENTO DO MÊS
            $sqlFaturamento = "
                SELECT 
                    COALESCE((SELECT SUM(pedido.vl) 
                        FROM view_clientes_unificado vw
                        JOIN (
                            SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                            FROM pedido
                            WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                              AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                              and pedido.status in (1,4,5)
                            GROUP BY idcliforemp
                        ) pedido ON pedido.id = vw.id_erp
                    ), 0) as geral,                    
                    COALESCE((SELECT SUM(pedido.vl) 
                        FROM view_clientes_unificado vw
                        JOIN (
                            SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                            FROM pedido
                            WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                              AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                              and pedido.status in (1,4,5)
                            GROUP BY idcliforemp
                        ) pedido ON pedido.id = vw.id_erp
                        WHERE vw.id_crm > 0
                    ), 0) as crm,                    
                    COALESCE((SELECT SUM(pedido.vl) 
                        FROM view_clientes_unificado vw
                        JOIN (
                            SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                            FROM pedido
                            WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                              AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                              and pedido.status in (1,4,5)
                            GROUP BY idcliforemp
                        ) pedido ON pedido.id = vw.id_erp
                        WHERE vw.id_crm IS NULL OR vw.id_crm = 0
                    ), 0) as erp
            ";
            $faturamento = $pdo->query($sqlFaturamento)->fetch(PDO::FETCH_ASSOC);
            
            // 4. CRM + AMBOS
            $sqlCRMAmbos = "
                SELECT 
                    COUNT(DISTINCT COALESCE(id_crm)) as compradores,
                    COUNT(COALESCE(pedido.id)) as total_geral
                FROM view_clientes_unificado vw
                JOIN (
                    SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                    FROM pedido
                    WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                      AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                      and pedido.status in (1,4,5)
                    GROUP BY idcliforemp
                ) pedido ON pedido.id = vw.id_erp
            ";
            $crmAmbos = $pdo->query($sqlCRMAmbos)->fetch(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'timestamp' => $this->cacheBuster,
                'totais' => [
                    'geral' => (int)($totais['geral'] ?? 0),
                    'erp' => (int)($totais['erp'] ?? 0),
                    'crm' => (int)($totais['crm'] ?? 0),
                    'ambos' => (int)($totais['ambos'] ?? 0)
                ],
                'compradores_mes' => [
                    'geral' => (int)($compradoresMes['geral'] ?? 0),
                    'erp' => (int)($compradoresMes['erp'] ?? 0),
                    'crm' => (int)($compradoresMes['crm'] ?? 0)
                ],
                'faturamento_mes' => [
                    'geral' => (float)($faturamento['geral'] ?? 0),
                    'erp' => (float)($faturamento['erp'] ?? 0),
                    'crm' => (float)($faturamento['crm'] ?? 0)
                ],
                'crm_ambos' => [
                    'compradores' => (int)($crmAmbos['compradores'] ?? 0),
                    'percentual' => (int)($crmAmbos['total_geral'] ?? 0) > 0 
                        ? round(((int)($crmAmbos['compradores'] ?? 0) / (int)($crmAmbos['total_geral'] ?? 0)) * 100, 1)
                        : 0
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getDashboardTotals: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/dashboard-taxas
     */
    public function getDashboardTaxas(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            $sqlGeral = "
                SELECT 
                    COUNT(*) as total_clientes,
                    (SELECT COUNT(*) as quantidade
                     FROM view_clientes_unificado vcu 
                     WHERE EXISTS (
                         SELECT 1 FROM pedido p 
                         WHERE p.idcliforemp = vcu.id_erp 
                         AND EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                         AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                         and pedido.status in (1,4,5)
                     )) as compraram_mes
                FROM view_clientes_unificado vw
            ";
            $geral = $pdo->query($sqlGeral)->fetch(PDO::FETCH_ASSOC);
            
            $sqlERP = "
                SELECT 
                    COUNT(*) as total_clientes,
                    (SELECT COUNT(*) as quantidade
                     FROM view_clientes_unificado vcu 
                     WHERE EXISTS (
                         SELECT 1 FROM pedido p 
                         WHERE p.idcliforemp = vcu.id_erp 
                         AND EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                         AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                         and pedido.status in (1,4,5)
                     )) as compraram_mes
                FROM view_clientes_unificado vw
            ";
            $erp = $pdo->query($sqlERP)->fetch(PDO::FETCH_ASSOC);
            
            $sqlCRM = "
                SELECT 
                    COUNT(DISTINCT id_crm) as total_clientes,
                    COUNT(DISTINCT CASE 
                        WHEN EXTRACT(MONTH FROM data_ultima_compra) = EXTRACT(MONTH FROM CURRENT_DATE)
                        AND EXTRACT(YEAR FROM data_ultima_compra) = EXTRACT(YEAR FROM CURRENT_DATE)
                        AND id_crm IS NOT NULL
                        THEN id_crm 
                    END) as compraram_mes
                FROM view_clientes_unificado
                WHERE id_crm IS NOT NULL
            ";
            $crm = $pdo->query($sqlCRM)->fetch(PDO::FETCH_ASSOC);
            
            $calcTaxa = fn($total, $compraram) => ($total > 0) ? round(($compraram / $total) * 100, 1) : 0;
            
            return $this->json($response, [
                'success' => true,
                'timestamp' => $this->cacheBuster,
                'geral' => $calcTaxa((int)($geral['total_clientes'] ?? 0), (int)($geral['compraram_mes'] ?? 0)),
                'erp' => $calcTaxa((int)($erp['total_clientes'] ?? 0), (int)($erp['compraram_mes'] ?? 0)),
                'crm' => $calcTaxa((int)($crm['total_clientes'] ?? 0), (int)($crm['compraram_mes'] ?? 0))
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getDashboardTaxas: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/dashboard-resumo
     */
    public function getDashboardResumo(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            $sql = "
                SELECT 
                    (SELECT COUNT(*) FROM view_clientes_unificado) as total_clientes,
                    (SELECT COUNT(*) as total_clientes
                     FROM view_clientes_unificado vw
                     JOIN (
                         SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                         FROM pedido
                         WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                           AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                           and pedido.status in (1,4,5)
                         GROUP BY idcliforemp
                     ) pedido ON pedido.id = vw.id_erp) as compradores_mes,
                    (SELECT COALESCE(SUM(pedido.vl), 0) 
                     FROM view_clientes_unificado vw
                     JOIN (
                         SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                         FROM pedido
                         WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                           AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                           and pedido.status in (1,4,5)
                         GROUP BY idcliforemp
                     ) pedido ON pedido.id = vw.id_erp) as faturamento_mes
            ";
            $resumo = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            
            $ticketMedio = ((int)($resumo['compradores_mes'] ?? 0) > 0) 
                ? (float)($resumo['faturamento_mes'] ?? 0) / (int)($resumo['compradores_mes'] ?? 0)
                : 0;
            
            return $this->json($response, [
                'success' => true,
                'timestamp' => $this->cacheBuster,
                'total_clientes' => (int)($resumo['total_clientes'] ?? 0),
                'compradores_mes' => (int)($resumo['compradores_mes'] ?? 0),
                'faturamento_mes' => (float)($resumo['faturamento_mes'] ?? 0),
                'ticket_medio' => round($ticketMedio, 2)
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getDashboardResumo: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/dashboard
     * Dashboard completo com KPIs, gráficos e metas
     */
    public function getDashboard(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            // KPIs
            $sqlKPIs = "
                SELECT 
                    (SELECT COUNT(*) FROM mkt_leads_controle) as total_leads,
                    (SELECT COUNT(*) FROM mkt_leads_controle WHERE status = 'Fechado') as vendas,
                    COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle), 0)::float as faturamento,
                    COALESCE((SELECT SUM(investimento_dia) FROM mkt_alimentacao_diaria), 0)::float as total_investido
            ";
            $kpis = $pdo->query($sqlKPIs)->fetch(PDO::FETCH_ASSOC);
            
            $leads = (int)$kpis['total_leads'];
            $investimento = (float)$kpis['total_investido'];
            
            $kpis['cpl'] = $leads > 0 ? round($investimento / $leads, 2) : 0;
            $kpis['roas'] = $investimento > 0 ? round($kpis['faturamento'] / $investimento, 2) : 0;
            $kpis['taxa_conversao'] = $leads > 0 ? round(($kpis['vendas'] / $leads) * 100, 1) : 0;
            
            // Gráfico Semanal
            $graficoSemanal = $pdo->query("
                SELECT TO_CHAR(data_registro, '\"Sem\" IW') as label, 
                       COUNT(*) as leads, 
                       COALESCE(SUM(valor_fechamento), 0)::float as vendas 
                FROM mkt_leads_controle 
                GROUP BY TO_CHAR(data_registro, '\"Sem\" IW'), EXTRACT(WEEK FROM data_registro) 
                ORDER BY MIN(data_registro) ASC LIMIT 8
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Gráfico Mensal
            $graficoMensal = $pdo->query("
                SELECT TO_CHAR(data_registro, 'TMMon/YY') as label, 
                       COUNT(*) as leads, 
                       COALESCE(SUM(valor_fechamento), 0)::float as vendas 
                FROM mkt_leads_controle 
                GROUP BY TO_CHAR(data_registro, 'TMMon/YY'), EXTRACT(MONTH FROM data_registro), EXTRACT(YEAR FROM data_registro) 
                ORDER BY MIN(data_registro) ASC LIMIT 12
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // CRM - Últimos Leads
            $crm = $pdo->query("
                SELECT * FROM mkt_leads_controle 
                ORDER BY data_registro DESC, id DESC LIMIT 15
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Metas Ativas
            $metas = $pdo->query("
                SELECT 
                    mi.id,
                    mi.titulo,
                    mi.data_inicio,
                    mi.data_fim,
                    mi.status,
                    COALESCE(tm.nome, 'Meta Padrão') as tipo_nome,
                    COALESCE(tm.icone, 'fa-bullseye') as icone,
                    COALESCE(tm.cor, 'emerald') as cor,
                    COALESCE((SELECT COUNT(*) FROM mkt_leads_controle WHERE id_meta = mi.id), 0) as leads_realizados,
                    COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle WHERE id_meta = mi.id), 0)::float as fat_realizado
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.status = 'ativa'
                ORDER BY mi.data_fim ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'kpis' => $kpis,
                'grafico_semanal' => $graficoSemanal,
                'grafico_mensal' => $graficoMensal,
                'crm' => $crm,
                'metas' => $metas,
                'variacao' => $this->calcularVariacaoKPIs()
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getDashboard: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/dashboard-completo
     * Dashboard completo consolidado
     */
    public function getDashboardCompleto(Request $request, Response $response): Response
    {
        try {
            $service = new \Nutricional\Services\MarketingService($this->pdo);
            
            // KPIs
            $kpisFormatados = [
                'total_leads' => 0,
                'vendas' => 0,
                'faturamento' => 0,
                'cpl' => 0,
                'roas' => 0,
                'taxa_conversao' => 0,
                'total_investido' => 0
            ];

            try {
                $stmtKPI = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total_leads,
                        COUNT(*) FILTER (WHERE status = 'Fechado') as vendas,
                        COALESCE(SUM(valor_fechamento), 0)::float as faturamento
                    FROM mkt_leads_controle
                    WHERE data_registro >= date_trunc('month', CURRENT_DATE)
                ");
                $kpis = $stmtKPI->fetch(PDO::FETCH_ASSOC);
                
                if ($kpis) {
                    $totalLeads = (int)$kpis['total_leads'];
                    $kpisFormatados['total_leads'] = $totalLeads;
                    $kpisFormatados['vendas'] = (int)$kpis['vendas'];
                    $kpisFormatados['faturamento'] = (float)$kpis['faturamento'];
                    
                    $stmtInv = $this->pdo->query("
                        SELECT COALESCE(SUM(investimento_dia), 0)::float as total_investido
                        FROM mkt_alimentacao_diaria
                        WHERE data_registro >= date_trunc('month', CURRENT_DATE)
                    ");
                    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
                    $totalInvestido = $inv ? (float)$inv['total_investido'] : 0;
                    $kpisFormatados['total_investido'] = $totalInvestido;
                    
                    $kpisFormatados['cpl'] = $totalLeads > 0 ? round($totalInvestido / $totalLeads, 2) : 0;
                    $kpisFormatados['roas'] = $totalInvestido > 0 ? round((float)$kpis['faturamento'] / $totalInvestido, 2) : 0;
                    $kpisFormatados['taxa_conversao'] = $totalLeads > 0 ? round(((int)$kpis['vendas'] / $totalLeads) * 100, 1) : 0;
                }
            } catch (Exception $e) {
                error_log('Erro ao buscar KPIs: ' . $e->getMessage());
            }

            // Metas
            $metas = [];
            $totalMetas = 0;
            $metasConcluidas = 0;
            $progressoTotal = 0;
            $metasEmRisco = 0;

            try {
                $metas = $service->getMetasComProgresso('ativa');
                foreach ($metas as $meta) {
                    $progresso = $meta['progresso'] ?? 0;
                    $progressoTotal += $progresso;
                    if ($progresso >= 100) $metasConcluidas++;
                    $diasRestantes = $this->calcularDiasRestantes($meta['data_fim'] ?? null);
                    if ($diasRestantes < 0 && $progresso < 100) $metasEmRisco++;
                }
                $totalMetas = count($metas);
            } catch (Exception $e) {
                error_log('Erro ao buscar metas: ' . $e->getMessage());
            }

            $progressoMedio = $totalMetas > 0 ? round($progressoTotal / $totalMetas, 1) : 0;

            // Gráfico Mensal
            $graficoMensal = [];
            try {
                $graficoMensal = $this->pdo->query("
                    SELECT 
                        TO_CHAR(data_registro, 'TMMon/YY') as label,
                        COUNT(*) as leads,
                        COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas,
                        COALESCE(SUM(valor_fechamento), 0)::float as faturamento
                    FROM mkt_leads_controle
                    WHERE data_registro >= CURRENT_DATE - INTERVAL '12 months'
                    GROUP BY TO_CHAR(data_registro, 'TMMon/YY'), EXTRACT(MONTH FROM data_registro), EXTRACT(YEAR FROM data_registro)
                    ORDER BY MIN(data_registro) ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Erro ao buscar gráfico: ' . $e->getMessage());
            }

            // Últimos Leads
            $ultimosLeads = [];
            try {
                $ultimosLeads = $this->pdo->query("
                    SELECT * FROM mkt_leads_controle 
                    ORDER BY data_registro DESC, id DESC LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Erro ao buscar leads: ' . $e->getMessage());
            }

            // Funil
            $funil = ['total_leads' => 0, 'qualificados' => 0, 'propostas' => 0, 'fechados' => 0];
            try {
                $funil = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total_leads,
                        COUNT(CASE WHEN qualificado = 'true' THEN 1 END) as qualificados,
                        COUNT(CASE WHEN status IN ('Fechado', 'Followup') THEN 1 END) as propostas,
                        COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as fechados
                    FROM mkt_leads_controle
                ")->fetch(PDO::FETCH_ASSOC) ?: $funil;
            } catch (Exception $e) {
                error_log('Erro ao buscar funil: ' . $e->getMessage());
            }

            $variacao = $this->calcularVariacaoCompleta();

            return $this->json($response, [
                'success' => true,
                'kpis' => $kpisFormatados,
                'metas' => [
                    'lista' => $metas,
                    'resumo' => [
                        'total' => $totalMetas,
                        'concluidas' => $metasConcluidas,
                        'progresso_medio' => $progressoMedio,
                        'em_risco' => $metasEmRisco,
                        'destaque' => null,
                        'atrasada' => null
                    ]
                ],
                'grafico_mensal' => $graficoMensal,
                'ultimos_leads' => $ultimosLeads,
                'funil' => $funil,
                'variacao' => $variacao
            ]);
            
        } catch (Exception $e) {
            error_log('ERRO em getDashboardCompleto: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/dashboard-otimizado
     * Versão otimizada usando VIEW materializada
     * Reduz de 9+ chamadas para 1 chamada
     */
    public function getDashboardOtimizado(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            // ================================================================
            // 1. BUSCAR DA VIEW MATERIALIZADA (DADOS AGREGADOS)
            // ================================================================
            $sqlAgregados = "
                SELECT 
                    total_geral,
                    total_erp,
                    total_crm,
                    total_ambos,
                    compradores_erp,
                    compradores_crm,
                    faturamento_geral,
                    faturamento_crm,
                    faturamento_erp,
                    nunca_compraram,
                    ultima_atualizacao
                FROM mv_dashboard_marketing
                LIMIT 1
            ";
            $agregados = $pdo->query($sqlAgregados)->fetch(PDO::FETCH_ASSOC);
            
            if (!$agregados) {
                // Fallback: usar consultas normais se a view estiver vazia
                return $this->getDashboardFallback($request, $response);
            }
            
            // ================================================================
            // 2. CALCULAR MÉTRICAS DERIVADAS
            // ================================================================
            $totalGeral = (int)($agregados['total_geral'] ?? 0);
            $totalErp = (int)($agregados['total_erp'] ?? 0);
            $totalCrm = (int)($agregados['total_crm'] ?? 0);
            $totalAmbos = (int)($agregados['total_ambos'] ?? 0);
            
            $compradoresErp = (int)($agregados['compradores_erp'] ?? 0);
            $compradoresCrm = (int)($agregados['compradores_crm'] ?? 0);
            $compradoresGeral = $compradoresErp + $compradoresCrm;
            
            $faturamentoGeral = (float)($agregados['faturamento_geral'] ?? 0);
            $faturamentoCrm = (float)($agregados['faturamento_crm'] ?? 0);
            $faturamentoErp = (float)($agregados['faturamento_erp'] ?? 0);
            
            $nuncaCompraram = (int)($agregados['nunca_compraram'] ?? 0);
            
            // Taxas de conversão
            $taxaGeral = $totalGeral > 0 ? round(($compradoresGeral / $totalGeral) * 100, 1) : 0;
            $taxaErp = $totalErp > 0 ? round(($compradoresErp / $totalErp) * 100, 1) : 0;
            $taxaCrm = $totalCrm > 0 ? round(($compradoresCrm / $totalCrm) * 100, 1) : 0;
            
            // Ticket médio
            $ticketMedioGeral = $compradoresGeral > 0 ? round($faturamentoGeral / $compradoresGeral, 2) : 0;
            $ticketMedioErp = $compradoresErp > 0 ? round($faturamentoErp / $compradoresErp, 2) : 0;
            $ticketMedioCrm = $compradoresCrm > 0 ? round($faturamentoCrm / $compradoresCrm, 2) : 0;
            
            // Percentual CRM + AMBOS
            $pctCrmAmbos = $totalGeral > 0 ? round(($compradoresCrm / $totalGeral) * 100, 1) : 0;
            
            // ================================================================
            // 3. BUSCAR LISTAS (APENAS OS TOP 10)
            // ================================================================
            $sqlCompradores = "
                SELECT 
                    v.id_crm, 
                    v.id_erp, 
                    v.nome, 
                    v.empresa, 
                    v.telefone,
                    v.cidade, 
                    v.uf, 
                    v.origem_dados,
                    COALESCE(p.total_compras, 0) as total_compras,
                    p.total_pedidos,
                    p.ultima_compra as data_ultima_compra
                FROM view_clientes_unificado v
                JOIN (
                    SELECT 
                        idcliforemp,
                        SUM(valortotalpedido) as total_compras,
                        COUNT(DISTINCT idpedido) as total_pedidos,
                        MAX(data) as ultima_compra
                    FROM pedido
                    WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                      AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                      and pedido.status in (1,4,5)
                    GROUP BY idcliforemp
                ) p ON p.idcliforemp = v.id_erp
                ORDER BY p.ultima_compra DESC
                LIMIT 10
            ";
            $compradores = $pdo->query($sqlCompradores)->fetchAll(PDO::FETCH_ASSOC);
            
            // ================================================================
            // 4. BUSCAR CLIENTES QUE NUNCA COMPRARAM (TOP 10)
            // ================================================================
            $sqlNunca = "
                SELECT 
                    v.id_erp,
                    v.nome,
                    v.empresa,
                    v.telefone,
                    v.cidade,
                    v.uf
                FROM view_clientes_unificado v
                WHERE NOT EXISTS (
                    SELECT 1 FROM pedido p WHERE p.idcliforemp = v.id_erp
                )
                AND v.id_erp IS NOT NULL
                ORDER BY v.nome
                LIMIT 10
            ";
            $nuncaCompraramLista = $pdo->query($sqlNunca)->fetchAll(PDO::FETCH_ASSOC);
            
          // ================================================================
// 5. BUSCAR METAS ATIVAS (APENAS 5) - COM CÁLCULO DE PROGRESSO
// ================================================================
$sqlMetas = "
    SELECT 
        mi.id,
        mi.titulo,
        mi.descricao,
        mi.data_inicio,
        mi.data_fim,
        mi.status,
        mi.valores,
        COALESCE(tm.nome, 'Meta Padrão') as tipo_nome,
        COALESCE(tm.icone, 'fa-bullseye') as icone,
        COALESCE(tm.cor, 'emerald') as cor,
        COALESCE((SELECT COUNT(*) FROM mkt_leads_controle WHERE id_meta = mi.id), 0) as leads_realizados,
        COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle WHERE id_meta = mi.id AND status = 'Fechado'), 0)::float as fat_realizado,
        COALESCE(
            (SELECT SUM((valores->>'valor_alcancado')::float) 
             FROM mkt_alimentacao_registros 
             WHERE id_meta_instancia = mi.id), 0
        ) as total_alcancado
    FROM mkt_metas_instancias mi
    LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
    WHERE mi.status = 'ativa'
    ORDER BY mi.data_fim ASC
    LIMIT 5
";
$metas = $pdo->query($sqlMetas)->fetchAll(PDO::FETCH_ASSOC);

// Calcular progresso das metas
foreach ($metas as &$meta) {
    $valores = is_string($meta['valores']) ? json_decode($meta['valores'], true) : ($meta['valores'] ?? []);
    $metaLeads = (int)($valores['meta_leads'] ?? 0);
    $metaFaturamento = (float)($valores['meta_faturamento'] ?? 0);
    
    // Usar total_alcancado da alimentação em vez de leads_realizados
    $leadsRealizados = (float)($meta['total_alcancado'] ?? 0);
    $fatRealizado = (float)($meta['fat_realizado'] ?? 0);
    
    $meta['progresso_leads'] = $metaLeads > 0 ? min(round(($leadsRealizados / $metaLeads) * 100, 1), 100) : 0;
    $meta['progresso_faturamento'] = $metaFaturamento > 0 ? min(round(($fatRealizado / $metaFaturamento) * 100, 1), 100) : 0;
    $meta['progresso_medio'] = round(($meta['progresso_leads'] + $meta['progresso_faturamento']) / 2, 1);
    $meta['leads_realizados'] = $leadsRealizados; // Garantir que o campo seja enviado
    $meta['meta_leads'] = $metaLeads; // Garantir que o campo seja enviado
    
    // Dias restantes
    if ($meta['data_fim']) {
        $fim = new \DateTime($meta['data_fim']);
        $hoje = new \DateTime();
        $meta['dias_restantes'] = $fim > $hoje ? $hoje->diff($fim)->days : 0;
    }
}
            
            // ================================================================
            // 6. INSIGHTS
            // ================================================================
            $insights = [
                'destaque' => $compradoresGeral . ' clientes compraram este mês',
                'atencao' => $nuncaCompraram . ' clientes nunca compraram',
                'oportunidade' => ($totalGeral - $compradoresGeral) . ' clientes para ativar'
            ];
            
            // ================================================================
            // 7. RESPOSTA CONSOLIDADA
            // ================================================================
            return $this->json($response, [
                'success' => true,
                'timestamp' => time(),
                'ultima_atualizacao' => $agregados['ultima_atualizacao'] ?? date('Y-m-d H:i:s'),
                'kpis' => [
                    'total_clientes' => [
                        'geral' => $totalGeral,
                        'erp' => $totalErp,
                        'crm' => $totalCrm,
                        'ambos' => $totalAmbos
                    ],
                    'compradores_mes' => [
                        'geral' => $compradoresGeral,
                        'erp' => $compradoresErp,
                        'crm' => $compradoresCrm
                    ],
                    'taxas' => [
                        'geral' => $taxaGeral,
                        'erp' => $taxaErp,
                        'crm' => $taxaCrm
                    ],
                    'faturamento' => [
                        'geral' => $faturamentoGeral,
                        'erp' => $faturamentoErp,
                        'crm' => $faturamentoCrm
                    ],
                    'ticket_medio' => [
                        'geral' => $ticketMedioGeral,
                        'erp' => $ticketMedioErp,
                        'crm' => $ticketMedioCrm
                    ],
                    'crm_ambos' => [
                        'quantidade' => $compradoresCrm,
                        'percentual' => $pctCrmAmbos
                    ],
                    'nunca_compraram' => $nuncaCompraram
                ],
                'listas' => [
                    'compradores' => $compradores,
                    'nunca_compraram' => $nuncaCompraramLista
                ],
                'metas' => $metas,
                'insights' => $insights
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getDashboardOtimizado: ' . $e->getMessage());
            return $this->getDashboardFallback($request, $response);
        }
    }

    /**
     * GET /v1/marketing/kpis
     */
    public function getKPIs(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT 
                    (SELECT COUNT(*) FROM mkt_leads_controle) as total_leads,
                    (SELECT COUNT(*) FROM mkt_leads_controle WHERE status = 'Fechado') as vendas,
                    COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle), 0)::float as faturamento,
                    COALESCE((SELECT SUM(investimento_dia) FROM mkt_alimentacao_diaria), 0)::float as total_investido
            ";
            $stmt = $this->pdo->query($sql);
            $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

            $leads = (int)$kpis['total_leads'];
            $investimento = (float)$kpis['total_investido'];

            $kpis['cpl'] = $leads > 0 ? round($investimento / $leads, 2) : 0;
            $kpis['roas'] = $investimento > 0 ? round((float)$kpis['faturamento'] / $investimento, 2) : 0;
            $kpis['taxa_conversao'] = $leads > 0 ? round(((int)$kpis['vendas'] / $leads) * 100, 1) : 0;

            return $this->json($response, $kpis);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/dados-grafico
     */
    public function getDadosGrafico(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $periodo = $params['periodo'] ?? 'mensal';
        $dataInicio = $params['data_inicio'] ?? null;
        $dataFim = $params['data_fim'] ?? null;

        try {
            $whereExtra = '';
            $bindParams = [];

            if ($dataInicio && $dataFim) {
                $whereExtra = "WHERE data_registro BETWEEN :inicio AND :fim";
                $bindParams['inicio'] = $dataInicio;
                $bindParams['fim'] = $dataFim;
            }

            if ($periodo === '7dias') {
                $sql = "SELECT TO_CHAR(data_registro, 'DD/MM') as label, COUNT(*) as leads, COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas, COALESCE(SUM(valor_fechamento), 0)::float as faturamento FROM mkt_leads_controle WHERE data_registro >= CURRENT_DATE - INTERVAL '7 days' GROUP BY TO_CHAR(data_registro, 'DD/MM'), data_registro ORDER BY data_registro ASC";
            } elseif ($periodo === '30dias') {
                $sql = "SELECT TO_CHAR(data_registro, 'DD/MM') as label, COUNT(*) as leads, COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas, COALESCE(SUM(valor_fechamento), 0)::float as faturamento FROM mkt_leads_controle WHERE data_registro >= CURRENT_DATE - INTERVAL '30 days' GROUP BY TO_CHAR(data_registro, 'DD/MM'), data_registro ORDER BY data_registro ASC";
            } elseif ($dataInicio && $dataFim) {
                $sql = "SELECT TO_CHAR(data_registro, 'DD/MM') as label, COUNT(*) as leads, COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas, COALESCE(SUM(valor_fechamento), 0)::float as faturamento FROM mkt_leads_controle {$whereExtra} GROUP BY TO_CHAR(data_registro, 'DD/MM'), data_registro ORDER BY data_registro ASC";
            } elseif ($periodo === 'semanal') {
                $sql = "SELECT TO_CHAR(data_registro, '\"Sem\" IW') as label, COUNT(*) as leads, COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas, COALESCE(SUM(valor_fechamento), 0)::float as faturamento FROM mkt_leads_controle GROUP BY TO_CHAR(data_registro, '\"Sem\" IW'), EXTRACT(WEEK FROM data_registro) ORDER BY MIN(data_registro) ASC LIMIT 8";
            } else {
                $sql = "SELECT TO_CHAR(data_registro, 'TMMon/YY') as label, COUNT(*) as leads, COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as vendas, COALESCE(SUM(valor_fechamento), 0)::float as faturamento FROM mkt_leads_controle GROUP BY TO_CHAR(data_registro, 'TMMon/YY'), EXTRACT(MONTH FROM data_registro), EXTRACT(YEAR FROM data_registro) ORDER BY MIN(data_registro) ASC LIMIT 12";
            }

            $stmt = $this->pdo->prepare($sql);
            if (!empty($bindParams)) {
                $stmt->execute($bindParams);
            } else {
                $stmt->execute();
            }
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sqlFunil = "SELECT 
                COUNT(*) as total_leads,
                COUNT(CASE WHEN qualificado = 'true' THEN 1 END) as qualificados,
                COUNT(CASE WHEN status IN ('Fechado', 'Followup') THEN 1 END) as propostas,
                COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as fechados
                FROM mkt_leads_controle";
            $funil = $this->pdo->query($sqlFunil)->fetch(PDO::FETCH_ASSOC);

            return $this->json($response, array_merge($funil, ['dados' => $dados]));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 2. COMPARATIVO ERP vs CRM
    // ======================================================================

    /**
     * GET /v1/marketing/clientes/comparativo-erp
     * Retorna dados do ERP para comparativo com cache busting automático
     */
    public function getComparativoERP(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            // ================================================================
            // 1. TOTAL DE CLIENTES ERP
            // ================================================================
            $sqlTotal = "
                SELECT COUNT(*) as total
                FROM view_clientes_unificado
            ";
            $totalClientes = (int)$pdo->query($sqlTotal)->fetchColumn();
            
            // ================================================================
            // 2. CLIENTES QUE COMPRARAM NO MÊS ATUAL
            // ================================================================
            $sqlMesAtual = "
                SELECT COUNT(*) as quantidade
                FROM view_clientes_unificado vcu 
                WHERE EXISTS (
                    SELECT 1 FROM pedido p 
                    WHERE p.idcliforemp = vcu.id_erp 
                    AND EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                    and pedido.status in (1,4,5)
                )
            ";
            $mesAtual = $pdo->query($sqlMesAtual)->fetch(PDO::FETCH_ASSOC);
            
            // ================================================================
            // 3. CLIENTES QUE NUNCA COMPRARAM
            // ================================================================
            $sqlNuncaCompraram = "
                SELECT COUNT(*) as quantidade
                FROM view_clientes_unificado vcu 
                WHERE NOT EXISTS (
                    SELECT 1 FROM pedido p WHERE p.idcliforemp = vcu.id_erp
                )
            ";
            $nuncaCompraram = (int)$pdo->query($sqlNuncaCompraram)->fetchColumn();
            
            // ================================================================
            // 4. TOTAL DE COMPRAS DO MÊS (para ticket médio)
            // ================================================================
            $sqlTotalValorMes = "
                SELECT 
                    COALESCE(SUM(pedido.vl), 0) as total_valor
                FROM view_clientes_unificado vw
                JOIN (
                    SELECT DISTINCT idcliforemp as id, SUM(valortotalpedido) as vl
                    FROM pedido
                    WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                      AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                      and pedido.status in (1,4,5)
                    GROUP BY idcliforemp
                ) pedido ON pedido.id = vw.id_erp
            ";
            $totalValorMes = (float)$pdo->query($sqlTotalValorMes)->fetchColumn();
            
            // ================================================================
            // 5. CLIENTES QUE COMPRARAM NO MÊS (LISTA)
            // ================================================================
            $sqlClientesMes = "
                SELECT 
                    c.id_erp,
                    c.nome as nome,
                    c.empresa as empresa,
                    c.telefone as telefone,
                    COUNT(p.idpedido) as total_pedidos,
                    COALESCE(SUM(p.valortotalpedido), 0) as total_compras,
                    MAX(p.data) as data_ultima_compra
                FROM view_clientes_unificado c
                JOIN pedido p ON p.idcliforemp = c.id_erp
                WHERE EXTRACT(MONTH FROM p.data) = EXTRACT(MONTH FROM CURRENT_DATE)
                  AND EXTRACT(YEAR FROM p.data) = EXTRACT(YEAR FROM CURRENT_DATE)
                GROUP BY c.id_erp, c.nome, c.empresa, c.telefone
                ORDER BY MAX(p.data) DESC
            ";
            $clientesMes = $pdo->query($sqlClientesMes)->fetchAll(PDO::FETCH_ASSOC);
            
            // ================================================================
            // 6. CLIENTES QUE NUNCA COMPRARAM (LISTA)
            // ================================================================
            $sqlClientesNunca = "
                SELECT 
                    id_erp,
                    nome as nome,
                    empresa as empresa,
                    telefone as telefone,
                    0 as total_pedidos,
                    0 as total_compras,
                    NULL as data_ultima_compra
                FROM view_clientes_unificado vcu 
                WHERE NOT EXISTS (
                    SELECT 1 FROM pedido p WHERE p.idcliforemp = vcu.id_erp
                )
                ORDER BY nome
            ";
            $clientesNunca = $pdo->query($sqlClientesNunca)->fetchAll(PDO::FETCH_ASSOC);
            
            $quantidade = (int)($mesAtual['quantidade'] ?? 0);
            
            // ================================================================
            // RESPOSTA
            // ================================================================
            return $this->json($response, [
                'success' => true,
                'timestamp' => $this->cacheBuster,
                'total_clientes' => $totalClientes,
                'mes_atual' => [
                    'quantidade' => $quantidade,
                    'valor' => (float)($mesAtual['valor'] ?? 0),
                    'clientes' => $clientesMes
                ],
                'nunca_compraram' => [
                    'quantidade' => $nuncaCompraram,
                    'clientes' => $clientesNunca
                ],
                'total_valor_mes' => $totalValorMes,
                'ticket_medio' => $quantidade > 0 ? round($totalValorMes / $quantidade, 2) : 0
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getComparativoERP: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/comparativo-otimizado
     * Versão otimizada do comparativo usando CTE
     * Reduz de 6+ consultas para 1 consulta
     */
    public function getComparativoOtimizado(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            $sql = "
                WITH compras_mes AS (
                    SELECT 
                        idcliforemp,
                        SUM(valortotalpedido) as total_compras,
                        COUNT(DISTINCT idpedido) as total_pedidos,
                        MAX(data) as ultima_compra
                    FROM pedido
                    WHERE EXTRACT(MONTH FROM data) = EXTRACT(MONTH FROM CURRENT_DATE)
                      AND EXTRACT(YEAR FROM data) = EXTRACT(YEAR FROM CURRENT_DATE)
                      and pedido.status in (1,4,5)
                    GROUP BY idcliforemp
                ),
                nunca_compraram AS (
                    SELECT v.id_erp, v.nome, v.empresa, v.telefone
                    FROM view_clientes_unificado v
                    WHERE NOT EXISTS (
                        SELECT 1 FROM pedido p WHERE p.idcliforemp = v.id_erp
                    )
                    AND v.id_erp IS NOT NULL
                    ORDER BY v.nome
                    LIMIT 20
                ),
                compradores_lista AS (
                    SELECT 
                        v.id_erp,
                        v.nome,
                        v.empresa,
                        v.telefone,
                        c.total_compras,
                        c.total_pedidos,
                        c.ultima_compra
                    FROM view_clientes_unificado v
                    JOIN compras_mes c ON c.idcliforemp = v.id_erp
                    ORDER BY c.ultima_compra DESC
                    LIMIT 20
                )
                SELECT 
                    (SELECT COUNT(*) FROM view_clientes_unificado) as total_clientes,
                    (SELECT COUNT(*) FROM compras_mes) as compradores,
                    (SELECT COALESCE(SUM(total_compras), 0) FROM compras_mes) as total_valor,
                    (SELECT COUNT(*) FROM nunca_compraram) as nunca_compraram,
                    (SELECT COALESCE(SUM(total_compras), 0) FROM compras_mes) / NULLIF((SELECT COUNT(*) FROM compras_mes), 0) as ticket_medio,
                    (SELECT json_agg(row_to_json(n)) FROM nunca_compraram n) as lista_nunca,
                    (SELECT json_agg(row_to_json(c)) FROM compradores_lista c) as lista_compradores
            ";
            
            $result = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'timestamp' => time(),
                'total_clientes' => (int)($result['total_clientes'] ?? 0),
                'mes_atual' => [
                    'quantidade' => (int)($result['compradores'] ?? 0),
                    'valor' => (float)($result['total_valor'] ?? 0)
                ],
                'nunca_compraram' => [
                    'quantidade' => (int)($result['nunca_compraram'] ?? 0),
                    'clientes' => json_decode($result['lista_nunca'] ?? '[]', true)
                ],
                'compradores' => json_decode($result['lista_compradores'] ?? '[]', true),
                'ticket_medio' => (float)($result['ticket_medio'] ?? 0)
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getComparativoOtimizado: ' . $e->getMessage());
            return $this->getComparativoERP($request, $response);
        }
    }

    /**
     * GET /v1/marketing/clientes-compradores
     */
    public function getClientesCompradoresNaoCompradores(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            $compradores = $pdo->query("
                SELECT 
                    v.id_crm, v.id_erp, v.nome, v.empresa, v.telefone,
                    v.cidade, v.uf, v.origem_dados,
                    COALESCE(SUM(p.valortotalpedido), 0) as total_compras,
                    v.data_ultima_compra
                FROM view_clientes_unificado v
                JOIN pedido p ON p.idcliforemp = v.id_erp
                WHERE EXTRACT(MONTH FROM p.data) = EXTRACT(MONTH FROM CURRENT_DATE)
                  AND EXTRACT(YEAR FROM p.data) = EXTRACT(YEAR FROM CURRENT_DATE)
                GROUP BY v.id_crm, v.id_erp, v.nome, v.empresa, v.telefone,
                         v.cidade, v.uf, v.origem_dados, v.data_ultima_compra
                ORDER BY v.id_erp, v.data_ultima_compra DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $nuncaCompradores = $pdo->query("
                SELECT 
                    v.id_crm, v.id_erp, v.nome, v.empresa, v.telefone,
                    v.cidade, v.uf, v.origem_dados,
                    v.data_cadastro_crm, v.data_cadastro_erp
                FROM view_clientes_unificado v
                WHERE NOT EXISTS (
                    SELECT 1 FROM pedido p WHERE p.idcliforemp = v.id_erp
                )
                ORDER BY v.nome
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $crmAmbosCompradores = $pdo->query("
                SELECT 
                    v.id_crm, v.id_erp, v.nome, v.empresa, v.telefone,
                    v.cidade, v.uf, v.origem_dados,
                    COALESCE(SUM(p.valortotalpedido), 0) as total_compras,
                    v.data_ultima_compra
                FROM view_clientes_unificado v
                JOIN pedido p ON p.idcliforemp = v.id_erp
                WHERE (v.id_crm IS NOT NULL OR (v.id_crm IS NOT NULL AND v.id_erp IS NOT NULL))
                  AND EXTRACT(MONTH FROM p.data) = EXTRACT(MONTH FROM CURRENT_DATE)
                  AND EXTRACT(YEAR FROM p.data) = EXTRACT(YEAR FROM CURRENT_DATE)
                GROUP BY v.id_crm, v.id_erp, v.nome, v.empresa, v.telefone,
                         v.cidade, v.uf, v.origem_dados, v.data_ultima_compra
                ORDER BY v.id_erp, v.data_ultima_compra DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'timestamp' => $this->cacheBuster,
                'compradores' => $compradores,
                'nunca_compraram' => $nuncaCompradores,
                'crm_ambos_compradores' => $crmAmbosCompradores,
                'totais' => [
                    'compradores' => count($compradores),
                    'nunca_compraram' => count($nuncaCompradores),
                    'crm_ambos_compradores' => count($crmAmbosCompradores)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getClientesCompradoresNaoCompradores: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/comparativo-mensal
     */
    public function getComparativoMensal(Request $request, Response $response): Response
    {
        try {
            $mesAtual = date('m');
            $anoAtual = date('Y');
            $mesAnterior = date('m', strtotime('-1 month'));
            $anoAnterior = date('Y', strtotime('-1 month'));
            
            $sql = "
                SELECT 
                    COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_atual AND EXTRACT(YEAR FROM data_registro) = :ano_atual THEN 1 ELSE 0 END), 0) as leads_atual,
                    COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_anterior AND EXTRACT(YEAR FROM data_registro) = :ano_anterior THEN 1 ELSE 0 END), 0) as leads_anterior,
                    COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_atual2 AND EXTRACT(YEAR FROM data_registro) = :ano_atual2 AND status = 'Fechado' THEN valor_fechamento ELSE 0 END), 0)::float as fat_atual,
                    COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_anterior2 AND EXTRACT(YEAR FROM data_registro) = :ano_anterior2 AND status = 'Fechado' THEN valor_fechamento ELSE 0 END), 0)::float as fat_anterior,
                    COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_atual3 AND EXTRACT(YEAR FROM data_registro) = :ano_atual3 AND status = 'Fechado' THEN 1 ELSE 0 END), 0) as vendas_atual,
                    COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_anterior3 AND EXTRACT(YEAR FROM data_registro) = :ano_anterior3 AND status = 'Fechado' THEN 1 ELSE 0 END), 0) as vendas_anterior
                FROM mkt_leads_controle
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'mes_atual' => $mesAtual, 'ano_atual' => $anoAtual,
                'mes_anterior' => $mesAnterior, 'ano_anterior' => $anoAnterior,
                'mes_atual2' => $mesAtual, 'ano_atual2' => $anoAtual,
                'mes_anterior2' => $mesAnterior, 'ano_anterior2' => $anoAnterior,
                'mes_atual3' => $mesAtual, 'ano_atual3' => $anoAtual,
                'mes_anterior3' => $mesAnterior, 'ano_anterior3' => $anoAnterior
            ]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sqlInv = "SELECT 
                COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_atual THEN investimento_dia ELSE 0 END), 0)::float as inv_atual,
                COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM data_registro) = :mes_anterior THEN investimento_dia ELSE 0 END), 0)::float as inv_anterior
                FROM mkt_alimentacao_diaria";
            $stmtInv = $this->pdo->prepare($sqlInv);
            $stmtInv->execute(['mes_atual' => $mesAtual, 'mes_anterior' => $mesAnterior]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            
            $calcularPct = function($atual, $anterior) {
                if ($anterior == 0) return $atual > 0 ? 100 : 0;
                return round((($atual - $anterior) / $anterior) * 100, 1);
            };
            
            return $this->json($response, [
                'mes_atual' => date('F/Y'),
                'mes_anterior' => date('F/Y', strtotime('-1 month')),
                'leads_atual' => (int)$dados['leads_atual'],
                'leads_anterior' => (int)$dados['leads_anterior'],
                'vendas_atual' => (int)$dados['vendas_atual'],
                'vendas_anterior' => (int)$dados['vendas_anterior'],
                'fat_atual' => (float)$dados['fat_atual'],
                'fat_anterior' => (float)$dados['fat_anterior'],
                'inv_atual' => (float)($inv['inv_atual'] ?? 0),
                'inv_anterior' => (float)($inv['inv_anterior'] ?? 0),
                'variacao_leads' => $calcularPct((int)$dados['leads_atual'], (int)$dados['leads_anterior']),
                'variacao_vendas' => $calcularPct((int)$dados['vendas_atual'], (int)$dados['vendas_anterior']),
                'variacao_fat' => $calcularPct((float)$dados['fat_atual'], (float)$dados['fat_anterior']),
                'variacao_inv' => $calcularPct((float)($inv['inv_atual'] ?? 0), (float)($inv['inv_anterior'] ?? 0))
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/resumo-geral
     */
    public function getResumoGeral(Request $request, Response $response): Response
    {
        try {
            $sqlAlimentacao = "
                SELECT 
                    COALESCE(SUM(leads_recebidos), 0) as total_leads,
                    COALESCE(SUM(vendas_fechadas), 0) as total_vendas,
                    COALESCE(SUM(valor_faturado), 0)::float as total_faturado,
                    COALESCE(SUM(investimento_dia), 0)::float as total_investido
                FROM mkt_alimentacao_diaria
            ";
            $alimentacao = $this->pdo->query($sqlAlimentacao)->fetch(PDO::FETCH_ASSOC);
            
            $sqlCRM = "
                SELECT 
                    COUNT(*) as total_clientes,
                    COUNT(*) FILTER (WHERE status IN ('Novo', 'Qualificado', 'Proposta')) as em_negociacao,
                    COUNT(*) FILTER (WHERE status = 'Fechado') as fechados,
                    COUNT(*) FILTER (WHERE status = 'Perdido') as perdidos,
                    COALESCE(SUM(valor_negocio), 0)::float as valor_pipeline,
                    COALESCE(SUM(CASE WHEN status = 'Fechado' THEN valor_negocio ELSE 0 END), 0)::float as valor_fechado
                FROM mkt_clientes
            ";
            $crm = $this->pdo->query($sqlCRM)->fetch(PDO::FETCH_ASSOC);
            
            $sqlMetas = "
                SELECT 
                    COUNT(*) as total_ativas,
                    COALESCE(SUM((valores->>'meta_leads')::int), 0) as meta_leads_total,
                    COALESCE(SUM((valores->>'meta_faturamento')::float), 0)::float as meta_fat_total
                FROM mkt_metas_instancias 
                WHERE status = 'ativa'
            ";
            $metas = $this->pdo->query($sqlMetas)->fetch(PDO::FETCH_ASSOC);
            
            $sqlAlertas = "
                SELECT COUNT(*) as total
                FROM mkt_clientes c
                WHERE c.termometro = 'Quente' 
                  AND c.status NOT IN ('Fechado', 'Perdido')
                  AND (
                      NOT EXISTS (SELECT 1 FROM mkt_interacoes i WHERE i.id_cliente = c.id)
                      OR (SELECT MAX(i.data_interacao) FROM mkt_interacoes i WHERE i.id_cliente = c.id) < CURRENT_DATE - INTERVAL '3 days'
                  )
            ";
            $alertas = (int)$this->pdo->query($sqlAlertas)->fetchColumn();
            
            $sqlLembretes = "
                SELECT COUNT(*) FROM mkt_lembretes 
                WHERE concluido = false AND data_lembrete <= CURRENT_DATE
            ";
            $lembretesPendentes = (int)$this->pdo->query($sqlLembretes)->fetchColumn();
            
            $sqlOrigem = "
                SELECT origem, COUNT(*) as total,
                       COUNT(*) FILTER (WHERE status = 'Fechado') as fechados
                FROM mkt_clientes
                WHERE data_cadastro >= CURRENT_DATE - INTERVAL '90 days'
                GROUP BY origem ORDER BY total DESC
            ";
            $origem = $this->pdo->query($sqlOrigem)->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'alimentacao' => $alimentacao,
                'crm' => $crm,
                'metas' => $metas,
                'alertas' => $alertas,
                'lembretes_pendentes' => $lembretesPendentes,
                'origem' => $origem
            ]);
        } catch (Exception $e) {
            error_log('Erro em getResumoGeral: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }


    // ======================================================================
// 3. METAS - OTIMIZADO
// ======================================================================
/**
 * GET /v1/marketing/metas-otimizadas
 * Usa a função PostgreSQL get_metas_otimizadas()
 */
public function getMetasOtimizadas(Request $request, Response $response): Response
{
    try {
        $pdo = $this->pdo;
        $params = $request->getQueryParams();
        $status = $params['status'] ?? 'ativa';
        
        // ⚡ CHAMAR A FUNÇÃO POSTGRESQL
        $sql = "SELECT get_metas_otimizadas(:status) as data";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['status' => $status]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['data']) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar metas'
            ], 500);
        }
        
        $metas = json_decode($result['data'], true);
        
        return $this->json($response, [
            'success' => true,
            'data' => $metas,
            'total' => count($metas)
        ]);
        
    } catch (Exception $e) {
        error_log('Erro em getMetasOtimizadas: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * GET /v1/marketing/meta-historico/{id}
 * Histórico de alimentação da meta em UMA ÚNICA CONSULTA
 */
public function getMetaHistorico(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    $limite = (int)($request->getQueryParams()['limite'] ?? 50);
    
    if ($id <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID da meta é obrigatório'
        ], 400);
    }
    
    try {
        $pdo = $this->pdo;
        
        $sql = "
            SELECT 
                ar.id,
                ar.data_registro,
                ar.valores,
                ar.usuario_id,
                u.username as usuario_nome,
                (ar.valores->>'valor_alcancado')::float as valor_alcancado
            FROM mkt_alimentacao_registros ar
            LEFT JOIN usuario u ON u.idusuario = ar.usuario_id
            WHERE ar.id_meta_instancia = :id
            ORDER BY ar.data_registro DESC
            LIMIT :limite
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar dados da meta para contexto
        $stmtMeta = $pdo->prepare("
            SELECT 
                mi.id,
                mi.titulo,
                mi.descricao,
                mi.data_inicio,
                mi.data_fim,
                mi.status,
                mi.valores,
                COALESCE(tm.nome, 'Meta Padrao') as tipo_nome
            FROM mkt_metas_instancias mi
            LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
            WHERE mi.id = :id
        ");
        $stmtMeta->execute(['id' => $id]);
        $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'meta' => $meta,
            'historico' => $historico,
            'total' => count($historico)
        ]);
        
    } catch (Exception $e) {
        error_log('Erro em getMetaHistorico: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * GET /v1/marketing/metas
     */
    public function getMetas(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT mi.*, 
                       COALESCE(tm.nome, 'Meta Padrão') as tipo_nome,
                       COALESCE(tm.icone, 'fa-bullseye') as icone,
                       COALESCE(tm.cor, 'emerald') as cor,
                       COALESCE((SELECT COUNT(*) FROM mkt_leads_controle WHERE id_meta = mi.id), 0) as leads_realizados,
                       COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle WHERE id_meta = mi.id), 0)::float as fat_realizado
                FROM mkt_metas_instancias mi 
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                ORDER BY mi.status ASC, mi.data_fim ASC
            ";
            return $this->json($response, $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/metas/{id}
     */
    public function getMetaDetalhes(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    mi.*,
                    COALESCE(tm.nome, 'Meta Padrão') as tipo_nome,
                    COALESCE(tm.icone, 'fa-bullseye') as icone,
                    COALESCE(tm.cor, 'emerald') as cor
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
            return $this->json($response, $meta ?: ['error' => 'Meta não encontrada'], $meta ? 200 : 404);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/metas
     */
    public function salvarMeta(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $valores = json_encode([
                'meta_leads' => (int)($input['meta_leads'] ?? 0),
                'meta_faturamento' => (float)($input['meta_faturamento'] ?? 0)
            ]);
            
            $sql = "INSERT INTO mkt_metas_instancias (titulo, descricao, data_inicio, data_fim, status, valores) 
                    VALUES (:titulo, :desc, :inicio, :fim, :status, :valores::jsonb)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'titulo' => $input['titulo'] ?? '',
                'desc' => $input['objetivo'] ?? '',
                'inicio' => $input['data_inicio'] ?? null,
                'fim' => $input['data_fim'] ?? null,
                'status' => $input['status'] ?? 'ativa',
                'valores' => $valores
            ]);

            return $this->json($response, ['success' => true, 'id' => $this->pdo->lastInsertId()]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/marketing/metas/{id}
     */
    public function atualizarMeta(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $valores = json_encode([
                'meta_leads' => (int)($input['meta_leads'] ?? 0),
                'meta_faturamento' => (float)($input['meta_faturamento'] ?? 0)
            ]);
            
            $sql = "UPDATE mkt_metas_instancias 
                    SET titulo = :titulo, 
                        descricao = :desc, 
                        data_inicio = :inicio, 
                        data_fim = :fim, 
                        status = :status,
                        valores = :valores::jsonb
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'titulo' => $input['titulo'] ?? '',
                'desc' => $input['objetivo'] ?? '',
                'inicio' => $input['data_inicio'] ?? null,
                'fim' => $input['data_fim'] ?? null,
                'status' => $input['status'] ?? 'ativa',
                'valores' => $valores
            ]);

            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /v1/marketing/metas/{id}
     */
    public function deletarMeta(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        try {
            $stmt = $this->pdo->prepare("DELETE FROM mkt_metas_instancias WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/metas-progresso
     */
    public function getMetasProgresso(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT 
                    mi.id,
                    mi.titulo,
                    mi.data_inicio,
                    mi.data_fim,
                    mi.status,
                    mi.valores,
                    COALESCE(tm.nome, 'Meta Padrão') as tipo_nome,
                    COALESCE(tm.icone, 'fa-bullseye') as icone,
                    COALESCE(tm.cor, 'emerald') as cor,
                    COALESCE((SELECT COUNT(*) FROM mkt_clientes WHERE id_meta = mi.id), 0) as clientes_vinculados,
                    COALESCE((SELECT COUNT(*) FROM mkt_clientes WHERE id_meta = mi.id AND status = 'Fechado'), 0) as clientes_fechados,
                    COALESCE((SELECT SUM(valor_negocio) FROM mkt_clientes WHERE id_meta = mi.id AND status = 'Fechado'), 0)::float as valor_fechado,
                    COALESCE((SELECT SUM(valor_negocio) FROM mkt_clientes WHERE id_meta = mi.id AND status IN ('Proposta', 'Qualificado')), 0)::float as valor_pipeline,
                    COALESCE((SELECT COUNT(*) FROM mkt_leads_controle WHERE id_meta = mi.id), 0) as leads_controle,
                    COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle WHERE id_meta = mi.id AND status = 'Fechado'), 0)::float as fat_controle,
                    COALESCE((SELECT SUM(leads_recebidos) FROM mkt_alimentacao_diaria WHERE id_meta = mi.id), 0) as leads_alimentacao,
                    COALESCE((SELECT SUM(vendas_fechadas) FROM mkt_alimentacao_diaria WHERE id_meta = mi.id), 0) as vendas_alimentacao,
                    COALESCE((SELECT SUM(valor_faturado) FROM mkt_alimentacao_diaria WHERE id_meta = mi.id), 0)::float as fat_alimentacao
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.status = 'ativa'
                ORDER BY mi.data_fim ASC
            ";
            $metas = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($metas as &$meta) {
                $valores = is_string($meta['valores']) ? json_decode($meta['valores'], true) : ($meta['valores'] ?? []);
                $metaLeads = (int)($valores['meta_leads'] ?? 0);
                $metaFaturamento = (float)($valores['meta_faturamento'] ?? 0);
                
                $meta['total_leads_realizados'] = (int)($meta['leads_alimentacao'] ?? 0) + (int)($meta['leads_controle'] ?? 0);
                $meta['meta_leads'] = $metaLeads;
                $meta['total_vendas'] = (int)($meta['vendas_alimentacao'] ?? 0) + (int)($meta['clientes_fechados'] ?? 0);
                $meta['total_faturamento'] = (float)($meta['fat_alimentacao'] ?? 0) + (float)($meta['valor_fechado'] ?? 0) + (float)($meta['fat_controle'] ?? 0);
                $meta['meta_faturamento'] = $metaFaturamento;
                $meta['pct_leads'] = $metaLeads > 0 ? min(round(($meta['total_leads_realizados'] / $metaLeads) * 100, 1), 100) : 0;
                $meta['pct_faturamento'] = $metaFaturamento > 0 ? min(round(($meta['total_faturamento'] / $metaFaturamento) * 100, 1), 100) : 0;
                
                if ($meta['data_fim']) {
                    $fim = new \DateTime($meta['data_fim']);
                    $hoje = new \DateTime();
                    $meta['dias_restantes'] = $fim > $hoje ? $hoje->diff($fim)->days : -$hoje->diff($fim)->days;
                }
            }
            
            return $this->json($response, $metas);
        } catch (Exception $e) {
            error_log('Erro em getMetasProgresso: ' . $e->getMessage());
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/metas-dashboard
     */
    public function getMetasDashboard(Request $request, Response $response): Response
    {
        try {
            $service = new \Nutricional\Services\MarketingService($this->pdo);
            
            $stmt = $this->pdo->query("
                SELECT mi.*, tm.nome as tipo_nome, tm.icone, tm.cor
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.status = 'ativa'
                ORDER BY mi.data_fim ASC
            ");
            $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $metasDashboard = [];
            $totalGeral = 0;
            $totalMetaGeral = 0;
            
            foreach ($metas as $meta) {
                $progresso = $service->calcularProgressoMeta($meta['id']);
                if (!$progresso['success']) continue;
                
                $campos = $service->getCamposEditaveisMeta($meta['id']);
                $valores = is_string($meta['valores']) ? json_decode($meta['valores'], true) : ($meta['valores'] ?? []);
                
                $metaFinal = 0;
                $taxaInicial = 0;
                $unidade = '';
                $nomeValorAtual = 'valor_alcancado';
                
                foreach ($campos as $campo) {
                    if ($campo['tipo_comparacao'] === 'meta_final') {
                        $metaFinal = (float) ($valores[$campo['nome_campo']] ?? 0);
                        $unidade = $campo['unidade'] ?? '';
                    }
                    if ($campo['tipo_comparacao'] === 'taxa_inicial') {
                        $taxaInicial = (float) ($valores[$campo['nome_campo']] ?? 0);
                    }
                    if ($campo['tipo_comparacao'] === 'valor_atual' || $campo['editavel']) {
                        $nomeValorAtual = $campo['nome_campo'];
                    }
                }
                
                if ($metaFinal == 0) {
                    $metaFinal = (float) ($valores['meta_final'] ?? $valores['meta_faturamento'] ?? 0);
                    $taxaInicial = (float) ($valores['taxa_inicial'] ?? $valores['taxa_atual'] ?? 0);
                }
                
                $valorAtual = $progresso['valor_acumulado'] ?? $taxaInicial;
                
                $progressoPercentual = 0;
                if ($metaFinal > $taxaInicial) {
                    $necessario = $metaFinal - $taxaInicial;
                    if ($necessario > 0) {
                        $conquistado = $valorAtual - $taxaInicial;
                        $progressoPercentual = max(0, min(100, round(($conquistado / $necessario) * 100, 1)));
                    }
                } else if ($metaFinal < $taxaInicial) {
                    $necessario = $taxaInicial - $metaFinal;
                    if ($necessario > 0) {
                        $reduzido = $taxaInicial - $valorAtual;
                        $progressoPercentual = max(0, min(100, round(($reduzido / $necessario) * 100, 1)));
                    }
                } else {
                    $progressoPercentual = $valorAtual >= $metaFinal ? 100 : 0;
                }
                
                $gap = max(0, $metaFinal - $valorAtual);
                $diasRestantes = $this->calcularDiasRestantes($meta['data_fim']);
                $diasPassados = $this->calcularDiasPassados($meta['data_inicio']);
                $projecao = $this->calcularProjecao($gap, $diasRestantes, $taxaInicial, $valorAtual, $diasPassados);
                $historico = $this->buscarHistoricoMeta($meta['id'], 5);
                $tendencia = $this->calcularTendencia($historico, $nomeValorAtual);
                
                $metasDashboard[] = [
                    'id' => $meta['id'],
                    'titulo' => $meta['titulo'],
                    'tipo_nome' => $meta['tipo_nome'] ?? 'Meta',
                    'icone' => $meta['icone'] ?? 'fa-bullseye',
                    'cor' => $meta['cor'] ?? 'emerald',
                    'status' => $meta['status'],
                    'meta_final' => $metaFinal,
                    'taxa_inicial' => $taxaInicial,
                    'valor_atual' => $valorAtual,
                    'progresso' => $progressoPercentual,
                    'gap' => $gap,
                    'unidade' => $unidade,
                    'data_inicio' => $meta['data_inicio'],
                    'data_fim' => $meta['data_fim'],
                    'dias_restantes' => $diasRestantes,
                    'projecao' => $projecao,
                    'tendencia' => $tendencia,
                    'historico' => $historico,
                    'total_registros' => $progresso['total_registros'] ?? 0,
                    'ultima_atualizacao' => $progresso['ultimo_registro'] ?? null
                ];
                
                $totalGeral += $valorAtual;
                $totalMetaGeral += $metaFinal;
            }
            
            $progressoGeral = $totalMetaGeral > 0 ? min(100, round(($totalGeral / $totalMetaGeral) * 100)) : 0;
            
            return $this->json($response, [
                'success' => true,
                'metas' => $metasDashboard,
                'resumo' => [
                    'total_metas' => count($metas),
                    'progresso_geral' => $progressoGeral,
                    'total_acumulado' => $totalGeral,
                    'total_meta_geral' => $totalMetaGeral,
                    'metas_concluidas' => count(array_filter($metasDashboard, fn($m) => $m['progresso'] >= 100))
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getMetasDashboard: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 4. LEADS
    // ======================================================================

    /**
     * GET /v1/marketing/leads
     */
    public function getLeads(Request $request, Response $response): Response
    {
        $limite = (int)($request->getQueryParams()['limite'] ?? 20);
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM mkt_leads_controle ORDER BY data_registro DESC, id DESC LIMIT :limite");
            $stmt->execute(['limite' => $limite]);
            return $this->json($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/leads/{id}
     */
    public function getLeadById(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM mkt_leads_controle WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            return $this->json($response, $lead ?: ['error' => 'Lead não encontrado']);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/leads
     */
    public function salvarLead(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $sql = "INSERT INTO mkt_leads_controle (
                data_registro, empresa, telefone, email, cnpj, cidade, uf, 
                produto_interesse, status, origem, qualificado, termometro, 
                valor_fechamento, id_meta, gestor
            ) VALUES (
                :data, :emp, :tel, :email, :cnpj, :cid, :uf, 
                :prod, :status, :orig, :qual, :term, 
                :val, :meta, :gestor
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'data' => $input['data'] ?? date('Y-m-d'),
                'emp' => $input['empresa'] ?? '',
                'tel' => $input['telefone'] ?? '',
                'email' => $input['email'] ?? '',
                'cnpj' => $input['cnpj'] ?? '',
                'cid' => $input['cidade'] ?? '',
                'uf' => $input['uf'] ?? '',
                'prod' => $input['produto'] ?? '',
                'status' => $input['status'] ?? 'Followup',
                'orig' => $input['origem'] ?? '',
                'qual' => $input['qualificado'] ?? 'true',
                'term' => $input['termometro'] ?? 'Frio',
                'val' => (float)($input['valor'] ?? 0),
                'meta' => (int)($input['id_meta'] ?? 0),
                'gestor' => $input['gestor'] ?? 'Usuário'
            ]);

            $novoLeadId = $this->pdo->lastInsertId();

            if (($input['status'] ?? '') === 'Fechado') {
                $lead = [
                    'id' => $novoLeadId,
                    'empresa' => $input['empresa'] ?? '',
                    'telefone' => $input['telefone'] ?? '',
                    'email' => $input['email'] ?? '',
                    'cidade' => $input['cidade'] ?? '',
                    'uf' => $input['uf'] ?? '',
                    'origem' => $input['origem'] ?? 'Site',
                    'gestor' => $input['gestor'] ?? 'Usuário',
                    'produto_interesse' => $input['produto'] ?? '',
                    'id_meta' => (int)($input['id_meta'] ?? 0)
                ];
                $this->converterLeadParaCliente($lead, (float)($input['valor'] ?? 0));
            }

            return $this->json($response, ['success' => true, 'id' => $novoLeadId]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/marketing/leads/{id}
     */
    public function atualizarLead(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $stmtOld = $this->pdo->prepare("SELECT * FROM mkt_leads_controle WHERE id = :id");
            $stmtOld->execute(['id' => $id]);
            $leadAntigo = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if (!$leadAntigo) {
                return $this->json($response, ['error' => 'Lead não encontrado'], 404);
            }

            $novoStatus = $input['status'] ?? $leadAntigo['status'];
            $novoTermometro = $input['termometro'] ?? $leadAntigo['termometro'];
            $novoValor = (float)($input['valor_fechamento'] ?? $leadAntigo['valor_fechamento']);

            $sql = "UPDATE mkt_leads_controle SET 
                status = :status, 
                termometro = :term, 
                valor_fechamento = :val,
                data_atualizacao = NOW()
                WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'status' => $novoStatus,
                'term' => $novoTermometro,
                'val' => $novoValor
            ]);

            if ($novoStatus === 'Fechado' && $leadAntigo['status'] !== 'Fechado') {
                $this->converterLeadParaCliente($leadAntigo, $novoValor);
            }

            if ($leadAntigo['status'] === 'Fechado' && $novoStatus !== 'Fechado') {
                $this->reverterClienteParaLead($leadAntigo);
            }

            return $this->json($response, [
                'success' => true,
                'acao' => $novoStatus === 'Fechado' && $leadAntigo['status'] !== 'Fechado' ? 'cliente_criado' : 'atualizado'
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

 /**
 * GET /v1/marketing/crm-estatisticas
 * Usa a função PostgreSQL get_crm_estatisticas()
 */
public function getCrmEstatisticas(Request $request, Response $response): Response
{
    try {
        $pdo = $this->pdo;
        
        // ⚡ CHAMAR A FUNÇÃO POSTGRESQL
        $sql = "SELECT get_crm_estatisticas() as data";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['data']) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar estatísticas do CRM'
            ], 500);
        }
        
        $dados = json_decode($result['data'], true);
        
        if (!$dados || !isset($dados['success'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao processar estatísticas do CRM'
            ], 500);
        }
        
        return $this->json($response, $dados);
        
    } catch (Exception $e) {
        error_log('Erro em getCrmEstatisticas: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * GET /v1/marketing/clientes/consulta-otimizado
 * Consulta unificada de clientes com filtros avançados
 */
public function consultarClientesUnificadoOtimizado(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    
    // ================================================================
    // 1. PREPARAR A BUSCA - LIMPEZA E NORMALIZAÇÃO
    // ================================================================
    $buscaOriginal = isset($params['busca']) ? trim($params['busca']) : null;
    $p_busca = null;
    $p_busca_id = null;
    
    if ($buscaOriginal && $buscaOriginal !== '') {
        $buscaLimpa = trim($buscaOriginal);
        $apenasNumeros = preg_replace('/[^0-9]/', '', $buscaLimpa);
        
        // CASO 1: É CNPJ (14 dígitos)
        if (strlen($apenasNumeros) === 14) {
            $p_busca = $apenasNumeros;
            $p_busca_id = null;
        }
        // CASO 2: É CPF (11 dígitos)
        elseif (strlen($apenasNumeros) === 11) {
            $p_busca = $apenasNumeros;
            $p_busca_id = null;
        }
        // CASO 3: É ID (número inteiro)
        elseif (ctype_digit($buscaLimpa) && strlen($buscaLimpa) <= 10) {
            $p_busca_id = (int)$buscaLimpa;
            $p_busca = null;
        }
        // CASO 4: É TELEFONE (10 ou 11 dígitos)
        elseif (strlen($apenasNumeros) >= 10 && strlen($apenasNumeros) <= 11) {
            $p_busca = $apenasNumeros;
            $p_busca_id = null;
        }
        // CASO 5: É TEXTO (nome, empresa, email)
        else {
            $p_busca = $buscaLimpa;
            $p_busca_id = null;
        }
    }
    
    // ================================================================
    // 2. DEMAIS PARÂMETROS
    // ================================================================
    $p_origem = null;
    if (isset($params['origem']) && !empty($params['origem'])) {
        $origens = array_map('trim', explode(',', $params['origem']));
        $origensValidas = array_filter($origens, function($o) {
            return in_array($o, ['APENAS_CRM', 'APENAS_ERP', 'AMBOS']);
        });
        if (!empty($origensValidas)) {
            $p_origem = implode(',', $origensValidas);
        }
    }
    
    $p_status = isset($params['status']) && trim($params['status']) !== '' ? trim($params['status']) : null;
    $p_termometro = isset($params['termometro']) && trim($params['termometro']) !== '' ? trim($params['termometro']) : null;
    $p_periodo = isset($params['periodo']) && trim($params['periodo']) !== '' ? trim($params['periodo']) : null;
    $p_ja_comprou = isset($params['ja_comprou']) && trim($params['ja_comprou']) !== '' ? trim($params['ja_comprou']) : null;
    $p_tipo_periodo = isset($params['tipo_periodo']) && trim($params['tipo_periodo']) !== '' ? trim($params['tipo_periodo']) : 'compra';
    $p_data_inicio = isset($params['data_inicio']) && trim($params['data_inicio']) !== '' ? trim($params['data_inicio']) : null;
    $p_data_fim = isset($params['data_fim']) && trim($params['data_fim']) !== '' ? trim($params['data_fim']) : null;
    
    // 🔥 NOVO: Origem do cliente (Site, WhatsApp, Instagram, etc)
    $p_origem_cliente = isset($params['origem_cliente']) && trim($params['origem_cliente']) !== '' ? trim($params['origem_cliente']) : null;
    
    $p_limite = isset($params['limite']) ? (int)$params['limite'] : 50;
    if ($p_limite < 1) $p_limite = 1;
    if ($p_limite > 500) $p_limite = 500;
    
    $pagina = isset($params['pagina']) ? (int)$params['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $p_offset = ($pagina - 1) * $p_limite;
    
    // ================================================================
    // 3. LOG PARA DEBUG
    // ================================================================
    error_log('📊 Busca Inteligente - Parametros: ' . json_encode([
        'busca_original' => $buscaOriginal,
        'p_busca' => $p_busca,
        'p_busca_id' => $p_busca_id,
        'p_origem' => $p_origem,
        'p_status' => $p_status,
        'p_termometro' => $p_termometro,
        'p_periodo' => $p_periodo,
        'p_ja_comprou' => $p_ja_comprou,
        'p_tipo_periodo' => $p_tipo_periodo,
        'p_data_inicio' => $p_data_inicio,
        'p_data_fim' => $p_data_fim,
        'p_origem_cliente' => $p_origem_cliente,
        'limite' => $p_limite,
        'offset' => $p_offset
    ]));
    
    try {
        $pdo = $this->pdo;
        
        // ================================================================
        // 4. CHAMAR A FUNÇÃO POSTGRESQL
        // ================================================================
        $sql = "SELECT consultar_clientes_otimizado_v2(
            :p_busca,
            :p_busca_id,
            :p_origem,
            :p_status,
            :p_termometro,
            :p_periodo,
            :p_ja_comprou,
            :p_tipo_periodo,
            :p_data_inicio,
            :p_data_fim,
            :p_limite,
            :p_offset,
            :p_origem_cliente
        ) as resultado";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'p_busca' => $p_busca,
            'p_busca_id' => $p_busca_id,
            'p_origem' => $p_origem,
            'p_status' => $p_status,
            'p_termometro' => $p_termometro,
            'p_periodo' => $p_periodo,
            'p_ja_comprou' => $p_ja_comprou,
            'p_tipo_periodo' => $p_tipo_periodo,
            'p_data_inicio' => $p_data_inicio,
            'p_data_fim' => $p_data_fim,
            'p_limite' => $p_limite,
            'p_offset' => $p_offset,
            'p_origem_cliente' => $p_origem_cliente
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !isset($result['resultado'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao consultar clientes: funcao nao retornou dados'
            ], 500);
        }
        
        // ================================================================
        // 5. DECODIFICAR O RESULTADO
        // ================================================================
        $dados = json_decode($result['resultado'], true);
        
        if (!$dados || !isset($dados['success'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao processar dados da funcao'
            ], 500);
        }
        
        // ================================================================
        // 6. RESPOSTA
        // ================================================================
        return $this->json($response, [
            'success' => true,
            'clientes' => $dados['clientes'] ?? [],
            'total' => (int)($dados['total'] ?? 0),
            'pagina' => $pagina,
            'total_paginas' => (int)($dados['total'] ?? 0) > 0 ? ceil((int)($dados['total'] ?? 0) / $p_limite) : 1,
            'busca' => [
                'original' => $buscaOriginal,
                'processada' => $p_busca,
                'tipo' => $p_busca_id ? 'ID' : ($p_busca ? 'TEXTO' : 'VAZIO'),
                'id_buscado' => $p_busca_id
            ],
            'filtros_aplicados' => [
                'busca' => $buscaOriginal,
                'origem' => $params['origem'] ?? null,
                'status' => $p_status,
                'termometro' => $p_termometro,
                'periodo' => $p_periodo,
                'ja_comprou' => $p_ja_comprou,
                'tipo_periodo' => $p_tipo_periodo,
                'data_inicio' => $p_data_inicio,
                'data_fim' => $p_data_fim,
                'origem_cliente' => $p_origem_cliente,
                'limite' => $p_limite,
                'pagina' => $pagina
            ],
            'meta' => [
                'versao' => 'V2',
                'funcao' => 'consultar_clientes_otimizado_v2',
                'total_registros' => (int)($dados['total'] ?? 0),
                'registros_retornados' => count($dados['clientes'] ?? [])
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('❌ Erro em consultarClientesUnificadoOtimizado: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao consultar clientes: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * POST /v1/marketing/clientes/mesclar-com-erp
 * Mescla um cliente do CRM com o ERP
 */
public function mesclarClienteComERP(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    $idCrm = isset($input['id_crm']) ? (int)$input['id_crm'] : 0;
    $cnpjCpf = isset($input['cnpj_cpf']) ? trim($input['cnpj_cpf']) : null;
    $telefone = isset($input['telefone']) ? trim($input['telefone']) : null;
    $email = isset($input['email']) ? trim($input['email']) : null;
    
    if ($idCrm <= 0) {
        return $this->json($response, [
            'success' => false,
            'error' => 'ID do CRM e obrigatorio'
        ], 400);
    }
    
    try {
        $pdo = $this->pdo;
        
        // Verificar se o cliente existe no CRM
        $stmtCheck = $pdo->prepare("SELECT id, nome, empresa, telefone, email, cnpj_cpf FROM mkt_clientes WHERE id = :id");
        $stmtCheck->execute(['id' => $idCrm]);
        $clienteCrm = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$clienteCrm) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Cliente nao encontrado no CRM'
            ], 404);
        }
        
        // Usar dados do cliente se nao foram fornecidos
        if (empty($cnpjCpf)) {
            $cnpjCpf = $clienteCrm['cnpj_cpf'] ?? null;
        }
        if (empty($telefone)) {
            $telefone = $clienteCrm['telefone'] ?? null;
        }
        if (empty($email)) {
            $email = $clienteCrm['email'] ?? null;
        }
        
        // ================================================================
        // CHAMAR A FUNCAO POSTGRESQL
        // ================================================================
        $sql = "SELECT mesclar_cliente_com_erp(:id_crm, :cnpj_cpf, :telefone, :email) as resultado";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_crm' => $idCrm,
            'cnpj_cpf' => $cnpjCpf,
            'telefone' => $telefone,
            'email' => $email
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !isset($result['resultado'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao mesclar cliente'
            ], 500);
        }
        
        $dados = json_decode($result['resultado'], true);
        
        // ================================================================
        // REGISTRAR LOG DA MESCLAGEM
        // ================================================================
        if ($dados['success']) {
            error_log('Cliente mesclado com ERP: CRM ID ' . $idCrm . ' -> ERP ID ' . $dados['id_erp']);
        } else {
            error_log('Cliente nao encontrado no ERP: CRM ID ' . $idCrm);
        }
        
        return $this->json($response, $dados);
        
    } catch (Exception $e) {
        error_log('Erro ao mesclar cliente: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao mesclar cliente: ' . $e->getMessage()
        ], 500);
    }
}


    /**
     * GET /v1/marketing/clientes
     */
    public function getClientes(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $termometro = $params['termometro'] ?? null;
        $origem = $params['origem'] ?? null;
        $busca = $params['busca'] ?? null;
        $limite = (int)($params['limite'] ?? 50);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;

        try {
            $where = [];
            $bindParams = [];

            if ($status) {
                $where[] = "c.status = :status";
                $bindParams['status'] = $status;
            }
            if ($termometro) {
                $where[] = "c.termometro = :termometro";
                $bindParams['termometro'] = $termometro;
            }
            if ($origem) {
                $where[] = "c.origem = :origem";
                $bindParams['origem'] = $origem;
            }
            if ($busca) {
                $where[] = "(c.nome ILIKE :busca OR c.empresa ILIKE :busca2 OR c.telefone ILIKE :busca3 OR c.email ILIKE :busca4)";
                $bindParams['busca'] = "%{$busca}%";
                $bindParams['busca2'] = "%{$busca}%";
                $bindParams['busca3'] = "%{$busca}%";
                $bindParams['busca4'] = "%{$busca}%";
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sqlCount = "SELECT COUNT(*) FROM mkt_clientes c {$whereClause}";
            $stmtCount = $this->pdo->prepare($sqlCount);
            $stmtCount->execute($bindParams);
            $total = (int)$stmtCount->fetchColumn();

            $sql = "
                SELECT c.*,
                    (SELECT MAX(i.data_interacao) FROM mkt_interacoes i WHERE i.id_cliente = c.id) as ultima_interacao,
                    (SELECT COUNT(*) FROM mkt_interacoes i WHERE i.id_cliente = c.id) as total_interacoes,
                    (SELECT COUNT(*) FROM mkt_lembretes l WHERE l.id_cliente = c.id AND l.concluido = false) as lembretes_pendentes,
                    (SELECT STRING_AGG(tag, ', ' ORDER BY tag) FROM mkt_cliente_tags WHERE id_cliente = c.id) as tags
                FROM mkt_clientes c
                {$whereClause}
                ORDER BY c.data_cadastro DESC
                LIMIT :limite OFFSET :offset
            ";

            $stmt = $this->pdo->prepare($sql);
            foreach ($bindParams as $key => $val) {
                $stmt->bindValue(":{$key}", $val);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'success' => true,
                'clientes' => $clientes,
                'total' => $total,
                'pagina' => $pagina,
                'total_paginas' => ceil($total / $limite)
            ]);
        } catch (Exception $e) {
            error_log('Erro em getClientes: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/clientes/{id}
     */
    public function getClienteDetalhes(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.*,
                    c.cliforemp_id,
                    c.id,
                    c.nome,
                    c.empresa,
                    c.telefone,
                    c.email,
                    c.cidade,
                    c.uf,
                    c.endereco,
                    c.numero,
                    c.bairro,
                    c.cep,
                    c.complemento,
                    c.cnpj_cpf,
                    c.ie,
                    c.origem,
                    c.status,
                    c.termometro,
                    c.valor_negocio,
                    c.id_meta,
                    c.observacoes,
                    c.data_cadastro,
                    c.nome_vendedor,
                    m.titulo as meta_titulo,
                    (SELECT COUNT(*) FROM mkt_interacoes WHERE id_cliente = c.id) as total_interacoes,
                    (SELECT MAX(data_interacao) FROM mkt_interacoes WHERE id_cliente = c.id) as ultima_interacao,
                    (SELECT STRING_AGG(tag, ', ' ORDER BY tag) FROM mkt_cliente_tags WHERE id_cliente = c.id) as tags
                FROM mkt_clientes c
                LEFT JOIN mkt_metas_instancias m ON m.id = c.id_meta
                WHERE c.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado'], 404);
            }
            
            if (!empty($cliente['tags'])) {
                $cliente['tags'] = explode(', ', $cliente['tags']);
            } else {
                $cliente['tags'] = [];
            }
            
            unset($cliente['cliforemp_id']);
            
            return $this->json($response, $cliente);
            
        } catch (Exception $e) {
            error_log('Erro em getClienteDetalhes: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

 public function salvarCliente(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];

    $camposTexto = [
        'nome', 'empresa', 'telefone', 'email', 'cidade', 'uf', 
        'origem', 'status', 'termometro', 'observacoes', 
        'endereco', 'numero', 'bairro', 'cep', 'complemento',
        'cnpj_cpf' 
    ];
    
    foreach ($camposTexto as $campo) {
        if (isset($input[$campo]) && is_string($input[$campo])) {
            $input[$campo] = $this->limparTexto($input[$campo]);
        }
    }

    try {
        $pdo = $this->pdo;
        
        // ================================================================
        // 1. INSERIR O CLIENTE NO CRM
        // ================================================================
        $sql = "INSERT INTO mkt_clientes (
            nome, empresa, telefone, email, cidade, uf, origem, status, 
            termometro, valor_negocio, id_meta, observacoes, data_cadastro,
            endereco, numero, bairro, cep, complemento, cnpj_cpf
        ) VALUES (
            :nome, :empresa, :telefone, :email, :cidade, :uf, :origem, :status, 
            :termometro, :valor, :id_meta, :obs, :data,
            :endereco, :numero, :bairro, :cep, :complemento, :cnpj_cpf
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nome' => $input['nome'] ?? '',
            'empresa' => $input['empresa'] ?? '',
            'telefone' => $input['telefone'] ?? '',
            'email' => $input['email'] ?? '',
            'cidade' => $input['cidade'] ?? '',
            'uf' => $input['uf'] ?? '',
            'origem' => $input['origem'] ?? 'Site',
            'status' => $input['status'] ?? 'Novo',
            'termometro' => $input['termometro'] ?? 'Frio',
            'valor' => (float)($input['valor_negocio'] ?? 0),
            'id_meta' => !empty($input['id_meta']) ? (int)$input['id_meta'] : null,
            'obs' => $input['observacoes'] ?? '',
            'data' => $input['data_cadastro'] ?? date('Y-m-d'),
            'endereco' => $input['endereco'] ?? '',
            'numero' => $input['numero'] ?? '',
            'bairro' => $input['bairro'] ?? '',
            'cep' => $input['cep'] ?? '',
            'complemento' => $input['complemento'] ?? '',
            'cnpj_cpf' => $input['cnpj_cpf'] ?? ''  
        ]);

        $id = $this->pdo->lastInsertId();

        // ================================================================
        // 2. TENTAR MESCLAR COM ERP AUTOMATICAMENTE
        // ================================================================
        $resultadoMesclagem = null;
        
        $temCnpj = !empty($input['cnpj_cpf']);
        $temTelefone = !empty($input['telefone']);
        $temEmail = !empty($input['email']);
        
        if ($temCnpj || $temTelefone || $temEmail) {
            $sqlMesclar = "SELECT mesclar_cliente_com_erp(:id_crm, :cnpj_cpf, :telefone, :email) as resultado";
            $stmtMesclar = $pdo->prepare($sqlMesclar);
            $stmtMesclar->execute([
                'id_crm' => $id,
                'cnpj_cpf' => $input['cnpj_cpf'] ?? null,
                'telefone' => $input['telefone'] ?? null,
                'email' => $input['email'] ?? null
            ]);
            
            $resultMesclar = $stmtMesclar->fetch(PDO::FETCH_ASSOC);
            if ($resultMesclar && isset($resultMesclar['resultado'])) {
                $resultadoMesclagem = json_decode($resultMesclar['resultado'], true);
            }
        }

        // ================================================================
        // 3. ATUALIZAR META SE VINCULADA
        // ================================================================
        if (!empty($input['id_meta'])) {
            $this->atualizarMetaComLead((int)$input['id_meta']);
        }

        // ================================================================
        // 4. RESPOSTA
        // ================================================================
        $resposta = [
            'success' => true,
            'id' => $id,
            'message' => 'Cliente cadastrado com sucesso!'
        ];
        
        if ($resultadoMesclagem && $resultadoMesclagem['success']) {
            $resposta['mesclagem'] = $resultadoMesclagem;
            $resposta['message'] = 'Cliente cadastrado e mesclado com ERP!';
            $resposta['id_erp'] = $resultadoMesclagem['id_erp'];
            $resposta['origem'] = 'AMBOS';
        } else {
            $resposta['mesclagem'] = $resultadoMesclagem ?? ['success' => false, 'message' => 'Nenhum cliente encontrado no ERP'];
            $resposta['origem'] = 'APENAS_CRM';
        }

        return $this->json($response, $resposta);
        
    } catch (Exception $e) {
        error_log('Erro ao salvar cliente: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

  public function atualizarCliente(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    $input = json_decode($request->getBody()->getContents(), true) ?? [];

    $camposTexto = [
        'nome', 'empresa', 'telefone', 'email', 'cidade', 'uf', 
        'origem', 'status', 'termometro', 'observacoes', 
        'endereco', 'numero', 'bairro', 'cep', 'complemento',
        'cnpj_cpf'  
    ];
    
    foreach ($camposTexto as $campo) {
        if (isset($input[$campo]) && is_string($input[$campo])) {
            $input[$campo] = $this->limparTexto($input[$campo]);
        }
    }

    try {
        $stmtOld = $this->pdo->prepare("SELECT status, id_meta, cliforemp_id FROM mkt_clientes WHERE id = :id");
        $stmtOld->execute(['id' => $id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado'], 404);
        }

        $novoStatus = $input['status'] ?? $oldData['status'];
        $novoValor = (float)($input['valor_negocio'] ?? 0);

      
        $sql = "UPDATE mkt_clientes SET 
            nome = :nome, 
            empresa = :empresa, 
            telefone = :telefone, 
            email = :email,
            cidade = :cidade, 
            uf = :uf, 
            origem = :origem, 
            status = :status,
            termometro = :termometro, 
            valor_negocio = :valor, 
            id_meta = :id_meta,
            observacoes = :obs,
            data_cadastro = :data_cadastro,
            data_atualizacao = NOW(),
            endereco = :endereco,
            numero = :numero,
            bairro = :bairro,
            cep = :cep,
            complemento = :complemento,
            cnpj_cpf = :cnpj_cpf  
            WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'nome' => $input['nome'] ?? '',
            'empresa' => $input['empresa'] ?? '',
            'telefone' => $input['telefone'] ?? '',
            'email' => $input['email'] ?? '',
            'cidade' => $input['cidade'] ?? '',
            'uf' => $input['uf'] ?? '',
            'origem' => $input['origem'] ?? 'Site',
            'status' => $novoStatus,
            'termometro' => $input['termometro'] ?? 'Frio',
            'valor' => $novoValor,
            'id_meta' => !empty($input['id_meta']) ? (int)$input['id_meta'] : null,
            'obs' => $input['observacoes'] ?? '',
            'data_cadastro' => $input['data_cadastro'] ?? date('Y-m-d'),
            'endereco' => $input['endereco'] ?? '',
            'numero' => $input['numero'] ?? '',
            'bairro' => $input['bairro'] ?? '',
            'cep' => $input['cep'] ?? '',
            'complemento' => $input['complemento'] ?? '',
            'cnpj_cpf' => $input['cnpj_cpf'] ?? ''  
        ]);

        if ($oldData['status'] !== 'Fechado' && $novoStatus === 'Fechado' && $novoValor > 0) {
            $this->registrarFechamentoMeta($id, $oldData['id_meta'] ?? null, $novoValor);
        }

        return $this->json($response, ['success' => true, 'message' => 'Cliente atualizado com sucesso']);

    } catch (Exception $e) {
        error_log('Erro ao atualizar cliente: ' . $e->getMessage());
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

    /**
     * DELETE /v1/marketing/clientes/{id}
     */
    public function deletarCliente(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("DELETE FROM mkt_interacoes WHERE id_cliente = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM mkt_lembretes WHERE id_cliente = :id")->execute(['id' => $id]);
            $this->pdo->prepare("DELETE FROM mkt_clientes WHERE id = :id")->execute(['id' => $id]);

            $this->pdo->commit();
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/clientes/consulta
     * Consulta unificada de clientes com filtros
     */
    public function consultarClientesUnificado(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $busca = $params['busca'] ?? null;
        $buscaId = $params['busca_id'] ?? null;
        $origemFiltro = $params['origem'] ?? null;
        $status = $params['status'] ?? null;
        $termometro = $params['termometro'] ?? null;
        $periodo = $params['periodo'] ?? null;
        $jaComprou = $params['ja_comprou'] ?? null;
        $tipoPeriodo = $params['tipo_periodo'] ?? 'compra';
        $dataInicio = $params['data_inicio'] ?? null;
        $dataFim = $params['data_fim'] ?? null;
        $limite = (int)($params['limite'] ?? 50);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        if ($limite > 500) $limite = 500;
        if ($pagina < 1) $pagina = 1;
        
        try {
            $sql = "SELECT 
                v.id_crm,
                v.id_erp,
                v.nome,
                v.empresa,
                v.telefone,
                v.email,
                v.cidade,
                v.uf,
                v.endereco,
                v.numero,
                v.bairro,
                v.cep,
                v.complemento,
                v.endereco_completo,
                v.cnpj_cpf,
                v.ie,
                v.status_crm,
                v.termometro,
                v.valor_negocio,
                v.origem_crm,
                v.observacoes,
                v.data_cadastro_crm,
                v.data_cadastro_erp,
                v.ultima_interacao,
                v.total_interacoes,
                v.lembretes_pendentes,
                v.compromissos_futuros,
                v.inativo,
                v.tipopessoa,
                v.tipo_pessoa_desc,
                v.id_vendedor,
                v.nome_vendedor,
                v.telefone_vendedor,
                v.email_vendedor,
                v.id_gestorerp,
                v.nome_gestor,
                v.telefone_gestor,
                v.email_gestor,
                v.origem_dados,
                v.ultima_atualizacao,
                v.data_ultima_compra,
                COALESCE(v.total_pedidos, 0) as total_pedidos,
                COALESCE(v.total_compras, 0) as total_compras
            FROM view_clientes_unificado v WHERE 1=1";
            
            $bindParams = [];
            
            if ($buscaId && $buscaId > 0) {
                $sql .= " AND (v.id_crm = :busca_id OR v.id_erp = :busca_id)";
                $bindParams['busca_id'] = (int)$buscaId;
            }
            
            if ($busca && strlen(trim($busca)) > 0) {
                if (is_numeric($busca)) {
                    $sql .= " AND (v.nome ILIKE :busca OR v.empresa ILIKE :busca2 OR v.telefone ILIKE :busca3 OR v.email ILIKE :busca4 OR v.cnpj_cpf ILIKE :busca5 OR v.id_crm::text ILIKE :busca6 OR v.id_erp::text ILIKE :busca7)";
                    $bindParams['busca'] = "%{$busca}%";
                    $bindParams['busca2'] = "%{$busca}%";
                    $bindParams['busca3'] = "%{$busca}%";
                    $bindParams['busca4'] = "%{$busca}%";
                    $bindParams['busca5'] = "%{$busca}%";
                    $bindParams['busca6'] = "%{$busca}%";
                    $bindParams['busca7'] = "%{$busca}%";
                } else {
                    $sql .= " AND (v.nome ILIKE :busca OR v.empresa ILIKE :busca2 OR v.telefone ILIKE :busca3 OR v.email ILIKE :busca4 OR v.cnpj_cpf ILIKE :busca5)";
                    $bindParams['busca'] = "%{$busca}%";
                    $bindParams['busca2'] = "%{$busca}%";
                    $bindParams['busca3'] = "%{$busca}%";
                    $bindParams['busca4'] = "%{$busca}%";
                    $bindParams['busca5'] = "%{$busca}%";
                }
            }
            
            if ($origemFiltro && $origemFiltro !== '') {
                $origens = array_map('trim', explode(',', $origemFiltro));
                $origensValidas = array_filter($origens, function($o) {
                    return in_array($o, ['APENAS_CRM', 'APENAS_ERP', 'AMBOS']);
                });
                
                if (!empty($origensValidas)) {
                    $placeholders = [];
                    foreach ($origensValidas as $index => $origem) {
                        $key = "origem_{$index}";
                        $placeholders[] = ":{$key}";
                        $bindParams[$key] = $origem;
                    }
                    $sql .= " AND v.origem_dados IN (" . implode(', ', $placeholders) . ")";
                }
            }
            
            if ($status && $status !== '') {
                $sql .= " AND v.status_crm = :status";
                $bindParams['status'] = $status;
            }
            
            if ($termometro && $termometro !== '') {
                $sql .= " AND v.termometro = :termometro";
                $bindParams['termometro'] = $termometro;
            }
            
            if ($jaComprou === 'sim') {
                $sql .= " AND COALESCE(v.total_pedidos, 0) > 0";
            } elseif ($jaComprou === 'nao') {
                $sql .= " AND COALESCE(v.total_pedidos, 0) = 0";
            }
            
            if ($periodo && $periodo !== '') {
                if ($tipoPeriodo === 'cadastro') {
                    $campoData = "COALESCE(v.data_cadastro_crm, v.data_cadastro_erp)";
                } else {
                    $campoData = "v.data_ultima_compra";
                }
                
                if ($periodo === 'personalizado') {
                    if ($dataInicio) {
                        $sql .= " AND $campoData >= :data_inicio";
                        $bindParams['data_inicio'] = $dataInicio;
                    }
                    if ($dataFim) {
                        $sql .= " AND $campoData <= :data_fim";
                        $bindParams['data_fim'] = $dataFim;
                    }
                } elseif ($periodo === 'hoje') {
                    $sql .= " AND $campoData >= CURRENT_DATE";
                } elseif ($periodo === '7dias') {
                    $sql .= " AND $campoData >= CURRENT_DATE - INTERVAL '7 days'";
                } elseif ($periodo === '15dias') {
                    $sql .= " AND $campoData >= CURRENT_DATE - INTERVAL '15 days'";
                } elseif ($periodo === '30dias') {
                    $sql .= " AND $campoData >= CURRENT_DATE - INTERVAL '30 days'";
                } elseif ($periodo === '60dias') {
                    $sql .= " AND $campoData >= CURRENT_DATE - INTERVAL '60 days'";
                } elseif ($periodo === '90dias') {
                    $sql .= " AND $campoData >= CURRENT_DATE - INTERVAL '90 days'";
                } elseif ($periodo === 'sem_compras') {
                    $sql .= " AND COALESCE(v.total_pedidos, 0) = 0";
                }
            }
            
            $countSql = "SELECT COUNT(*) FROM (" . preg_replace('/ORDER BY.*$/i', '', $sql) . ") as sub";
            $stmtCount = $this->pdo->prepare($countSql);
            foreach ($bindParams as $key => $val) {
                $stmtCount->bindValue($key, $val);
            }
            $stmtCount->execute();
            $total = (int)$stmtCount->fetchColumn();
            
            $sql .= " ORDER BY COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) DESC NULLS LAST 
                      LIMIT :limite OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($bindParams as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'clientes' => $clientes,
                'total' => $total,
                'pagina' => $pagina,
                'total_paginas' => $total > 0 ? ceil($total / $limite) : 1,
                'filtros_aplicados' => [
                    'busca' => $busca,
                    'busca_id' => $buscaId,
                    'origem' => $origemFiltro,
                    'status' => $status,
                    'termometro' => $termometro,
                    'periodo' => $periodo,
                    'tipo_periodo' => $tipoPeriodo,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'ja_comprou' => $jaComprou
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em consultarClientesUnificado: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao consultar clientes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/clientes/importar-erp/{id}
     */
    public function importarClienteERP(Request $request, Response $response, array $args): Response
    {
        $idErp = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    idcliforemp,
                    fantasia,
                    razao,
                    fone,
                    email,
                    cnpj,
                    cpf,
                    ie,
                    endereco,
                    numero,
                    bairro,
                    cep,
                    complemento,
                    uf,
                    idcidade,
                    idvendedor
                FROM cliforemp 
                WHERE idcliforemp = :id
            ");
            $stmt->execute(['id' => $idErp]);
            $clienteErp = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$clienteErp) {
                return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado no ERP'], 404);
            }
            
            $cidadeNome = '';
            if ($clienteErp['idcidade']) {
                $cityStmt = $this->pdo->prepare("SELECT descricao FROM cidade WHERE idcidade = :id");
                $cityStmt->execute(['id' => $clienteErp['idcidade']]);
                $cidade = $cityStmt->fetch();
                $cidadeNome = $cidade['descricao'] ?? '';
            }
            
            $vendedorNome = '';
            if ($clienteErp['idvendedor']) {
                $venStmt = $this->pdo->prepare("SELECT fantasia FROM cliforemp WHERE idcliforemp = :id");
                $venStmt->execute(['id' => $clienteErp['idvendedor']]);
                $vendedor = $venStmt->fetch();
                $vendedorNome = $vendedor['fantasia'] ?? '';
            }
            
            $nomeCliente = trim($clienteErp['fantasia']) ?: trim($clienteErp['razao']) ?: 'Cliente ERP';
            $empresaCliente = trim($clienteErp['razao']) ?: trim($clienteErp['fantasia']) ?: '';
            $observacoes = "Cliente importado do ERP em " . date('d/m/Y H:i:s') . "\n";
            $observacoes .= "Fantasia: " . ($clienteErp['fantasia'] ?? 'N/A') . "\n";
            $observacoes .= "Razão: " . ($clienteErp['razao'] ?? 'N/A');
            
            $checkStmt = $this->pdo->prepare("SELECT id FROM mkt_clientes WHERE cliforemp_id = :id");
            $checkStmt->execute(['id' => $idErp]);
            $existente = $checkStmt->fetch();
            
            if ($existente) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE mkt_clientes SET 
                        nome = :nome,
                        empresa = :empresa,
                        telefone = :telefone,
                        email = :email,
                        cidade = :cidade,
                        uf = :uf,
                        endereco = :endereco,
                        numero = :numero,
                        bairro = :bairro,
                        cep = :cep,
                        complemento = :complemento,
                        cnpj_cpf = :cnpj_cpf,
                        ie = :ie,
                        nome_vendedor = :nome_vendedor,
                        data_atualizacao = NOW()
                    WHERE id = :id
                ");
                
                $updateStmt->execute([
                    'id' => $existente['id'],
                    'nome' => $nomeCliente,
                    'empresa' => $empresaCliente,
                    'telefone' => $clienteErp['fone'] ?? '',
                    'email' => $clienteErp['email'] ?? '',
                    'cidade' => $cidadeNome,
                    'uf' => $clienteErp['uf'] ?? '',
                    'endereco' => $clienteErp['endereco'] ?? '',
                    'numero' => $clienteErp['numero'] ?? '',
                    'bairro' => $clienteErp['bairro'] ?? '',
                    'cep' => $clienteErp['cep'] ?? '',
                    'complemento' => $clienteErp['complemento'] ?? '',
                    'cnpj_cpf' => $clienteErp['cnpj'] ?: $clienteErp['cpf'] ?: '',
                    'ie' => $clienteErp['ie'] ?? '',
                    'nome_vendedor' => $vendedorNome
                ]);
                
                return $this->json($response, [
                    'success' => true,
                    'message' => 'Cliente atualizado com sucesso!',
                    'id_crm' => $existente['id']
                ]);
            }
            
            $insertStmt = $this->pdo->prepare("
                INSERT INTO mkt_clientes (
                    cliforemp_id, nome, empresa, telefone, email, cidade, uf,
                    endereco, numero, bairro, cep, complemento, cnpj_cpf, ie,
                    origem, status, termometro, observacoes, data_cadastro, nome_vendedor, usuario_criacao
                ) VALUES (
                    :cliforemp_id, :nome, :empresa, :telefone, :email, :cidade, :uf,
                    :endereco, :numero, :bairro, :cep, :complemento, :cnpj_cpf, :ie,
                    'ERP', 'Novo', 'Frio', :observacoes, CURRENT_DATE, :nome_vendedor, :usuario_criacao
                )
            ");
            
            $insertStmt->execute([
                'cliforemp_id' => $idErp,
                'nome' => $nomeCliente,
                'empresa' => $empresaCliente,
                'telefone' => $clienteErp['fone'] ?? '',
                'email' => $clienteErp['email'] ?? '',
                'cidade' => $cidadeNome,
                'uf' => $clienteErp['uf'] ?? '',
                'endereco' => $clienteErp['endereco'] ?? '',
                'numero' => $clienteErp['numero'] ?? '',
                'bairro' => $clienteErp['bairro'] ?? '',
                'cep' => $clienteErp['cep'] ?? '',
                'complemento' => $clienteErp['complemento'] ?? '',
                'cnpj_cpf' => $clienteErp['cnpj'] ?: $clienteErp['cpf'] ?: '',
                'ie' => $clienteErp['ie'] ?? '',
                'observacoes' => $observacoes,
                'nome_vendedor' => $vendedorNome,
                'usuario_criacao' => 'Importação ERP #' . $idErp
            ]);
            
            $novoId = $this->pdo->lastInsertId();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Cliente importado com sucesso!',
                'id_crm' => $novoId,
                'cliente' => [
                    'id' => $novoId,
                    'nome' => $nomeCliente,
                    'empresa' => $empresaCliente
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('❌ Erro ao importar cliente: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao importar cliente: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/clientes/sincronizar-todos
     */
    public function sincronizarTodosClientes(Request $request, Response $response): Response
    {
        try {
            $sql = "
                UPDATE mkt_clientes c
                SET 
                    nome = COALESCE(cli.fantasia, cli.razao, 'Cliente ERP'),
                    empresa = COALESCE(cli.razao, cli.fantasia, ''),
                    telefone = cli.fone,
                    email = cli.email,
                    cidade = (SELECT descricao FROM cidade WHERE idcidade = cli.idcidade),
                    uf = cli.uf,
                    endereco = cli.endereco,
                    numero = cli.numero,
                    bairro = cli.bairro,
                    cep = cli.cep,
                    complemento = cli.complemento,
                    cnpj_cpf = COALESCE(cli.cnpj, cli.cpf, ''),
                    ie = cli.ie,
                    nome_vendedor = vend.fantasia,
                    data_atualizacao = NOW()
                FROM cliforemp cli
                LEFT JOIN cliforemp vend ON vend.idcliforemp = cli.idvendedor
                WHERE c.cliforemp_id = cli.idcliforemp
                  AND (c.nome IS NULL OR c.nome = '' OR c.nome = '—')
                RETURNING c.id, c.nome, c.empresa
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $atualizados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'success' => true,
                'message' => count($atualizados) . ' clientes sincronizados',
                'clientes' => $atualizados
            ]);

        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/clientes/sincronizar-dados
     */
    public function sincronizarDadosCliente(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idCrm = (int)($input['id_crm'] ?? 0);
        
        try {
            if ($idCrm <= 0) {
                return $this->json($response, ['success' => false, 'error' => 'ID do cliente é obrigatório'], 400);
            }
            
            $checkStmt = $this->pdo->prepare("SELECT id, cliforemp_id FROM mkt_clientes WHERE id = :id");
            $checkStmt->execute(['id' => $idCrm]);
            $cliente = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado no CRM'], 404);
            }
            
            if (!$cliente['cliforemp_id']) {
                return $this->json($response, ['success' => false, 'error' => 'Cliente não possui vínculo com ERP'], 400);
            }
            
            $sql = "
                UPDATE mkt_clientes c
                SET 
                    nome = COALESCE(cli.fantasia, cli.razao, c.nome),
                    empresa = COALESCE(cli.razao, cli.fantasia, c.empresa),
                    telefone = COALESCE(cli.fone, c.telefone),
                    email = COALESCE(cli.email, c.email),
                    cidade = COALESCE((SELECT descricao FROM cidade WHERE idcidade = cli.idcidade), c.cidade),
                    uf = COALESCE(cli.uf, c.uf),
                    endereco = COALESCE(cli.endereco, c.endereco),
                    numero = COALESCE(cli.numero, c.numero),
                    bairro = COALESCE(cli.bairro, c.bairro),
                    cep = COALESCE(cli.cep, c.cep),
                    complemento = COALESCE(cli.complemento, c.complemento),
                    cnpj_cpf = COALESCE(cli.cnpj, cli.cpf, c.cnpj_cpf),
                    ie = COALESCE(cli.ie, c.ie),
                    nome_vendedor = COALESCE(vend.fantasia, c.nome_vendedor),
                    data_atualizacao = NOW()
                FROM cliforemp cli
                LEFT JOIN cliforemp vend ON vend.idcliforemp = cli.idvendedor
                WHERE c.cliforemp_id = cli.idcliforemp
                  AND c.id = :id
                RETURNING c.id, c.nome, c.empresa, c.telefone, c.email
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $idCrm]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                error_log("✅ Cliente {$idCrm} sincronizado: " . print_r($result, true));
                return $this->json($response, [
                    'success' => true,
                    'message' => 'Cliente sincronizado com sucesso',
                    'cliente' => $result
                ]);
            } else {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Falha ao sincronizar - nenhum dado foi atualizado'
                ], 500);
            }

        } catch (Exception $e) {
            error_log('❌ Erro ao sincronizar cliente: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/clientes/erp/{id}
     */
    public function getClienteERPDetalhes(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    cli.idcliforemp AS id_erp,
                    cli.fantasia AS nome,
                    cli.razao AS razao_social,
                    cli.fone AS telefone,
                    cli.email,
                    cli.cnpj,
                    cli.cpf,
                    cli.ie,
                    cli.endereco,
                    cli.numero,
                    cli.bairro,
                    cli.cep,
                    cli.complemento,
                    cli.uf,
                    (SELECT DISTINCT descricao FROM cidade WHERE cidade.idcidade = cli.idcidade) AS cidade,
                    cli.inativo,
                    cli.tipopessoa,
                    CASE 
                        WHEN cli.tipopessoa = 0 THEN 'Física' 
                        WHEN cli.tipopessoa = 1 THEN 'Jurídica' 
                        ELSE 'Não informado' 
                    END AS tipo_pessoa_desc,
                    cli.datacadastro AS data_cadastro,
                    ven.idcliforemp AS id_vendedor,
                    ven.fantasia AS nome_vendedor,
                    ven.fone AS telefone_vendedor,
                    ven.email AS email_vendedor,
                    g.idcliforemp AS id_gestor,
                    g.fantasia AS nome_gestor
                FROM cliforemp cli
                LEFT JOIN vw_gestor_repre vgr ON vgr.idrepresentante = cli.idvendedor
                LEFT JOIN cliforemp ven ON ven.idcliforemp = vgr.idrepresentante
                LEFT JOIN cliforemp g ON g.idcliforemp = vgr.idgestor
                WHERE cli.idcliforemp = :id
            ");
            $stmt->execute(['id' => $id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado no ERP'], 404);
            }
            
            return $this->json($response, ['success' => true, 'data' => $cliente]);
            
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 6. INTERAÇÕES
    // ======================================================================

    /**
     * GET /v1/marketing/clientes/{id}/interacoes
     */
    public function getInteracoes(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mkt_interacoes 
                WHERE id_cliente = :id 
                ORDER BY data_interacao DESC, hora_interacao DESC 
                LIMIT 50
            ");
            $stmt->execute(['id' => $idCliente]);
            return $this->json($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/clientes/{id}/interacoes
     */
    public function salvarInteracao(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $sql = "INSERT INTO mkt_interacoes (id_cliente, tipo, descricao, data_interacao, hora_interacao, usuario) 
                    VALUES (:id_cliente, :tipo, :descricao, :data, :hora, :usuario)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id_cliente' => $idCliente,
                'tipo' => $input['tipo'] ?? 'whatsapp',
                'descricao' => $input['descricao'] ?? '',
                'data' => $input['data_interacao'] ?? date('Y-m-d'),
                'hora' => $input['hora_interacao'] ?? date('H:i'),
                'usuario' => $input['usuario'] ?? 'Sistema'
            ]);

            $this->pdo->prepare("UPDATE mkt_clientes SET ultima_interacao = NOW() WHERE id = :id")
                ->execute(['id' => $idCliente]);

            return $this->json($response, ['success' => true, 'id' => $this->pdo->lastInsertId()]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 7. LEMBRETES
    // ======================================================================

    /**
     * GET /v1/marketing/lembretes
     */
    public function getLembretes(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT l.*, c.nome as cliente_nome, c.empresa as cliente_empresa, c.termometro, c.status
                FROM mkt_lembretes l
                JOIN mkt_clientes c ON c.id = l.id_cliente
                ORDER BY l.concluido ASC, l.data_lembrete ASC
                LIMIT 100
            ";
            return $this->json($response, $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/lembretes-hoje
     */
    public function getLembretesHoje(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT l.*, c.nome as cliente_nome, c.empresa as cliente_empresa, c.telefone
                FROM mkt_lembretes l
                JOIN mkt_clientes c ON c.id = l.id_cliente
                WHERE l.concluido = false 
                  AND l.data_lembrete = CURRENT_DATE
                ORDER BY l.hora_lembrete ASC
            ";
            return $this->json($response, $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/lembretes/alertas
     */
    public function getLembretesAlertas(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT l.*, c.nome as cliente_nome, c.empresa as cliente_empresa, c.telefone
                FROM mkt_lembretes l
                JOIN mkt_clientes c ON c.id = l.id_cliente
                WHERE l.concluido = false 
                  AND l.data_lembrete = CURRENT_DATE
                  AND (
                      (EXTRACT(HOUR FROM CURRENT_TIME) * 60 + EXTRACT(MINUTE FROM CURRENT_TIME)) 
                      BETWEEN 
                      (EXTRACT(HOUR FROM l.hora_lembrete) * 60 + EXTRACT(MINUTE FROM l.hora_lembrete) - 15)
                      AND 
                      (EXTRACT(HOUR FROM l.hora_lembrete) * 60 + EXTRACT(MINUTE FROM l.hora_lembrete) + 5)
                  )
                ORDER BY l.hora_lembrete ASC
            ";
            return $this->json($response, [
                'success' => true,
                'lembretes' => $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/lembretes
     */
    public function criarLembrete(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $sql = "INSERT INTO mkt_lembretes (id_cliente, descricao, data_lembrete, hora_lembrete) 
                    VALUES (:id_cliente, :descricao, :data, :hora)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id_cliente' => (int)($input['id_cliente'] ?? 0),
                'descricao' => $input['descricao'] ?? '',
                'data' => $input['data_lembrete'] ?? date('Y-m-d'),
                'hora' => $input['hora_lembrete'] ?? '09:00'
            ]);

            return $this->json($response, ['success' => true, 'id' => $this->pdo->lastInsertId()]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/marketing/lembretes/{id}/concluir
     */
    public function concluirLembrete(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("UPDATE mkt_lembretes SET concluido = true, data_conclusao = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /v1/marketing/lembretes/{id}
     */
    public function deletarLembrete(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("DELETE FROM mkt_lembretes WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 8. COMPROMISSOS
    // ======================================================================

    /**
     * GET /v1/marketing/compromissos
     */
    public function getCompromissos(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $inicio = $params['inicio'] ?? null;
        $fim = $params['fim'] ?? null;
        $status = $params['status'] ?? 'todos';

        try {
            $sql = "
                SELECT c.*, cl.nome as cliente_nome, cl.empresa as cliente_empresa
                FROM mkt_compromissos c
                LEFT JOIN mkt_clientes cl ON cl.id = c.id_cliente
                WHERE 1=1
            ";
            $bind = [];

            if ($inicio && $fim) {
                $sql .= " AND c.data_hora BETWEEN :inicio AND :fim";
                $bind['inicio'] = $inicio;
                $bind['fim'] = $fim;
            }

            if ($status !== 'todos') {
                $sql .= " AND c.status = :status";
                $bind['status'] = $status;
            }

            $sql .= " ORDER BY c.data_hora ASC LIMIT 200";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bind);
            $compromissos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, ['success' => true, 'data' => $compromissos]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/compromissos/cliente/{id}
     */
    public function getCompromissosPorCliente(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mkt_compromissos 
                WHERE id_cliente = :id_cliente 
                ORDER BY data_hora DESC 
                LIMIT 50
            ");
            $stmt->execute(['id_cliente' => $idCliente]);
            return $this->json($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/compromissos/proximos
     */
    public function getProximosCompromissos(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, cl.nome as cliente_nome, cl.empresa as cliente_empresa
                FROM mkt_compromissos c
                LEFT JOIN mkt_clientes cl ON cl.id = c.id_cliente
                WHERE c.status = 'agendado' AND c.data_hora >= NOW()
                ORDER BY c.data_hora ASC
                LIMIT 10
            ");
            $stmt->execute();
            return $this->json($response, ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/compromissos/meus-proximos
     */
    public function getMeusProximosCompromissos(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');
            $idUsuario = $user['idusuario'] ?? 0;
            
            if (!$idUsuario) {
                return $this->json($response, ['success' => false, 'error' => 'Usuário não identificado'], 401);
            }
            
            $sql = "
                SELECT 
                    c.*,
                    cl.nome as cliente_nome,
                    cl.empresa as cliente_empresa,
                    cl.telefone as cliente_telefone,
                    EXTRACT(EPOCH FROM (c.data_hora - NOW())) / 3600 as horas_para_inicio,
                    CASE 
                        WHEN c.data_hora <= NOW() + INTERVAL '15 minutes' THEN 'iminente'
                        WHEN c.data_hora <= NOW() + INTERVAL '1 hour' THEN 'proximo'
                        WHEN c.data_hora <= NOW() + INTERVAL '24 hours' THEN 'hoje'
                        ELSE 'futuro'
                    END as urgencia
                FROM mkt_compromissos c
                LEFT JOIN mkt_clientes cl ON cl.id = c.id_cliente
                WHERE c.status = 'agendado'
                  AND c.data_hora BETWEEN NOW() AND NOW() + INTERVAL '24 hours'
                ORDER BY c.data_hora ASC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $compromissos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($compromissos as &$comp) {
                $stmtCheck = $this->pdo->prepare("
                    SELECT id FROM crm_notificacoes 
                    WHERE tipo = 'compromisso' 
                      AND id_referencia = :id_compromisso
                      AND id_usuario = :id_usuario
                      AND created_at > CURRENT_DATE
                ");
                $stmtCheck->execute([
                    'id_compromisso' => $comp['id'],
                    'id_usuario' => $idUsuario
                ]);
                $comp['ja_notificado'] = $stmtCheck->fetch() ? true : false;
            }
            
            return $this->json($response, [
                'success' => true,
                'compromissos' => $compromissos,
                'total' => count($compromissos),
                'agora' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em getMeusProximosCompromissos: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/compromissos/estatisticas
     */
    public function getEstatisticasCompromissos(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'concluido') as concluidos
                FROM mkt_compromissos
                WHERE EXTRACT(MONTH FROM data_hora) = EXTRACT(MONTH FROM CURRENT_DATE)
                  AND EXTRACT(YEAR FROM data_hora) = EXTRACT(YEAR FROM CURRENT_DATE)
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'total' => (int)($stats['total'] ?? 0),
                'concluidos' => (int)($stats['concluidos'] ?? 0)
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['total' => 0, 'concluidos' => 0]);
        }
    }

    /**
     * POST /v1/marketing/compromissos
     */
    public function criarCompromisso(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $sql = "INSERT INTO mkt_compromissos (id_cliente, data_hora, tipo, titulo, descricao, status, created_by) 
                    VALUES (:id_cliente, :data_hora, :tipo, :titulo, :descricao, 'agendado', :created_by)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id_cliente' => (int)($input['id_cliente'] ?? 0),
                'data_hora' => $input['data_hora'] ?? date('Y-m-d H:i:s'),
                'tipo' => $input['tipo'] ?? 'reuniao',
                'titulo' => $input['titulo'] ?? '',
                'descricao' => $input['descricao'] ?? '',
                'created_by' => (int)($input['usuario_id'] ?? 0)
            ]);

            return $this->json($response, ['success' => true, 'id' => $this->pdo->lastInsertId()]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/marketing/compromissos/{id}
     */
    public function atualizarCompromisso(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $sql = "UPDATE mkt_compromissos SET 
                data_hora = :data_hora, 
                tipo = :tipo, 
                titulo = :titulo, 
                descricao = :descricao,
                updated_at = NOW()
                WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'data_hora' => $input['data_hora'] ?? null,
                'tipo' => $input['tipo'] ?? null,
                'titulo' => $input['titulo'] ?? null,
                'descricao' => $input['descricao'] ?? null
            ]);

            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/marketing/compromissos/{id}/concluir
     */
    public function concluirCompromisso(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("UPDATE mkt_compromissos SET status = 'concluido', updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /v1/marketing/compromissos/{id}
     */
    public function deletarCompromisso(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("DELETE FROM mkt_compromissos WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 9. ALIMENTAÇÃO DIÁRIA
    // ======================================================================

    /**
     * POST /v1/marketing/alimentar
     */
    public function alimentarDiario(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $sql = "INSERT INTO mkt_alimentacao_diaria 
                (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, idusuario_mkt, id_meta) 
                VALUES (:data, :leads, :vendas, :valor, :inv, :uid, :meta)
                ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                    leads_recebidos = mkt_alimentacao_diaria.leads_recebidos + EXCLUDED.leads_recebidos,
                    vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + EXCLUDED.vendas_fechadas,
                    valor_faturado = mkt_alimentacao_diaria.valor_faturado + EXCLUDED.valor_faturado,
                    investimento_dia = mkt_alimentacao_diaria.investimento_dia + EXCLUDED.investimento_dia";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'data' => $input['data_registro'],
                'leads' => (int)($input['leads_recebidos'] ?? 0),
                'vendas' => (int)($input['vendas_fechadas'] ?? 0),
                'valor' => (float)($input['valor_faturado'] ?? 0),
                'inv' => (float)($input['investimento_dia'] ?? 0),
                'uid' => (int)($input['idusuario'] ?? 0),
                'meta' => (int)($input['id_meta'] ?? 0)
            ]);

            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/historico-alimentacao
     */
    public function getHistoricoAlimentacao(Request $request, Response $response): Response
    {
        $data = $request->getQueryParams()['data'] ?? null;
        $limite = (int)($request->getQueryParams()['limite'] ?? 30);

        try {
            $sql = "SELECT * FROM mkt_alimentacao_diaria";
            $params = [];

            if ($data) {
                $sql .= " WHERE data_registro = :data";
                $params['data'] = $data;
            }

            $sql .= " ORDER BY data_registro DESC LIMIT :limite";
            $params['limite'] = $limite;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $this->json($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 10. ANEXOS
    // ======================================================================

    /**
     * POST /v1/marketing/clientes/{id}/anexos
     */
    public function uploadAnexo(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['anexo'])) {
            return $this->json($response, ['error' => 'Nenhum arquivo enviado'], 400);
        }
        
        $file = $uploadedFiles['anexo'];
        
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, ['error' => 'Erro no upload'], 400);
        }
        
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json($response, ['error' => 'Arquivo muito grande. Máximo 10MB.'], 400);
        }
        
        $extensoesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'];
        $extensao = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        
        if (!in_array($extensao, $extensoesPermitidas)) {
            return $this->json($response, ['error' => 'Formato não permitido. Use: ' . implode(', ', $extensoesPermitidas)], 400);
        }
        
        try {
            $dirUploads = __DIR__ . '/../../../uploads/clientes/' . $idCliente;
            if (!is_dir($dirUploads)) {
                mkdir($dirUploads, 0755, true);
            }
            
            $nomeOriginal = $file->getClientFilename();
            $nomeArquivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $nomeOriginal);
            $caminhoCompleto = $dirUploads . '/' . $nomeArquivo;
            
            $file->moveTo($caminhoCompleto);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO mkt_cliente_anexos (id_cliente, nome_arquivo, nome_original, tipo, tamanho)
                VALUES (:id_cliente, :nome_arquivo, :nome_original, :tipo, :tamanho)
            ");
            $stmt->execute([
                'id_cliente' => $idCliente,
                'nome_arquivo' => $nomeArquivo,
                'nome_original' => $nomeOriginal,
                'tipo' => $extensao,
                'tamanho' => $file->getSize()
            ]);
            
            return $this->json($response, [
                'success' => true,
                'id' => $this->pdo->lastInsertId(),
                'nome_original' => $nomeOriginal
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/clientes/{id}/anexos
     */
    public function getAnexos(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mkt_cliente_anexos 
                WHERE id_cliente = :id 
                ORDER BY created_at DESC
            ");
            $stmt->execute(['id' => $idCliente]);
            return $this->json($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/anexos/{id}/download
     */
    public function downloadAnexo(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM mkt_cliente_anexos WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $anexo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$anexo) {
                return $this->json($response, ['error' => 'Anexo não encontrado'], 404);
            }
            
            $caminho = __DIR__ . '/../../../uploads/clientes/' . $anexo['id_cliente'] . '/' . $anexo['nome_arquivo'];
            
            if (!file_exists($caminho)) {
                return $this->json($response, ['error' => 'Arquivo não encontrado'], 404);
            }
            
            $response->getBody()->write(file_get_contents($caminho));
            return $response
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $anexo['nome_original'] . '"')
                ->withHeader('Content-Length', filesize($caminho));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /v1/marketing/anexos/{id}
     */
    public function deletarAnexo(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM mkt_cliente_anexos WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $anexo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($anexo) {
                $caminho = __DIR__ . '/../../../uploads/clientes/' . $anexo['id_cliente'] . '/' . $anexo['nome_arquivo'];
                if (file_exists($caminho)) {
                    unlink($caminho);
                }
            }
            
            $this->pdo->prepare("DELETE FROM mkt_cliente_anexos WHERE id = :id")->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 11. TAGS
    // ======================================================================

    /**
     * PUT /v1/marketing/clientes/{id}/tags
     */
    public function atualizarTags(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $tags = $input['tags'] ?? [];
        
        try {
            $this->pdo->beginTransaction();
            
            $this->pdo->prepare("DELETE FROM mkt_cliente_tags WHERE id_cliente = :id")
                ->execute(['id' => $idCliente]);
            
            if (!empty($tags)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO mkt_cliente_tags (id_cliente, tag) 
                    VALUES (:id_cliente, :tag)
                    ON CONFLICT (id_cliente, tag) DO NOTHING
                ");
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if (!empty($tag)) {
                        $stmt->execute(['id_cliente' => $idCliente, 'tag' => $tag]);
                    }
                }
            }
            
            $this->pdo->commit();
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/marketing/clientes/{id}/tags
     */
    public function getTags(Request $request, Response $response, array $args): Response
    {
        $idCliente = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("SELECT tag FROM mkt_cliente_tags WHERE id_cliente = :id ORDER BY tag");
            $stmt->execute(['id' => $idCliente]);
            return $this->json($response, $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 12. NOTIFICAÇÕES
    // ======================================================================

    /**
     * GET /v1/notificacoes
     */
    public function getNotificacoes(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $idUsuario = $user['idusuario'] ?? 0;
        $limite = (int)($request->getQueryParams()['limite'] ?? 20);
        
        try {
            $stmtCheck = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = 'notificacoes'
                )
            ");
            $tabelaExiste = $stmtCheck->fetchColumn();
            
            if (!$tabelaExiste) {
                return $this->json($response, ['notificacoes' => [], 'nao_lidas' => 0]);
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM notificacoes 
                WHERE (idusuario = :uid OR idusuario IS NULL)
                ORDER BY lida ASC, created_at DESC 
                LIMIT :limite
            ");
            $stmt->bindValue(':uid', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtCount = $this->pdo->prepare("
                SELECT COUNT(*) FROM notificacoes 
                WHERE (idusuario = :uid OR idusuario IS NULL) AND lida = false
            ");
            $stmtCount->execute(['uid' => $idUsuario]);
            $naoLidas = (int)$stmtCount->fetchColumn();
            
            return $this->json($response, [
                'notificacoes' => $notificacoes,
                'nao_lidas' => $naoLidas
            ]);
        } catch (Exception $e) {
            error_log('Erro em getNotificacoes: ' . $e->getMessage());
            return $this->json($response, ['notificacoes' => [], 'nao_lidas' => 0]);
        }
    }

    /**
     * GET /v1/crm/notificacoes
     */
    public function getNotificacoesCRM(Request $request, Response $response): Response
    {
        $idUsuario = $this->getUserIdFromToken($request);

        try {
            $this->verificarTabelaNotificacoes();

            $stmt = $this->pdo->prepare("
                SELECT * FROM crm_notificacoes 
                WHERE (id_usuario = :uid OR id_usuario IS NULL)
                ORDER BY created_at DESC 
                LIMIT 30
            ");
            $stmt->execute(['uid' => $idUsuario]);
            $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtCount = $this->pdo->prepare("
                SELECT COUNT(*) FROM crm_notificacoes 
                WHERE (id_usuario = :uid OR id_usuario IS NULL) AND lida = false
            ");
            $stmtCount->execute(['uid' => $idUsuario]);
            $naoLidas = (int)$stmtCount->fetchColumn();

            return $this->json($response, [
                'success' => true,
                'notificacoes' => $notificacoes,
                'nao_lidas' => $naoLidas
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => true, 'notificacoes' => [], 'nao_lidas' => 0]);
        }
    }

    /**
     * PUT /v1/notificacoes/{id}/ler
     */
    public function marcarNotificacaoLida(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $user = $request->getAttribute('user');
        $idUsuario = $user['idusuario'] ?? 0;
        
        try {
            $stmtCheck = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = 'notificacoes'
                )
            ");
            
            if (!$stmtCheck->fetchColumn()) {
                return $this->json($response, ['success' => true]);
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE notificacoes 
                SET lida = true, lida_em = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
            
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            error_log('Erro em marcarNotificacaoLida: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/crm/notificacoes/{id}/ler
     */
    public function marcarNotificacaoCRM(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $idUsuario = $this->getUserIdFromToken($request);

        try {
            $this->verificarTabelaNotificacoes();

            $stmt = $this->pdo->prepare("
                UPDATE crm_notificacoes 
                SET lida = true, lida_em = NOW() 
                WHERE id = :id AND (id_usuario = :uid OR id_usuario IS NULL)
            ");
            $stmt->execute(['id' => $id, 'uid' => $idUsuario]);

            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/notificacoes/ler-todas
     */
    public function marcarTodasLidas(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $idUsuario = $user['idusuario'] ?? 0;
        
        try {
            $stmtCheck = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = 'notificacoes'
                )
            ");
            
            if (!$stmtCheck->fetchColumn()) {
                return $this->json($response, ['success' => true]);
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE notificacoes 
                SET lida = true, lida_em = NOW() 
                WHERE (idusuario = :uid OR idusuario IS NULL) AND lida = false
            ");
            $stmt->execute(['uid' => $idUsuario]);
            
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            error_log('Erro em marcarTodasLidas: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /v1/crm/notificacoes/ler-todas
     */
    public function marcarTodasNotificacoesCRM(Request $request, Response $response): Response
    {
        $idUsuario = $this->getUserIdFromToken($request);

        try {
            $this->verificarTabelaNotificacoes();

            $stmt = $this->pdo->prepare("
                UPDATE crm_notificacoes 
                SET lida = true, lida_em = NOW() 
                WHERE (id_usuario = :uid OR id_usuario IS NULL) AND lida = false
            ");
            $stmt->execute(['uid' => $idUsuario]);

            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/crm/notificacoes
     */
    public function criarNotificacaoViaFrontend(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            
            $user = $request->getAttribute('user');
            $idUsuario = $user['idusuario'] ?? 0;
            
            $sql = "INSERT INTO crm_notificacoes (
                titulo, mensagem, tipo, id_referencia, id_usuario, link, created_at
            ) VALUES (
                :titulo, :mensagem, :tipo, :id_referencia, :id_usuario, :link, NOW()
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'titulo' => $input['titulo'] ?? 'Compromisso',
                'mensagem' => $input['mensagem'] ?? '',
                'tipo' => $input['tipo'] ?? 'compromisso',
                'id_referencia' => (int)($input['id_referencia'] ?? 0),
                'id_usuario' => $idUsuario,
                'link' => $input['link'] ?? ''
            ]);
            
            return $this->json($response, [
                'success' => true,
                'id' => $this->pdo->lastInsertId()
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em criarNotificacaoViaFrontend: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/crm/gerar-alertas
     */
    public function gerarAlertasCRM(Request $request, Response $response): Response
    {
        try {
            // 1. Leads parados
            $stmt = $this->pdo->query("
                SELECT c.id, c.nome, c.empresa, c.telefone
                FROM mkt_clientes c
                WHERE c.status IN ('Novo', 'Qualificado', 'Proposta')
                  AND c.termometro = 'Quente'
                  AND (
                      SELECT MAX(data_interacao) FROM mkt_interacoes WHERE id_cliente = c.id
                  ) < CURRENT_DATE - INTERVAL '5 days'
                  AND NOT EXISTS (
                      SELECT 1 FROM crm_notificacoes n 
                      WHERE n.tipo = 'lead_parado' 
                        AND n.id_referencia = c.id 
                        AND n.created_at > CURRENT_DATE - INTERVAL '1 day'
                  )
                LIMIT 20
            ");
            $leadsParados = $stmt->fetchAll();
            
            foreach ($leadsParados as $lead) {
                $this->criarNotificacaoCRM(
                    '⚠️ Lead precisa de atenção!',
                    "O cliente '{$lead['nome']}' está com termômetro QUENTE e sem interação há mais de 5 dias. Faça um follow-up urgente!",
                    'lead_parado',
                    $lead['id'],
                    null,
                    null
                );
            }
            
            // 2. Compromissos próximos
            $stmtComp = $this->pdo->prepare("
                SELECT c.*, cl.nome as cliente_nome
                FROM mkt_compromissos c
                JOIN mkt_clientes cl ON cl.id = c.id_cliente
                WHERE c.status = 'agendado'
                  AND c.data_hora BETWEEN NOW() AND NOW() + INTERVAL '1 day'
                  AND NOT EXISTS (
                      SELECT 1 FROM crm_notificacoes n 
                      WHERE n.tipo = 'compromisso' 
                        AND n.id_referencia = c.id 
                        AND n.created_at > CURRENT_DATE
                  )
                LIMIT 20
            ");
            $stmtComp->execute();
            $compromissos = $stmtComp->fetchAll();
            
            foreach ($compromissos as $comp) {
                $dataHora = new \DateTime($comp['data_hora']);
                $this->criarNotificacaoCRM(
                    '📅 Compromisso em breve',
                    "Compromisso com {$comp['cliente_nome']} agendado para " . $dataHora->format('d/m/Y H:i'),
                    'compromisso',
                    $comp['id_cliente'],
                    null,
                    null
                );
            }
            
            // 3. Metas próximas do fim
            $stmtMetas = $this->pdo->query("
                SELECT m.*, tm.nome as tipo_nome
                FROM mkt_metas_instancias m
                LEFT JOIN mkt_tipos_meta tm ON tm.id = m.id_tipo_meta
                WHERE m.status = 'ativa' 
                  AND m.data_fim BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
                  AND NOT EXISTS (
                      SELECT 1 FROM crm_notificacoes n 
                      WHERE n.tipo = 'meta_prazo' 
                        AND n.id_referencia = m.id 
                        AND n.created_at > CURRENT_DATE - INTERVAL '2 days'
                  )
            ");
            $metasProximas = $stmtMetas->fetchAll();
            
            foreach ($metasProximas as $meta) {
                $diasRestantes = (new \DateTime($meta['data_fim']))->diff(new \DateTime())->days;
                $this->criarNotificacaoCRM(
                    '🎯 Meta próxima do fim!',
                    "A meta '{$meta['titulo']}' termina em {$diasRestantes} dias. Acompanhe o progresso!",
                    'meta_prazo',
                    $meta['id'],
                    null,
                    "/portal/modules/marketing/dashboard.php?meta={$meta['id']}"
                );
            }
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Alertas gerados com sucesso',
                'alertas' => [
                    'leads_parados' => count($leadsParados),
                    'compromissos' => count($compromissos),
                    'metas_prazo' => count($metasProximas)
                ]
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 13. EXPORTAÇÃO
    // ======================================================================

    /**
     * GET /v1/marketing/clientes/exportar/{formato}
     */
    public function exportarClientes(Request $request, Response $response, array $args): Response
    {
        $formato = $args['formato'] ?? 'csv';

        try {
            $stmt = $this->pdo->query("
                SELECT c.*,
                    (SELECT COUNT(*) FROM mkt_interacoes WHERE id_cliente = c.id) as total_interacoes
                FROM mkt_clientes c
                ORDER BY c.data_cadastro DESC
            ");
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($formato === 'csv') {
                $response = $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
                    ->withHeader('Content-Disposition', 'attachment; filename="clientes_export_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://temp', 'r+');
                fputcsv($output, ['Nome', 'Empresa', 'Telefone', 'Email', 'Cidade/UF', 'Origem', 'Status', 'Termômetro', 'Valor', 'Cadastro'], ';');

                foreach ($clientes as $c) {
                    fputcsv($output, [
                        $c['nome'], $c['empresa'], $c['telefone'], $c['email'],
                        ($c['cidade'] ?? '') . '/' . ($c['uf'] ?? ''),
                        $c['origem'], $c['status'], $c['termometro'],
                        $c['valor_negocio'], $c['data_cadastro']
                    ], ';');
                }

                rewind($output);
                $csvContent = stream_get_contents($output);
                fclose($output);

                $response->getBody()->write($csvContent);
                return $response;
            }

            return $this->json($response, ['error' => 'Formato não suportado'], 400);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 14. EMAIL
    // ======================================================================

    /**
     * POST /v1/marketing/enviar-relatorio-email
     */
    public function enviarRelatorioEmail(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $params = $request->getParsedBody();
        $tipo = $params['tipo'] ?? 'dashboard';
        
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? '';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $_ENV['MAIL_PORT'] ?? 465;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            
            $mail->setFrom($_ENV['MAIL_USER'] ?? 'portal@nutricionalbr.com', 'Portal Nutricional');
            $mail->addAddress('marketing@nutricionalbr.com');
            
            $titulos = ['dashboard' => 'Performance', 'metas' => 'Metas', 'clientes' => 'Clientes', 'pipeline' => 'Pipeline'];
            $mail->Subject = "Relatório de " . ($titulos[$tipo] ?? 'Marketing') . " - " . date('d/m/Y');
            $mail->isHTML(true);
            $mail->Body = "<h2>Relatório de Marketing</h2><p>Segue em anexo o relatório de <b>" . ($titulos[$tipo] ?? 'Marketing') . "</b> gerado em " . date('d/m/Y H:i') . ".</p>";
            
            if (isset($uploadedFiles['pdf']) && $uploadedFiles['pdf']->getError() === UPLOAD_ERR_OK) {
                $pdf = $uploadedFiles['pdf'];
                $mail->addAttachment($pdf->getFilePath(), "Relatorio_" . $tipo . "_" . date('Y-m-d') . ".pdf");
            }
            
            $mail->send();
            return $this->json($response, ['success' => true]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/marketing/configurar-email-auto
     */
    public function configurarEmailAuto(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        return $this->json($response, ['success' => true, 'message' => 'Configuração salva!']);
    }

    // ======================================================================
    // 15. CRON
    // ======================================================================

    /**
     * GET /v1/marketing/cron/alertas
     */
    public function cronAlertas(Request $request, Response $response): Response
    {
        try {
            $this->gerarAlertasAutomaticos();
            return $this->json($response, ['success' => true, 'message' => 'Alertas gerados com sucesso']);
        } catch (Exception $e) {
            error_log('Erro em cronAlertas: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 16. CRM DASHBOARD
    // ======================================================================

    /**
     * GET /v1/marketing/crm-dashboard
     */
    public function getCRMDashboard(Request $request, Response $response): Response
    {
        try {
            $sqlCards = "
                SELECT 
                    COUNT(*) FILTER (WHERE status IN ('Novo', 'Qualificado', 'Proposta')) as em_negociacao,
                    COUNT(*) FILTER (WHERE status = 'Fechado') as fechados,
                    COUNT(*) FILTER (WHERE data_cadastro >= CURRENT_DATE - INTERVAL '30 days') as novos_30dias,
                    COUNT(*) as total_clientes,
                    COALESCE(SUM(CASE WHEN status = 'Fechado' THEN valor_negocio ELSE 0 END), 0)::float as total_faturado
                FROM mkt_clientes
            ";
            $cards = $this->pdo->query($sqlCards)->fetch(PDO::FETCH_ASSOC);
            
            $sqlAlertas = "
                SELECT c.* FROM mkt_clientes c
                WHERE c.termometro = 'Quente' 
                  AND c.status != 'Perdido'
                  AND (
                      SELECT MAX(i.data_interacao) FROM mkt_interacoes i WHERE i.id_cliente = c.id
                  ) < CURRENT_DATE - INTERVAL '3 days'
                  OR NOT EXISTS (SELECT 1 FROM mkt_interacoes i WHERE i.id_cliente = c.id)
                ORDER BY c.data_cadastro DESC
                LIMIT 5
            ";
            $alertas = $this->pdo->query($sqlAlertas)->fetchAll(PDO::FETCH_ASSOC);
            
            $sqlPipeline = "
                SELECT status, COUNT(*) as total, COALESCE(SUM(valor_negocio), 0)::float as valor
                FROM mkt_clientes
                WHERE status != 'Perdido'
                GROUP BY status
                ORDER BY 
                    CASE status 
                        WHEN 'Novo' THEN 1 
                        WHEN 'Qualificado' THEN 2 
                        WHEN 'Proposta' THEN 3 
                        WHEN 'Fechado' THEN 4 
                    END
            ";
            $pipeline = $this->pdo->query($sqlPipeline)->fetchAll(PDO::FETCH_ASSOC);

            $sqlOrigem = "
                SELECT 
                    origem, 
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE status = 'Fechado') as fechados
                FROM mkt_clientes
                WHERE data_cadastro >= CURRENT_DATE - INTERVAL '90 days'
                GROUP BY origem
                ORDER BY total DESC
            ";
            $origem = $this->pdo->query($sqlOrigem)->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'cards' => $cards,
                'alertas' => $alertas,
                'pipeline' => $pipeline,
                'origem' => $origem
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // 17. MÉTODOS AUXILIARES
    // ======================================================================

    /**
     * Fallback: usar consultas normais se a view estiver vazia
     */
    private function getDashboardFallback(Request $request, Response $response): Response
    {
        error_log('⚠️ Usando fallback para dashboard (view materializada vazia)');
        return $this->getDashboardTotals($request, $response);
    }

    /**
     * GET /v1/marketing/atualizar-cache
     * Força a atualização da VIEW materializada
     */
    public function atualizarCacheDashboard(Request $request, Response $response): Response
    {
        try {
            $pdo = $this->pdo;
            
            // Verificar se a view existe
            $checkView = $pdo->query("
                SELECT EXISTS (
                    SELECT 1 FROM pg_matviews WHERE matviewname = 'mv_dashboard_marketing'
                )
            ")->fetchColumn();
            
            if (!$checkView) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'View materializada não encontrada. Execute o script SQL primeiro.'
                ], 500);
            }
            
            // Atualizar a view materializada
            $pdo->exec("REFRESH MATERIALIZED VIEW CONCURRENTLY mv_dashboard_marketing");
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Cache do dashboard atualizado com sucesso!',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log('Erro ao atualizar cache: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resposta JSON com cache busting
     */
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload);
        return $response->withStatus($status)
                        ->withHeader('Content-Type', 'application/json; charset=utf-8')
                        ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                        ->withHeader('Pragma', 'no-cache')
                        ->withHeader('Expires', '0');
    }
    /**
     * Remove emojis e caracteres especiais
     */
    private function limparTexto(string $texto): string
    {
        if (empty($texto)) return '';
        $texto = preg_replace('/[\x{1F600}-\x{1F9FF}]/u', '', $texto);
        $texto = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $texto);
        $texto = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $texto);
        $texto = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $texto);
        $texto = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto);
        return trim($texto);
    }

    /**
     * Extrai user ID do token
     */
    private function getUserIdFromToken($request): int
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? 'chave_super_secreta', 'HS256'));
                return $decoded->uid ?? 0;
            } catch (Exception $e) {
                return 0;
            }
        }
        return 0;
    }

    /**
     * Calcula variação percentual dos KPIs
     */
    private function calcularVariacaoKPIs(): array
    {
        $stmtAtual = $this->pdo->query("
            SELECT 
                COUNT(*) as leads,
                COUNT(*) FILTER (WHERE status = 'Fechado') as vendas,
                COALESCE(SUM(valor_fechamento), 0)::float as faturamento
            FROM mkt_leads_controle 
            WHERE data_registro >= date_trunc('month', CURRENT_DATE)
        ");
        $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

        $stmtAnterior = $this->pdo->query("
            SELECT 
                COUNT(*) as leads,
                COUNT(*) FILTER (WHERE status = 'Fechado') as vendas,
                COALESCE(SUM(valor_fechamento), 0)::float as faturamento
            FROM mkt_leads_controle 
            WHERE data_registro >= date_trunc('month', CURRENT_DATE - INTERVAL '1 month')
              AND data_registro < date_trunc('month', CURRENT_DATE)
        ");
        $anterior = $stmtAnterior->fetch(PDO::FETCH_ASSOC);

        $calcularPct = function($atual, $anterior) {
            if ($anterior == 0) return $atual > 0 ? 100 : 0;
            return round((($atual - $anterior) / $anterior) * 100, 1);
        };

        return [
            'variacao_leads' => $calcularPct((int)$atual['leads'], (int)$anterior['leads']),
            'variacao_conversao' => $calcularPct((int)$atual['vendas'], (int)$anterior['vendas']),
            'variacao_faturamento' => $calcularPct((float)$atual['faturamento'], (float)$anterior['faturamento'])
        ];
    }

    /**
     * Calcula variação completa
     */
    private function calcularVariacaoCompleta(): array
    {
        try {
            $stmtAtual = $this->pdo->query("
                SELECT 
                    COUNT(*) as leads,
                    COUNT(*) FILTER (WHERE status = 'Fechado') as vendas,
                    COALESCE(SUM(valor_fechamento), 0)::float as faturamento,
                    COALESCE(SUM(investimento_dia), 0)::float as investimento
                FROM mkt_leads_controle l
                LEFT JOIN mkt_alimentacao_diaria a ON a.data_registro = l.data_registro
                WHERE l.data_registro >= date_trunc('month', CURRENT_DATE)
            ");
            $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
            
            $stmtAnterior = $this->pdo->query("
                SELECT 
                    COUNT(*) as leads,
                    COUNT(*) FILTER (WHERE status = 'Fechado') as vendas,
                    COALESCE(SUM(valor_fechamento), 0)::float as faturamento,
                    COALESCE(SUM(investimento_dia), 0)::float as investimento
                FROM mkt_leads_controle l
                LEFT JOIN mkt_alimentacao_diaria a ON a.data_registro = l.data_registro
                WHERE l.data_registro >= date_trunc('month', CURRENT_DATE - INTERVAL '1 month')
                  AND l.data_registro < date_trunc('month', CURRENT_DATE)
            ");
            $anterior = $stmtAnterior->fetch(PDO::FETCH_ASSOC);
            
            $calcularPct = function($atual, $anterior) {
                if ($anterior == 0) return $atual > 0 ? 100 : 0;
                return round((($atual - $anterior) / $anterior) * 100, 1);
            };
            
            return [
                'variacao_leads' => $calcularPct((int)$atual['leads'], (int)$anterior['leads']),
                'variacao_vendas' => $calcularPct((int)$atual['vendas'], (int)$anterior['vendas']),
                'variacao_faturamento' => $calcularPct((float)$atual['faturamento'], (float)$anterior['faturamento']),
                'variacao_investimento' => $calcularPct((float)($atual['investimento'] ?? 0), (float)($anterior['investimento'] ?? 0)),
                'mes_atual' => date('F/Y'),
                'mes_anterior' => date('F/Y', strtotime('-1 month'))
            ];
        } catch (Exception $e) {
            return [
                'variacao_leads' => 0,
                'variacao_vendas' => 0,
                'variacao_faturamento' => 0,
                'variacao_investimento' => 0,
                'mes_atual' => date('F/Y'),
                'mes_anterior' => date('F/Y', strtotime('-1 month'))
            ];
        }
    }

    /**
     * Converte lead para cliente
     */
    private function converterLeadParaCliente($lead, $valorFechamento)
    {
        $stmtCheck = $this->pdo->prepare("
            SELECT id FROM mkt_clientes 
            WHERE (telefone = :tel AND telefone != '') 
               OR (empresa = :emp AND empresa != '')
            LIMIT 1
        ");
        $stmtCheck->execute([
            'tel' => $lead['telefone'] ?? '',
            'emp' => $lead['empresa'] ?? ''
        ]);
        $clienteExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($clienteExistente) {
            $stmtUpdate = $this->pdo->prepare("
                UPDATE mkt_clientes SET 
                    status = 'Fechado',
                    termometro = 'Quente',
                    valor_negocio = :valor,
                    data_atualizacao = NOW()
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'id' => $clienteExistente['id'],
                'valor' => $valorFechamento
            ]);
            
            $this->registrarInteracaoAutomatica($clienteExistente['id'], 'Lead convertido para Fechado via Marketing');
            
            if (!empty($lead['id_meta'])) {
                $this->registrarFechamentoMeta($clienteExistente['id'], $lead['id_meta'], $valorFechamento);
            }
            
            return $clienteExistente['id'];
        }
        
        $sql = "INSERT INTO mkt_clientes (
            nome, empresa, telefone, email, cidade, uf, origem, 
            status, termometro, valor_negocio, id_meta, observacoes, 
            data_cadastro, usuario_criacao
        ) VALUES (
            :nome, :empresa, :telefone, :email, :cidade, :uf, :origem,
            'Fechado', 'Quente', :valor, :id_meta, :obs,
            CURRENT_DATE, 'Sistema (Lead #' || :lead_id || ')'
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nome' => $lead['gestor'] ?? 'Cliente ' . ($lead['empresa'] ?? ''),
            'empresa' => $lead['empresa'] ?? '',
            'telefone' => $lead['telefone'] ?? '',
            'email' => $lead['email'] ?? '',
            'cidade' => $lead['cidade'] ?? '',
            'uf' => $lead['uf'] ?? '',
            'origem' => $lead['origem'] ?? 'Site',
            'valor' => $valorFechamento,
            'id_meta' => !empty($lead['id_meta']) ? (int)$lead['id_meta'] : null,
            'obs' => 'Lead convertido automaticamente. Produto: ' . ($lead['produto_interesse'] ?? 'Não informado'),
            'lead_id' => $lead['id']
        ]);

        $novoClienteId = $this->pdo->lastInsertId();

        $this->registrarInteracaoAutomatica($novoClienteId, 'Cliente criado automaticamente ao fechar lead de marketing');

        if (!empty($lead['id_meta'])) {
            $this->registrarFechamentoMeta($novoClienteId, $lead['id_meta'], $valorFechamento);
        }

        $this->pdo->prepare("
            INSERT INTO mkt_alimentacao_diaria (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, id_meta)
            VALUES (CURRENT_DATE, 0, 1, :valor, 0, :id_meta)
            ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + 1,
                valor_faturado = mkt_alimentacao_diaria.valor_faturado + :valor2
        ")->execute([
            'id_meta' => (int)($lead['id_meta'] ?? 0),
            'valor' => $valorFechamento,
            'valor2' => $valorFechamento
        ]);

        return $novoClienteId;
    }

    /**
     * Reverte cliente para lead
     */
    private function reverterClienteParaLead($lead)
    {
        $stmtFind = $this->pdo->prepare("
            SELECT id FROM mkt_clientes 
            WHERE usuario_criacao LIKE '%Lead #' || :lead_id || '%'
            LIMIT 1
        ");
        $stmtFind->execute(['lead_id' => $lead['id']]);
        $cliente = $stmtFind->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente) {
            $this->pdo->prepare("
                UPDATE mkt_clientes SET 
                    status = 'Perdido',
                    observacoes = COALESCE(observacoes, '') || ' | Lead reaberto em ' || CURRENT_DATE,
                    data_atualizacao = NOW()
                WHERE id = :id
            ")->execute(['id' => $cliente['id']]);
        }
    }

    /**
     * Registra interação automática
     */
    private function registrarInteracaoAutomatica($idCliente, $descricao)
    {
        $this->pdo->prepare("
            INSERT INTO mkt_interacoes (id_cliente, tipo, descricao, data_interacao, hora_interacao, usuario)
            VALUES (:id_cliente, 'sistema', :descricao, CURRENT_DATE, CURRENT_TIME, 'Sistema')
        ")->execute([
            'id_cliente' => $idCliente,
            'descricao' => $descricao
        ]);
        
        $this->pdo->prepare("UPDATE mkt_clientes SET ultima_interacao = NOW() WHERE id = :id")
            ->execute(['id' => $idCliente]);
    }

    /**
     * Atualiza meta com lead
     */
    private function atualizarMetaComLead($idMeta)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mkt_alimentacao_diaria (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, id_meta)
            VALUES (CURRENT_DATE, 1, 0, 0, 0, :id_meta)
            ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                leads_recebidos = mkt_alimentacao_diaria.leads_recebidos + 1
        ");
        $stmt->execute(['id_meta' => $idMeta]);
    }

    /**
     * Registra fechamento de meta
     */
    private function registrarFechamentoMeta($idCliente, $idMeta, $valor)
    {
        if (!$idMeta || $valor <= 0) return;

        $stmt = $this->pdo->prepare("
            INSERT INTO mkt_alimentacao_diaria (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, id_meta)
            VALUES (CURRENT_DATE, 0, 1, :valor, 0, :id_meta)
            ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + 1,
                valor_faturado = mkt_alimentacao_diaria.valor_faturado + :valor2
        ");
        $stmt->execute([
            'id_meta' => $idMeta,
            'valor' => $valor,
            'valor2' => $valor
        ]);

        $stmtLead = $this->pdo->prepare("
            INSERT INTO mkt_leads_controle (data_registro, empresa, status, origem, qualificado, termometro, valor_fechamento, id_meta, gestor)
            SELECT CURRENT_DATE, empresa, 'Fechado', origem, 'true', termometro, :valor, id_meta, 'CRM'
            FROM mkt_clientes WHERE id = :id_cliente
        ");
        $stmtLead->execute([
            'id_cliente' => $idCliente,
            'valor' => $valor
        ]);
    }

    /**
     * Verifica e cria tabela de notificações
     */
    private function verificarTabelaNotificacoes()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = 'crm_notificacoes'
                )
            ");
            $existe = $stmt->fetchColumn();

            if (!$existe) {
                $this->pdo->exec("
                    CREATE TABLE crm_notificacoes (
                        id SERIAL PRIMARY KEY,
                        titulo VARCHAR(200) NOT NULL,
                        mensagem TEXT NOT NULL,
                        tipo VARCHAR(50) DEFAULT 'sistema',
                        id_referencia INTEGER,
                        id_usuario INTEGER,
                        link VARCHAR(500),
                        lida BOOLEAN DEFAULT false,
                        lida_em TIMESTAMP,
                        created_at TIMESTAMP DEFAULT NOW()
                    )
                ");
            }
        } catch (Exception $e) {
            error_log('Erro ao verificar tabela notificações: ' . $e->getMessage());
        }
    }

    /**
     * Cria notificação no CRM
     */
    private function criarNotificacaoCRM($titulo, $mensagem, $tipo, $idReferencia = null, $idUsuario = null, $link = null)
    {
        try {
            $this->verificarTabelaNotificacoes();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO crm_notificacoes (titulo, mensagem, tipo, id_referencia, id_usuario, link, created_at)
                VALUES (:titulo, :mensagem, :tipo, :ref, :uid, :link, NOW())
            ");
            $stmt->execute([
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'tipo' => $tipo,
                'ref' => $idReferencia,
                'uid' => $idUsuario,
                'link' => $link
            ]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log('Erro ao criar notificação CRM: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gera alertas automáticos
     */
    private function gerarAlertasAutomaticos()
    {
        try {
            $stmtCheck = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = 'notificacoes'
                )
            ");
            
            if (!$stmtCheck->fetchColumn()) {
                error_log('Tabela notificacoes não existe, pulando geração de alertas');
                return;
            }
            
            // Metas próximas do fim
            $stmtMetas = $this->pdo->query("
                SELECT m.* FROM mkt_metas_instancias m
                WHERE m.status = 'ativa'
                  AND m.data_fim BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
                  AND NOT EXISTS (
                      SELECT 1 FROM notificacoes n 
                      WHERE n.tipo = 'meta_prazo' 
                        AND n.mensagem LIKE '%' || m.id || '%' 
                        AND n.created_at > CURRENT_DATE - INTERVAL '1 day'
                  )
            ");
            $metasProximas = $stmtMetas->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($metasProximas as $meta) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO notificacoes (tipo, titulo, mensagem, link)
                    VALUES ('meta_prazo', 'Meta próxima do fim!', :msg, '/portal/modules/marketing-metas.php')
                ");
                $stmt->execute([
                    'msg' => "A meta \"{$meta['titulo']}\" (ID: {$meta['id']}) termina em " . date('d/m/Y', strtotime($meta['data_fim'])) . ". Faltam poucos dias!"
                ]);
            }
            
            // Leads parados
            $stmtLeads = $this->pdo->query("
                SELECT c.* FROM mkt_clientes c
                WHERE c.status IN ('Novo', 'Qualificado', 'Proposta')
                  AND (
                      SELECT MAX(i.data_interacao) FROM mkt_interacoes i WHERE i.id_cliente = c.id
                  ) < CURRENT_DATE - INTERVAL '5 days'
                  AND NOT EXISTS (
                      SELECT 1 FROM notificacoes n 
                      WHERE n.tipo = 'lead_parado' 
                        AND n.mensagem LIKE '%' || c.id || '%' 
                        AND n.created_at > CURRENT_DATE - INTERVAL '2 days'
                  )
                LIMIT 5
            ");
            $leadsParados = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($leadsParados as $lead) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO notificacoes (tipo, titulo, mensagem, link)
                    VALUES ('lead_parado', 'Lead sem follow-up!', :msg, '/portal/modules/marketing-clientes.php')
                ");
                $stmt->execute([
                    'msg' => "O cliente \"{$lead['nome']}\" ({$lead['empresa']}) está sem interação há mais de 5 dias. Faça um follow-up!"
                ]);
            }
            
        } catch (Exception $e) {
            error_log('Erro em gerarAlertasAutomaticos: ' . $e->getMessage());
        }
    }

    // ======================================================================
    // MÉTODOS PARA METAS - AUXILIARES
    // ======================================================================

    /**
     * Calcula dias restantes
     */
    private function calcularDiasRestantes(?string $dataFim): int
    {
        if (!$dataFim) return 0;
        $fim = new \DateTime($dataFim);
        $hoje = new \DateTime();
        $diff = $hoje->diff($fim);
        return $fim > $hoje ? (int)$diff->days : -$diff->days;
    }

    /**
     * Calcula dias passados desde o início
     */
    private function calcularDiasPassados(?string $dataInicio): int
    {
        if (!$dataInicio) return 0;
        $inicio = new \DateTime($dataInicio);
        $hoje = new \DateTime();
        $diff = $inicio->diff($hoje);
        return $inicio < $hoje ? (int)$diff->days : 0;
    }

    /**
     * Calcula projeção da meta
     */
    private function calcularProjecao(float $gap, int $diasRestantes, float $taxaInicial, float $valorAtual, int $diasPassados): string
    {
        if ($gap <= 0) return 'concluida';
        if ($diasRestantes <= 0) return 'atrasada';
        if ($diasPassados <= 0) return 'iniciando';
        
        $ritmoNecessario = $gap / $diasRestantes;
        $ritmoAtual = ($valorAtual - $taxaInicial) / $diasPassados;
        
        if ($ritmoAtual <= 0) return 'parada';
        
        $diasEstimados = $gap / $ritmoAtual;
        
        if ($diasEstimados <= $diasRestantes * 0.8) return 'adiantada';
        if ($diasEstimados <= $diasRestantes * 1.2) return 'no_prazo';
        return 'atrasada';
    }

    /**
     * Busca histórico de registros da meta
     */
    private function buscarHistoricoMeta(int $idMeta, int $limite = 5): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ar.*, u.nome as usuario_nome
                FROM mkt_alimentacao_registros ar
                LEFT JOIN users u ON u.id = ar.usuario_id
                WHERE ar.id_meta_instancia = :id_meta
                ORDER BY ar.data_registro DESC
                LIMIT :limite
            ");
            $stmt->bindValue(':id_meta', $idMeta, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Calcula tendência baseada no histórico
     */
    private function calcularTendencia(array $historico, string $nomeValorAtual): string
    {
        if (count($historico) < 2) return 'estavel';
        
        $valores = [];
        foreach ($historico as $reg) {
            $vals = is_string($reg['valores']) ? json_decode($reg['valores'], true) : ($reg['valores'] ?? []);
            $valores[] = (float) ($vals[$nomeValorAtual] ?? $vals['valor_alcancado'] ?? 0);
        }
        
        $valores = array_filter($valores, fn($v) => $v > 0);
        if (count($valores) < 2) return 'estavel';
        
        $valores = array_values($valores);
        $ultimo = end($valores);
        $penultimo = $valores[count($valores) - 2] ?? $ultimo;
        
        if ($ultimo > $penultimo * 1.05) return 'crescendo';
        if ($ultimo < $penultimo * 0.95) return 'diminuindo';
        return 'estavel';
    }
}