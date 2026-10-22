<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Transferencias de compra entre Matriz e Filial (somente consulta; a criacao da solicitacao continua no ERP).
 *
 *  - Matriz -> Filial: solicitacao_compra da filial 1 gerada de um embarque/remessa (vw_pex_embarque).
 *  - Filial -> Matriz: solicitacao_compra da filial 6 gerada de uma ordem de compra (vw_ordem_compra_transferencia).
 *  - Guia: cálculo agrupado por NCM e vigência, com base nos itens faturados ST 010.
 */
class TransferenciaCompraController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function erro(Response $response, string $msg, int $status = 400): Response
    {
        return $this->json($response, ['success' => false, 'error' => $msg], $status);
    }

    private function consultar(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{inicio:?string,fim:?string,busca:string,limite:int} */
    private function filtros(Request $request): array
    {
        $q = $request->getQueryParams();
        $data = static fn ($d): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? (string)$d : null;
        $inicio = $data($q['inicio'] ?? '');
        $fim = $data($q['fim'] ?? '');
        if ($inicio && $fim && $fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'busca' => trim((string)($q['q'] ?? '')),
            'limite' => max(1, min(2000, (int)($q['limite'] ?? 10))),
        ];
    }

    /** Filtros de data comuns. @return array{0:string,1:array} */
    private function filtroData(array $f, string $coluna): array
    {
        $sql = '';
        $p = [];
        if ($f['inicio']) {
            $sql .= " AND $coluna >= :inicio::date";
            $p['inicio'] = $f['inicio'];
        }
        if ($f['fim']) {
            $sql .= " AND $coluna <= :fim::date";
            $p['fim'] = $f['fim'];
        }
        return [$sql, $p];
    }

    // ======================================================================
    // MATRIZ -> FILIAL
    // ======================================================================

    /** GET /v1/transf-compras/matriz-filial */
    public function matrizFilial(Request $request, Response $response): Response
    {
        try {
            $f = $this->filtros($request);
            [$whereData, $p] = $this->filtroData($f, 'sc.data');
            $where = " WHERE sc.idfilial = 1 AND sc.status <> 3
                       AND EXISTS (SELECT 1 FROM nfs_item nf WHERE nf.idsolicitacaocompra = sc.idsolicitacaocompra)" . $whereData;
            if ($f['busca'] !== '') {
                $where .= ' AND (sc.observacao ILIKE :b OR ep.observacao ILIKE :b OR sc.idsolicitacaocompra::text = :bx)';
                $p['b'] = '%' . $f['busca'] . '%';
                $p['bx'] = $f['busca'];
            }

            $itensGuia = "SELECT itens.ncm, itens.base_ncm, regra.id AS regra_id, regra.descricao,
                                 regra.st_pr, regra.mva_percentual, regra.aliquota_interna_percentual,
                                 regra.credito_interestadual_percentual, regra.operador_mva,
                                 regra.operador_guia,
                                 CASE
                                   WHEN regra.operador_mva = '+' THEN itens.base_ncm + (itens.base_ncm * regra.mva_percentual / 100)
                                   WHEN regra.operador_mva = '-' THEN itens.base_ncm - (itens.base_ncm * regra.mva_percentual / 100)
                                   WHEN regra.operador_mva = '*' THEN itens.base_ncm * (1 + regra.mva_percentual / 100)
                                   WHEN regra.operador_mva = '/' THEN itens.base_ncm / NULLIF(1 + regra.mva_percentual / 100, 0)
                                 END AS base_ajustada,
                                 (CASE
                                   WHEN regra.operador_mva = '+' THEN itens.base_ncm + (itens.base_ncm * regra.mva_percentual / 100)
                                   WHEN regra.operador_mva = '-' THEN itens.base_ncm - (itens.base_ncm * regra.mva_percentual / 100)
                                   WHEN regra.operador_mva = '*' THEN itens.base_ncm * (1 + regra.mva_percentual / 100)
                                   WHEN regra.operador_mva = '/' THEN itens.base_ncm / NULLIF(1 + regra.mva_percentual / 100, 0)
                                 END) * regra.aliquota_interna_percentual / 100 AS icms_st_bruto,
                                 itens.base_ncm * regra.credito_interestadual_percentual / 100 AS credito_origem,
                                 CASE
                                   WHEN regra.id IS NULL OR NOT regra.st_pr THEN 0
                                   WHEN regra.operador_mva = '/' AND 1 + regra.mva_percentual / 100 = 0 THEN NULL
                                   WHEN regra.operador_guia = '+' THEN
                                     (((CASE WHEN regra.operador_mva = '+' THEN itens.base_ncm + (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '-' THEN itens.base_ncm - (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '*' THEN itens.base_ncm * (1 + regra.mva_percentual / 100)
                                             ELSE itens.base_ncm / NULLIF(1 + regra.mva_percentual / 100, 0) END)
                                       * regra.aliquota_interna_percentual / 100)
                                      + (itens.base_ncm * regra.credito_interestadual_percentual / 100))
                                   WHEN regra.operador_guia = '-' THEN
                                     (((CASE WHEN regra.operador_mva = '+' THEN itens.base_ncm + (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '-' THEN itens.base_ncm - (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '*' THEN itens.base_ncm * (1 + regra.mva_percentual / 100)
                                             ELSE itens.base_ncm / NULLIF(1 + regra.mva_percentual / 100, 0) END)
                                       * regra.aliquota_interna_percentual / 100)
                                      - (itens.base_ncm * regra.credito_interestadual_percentual / 100))
                                   WHEN regra.operador_guia = '*' THEN
                                     (((CASE WHEN regra.operador_mva = '+' THEN itens.base_ncm + (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '-' THEN itens.base_ncm - (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '*' THEN itens.base_ncm * (1 + regra.mva_percentual / 100)
                                             ELSE itens.base_ncm / NULLIF(1 + regra.mva_percentual / 100, 0) END)
                                       * regra.aliquota_interna_percentual / 100)
                                      * (itens.base_ncm * regra.credito_interestadual_percentual / 100))
                                   WHEN regra.operador_guia = '/' THEN
                                     (((CASE WHEN regra.operador_mva = '+' THEN itens.base_ncm + (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '-' THEN itens.base_ncm - (itens.base_ncm * regra.mva_percentual / 100)
                                             WHEN regra.operador_mva = '*' THEN itens.base_ncm * (1 + regra.mva_percentual / 100)
                                             ELSE itens.base_ncm / NULLIF(1 + regra.mva_percentual / 100, 0) END)
                                       * regra.aliquota_interna_percentual / 100)
                                      / NULLIF(itens.base_ncm * regra.credito_interestadual_percentual / 100, 0))
                                 END AS valor_guia,
                                 (regra.st_pr AND (
                                    (regra.operador_mva = '/' AND 1 + regra.mva_percentual / 100 = 0)
                                    OR (regra.operador_guia = '/' AND itens.base_ncm * regra.credito_interestadual_percentual = 0)
                                 )) AS erro_calculo
                          FROM (
                              SELECT COALESCE(NULLIF(regexp_replace(cf.classificacao, '[^0-9]', '', 'g'), ''), 'SEM_NCM') AS ncm,
                                     SUM(ni.valortotal_imp) AS base_ncm
                              FROM nfs_item ni
                              LEFT JOIN item i ON i.iditem = ni.iditem
                              LEFT JOIN classificacaofiscal cf
                                ON cf.idclassificacaofiscal = i.idclassfiscal
                              WHERE ni.idsolicitacaocompra = sc.idsolicitacaocompra
                                AND ni.idsituacaotributaria = 10
                              GROUP BY 1
                          ) itens
                          LEFT JOIN LATERAL (
                              SELECT r.id, r.descricao, r.st_pr, r.mva_percentual,
                                     r.aliquota_interna_percentual, r.credito_interestadual_percentual,
                                     r.operador_mva, r.operador_guia
                              FROM guia_transferencia_ncm_regra r
                              WHERE r.ncm = itens.ncm AND r.ativo AND r.vigencia_inicio <= sc.data
                              ORDER BY r.vigencia_inicio DESC, r.id DESC
                              LIMIT 1
                          ) regra ON TRUE
                          WHERE regra.id IS NOT NULL AND regra.st_pr";

            $from = " FROM solicitacao_compra sc
                CROSS JOIN LATERAL (
                    SELECT substring(sc.observacao FROM 'Embarque: ([0-9]+)') AS emb,
                           substring(sc.observacao FROM 'Remessa: ([0-9]+)') AS rem
                ) x
                LEFT JOIN embarque_pedido ep ON ep.idembarque = COALESCE(x.emb, x.rem)::int
                LEFT JOIN LATERAL (
                    SELECT COALESCE(SUM(c.base_ncm), 0) AS base_st,
                           COALESCE(SUM(CASE WHEN c.regra_id IS NOT NULL AND c.st_pr AND NOT c.erro_calculo THEN c.valor_guia ELSE 0 END), 0) AS valor_guia,
                           COUNT(*) FILTER (WHERE c.erro_calculo) AS erros_calculo,
                           COALESCE(JSON_AGG(JSON_BUILD_OBJECT(
                               'ncm', c.ncm,
                               'base', c.base_ncm,
                               'regra_encontrada', c.regra_id IS NOT NULL,
                               'st_pr', COALESCE(c.st_pr, FALSE),
                               'descricao', c.descricao,
                               'mva_percentual', c.mva_percentual,
                               'aliquota_interna_percentual', c.aliquota_interna_percentual,
                               'credito_interestadual_percentual', c.credito_interestadual_percentual,
                               'operador_mva', c.operador_mva,
                               'base_ajustada', c.base_ajustada,
                               'icms_st_bruto', c.icms_st_bruto,
                               'credito_origem', c.credito_origem,
                               'operador_guia', c.operador_guia,
                               'valor_guia', c.valor_guia,
                               'erro_calculo', c.erro_calculo
                           ) ORDER BY c.ncm), '[]'::json) AS detalhes_ncm
                    FROM (" . $itensGuia . ") c
                ) guia ON TRUE" . $where;

            $rows = $this->consultar(
                "SELECT sc.idsolicitacaocompra AS idcompra,
                        COALESCE(x.emb, x.rem) AS embarque,
                        (x.emb IS NULL AND x.rem IS NOT NULL) AS remessa,
                        sc.data,
                        TRIM(COALESCE(ep.observacao, '') || CASE WHEN x.emb IS NULL AND x.rem IS NOT NULL THEN ' Remessa ' ELSE ' ' END || COALESCE(TO_CHAR(ep.data, 'DD/MM/YYYY'), '')) AS nomeembarque,
                        ep.qt_pedido AS qtpedido,
                        guia.base_st AS valor_st,
                        ROUND(guia.valor_guia, 2) AS valor_guia,
                        guia.erros_calculo,
                        guia.detalhes_ncm
                 $from ORDER BY sc.idsolicitacaocompra DESC LIMIT " . $f['limite'],
                $p
            );
            foreach ($rows as &$row) {
                $row['detalhes_ncm'] = json_decode((string)$row['detalhes_ncm'], true, 512, JSON_THROW_ON_ERROR);
            }
            unset($row);
            $tot = $this->consultar(
                'SELECT COUNT(*) AS qtd, COALESCE(SUM(ROUND(guia.valor_guia, 2)), 0) AS guia,
                        COALESCE(SUM(guia.erros_calculo), 0) AS erros_calculo' . $from,
                $p
            )[0];

            return $this->json($response, [
                'success' => true,
                'registros' => $rows,
                'quantidade' => (int)$tot['qtd'],
                'total_guia' => (float)$tot['guia'],
                'erros_calculo' => (int)$tot['erros_calculo'],
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar transferencias: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/transf-compras/matriz-filial/pendentes : vw_pex_embarque (filial 6) */
    public function embarquesPendentes(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $incluirFaturados = !empty($params['faturados']) || !empty($params['todos']);
            $busca = trim((string)($params['q'] ?? ''));

            $where = "v.filial = 6";
            if (!$incluirFaturados) {
                $where .= " AND v.faturado = 'N'";
            }

            $sqlParams = [];
            if ($busca !== '') {
                $where .= " AND (v.novo_idembarque::text = :bx OR v.nova_placa ILIKE :b OR v.nome_embarque ILIKE :b)";
                $sqlParams['bx'] = $busca;
                $sqlParams['b'] = '%' . $busca . '%';
            }

            $rows = $this->consultar(
                "SELECT v.novo_idembarque AS embarque, v.nome_embarque, v.nova_placa AS placa, v.data,
                        v.qt_pedido, v.totalpesoliquido AS peso, v.faturado,
                        sol.idsolicitacaocompra AS idsolicitacao,
                        sol.status AS status_solicitacao,
                        (sol.idsolicitacaocompra IS NOT NULL) AS ja_solicitado
                 FROM vw_pex_embarque v
                 LEFT JOIN LATERAL (
                     SELECT sc.idsolicitacaocompra, sc.status
                     FROM solicitacao_compra sc
                     WHERE sc.status <> 3
                       AND (substring(sc.observacao FROM 'Embarque: ([0-9]+)') = v.novo_idembarque::text
                         OR substring(sc.observacao FROM 'Remessa: ([0-9]+)') = v.novo_idembarque::text)
                     ORDER BY sc.idsolicitacaocompra DESC
                     LIMIT 1
                 ) sol ON TRUE
                 WHERE {$where}
                 ORDER BY v.novo_idembarque DESC LIMIT 100",
                $sqlParams
            );
            return $this->json($response, ['success' => true, 'registros' => $rows]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar embarques: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/transf-compras/matriz-filial/previa/{embarque} : Análise detalhada prévia da transferência */
    public function previaEmbarque(Request $request, Response $response, array $args): Response
    {
        try {
            $embarqueId = (int)($args['embarque'] ?? 0);
            if ($embarqueId <= 0) {
                return $this->erro($response, 'ID de embarque inválido.', 400);
            }

            // Dados do Embarque
            $stmtEmb = $this->pdo->prepare('
                SELECT ep.idembarque, ep.data, ep.observacao, ep.placa, ep.gerou_nf,
                       ep.qt_pedido, ep.totalpesoliquido, ep.valortotal
                FROM embarque_pedido ep
                WHERE ep.idembarque = :emb
            ');
            $stmtEmb->execute(['emb' => $embarqueId]);
            $embarque = $stmtEmb->fetch(PDO::FETCH_ASSOC);

            if (!$embarque) {
                return $this->erro($response, "Embarque {$embarqueId} não encontrado.", 404);
            }

            // Solicitações de compra vinculadas a este embarque
            $stmtSol = $this->pdo->prepare("
                SELECT sc.idsolicitacaocompra, sc.data, sc.status, sc.observacao
                FROM solicitacao_compra sc
                WHERE (
                        substring(sc.observacao FROM 'Embarque: ([0-9]+)') = :emb
                     OR substring(sc.observacao FROM 'Remessa: ([0-9]+)') = :emb
                )
                AND sc.status <> 3
                ORDER BY sc.idsolicitacaocompra DESC
            ");
            $stmtSol->execute(['emb' => (string)$embarqueId]);
            $solicitacoes = $stmtSol->fetchAll(PDO::FETCH_ASSOC);

            // Itens com cálculo da necessidade, estoque PR e reserva do cliente 14523 (Nutricional Filial PR)
            $sqlItens = '
                WITH itens_embarque AS (
                    SELECT 
                        pi.iditem,
                        i.referencia,
                        i.descricao,
                        i.complemento,
                        u.sigla AS unidade,
                        i.idmarca,
                        m.descricao AS marca,
                        SUM(pi.quant_impressao) AS qtd_embarque
                    FROM embarque_pedido_item epi
                    JOIN embarque_pedido ep ON ep.idembarque = epi.idembarque
                    JOIN pedido p ON p.idpedido = epi.idpedido
                    JOIN pedido_item pi ON pi.idpedido = p.idpedido
                    JOIN item i ON i.iditem = pi.iditem
                    LEFT JOIN unidade u ON u.idunidade = i.idunidadebasica
                    LEFT JOIN marca m ON m.idmarca = i.idmarca
                    WHERE ep.idembarque = :emb
                    GROUP BY pi.iditem, i.referencia, i.descricao, i.complemento, u.sigla, i.idmarca, m.descricao
                ),
                reserva_14523 AS (
                    SELECT 
                        pi.iditem,
                        SUM(COALESCE(pi.qtsaldo, pi.qt - COALESCE(pi.qtfaturado, 0))) AS qtd_reservada
                    FROM pedido p
                    JOIN pedido_item pi ON pi.idpedido = p.idpedido
                    WHERE p.idcliforemp = 14523
                      AND p.idfilial = 6
                      AND p.status = 1
                    GROUP BY pi.iditem
                ),
                estoque_pr AS (
                    SELECT 
                        ef.iditem,
                        COALESCE(ef.saldoatual, 0) AS saldo_fisico
                    FROM estoque_filial ef
                    WHERE ef.idfilial = 6
                )
                SELECT 
                    ie.iditem,
                    ie.referencia,
                    ie.descricao,
                    ie.complemento,
                    ie.unidade,
                    ie.idmarca,
                    ie.marca,
                    (ie.idmarca = 60) AS is_remessa,
                    ROUND(ie.qtd_embarque::numeric, 2) AS qtd_embarque,
                    ROUND(COALESCE(ep.saldo_fisico, 0)::numeric, 2) AS estoque_pr,
                    ROUND(COALESCE(res.qtd_reservada, 0)::numeric, 2) AS reserva_14523,
                    ROUND(GREATEST(COALESCE(ep.saldo_fisico, 0) - COALESCE(res.qtd_reservada, 0), 0)::numeric, 2) AS estoque_livre_pr,
                    ROUND(GREATEST(ie.qtd_embarque - GREATEST(COALESCE(ep.saldo_fisico, 0) - COALESCE(res.qtd_reservada, 0), 0), 0)::numeric, 2) AS qtd_solicitar,
                    (COALESCE(res.qtd_reservada, 0) > 0) AS ajustado_reserva
                FROM itens_embarque ie
                LEFT JOIN estoque_pr ep ON ep.iditem = ie.iditem
                LEFT JOIN reserva_14523 res ON res.iditem = ie.iditem
                ORDER BY is_remessa DESC, ie.descricao ASC
            ';
            $stmtItens = $this->pdo->prepare($sqlItens);
            $stmtItens->execute(['emb' => $embarqueId]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            // Totalizadores de auditoria e decisão
            $totalItens = count($itens);
            $itensASolicitar = 0;
            $qtdTotalSolicitar = 0.0;
            $normaisItens = 0;
            $normaisUnidades = 0.0;
            $remessaItens = 0;
            $remessaUnidades = 0.0;
            $itensComReserva = 0;

            foreach ($itens as &$it) {
                $it['qtd_embarque'] = (float)$it['qtd_embarque'];
                $it['estoque_pr'] = (float)$it['estoque_pr'];
                $it['reserva_14523'] = (float)$it['reserva_14523'];
                $it['estoque_livre_pr'] = (float)$it['estoque_livre_pr'];
                $it['qtd_solicitar'] = (float)$it['qtd_solicitar'];
                $it['is_remessa'] = (bool)$it['is_remessa'];
                $it['ajustado_reserva'] = (bool)$it['ajustado_reserva'];

                if ($it['ajustado_reserva']) {
                    $itensComReserva++;
                }

                if ($it['qtd_solicitar'] > 0) {
                    $itensASolicitar++;
                    $qtdTotalSolicitar += $it['qtd_solicitar'];
                    if ($it['is_remessa']) {
                        $remessaItens++;
                        $remessaUnidades += $it['qtd_solicitar'];
                    } else {
                        $normaisItens++;
                        $normaisUnidades += $it['qtd_solicitar'];
                    }
                }
            }
            unset($it);

            return $this->json($response, [
                'success' => true,
                'embarque' => $embarque,
                'solicitacoes_existentes' => $solicitacoes,
                'resumo' => [
                    'total_itens' => $totalItens,
                    'itens_a_solicitar' => $itensASolicitar,
                    'qtd_total_solicitar' => $qtdTotalSolicitar,
                    'normais_itens' => $normaisItens,
                    'normais_unidades' => $normaisUnidades,
                    'remessa_itens' => $remessaItens,
                    'remessa_unidades' => $remessaUnidades,
                    'itens_ajustados_reserva' => $itensComReserva,
                    'ja_solicitado' => count($solicitacoes) > 0,
                ],
                'itens' => $itens
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao gerar prévia da solicitação: ' . $e->getMessage(), 500);
        }
    }

    // ======================================================================
    // FILIAL -> MATRIZ
    // ======================================================================

    /** GET /v1/transf-compras/filial-matriz */
    public function filialMatriz(Request $request, Response $response): Response
    {
        try {
            $f = $this->filtros($request);
            [$whereData, $p] = $this->filtroData($f, 'sc.data');
            $where = ' WHERE sc.idfilial = 6 AND x.ordem IS NOT NULL' . $whereData;
            if ($f['busca'] !== '') {
                $where .= ' AND (cf.fantasia ILIKE :b OR x.ordem = :bx OR sc.idsolicitacaocompra::text = :bx)';
                $p['b'] = '%' . $f['busca'] . '%';
                $p['bx'] = $f['busca'];
            }

            $from = " FROM solicitacao_compra sc
                CROSS JOIN LATERAL (SELECT substring(sc.observacao FROM 'Ordem de Compra: ([0-9]+)') AS ordem) x
                LEFT JOIN oc ON oc.idoc = x.ordem::int
                LEFT JOIN cliforemp cf ON cf.idcliforemp = oc.idcliforemp
                LEFT JOIN LATERAL (
                    SELECT COALESCE(SUM(oci.qt), 0) AS qt, COALESCE(SUM(item.pesoliquido * oci.qt), 0) AS peso
                    FROM oc_item oci JOIN item ON item.iditem = oci.iditem
                    WHERE oci.idoc = oc.idoc
                ) tot ON TRUE" . $where;

            $rows = $this->consultar(
                "SELECT sc.idsolicitacaocompra AS idcompra, sc.status, x.ordem::int AS ordem, sc.data,
                        cf.fantasia AS fornecedor, tot.qt AS quantidade, tot.peso, COALESCE(oc.valortotal, 0) AS valor
                 $from ORDER BY sc.idsolicitacaocompra DESC LIMIT " . $f['limite'],
                $p
            );
            $t = $this->consultar('SELECT COUNT(*) AS qtd, COALESCE(SUM(oc.valortotal), 0) AS valor' . $from, $p)[0];

            return $this->json($response, [
                'success' => true,
                'registros' => $rows,
                'quantidade' => (int)$t['qtd'],
                'total_valor' => (float)$t['valor'],
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar transferencias: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/transf-compras/ordens-pendentes : vw_ordem_compra_transferencia */
    public function ordensPendentes(Request $request, Response $response): Response
    {
        try {
            $rows = $this->consultar(
                "SELECT v.idoc AS ordem, v.fornecedor, v.idfilial,
                        EXISTS (SELECT 1 FROM solicitacao_compra sc
                                WHERE substring(sc.observacao FROM 'Ordem de Compra: ([0-9]+)') = v.idoc::text) AS ja_solicitado
                 FROM vw_ordem_compra_transferencia v
                 ORDER BY v.idoc DESC LIMIT 50"
            );
            return $this->json($response, ['success' => true, 'registros' => $rows]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar ordens: ' . $e->getMessage(), 500);
        }
    }

}
