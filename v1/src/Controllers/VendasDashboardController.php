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
    private const JANELA_RISCO = 180;
    private const DIAS_RISCO = 30;

    /** Valor faturado do item: idêntico ao Cubo de Vendas. */
    private const VAL = "(ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0))";

    /** Transações de devolução de venda (tabela nfe). Bonificações (21, 1010, 1011, 1016, 1028) ficam de fora. */
    private const TRANS_DEVOLUCAO = '8,9,1000,1004,1005,1007,1015,1020,1024,1025,1031,1032,1033';

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

            $r = $this->one("SELECT SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade,
                    COUNT(DISTINCT n.idcliforemp) AS total_clientes, COUNT(DISTINCT n.idnfs) AS total_notas,
                    SUM(COALESCE(ni.valor_comissao,0)) AS comissao
                $from", $p);
            $valor = (float)$r['valor'];
            $qt = (float)$r['quantidade'];

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
                    'comissao' => round((float)$r['comissao'], 2),
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
            $extra = [];

            switch ($tipo) {
                case 'Faturamento':
                case 'Notas':
                case 'Pedidos':
                case 'Ticket Médio':
                case 'Vendas por Dia':
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade, COUNT(DISTINCT n.idnfs) AS documentos
                        " . $this->nfFrom() . $this->w($e, 'n') . " GROUP BY n.dataemissao ORDER BY n.dataemissao", $p,
                        'valor', 'quantidade', 'documentos');
                    $rotulo = 'NFs';
                    if ($tipo === 'Vendas por Dia') {
                        $extra['por_dia_semana'] = $this->porDiaSemana($e, $ini, $fim);
                    }
                    break;
                case 'Carteira':
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'p.data') . " AS label,
                            SUM(pi.qt * pi.valor) AS valor, SUM(pi.qt) AS quantidade, COUNT(DISTINCT p.idpedido) AS documentos
                        " . $this->pedFrom('p.status IN (1,4)') . $this->w($e, 'p') . " GROUP BY p.data ORDER BY p.data", $p,
                        'valor', 'quantidade', 'documentos');
                    $rotulo = 'Pedidos';
                    break;
                case 'Cancelados':
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            SUM(n.valortotalnota) AS valor, 0 AS quantidade, COUNT(*) AS documentos
                        FROM nfs n WHERE n.status = 3 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :di AND :df"
                        . $this->w($e, 'n') . " GROUP BY n.dataemissao ORDER BY n.dataemissao", $p, 'valor', 'quantidade', 'documentos');
                    $rotulo = 'NFs';
                    break;
                case 'Devoluções':
                    $rows = $this->all("SELECT " . sprintf($fmtDia, 'n.dataemissao') . " AS label,
                            SUM(n.valortotalnota) AS valor, 0 AS quantidade, COUNT(*) AS documentos
                        " . $this->devFrom() . $this->w($e, 'n') . " GROUP BY n.dataemissao ORDER BY n.dataemissao", $p,
                        'valor', 'quantidade', 'documentos');
                    $rotulo = 'NFs de devolução';
                    break;
                case 'Positivação':
                    $base = $this->baseClientesSql($e);
                    $rows = $this->all("SELECT COALESCE(v.fantasia, 'REP ' || n.idvendrepre) AS label,
                            SUM(" . self::VAL . ") AS valor, SUM(ni.qt) AS quantidade, COUNT(DISTINCT n.idcliforemp) AS documentos
                        " . $this->nfFrom() . $this->w($e, 'n') . " AND n.idcliforemp IN ($base)
                        GROUP BY n.idvendrepre, v.fantasia ORDER BY valor DESC", $p, 'valor', 'quantidade', 'documentos', true);
                    $rotulo = 'Clientes';
                    $extra['positivacao'] = $this->resumoPositivacao($e, $ini, $fim);
                    break;
                default:
                    throw new \InvalidArgumentException('Tipo de card inválido.');
            }

            $tv = 0.0; $tq = 0.0; $td = 0;
            foreach ($rows as $r) {
                $tv += $r['valor']; $tq += $r['quantidade']; $td += $r['documentos'];
            }
            $total = ['valor' => round($tv, 2), 'quantidade' => $tq, 'documentos' => $td];
            if ($tipo !== 'Positivação') {
                $total['ticket_medio'] = $td > 0 ? round($tv / $td, 2) : 0;
            } else {
                $total['documentos'] = $extra['positivacao']['positivados'];
            }

            return ['tipo' => $tipo, 'rotulo_documentos' => $rotulo, 'dados' => $rows, 'total' => $total] + $extra;
        });
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

    /** NFs faturadas (mesma regra do Cubo). Aliases: n, ni, i, c (cliente), v (representante). */
    private function nfFrom(string $periodo = 'n.dataemissao BETWEEN :di AND :df'): string
    {
        return "FROM nfs n
            JOIN nfs_item ni ON ni.idnfs = n.idnfs
            JOIN item i ON i.iditem = ni.iditem
            JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            LEFT JOIN cliforemp v ON v.idcliforemp = n.idvendrepre
            WHERE n.status = 2 AND n.idtransacao = 1 AND $periodo";
    }

    /** Pedidos (carteira/funil). Aliases: p, pi, c, vend. */
    private function pedFrom(string $status, bool $apenasAtivos = true): string
    {
        return "FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido" . ($apenasAtivos ? " AND pi.ativo = 'S'" : '') . "
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
            WHERE $status AND p.idtransacao IN (1,17) AND p.data BETWEEN :di AND :df";
    }

    private function devFrom(): string
    {
        return 'FROM nfe n WHERE n.status = 2 AND n.idtransacao IN (' . self::TRANS_DEVOLUCAO . ') AND n.dataemissao BETWEEN :di AND :df';
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
        $r = $this->one("SELECT COALESCE(SUM(pi.qt * pi.valor),0) AS valor, COALESCE(SUM(pi.qt),0) AS quantidade,
                COUNT(DISTINCT p.idpedido) AS pedidos
            " . $this->pedFrom('p.status IN (1,4)') . $this->w($e, 'p'), $this->pp($ini, $fim));
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
        $r = $this->one("SELECT COALESCE(SUM(n.valortotalnota),0) AS valor, COUNT(*) AS notas " . $this->devFrom() . $this->w($e, 'n'), $this->pp($ini, $fim));
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

    /** Comissão ÷ receita (% Comissão) e margem gerencial real = (receita - custo gerencial) ÷ receita. */
    private function margemProdutos(array $e, \DateTimeImmutable $ini, \DateTimeImmutable $fim): array
    {
        $rows = $this->all("SELECT i.iditem, i.descricao AS produto, i.referencia,
                SUM(" . self::VAL . ") AS receita, SUM(ni.qt) AS quantidade,
                SUM(COALESCE(ni.valor_comissao,0)) AS comissao,
                SUM(ni.valor_total) AS receita_itens,
                SUM(ni.qt * COALESCE(ni.valorcustogerencial,0)) AS custo
            " . $this->nfFrom() . $this->w($e, 'n') . "
            GROUP BY i.iditem, i.descricao, i.referencia ORDER BY receita DESC LIMIT 15", $this->pp($ini, $fim),
            'receita', 'quantidade', 'comissao', 'receita_itens', 'custo');
        foreach ($rows as &$r) {
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
     * Média móvel de 3 meses sobre os 3 últimos meses fechados anteriores a data_fim
     * (o próprio mês de data_fim conta como fechado se já terminou).
     */
    private function projecaoProximosMeses(array $e, \DateTimeImmutable $fim): array
    {
        $hoje = new \DateTimeImmutable('today');
        $ref = $fim < $hoje ? $fim : $hoje;
        $ultimo = $ref->modify('last day of this month');
        $mesFim = ($ref->format('Y-m-d') === $ultimo->format('Y-m-d')) ? $ref->modify('first day of this month')
            : $ref->modify('first day of last month');
        $mIni = $mesFim->modify('-2 month');
        $rows = $this->all("SELECT to_char(date_trunc('month', n.dataemissao), 'YYYY-MM') AS periodo, SUM(" . self::VAL . ") AS valor
            " . $this->nfFrom('n.dataemissao BETWEEN :di AND :df') . $this->w($e, 'n') . " GROUP BY 1",
            ['di' => $mIni->format('Y-m-d'), 'df' => $mesFim->modify('last day of this month')->format('Y-m-d')], 'valor');
        $mapa = array_column($rows, 'valor', 'periodo');

        $vals = [];
        $out = [];
        for ($d = $mIni; $d <= $mesFim; $d = $d->modify('first day of next month')) {
            $v = round($mapa[$d->format('Y-m')] ?? 0, 2);
            $vals[] = $v;
            $out[] = ['mes' => $d->format('Y-m-01'), 'valor' => $v, 'tipo' => 'realizado'];
        }
        for ($k = 1; $k <= 3; $k++) {
            $v = round(array_sum(array_slice($vals, -3)) / 3, 2);
            $vals[] = $v;
            $out[] = ['mes' => $mesFim->modify("+$k month")->format('Y-m-01'), 'valor' => $v, 'tipo' => 'projecao'];
        }
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

    /** Projeção do mês de referência (NF faturadas, média por dia útil decorrido x dias úteis restantes). */
    private function projecaoFechamento(array $e, \DateTimeImmutable $ref): array
    {
        $mIni = $ref->modify('first day of this month');
        $mFim = $ref->modify('last day of this month');
        $r = $this->one("SELECT COALESCE(SUM(" . self::VAL . "),0) AS valor " . $this->nfFrom() . $this->w($e, 'n'), $this->pp($mIni, $ref));
        $realizado = (float)$r['valor'];
        $decorridos = $this->diasUteis($mIni, $ref);
        $restantes = $ref < $mFim ? $this->diasUteis($ref->modify('+1 day'), $mFim) : 0;
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

    private function clientesRisco(array $e, \DateTimeImmutable $ref): array
    {
        $jIni = $ref->modify('-' . self::JANELA_RISCO . ' day');
        $limite = $ref->modify('-' . self::DIAS_RISCO . ' day');
        $rows = $this->all("WITH compras AS (
                SELECT n.idcliforemp, n.dataemissao AS d, SUM(" . self::VAL . ") AS v
                FROM nfs n JOIN nfs_item ni ON ni.idnfs = n.idnfs JOIN item i ON i.iditem = ni.iditem
                WHERE n.status = 2 AND n.idtransacao = 1 AND n.dataemissao BETWEEN :ji AND :ref" . $this->w($e, 'n') . "
                GROUP BY n.idcliforemp, n.dataemissao
                UNION ALL
                SELECT p.idcliforemp, p.data, 0 FROM pedido p
                WHERE p.status IN (1,4,5) AND p.idtransacao IN (1,17) AND p.data BETWEEN :ji AND :ref" . $this->w($e, 'p') . "),
            ult AS (SELECT idcliforemp, MAX(d) AS ultima, SUM(v) AS historico FROM compras GROUP BY idcliforemp)
            SELECT u.idcliforemp AS id, c.fantasia AS nome, u.ultima, u.historico AS valor_historico,
                (:ref::date - u.ultima) AS dias_sem_comprar
            FROM ult u JOIN cliforemp c ON c.idcliforemp = u.idcliforemp AND c.inativo = 'N'
            WHERE u.ultima < :lim
            ORDER BY u.historico DESC LIMIT 10",
            ['ji' => $jIni->format('Y-m-d'), 'ref' => $ref->format('Y-m-d'), 'lim' => $limite->format('Y-m-d')], 'valor_historico');
        foreach ($rows as &$r) {
            $r['dias_sem_comprar'] = (int)$r['dias_sem_comprar'];
            $r['valor_historico'] = round($r['valor_historico'], 2);
        }
        return $rows;
    }
}
