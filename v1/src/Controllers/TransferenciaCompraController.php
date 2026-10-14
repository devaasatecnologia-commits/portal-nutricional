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
 *  - Guia: valor = base de ST (nfs_item.idsituacaotributaria = 10) x aliquota vigente em guia_transferencia_config.
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

    private function isAdmin(Request $request): bool
    {
        $user = $request->getAttribute('user') ?? [];
        return (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);
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
            $where = ' WHERE sc.idfilial = 1 AND sc.status <> 3' . $whereData;
            if ($f['busca'] !== '') {
                $where .= ' AND (sc.observacao ILIKE :b OR ep.observacao ILIKE :b OR sc.idsolicitacaocompra::text = :bx)';
                $p['b'] = '%' . $f['busca'] . '%';
                $p['bx'] = $f['busca'];
            }

            $from = " FROM solicitacao_compra sc
                CROSS JOIN LATERAL (
                    SELECT substring(sc.observacao FROM 'Embarque: ([0-9]+)') AS emb,
                           substring(sc.observacao FROM 'Remessa: ([0-9]+)') AS rem
                ) x
                LEFT JOIN embarque_pedido ep ON ep.idembarque = COALESCE(x.emb, x.rem)::int
                LEFT JOIN LATERAL (
                    SELECT COALESCE(SUM(ni.valortotal_imp), 0) AS base_st
                    FROM nfs_item ni
                    WHERE ni.idsolicitacaocompra = sc.idsolicitacaocompra AND ni.idsituacaotributaria = 10
                ) st ON TRUE
                LEFT JOIN LATERAL (
                    SELECT g.aliquota FROM guia_transferencia_config g
                    WHERE g.tipo = 'ST' AND g.ativo AND g.vigencia_inicio <= sc.data
                    ORDER BY g.vigencia_inicio DESC LIMIT 1
                ) al ON TRUE" . $where;

            $rows = $this->consultar(
                "SELECT sc.idsolicitacaocompra AS idcompra,
                        COALESCE(x.emb, x.rem) AS embarque,
                        (x.emb IS NULL AND x.rem IS NOT NULL) AS remessa,
                        sc.data,
                        TRIM(COALESCE(ep.observacao, '') || CASE WHEN x.emb IS NULL AND x.rem IS NOT NULL THEN ' Remessa ' ELSE ' ' END || COALESCE(TO_CHAR(ep.data, 'DD/MM/YYYY'), '')) AS nomeembarque,
                        ep.qt_pedido AS qtpedido,
                        st.base_st AS valor_st,
                        al.aliquota,
                        ROUND(st.base_st * COALESCE(al.aliquota, 0), 2) AS valor_guia
                 $from ORDER BY sc.idsolicitacaocompra DESC LIMIT " . $f['limite'],
                $p
            );
            $tot = $this->consultar(
                'SELECT COUNT(*) AS qtd, COALESCE(SUM(ROUND(st.base_st * COALESCE(al.aliquota, 0), 2)), 0) AS guia' . $from,
                $p
            )[0];

            return $this->json($response, [
                'success' => true,
                'registros' => $rows,
                'quantidade' => (int)$tot['qtd'],
                'total_guia' => (float)$tot['guia'],
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar transferencias: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/transf-compras/embarques-pendentes : vw_pex_embarque (filial 6, nao faturado) */
    public function embarquesPendentes(Request $request, Response $response): Response
    {
        try {
            $rows = $this->consultar(
                "SELECT v.novo_idembarque AS embarque, v.nome_embarque, v.nova_placa AS placa, v.data,
                        v.qt_pedido, v.totalpesoliquido AS peso,
                        EXISTS (SELECT 1 FROM solicitacao_compra sc
                                WHERE sc.status <> 3
                                  AND (substring(sc.observacao FROM 'Embarque: ([0-9]+)') = v.novo_idembarque::text
                                    OR substring(sc.observacao FROM 'Remessa: ([0-9]+)') = v.novo_idembarque::text)) AS ja_solicitado
                 FROM vw_pex_embarque v
                 WHERE v.filial = 6 AND v.faturado = 'N'
                 ORDER BY v.novo_idembarque DESC LIMIT 50"
            );
            return $this->json($response, ['success' => true, 'registros' => $rows]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar embarques: ' . $e->getMessage(), 500);
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

    // ======================================================================
    // GUIA: ALIQUOTAS ADMINISTRAVEIS
    // ======================================================================

    /** GET /v1/transf-compras/guia-config */
    public function guiaConfig(Request $request, Response $response): Response
    {
        try {
            $rows = $this->consultar('SELECT id, tipo, ncm, descricao, aliquota, vigencia_inicio, ativo, atualizado_por, atualizado_em FROM guia_transferencia_config ORDER BY tipo, ncm NULLS FIRST, vigencia_inicio DESC');
            return $this->json($response, ['success' => true, 'regras' => $rows, 'pode_editar' => $this->isAdmin($request)]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar regras da guia: ' . $e->getMessage(), 500);
        }
    }

    /** POST /v1/transf-compras/guia-config (somente admin): cria nova vigencia ou desativa uma regra */
    public function guiaSalvar(Request $request, Response $response): Response
    {
        if (!$this->isAdmin($request)) {
            return $this->erro($response, 'Somente administradores alteram a guia.', 403);
        }
        try {
            $in = json_decode((string)$request->getBody(), true) ?? [];
            $usuario = (string)(($request->getAttribute('user') ?? [])['username'] ?? 'sistema');

            if (!empty($in['desativar_id'])) {
                $this->pdo->prepare('UPDATE guia_transferencia_config SET ativo = FALSE, atualizado_por = ?, atualizado_em = NOW() WHERE id = ?')
                    ->execute([$usuario, (int)$in['desativar_id']]);
                return $this->json($response, ['success' => true]);
            }

            $aliquotaPct = str_replace(',', '.', (string)($in['aliquota_percentual'] ?? ''));
            if (!is_numeric($aliquotaPct) || (float)$aliquotaPct < 0 || (float)$aliquotaPct > 100) {
                return $this->erro($response, 'Informe uma aliquota entre 0 e 100%.');
            }
            $vigencia = (string)($in['vigencia_inicio'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vigencia)) {
                return $this->erro($response, 'Informe a data de inicio da vigencia.');
            }
            $tipo = ($in['tipo'] ?? 'ST') === 'NCM' ? 'NCM' : 'ST';
            $ncm = $tipo === 'NCM' ? preg_replace('/\D/', '', (string)($in['ncm'] ?? '')) : null;
            if ($tipo === 'NCM' && strlen((string)$ncm) < 2) {
                return $this->erro($response, 'Informe o NCM (ao menos 2 digitos).');
            }

            $this->pdo->prepare('INSERT INTO guia_transferencia_config (tipo, ncm, descricao, aliquota, vigencia_inicio, ativo, atualizado_por) VALUES (?, ?, ?, ?, ?, TRUE, ?)')
                ->execute([$tipo, $ncm, mb_substr(trim((string)($in['descricao'] ?? '')), 0, 120), round((float)$aliquotaPct / 100, 6), $vigencia, $usuario]);
            return $this->json($response, ['success' => true]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao salvar regra: ' . $e->getMessage(), 500);
        }
    }
}
