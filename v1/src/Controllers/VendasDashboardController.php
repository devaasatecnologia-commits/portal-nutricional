<?php
namespace Nutricional\Controllers;

use Nutricional\Utils\UserScope;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Dashboard de Vendas.
 *
 * Regras de negócio:
 *  - Faturamento = NFs faturadas (nfs.status=2, idtransacao=1), mesma definição do Cubo de Vendas.
 *  - Carteira de pedidos = pedidos abertos/parciais (status 1 e 4, idtransacao 1/17), card separado.
 *  - Ticket médio = faturamento ÷ nº de NFs distintas.
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

    /** Valor devolvido e comissão devolvida por item: idênticos ao Cubo de Vendas. */
    private const VAL_DEV = "(ni.valortotal + COALESCE(ni.valoripi, 0) + COALESCE(ni.valorsubsttribicms, 0) + COALESCE(ni.valorfrete, 0) + COALESCE(ni.valorseguro, 0) + COALESCE(ni.valoroutrasdespesas, 0) - COALESCE(ni.valor_desconto, 0))";
    private const COM_DEV = "CASE WHEN COALESCE(ni.valor_comissao, 0) > 0 THEN ni.valor_comissao WHEN COALESCE(ni_orig.perc_comissao, 0) > 0 THEN ROUND(" . self::VAL_DEV . " * (ni_orig.perc_comissao / 100.0), 2) ELSE 0 END";

    /** Transações de devolução de venda (tabela nfe, status=2 e status_devolucao=1), alinhadas ao Cubo de Vendas. */
    private const TRANS_DEVOLUCAO = '8,9,15,1000,1001,1004,1005,1007,1011,1014,1015,1020,1024,1025,1031,1032,1033';

    private const ETAPAS_FUNIL = [
        'Abertos' => 1,
        'Faturados Parcial' => 4,
        'Faturados' => 5,
        'Cancelados' => 3,
    ];

    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
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

            return [
                'periodo' => ['inicio' => $ini->format('Y-m-d'), 'fim' => $fim->format('Y-m-d'), 'dias' => $this->dias($ini, $fim)],
                'periodo_anterior' => [
                    'inicio' => $ant['ini']->format('Y-m-d'),
                    'fim' => $ant['fim']->format('Y-m-d'),
                    'parcial' => $ant['parcial'],
                ],
                'faturamento' => [
                    'valor' => round($atual['valor'], 2),
                    'anterior' => round($anterior['valor'], 2),
                    'variacao' => $this->variacao($atual['valor'], $anterior['valor']),
                    'quantidade' => $atual['quantidade'],
                ],
                'notas' => [
                    'atual' => $atual['notas'],
                    'anterior' => $notasAnt,
                    'variacao' => $this->variacao($atual['notas'], $notasAnt),
                ],
                'ticket_medio' => [
                    'atual' => round($ticket, 2),
                    'anterior' => round($ticketAnt, 2),
                    'variacao' => $this->variacao($ticket, $ticketAnt),
                ],
                'carteira' => $this->resumoCarteira($e, $ini, $fim),
                'cancelados' => $this->resumoCancelados($e, $ini, $fim),
                'devolucoes' => $this->resumoDevolucoes($e, $ini, $fim),
                'positivacao' => $this->resumoPositivacao($e, $ini, $fim),
                'velocidade_venda' => [
                    'media_diaria' => $diasUteis > 0 ? round($atual['valor'] / $diasUteis, 2) : 0,
                    'media_dia_com_venda' => $diasComVenda > 0 ? round($atual['valor'] / $diasComVenda, 2) : 0,
                    'dias_uteis' => $diasUteis,
                    'dias_com_venda' => $diasComVenda,
                    'por_dia_semana' => $this->porDiaSemana($e, $ini, $fim),
                ],
                'top_produtos' => $this->topProdutos($e, $ini, $fim),
                'top_clientes' => $this->topClientes($e, $ini, $fim),
                'distribuicao_regiao' => $this->distribuicaoRegiao($e, $ini, $fim),
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
                $out['mensagem'] = 'Período acima de ' . self::MAX_DIAS_PESADO . ' dias: margem por produto e produtos comprados juntos não foram calculados. Reduza o período.';
                return $out;
            }

            $out['margem_produtos'] = $this->margemProdutos($e, $ini, $fim);
            $out['matriz_cross_selling'] = $this->crossSelling($e, $ini, $fim);
            return $out;
        });
    }

    /** POST /v1/vendas/dashboard/produto-detalhes */
    public function getProdutoDetalhes(Request $request, Response $response): Response
    {
        return $this->run($request, $response, function (array $in, array $e) {
            [$ini, $fim] = $this->periodo($in, self::MAX_DIAS);
            $idproduto = (int)($in['idproduto'] ?? 0);
            if ($idproduto <= 0) {
                throw new \InvalidArgumentException('Produto inválido.');
            }
            $p = ['di' => $ini->format('Y-m-d'), 'df' => $fim->format('Y-m-d'), 'it' => $idproduto];
            $w = $this->w($e, 'n');
            $from = $this->nfFrom() . $w . ' AND ni.iditem = :it';

            $r = $this->one("SELECT COALESCE(SUM(" . self::VAL . "),0) AS valor, COALESCE(SUM(ni.qt),0) AS quantidade,
                    COUNT(DISTINCT n.idcliforemp) AS total_clientes, COUNT(DISTINCT n.idnfs) AS total_notas,
                    COALESCE(SUM(COALESCE(ni.valor_comissao,0)),0) AS comissao
                $from", $p);
            $dev = $this->one("SELECT COALESCE(SUM(" . self::VAL_DEV . "),0) AS valor_dev,
                    COALESCE(SUM(ni.qt),0) AS qt_dev,
                    COALESCE(SUM(" . self::COM_DEV . "),0) AS com_dev
                FROM nfe n
                JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                WHERE n.status = 2 AND n.status_devolucao = 1
                  AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                  AND n.dataentrada BETWEEN :di AND :df" . $w . " AND ni.iditem = :it", $p);

            $valor = (float)$r['valor'] - (float)($dev['valor_dev'] ?? 0);
            $qt = (float)$r['quantidade'] - (float)($dev['qt_dev'] ?? 0);
            $comissaoAjustada = (float)$r['comissao'] - (float)($dev['com_dev'] ?? 0);

            $clientes = $this->all("SELECT c.fantasia AS cliente, SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade
                $from GROUP BY c.idcliforemp, c.fantasia ORDER BY valor DESC LIMIT 10", $p, 'valor', 'quantidade');

            // Evolução dos 6 meses até o mês de data_fim, mesmo escopo.
            $mIni = $fim->modify('first day of this month')->modify('-5 month');
            $evo = $this->all("SELECT to_char(date_trunc('month', n.dataemissao), 'YYYY-MM') AS periodo,
                    SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade
                " . $this->nfFrom('n.dataemissao BETWEEN :mi AND :df') . $w . " AND ni.iditem = :it
                GROUP BY 1 ORDER BY 1", ['mi' => $mIni->format('Y-m-d'), 'df' => $fim->format('Y-m-d'), 'it' => $idproduto], 'valor', 'quantidade');
            $evo = $this->completarMeses($evo, $mIni, $fim);

            return [
                'produto' => [
                    'valor' => round($valor, 2),
                    'quantidade' => $qt,
                    'preco_medio' => $qt > 0 ? round($valor / $qt, 2) : 0,
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

            $r = $this->one("SELECT SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade,
                    COUNT(DISTINCT n.idnfs) AS total_notas, MAX(n.dataemissao) AS ultima_compra
                $from", $p);
            $notas = (int)$r['total_notas'];
            $valor = (float)$r['valor'];

            $info = null;
            if ($notas > 0) {
                $info = $this->one("SELECT c.fantasia AS cliente, c.cnpj, cid.descricao AS cidade, cid.uf AS estado,
                        COALESCE(rg.descricao, 'SEM REGIAO') AS regiao
                    FROM cliforemp c
                    LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                    LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
                    LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
                    WHERE c.idcliforemp = :cl", ['cl' => $idcliente]);
            }

            $produtos = $this->all("SELECT i.descricao AS produto, i.referencia, SUM(ni.qt) AS quantidade,
                    SUM(" . self::VAL . ") AS valor, SUM(" . self::VAL . ") / NULLIF(SUM(ni.qt),0) AS preco_medio
                $from GROUP BY i.iditem, i.descricao, i.referencia ORDER BY valor DESC LIMIT 10", $p, 'quantidade', 'valor', 'preco_medio');

            return [
                'cliente' => [
                    'cliente' => $info['cliente'] ?? null,
                    'cnpj' => $info['cnpj'] ?? null,
                    'cidade' => $info['cidade'] ?? null,
                    'estado' => $info['estado'] ?? null,
                    'regiao' => $info['regiao'] ?? null,
                    'valor' => round($valor, 2),
                    'quantidade' => (float)$r['quantidade'],
                    'total_notas' => $notas,
                    'ticket_medio' => $notas > 0 ? round($valor / $notas, 2) : 0,
                    'ultima_compra' => $r['ultima_compra'],
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
            return [
                'ranking_representantes' => $this->rankingRepresentantes($e, $ini, $fim),
                'funil_pedidos' => $this->funil($e, $ini, $fim),
                'projecao_fechamento' => $this->projecaoFechamento($e, $ref),
                'clientes_risco' => $this->clientesRisco($e, $ref),
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
                    $abaPadrao = 'resumo';
                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(" . self::VAL . ") AS valor,
                            SUM(COALESCE(ni.valor_comissao, 0)) AS comissao,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $this->nfFrom() . $wN . "
                        GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p,
                        'valor', 'comissao', 'documentos', 'clientes');
                    $totGeral = array_sum(array_column($rows, 'valor'));
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
                        $r['ticket_medio'] = $r['documentos'] > 0 ? round($r['valor'] / $r['documentos'], 2) : 0;
                        $r['percentual'] = $totGeral > 0 ? round($r['valor'] / $totGeral * 100, 1) : 0;
                    }
                    unset($r);

                    $porDia = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            EXTRACT(DOW FROM n.dataemissao)::int AS dow,
                            SUM(" . self::VAL . ") AS valor,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $this->nfFrom() . $wN . "
                        GROUP BY n.dataemissao ORDER BY n.dataemissao DESC", $p,
                        'valor', 'documentos', 'clientes');
                    foreach ($porDia as &$d) {
                        $d['sublabel'] = $nomesDow[(int)$d['dow']] ?? '';
                        $d['clientes'] = (int)$d['clientes'];
                        $d['ticket_medio'] = $d['documentos'] > 0 ? round($d['valor'] / $d['documentos'], 2) : 0;
                    }
                    unset($d);

                    $rastreio = $this->rastreioNfsFaturadas($wN, $p, 'n.dataemissao DESC, n.idnfs DESC');
                    $rotulo = 'NFs';
                    break;

                case 'Notas':
                case 'Pedidos':
                    $abaPadrao = 'rastreio';
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            EXTRACT(DOW FROM n.dataemissao)::int AS dow,
                            SUM(" . self::VAL . ") AS valor,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $this->nfFrom() . $wN . "
                        GROUP BY n.dataemissao ORDER BY n.dataemissao DESC", $p,
                        'valor', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['sublabel'] = $nomesDow[(int)$r['dow']] ?? '';
                        $r['clientes'] = (int)$r['clientes'];
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
                                   SUM(" . self::VAL . ") AS valor_nf
                            " . $this->nfFrom() . $wN . "
                            GROUP BY n.idnfs, n.idvendrepre, n.idcliforemp, v.fantasia
                        )
                        SELECT rep_nome AS label,
                               SUM(valor_nf) AS valor,
                               COUNT(DISTINCT idnfs) AS documentos,
                               COUNT(DISTINCT idcliforemp) AS clientes,
                               MIN(valor_nf) AS menor_nf,
                               MAX(valor_nf) AS maior_nf,
                               AVG(valor_nf) AS ticket_medio
                        FROM nf_tot
                        GROUP BY idvendrepre, rep_nome
                        ORDER BY ticket_medio DESC", $p,
                        'valor', 'documentos', 'clientes', 'menor_nf', 'maior_nf', 'ticket_medio');
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
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
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        " . $this->nfFrom() . $wN . "
                        GROUP BY n.dataemissao ORDER BY n.dataemissao DESC", $p,
                        'valor', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['sublabel'] = $nomesDow[(int)$r['dow']] ?? '';
                        $r['clientes'] = (int)$r['clientes'];
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
                    $rows = $this->all("SELECT COALESCE(vend.fantasia, 'REP ' || p.idvendrepre) AS label,
                            SUM($saldoExpr * pi.valor) AS valor,
                            COUNT(DISTINCT p.idpedido) AS documentos,
                            COUNT(DISTINCT p.idcliforemp) AS clientes
                        " . $this->pedFrom("p.status IN (1,4) AND $saldoExpr > 0") . $wP . "
                        GROUP BY p.idvendrepre, vend.fantasia ORDER BY valor DESC", $p,
                        'valor', 'documentos', 'clientes');
                    $totCart = array_sum(array_column($rows, 'valor'));
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
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
                            SUM($saldoExpr * pi.valor) AS valor
                        " . $this->pedFrom("p.status IN (1,4) AND $saldoExpr > 0") . $wP . "
                        GROUP BY p.idpedido, p.idcliforemp, p.data, p.status, c.fantasia, vend.fantasia, p.idvendrepre, f.nome, p.idfilial
                        ORDER BY p.data DESC, valor DESC LIMIT 200", $p, 'valor');
                    $rotulo = 'Pedidos';
                    break;

                case 'Cancelados':
                    $abaPadrao = 'rastreio';
                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(n.valortotalnota) AS valor,
                            COUNT(DISTINCT n.idnfs) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        FROM nfs n
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df"
                        . $wN . " GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p,
                        'valor', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
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
                            n.valortotalnota AS valor
                        FROM nfs n
                        JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        LEFT JOIN filial f ON f.idfilial = n.idfilial
                        WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df"
                        . $wN . " ORDER BY n.dataemissao DESC, n.idnfs DESC LIMIT 200", $p, 'valor');
                    $rotulo = 'NFs Canceladas';
                    break;

                case 'Devoluções':
                    $abaPadrao = 'rastreio';
                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(" . self::VAL_DEV . ") AS valor,
                            SUM(" . self::COM_DEV . ") AS comissao,
                            COUNT(DISTINCT n.idnfe) AS documentos,
                            COUNT(DISTINCT n.idcliforemp) AS clientes
                        FROM nfe n
                        JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                        LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                          AND n.dataentrada BETWEEN :di AND :df" . $wN . "
                        GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p,
                        'valor', 'comissao', 'documentos', 'clientes');
                    foreach ($rows as &$r) {
                        $r['clientes'] = (int)$r['clientes'];
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
                            SUM(" . self::COM_DEV . ") AS comissao,
                            SUM(" . self::VAL_DEV . ") AS valor
                        FROM nfe n
                        JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                        LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                        JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                        LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
                        LEFT JOIN filial f ON f.idfilial = n.idfilial
                        LEFT JOIN nfe_transacao nt ON nt.idtransacao = n.idtransacao
                        WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                          AND n.dataentrada BETWEEN :di AND :df" . $wN . "
                        GROUP BY n.idnfe, n.numero, n.idcliforemp, n.dataentrada, nt.descricao, c.fantasia, v.fantasia, n.idvendrepre, f.nome, n.idfilial
                        ORDER BY n.dataentrada DESC, valor DESC LIMIT 200", $p, 'comissao', 'valor');
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
                                   COUNT(DISTINCT n.idcliforemp) AS documentos,
                                   COUNT(DISTINCT n.idnfs) AS notas
                            " . $this->nfFrom() . $wN . " AND n.idcliforemp IN ($base)
                            GROUP BY n.idvendrepre, v.fantasia
                        )
                        SELECT vr.idrep,
                               vr.label,
                               vr.valor,
                               vr.documentos,
                               vr.notas,
                               COALESCE(cr.qt_carteira, 0) AS qt_carteira_bruta
                        FROM vendas_rep vr
                        LEFT JOIN carteira_rep cr ON cr.idrep = vr.idrep
                        ORDER BY vr.valor DESC", $p, 'valor', 'documentos', 'notas', 'qt_carteira_bruta');

                    foreach ($rows as &$r) {
                        $pos = (int)$r['documentos'];
                        $cart = max((int)$r['qt_carteira_bruta'], $pos);
                        $r['positivados'] = $pos;
                        $r['qt_carteira'] = $cart;
                        $r['clientes'] = $cart;
                        $r['notas'] = (int)$r['notas'];
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
                            SUM(" . self::VAL . ") AS valor
                        " . $this->nfFrom() . $wN . " AND n.idcliforemp IN ($base)
                        GROUP BY c.idcliforemp, c.fantasia, v.fantasia, n.idvendrepre
                        ORDER BY valor DESC LIMIT 200", $p, 'valor', 'notas', 'documentos');
                    foreach ($rastreio as &$rc) {
                        $rc['notas'] = (int)$rc['notas'];
                        $rc['documentos'] = (int)$rc['documentos'];
                    }
                    unset($rc);

                    $rotulo = 'Clientes Positivados';
                    $extra['positivacao'] = $this->resumoPositivacao($e, $ini, $fim);
                    break;

                default:
                    throw new \InvalidArgumentException('Tipo de card inválido.');
            }

            $tv = 0.0; $td = 0; $tCart = 0;
            foreach ($rows as $r) {
                $tv += $r['valor'];
                $td += $r['documentos'];
                $tCart += (int)($r['qt_carteira'] ?? 0);
            }
            $total = ['valor' => round($tv, 2), 'documentos' => $td];
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
    private function rastreioNfsFaturadas(string $wN, array $p, string $orderBy = 'n.dataemissao DESC, n.idnfs DESC'): array
    {
        $rows = $this->all("SELECT COALESCE(CAST(n.numeronf AS text), '#' || n.idnfs) AS numero,
                n.idnfs AS id_doc,
                n.idcliforemp AS idcliente,
                MAX(ni.idpedido) AS idpedido,
                to_char(n.dataemissao, 'DD/MM/YYYY') AS data,
                c.fantasia AS cliente,
                COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS representante,
                COALESCE(f.nome, 'Filial ' || n.idfilial) AS filial,
                SUM(COALESCE(ni.valor_comissao, 0)) AS comissao,
                SUM(" . self::VAL . ") AS valor
            " . $this->nfFrom() . $wN . "
            GROUP BY n.idnfs, n.numeronf, n.idcliforemp, n.dataemissao, c.fantasia, v.fantasia, n.idvendrepre, f.nome, n.idfilial
            ORDER BY $orderBy LIMIT 200", $p, 'comissao', 'valor');
        foreach ($rows as &$r) {
            $r['valor'] = round($r['valor'], 2);
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

            $clientes = $this->all("SELECT c.idcliforemp AS id, c.fantasia AS nome, COUNT(DISTINCT n.idnfs) AS total_notas,
                    SUM(" . self::VAL . ") AS valor
                $from GROUP BY c.idcliforemp, c.fantasia ORDER BY valor DESC LIMIT 30", $p, 'valor');
            $produtos = $this->all("SELECT i.descricao AS produto, SUM(ni.qt) AS quantidade, SUM(" . self::VAL . ") AS valor
                $from GROUP BY i.iditem, i.descricao ORDER BY valor DESC LIMIT 5", $p, 'valor', 'quantidade');
            $tot = $this->one("SELECT SUM(" . self::VAL . ") AS valor, COUNT(DISTINCT n.idnfs) AS notas, COUNT(DISTINCT n.idcliforemp) AS clientes $from", $p);

            return [
                'clientes' => $clientes,
                'produtos' => $produtos,
                'evolucao' => $this->evolucao($e, $ini, $fim),
                'total' => ['valor' => round((float)$tot['valor'], 2), 'notas' => (int)$tot['notas'], 'clientes' => (int)$tot['clientes']],
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
            $from = $this->pedFrom("p.status = $status", $status !== 3) . $this->w($e, 'p');

            $tot = $this->one("SELECT COUNT(DISTINCT p.idpedido) AS quantidade, COALESCE(SUM(pi.qt * pi.valor),0) AS valor $from", $p);
            $pedidos = $this->all("SELECT p.idpedido, to_char(p.data, 'DD/MM/YYYY') AS data, c.fantasia AS cliente,
                    vend.fantasia AS representante, SUM(pi.qt * pi.valor) AS valor, SUM(pi.qt) AS quantidade
                $from GROUP BY p.idpedido, p.data, c.fantasia, vend.fantasia ORDER BY p.data DESC, p.idpedido DESC LIMIT 50",
                $p, 'valor', 'quantidade');

            return [
                'etapa' => $etapa,
                'pedidos' => $pedidos,
                'total' => ['quantidade' => (int)$tot['quantidade'], 'valor' => round((float)$tot['valor'], 2)],
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

    /**
     * Helper único de escopo (filiais, gestores e representante), usado por todos os endpoints.
     * Identidade sempre do JWT; pedido fora do escopo => DomainException (403).
     *
     * @return array{filiais:int[],gestores:int[],rep:int}
     */
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

        return ['filiais' => $filiais, 'gestores' => $gestores, 'rep' => $rep];
    }

    /** Predicado de escopo para tabelas com idfilial/idsupervisor/idvendrepre (nfs, nfe, pedido). */
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

    /** @return \DateTimeImmutable[] [inicio, fim] */
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

    /**
     * Período anterior: meses calendário completos => mesmos meses imediatamente anteriores;
     * caso contrário, intervalo imediatamente anterior de mesma duração.
     * Se o período corrente ainda está em andamento, compara com o mesmo nº de dias decorridos.
     */
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

    /** NFs faturadas (mesma regra do Cubo). Aliases: n, ni, i, c (cliente), v (representante), f (filial). */
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

    /** Pedidos (carteira/funil). Aliases: p, pi, c, vend. */
    private function pedFrom(string $status, bool $apenasAtivos = true): string
    {
        return "FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido" . ($apenasAtivos ? " AND pi.ativo = 'S'" : '') . "
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
            LEFT JOIN filial f ON f.idfilial = p.idfilial
            WHERE $status AND p.idtransacao IN (1,17) AND p.data BETWEEN :di AND :df";
    }

    private function devFrom(): string
    {
        return 'FROM nfe n WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (' . self::TRANS_DEVOLUCAO . ') AND n.dataentrada BETWEEN :di AND :df';
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

    /**
     * Executa e converte as colunas numéricas informadas para float (documentos => int).
     * O último argumento true é ignorado (compatibilidade de chamada).
     */
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
        $r = $this->one("SELECT COALESCE(SUM(" . self::VAL . "),0) AS valor, COALESCE(SUM(ni.qt),0) AS quantidade,
                COUNT(DISTINCT n.idnfs) AS notas
            " . $this->nfFrom() . $this->w($e, 'n'), $this->pp($ini, $fim));
        return ['valor' => (float)$r['valor'], 'quantidade' => (float)$r['quantidade'], 'notas' => (int)$r['notas']];
    }

    private function resumoCarteira(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $saldo = 'GREATEST(pi.qt - COALESCE(pi.qtfaturado, 0), 0)';
        $r = $this->one("SELECT COALESCE(SUM($saldo * pi.valor),0) AS valor, COALESCE(SUM($saldo),0) AS quantidade,
                COUNT(DISTINCT p.idpedido) AS pedidos
            " . $this->pedFrom("p.status IN (1,4) AND $saldo > 0") . $this->w($e, 'p'), $this->pp($ini, $fim));
        return ['valor' => round((float)$r['valor'], 2), 'quantidade' => (float)$r['quantidade'], 'pedidos' => (int)$r['pedidos']];
    }

    private function resumoCancelados(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $r = $this->one("SELECT COALESCE(SUM(n.valortotalnota),0) AS valor, COUNT(*) AS notas
            FROM nfs n WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df" . $this->w($e, 'n'), $this->pp($ini, $fim));
        return ['valor' => round((float)$r['valor'], 2), 'notas' => (int)$r['notas']];
    }

    private function resumoDevolucoes(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $r = $this->one("SELECT COALESCE(SUM(" . self::VAL_DEV . "),0) AS valor, COUNT(DISTINCT n.idnfe) AS notas
            FROM nfe n
            JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
            WHERE n.status = 2 AND n.status_devolucao = 1 AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
              AND n.dataentrada BETWEEN :di AND :df" . $this->w($e, 'n'), $this->pp($ini, $fim));
        return ['valor' => round((float)$r['valor'], 2), 'notas' => (int)$r['notas']];
    }

    /** Base de clientes ativos (cliforemp.inativo='N') da carteira dos representantes do escopo. */
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
        return ['positivados' => $pos, 'base' => $b, 'percentual' => $b > 0 ? round($pos / $b * 100, 1) : 0];
    }

    private function diasComVenda(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): int
    {
        $r = $this->one("SELECT COUNT(DISTINCT n.dataemissao) AS d " . $this->nfFrom() . $this->w($e, 'n'), $this->pp($ini, $fim));
        return (int)$r['d'];
    }

    /** Média por dia: primeiro soma por data, depois agrega por dia da semana. */
    private function porDiaSemana(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("WITH d AS (
                SELECT n.dataemissao AS dt, SUM(" . self::VAL . ") AS v
                " . $this->nfFrom() . $this->w($e, 'n') . " GROUP BY n.dataemissao)
            SELECT EXTRACT(DOW FROM dt)::int AS dow, AVG(v) AS media, SUM(v) AS total, COUNT(*) AS dias
            FROM d GROUP BY 1 ORDER BY 1", $this->pp($ini, $fim), 'media', 'total');
        $nomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        foreach ($rows as &$r) {
            $r['dia'] = $nomes[(int)$r['dow']];
            $r['dias'] = (int)$r['dias'];
            $r['media'] = round($r['media'], 2);
            $r['total'] = round($r['total'], 2);
        }
        return $rows;
    }

    private function topProdutos(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT i.iditem, i.descricao AS produto, i.referencia, COALESCE(g.descricao, 'SEM GRUPO') AS grupo,
                SUM(ni.qt) AS quantidade, SUM(" . self::VAL . ") AS valor
            " . str_replace('WHERE n.status', 'LEFT JOIN grupo g ON g.idgrupo = i.idgrupo WHERE n.status', $this->nfFrom()) . $this->w($e, 'n') . "
            GROUP BY i.iditem, i.descricao, i.referencia, g.descricao ORDER BY valor DESC LIMIT 5", $this->pp($ini, $fim), 'quantidade', 'valor');
        foreach ($rows as &$r) {
            $r['valor'] = round($r['valor'], 2);
            $r['preco_medio'] = $r['quantidade'] > 0 ? round($r['valor'] / $r['quantidade'], 2) : 0;
        }
        return $rows;
    }

    private function topClientes(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT c.idcliforemp, c.fantasia AS cliente, COALESCE(rg.descricao, 'SEM REGIAO') AS regiao,
                COUNT(DISTINCT n.idnfs) AS total_notas, SUM(ni.qt) AS quantidade, SUM(" . self::VAL . ") AS valor
            " . str_replace('WHERE n.status', 'LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao WHERE n.status', $this->nfFrom()) . $this->w($e, 'n') . "
            GROUP BY c.idcliforemp, c.fantasia, rg.descricao ORDER BY valor DESC LIMIT 5", $this->pp($ini, $fim), 'quantidade', 'valor');
        foreach ($rows as &$r) {
            $r['total_notas'] = (int)$r['total_notas'];
            $r['valor'] = round($r['valor'], 2);
            $r['ticket_medio'] = $r['total_notas'] > 0 ? round($r['valor'] / $r['total_notas'], 2) : 0;
        }
        return $rows;
    }

    private function distribuicaoRegiao(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        return $this->all("SELECT COALESCE(rg.descricao, 'SEM REGIAO') AS regiao, SUM(" . self::VAL . ") AS valor,
                COUNT(DISTINCT c.idcliforemp) AS clientes
            " . str_replace('WHERE n.status', 'LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao WHERE n.status', $this->nfFrom()) . $this->w($e, 'n') . "
            GROUP BY rg.descricao ORDER BY valor DESC", $this->pp($ini, $fim), 'valor', 'clientes');
    }

    /** Diária para períodos curtos (<= 62 dias) e mensal para os longos; sempre com zeros preenchidos. */
    private function evolucao(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $diaria = $this->dias($ini, $fim) <= self::DIAS_EVOLUCAO_DIARIA;
        $trunc = $diaria ? 'n.dataemissao' : "date_trunc('month', n.dataemissao)::date";
        $rows = $this->all("SELECT $trunc AS periodo, SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade,
                COUNT(DISTINCT n.idnfs) AS notas
            " . $this->nfFrom() . $this->w($e, 'n') . " GROUP BY 1 ORDER BY 1", $this->pp($ini, $fim), 'valor', 'quantidade');
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r['periodo']] = $r;
        }

        $itens = [];
        if ($diaria) {
            for ($d = $ini; $d <= $fim; $d = $d->modify('+1 day')) {
                $k = $d->format('Y-m-d');
                $itens[] = $this->itemEvo($k, $d->format('d/m'), $mapa[$k] ?? null);
            }
        } else {
            for ($d = $ini->modify('first day of this month'); $d <= $fim; $d = $d->modify('first day of next month')) {
                $k = $d->format('Y-m-d');
                $itens[] = $this->itemEvo($k, $d->format('m/Y'), $mapa[$k] ?? null);
            }
        }
        return ['granularidade' => $diaria ? 'dia' : 'mes', 'itens' => $itens];
    }

    private function itemEvo(string $periodo, string $label, ?array $r): array
    {
        return [
            'periodo' => $periodo,
            'label' => $label,
            'valor' => $r ? round($r['valor'], 2) : 0,
            'quantidade' => $r ? $r['quantidade'] : 0,
            'notas' => $r ? (int)$r['notas'] : 0,
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
            $out[] = ['periodo' => $k, 'label' => $d->format('m/Y'), 'valor' => round($mapa[$k]['valor'] ?? 0, 2), 'quantidade' => $mapa[$k]['quantidade'] ?? 0];
        }
        return $out;
    }

    // ======================================================================
    // DADOS DETALHADOS (periodo <= 90 dias)
    // ======================================================================

    /**
     * Receita líquida e Comissão Ajustada (mesma lógica do Cubo de Vendas: abate devoluções e comissão de devolução).
     * % Comissão = comissão ajustada ÷ receita líquida.
     * Margem gerencial = (receita − custo gerencial) ÷ receita.
     */
    private function margemProdutos(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $wN = $this->w($e, 'n');
        $rows = $this->all("WITH mov AS (
                SELECT i.iditem, i.descricao AS produto, i.referencia,
                       " . self::VAL . " AS receita_bruta,
                       CAST(0 AS numeric) AS valor_devolvido,
                       " . self::VAL . " AS receita,
                       ni.qt AS quantidade,
                       COALESCE(ni.valor_comissao, 0) AS comissao,
                       ni.valor_total AS receita_itens,
                       (ni.qt * COALESCE(ni.valorcustogerencial, 0)) AS custo
                " . $this->nfFrom() . $wN . "
                UNION ALL
                SELECT i.iditem, i.descricao AS produto, i.referencia,
                       CAST(0 AS numeric) AS receita_bruta,
                       " . self::VAL_DEV . " AS valor_devolvido,
                       -(" . self::VAL_DEV . ") AS receita,
                       -(ni.qt) AS quantidade,
                       -(" . self::COM_DEV . ") AS comissao,
                       -(ni.valortotal) AS receita_itens,
                       -(ni.qt * COALESCE(ni_orig.valorcustogerencial, 0)) AS custo
                FROM nfe n
                JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.ativo = 'S'
                JOIN item i ON i.iditem = ni.iditem
                LEFT JOIN nfs_item ni_orig ON ni_orig.idnfs = ni.idnfsdevolvida AND ni_orig.iditemnfs = ni.idnfsitem_dev
                WHERE n.status = 2 AND n.status_devolucao = 1
                  AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                  AND n.dataentrada BETWEEN :di AND :df" . $wN . "
            )
            SELECT iditem, produto, referencia,
                   SUM(receita_bruta) AS receita_bruta,
                   SUM(valor_devolvido) AS valor_devolvido,
                   SUM(receita) AS receita,
                   SUM(quantidade) AS quantidade,
                   SUM(comissao) AS comissao,
                   SUM(receita_itens) AS receita_itens,
                   SUM(custo) AS custo
            FROM mov
            GROUP BY iditem, produto, referencia
            HAVING SUM(receita) > 0 OR SUM(valor_devolvido) > 0
            ORDER BY receita DESC LIMIT 15", $this->pp($ini, $fim),
            'receita_bruta', 'valor_devolvido', 'receita', 'quantidade', 'comissao', 'receita_itens', 'custo');
        foreach ($rows as &$r) {
            $r['receita_bruta'] = round($r['receita_bruta'], 2);
            $r['valor_devolvido'] = round($r['valor_devolvido'], 2);
            $r['receita'] = round($r['receita'], 2);
            $r['comissao'] = round($r['comissao'], 2);
            $r['comissao_percentual'] = $r['receita'] > 0 ? round($r['comissao'] / $r['receita'] * 100, 2) : 0;
            $r['margem_percentual'] = ($r['custo'] > 0 && $r['receita_itens'] > 0)
                ? round(($r['receita_itens'] - $r['custo']) / $r['receita_itens'] * 100, 1) : null;
            $r['custo'] = round($r['custo'], 2);
            unset($r['receita_itens']);
        }
        return $rows;
    }

    /** Pares de produtos na mesma NF; valor do par somado uma única vez por NF. */
    private function crossSelling(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("WITH it AS (
                SELECT ni.idnfs, ni.iditem, SUM(" . self::VAL . ") AS v
                FROM nfs n JOIN nfs_item ni ON ni.idnfs = n.idnfs JOIN item i ON i.iditem = ni.iditem
                WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df" . $this->w($e, 'n') . "
                GROUP BY ni.idnfs, ni.iditem)
            SELECT i1.descricao AS produto1, i2.descricao AS produto2, x.vezes_comprados_juntos, x.valor_total
            FROM (
                SELECT a.iditem AS i1, b.iditem AS i2, COUNT(*) AS vezes_comprados_juntos, SUM(a.v + b.v) AS valor_total
                FROM it a JOIN it b ON b.idnfs = a.idnfs AND b.iditem > a.iditem
                GROUP BY a.iditem, b.iditem ORDER BY 3 DESC, 4 DESC LIMIT 10) x
            JOIN item i1 ON i1.iditem = x.i1 JOIN item i2 ON i2.iditem = x.i2
            ORDER BY x.vezes_comprados_juntos DESC, x.valor_total DESC", $this->pp($ini, $fim), 'valor_total');
        foreach ($rows as &$r) {
            $r['vezes_comprados_juntos'] = (int)$r['vezes_comprados_juntos'];
            $r['valor_total'] = round($r['valor_total'], 2);
        }
        return $rows;
    }

    /**
     * Projeção de 3 meses SEMPRE relacionada ao mês atual (hoje):
     * - 3 últimos meses fechados anteriores ao mês atual (tipo = 'realizado')
     * - Mês atual em curso (tipo = 'atual', exibe o realizado até hoje e a projeção de fechamento do mês)
     * - 3 próximos meses subsequentes ao mês atual (tipo = 'projecao', média móvel ancorada no mês atual)
     */
    private function projecaoProximosMeses(array $e, \DateTimeImmutable $fim): array
    {
        $hoje = new \DateTimeImmutable('today');
        $mesAtualIni = $hoje->modify('first day of this month');
        $mesAtualFim = $hoje->modify('last day of this month');
        $mIni = $mesAtualIni->modify('-3 month');

        $rows = $this->all("SELECT to_char(date_trunc('month', n.dataemissao), 'YYYY-MM') AS periodo, SUM(" . self::VAL . ") AS valor
            " . $this->nfFrom('n.dataemissao BETWEEN :di AND :df') . $this->w($e, 'n') . " GROUP BY 1",
            ['di' => $mIni->format('Y-m-d'), 'df' => $hoje->format('Y-m-d')], 'valor');
        $mapa = array_column($rows, 'valor', 'periodo');

        // Projeção de fechamento do mês atual (realizado + média por dia útil × dias úteis restantes)
        $realizadoAtual = round((float)($mapa[$mesAtualIni->format('Y-m')] ?? 0), 2);
        $decorridos = $this->diasUteis($mesAtualIni, $hoje);
        $restantes = $hoje < $mesAtualFim ? $this->diasUteis($hoje->modify('+1 day'), $mesAtualFim) : 0;
        $mediaDiaUtil = $decorridos > 0 ? ($realizadoAtual / $decorridos) : 0;
        $fechamentoEstimadoAtual = round($realizadoAtual + ($mediaDiaUtil * $restantes), 2);

        $vals = [];
        $out = [];
        // 3 meses fechados anteriores ao mês atual
        for ($k = 3; $k >= 1; $k--) {
            $d = $mesAtualIni->modify("-$k month");
            $v = round((float)($mapa[$d->format('Y-m')] ?? 0), 2);
            $vals[] = $v;
            $out[] = ['mes' => $d->format('Y-m-01'), 'valor' => $v, 'tipo' => 'realizado'];
        }

        // Mês atual (referência central)
        $baseMesAtual = $fechamentoEstimadoAtual > 0 ? $fechamentoEstimadoAtual : (count($vals) ? round(array_sum($vals) / count($vals), 2) : 0);
        $vals[] = $baseMesAtual;
        $out[] = [
            'mes' => $mesAtualIni->format('Y-m-01'),
            'valor' => $realizadoAtual,
            'valor_projetado' => $fechamentoEstimadoAtual,
            'tipo' => 'atual',
        ];

        // Projeção futura suprimida conforme solicitação (exibe apenas meses fechados + mês atual)
        return $out;
    }

    // ======================================================================
    // INSIGHTS
    // ======================================================================

    private function rankingRepresentantes(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT n.idvendrepre AS id, COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS nome,
                SUM(" . self::VAL . ") AS valor, COUNT(DISTINCT n.idnfs) AS total_notas, COUNT(DISTINCT n.idcliforemp) AS clientes_ativos
            " . $this->nfFrom() . $this->w($e, 'n') . " GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC LIMIT 5",
            $this->pp($ini, $fim), 'valor', 'total_notas', 'clientes_ativos');
        foreach ($rows as &$r) {
            $r['total_notas'] = (int)$r['total_notas'];
            $r['clientes_ativos'] = (int)$r['clientes_ativos'];
            $r['valor'] = round($r['valor'], 2);
        }
        return $rows;
    }

    /** Funil de pedidos por status, sempre com as 4 etapas, mais taxas de conversão e cancelamento. */
    private function funil(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT p.status, COUNT(DISTINCT p.idpedido) AS quantidade, COALESCE(SUM(pi.qt * pi.valor),0) AS valor
            FROM pedido p
            LEFT JOIN pedido_item pi ON pi.idpedido = p.idpedido AND (pi.ativo = 'S' OR p.status = 3)
            WHERE p.status IN (1,3,4,5) AND p.idtransacao IN (1,17) AND p.data BETWEEN :di AND :df" . $this->w($e, 'p') . "
            GROUP BY p.status", $this->pp($ini, $fim), 'valor');
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
                'valor' => round($porStatus[$st]['valor'] ?? 0, 2),
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

    /** Projeção do mês corrente (NF faturadas, média por dia útil decorrido x dias úteis restantes). */
    private function projecaoFechamento(array $e, \DateTimeImmutable $ref): array
    {
        $hoje = new \DateTimeImmutable('today');
        $mIni = $hoje->modify('first day of this month');
        $mFim = $hoje->modify('last day of this month');
        $r = $this->one("SELECT COALESCE(SUM(" . self::VAL . "),0) AS valor " . $this->nfFrom() . $this->w($e, 'n'), $this->pp($mIni, $hoje));
        $realizado = (float)$r['valor'];
        $decorridos = $this->diasUteis($mIni, $hoje);
        $restantes = $hoje < $mFim ? $this->diasUteis($hoje->modify('+1 day'), $mFim) : 0;
        $media = $decorridos > 0 ? $realizado / $decorridos : 0;
        $adicional = $media * $restantes;
        return [
            'carregado' => true,
            'mes_referencia' => $mIni->format('Y-m'),
            'realizado' => round($realizado, 2),
            'media_dia_util' => round($media, 2),
            'dias_uteis_decorridos' => $decorridos,
            'dias_uteis_restantes' => $restantes,
            'projecao_adicional' => round($adicional, 2),
            'projecao' => round($realizado + $adicional, 2),
        ];
    }

    /**
     * Todos os clientes ativos com 30+ dias sem comprar no escopo selecionado, identificados por representante.
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
        $rows = $this->all("WITH compras AS (
                SELECT n.idcliforemp, n.idvendrepre, n.dataemissao AS d, SUM(" . self::VAL . ") AS v
                FROM nfs n JOIN nfs_item ni ON ni.idnfs = n.idnfs JOIN item i ON i.iditem = ni.iditem
                WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :ji AND :ref" . $this->w($e, 'n') . "
                GROUP BY n.idcliforemp, n.idvendrepre, n.dataemissao
                UNION ALL
                SELECT p.idcliforemp, p.idvendrepre, p.data AS d, 0 AS v
                FROM pedido p
                WHERE p.status IN (1,4,5) AND p.idtransacao IN (1,17) AND p.data BETWEEN :ji AND :ref" . $this->w($e, 'p') . "
            ),
            ult AS (
                SELECT idcliforemp,
                       MAX(d) AS ultima,
                       SUM(v) AS historico,
                       (ARRAY_AGG(idvendrepre ORDER BY d DESC))[1] AS ult_rep
                FROM compras
                GROUP BY idcliforemp
            )
            SELECT u.idcliforemp AS id,
                   c.fantasia AS nome,
                   COALESCE(cid.descricao || '/' || cid.uf, '') AS cidade_uf,
                   COALESCE(cl.idvendedor, u.ult_rep) AS idrepresentante,
                   COALESCE(v.fantasia, 'REP ' || COALESCE(cl.idvendedor, u.ult_rep)) AS representante,
                   to_char(u.ultima, 'DD/MM/YYYY') AS ultima_fmt,
                   u.ultima,
                   u.historico AS valor_historico,
                   (:ref::date - u.ultima) AS dias_sem_comprar
            FROM ult u
            JOIN cliforemp c ON c.idcliforemp = u.idcliforemp AND c.inativo = 'N'
            LEFT JOIN cliente cl ON cl.idcliforemp = u.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN cliforemp v ON v.idcliforemp = COALESCE(cl.idvendedor, u.ult_rep)
            WHERE u.ultima <= :lim" . $repFiltroSql . "
            ORDER BY v.fantasia ASC, u.historico DESC",
            $params, 'valor_historico');
        foreach ($rows as &$r) {
            $r['idrepresentante'] = (int)$r['idrepresentante'];
            $r['dias_sem_comprar'] = (int)$r['dias_sem_comprar'];
            $r['valor_historico'] = round($r['valor_historico'], 2);
        }
        return $rows;
    }
}
