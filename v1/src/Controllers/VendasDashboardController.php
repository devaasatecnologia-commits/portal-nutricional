<?php
namespace Nutricional\Controllers;

use Nutricional\Utils\UserScope;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Dashboard Executivo de Vendas v3.0 — Edição Especialista
 *
 * Regras de negócio:
 *  - Faturamento = NFs faturadas (nfs.status=2, idtransacao=1), mesma definição do Cubo de Vendas.
 *  - Peso Líquido (kg) = COALESCE(ni.pesoliquido, i.pesoliquido, 0) * ni.qt (idêntico ao Cubo de Vendas).
 *  - Carteira de pedidos = pedidos abertos/parciais (status 1 e 4, idtransacao 1/17), card separado.
 *  - Ticket médio = faturamento ÷ nº de NFs distintas.
 *  - Regiões Unificadas = 6 Macro-Regiões Oficiais: SUL, GRF, AVI, NOR, PLN, RMC.
 *  - Todo endpoint aplica o escopo do usuário (JWT) via helper único escopo().
 */
class VendasDashboardController
{
    private const MAX_DIAS = 400;
    private const MAX_DIAS_PESADO = 90;
    private const DIAS_EVOLUCAO_DIARIA = 62;
    private const JANELA_RISCO = 365;
    private const DIAS_RISCO = 30;

    /** Valor faturado do item: idêntico ao Cubo de Vendas. */
    private const VAL = "(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0))";

    /** Peso líquido faturado do item (kg): idêntico ao Cubo de Vendas. */
    private const PESO = "(COALESCE(ni.pesoliquido, i.pesoliquido, 0) * ni.qt)";

    /** Valor devolvido, peso devolvido e comissão devolvida por item: idênticos ao Cubo de Vendas. */
    private const VAL_DEV = "(ni.valortotal + COALESCE(ni.valoripi, 0) + COALESCE(ni.valorsubsttribicms, 0) + COALESCE(ni.valorfrete, 0) + COALESCE(ni.valorseguro, 0) + COALESCE(ni.valoroutrasdespesas, 0) - COALESCE(ni.valor_desconto, 0))";
    private const PESO_DEV = "(COALESCE(ni.pesoliquido, i.pesoliquido, 0) * ni.qt)";
    private const COM_DEV = "CASE WHEN COALESCE(ni.valor_comissao, 0) > 0 THEN ni.valor_comissao WHEN COALESCE(ni_orig.perc_comissao, 0) > 0 THEN ROUND(" . self::VAL_DEV . " * (ni_orig.perc_comissao / 100.0), 2) ELSE 0 END";

    /** Transações de devolução de venda (tabela nfe, status=2 e status_devolucao=1), alinhadas ao Cubo de Vendas. */
    private const TRANS_DEVOLUCAO = '8,9,15,1000,1001,1004,1005,1007,1011,1014,1015,1020,1024,1025,1031,1032,1033';

    private const ETAPAS_FUNIL = [
        'Abertos' => 1,
        'Faturados Parcial' => 4,
        'Faturados' => 5,
        'Cancelados' => 3,
    ];

    /** Catálogo das 7 Macro-Regiões Oficiais (incluindo Região RED separada para acesso irrestrito) */
    private const REGIOES_MACRO = [
        'SUL' => [
            'sigla' => 'SUL',
            'nome' => 'Sul Catarinense',
            'cor' => '#059669',
            'subregioes' => 'Encantos de Imbituba, Região Carbonífera, Monte de Braço do Norte, Caminho dos Canyons',
            'ids' => [14, 15, 16, 17],
            'restrita_redes' => false
        ],
        'AVI' => [
            'sigla' => 'AVI',
            'nome' => 'Vale do Itajaí & Litoral',
            'cor' => '#f59e0b',
            'subregioes' => 'Foz do Itajaí, Médio Vale, Vale Europeu, Vales do Tijucas',
            'ids' => [2, 3, 4, 5],
            'restrita_redes' => false
        ],
        'GRF' => [
            'sigla' => 'GRF',
            'nome' => 'Grande Florianópolis',
            'cor' => '#3b82f6',
            'subregioes' => 'Ilha/Estreito, Norte/Centro, Sul/Interior, Nascentes',
            'ids' => [6, 7, 8, 9],
            'restrita_redes' => false
        ],
        'NOR' => [
            'sigla' => 'NOR',
            'nome' => 'Norte & Vale do Itapocu',
            'cor' => '#8b5cf6',
            'subregioes' => 'Costa Verde e Mar, Caminho dos Príncipes, Joinville, Vale do Itapocu, Mesorregião Norte',
            'ids' => [1, 10, 11, 12, 13],
            'restrita_redes' => false
        ],
        'PLN' => [
            'sigla' => 'PLN',
            'nome' => 'Planalto Serrano & Oeste',
            'cor' => '#ec4899',
            'subregioes' => 'Caminhos da Neve, Alto Vale, Vale dos Imigrantes, Oeste Catarinense',
            'ids' => [19, 20, 21, 22],
            'restrita_redes' => false
        ],
        'RMC' => [
            'sigla' => 'RMC',
            'nome' => 'Curitiba & Litoral PR',
            'cor' => '#06b6d4',
            'subregioes' => 'Litoral Paranaense, Curitiba Leste, Oeste, Norte, Sul, Divisa SC',
            'ids' => [24, 25, 26, 27, 28, 29],
            'restrita_redes' => false
        ],
        'RED' => [
            'sigla' => 'RED',
            'nome' => 'Todas as Redes',
            'cor' => '#dc2626',
            'subregioes' => 'Todas as Redes (Grandes Contas)',
            'ids' => [18],
            'restrita_redes' => true
        ],
    ];

    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    /**
     * Expressão SQL que unifica as sub-regiões do ERP nas Macro-Regiões Oficiais:
     * SUL, AVI, GRF, NOR, PLN, RMC e RED (Região 18).
     */
    private function regiaoMacroSql(string $rgAlias = 'rg', string $cidAlias = 'cid', string $cliAlias = 'cli'): string
    {
        return "CASE
            WHEN {$cliAlias}.idregiao = 18 OR {$rgAlias}.descricao ILIKE '%*RED%' THEN 'RED'
            WHEN {$cliAlias}.idregiao IN (24, 25, 26, 27, 28, 29) OR {$rgAlias}.descricao ILIKE '%[PR]%' OR {$cidAlias}.uf = 'PR' THEN 'RMC'
            WHEN {$cliAlias}.idregiao IN (14, 15, 16, 17) OR {$rgAlias}.descricao ILIKE '%[SC] SUL%' THEN 'SUL'
            WHEN {$cliAlias}.idregiao IN (2, 3, 4, 5) OR {$rgAlias}.descricao ILIKE '%[SC] AVI%' THEN 'AVI'
            WHEN {$cliAlias}.idregiao IN (6, 7, 8, 9) OR {$rgAlias}.descricao ILIKE '%[SC] GRF%' THEN 'GRF'
            WHEN {$cliAlias}.idregiao IN (1, 10, 11, 12, 13) OR {$rgAlias}.descricao ILIKE '%[SC] NOR%' OR {$rgAlias}.descricao ILIKE '%[SC] MRG%' THEN 'NOR'
            WHEN {$cliAlias}.idregiao IN (19, 20, 21, 22) OR {$rgAlias}.descricao ILIKE '%[SC] PLN%' OR {$rgAlias}.descricao ILIKE '%[SC] OES%' THEN 'PLN'
            ELSE 'SUL'
        END";
    }

    // ======================================================================
    // ENDPOINTS
    // ======================================================================

    /** POST /v1/vendas/dashboard/kpis */
    public function getKpis(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $hoje = new \DateTimeImmutable('today');
            $ant = $this->periodoAnterior($ini, $fim, $hoje);

            $atual = $this->resumoFaturamento($e, $ini, $fim);
            $anterior = $this->resumoFaturamento($e, $ant['ini'], $ant['fim']);

            $notasAnt = (int)$anterior['notas'];
            $ticket = $atual['notas'] > 0 ? $atual['valor'] / $atual['notas'] : 0.0;
            $ticketAnt = $notasAnt > 0 ? $anterior['valor'] / $notasAnt : 0.0;

            $fimEfetivo = $fim < $hoje ? $fim : $hoje;
            $diasUteis = $ini > $fimEfetivo ? 0 : $this->diasUteis($ini, $fimEfetivo);
            $diasComVenda = $this->diasComVenda($e, $ini, $fim);

            $dev = $this->resumoDevolucoes($e, $ini, $fim);
            $dev['percentual_faturamento'] = $atual['valor'] > 0 ? round($dev['valor'] / $atual['valor'] * 100, 2) : 0;

            return [
                'periodo' => [
                    'inicio' => $ini->format('Y-m-d'),
                    'fim' => $fim->format('Y-m-d'),
                    'dias' => $this->dias($ini, $fim),
                    'dias_uteis' => $diasUteis,
                ],
                'periodo_anterior' => [
                    'inicio' => $ant['ini']->format('Y-m-d'),
                    'fim' => $ant['fim']->format('Y-m-d'),
                    'parcial' => $ant['parcial'],
                ],
                'faturamento' => [
                    'valor' => round($atual['valor'], 2),
                    'anterior' => round($anterior['valor'], 2),
                    'variacao' => $this->variacao($atual['valor'], $anterior['valor']),
                    'quantidade' => round($atual['quantidade'], 2),
                    'quantidade_anterior' => round($anterior['quantidade'], 2),
                    'variacao_quantidade' => $this->variacao($atual['quantidade'], $anterior['quantidade']),
                    'peso' => round($atual['peso'], 2),
                    'peso_anterior' => round($anterior['peso'], 2),
                    'variacao_peso' => $this->variacao($atual['peso'], $anterior['peso']),
                    'clientes' => $atual['clientes'],
                    'notas' => $atual['notas'],
                ],
                'notas' => [
                    'atual' => $atual['notas'],
                    'anterior' => $notasAnt,
                    'variacao' => $this->variacao($atual['notas'], $notasAnt),
                    'quantidade_itens' => round($atual['quantidade'], 2),
                    'peso_total' => round($atual['peso'], 2),
                    'media_itens_nf' => $atual['notas'] > 0 ? round($atual['quantidade'] / $atual['notas'], 1) : 0,
                    'media_peso_nf' => $atual['notas'] > 0 ? round($atual['peso'] / $atual['notas'], 2) : 0,
                ],
                'ticket_medio' => [
                    'atual' => round($ticket, 2),
                    'anterior' => round($ticketAnt, 2),
                    'variacao' => $this->variacao($ticket, $ticketAnt),
                    'preco_medio_kg' => $atual['peso'] > 0 ? round($atual['valor'] / $atual['peso'], 2) : 0,
                    'preco_medio_un' => $atual['quantidade'] > 0 ? round($atual['valor'] / $atual['quantidade'], 2) : 0,
                ],
                'carteira' => $this->resumoCarteira($e, $ini, $fim),
                'cancelados' => $this->resumoCancelados($e, $ini, $fim),
                'devolucoes' => $dev,
                'positivacao' => $this->resumoPositivacao($e, $ini, $fim),
                'velocidade_venda' => [
                    'media_diaria' => $diasUteis > 0 ? round($atual['valor'] / $diasUteis, 2) : 0,
                    'media_peso_diario' => $diasUteis > 0 ? round($atual['peso'] / $diasUteis, 2) : 0,
                    'media_qtd_diaria' => $diasUteis > 0 ? round($atual['quantidade'] / $diasUteis, 1) : 0,
                    'media_notas_diaria' => $diasUteis > 0 ? round($atual['notas'] / $diasUteis, 1) : 0,
                    'media_dia_com_venda' => $diasComVenda > 0 ? round($atual['valor'] / $diasComVenda, 2) : 0,
                    'dias_uteis' => $diasUteis,
                    'dias_com_venda' => $diasComVenda,
                    'por_dia_semana' => $this->porDiaSemana($e, $ini, $fim),
                ],
                'top_produtos' => $this->topProdutos($e, $ini, $fim, $atual['valor']),
                'top_clientes' => $this->topClientes($e, $ini, $fim, $atual['valor']),
                'distribuicao_regiao' => $this->distribuicaoRegiao($e, $ini, $fim),
                'pode_ver_redes' => !empty($e['pode_ver_redes']),
                'evolucao' => $this->evolucao($e, $ini, $fim),
            ];
        });
    }

    /** POST /v1/vendas/dashboard/kpis-detalhes */
    public function getKpisDetalhes(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $out = [
                'margem_produtos' => [],
                'matriz_cross_selling' => [],
                'projecao_vendas' => $this->projecaoProximosMeses($e, $fim),
                'limite_dias' => self::MAX_DIAS_PESADO,
            ];

            if ($this->dias($ini, $fim) > self::MAX_DIAS_PESADO) {
                $out['mensagem'] = 'Período acima de ' . self::MAX_DIAS_PESADO . ' dias: produtos comprados juntos não foram calculados. Reduza o período.';
                return $out;
            }

            $out['matriz_cross_selling'] = $this->crossSelling($e, $ini, $fim);
            return $out;
        });
    }

    /** POST /v1/vendas/dashboard/produto-detalhes */
    public function getProdutoDetalhes(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $idproduto = (int)($in['idproduto'] ?? $in['iditem'] ?? 0);
            if ($idproduto <= 0) {
                throw new \InvalidArgumentException('Produto inválido.');
            }
            $p = ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d'), 'it' => $idproduto];
            $w = $this->w($e, 'n');
            $from = $this->nfFrom() . $w . ' AND ni.iditem = :it';

            $r = $this->one("SELECT COALESCE(SUM(" . self::VAL . "),0) AS valor,
                    COALESCE(SUM(ni.qt),0) AS quantidade,
                    COALESCE(SUM(" . self::PESO . "),0) AS peso,
                    COUNT(DISTINCT n.idcliforemp) AS total_clientes,
                    COUNT(DISTINCT n.idnfs) AS total_notas,
                    COALESCE(SUM(COALESCE(ni.valor_comissao,0)),0) AS comissao
                $from", $p);
            $dev = $this->one("SELECT COALESCE(SUM(" . self::VAL_DEV . "),0) AS valor_dev,
                    COALESCE(SUM(ni.qt),0) AS qt_dev,
                    COALESCE(SUM(" . self::PESO_DEV . "),0) AS peso_dev,
                    COALESCE(SUM(" . self::COM_DEV . "),0) AS com_dev
                FROM nfe n
                JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                JOIN item i ON i.iditem = ni.iditem
                LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                WHERE n.status = 2 AND n.status_devolucao = 1
                  AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                  AND n.dataentrada BETWEEN :di AND :df" . $w . " AND ni.iditem = :it", $p);

            $valor = (float)$r['valor'] - (float)($dev['valor_dev'] ?? 0);
            $qt = (float)$r['quantidade'] - (float)($dev['qt_dev'] ?? 0);
            $peso = (float)$r['peso'] - (float)($dev['peso_dev'] ?? 0);
            $comissaoAjustada = (float)$r['comissao'] - (float)($dev['com_dev'] ?? 0);

            $clientes = $this->all("SELECT c.idcliforemp AS idcliente, c.fantasia AS cliente,
                    SUM(" . self::VAL . ") AS valor,
                    SUM(ni.qt) AS quantidade,
                    SUM(" . self::PESO . ") AS peso,
                    COUNT(DISTINCT n.idnfs) AS notas
                $from GROUP BY c.idcliforemp, c.fantasia ORDER BY valor DESC LIMIT 10", $p, 'valor', 'quantidade', 'peso');

            $mIni = $fim->modify('first day of this month')->modify('-5 month');
            $evo = $this->all("SELECT to_char(date_trunc('month', n.dataemissao), 'YYYY-MM') AS periodo,
                    SUM(" . self::VAL . ") AS valor,
                    SUM(ni.qt) AS quantidade,
                    SUM(" . self::PESO . ") AS peso
                " . $this->nfFrom('n.dataemissao BETWEEN :mi AND :df') . $w . " AND ni.iditem = :it
                GROUP BY 1 ORDER BY 1", ['mi' => $mIni->format('Y-m-d'), 'df' => $fim->format('Y-m-d'), 'it' => $idproduto], 'valor', 'quantidade', 'peso');
            $evo = $this->completarMeses($evo, $mIni, $fim);

            return [
                'produto' => [
                    'valor' => round($valor, 2),
                    'quantidade' => round($qt, 2),
                    'peso' => round($peso, 2),
                    'preco_medio' => $qt > 0 ? round($valor / $qt, 2) : 0,
                    'preco_medio_kg' => $peso > 0 ? round($valor / $peso, 2) : 0,
                    'total_clientes' => (int)$r['total_clientes'],
                    'total_notas' => (int)$r['total_notas'],
                    'comissao' => round($comissaoAjustada, 2),
                ],
                'clientes' => $clientes,
                'evolucao' => $evo,
            ];
        });
    }

    /** POST /v1/vendas/dashboard/cliente-detalhes */
    public function getClienteDetalhes(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $idcliente = (int)($in['idcliente'] ?? 0);
            if ($idcliente <= 0) {
                throw new \InvalidArgumentException('Cliente inválido.');
            }
            $p = ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d'), 'cl' => $idcliente];
            $w = $this->w($e, 'n') . ' AND n.idcliforemp = :cl';
            $from = $this->nfFrom() . $w;

            $r = $this->one("SELECT SUM(" . self::VAL . ") AS valor,
                    SUM(ni.qt) AS quantidade,
                    SUM(" . self::PESO . ") AS peso,
                    COUNT(DISTINCT n.idnfs) AS total_notas,
                    MAX(n.dataemissao) AS ultima_compra
                $from", $p);
            $notas = (int)($r['total_notas'] ?? 0);
            $valor = (float)($r['valor'] ?? 0);
            $peso = (float)($r['peso'] ?? 0);
            $qtd = (float)($r['quantidade'] ?? 0);

            $macroExpr = $this->regiaoMacroSql('rg', 'cid', 'cli');
            $info = $this->one("SELECT c.idcliforemp, c.fantasia AS cliente, c.cnpj, cid.descricao AS cidade, cid.uf AS estado,
                    ($macroExpr) AS regiao,
                    COALESCE(rg.descricao, 'SEM REGIAO') AS subregiao,
                    COALESCE(v.fantasia, '—') AS representante
                FROM cliforemp c
                LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
                LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
                LEFT JOIN cliforemp v ON v.idcliforemp = cli.idvendedor
                WHERE c.idcliforemp = :cl", ['cl' => $idcliente]);

            if (($info['regiao'] ?? '') === 'RED' && empty($e['pode_ver_redes'])) {
                throw new \DomainException('Você não tem permissão para visualizar clientes da rede corporativa (RED).');
            }

            // Última compra geral no ERP
            $ultGeral = $this->one("SELECT MAX(n.dataemissao) AS ultima_compra, COUNT(DISTINCT n.idnfs) AS total_nfs_vida
                FROM nfs n
                WHERE n.status = 2 AND n.idtransacao = 1 AND n.idcliforemp = :cl", ['cl' => $idcliente]);
            $ultimaCompraReal = $ultGeral['ultima_compra'] ?? $r['ultima_compra'] ?? null;
            $diasSemComprar = null;
            if ($ultimaCompraReal) {
                $dtUlt = new \DateTimeImmutable($ultimaCompraReal);
                $hoje = new \DateTimeImmutable('today');
                $diasSemComprar = $hoje >= $dtUlt ? (int)$hoje->diff($dtUlt)->days : 0;
            }

            // Se o cliente não comprou no período filtrado do dashboard, busca consolidado dos últimos 12 meses
            $modo = 'periodo';
            if ($notas === 0) {
                $modo = 'historico';
                $pHist = [
                    'di' => (new \DateTimeImmutable('-12 months'))->format('Y-m-d'),
                    'df' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                    'cl' => $idcliente
                ];
                $fromHist = $this->nfFrom('n.dataemissao BETWEEN :di AND :df') . ' AND n.idcliforemp = :cl';
                $rHist = $this->one("SELECT SUM(" . self::VAL . ") AS valor,
                        SUM(ni.qt) AS quantidade,
                        SUM(" . self::PESO . ") AS peso,
                        COUNT(DISTINCT n.idnfs) AS total_notas
                    $fromHist", $pHist);
                $notas = (int)($rHist['total_notas'] ?? 0);
                $valor = (float)($rHist['valor'] ?? 0);
                $peso = (float)($rHist['peso'] ?? 0);
                $qtd = (float)($rHist['quantidade'] ?? 0);

                $produtos = $this->all("SELECT i.iditem, i.descricao AS produto, i.referencia,
                        SUM(ni.qt) AS quantidade,
                        SUM(" . self::PESO . ") AS peso,
                        SUM(" . self::VAL . ") AS valor,
                        SUM(" . self::VAL . ") / NULLIF(SUM(ni.qt),0) AS preco_medio
                    $fromHist GROUP BY i.iditem, i.descricao, i.referencia ORDER BY valor DESC LIMIT 10",
                    $pHist, 'quantidade', 'peso', 'valor', 'preco_medio');
            } else {
                $produtos = $this->all("SELECT i.iditem, i.descricao AS produto, i.referencia,
                        SUM(ni.qt) AS quantidade,
                        SUM(" . self::PESO . ") AS peso,
                        SUM(" . self::VAL . ") AS valor,
                        SUM(" . self::VAL . ") / NULLIF(SUM(ni.qt),0) AS preco_medio
                    $from GROUP BY i.iditem, i.descricao, i.referencia ORDER BY valor DESC LIMIT 10",
                    $p, 'quantidade', 'peso', 'valor', 'preco_medio');
            }

            return [
                'cliente' => [
                    'idcliforemp' => $idcliente,
                    'cliente' => $info['cliente'] ?? null,
                    'cnpj' => $info['cnpj'] ?? null,
                    'cidade' => $info['cidade'] ?? null,
                    'estado' => $info['estado'] ?? null,
                    'regiao' => $info['regiao'] ?? null,
                    'subregiao' => $info['subregiao'] ?? null,
                    'representante' => $info['representante'] ?? null,
                    'valor' => round($valor, 2),
                    'quantidade' => round($qtd, 2),
                    'peso' => round($peso, 2),
                    'total_notas' => $notas,
                    'ticket_medio' => $notas > 0 ? round($valor / $notas, 2) : 0,
                    'ultima_compra' => $ultimaCompraReal,
                    'dias_sem_comprar' => $diasSemComprar,
                    'modo' => $modo,
                    'periodo_label' => $modo === 'historico' ? 'Histórico Geral (Últimos 12 meses)' : 'Período Selecionado',
                ],
                'top_produtos' => $produtos,
            ];
        });
    }

    /** POST /v1/vendas/dashboard/insights */
    public function getInsights(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $hoje = new \DateTimeImmutable('today');
            $ref = $fim < $hoje ? $fim : $hoje;
            $risco = $this->clientesRisco($e, $ref);
            return [
                'ranking_representantes' => $this->rankingRepresentantes($e, $ini, $fim),
                'funil_pedidos' => $this->funil($e, $ini, $fim),
                'projecao_fechamento' => $this->projecaoFechamento($e, $ref),
                'clientes_risco' => $risco['lista'],
                'resumo_risco' => $risco['resumo'],
                'risco_criterio' => ['dias_sem_comprar' => self::DIAS_RISCO, 'janela_dias' => self::JANELA_RISCO],
            ];
        });
    }

    /** POST /v1/vendas/dashboard/detalhes-card */
    public function getDetalhesCard(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $tipo = (string)($in['tipo'] ?? '');
            $regiaoFiltro = strtoupper(trim((string)($in['regiao'] ?? '')));
            $p = ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d')];
            $fmtDia = "to_char(%s, 'DD/MM/YYYY')";
            $wN = $this->w($e, 'n');
            $wP = $this->w($e, 'p');
            $extra = [];
            $rastreio = [];
            $porDia = [];
            $abaPadrao = 'resumo';
            $nomesDow = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

            switch ($tipo) {
                case 'Faturamento':
                case 'Regiao':
                    $abaPadrao = 'resumo';
                    $joinRegiao = '';
                    $whereRegiao = '';
                    if ($tipo === 'Regiao' && isset(self::REGIOES_MACRO[$regiaoFiltro])) {
                        if ($regiaoFiltro === 'RED' && empty($e['pode_ver_redes'])) {
                            throw new \DomainException('Acesso negado: região restrita.');
                        }
                        $macroSql = $this->regiaoMacroSql('rg', 'cid', 'cli');
                        $joinRegiao = " LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao LEFT JOIN cidade cid ON cid.idcidade = c.idcidade ";
                        $whereRegiao = " AND ($macroSql) = :regiao_macro ";
                        $p['regiao_macro'] = $regiaoFiltro;
                        $extra['subregioes'] = self::REGIOES_MACRO[$regiaoFiltro]['subregioes'] ?? '';
                    }
                    $nfFromCustom = $joinRegiao !== ''
                        ? str_replace('WHERE n.status', $joinRegiao . 'WHERE n.status', $this->nfFrom())
                        : $this->nfFrom();

                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(" . self::VAL . ") AS valor,
                            SUM(" . self::PESO . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            SUM(COALESCE(ni.valor_comissao, 0)) AS comissao,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $nfFromCustom . $wN . $whereRegiao . "
                        GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p,
                        'valor', 'peso', 'quantidade', 'comissao', 'documentos', 'clientes');
                    $totGeral = array_sum(array_column($rows, 'valor'));
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                        $r['percentual'] = $totGeral > 0 ? round($r['valor'] / $totGeral * 100, 1) : 0;
                    }
                    unset($r);

                    $porDia = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            EXTRACT(DOW FROM n.dataemissao)::int AS dow,
                            SUM(" . self::VAL . ") AS valor,
                            SUM(" . self::PESO . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $nfFromCustom . $wN . $whereRegiao . "
                        GROUP BY n.dataemissao ORDER BY n.dataemissao DESC", $p,
                        'valor', 'peso', 'quantidade', 'documentos', 'clientes');
                    foreach ($porDia as &$d) {
                        $d['sublabel'] = $nomesDow[(int)$d['dow']] ?? '';
                        $d['clientes'] = (int)$d['clientes'];
                        $d['peso'] = round($d['peso'], 2);
                        $d['quantidade'] = round($d['quantidade'], 2);
                        $d['ticket_medio'] = $d['documentos'] > 0 ? round($d['valor'] / $d['documentos'], 2) : 0;
                    }
                    unset($d);

                    $rastreio = $this->rastreioNfsFaturadas($wN . $whereRegiao, $p, 'n.dataemissao DESC, n.idnfs DESC', $joinRegiao);
                    $rotulo = 'NFs';
                    break;

                case 'Notas':
                case 'Pedidos':
                    $abaPadrao = 'rastreio';
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            EXTRACT(DOW FROM n.dataemissao)::int AS dow,
                            SUM(" . self::VAL . ") AS valor,
                            SUM(" . self::PESO . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $this->nfFrom() . $wN . "
                        GROUP BY n.dataemissao ORDER BY n.dataemissao DESC", $p,
                        'valor', 'peso', 'quantidade', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['sublabel'] = $nomesDow[(int)$r['dow']] ?? '';
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                    }
                    unset($r);

                    $rastreio = $this->rastreioNfsFaturadas($wN, $p, 'n.dataemissao DESC, n.idnfs DESC');
                    $rotulo = 'NFs Emitidas';
                    break;

                case 'Ticket Médio':
                    $abaPadrao = 'resumo';
                    $rows = $this->all("WITH nf_tot AS (
                            SELECT n.idnfs, n.idvendrepre, n.idcliforemp,
                                   COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS rep_nome,
                                   SUM(" . self::VAL . ") AS valor_nf,
                                   SUM(" . self::PESO . ") AS peso_nf,
                                   SUM(ni.qt) AS qt_nf
                            " . $this->nfFrom() . $wN . "
                            GROUP BY n.idnfs, n.idvendrepre, n.idcliforemp, v.fantasia
                        )
                        SELECT rep_nome AS label,
                               SUM(valor_nf) AS valor,
                               SUM(peso_nf) AS peso,
                               SUM(qt_nf) AS quantidade,
                               COUNT(DISTINCT idnfs) AS documentos,
                               COUNT(DISTINCT idcliforemp) AS clientes,
                               MIN(valor_nf) AS menor_nf,
                               MAX(valor_nf) AS maior_nf,
                               AVG(valor_nf) AS ticket_medio
                        FROM nf_tot
                        GROUP BY idvendrepre, rep_nome
                        ORDER BY ticket_medio DESC", $p,
                        'valor', 'peso', 'quantidade', 'documentos', 'clientes', 'menor_nf', 'maior_nf', 'ticket_medio');
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['menor_nf'] = round($r['menor_nf'], 2);
                        $r['maior_nf'] = round($r['maior_nf'], 2);
                        $r['ticket_medio'] = round($r['ticket_medio'], 2);
                    }
                    unset($r);

                    $rastreio = $this->rastreioNfsFaturadas($wN, $p, 'valor DESC, n.dataemissao DESC');
                    $rotulo = 'NFs';
                    break;

                case 'Vendas por Dia':
                    $abaPadrao = 'resumo';
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            EXTRACT(DOW FROM n.dataemissao)::int AS dow,
                            SUM(" . self::VAL . ") AS valor,
                            SUM(" . self::PESO . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $this->nfFrom() . $wN . "
                        GROUP BY n.dataemissao ORDER BY n.dataemissao DESC", $p,
                        'valor', 'peso', 'quantidade', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['sublabel'] = $nomesDow[(int)$r['dow']] ?? '';
                        $r['dia_semana'] = $r['sublabel'];
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                    }
                    unset($r);

                    $extra['por_dia_semana'] = $this->porDiaSemana($e, $ini, $fim);
                    $rastreio = $this->rastreioNfsFaturadas($wN, $p, 'n.dataemissao DESC, valor DESC');
                    $rotulo = 'NFs';
                    break;

                case 'Carteira':
                    $abaPadrao = 'rastreio';
                    $saldoExpr = 'GREATEST(pi.qt - COALESCE(pi.qtfaturado, 0), 0)';
                    $pesoPedExpr = "($saldoExpr * COALESCE(i.pesoliquido, 0))";
                    $pedJoinItem = str_replace('JOIN cliforemp c', 'JOIN item i ON i.iditem = pi.iditem JOIN cliforemp c', $this->pedFrom("p.status IN (1,4) AND $saldoExpr > 0"));

                    $rows = $this->all("SELECT COALESCE(vend.fantasia, 'REP ' || p.idvendrepre) AS label,
                            SUM($saldoExpr * pi.valor) AS valor,
                            SUM($pesoPedExpr) AS peso,
                            SUM($saldoExpr) AS quantidade,
                            COUNT(DISTINCT p.idpedido) AS documentos,
                            COUNT(DISTINCT p.idcliforemp) AS clientes
                        " . $pedJoinItem . $wP . "
                        GROUP BY p.idvendrepre, vend.fantasia ORDER BY valor DESC", $p,
                        'valor', 'peso', 'quantidade', 'documentos', 'clientes');
                    $totCart = array_sum(array_column($rows, 'valor'));
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                        $r['percentual'] = $totCart > 0 ? round($r['valor'] / $totCart * 100, 1) : 0;
                    }
                    unset($r);

                    $rastreio = $this->all("SELECT '#' || p.idpedido AS numero,
                            p.idpedido AS id_doc,
                            p.idcliforemp AS idcliente,
                            " . sprintf($fmtDia, 'p.data') . " AS data,
                            CASE p.status WHEN 1 THEN 'ABERTO' WHEN 4 THEN 'PARCIAL' ELSE 'CARTEIRA' END AS situacao,
                            c.fantasia AS cliente,
                            COALESCE(vend.fantasia, 'REP ' || p.idvendrepre) AS representante,
                            COALESCE(f.nome, 'Filial ' || p.idfilial) AS filial,
                            SUM($pesoPedExpr) AS peso,
                            SUM($saldoExpr) AS quantidade,
                            SUM($saldoExpr * pi.valor) AS valor
                        " . $pedJoinItem . $wP . "
                        GROUP BY p.idpedido, p.idcliforemp, p.data, p.status, c.fantasia, vend.fantasia, p.idvendrepre, f.nome, p.idfilial
                        ORDER BY p.data DESC, valor DESC LIMIT 200", $p, 'peso', 'quantidade', 'valor');
                    $rotulo = 'Pedidos';
                    break;

                case 'Cancelados':
                    $abaPadrao = 'rastreio';
                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(n.valortotalnota) AS valor,
                            SUM(COALESCE(n.pesoliquido, 0)) AS peso,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        FROM nfs n
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df"
                        . $wN . " GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p,
                        'valor', 'peso', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                    }
                    unset($r);

                    $rastreio = $this->all("SELECT COALESCE(CAST(n.numeronf AS text), '#' || n.idnfs) AS numero,
                            n.idnfs AS id_doc,
                            n.idcliforemp AS idcliente,
                            " . sprintf($fmtDia, 'n.dataemissao') . " AS data,
                            'CANCELADA' AS situacao,
                            c.fantasia AS cliente,
                            COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS representante,
                            COALESCE(f.nome, 'Filial ' || n.idfilial) AS filial,
                            COALESCE(n.pesoliquido, 0) AS peso,
                            n.valortotalnota AS valor
                        FROM nfs n
                        JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        LEFT JOIN filial f ON f.idfilial = n.idfilial
                        WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df"
                        . $wN . " ORDER BY n.dataemissao DESC, n.idnfs DESC LIMIT 200", $p, 'peso', 'valor');
                    $rotulo = 'NFs Canceladas';
                    break;

                case 'Devoluções':
                    $abaPadrao = 'rastreio';
                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(" . self::VAL_DEV . ") AS valor,
                            SUM(" . self::PESO_DEV . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            SUM(" . self::COM_DEV . ") AS comissao,
                            COUNT(DISTINCT n.idnfe) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        FROM nfe n
                        JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                        JOIN item i ON i.iditem = ni.iditem
                        LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                          AND n.dataentrada BETWEEN :di AND :df" . $wN . "
                        GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p,
                        'valor', 'peso', 'quantidade', 'comissao', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                    }
                    unset($r);

                    $rastreio = $this->all("SELECT COALESCE(CAST(n.numero AS text), '#' || n.idnfe) AS numero,
                            n.idnfe AS id_doc,
                            n.idcliforemp AS idcliente,
                            " . sprintf($fmtDia, 'n.dataentrada') . " AS data,
                            COALESCE(nt.descricao, 'DEVOLUÇÃO') AS situacao,
                            c.fantasia AS cliente,
                            COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS representante,
                            COALESCE(f.nome, 'Filial ' || n.idfilial) AS filial,
                            SUM(" . self::PESO_DEV . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            SUM(" . self::COM_DEV . ") AS comissao,
                            SUM(" . self::VAL_DEV . ") AS valor
                        FROM nfe n
                        JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                        JOIN item i ON i.iditem = ni.iditem
                        LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                        JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        LEFT JOIN filial f ON f.idfilial = n.idfilial
                        LEFT JOIN nfe_transacao nt ON nt.idtransacao = n.idtransacao
                        WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                          AND n.dataentrada BETWEEN :di AND :df" . $wN . "
                        GROUP BY n.idnfe, n.numero, n.idcliforemp, n.dataentrada, nt.descricao, c.fantasia, v.fantasia, n.idvendrepre, f.nome, n.idfilial
                        ORDER BY n.dataentrada DESC, valor DESC LIMIT 200", $p, 'peso', 'quantidade', 'comissao', 'valor');
                    $rotulo = 'NFs de Devolução';
                    break;

                case 'Positivação':
                    $abaPadrao = 'resumo';
                    $base = $this->baseClientesSql($e);
                    $rows = $this->all("WITH carteira_rep AS (
                            SELECT cl.idvendedor AS idrep, COUNT(DISTINCT cl.idcliforemp) AS qt_carteira
                            FROM cliente cl
                            JOIN cliforemp cf ON cf.idcliforemp = cl.idcliforemp AND cf.inativo = 'N'
                            WHERE cl.idvendedor IS NOT NULL
                            GROUP BY cl.idvendedor
                        ),
                        vendas_rep AS (
                            SELECT n.idvendrepre AS idrep,
                                   COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                                   SUM(" . self::VAL . ") AS valor,
                                   SUM(" . self::PESO . ") AS peso,
                                   SUM(ni.qt) AS quantidade,
                                   COUNT(DISTINCT n.idcliforemp) AS documentos,
                                   COUNT(DISTINCT n.idnfs) AS notas
                            " . $this->nfFrom() . $wN . " AND n.idcliforemp IN ($base)
                            GROUP BY n.idvendrepre, v.fantasia
                        )
                        SELECT vr.idrep,
                               vr.label,
                               vr.valor,
                               vr.peso,
                               vr.quantidade,
                               vr.documentos,
                               vr.notas,
                               COALESCE(cr.qt_carteira, 0) AS qt_carteira_bruta
                        FROM vendas_rep vr
                        LEFT JOIN carteira_rep cr ON cr.idrep = vr.idrep
                        ORDER BY vr.valor DESC", $p, 'valor', 'peso', 'quantidade', 'documentos', 'notas', 'qt_carteira_bruta');

                    foreach ($rows as &$r) {
                        $pos = (int)$r['documentos'];
                        $cart = max((int)$r['qt_carteira_bruta'], $pos);
                        $r['positivados'] = $pos;
                        $r['qt_carteira'] = $cart;
                        $r['clientes'] = $cart;
                        $r['notas'] = (int)$r['notas'];
                        $r['peso'] = round($r['peso'], 2);
                        $r['quantidade'] = round($r['quantidade'], 2);
                        $r['ticket_medio'] = $pos > 0 ? round($r['valor'] / $pos, 2) : 0;
                        $r['percentual'] = $cart > 0 ? round($pos / $cart * 100, 1) : 0.0;
                        unset($r['qt_carteira_bruta']);
                    }
                    unset($r);

                    $rastreio = $this->all("SELECT c.idcliforemp AS id_doc,
                            c.idcliforemp AS idcliente,
                            COALESCE(CAST(MAX(n.numeronf) AS text), '#' || c.idcliforemp) AS numero,
                            COALESCE(CAST(MAX(n.numeronf) AS text), '#' || c.idcliforemp) AS documento,
                            c.fantasia AS cliente,
                            COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS representante,
                            " . sprintf($fmtDia, 'MAX(n.dataemissao)') . " AS data,
                            COUNT(DISTINCT n.idnfs) AS notas,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            SUM(" . self::PESO . ") AS peso,
                            SUM(ni.qt) AS quantidade,
                            SUM(" . self::VAL . ") AS valor
                        " . $this->nfFrom() . $wN . " AND n.idcliforemp IN ($base)
                        GROUP BY c.idcliforemp, c.fantasia, v.fantasia, n.idvendrepre
                        ORDER BY valor DESC LIMIT 200", $p, 'peso', 'quantidade', 'valor', 'notas', 'documentos');
                    foreach ($rastreio as &$rc) {
                        $rc['notas'] = (int)$rc['notas'];
                        $rc['documentos'] = (int)$rc['documentos'];
                        $rc['peso'] = round($rc['peso'], 2);
                        $rc['quantidade'] = round($rc['quantidade'], 2);
                    }
                    unset($rc);

                    $rotulo = 'Clientes Positivados';
                    $extra['positivacao'] = $this->resumoPositivacao($e, $ini, $fim);
                    break;

                default:
                    throw new \InvalidArgumentException('Tipo de card inválido.');
            }

            $tv = 0.0; $td = 0; $tCart = 0; $tPeso = 0.0; $tQtd = 0.0;
            foreach ($rows as $r) {
                $tv += $r['valor'];
                $td += $r['documentos'];
                $tCart += (int)($r['qt_carteira'] ?? 0);
                $tPeso += (float)($r['peso'] ?? 0);
                $tQtd += (float)($r['quantidade'] ?? 0);
            }
            $total = [
                'valor' => round($tv, 2),
                'documentos' => $td,
                'peso' => round($tPeso, 2),
                'quantidade' => round($tQtd, 2),
            ];
            if ($tipo !== 'Positivação') {
                $total['ticket_medio'] = $td > 0 ? round($tv / $td, 2) : 0;
            } else {
                $posInfo = $extra['positivacao'];
                $total['documentos'] = (int)$posInfo['positivados'];
                $total['qt_carteira'] = max((int)$posInfo['base'], $tCart, (int)$posInfo['positivados']);
                $total['percentual'] = $total['qt_carteira'] > 0
                    ? round($total['documentos'] / $total['qt_carteira'] * 100, 1)
                    : 0;
                $total['ticket_medio'] = $total['documentos'] > 0 ? round($tv / $total['documentos'], 2) : 0;
            }

            return [
                'tipo' => $tipo,
                'aba_padrao' => $abaPadrao,
                'rotulo_documentos' => $rotulo,
                'dados' => $rows,
                'por_dia' => $porDia,
                'rastreio' => $rastreio,
                'total' => $total,
            ] + $extra;
        });
    }

    /** Lista rastreável de NFs faturadas no período e escopo. */
    private function rastreioNfsFaturadas(string $wN, array $p, string $orderBy = 'n.dataemissao DESC, n.idnfs DESC', string $extraJoin = ''): array
    {
        $nfFromCustom = $extraJoin !== ''
            ? str_replace('WHERE n.status', $extraJoin . 'WHERE n.status', $this->nfFrom())
            : $this->nfFrom();

        $rows = $this->all("SELECT COALESCE(CAST(n.numeronf AS text), '#' || n.idnfs) AS numero,
                n.idnfs AS id_doc,
                n.idcliforemp AS idcliente,
                MAX(ni.idpedido) AS idpedido,
                to_char(n.dataemissao, 'DD/MM/YYYY') AS data,
                c.fantasia AS cliente,
                COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS representante,
                COALESCE(f.nome, 'Filial ' || n.idfilial) AS filial,
                SUM(" . self::PESO . ") AS peso,
                SUM(ni.qt) AS quantidade,
                SUM(COALESCE(ni.valor_comissao, 0)) AS comissao,
                SUM(" . self::VAL . ") AS valor
            " . $nfFromCustom . $wN . "
            GROUP BY n.idnfs, n.numeronf, n.idcliforemp, n.dataemissao, c.fantasia, v.fantasia, n.idvendrepre, f.nome, n.idfilial
            ORDER BY $orderBy LIMIT 250", $p, 'peso', 'quantidade', 'comissao', 'valor');
        foreach ($rows as &$r) {
            $r['valor'] = round($r['valor'], 2);
            $r['peso'] = round($r['peso'], 2);
            $r['quantidade'] = round($r['quantidade'], 2);
            $r['comissao'] = round($r['comissao'], 2);
            $r['pedido'] = !empty($r['idpedido']) ? '#' . $r['idpedido'] : '—';
        }
        return $rows;
    }

    /** POST /v1/vendas/dashboard/detalhes-representante */
    public function getDetalhesRepresentante(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $p = ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d')];
            $from = $this->nfFrom() . $this->w($e, 'n');

            $clientes = $this->all("SELECT c.idcliforemp AS id, c.fantasia AS nome,
                    COUNT(DISTINCT n.idnfs) AS total_notas,
                    SUM(" . self::PESO . ") AS peso,
                    SUM(ni.qt) AS quantidade,
                    SUM(" . self::VAL . ") AS valor
                $from GROUP BY c.idcliforemp, c.fantasia ORDER BY valor DESC LIMIT 30", $p, 'peso', 'quantidade', 'valor');
            $produtos = $this->all("SELECT i.iditem, i.descricao AS produto,
                    SUM(ni.qt) AS quantidade,
                    SUM(" . self::PESO . ") AS peso,
                    SUM(" . self::VAL . ") AS valor
                $from GROUP BY i.iditem, i.descricao ORDER BY valor DESC LIMIT 10", $p, 'valor', 'peso', 'quantidade');
            $tot = $this->one("SELECT SUM(" . self::VAL . ") AS valor,
                    SUM(" . self::PESO . ") AS peso,
                    SUM(ni.qt) AS quantidade,
                    COUNT(DISTINCT n.idnfs) AS notas,
                    COUNT(DISTINCT n.idcliforemp) AS clientes
                $from", $p);

            return [
                'clientes' => $clientes,
                'produtos' => $produtos,
                'evolucao' => $this->evolucao($e, $ini, $fim),
                'total' => [
                    'valor' => round((float)$tot['valor'], 2),
                    'peso' => round((float)$tot['peso'], 2),
                    'quantidade' => round((float)$tot['quantidade'], 2),
                    'notas' => (int)$tot['notas'],
                    'clientes' => (int)$tot['clientes'],
                ],
            ];
        }, 'id');
    }

    /** POST /v1/vendas/dashboard/detalhes-funil */
    public function getDetalhesFunil(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $etapa = (string)($in['etapa'] ?? '');
            if (!isset(self::ETAPAS_FUNIL[$etapa])) {
                throw new \InvalidArgumentException('Etapa do funil inválida.');
            }
            $status = self::ETAPAS_FUNIL[$etapa];
            $p = ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d')];
            $from = str_replace('JOIN cliforemp c', 'JOIN item i ON i.iditem = pi.iditem JOIN cliforemp c', $this->pedFrom("p.status = $status", $status !== 3)) . $this->w($e, 'p');

            $tot = $this->one("SELECT COUNT(DISTINCT p.idpedido) AS quantidade,
                    COALESCE(SUM(pi.qt), 0) AS itens,
                    COALESCE(SUM(pi.qt * COALESCE(i.pesoliquido, 0)), 0) AS peso,
                    COALESCE(SUM(pi.qt * pi.valor),0) AS valor
                $from", $p);
            $pedidos = $this->all("SELECT p.idpedido, p.idcliforemp AS idcliente, to_char(p.data, 'DD/MM/YYYY') AS data, c.fantasia AS cliente,
                    vend.fantasia AS representante,
                    SUM(pi.qt * pi.valor) AS valor,
                    SUM(pi.qt * COALESCE(i.pesoliquido, 0)) AS peso,
                    SUM(pi.qt) AS quantidade
                $from GROUP BY p.idpedido, p.idcliforemp, p.data, c.fantasia, vend.fantasia ORDER BY p.data DESC, p.idpedido DESC LIMIT 80",
                $p, 'valor', 'peso', 'quantidade');

            return [
                'etapa' => $etapa,
                'pedidos' => $pedidos,
                'total' => [
                    'quantidade' => (int)$tot['quantidade'],
                    'itens' => round((float)$tot['itens'], 2),
                    'peso' => round((float)$tot['peso'], 2),
                    'valor' => round((float)$tot['valor'], 2),
                ],
                'truncado' => (int)$tot['quantidade'] > count($pedidos),
            ];
        });
    }

    // ======================================================================
    // INFRAESTRUTURA: execução, escopo, datas
    // ======================================================================

    private function run(Request $request, Response $response, callable $fn, string $repKey = 'representante'): Response
    {
        try {
            $in = json_decode((string)$request->getBody(), true);
            if (!is_array($in)) {
                $in = [];
            }
            $e = $this->escopo($request, $in, $repKey);
            $data = $fn($in, $e);
            return $this->jsonResponse($response, ['success' => true] + $data);
        } catch (\DomainException $ex) {
            $code = in_array($ex->getCode(), [401, 403], true) ? $ex->getCode() : 403;
            return $this->jsonResponse($response, ['success' => false, 'error' => $ex->getMessage()], $code);
        } catch (\InvalidArgumentException $ex) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $ex->getMessage()], 422);
        } catch (\Throwable $ex) {
            error_log('[VendasDashboard] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Não foi possível carregar os dados. Tente novamente.'], 500);
        }
    }

    private function escopo(Request $request, array $in, string $repKey = 'representante'): array
    {
        $s = UserScope::fromRequest($request, $this->pdo);
        if ($s->uid <= 0) {
            throw new \DomainException('Não autenticado.', 401);
        }

        $filiais = $s->resolverFiliais($in['filial'] ?? null);
        $gestores = $s->resolverGestores($in['gestor'] ?? null);
        $rep = (int)($in[$repKey] ?? 0);

        if ($rep > 0 && ($s->filialRestrita() || $s->gestorRestrito())) {
            $sql = 'SELECT 1 FROM vw_gestor_repre WHERE idrepresentante = ' . $rep;
            if ($s->gestorRestrito()) {
                $sql .= ' AND idgestor IN (' . implode(',', $gestores) . ')';
            }
            if ($s->filialRestrita()) {
                $sql .= ' AND idfilial IN (' . implode(',', $filiais) . ')';
            }
            if (!$this->pdo->query($sql . ' LIMIT 1')->fetchColumn()) {
                throw new \DomainException('Acesso negado: representante fora do seu escopo.');
            }
        }

        // Regra RED (Região 18): Apenas usuário com acesso a todas as filiais e a todos os usuários
        $podeVerRedes = $s->temAcessoTotal();

        return [
            'filiais' => $filiais,
            'gestores' => $gestores,
            'rep' => $rep,
            'pode_ver_redes' => $podeVerRedes,
            'scope' => $s,
        ];
    }

    private function w(array $e, string $a): string
    {
        $s = '';
        if ($e['filiais']) {
            $s .= " AND $a.idfilial IN (" . implode(',', array_map('intval', $e['filiais'])) . ')';
        }
        if ($e['gestores']) {
            $s .= " AND $a.idsupervisor IN (" . implode(',', array_map('intval', $e['gestores'])) . ')';
        }
        if ($e['rep'] > 0) {
            $s .= " AND $a.idvendrepre = " . (int)$e['rep'];
        }
        return $s;
    }

    private function parseData($v, \DateTimeImmutable $padrao): \DateTimeImmutable
    {
        if ($v === null || $v === '') {
            return $padrao;
        }
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new \InvalidArgumentException('Data inválida. Use o formato AAAA-MM-DD.');
        }
        return new \DateTimeImmutable($v);
    }

    private function periodo(array $in, int $max): array
    {
        $ini = $this->parseData($in['data_inicio'] ?? null, new \DateTimeImmutable('first day of this month'));
        $fim = $this->parseData($in['data_fim'] ?? null, new \DateTimeImmutable('last day of this month'));
        if ($ini > $fim) {
            throw new \InvalidArgumentException('A data inicial não pode ser maior que a data final.');
        }
        if ($this->dias($ini, $fim) > $max) {
            throw new \InvalidArgumentException("Período máximo de $max dias.");
        }
        return [$ini, $fim];
    }

    private function dias(\DateTimeImmutable $a, \DateTimeImmutable $b): int
    {
        return (int)$a->diff($b)->days + 1;
    }

    private function diasUteis(\DateTimeImmutable $a, \DateTimeImmutable $b): int
    {
        $n = 0;
        for ($d = $a; $d <= $b; $d = $d->modify('+1 day')) {
            if ((int)$d->format('N') < 6) {
                $n++;
            }
        }
        return $n;
    }

    private function periodoAnterior(\DateTimeImmutable $ini, \DateTimeImmutable $fim, \DateTimeImmutable $hoje): array
    {
        $aFim = $ini->modify('-1 day');
        $mesCompleto = $ini->format('d') === '01' && $fim->format('Y-m-d') === $fim->modify('last day of this month')->format('Y-m-d');
        if ($mesCompleto) {
            $n = ((int)$fim->format('Y') * 12 + (int)$fim->format('n')) - ((int)$ini->format('Y') * 12 + (int)$ini->format('n')) + 1;
            $aIni = $ini->modify("-$n month");
        } else {
            $aIni = $aFim->modify('-' . ($this->dias($ini, $fim) - 1) . ' day');
        }

        $parcial = false;
        if ($fim > $hoje && $ini <= $hoje) {
            $corte = $aIni->modify('+' . ($this->dias($ini, $hoje) - 1) . ' day');
            if ($corte < $aFim) {
                $aFim = $corte;
                $parcial = true;
            }
        }
        return ['ini' => $aIni, 'fim' => $aFim, 'parcial' => $parcial];
    }

    private function variacao($atual, $anterior): ?float
    {
        return $anterior > 0 ? round(($atual - $anterior) / $anterior * 100, 1) : null;
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    // ======================================================================
    // SQL BASES E HELPERS DE CONSULTA
    // ======================================================================

    private function nfFrom(string $periodo = 'n.dataemissao BETWEEN :di AND :df'): string
    {
        return "FROM nfs n
            JOIN nfs_item ni ON ni.idnfs = n.idnfs
            JOIN item i ON i.iditem = ni.iditem
            JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            WHERE n.status = 2 AND n.idtransacao = 1 AND $periodo";
    }

    private function pedFrom(string $status, bool $apenasAtivos = true): string
    {
        return "FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido" . ($apenasAtivos ? " AND pi.ativo = 'S'" : '') . "
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
            LEFT JOIN filial f ON f.idfilial = p.idfilial
            WHERE $status AND p.idtransacao IN (1,17) AND p.data BETWEEN :di AND :df";
    }

    private function pp(\DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        return ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d')];
    }

    private function one(string $sql, array $params): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function all(string $sql, array $params, ...$numericas): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            foreach ($numericas as $k) {
                if (is_string($k) && array_key_exists($k, $r) && $r[$k] !== null) {
                    $r[$k] = $k === 'documentos' ? (int)$r[$k] : (float)$r[$k];
                }
            }
        }
        return $rows;
    }

    // ======================================================================
    // KPIs
    // ======================================================================

    private function resumoFaturamento(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $r = $this->one("SELECT COALESCE(SUM(" . self::VAL . "),0) AS valor,
                COALESCE(SUM(ni.qt),0) AS quantidade,
                COALESCE(SUM(" . self::PESO . "),0) AS peso,
                COUNT(DISTINCT n.idnfs) AS notas,
                COUNT(DISTINCT n.idcliforemp) AS clientes
            " . $this->nfFrom() . $this->w($e, 'n'), $this->pp($ini, $fim));
        return [
            'valor' => (float)$r['valor'],
            'quantidade' => (float)$r['quantidade'],
            'peso' => (float)$r['peso'],
            'notas' => (int)$r['notas'],
            'clientes' => (int)$r['clientes'],
        ];
    }

    private function resumoCarteira(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $saldo = 'GREATEST(pi.qt - COALESCE(pi.qtfaturado, 0), 0)';
        $pedJoinItem = str_replace('JOIN cliforemp c', 'JOIN item i ON i.iditem = pi.iditem JOIN cliforemp c', $this->pedFrom("p.status IN (1,4) AND $saldo > 0"));
        $r = $this->one("SELECT COALESCE(SUM($saldo * pi.valor),0) AS valor,
                COALESCE(SUM($saldo),0) AS quantidade,
                COALESCE(SUM($saldo * COALESCE(i.pesoliquido, 0)),0) AS peso,
                COUNT(DISTINCT p.idpedido) AS pedidos,
                COUNT(DISTINCT p.idcliforemp) AS clientes
            " . $pedJoinItem . $this->w($e, 'p'), $this->pp($ini, $fim));
        return [
            'valor' => round((float)$r['valor'], 2),
            'quantidade' => round((float)$r['quantidade'], 2),
            'peso' => round((float)$r['peso'], 2),
            'pedidos' => (int)$r['pedidos'],
            'clientes' => (int)$r['clientes'],
        ];
    }

    private function resumoCancelados(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $r = $this->one("SELECT COALESCE(SUM(n.valortotalnota),0) AS valor,
                COALESCE(SUM(n.pesoliquido),0) AS peso,
                COUNT(*) AS notas
            FROM nfs n WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df" . $this->w($e, 'n'), $this->pp($ini, $fim));
        return [
            'valor' => round((float)$r['valor'], 2),
            'peso' => round((float)$r['peso'], 2),
            'notas' => (int)$r['notas'],
        ];
    }

    private function resumoDevolucoes(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $r = $this->one("SELECT COALESCE(SUM(" . self::VAL_DEV . "),0) AS valor,
                COALESCE(SUM(ni.qt),0) AS quantidade,
                COALESCE(SUM(" . self::PESO_DEV . "),0) AS peso,
                COUNT(DISTINCT n.idnfe) AS notas
            FROM nfe n
            JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
            JOIN item i ON i.iditem = ni.iditem
            WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
              AND n.dataentrada BETWEEN :di AND :df" . $this->w($e, 'n'), $this->pp($ini, $fim));
        return [
            'valor' => round((float)$r['valor'], 2),
            'quantidade' => round((float)$r['quantidade'], 2),
            'peso' => round((float)$r['peso'], 2),
            'notas' => (int)$r['notas'],
        ];
    }

    private function baseClientesSql(array $e): string
    {
        $vw = '';
        if ($e['gestores']) {
            $vw .= ' AND idgestor IN (' . implode(',', array_map('intval', $e['gestores'])) . ')';
        }
        if ($e['filiais']) {
            $vw .= ' AND idfilial IN (' . implode(',', array_map('intval', $e['filiais'])) . ')';
        }
        if ($e['rep'] > 0) {
            $vw .= ' AND idrepresentante = ' . (int)$e['rep'];
        }
        $sql = "SELECT cl.idcliforemp FROM cliente cl JOIN cliforemp cf ON cf.idcliforemp = cl.idcliforemp AND cf.inativo = 'N'";
        if ($vw !== '') {
            $sql .= " WHERE cl.idvendedor IN (SELECT idrepresentante FROM vw_gestor_repre WHERE 1=1$vw)";
        }
        return $sql;
    }

    private function resumoPositivacao(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $base = $this->baseClientesSql($e);
        $r = $this->one("SELECT
                (SELECT COUNT(*) FROM ($base) b) AS base,
                (SELECT COUNT(DISTINCT n.idcliforemp) FROM nfs n
                   WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df" . $this->w($e, 'n') . "
                   AND n.idcliforemp IN ($base)) AS positivados", $this->pp($ini, $fim));
        $b = (int)$r['base'];
        $pos = (int)$r['positivados'];
        return [
            'positivados' => $pos,
            'base' => $b,
            'nao_positivados' => max($b - $pos, 0),
            'percentual' => $b > 0 ? round($pos / $b * 100, 1) : 0,
        ];
    }

    private function diasComVenda(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): int
    {
        $r = $this->one("SELECT COUNT(DISTINCT n.dataemissao) AS d " . $this->nfFrom() . $this->w($e, 'n'), $this->pp($ini, $fim));
        return (int)$r['d'];
    }

    private function porDiaSemana(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("WITH d AS (
                SELECT n.dataemissao AS dt,
                       SUM(" . self::VAL . ") AS v,
                       SUM(" . self::PESO . ") AS p,
                       SUM(ni.qt) AS q,
                       COUNT(DISTINCT n.idnfs) AS nfs
                " . $this->nfFrom() . $this->w($e, 'n') . " GROUP BY n.dataemissao)
            SELECT EXTRACT(DOW FROM dt)::int AS dow,
                   AVG(v) AS media,
                   SUM(v) AS total,
                   AVG(p) AS media_peso,
                   SUM(p) AS total_peso,
                   SUM(q) AS total_qtd,
                   SUM(nfs) AS total_nfs,
                   COUNT(*) AS dias
            FROM d GROUP BY 1 ORDER BY 1", $this->pp($ini, $fim), 'media', 'total', 'media_peso', 'total_peso', 'total_qtd');
        $nomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        foreach ($rows as &$r) {
            $r['dia'] = $nomes[(int)$r['dow']];
            $r['dias'] = (int)$r['dias'];
            $r['total_nfs'] = (int)$r['total_nfs'];
            $r['media'] = round($r['media'], 2);
            $r['total'] = round($r['total'], 2);
            $r['media_peso'] = round($r['media_peso'], 2);
            $r['total_peso'] = round($r['total_peso'], 2);
            $r['total_qtd'] = round($r['total_qtd'], 2);
        }
        return $rows;
    }

    /** Top 10 Produtos com Valor, Peso e Quantidade */
    private function topProdutos(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim, float $faturamentoTotal = 0): array
    {
        $rows = $this->all("SELECT i.iditem, i.descricao AS produto, i.referencia, COALESCE(g.descricao, 'SEM GRUPO') AS grupo,
                SUM(ni.qt) AS quantidade,
                SUM(" . self::PESO . ") AS peso,
                COUNT(DISTINCT n.idnfs) AS total_notas,
                COUNT(DISTINCT n.idcliforemp) AS clientes,
                SUM(" . self::VAL . ") AS valor
            " . str_replace('WHERE n.status', 'LEFT JOIN grupo g ON g.idgrupo = i.idgrupo WHERE n.status', $this->nfFrom()) . $this->w($e, 'n') . "
            GROUP BY i.iditem, i.descricao, i.referencia, g.descricao ORDER BY valor DESC LIMIT 10",
            $this->pp($ini, $fim), 'quantidade', 'peso', 'valor');
        foreach ($rows as &$r) {
            $r['total_notas'] = (int)$r['total_notas'];
            $r['clientes'] = (int)$r['clientes'];
            $r['valor'] = round($r['valor'], 2);
            $r['peso'] = round($r['peso'], 2);
            $r['quantidade'] = round($r['quantidade'], 2);
            $r['preco_medio'] = $r['quantidade'] > 0 ? round($r['valor'] / $r['quantidade'], 2) : 0;
            $r['participacao'] = $faturamentoTotal > 0 ? round($r['valor'] / $faturamentoTotal * 100, 1) : 0;
        }
        return $rows;
    }

    /** Top 10 Clientes com Valor, Peso, Quantidade e Região Unificada */
    private function topClientes(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim, float $faturamentoTotal = 0): array
    {
        $macroSql = $this->regiaoMacroSql('rg', 'cid', 'cli');
        $filtroRedes = empty($e['pode_ver_redes']) ? " AND ($macroSql) <> 'RED' " : "";
        $rows = $this->all("SELECT c.idcliforemp, c.fantasia AS cliente,
                ($macroSql) AS regiao,
                COALESCE(cid.descricao || '/' || cid.uf, '') AS cidade_uf,
                COALESCE(v.fantasia, '—') AS representante,
                COUNT(DISTINCT n.idnfs) AS total_notas,
                SUM(ni.qt) AS quantidade,
                SUM(" . self::PESO . ") AS peso,
                SUM(" . self::VAL . ") AS valor
            " . str_replace('WHERE n.status', 'LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao LEFT JOIN cidade cid ON cid.idcidade = c.idcidade WHERE n.status', $this->nfFrom()) . $this->w($e, 'n') . " {$filtroRedes}
            GROUP BY c.idcliforemp, c.fantasia, ($macroSql), cid.descricao, cid.uf, v.fantasia
            ORDER BY valor DESC LIMIT 10", $this->pp($ini, $fim), 'quantidade', 'peso', 'valor');
        foreach ($rows as &$r) {
            $r['total_notas'] = (int)$r['total_notas'];
            $r['valor'] = round($r['valor'], 2);
            $r['peso'] = round($r['peso'], 2);
            $r['quantidade'] = round($r['quantidade'], 2);
            $r['ticket_medio'] = $r['total_notas'] > 0 ? round($r['valor'] / $r['total_notas'], 2) : 0;
            $r['participacao'] = $faturamentoTotal > 0 ? round($r['valor'] / $faturamentoTotal * 100, 1) : 0;
        }
        return $rows;
    }

    /**
     * Distribuição Unificada nas Macro-Regiões Oficiais: SUL, AVI, GRF, NOR, PLN, RMC e RED.
     * Retorna Valor (R$), Peso (Kg), Quantidade (Un), NFs, Clientes e Sub-regiões agrupadas.
     * A região RED (18) é restrita a usuários com acesso total (todas as filiais e todos os usuários).
     */
    private function distribuicaoRegiao(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $macroSql = $this->regiaoMacroSql('rg', 'cid', 'cli');
        $filtroRedes = empty($e['pode_ver_redes']) ? " AND ($macroSql) <> 'RED' " : "";

        $rows = $this->all("SELECT ($macroSql) AS regiao,
                SUM(" . self::VAL . ") AS valor,
                SUM(" . self::PESO . ") AS peso,
                SUM(ni.qt) AS quantidade,
                COUNT(DISTINCT n.idnfs) AS notas,
                COUNT(DISTINCT c.idcliforemp) AS clientes
            " . str_replace('WHERE n.status', 'LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao LEFT JOIN cidade cid ON cid.idcidade = c.idcidade WHERE n.status', $this->nfFrom()) . $this->w($e, 'n') . $filtroRedes . "
            GROUP BY 1 ORDER BY valor DESC", $this->pp($ini, $fim), 'valor', 'peso', 'quantidade', 'notas', 'clientes');

        $porSigla = [];
        $totValor = 0.0;
        $totPeso = 0.0;
        foreach ($rows as $r) {
            $sigla = $r['regiao'];
            $porSigla[$sigla] = $r;
            $totValor += (float)$r['valor'];
            $totPeso += (float)$r['peso'];
        }

        $out = [];
        foreach (self::REGIOES_MACRO as $sigla => $meta) {
            if ($sigla === 'RED' && empty($e['pode_ver_redes'])) {
                continue;
            }
            $d = $porSigla[$sigla] ?? null;
            if (!$d) {
                continue;
            }
            $val = round((float)$d['valor'], 2);
            $peso = round((float)$d['peso'], 2);
            $qtd = round((float)$d['quantidade'], 2);
            $nfs = (int)$d['notas'];
            $cli = (int)$d['clientes'];

            if ($val <= 0 && $peso <= 0 && $nfs <= 0 && $cli <= 0) {
                continue;
            }

            $out[] = [
                'regiao' => $sigla,
                'nome' => $meta['nome'],
                'label' => $sigla . ' — ' . $meta['nome'],
                'cor' => $meta['cor'],
                'subregioes' => $meta['subregioes'] ?? '',
                'ids' => $meta['ids'] ?? [],
                'is_redes' => ($sigla === 'RED'),
                'valor' => $val,
                'peso' => $peso,
                'quantidade' => $qtd,
                'notas' => $nfs,
                'clientes' => $cli,
                'ticket_medio' => $nfs > 0 ? round($val / $nfs, 2) : 0.0,
                'participacao' => $totValor > 0 ? round($val / $totValor * 100, 1) : 0.0,
                'participacao_peso' => $totPeso > 0 ? round($peso / $totPeso * 100, 1) : 0.0,
            ];
        }

        usort($out, fn($a, $b) => $b['valor'] <=> $a['valor']);
        return $out;
    }

    /**
     * Evolução de vendas com suporte a Valor (R$), Peso (Kg), Quantidade (Un) e NFs,
     * entregando as séries Diária, Semanal e Mensal para alternância instantânea no gráfico.
     */
    private function evolucao(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $totalDias = $this->dias($ini, $fim);
        $diariaPadrao = $totalDias <= self::DIAS_EVOLUCAO_DIARIA;

        // Consulta diária base (permite derivar dia, semana e mês com 1 única query no banco!)
        $rowsDia = $this->all("SELECT n.dataemissao AS periodo,
                SUM(" . self::VAL . ") AS valor,
                SUM(" . self::PESO . ") AS peso,
                SUM(ni.qt) AS quantidade,
                COUNT(DISTINCT n.idnfs) AS notas,
                COUNT(DISTINCT n.idcliforemp) AS clientes
            " . $this->nfFrom() . $this->w($e, 'n') . "
            GROUP BY 1 ORDER BY 1", $this->pp($ini, $fim), 'valor', 'peso', 'quantidade');

        $mapaDia = [];
        $mapaSemana = [];
        $mapaMes = [];

        foreach ($rowsDia as $r) {
            $dtStr = $r['periodo'];
            $dt = new \DateTimeImmutable($dtStr);
            $mapaDia[$dtStr] = $r;

            // Agrupamento semanal (segunda-feira da semana)
            $dow = (int)$dt->format('N');
            $segunda = $dt->modify('-' . ($dow - 1) . ' day')->format('Y-m-d');
            if (!isset($mapaSemana[$segunda])) {
                $mapaSemana[$segunda] = ['valor' => 0.0, 'peso' => 0.0, 'quantidade' => 0.0, 'notas' => 0, 'clientes' => 0];
            }
            $mapaSemana[$segunda]['valor'] += (float)$r['valor'];
            $mapaSemana[$segunda]['peso'] += (float)$r['peso'];
            $mapaSemana[$segunda]['quantidade'] += (float)$r['quantidade'];
            $mapaSemana[$segunda]['notas'] += (int)$r['notas'];
            $mapaSemana[$segunda]['clientes'] += (int)$r['clientes'];

            // Agrupamento mensal (1º dia do mês)
            $mesKey = $dt->format('Y-m-01');
            if (!isset($mapaMes[$mesKey])) {
                $mapaMes[$mesKey] = ['valor' => 0.0, 'peso' => 0.0, 'quantidade' => 0.0, 'notas' => 0, 'clientes' => 0];
            }
            $mapaMes[$mesKey]['valor'] += (float)$r['valor'];
            $mapaMes[$mesKey]['peso'] += (float)$r['peso'];
            $mapaMes[$mesKey]['quantidade'] += (float)$r['quantidade'];
            $mapaMes[$mesKey]['notas'] += (int)$r['notas'];
            $mapaMes[$mesKey]['clientes'] += (int)$r['clientes'];
        }

        // Série Diária
        $itensDia = [];
        if ($totalDias <= 120) {
            for ($d = $ini; $d <= $fim; $d = $d->modify('+1 day')) {
                $k = $d->format('Y-m-d');
                $itensDia[] = $this->itemEvo($k, $d->format('d/m'), $mapaDia[$k] ?? null);
            }
        }

        // Série Semanal
        $itensSemana = [];
        $iniSem = $ini->modify('-' . ((int)$ini->format('N') - 1) . ' day');
        for ($d = $iniSem; $d <= $fim; $d = $d->modify('+7 day')) {
            $k = $d->format('Y-m-d');
            $fimSem = $d->modify('+6 day');
            $lbl = $d->format('d/m') . ' a ' . $fimSem->format('d/m');
            $itensSemana[] = $this->itemEvo($k, $lbl, $mapaSemana[$k] ?? null);
        }

        // Série Mensal
        $itensMes = [];
        for ($d = $ini->modify('first day of this month'); $d <= $fim; $d = $d->modify('first day of next month')) {
            $k = $d->format('Y-m-01');
            $itensMes[] = $this->itemEvo($k, $d->format('m/Y'), $mapaMes[$k] ?? null);
        }

        return [
            'granularidade' => $diariaPadrao ? 'dia' : 'mes',
            'itens' => $diariaPadrao ? $itensDia : $itensMes,
            'series_periodo' => [
                'dia' => $itensDia,
                'semana' => $itensSemana,
                'mes' => $itensMes,
            ],
        ];
    }

    private function itemEvo(string $periodo, string $label, ?array $r): array
    {
        $val = $r ? round((float)$r['valor'], 2) : 0.0;
        $nfs = $r ? (int)$r['notas'] : 0;
        return [
            'periodo' => $periodo,
            'label' => $label,
            'valor' => $val,
            'peso' => $r ? round((float)$r['peso'], 2) : 0.0,
            'quantidade' => $r ? round((float)$r['quantidade'], 2) : 0.0,
            'notas' => $nfs,
            'clientes' => $r ? (int)($r['clientes'] ?? 0) : 0,
            'ticket_medio' => $nfs > 0 ? round($val / $nfs, 2) : 0.0,
        ];
    }

    private function completarMeses(array $rows, \DateTimeImmutable $mIni, \DateTimeImmutable $fim): array
    {
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r['periodo']] = $r;
        }
        $out = [];
        for ($d = $mIni; $d <= $fim; $d = $d->modify('first day of next month')) {
            $k = $d->format('Y-m');
            $out[] = [
                'periodo' => $k,
                'label' => $d->format('m/Y'),
                'valor' => round((float)($mapa[$k]['valor'] ?? 0), 2),
                'quantidade' => round((float)($mapa[$k]['quantidade'] ?? 0), 2),
                'peso' => round((float)($mapa[$k]['peso'] ?? 0), 2),
            ];
        }
        return $out;
    }

    // ======================================================================
    // DADOS DETALHADOS & PROJEÇÃO
    // ======================================================================

    private function crossSelling(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("WITH it AS (
                SELECT ni.idnfs, ni.iditem, SUM(" . self::VAL . ") AS v, SUM(" . self::PESO . ") AS p, SUM(ni.qt) AS q
                FROM nfs n JOIN nfs_item ni ON ni.idnfs = n.idnfs JOIN item i ON i.iditem = ni.iditem
                WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df" . $this->w($e, 'n') . "
                GROUP BY ni.idnfs, ni.iditem)
            SELECT i1.iditem AS id1, i1.descricao AS produto1, i2.iditem AS id2, i2.descricao AS produto2,
                   x.vezes_comprados_juntos, x.valor_total, x.peso_total, x.qtd_total
            FROM (
                SELECT a.iditem AS i1, b.iditem AS i2,
                       COUNT(*) AS vezes_comprados_juntos,
                       SUM(a.v + b.v) AS valor_total,
                       SUM(a.p + b.p) AS peso_total,
                       SUM(a.q + b.q) AS qtd_total
                FROM it a JOIN it b ON b.idnfs = a.idnfs AND b.iditem > a.iditem
                GROUP BY a.iditem, b.iditem ORDER BY 3 DESC, 4 DESC LIMIT 10) x
            JOIN item i1 ON i1.iditem = x.i1 JOIN item i2 ON i2.iditem = x.i2
            ORDER BY x.vezes_comprados_juntos DESC, x.valor_total DESC", $this->pp($ini, $fim), 'valor_total', 'peso_total', 'qtd_total');
        foreach ($rows as &$r) {
            $r['vezes_comprados_juntos'] = (int)$r['vezes_comprados_juntos'];
            $r['valor_total'] = round($r['valor_total'], 2);
            $r['peso_total'] = round($r['peso_total'], 2);
            $r['qtd_total'] = round($r['qtd_total'], 2);
        }
        return $rows;
    }

    /**
     * Histórico dos 3 últimos meses fechados + Mês Atual (realizado e projeção de fechamento)
     * com Valor (R$), Peso (Kg), Quantidade (Un) e NFs.
     */
    private function projecaoProximosMeses(array $e, \DateTimeImmutable $fim): array
    {
        $hoje = new \DateTimeImmutable('today');
        $mesAtualIni = $hoje->modify('first day of this month');
        $mesAtualFim = $hoje->modify('last day of this month');
        $mIni = $mesAtualIni->modify('-3 month');

        $rows = $this->all("SELECT to_char(date_trunc('month', n.dataemissao), 'YYYY-MM') AS periodo,
                SUM(" . self::VAL . ") AS valor,
                SUM(" . self::PESO . ") AS peso,
                SUM(ni.qt) AS quantidade,
                COUNT(DISTINCT n.idnfs) AS notas,
                COUNT(DISTINCT n.idcliforemp) AS clientes
            " . $this->nfFrom('n.dataemissao BETWEEN :di AND :df') . $this->w($e, 'n') . " GROUP BY 1",
            ['di' => $mIni->format('Y-m-d'), 'df' => $hoje->format('Y-m-d')], 'valor', 'peso', 'quantidade');

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r['periodo']] = $r;
        }

        // Carteira aberta do mês atual
        $carteiraMes = $this->resumoCarteira($e, $mesAtualIni, $mesAtualFim);

        $chaveAtual = $mesAtualIni->format('Y-m');
        $dAtual = $mapa[$chaveAtual] ?? ['valor' => 0, 'peso' => 0, 'quantidade' => 0, 'notas' => 0, 'clientes' => 0];
        $realizadoAtual = round((float)$dAtual['valor'], 2);
        $pesoAtual = round((float)$dAtual['peso'], 2);
        $qtdAtual = round((float)$dAtual['quantidade'], 2);
        $notasAtual = (int)$dAtual['notas'];

        $decorridos = $this->diasUteis($mesAtualIni, $hoje);
        $restantes = $hoje < $mesAtualFim ? $this->diasUteis($hoje->modify('+1 day'), $mesAtualFim) : 0;
        $totaisUteis = $decorridos + $restantes;

        $mediaDiaUtil = $decorridos > 0 ? ($realizadoAtual / $decorridos) : 0;
        $mediaPesoDia = $decorridos > 0 ? ($pesoAtual / $decorridos) : 0;
        $mediaQtdDia = $decorridos > 0 ? ($qtdAtual / $decorridos) : 0;

        $out = [];
        for ($k = 3; $k >= 1; $k--) {
            $d = $mesAtualIni->modify("-$k month");
            $fimMes = $d->modify('last day of this month');
            $uteisMes = $this->diasUteis($d, $fimMes);
            $ym = $d->format('Y-m');
            $info = $mapa[$ym] ?? ['valor' => 0, 'peso' => 0, 'quantidade' => 0, 'notas' => 0, 'clientes' => 0];
            $v = round((float)$info['valor'], 2);
            $p = round((float)$info['peso'], 2);
            $q = round((float)$info['quantidade'], 2);
            $n = (int)$info['notas'];

            $out[] = [
                'mes' => $d->format('Y-m-01'),
                'valor' => $v,
                'peso' => $p,
                'quantidade' => $q,
                'notas' => $n,
                'clientes' => (int)$info['clientes'],
                'dias_uteis' => $uteisMes,
                'media_diaria' => $uteisMes > 0 ? round($v / $uteisMes, 2) : 0,
                'tipo' => 'realizado',
            ];
        }

        // Histórico diário ponderado dos meses anteriores (60% M-1 + 40% M-2)
        $m1Media = $out[2]['media_diaria'] ?? 0;
        $m2Media = $out[1]['media_diaria'] ?? 0;
        $mediaHistPondValor = ($m1Media > 0 || $m2Media > 0)
            ? (($m1Media * 0.60) + ($m2Media * 0.40))
            : $mediaDiaUtil;

        $m1PesoDia = ($out[2]['dias_uteis'] ?? 0) > 0 ? ($out[2]['peso'] / $out[2]['dias_uteis']) : 0;
        $m2PesoDia = ($out[1]['dias_uteis'] ?? 0) > 0 ? ($out[1]['peso'] / $out[1]['dias_uteis']) : 0;
        $mediaHistPondPeso = ($m1PesoDia > 0 || $m2PesoDia > 0)
            ? (($m1PesoDia * 0.60) + ($m2PesoDia * 0.40))
            : $mediaPesoDia;

        $m1QtdDia = ($out[2]['dias_uteis'] ?? 0) > 0 ? ($out[2]['quantidade'] / $out[2]['dias_uteis']) : 0;
        $m2QtdDia = ($out[1]['dias_uteis'] ?? 0) > 0 ? ($out[1]['quantidade'] / $out[1]['dias_uteis']) : 0;
        $mediaHistPondQtd = ($m1QtdDia > 0 || $m2QtdDia > 0)
            ? (($m1QtdDia * 0.60) + ($m2QtdDia * 0.40))
            : $mediaQtdDia;

        // Ritmo diário estimado para o mês atual:
        // Se decorridos > 7 e realizadoAtual > 0, usamos o ritmo real do próprio mês.
        // Se estivermos no início do mês (decorridos <= 7) ou sem faturamento ainda (realizadoAtual == 0),
        // interpolamos suavemente com a média histórica para evitar distorções de projeção zerada.
        if ($decorridos > 7 && $realizadoAtual > 0) {
            $taxaProjecaoValor = $mediaDiaUtil;
            $taxaProjecaoPeso = $mediaPesoDia;
            $taxaProjecaoQtd = $mediaQtdDia;
        } else {
            $pesoRitmoAtual = ($realizadoAtual > 0 && $decorridos > 0) ? min($decorridos / 15.0, 1.0) : 0.0;
            $taxaProjecaoValor = ($mediaDiaUtil * $pesoRitmoAtual) + ($mediaHistPondValor * (1.0 - $pesoRitmoAtual));
            $taxaProjecaoPeso = ($mediaPesoDia * $pesoRitmoAtual) + ($mediaHistPondPeso * (1.0 - $pesoRitmoAtual));
            $taxaProjecaoQtd = ($mediaQtdDia * $pesoRitmoAtual) + ($mediaHistPondQtd * (1.0 - $pesoRitmoAtual));
        }

        $fechamentoEstimadoAtual = round($realizadoAtual + ($taxaProjecaoValor * $restantes), 2);
        $pesoProjetadoAtual = round($pesoAtual + ($taxaProjecaoPeso * $restantes), 2);
        $qtdProjetadaAtual = round($qtdAtual + ($taxaProjecaoQtd * $restantes), 2);

        $out[] = [
            'mes' => $mesAtualIni->format('Y-m-01'),
            'valor' => $realizadoAtual,
            'valor_projetado' => $fechamentoEstimadoAtual,
            'carteira_aberta' => $carteiraMes['valor'],
            'projecao_com_carteira' => round($fechamentoEstimadoAtual + $carteiraMes['valor'], 2),
            'peso' => $pesoAtual,
            'peso_projetado' => $pesoProjetadoAtual,
            'quantidade' => $qtdAtual,
            'quantidade_projetada' => $qtdProjetadaAtual,
            'notas' => $notasAtual,
            'clientes' => (int)$dAtual['clientes'],
            'dias_uteis' => $totaisUteis,
            'dias_decorridos' => $decorridos,
            'dias_restantes' => $restantes,
            'media_diaria' => round($taxaProjecaoValor, 2),
            'tipo' => 'atual',
        ];

        // Projeção M+1 (Próximo Mês) por Média Móvel Ponderada por Dia Útil
        $proxIni = $mesAtualIni->modify('+1 month');
        $proxFim = $proxIni->modify('last day of this month');
        $uteisProx = $this->diasUteis($proxIni, $proxFim);
        $mediaM0 = $totaisUteis > 0 ? ($fechamentoEstimadoAtual / $totaisUteis) : $mediaHistPondValor;
        $mediaPondValor = ($mediaM0 > 0)
            ? (($mediaM0 * 0.50) + ($m1Media * 0.30) + ($m2Media * 0.20))
            : (($m1Media * 0.60) + ($m2Media * 0.40));
        $mediaPondPeso = ($mediaHistPondPeso > 0) ? $mediaHistPondPeso : $mediaPesoDia;
        $mediaPondQtd = ($mediaHistPondQtd > 0) ? $mediaHistPondQtd : $mediaQtdDia;

        $out[] = [
            'mes' => $proxIni->format('Y-m-01'),
            'valor' => round($mediaPondValor * $uteisProx, 2),
            'valor_projetado' => round($mediaPondValor * $uteisProx, 2),
            'peso' => round($mediaPondPeso * $uteisProx, 2),
            'peso_projetado' => round($mediaPondPeso * $uteisProx, 2),
            'quantidade' => round($mediaPondQtd * $uteisProx, 0),
            'quantidade_projetada' => round($mediaPondQtd * $uteisProx, 0),
            'notas' => 0,
            'clientes' => 0,
            'dias_uteis' => $uteisProx,
            'media_diaria' => round($mediaPondValor, 2),
            'tipo' => 'projecao',
        ];

        return $out;
    }

    // ======================================================================
    // INSIGHTS EXECUTIVOS
    // ======================================================================

    /** Top 10 Representantes com Valor, Peso, Quantidade, NFs e Clientes */
    private function rankingRepresentantes(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT n.idvendrepre AS id, COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS nome,
                SUM(" . self::VAL . ") AS valor,
                SUM(" . self::PESO . ") AS peso,
                SUM(ni.qt) AS quantidade,
                COUNT(DISTINCT n.idnfs) AS total_notas,
                COUNT(DISTINCT n.idcliforemp) AS clientes_ativos
            " . $this->nfFrom() . $this->w($e, 'n') . " GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC LIMIT 10",
            $this->pp($ini, $fim), 'valor', 'peso', 'quantidade', 'total_notas', 'clientes_ativos');
        $totGeral = array_sum(array_column($rows, 'valor'));
        foreach ($rows as &$r) {
            $r['total_notas'] = (int)$r['total_notas'];
            $r['clientes_ativos'] = (int)$r['clientes_ativos'];
            $r['valor'] = round($r['valor'], 2);
            $r['peso'] = round($r['peso'], 2);
            $r['quantidade'] = round($r['quantidade'], 2);
            $r['ticket_medio'] = $r['total_notas'] > 0 ? round($r['valor'] / $r['total_notas'], 2) : 0;
            $r['participacao'] = $totGeral > 0 ? round($r['valor'] / $totGeral * 100, 1) : 0;
        }
        return $rows;
    }

    /** Funil de pedidos por status com Valor, Peso e Quantidade */
    private function funil(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT p.status,
                COUNT(DISTINCT p.idpedido) AS quantidade,
                COALESCE(SUM(pi.qt), 0) AS itens,
                COALESCE(SUM(pi.qt * COALESCE(i.pesoliquido, 0)), 0) AS peso,
                COALESCE(SUM(pi.qt * pi.valor),0) AS valor
            FROM pedido p
            LEFT JOIN pedido_item pi ON pi.idpedido = p.idpedido AND (pi.ativo = 'S' OR p.status = 3)
            LEFT JOIN item i ON i.iditem = pi.iditem
            WHERE p.status IN (1,3,4,5) AND p.idtransacao IN (1,17) AND p.data BETWEEN :di AND :df" . $this->w($e, 'p') . "
            GROUP BY p.status", $this->pp($ini, $fim), 'valor', 'peso', 'itens');
        $porStatus = [];
        foreach ($rows as $r) {
            $porStatus[(int)$r['status']] = $r;
        }
        $etapas = [];
        foreach (self::ETAPAS_FUNIL as $nome => $st) {
            $etapas[] = [
                'etapa' => $nome,
                'status' => $st,
                'quantidade' => (int)($porStatus[$st]['quantidade'] ?? 0),
                'itens' => round((float)($porStatus[$st]['itens'] ?? 0), 2),
                'peso' => round((float)($porStatus[$st]['peso'] ?? 0), 2),
                'valor' => round((float)($porStatus[$st]['valor'] ?? 0), 2),
            ];
        }
        $q = array_column($etapas, 'quantidade', 'status');
        $v = array_column($etapas, 'valor', 'status');
        $baseQ = $q[1] + $q[4] + $q[5];
        $baseV = $v[1] + $v[4] + $v[5];
        $totQ = $baseQ + $q[3];
        return [
            'etapas' => $etapas,
            'taxa_conversao' => $baseQ > 0 ? round($q[5] / $baseQ * 100, 1) : null,
            'taxa_conversao_valor' => $baseV > 0 ? round($v[5] / $baseV * 100, 1) : null,
            'taxa_cancelamento' => $totQ > 0 ? round($q[3] / $totQ * 100, 1) : null,
        ];
    }

    /**
     * Projeção Completa de Fechamento do Mês Corrente:
     * - Globais (Valor, Peso, Quantidade, NFs, Carteira Aberta, Ritmo Diário, Comparativo vs Mês Anterior)
     * - Desdobramento por Representante (para o modal expandido e exportação Excel)
     * - Desdobramento pelas 6 Macro-Regiões (SUL, GRF, AVI, NOR, PLN, RMC)
     */
    private function projecaoFechamento(array $e, \DateTimeImmutable $ref): array
    {
        $hoje = new \DateTimeImmutable('today');
        $mIni = $hoje->modify('first day of this month');
        $mFim = $hoje->modify('last day of this month');

        $mAntIni = $mIni->modify('-1 month');
        $mAntFim = $mIni->modify('-1 day');

        $atual = $this->resumoFaturamento($e, $mIni, $hoje);
        $mesPassado = $this->resumoFaturamento($e, $mAntIni, $mAntFim);
        $carteira = $this->resumoCarteira($e, $mIni, $mFim);

        $decorridos = max($this->diasUteis($mIni, $hoje), 1);
        $restantes = $hoje < $mFim ? $this->diasUteis($hoje->modify('+1 day'), $mFim) : 0;
        $totaisUteis = $decorridos + $restantes;
        $diasUteisAnt = max($this->diasUteis($mAntIni, $mAntFim), 20);

        $mediaValorAnt = $mesPassado['valor'] > 0 ? ($mesPassado['valor'] / $diasUteisAnt) : 0;
        $mediaPesoAnt = $mesPassado['peso'] > 0 ? ($mesPassado['peso'] / $diasUteisAnt) : 0;
        $mediaQtdAnt = $mesPassado['quantidade'] > 0 ? ($mesPassado['quantidade'] / $diasUteisAnt) : 0;

        if ($decorridos > 7 && $atual['valor'] > 0) {
            $mediaValor = $atual['valor'] / $decorridos;
            $mediaPeso = $atual['peso'] / $decorridos;
            $mediaQtd = $atual['quantidade'] / $decorridos;
        } else {
            $pesoRitmo = ($atual['valor'] > 0 && $decorridos > 0) ? min($decorridos / 15.0, 1.0) : 0.0;
            $mediaValor = (($atual['valor'] / $decorridos) * $pesoRitmo) + ($mediaValorAnt * (1.0 - $pesoRitmo));
            $mediaPeso = (($atual['peso'] / $decorridos) * $pesoRitmo) + ($mediaPesoAnt * (1.0 - $pesoRitmo));
            $mediaQtd = (($atual['quantidade'] / $decorridos) * $pesoRitmo) + ($mediaQtdAnt * (1.0 - $pesoRitmo));
        }
        $mediaNotas = $atual['notas'] / $decorridos;

        $adicionalValor = $mediaValor * $restantes;
        $projValor = $atual['valor'] + $adicionalValor;
        $projPeso = $atual['peso'] + ($mediaPeso * $restantes);
        $projQtd = $atual['quantidade'] + ($mediaQtd * $restantes);
        $projNotas = (int)round($atual['notas'] + ($mediaNotas * $restantes));

        // Desdobramento por Representante no Mês Atual vs Mês Passado
        $wN = $this->w($e, 'n');
        $wP = $this->w($e, 'p');
        $saldo = 'GREATEST(pi.qt - COALESCE(pi.qtfaturado, 0), 0)';

        $repsRows = $this->all("WITH fat_atual AS (
                SELECT n.idvendrepre AS idrep,
                       COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS nome,
                       SUM(" . self::VAL . ") AS realizado,
                       SUM(" . self::PESO . ") AS peso_realizado,
                       SUM(ni.qt) AS qtd_realizada,
                       COUNT(DISTINCT n.idnfs) AS notas,
                       COUNT(DISTINCT n.idcliforemp) AS clientes
                " . $this->nfFrom('n.dataemissao BETWEEN :mi AND :hoje') . $wN . "
                GROUP BY n.idvendrepre, v.fantasia
            ),
            fat_ant AS (
                SELECT n.idvendrepre AS idrep,
                       COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS nome,
                       SUM(" . self::VAL . ") AS valor_ant,
                       SUM(" . self::PESO . ") AS peso_ant
                " . $this->nfFrom('n.dataemissao BETWEEN :ai AND :af') . $wN . "
                GROUP BY n.idvendrepre, v.fantasia
            ),
            cart_atual AS (
                SELECT p.idvendrepre AS idrep,
                       SUM($saldo * pi.valor) AS carteira_valor,
                       SUM($saldo * COALESCE(i.pesoliquido, 0)) AS carteira_peso,
                       COUNT(DISTINCT p.idpedido) AS carteira_pedidos
                FROM pedido p
                JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
                JOIN item i ON i.iditem = pi.iditem
                WHERE p.status IN (1,4) AND $saldo > 0 AND p.idtransacao IN (1,17)
                  AND p.data BETWEEN :mi AND :mf" . $wP . "
                GROUP BY p.idvendrepre
            )
            SELECT COALESCE(fa.idrep, fant.idrep) AS idrep,
                   COALESCE(fa.nome, fant.nome, 'REP') AS representante,
                   COALESCE(fa.realizado, 0) AS realizado,
                   COALESCE(fa.peso_realizado, 0) AS peso_realizado,
                   COALESCE(fa.qtd_realizada, 0) AS qtd_realizada,
                   COALESCE(fa.notas, 0) AS notas,
                   COALESCE(fa.clientes, 0) AS clientes,
                   COALESCE(fant.valor_ant, 0) AS mes_anterior,
                   COALESCE(fant.peso_ant, 0) AS peso_anterior,
                   COALESCE(ca.carteira_valor, 0) AS carteira_valor,
                   COALESCE(ca.carteira_peso, 0) AS carteira_peso,
                   COALESCE(ca.carteira_pedidos, 0) AS carteira_pedidos
            FROM fat_atual fa
            FULL OUTER JOIN fat_ant fant ON fant.idrep = fa.idrep
            LEFT JOIN cart_atual ca ON ca.idrep = COALESCE(fa.idrep, fant.idrep)
            WHERE COALESCE(fa.realizado, 0) > 0 OR COALESCE(fant.valor_ant, 0) > 0 OR COALESCE(ca.carteira_valor, 0) > 0
            ORDER BY COALESCE(fa.realizado, 0) DESC",
            [
                'mi' => $mIni->format('Y-m-d'),
                'hoje' => $hoje->format('Y-m-d'),
                'mf' => $mFim->format('Y-m-d'),
                'ai' => $mAntIni->format('Y-m-d'),
                'af' => $mAntFim->format('Y-m-d'),
            ],
            'realizado', 'peso_realizado', 'qtd_realizada', 'mes_anterior', 'peso_anterior', 'carteira_valor', 'carteira_peso'
        );

        $porRepresentante = [];
        foreach ($repsRows as $rr) {
            $real = round((float)$rr['realizado'], 2);
            $pesoR = round((float)$rr['peso_realizado'], 2);
            $qtdR = round((float)$rr['qtd_realizada'], 2);
            $cartV = round((float)$rr['carteira_valor'], 2);
            $cartP = round((float)$rr['carteira_peso'], 2);
            $antV = round((float)$rr['mes_anterior'], 2);
            $antP = round((float)$rr['peso_anterior'], 2);

            $medDiaAnt = $antV > 0 ? ($antV / $diasUteisAnt) : 0;
            $medPesoDiaAnt = $antP > 0 ? ($antP / $diasUteisAnt) : 0;

            if ($decorridos > 7 && $real > 0) {
                $medDia = $real / $decorridos;
                $medPesoDia = $pesoR / $decorridos;
                $medQtdDia = $qtdR / $decorridos;
            } else {
                $pesoRitmo = ($real > 0 && $decorridos > 0) ? min($decorridos / 15.0, 1.0) : 0.0;
                $medDia = (($real / $decorridos) * $pesoRitmo) + ($medDiaAnt * (1.0 - $pesoRitmo));
                $medPesoDia = (($pesoR / $decorridos) * $pesoRitmo) + ($medPesoDiaAnt * (1.0 - $pesoRitmo));
                $medQtdDia = $qtdR / $decorridos;
            }

            $projRep = round($real + ($medDia * $restantes), 2);
            $projPesoRep = round($pesoR + ($medPesoDia * $restantes), 2);
            $projQtdRep = round($qtdR + ($medQtdDia * $restantes), 2);

            $porRepresentante[] = [
                'id' => (int)$rr['idrep'],
                'representante' => $rr['representante'],
                'realizado' => $real,
                'peso_realizado' => $pesoR,
                'qtd_realizada' => $qtdR,
                'notas' => (int)$rr['notas'],
                'clientes' => (int)$rr['clientes'],
                'carteira_valor' => $cartV,
                'carteira_peso' => $cartP,
                'carteira_pedidos' => (int)$rr['carteira_pedidos'],
                'media_dia_util' => round($medDia, 2),
                'media_peso_dia' => round($medPesoDia, 2),
                'projecao' => $projRep,
                'projecao_com_carteira' => round($projRep + $cartV, 2),
                'peso_projetado' => $projPesoRep,
                'qtd_projetada' => $projQtdRep,
                'mes_anterior' => $antV,
                'peso_anterior' => $antP,
                'variacao_projecao' => $this->variacao($projRep, $antV),
            ];
        }

        // Projeção pelas 6 Regiões Unificadas no mês atual
        $regioesMes = $this->distribuicaoRegiao($e, $mIni, $hoje);
        $porRegiao = [];
        foreach ($regioesMes as $rg) {
            $medRegVal = $rg['valor'] / $decorridos;
            $medRegPeso = $rg['peso'] / $decorridos;
            $porRegiao[] = [
                'regiao' => $rg['regiao'],
                'nome' => $rg['nome'],
                'realizado' => $rg['valor'],
                'peso_realizado' => $rg['peso'],
                'quantidade_realizada' => $rg['quantidade'],
                'notas' => $rg['notas'],
                'clientes' => $rg['clientes'],
                'media_dia_util' => round($medRegVal, 2),
                'projecao' => round($rg['valor'] + ($medRegVal * $restantes), 2),
                'peso_projetado' => round($rg['peso'] + ($medRegPeso * $restantes), 2),
                'participacao' => $rg['participacao'],
            ];
        }

        return [
            'carregado' => true,
            'mes_referencia' => $mIni->format('Y-m'),
            'realizado' => round($atual['valor'], 2),
            'peso_realizado' => round($atual['peso'], 2),
            'quantidade_realizada' => round($atual['quantidade'], 2),
            'notas_realizadas' => $atual['notas'],
            'clientes_positivados' => $atual['clientes'],
            'carteira_valor' => $carteira['valor'],
            'carteira_peso' => $carteira['peso'],
            'carteira_quantidade' => $carteira['quantidade'],
            'carteira_pedidos' => $carteira['pedidos'],
            'media_dia_util' => round($mediaValor, 2),
            'media_peso_dia_util' => round($mediaPeso, 2),
            'media_qtd_dia_util' => round($mediaQtd, 1),
            'dias_uteis_totais' => $totaisUteis,
            'dias_uteis_decorridos' => $decorridos,
            'dias_uteis_restantes' => $restantes,
            'pct_tempo_decorrido' => $totaisUteis > 0 ? round($decorridos / $totaisUteis * 100, 1) : 100,
            'projecao_adicional' => round($adicionalValor, 2),
            'projecao' => round($projValor, 2),
            'projecao_com_carteira' => round($projValor + $carteira['valor'], 2),
            'peso_projetado' => round($projPeso, 2),
            'quantidade_projetada' => round($projQtd, 2),
            'notas_projetadas' => $projNotas,
            'mes_anterior_valor' => round($mesPassado['valor'], 2),
            'mes_anterior_peso' => round($mesPassado['peso'], 2),
            'mes_anterior_notas' => $mesPassado['notas'],
            'variacao_vs_mes_anterior' => $this->variacao($projValor, $mesPassado['valor']),
            'por_representante' => $porRepresentante,
            'por_regiao' => $porRegiao,
        ];
    }

    /**
     * Inteligência de Recompra & Clientes Ativos com 30+ dias sem comprar:
     * Retorna lista completa com NFs Históricas (compras_anteriores), Peso Histórico (kg),
     * Quantidade Histórica (un), Faturamento Histórico (R$), Ticket Médio, Região Unificada (6 regiões)
     * e Faixa de Inatividade (30-45d, 46-60d, 61-90d, +90d).
     */
    private function clientesRisco(array $e, \DateTimeImmutable $ref): array
    {
        $hoje = new \DateTimeImmutable('today');
        $jIni = $hoje->modify('-' . self::JANELA_RISCO . ' day');
        $limite = $hoje->modify('-' . self::DIAS_RISCO . ' day');
        $repFiltroSql = '';
        $params = ['ji' => $jIni->format('Y-m-d'), 'ref' => $hoje->format('Y-m-d'), 'lim' => $limite->format('Y-m-d')];
        if (!empty($e['rep']) && (int)$e['rep'] > 0) {
            $repFiltroSql = ' AND COALESCE(cl.idvendedor, u.ult_rep) = :rep_escopo ';
            $params['rep_escopo'] = (int)$e['rep'];
        }

        $macroSql = $this->regiaoMacroSql('rg', 'cid', 'cl');
        if (empty($e['pode_ver_redes'])) {
            $repFiltroSql .= " AND ($macroSql) <> 'RED' ";
        }

        $rows = $this->all("WITH compras AS (
                SELECT n.idcliforemp,
                       n.idvendrepre,
                       n.dataemissao AS d,
                       SUM(" . self::VAL . ") AS v,
                       COUNT(DISTINCT n.idnfs) AS nfs_dia,
                       SUM(ni.qt) AS qt_dia,
                       SUM(" . self::PESO . ") AS peso_dia
                FROM nfs n
                JOIN nfs_item ni ON ni.idnfs = n.idnfs
                JOIN item i ON i.iditem = ni.iditem
                WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :ji AND :ref" . $this->w($e, 'n') . "
                GROUP BY n.idcliforemp, n.idvendrepre, n.dataemissao
                UNION ALL
                SELECT p.idcliforemp, p.idvendrepre, p.data AS d, 0 AS v, 0 AS nfs_dia, 0 AS qt_dia, 0 AS peso_dia
                FROM pedido p
                WHERE p.status IN (1,4,5) AND p.idtransacao IN (1,17) AND p.data BETWEEN :ji AND :ref" . $this->w($e, 'p') . "
            ),
            ult AS (
                SELECT idcliforemp,
                       MAX(d) AS ultima,
                       SUM(v) AS historico,
                       SUM(nfs_dia) AS compras_anteriores,
                       SUM(qt_dia) AS quantidade_historico,
                       SUM(peso_dia) AS peso_historico,
                       (ARRAY_AGG(idvendrepre ORDER BY d DESC))[1] AS ult_rep
                FROM compras
                GROUP BY idcliforemp
            )
            SELECT u.idcliforemp AS id,
                   c.fantasia AS nome,
                   COALESCE(cid.descricao || '/' || cid.uf, '') AS cidade_uf,
                   ($macroSql) AS regiao,
                   COALESCE(cl.idvendedor, u.ult_rep) AS idrepresentante,
                   COALESCE(v.fantasia, 'REP ' || COALESCE(cl.idvendedor, u.ult_rep)) AS representante,
                   to_char(u.ultima, 'DD/MM/YYYY') AS ultima_fmt,
                   u.ultima,
                   u.compras_anteriores,
                   u.quantidade_historico,
                   u.peso_historico,
                   u.historico AS valor_historico,
                   (:ref::date - u.ultima) AS dias_sem_comprar
            FROM ult u
            JOIN cliforemp c ON c.idcliforemp = u.idcliforemp AND c.inativo = 'N'
            LEFT JOIN cliente cl ON cl.idcliforemp = u.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN regiao rg ON rg.idregiao = cl.idregiao
            LEFT JOIN cliforemp v ON v.idcliforemp = COALESCE(cl.idvendedor, u.ult_rep)
            WHERE u.ultima <= :lim" . $repFiltroSql . "
            ORDER BY u.historico DESC, v.fantasia ASC",
            $params, 'valor_historico', 'peso_historico', 'quantidade_historico', 'compras_anteriores');

        $resumoFaixas = [
            '30_45' => ['label' => '30 a 45 dias', 'clientes' => 0, 'valor' => 0.0, 'peso' => 0.0],
            '46_60' => ['label' => '46 a 60 dias', 'clientes' => 0, 'valor' => 0.0, 'peso' => 0.0],
            '61_90' => ['label' => '61 a 90 dias', 'clientes' => 0, 'valor' => 0.0, 'peso' => 0.0],
            '90_mais' => ['label' => '+90 dias', 'clientes' => 0, 'valor' => 0.0, 'peso' => 0.0],
        ];

        $totValorRisco = 0.0;
        $totPesoRisco = 0.0;
        $totQtdRisco = 0.0;
        $totNfsRisco = 0;

        foreach ($rows as &$r) {
            $r['idrepresentante'] = (int)$r['idrepresentante'];
            $r['dias_sem_comprar'] = (int)$r['dias_sem_comprar'];
            $r['compras_anteriores'] = (int)$r['compras_anteriores'];
            $r['valor_historico'] = round((float)$r['valor_historico'], 2);
            $r['peso_historico'] = round((float)$r['peso_historico'], 2);
            $r['quantidade_historico'] = round((float)$r['quantidade_historico'], 2);
            $r['ticket_medio'] = $r['compras_anteriores'] > 0
                ? round($r['valor_historico'] / $r['compras_anteriores'], 2)
                : 0.0;

            $dias = $r['dias_sem_comprar'];
            if ($dias <= 45) {
                $faixa = '30_45';
            } elseif ($dias <= 60) {
                $faixa = '46_60';
            } elseif ($dias <= 90) {
                $faixa = '61_90';
            } else {
                $faixa = '90_mais';
            }
            $r['faixa'] = $faixa;

            $resumoFaixas[$faixa]['clientes']++;
            $resumoFaixas[$faixa]['valor'] = round($resumoFaixas[$faixa]['valor'] + $r['valor_historico'], 2);
            $resumoFaixas[$faixa]['peso'] = round($resumoFaixas[$faixa]['peso'] + $r['peso_historico'], 2);

            $totValorRisco += $r['valor_historico'];
            $totPesoRisco += $r['peso_historico'];
            $totQtdRisco += $r['quantidade_historico'];
            $totNfsRisco += $r['compras_anteriores'];
        }
        unset($r);

        return [
            'lista' => $rows,
            'resumo' => [
                'total_clientes' => count($rows),
                'valor_historico_total' => round($totValorRisco, 2),
                'peso_historico_total' => round($totPesoRisco, 2),
                'quantidade_historico_total' => round($totQtdRisco, 2),
                'nfs_historico_total' => $totNfsRisco,
                'faixas' => $resumoFaixas,
            ],
        ];
    }
}
