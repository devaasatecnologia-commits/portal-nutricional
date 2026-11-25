<?php

namespace Nutricional\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Módulo Contábil & Fiscal (Conferência Domínio Sistemas).
 *
 * Apuração por competência ou período personalizado de ICMS, PIS, COFINS, IPI e ST,
 * com hierarquia de filiais (usuario.dash_filiais), filtros por Transação, Grupo, Marca,
 * Tipo de Nota e Item, além de DANFE Espelho + Rastreio de Origem da Geração.
 */
class ContabilController
{
    private const FILIAIS_PADRAO_ADMIN = [1, 2, 3, 5, 6];
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function utf8($val): string
    {
        if ($val === null) {
            return '';
        }
        $s = (string)$val;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        return $s;
    }

    /**
     * Resolve hierarquia de filiais autorizadas para o usuário logado.
     */
    private function resolveContextoUsuario(Request $request): array
    {
        $user = (array)($request->getAttribute('user') ?? []);
        $idusuario = (int)($user['idusuario'] ?? 0);
        $isAdmin = !empty($user['is_admin']) || in_array('admin', (array)($user['permissoes'] ?? []), true);

        $stmt = $this->pdo->prepare('SELECT username, dash_filiais FROM usuario WHERE idusuario = :id');
        $stmt->execute(['id' => $idusuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $filiais = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string)($row['dash_filiais'] ?? ''))),
            static fn(int $f): bool => $f > 0
        )));

        if ($filiais === [] && $isAdmin) {
            $filiais = self::FILIAIS_PADRAO_ADMIN;
        }

        $rowsFiliais = [];
        if ($filiais !== []) {
            $ph = implode(',', $filiais);
            $stF = $this->pdo->query("
                SELECT idfilial, nome, razao, cnpj, uf
                FROM filial
                WHERE idfilial IN ($ph) AND idfilial > 0 AND COALESCE(inativo, 'N') = 'N'
                ORDER BY idfilial
            ");
            foreach ($stF->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $rowsFiliais[] = [
                    'idfilial' => (int)$f['idfilial'],
                    'nome' => $this->utf8($f['nome']),
                    'razao' => $this->utf8($f['razao']),
                    'cnpj' => trim((string)$f['cnpj']),
                    'uf' => trim((string)$f['uf']),
                ];
            }
        }

        $idsDisponiveis = array_column($rowsFiliais, 'idfilial');
        if ($idsDisponiveis === [] && $isAdmin) {
            $idsDisponiveis = $filiais;
        }

        return [
            'idusuario' => $idusuario,
            'username' => $this->utf8($row['username'] ?? ($user['username'] ?? '')),
            'is_admin' => $isAdmin,
            'filiais_permitidas' => $idsDisponiveis,
            'filiais_lista' => $rowsFiliais,
        ];
    }

    private function filtrarFiliais(array $ctx, int $filialParam): array
    {
        $permitidas = $ctx['filiais_permitidas'];
        if ($permitidas === []) {
            return [-1];
        }
        if ($filialParam > 0) {
            return in_array($filialParam, $permitidas, true) ? [$filialParam] : [-1];
        }
        return $permitidas;
    }

    /**
     * Aceita competência (mes=YYYY-MM) ou período personalizado (data_inicio + data_fim).
     */
    private function parsePeriodo(array $q): array
    {
        $di = trim((string)($q['data_inicio'] ?? ''));
        $df = trim((string)($q['data_fim'] ?? ''));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $di) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
            $dtIni = \DateTimeImmutable::createFromFormat('Y-m-d', $di);
            $dtFim = \DateTimeImmutable::createFromFormat('Y-m-d', $df);
            if ($dtIni && $dtFim && $dtIni <= $dtFim) {
                return [
                    'modo' => 'personalizado',
                    'mes' => $dtIni->format('Y-m'),
                    'inicio' => $dtIni->format('Y-m-d'),
                    'fim_inclusivo' => $dtFim->format('Y-m-d'),
                    'fim' => $dtFim->modify('+1 day')->format('Y-m-d'),
                    'label' => $dtIni->format('d/m/Y') . ' a ' . $dtFim->format('d/m/Y'),
                ];
            }
        }

        $mesParam = trim((string)($q['mes'] ?? ''));
        if (!preg_match('/^(\d{4})-(\d{2})$/', $mesParam, $m)) {
            $dt = new \DateTimeImmutable('first day of this month');
        } else {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', "{$m[1]}-{$m[2]}-01")
                ?: new \DateTimeImmutable('first day of this month');
        }

        return [
            'modo' => 'mensal',
            'mes' => $dt->format('Y-m'),
            'inicio' => $dt->format('Y-m-d'),
            'fim_inclusivo' => $dt->modify('last day of this month')->format('Y-m-d'),
            'fim' => $dt->modify('first day of next month')->format('Y-m-d'),
            'label' => $dt->format('m/Y'),
        ];
    }

    /**
     * Constrói cláusulas SQL adicionais para filtros contábeis avançados em nível de item/nota.
     */
    private function buildFiltrosAvancados(array $q, array &$params, string $prefixo = ''): array
    {
        $transacaoS = (int)($q['transacao_saida'] ?? 0);
        $transacaoE = (int)($q['transacao_entrada'] ?? 0);
        $idgrupo = (int)($q['idgrupo'] ?? 0);
        $idmarca = (int)($q['idmarca'] ?? 0);
        $subtipo = strtoupper(trim((string)($q['subtipo_nota'] ?? '')));
        $itemBusca = trim((string)($q['item_busca'] ?? ''));
        $cfopGlobal = (int)($q['cfop'] ?? 0);

        $wS = '';
        $wE = '';

        if ($cfopGlobal > 0) {
            $params[$prefixo . 'cfop_g'] = $cfopGlobal;
            $wS .= " AND ni.idcfop = :{$prefixo}cfop_g";
            $wE .= " AND ni.idcfop = :{$prefixo}cfop_g";
        }
        if ($transacaoS > 0) {
            $params[$prefixo . 'ts'] = $transacaoS;
            $wS .= " AND n.idtransacao = :{$prefixo}ts";
        }
        if ($transacaoE > 0) {
            $params[$prefixo . 'te'] = $transacaoE;
            $wE .= " AND n.idtransacao = :{$prefixo}te";
        }
        if ($idgrupo > 0) {
            $params[$prefixo . 'grp'] = $idgrupo;
            $wS .= " AND i.idgrupo = :{$prefixo}grp";
            $wE .= " AND i.idgrupo = :{$prefixo}grp";
        }
        if ($idmarca > 0) {
            $params[$prefixo . 'mrc'] = $idmarca;
            $wS .= " AND i.idmarca = :{$prefixo}mrc";
            $wE .= " AND i.idmarca = :{$prefixo}mrc";
        }
        if ($itemBusca !== '') {
            $params[$prefixo . 'ib'] = '%' . $itemBusca . '%';
            $condItem = " AND (ni.iditem::text ILIKE :{$prefixo}ib OR COALESCE(i.referencia,'') ILIKE :{$prefixo}ib OR COALESCE(i.descricao,'') ILIKE :{$prefixo}ib OR COALESCE(i.idclassfiscal::text,'') ILIKE :{$prefixo}ib)";
            $wS .= $condItem;
            $wE .= $condItem;
        }

        if ($subtipo === 'VENDA_COMPRA') {
            $wS .= " AND ni.idcfop IN (5101,5102,5103,5104,5405,6101,6102,6108,6403,6404)";
            $wE .= " AND ni.idcfop IN (1101,1102,1403,2101,2102,2403)";
        } elseif ($subtipo === 'DEVOLUCAO') {
            $wS .= " AND ni.idcfop IN (5201,5202,5411,6201,6202,6411)";
            $wE .= " AND ni.idcfop IN (1201,1202,1411,2201,2202,2411)";
        } elseif ($subtipo === 'TRANSFERENCIA') {
            $wS .= " AND ni.idcfop IN (5151,5152,5409,6151,6152,6409)";
            $wE .= " AND ni.idcfop IN (1151,1152,1409,2151,2152,2409)";
        } elseif ($subtipo === 'BONIFICACAO') {
            $wS .= " AND ni.idcfop IN (5910,5911,6910,6911)";
            $wE .= " AND ni.idcfop IN (1910,1911,2910,2911)";
        }

        return [
            'wS' => $wS,
            'wE' => $wE,
            'tem_filtro_item' => ($idgrupo > 0 || $idmarca > 0 || $itemBusca !== '' || $subtipo !== '' || $cfopGlobal > 0),
        ];
    }

    /**
     * GET /v1/contabil/filtros
     */
    public function filtros(Request $request, Response $response): Response
    {
        $ts = [];
        foreach ($this->pdo->query("SELECT idtransacao, descricao FROM nfs_transacao ORDER BY idtransacao")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ts[] = ['id' => (int)$r['idtransacao'], 'descricao' => $this->utf8($r['descricao'])];
        }

        $te = [];
        foreach ($this->pdo->query("SELECT idtransacao, descricao FROM nfe_transacao ORDER BY idtransacao")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $te[] = ['id' => (int)$r['idtransacao'], 'descricao' => $this->utf8($r['descricao'])];
        }

        $grupos = [];
        foreach ($this->pdo->query("SELECT idgrupo, descricao FROM grupo WHERE COALESCE(inativo,'N')='N' AND COALESCE(descricao,'') <> '' ORDER BY descricao")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $grupos[] = ['id' => (int)$r['idgrupo'], 'descricao' => $this->utf8($r['descricao'])];
        }

        $marcas = [];
        foreach ($this->pdo->query("SELECT idmarca, descricao FROM marca WHERE COALESCE(inativo,'N')='N' AND COALESCE(descricao,'') <> '' ORDER BY descricao")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $marcas[] = ['id' => (int)$r['idmarca'], 'descricao' => $this->utf8($r['descricao'])];
        }

        $cfops = [];
        foreach ($this->pdo->query("SELECT idcfop, descricao FROM cfop WHERE COALESCE(inativo,'N')='N' ORDER BY idcfop")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cfops[] = ['id' => (int)$r['idcfop'], 'descricao' => $this->utf8($r['descricao'])];
        }

        return $this->json($response, [
            'success' => true,
            'transacoes_saida' => $ts,
            'transacoes_entrada' => $te,
            'grupos' => $grupos,
            'marcas' => $marcas,
            'cfops' => $cfops,
        ]);
    }

    /**
     * GET /v1/contabil/resumo
     */
    public function resumo(Request $request, Response $response): Response
    {
        $ctx = $this->resolveContextoUsuario($request);
        $q = $request->getQueryParams();
        $comp = $this->parsePeriodo($q);
        $filialParam = (int)($q['filial'] ?? 0);
        $filiais = $this->filtrarFiliais($ctx, $filialParam);
        $modoCredito = (($q['modo_credito'] ?? 'creditado') === 'destacado') ? 'destacado' : 'creditado';
        $tipoOp = strtoupper(trim((string)($q['tipo_nota'] ?? 'TODAS')));

        $inFiliais = implode(',', $filiais);
        $paramsBase = ['inicio' => $comp['inicio'], 'fim' => $comp['fim']];
        $adv = $this->buildFiltrosAvancados($q, $paramsBase);

        $cfopsSaida = [];
        $totSaida = $this->novoAcumulador();

        if ($tipoOp !== 'ENTRADA') {
            $sqlSaidasCfop = "
                SELECT
                    ni.idcfop,
                    COALESCE(NULLIF(TRIM(c.descricao), ''), 'CFOP ' || ni.idcfop::text) AS cfop_desc,
                    COUNT(DISTINCT (n.idfilial, n.idnfs)) AS qtd_notas,
                    COUNT(*) AS qtd_itens,
                    COALESCE(SUM(ni.valor_total), 0)::numeric(14,2) AS valor_contabil,
                    COALESCE(SUM(ni.valor_basecalculo), 0)::numeric(14,2) AS base_icms,
                    COALESCE(SUM(ni.valoricms), 0)::numeric(14,2) AS valor_icms,
                    COALESCE(SUM(ni.valorsubsttrib), 0)::numeric(14,2) AS valor_st,
                    COALESCE(SUM(ni.valorbase_ipi), 0)::numeric(14,2) AS base_ipi,
                    COALESCE(SUM(ni.valor_ipi), 0)::numeric(14,2) AS valor_ipi,
                    COALESCE(SUM(ni.valorbase_pis), 0)::numeric(14,2) AS base_pis,
                    COALESCE(SUM(ni.valor_pis), 0)::numeric(14,2) AS valor_pis,
                    COALESCE(SUM(ni.valorbase_cofins), 0)::numeric(14,2) AS base_cofins,
                    COALESCE(SUM(ni.valor_cofins), 0)::numeric(14,2) AS valor_cofins,
                    COALESCE(SUM(ni.valoricmsufdestino), 0)::numeric(14,2) AS valor_difal,
                    COALESCE(SUM(ni.valorfcp), 0)::numeric(14,2) AS valor_fcp
                FROM nfs_item ni
                JOIN nfs n ON n.idnfs = ni.idnfs AND n.idfilial = ni.idfilial
                LEFT JOIN item i ON i.iditem = ni.iditem
                LEFT JOIN cfop c ON c.idcfop = ni.idcfop
                WHERE n.idfilial IN ($inFiliais)
                  AND n.dataemissao >= :inicio AND n.dataemissao < :fim
                  AND n.status = 2
                  AND COALESCE(n.cancelado_sefaz, 'N') = 'N'
                  AND COALESCE(n.denegada, 'N') = 'N'
                  {$adv['wS']}
                GROUP BY ni.idcfop, c.descricao
                ORDER BY valor_contabil DESC
            ";
            $stS = $this->pdo->prepare($sqlSaidasCfop);
            $stS->execute($this->filtrarParamsSql($sqlSaidasCfop, $paramsBase));
            foreach ($stS->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $item = [
                    'tipo' => 'SAIDA',
                    'idcfop' => (int)$r['idcfop'],
                    'descricao' => $this->utf8($r['cfop_desc']),
                    'qtd_notas' => (int)$r['qtd_notas'],
                    'qtd_itens' => (int)$r['qtd_itens'],
                    'valor_contabil' => (float)$r['valor_contabil'],
                    'base_icms' => (float)$r['base_icms'],
                    'icms_destacado' => (float)$r['valor_icms'],
                    'icms_apurado' => (float)$r['valor_icms'],
                    'valor_st' => (float)$r['valor_st'],
                    'ipi_destacado' => (float)$r['valor_ipi'],
                    'ipi_apurado' => (float)$r['valor_ipi'],
                    'base_pis' => (float)$r['base_pis'],
                    'pis_destacado' => (float)$r['valor_pis'],
                    'pis_apurado' => (float)$r['valor_pis'],
                    'base_cofins' => (float)$r['base_cofins'],
                    'cofins_destacado' => (float)$r['valor_cofins'],
                    'cofins_apurado' => (float)$r['valor_cofins'],
                    'valor_difal' => (float)$r['valor_difal'],
                    'valor_fcp' => (float)$r['valor_fcp'],
                ];
                $cfopsSaida[] = $item;
                $this->somarAcumulador($totSaida, $item);
            }

            $sqlCntS = "
                SELECT COUNT(DISTINCT (n.idfilial, n.idnfs))
                FROM nfs n
                JOIN nfs_item ni ON ni.idnfs = n.idnfs AND ni.idfilial = n.idfilial
                LEFT JOIN item i ON i.iditem = ni.iditem
                WHERE n.idfilial IN ($inFiliais) AND n.dataemissao >= :inicio AND n.dataemissao < :fim
                  AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N' AND COALESCE(n.denegada,'N') = 'N'
                  {$adv['wS']}
            ";
            $stCntS = $this->pdo->prepare($sqlCntS);
            $stCntS->execute($this->filtrarParamsSql($sqlCntS, $paramsBase));
            $totSaida['qtd_notas'] = (int)$stCntS->fetchColumn();
        }

        $cfopsEntrada = [];
        $totEntrada = $this->novoAcumulador();

        if ($tipoOp !== 'SAIDA') {
            $sqlEntradasCfop = "
                SELECT
                    ni.idcfop,
                    COALESCE(NULLIF(TRIM(c.descricao), ''), 'CFOP ' || ni.idcfop::text) AS cfop_desc,
                    COUNT(DISTINCT (n.idfilial, n.idnfe)) AS qtd_notas,
                    COUNT(*) AS qtd_itens,
                    COALESCE(SUM(ni.valortotal), 0)::numeric(14,2) AS valor_contabil,
                    COALESCE(SUM(ni.valorbase_icms), 0)::numeric(14,2) AS base_icms,
                    COALESCE(SUM(ni.valoricms), 0)::numeric(14,2) AS icms_destacado,
                    COALESCE(SUM(ni.valoricmscreditado), 0)::numeric(14,2) AS icms_creditado,
                    COALESCE(SUM(ni.valorsubsttribicms), 0)::numeric(14,2) AS valor_st,
                    COALESCE(SUM(ni.valorbase_ipi), 0)::numeric(14,2) AS base_ipi,
                    COALESCE(SUM(ni.valoripi), 0)::numeric(14,2) AS ipi_destacado,
                    COALESCE(SUM(ni.valoripicreditado), 0)::numeric(14,2) AS ipi_creditado,
                    COALESCE(SUM(ni.valorbase_pis), 0)::numeric(14,2) AS base_pis,
                    COALESCE(SUM(ni.valorpis), 0)::numeric(14,2) AS pis_destacado,
                    COALESCE(SUM(ni.valorpiscreditado), 0)::numeric(14,2) AS pis_creditado,
                    COALESCE(SUM(ni.valorbase_cofins), 0)::numeric(14,2) AS base_cofins,
                    COALESCE(SUM(ni.valorcofins), 0)::numeric(14,2) AS cofins_destacado,
                    COALESCE(SUM(ni.valorcofinscreditado), 0)::numeric(14,2) AS cofins_creditado
                FROM nfe_item ni
                JOIN nfe n ON n.idnfe = ni.idnfe AND n.idfilial = ni.idfilial
                LEFT JOIN item i ON i.iditem = ni.iditem
                LEFT JOIN cfop c ON c.idcfop = ni.idcfop
                WHERE n.idfilial IN ($inFiliais)
                  AND n.dataentrada >= :inicio AND n.dataentrada < :fim
                  AND n.status = 2
                  {$adv['wE']}
                GROUP BY ni.idcfop, c.descricao
                ORDER BY valor_contabil DESC
            ";
            $stE = $this->pdo->prepare($sqlEntradasCfop);
            $stE->execute($this->filtrarParamsSql($sqlEntradasCfop, $paramsBase));
            foreach ($stE->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $icmsAp = $modoCredito === 'creditado' ? (float)$r['icms_creditado'] : (float)$r['icms_destacado'];
                $pisAp = $modoCredito === 'creditado' ? (float)$r['pis_creditado'] : (float)$r['pis_destacado'];
                $cofinsAp = $modoCredito === 'creditado' ? (float)$r['cofins_creditado'] : (float)$r['cofins_destacado'];
                $ipiAp = $modoCredito === 'creditado' ? (float)$r['ipi_creditado'] : (float)$r['ipi_destacado'];

                $item = [
                    'tipo' => 'ENTRADA',
                    'idcfop' => (int)$r['idcfop'],
                    'descricao' => $this->utf8($r['cfop_desc']),
                    'qtd_notas' => (int)$r['qtd_notas'],
                    'qtd_itens' => (int)$r['qtd_itens'],
                    'valor_contabil' => (float)$r['valor_contabil'],
                    'base_icms' => (float)$r['base_icms'],
                    'icms_destacado' => (float)$r['icms_destacado'],
                    'icms_creditado' => (float)$r['icms_creditado'],
                    'icms_apurado' => $icmsAp,
                    'valor_st' => (float)$r['valor_st'],
                    'ipi_destacado' => (float)$r['ipi_destacado'],
                    'ipi_creditado' => (float)$r['ipi_creditado'],
                    'ipi_apurado' => $ipiAp,
                    'base_pis' => (float)$r['base_pis'],
                    'pis_destacado' => (float)$r['pis_destacado'],
                    'pis_creditado' => (float)$r['pis_creditado'],
                    'pis_apurado' => $pisAp,
                    'base_cofins' => (float)$r['base_cofins'],
                    'cofins_destacado' => (float)$r['cofins_destacado'],
                    'cofins_creditado' => (float)$r['cofins_creditado'],
                    'cofins_apurado' => $cofinsAp,
                    'valor_difal' => 0.0,
                    'valor_fcp' => 0.0,
                ];
                $cfopsEntrada[] = $item;
                $this->somarAcumulador($totEntrada, $item);
            }

            $sqlCntE = "
                SELECT COUNT(DISTINCT (n.idfilial, n.idnfe))
                FROM nfe n
                JOIN nfe_item ni ON ni.idnfe = n.idnfe AND ni.idfilial = n.idfilial
                LEFT JOIN item i ON i.iditem = ni.iditem
                WHERE n.idfilial IN ($inFiliais) AND n.dataentrada >= :inicio AND n.dataentrada < :fim
                  AND n.status = 2
                  {$adv['wE']}
            ";
            $stCntE = $this->pdo->prepare($sqlCntE);
            $stCntE->execute($this->filtrarParamsSql($sqlCntE, $paramsBase));
            $totEntrada['qtd_notas'] = (int)$stCntE->fetchColumn();
        }

        $icmsSaldo = round($totSaida['icms_apurado'] - $totEntrada['icms_apurado'], 2);
        $pisSaldo = round($totSaida['pis_apurado'] - $totEntrada['pis_apurado'], 2);
        $cofinsSaldo = round($totSaida['cofins_apurado'] - $totEntrada['cofins_apurado'], 2);
        $ipiSaldo = round($totSaida['ipi_apurado'] - $totEntrada['ipi_apurado'], 2);
        $totalAPagar = round(max(0, $icmsSaldo) + max(0, $pisSaldo) + max(0, $cofinsSaldo), 2);

        $stCancS = $this->pdo->prepare("
            SELECT COUNT(*) AS qtd, COALESCE(SUM(valortotalnota),0)::numeric(14,2) AS val
            FROM nfs
            WHERE idfilial IN ($inFiliais) AND dataemissao >= :inicio AND dataemissao < :fim
              AND (status <> 2 OR COALESCE(cancelado_sefaz,'N') = 'S' OR COALESCE(denegada,'N') = 'S')
        ");
        $stCancS->execute(['inicio' => $comp['inicio'], 'fim' => $comp['fim']]);
        $cancS = $stCancS->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'val' => 0];

        $stDivCred = $this->pdo->prepare("
            SELECT COUNT(DISTINCT (n.idfilial, n.idnfe)) AS qtd_notas,
                   COALESCE(SUM(ni.valoricms - ni.valoricmscreditado), 0)::numeric(14,2) AS diff_icms,
                   COALESCE(SUM(ni.valorpis - ni.valorpiscreditado), 0)::numeric(14,2) AS diff_pis,
                   COALESCE(SUM(ni.valorcofins - ni.valorcofinscreditado), 0)::numeric(14,2) AS diff_cofins
            FROM nfe_item ni
            JOIN nfe n ON n.idnfe = ni.idnfe AND n.idfilial = ni.idfilial
            WHERE n.idfilial IN ($inFiliais) AND n.dataentrada >= :inicio AND n.dataentrada < :fim
              AND n.status = 2
              AND (ni.valoricms > ni.valoricmscreditado OR ni.valorpis > ni.valorpiscreditado OR ni.valorcofins > ni.valorcofinscreditado)
        ");
        $stDivCred->execute(['inicio' => $comp['inicio'], 'fim' => $comp['fim']]);
        $divCred = $stDivCred->fetch(PDO::FETCH_ASSOC) ?: ['qtd_notas' => 0, 'diff_icms' => 0, 'diff_pis' => 0, 'diff_cofins' => 0];

        $dtRef = \DateTimeImmutable::createFromFormat('Y-m-d', substr($comp['inicio'], 0, 7) . '-01');
        $histInicio = $dtRef->modify('-5 months')->format('Y-m-d');
        $histFim = $dtRef->modify('first day of next month')->format('Y-m-d');
        $colIcmsE = $modoCredito === 'creditado' ? 'ni.valoricmscreditado' : 'ni.valoricms';
        $colPisE = $modoCredito === 'creditado' ? 'ni.valorpiscreditado' : 'ni.valorpis';
        $colCofinsE = $modoCredito === 'creditado' ? 'ni.valorcofinscreditado' : 'ni.valorcofins';

        $sqlHist = "
            WITH meses AS (
                SELECT to_char(gs, 'YYYY-MM') AS ym
                FROM generate_series(:h_ini::date, :c_ini::date, interval '1 month') gs
            ),
            s AS (
                SELECT to_char(n.dataemissao, 'YYYY-MM') AS ym,
                       COALESCE(SUM(n.valoricms),0) AS icms,
                       COALESCE(SUM(n.valorpis),0) AS pis,
                       COALESCE(SUM(n.valorcofins),0) AS cofins
                FROM nfs n
                WHERE n.idfilial IN ($inFiliais) AND n.dataemissao >= :h_ini AND n.dataemissao < :c_fim
                  AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N' AND COALESCE(n.denegada,'N') = 'N'
                GROUP BY 1
            ),
            e AS (
                SELECT to_char(n.dataentrada, 'YYYY-MM') AS ym,
                       COALESCE(SUM($colIcmsE),0) AS icms,
                       COALESCE(SUM($colPisE),0) AS pis,
                       COALESCE(SUM($colCofinsE),0) AS cofins
                FROM nfe_item ni
                JOIN nfe n ON n.idnfe = ni.idnfe AND n.idfilial = ni.idfilial
                WHERE n.idfilial IN ($inFiliais) AND n.dataentrada >= :h_ini AND n.dataentrada < :c_fim
                  AND n.status = 2
                GROUP BY 1
            )
            SELECT m.ym,
                   COALESCE(s.icms,0)::numeric(14,2) AS s_icms, COALESCE(e.icms,0)::numeric(14,2) AS e_icms,
                   COALESCE(s.pis,0)::numeric(14,2) AS s_pis, COALESCE(e.pis,0)::numeric(14,2) AS e_pis,
                   COALESCE(s.cofins,0)::numeric(14,2) AS s_cofins, COALESCE(e.cofins,0)::numeric(14,2) AS e_cofins
            FROM meses m
            LEFT JOIN s ON s.ym = m.ym
            LEFT JOIN e ON e.ym = m.ym
            ORDER BY m.ym
        ";
        $stH = $this->pdo->prepare($sqlHist);
        $stH->execute(['h_ini' => $histInicio, 'c_ini' => $dtRef->format('Y-m-d'), 'c_fim' => $histFim]);
        $historico = [];
        foreach ($stH->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $sTot = (float)$h['s_icms'] + (float)$h['s_pis'] + (float)$h['s_cofins'];
            $eTot = (float)$h['e_icms'] + (float)$h['e_pis'] + (float)$h['e_cofins'];
            $historico[] = [
                'ym' => $h['ym'],
                'saidas_icms' => (float)$h['s_icms'],
                'entradas_icms' => (float)$h['e_icms'],
                'saidas_piscofins' => round((float)$h['s_pis'] + (float)$h['s_cofins'], 2),
                'entradas_piscofins' => round((float)$h['e_pis'] + (float)$h['e_cofins'], 2),
                'liquido_total' => round($sTot - $eTot, 2),
            ];
        }

        return $this->json($response, [
            'success' => true,
            'competencia' => $comp,
            'modo_credito' => $modoCredito,
            'filiais_disponiveis' => $ctx['filiais_lista'],
            'filial_selecionada' => $filialParam,
            'projecao' => [
                'icms' => [
                    'debito' => round($totSaida['icms_apurado'], 2),
                    'credito' => round($totEntrada['icms_apurado'], 2),
                    'credito_destacado' => round($totEntrada['icms_destacado'], 2),
                    'base_saida' => round($totSaida['base_icms'], 2),
                    'base_entrada' => round($totEntrada['base_icms'], 2),
                    'saldo' => $icmsSaldo,
                    'situacao' => $icmsSaldo >= 0 ? 'A PAGAR' : 'SALDO CREDOR',
                ],
                'pis' => [
                    'debito' => round($totSaida['pis_apurado'], 2),
                    'credito' => round($totEntrada['pis_apurado'], 2),
                    'credito_destacado' => round($totEntrada['pis_destacado'], 2),
                    'base_saida' => round($totSaida['base_pis'], 2),
                    'base_entrada' => round($totEntrada['base_pis'], 2),
                    'saldo' => $pisSaldo,
                    'situacao' => $pisSaldo >= 0 ? 'A PAGAR' : 'SALDO CREDOR',
                ],
                'cofins' => [
                    'debito' => round($totSaida['cofins_apurado'], 2),
                    'credito' => round($totEntrada['cofins_apurado'], 2),
                    'credito_destacado' => round($totEntrada['cofins_destacado'], 2),
                    'base_saida' => round($totSaida['base_cofins'], 2),
                    'base_entrada' => round($totEntrada['base_cofins'], 2),
                    'saldo' => $cofinsSaldo,
                    'situacao' => $cofinsSaldo >= 0 ? 'A PAGAR' : 'SALDO CREDOR',
                ],
                'ipi' => [
                    'debito' => round($totSaida['ipi_apurado'], 2),
                    'credito' => round($totEntrada['ipi_apurado'], 2),
                    'destacado_entrada' => round($totEntrada['ipi_destacado'], 2),
                    'saldo' => $ipiSaldo,
                ],
                'total_a_pagar' => $totalAPagar,
            ],
            'totais_saida' => $totSaida,
            'totais_entrada' => $totEntrada,
            'cfops_saida' => $cfopsSaida,
            'cfops_entrada' => $cfopsEntrada,
            'auditoria' => [
                'saidas_canceladas_qtd' => (int)$cancS['qtd'],
                'saidas_canceladas_valor' => (float)$cancS['val'],
                'entradas_credito_parcial_notas' => (int)$divCred['qtd_notas'],
                'entradas_icms_nao_creditado' => (float)$divCred['diff_icms'],
                'entradas_pis_nao_creditado' => (float)$divCred['diff_pis'],
                'entradas_cofins_nao_creditado' => (float)$divCred['diff_cofins'],
            ],
            'historico' => $historico,
        ]);
    }

    /**
     * GET /v1/contabil/notas
     */
    public function notas(Request $request, Response $response): Response
    {
        $ctx = $this->resolveContextoUsuario($request);
        $q = $request->getQueryParams();
        $comp = $this->parsePeriodo($q);
        $filiais = $this->filtrarFiliais($ctx, (int)($q['filial'] ?? 0));
        $inFiliais = implode(',', $filiais);

        $tipo = strtoupper(trim((string)($q['tipo'] ?? $q['tipo_nota'] ?? 'TODAS')));
        $cfop = (int)($q['cfop'] ?? 0);
        $busca = trim((string)($q['busca'] ?? ''));
        $page = max(1, (int)($q['page'] ?? 1));
        $limit = min(100, max(10, (int)($q['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $params = ['inicio' => $comp['inicio'], 'fim' => $comp['fim']];
        $adv = $this->buildFiltrosAvancados($q, $params);

        $whereS = '';
        $whereE = '';
        if ($cfop > 0) {
            $params['cfop'] = $cfop;
            $whereS .= " AND xi.idcfop = :cfop";
            $whereE .= " AND xi.idcfop = :cfop";
        }
        if ($adv['tem_filtro_item'] || $cfop > 0) {
            $subS = str_replace('ni.', 'xi.', $adv['wS']);
            $subE = str_replace('ni.', 'xi.', $adv['wE']);
            $whereS = " AND EXISTS (SELECT 1 FROM nfs_item xi LEFT JOIN item i ON i.iditem = xi.iditem WHERE xi.idnfs = n.idnfs AND xi.idfilial = n.idfilial $whereS $subS)";
            $whereE = " AND EXISTS (SELECT 1 FROM nfe_item xi LEFT JOIN item i ON i.iditem = xi.iditem WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial $whereE $subE)";
        } else {
            if (!empty($params['ts'])) {
                $whereS .= " AND n.idtransacao = :ts";
            }
            if (!empty($params['te'])) {
                $whereE .= " AND n.idtransacao = :te";
            }
        }

        if ($busca !== '') {
            $params['busca'] = '%' . $busca . '%';
            $whereS .= " AND (n.numeronf::text ILIKE :busca OR COALESCE(c.razao,'') ILIKE :busca OR COALESCE(c.cnpj,'') ILIKE :busca OR COALESCE(c.cpf,'') ILIKE :busca)";
            $whereE .= " AND (n.numero::text ILIKE :busca OR COALESCE(c.razao,'') ILIKE :busca OR COALESCE(c.cnpj,'') ILIKE :busca OR COALESCE(c.cpf,'') ILIKE :busca)";
        }

        $sqlSaida = "
            SELECT 'SAIDA'::text AS tipo, n.idfilial, COALESCE(f.nome, 'Filial '||n.idfilial) AS filial_nome,
                   n.idnfs AS idnota, n.numeronf, n.dataemissao AS data_doc,
                   n.idtransacao, COALESCE(t.descricao, '') AS transacao_desc,
                   n.usuario AS usuario_geracao, COALESCE(n.pedidos, '') AS origem_doc,
                   n.idcliforemp, COALESCE(c.razao, 'CONSUMIDOR') AS parceiro,
                   COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS doc_parceiro,
                   COALESCE(c.uf, n.uf_destino, '') AS uf,
                   COALESCE((SELECT string_agg(DISTINCT xi.idcfop::text, ', ') FROM nfs_item xi WHERE xi.idnfs = n.idnfs AND xi.idfilial = n.idfilial), n.idcfop::text) AS cfops,
                   COALESCE(n.valortotalnota, 0)::numeric(14,2) AS valortotal,
                   COALESCE(n.baseicms, 0)::numeric(14,2) AS base_icms,
                   COALESCE(n.valoricms, 0)::numeric(14,2) AS icms_dest,
                   COALESCE(n.valoricms, 0)::numeric(14,2) AS icms_cred,
                   COALESCE(n.valorpis, 0)::numeric(14,2) AS pis_dest,
                   COALESCE(n.valorpis, 0)::numeric(14,2) AS pis_cred,
                   COALESCE(n.valorcofins, 0)::numeric(14,2) AS cofins_dest,
                   COALESCE(n.valorcofins, 0)::numeric(14,2) AS cofins_cred,
                   COALESCE(n.valoripi, 0)::numeric(14,2) AS ipi,
                   COALESCE(n.valor_substituicaotrib, 0)::numeric(14,2) AS st
            FROM nfs n
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            LEFT JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            LEFT JOIN nfs_transacao t ON t.idtransacao = n.idtransacao
            WHERE n.idfilial IN ($inFiliais)
              AND n.dataemissao >= :inicio AND n.dataemissao < :fim
              AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N' AND COALESCE(n.denegada,'N') = 'N'
              $whereS
        ";

        $sqlEntrada = "
            SELECT 'ENTRADA'::text AS tipo, n.idfilial, COALESCE(f.nome, 'Filial '||n.idfilial) AS filial_nome,
                   n.idnfe AS idnota, n.numero AS numeronf, n.dataentrada AS data_doc,
                   n.idtransacao, COALESCE(t.descricao, '') AS transacao_desc,
                   n.usuario AS usuario_geracao,
                   COALESCE((SELECT string_agg(DISTINCT xi.idoc::text, ', ') FROM nfe_item xi WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial AND xi.idoc > 0), '') AS origem_doc,
                   n.idcliforemp, COALESCE(c.razao, 'FORNECEDOR') AS parceiro,
                   COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS doc_parceiro,
                   COALESCE(c.uf, '') AS uf,
                   COALESCE((SELECT string_agg(DISTINCT xi.idcfop::text, ', ') FROM nfe_item xi WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial), '') AS cfops,
                   COALESCE(n.valortotalnota, 0)::numeric(14,2) AS valortotal,
                   COALESCE(n.valorbaseicms, 0)::numeric(14,2) AS base_icms,
                   COALESCE(n.valoricms, 0)::numeric(14,2) AS icms_dest,
                   COALESCE((SELECT SUM(xi.valoricmscreditado) FROM nfe_item xi WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial), 0)::numeric(14,2) AS icms_cred,
                   COALESCE(n.valorpis, 0)::numeric(14,2) AS pis_dest,
                   COALESCE((SELECT SUM(xi.valorpiscreditado) FROM nfe_item xi WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial), 0)::numeric(14,2) AS pis_cred,
                   COALESCE(n.valorcofins, 0)::numeric(14,2) AS cofins_dest,
                   COALESCE((SELECT SUM(xi.valorcofinscreditado) FROM nfe_item xi WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial), 0)::numeric(14,2) AS cofins_cred,
                   COALESCE(n.valoripi, 0)::numeric(14,2) AS ipi,
                   COALESCE(n.valoricmssubst, 0)::numeric(14,2) AS st
            FROM nfe n
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            LEFT JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            LEFT JOIN nfe_transacao t ON t.idtransacao = n.idtransacao
            WHERE n.idfilial IN ($inFiliais)
              AND n.dataentrada >= :inicio AND n.dataentrada < :fim
              AND n.status = 2
              $whereE
        ";

        $union = match ($tipo) {
            'SAIDA' => $sqlSaida,
            'ENTRADA' => $sqlEntrada,
            default => "($sqlSaida) UNION ALL ($sqlEntrada)",
        };

        $execParams = $this->filtrarParamsSql($union, $params);
        $stCnt = $this->pdo->prepare("SELECT COUNT(*) FROM ($union) t");
        $stCnt->execute($execParams);
        $total = (int)$stCnt->fetchColumn();

        $stList = $this->pdo->prepare("SELECT * FROM ($union) t ORDER BY data_doc DESC, numeronf DESC LIMIT $limit OFFSET $offset");
        $stList->execute($execParams);
        $rows = [];
        foreach ($stList->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'tipo' => $r['tipo'],
                'idfilial' => (int)$r['idfilial'],
                'filial_nome' => $this->utf8($r['filial_nome']),
                'idnota' => (int)$r['idnota'],
                'numeronf' => (int)$r['numeronf'],
                'data_doc' => $r['data_doc'],
                'idtransacao' => (int)$r['idtransacao'],
                'transacao_desc' => $this->utf8($r['transacao_desc']),
                'usuario_geracao' => $this->utf8($r['usuario_geracao']),
                'origem_doc' => trim((string)$r['origem_doc']),
                'idcliforemp' => (int)$r['idcliforemp'],
                'parceiro' => $this->utf8($r['parceiro']),
                'doc_parceiro' => trim((string)$r['doc_parceiro']),
                'uf' => trim((string)$r['uf']),
                'cfops' => (string)$r['cfops'],
                'valortotal' => (float)$r['valortotal'],
                'base_icms' => (float)$r['base_icms'],
                'icms_dest' => (float)$r['icms_dest'],
                'icms_cred' => (float)$r['icms_cred'],
                'pis_dest' => (float)$r['pis_dest'],
                'pis_cred' => (float)$r['pis_cred'],
                'cofins_dest' => (float)$r['cofins_dest'],
                'cofins_cred' => (float)$r['cofins_cred'],
                'ipi' => (float)$r['ipi'],
                'st' => (float)$r['st'],
            ];
        }

        return $this->json($response, [
            'success' => true,
            'total' => $total,
            'page' => $page,
            'pages' => (int)ceil($total / $limit),
            'notas' => $rows,
        ]);
    }

    /**
     * GET /v1/contabil/notas/{tipo}/{idfilial}/{idnota}/itens
     */
    public function notaItens(Request $request, Response $response, array $args): Response
    {
        $ctx = $this->resolveContextoUsuario($request);
        $tipo = strtoupper(trim((string)($args['tipo'] ?? '')));
        $idfilial = (int)($args['idfilial'] ?? 0);
        $idnota = (int)($args['idnota'] ?? 0);

        if (!in_array($idfilial, $ctx['filiais_permitidas'], true)) {
            return $this->json($response, ['success' => false, 'error' => 'Filial fora da hierarquia permitida.'], 403);
        }

        $itens = $this->buscarItensNota($tipo, $idfilial, $idnota);
        if ($itens === null) {
            return $this->json($response, ['success' => false, 'error' => 'Tipo inválido.'], 400);
        }

        return $this->json($response, ['success' => true, 'itens' => $itens]);
    }

    /**
     * GET /v1/contabil/notas/{tipo}/{idfilial}/{idnota}/espelho
     * Retorna layout completo da Nota Fiscal (DANFE Espelho) + rastreabilidade de como foi gerada.
     */
    public function notaEspelho(Request $request, Response $response, array $args): Response
    {
        $ctx = $this->resolveContextoUsuario($request);
        $tipo = strtoupper(trim((string)($args['tipo'] ?? '')));
        $idfilial = (int)($args['idfilial'] ?? 0);
        $idnota = (int)($args['idnota'] ?? 0);

        if (!in_array($idfilial, $ctx['filiais_permitidas'], true)) {
            return $this->json($response, ['success' => false, 'error' => 'Filial fora da hierarquia permitida.'], 403);
        }

        if ($tipo === 'SAIDA') {
            $sql = "
                SELECT 'SAIDA'::text AS tipo, n.idfilial, n.idnfs AS idnota, n.numeronf, n.idserie,
                       n.dataemissao, n.datasaida AS data_mov, n.datahora AS datahora_geracao,
                       n.usuario AS usuario_geracao, n.idtransacao, COALESCE(t.descricao,'') AS transacao_desc,
                       n.idcfop AS cfop_principal, COALESCE(cf.descricao,'') AS natureza_operacao,
                       n.status, COALESCE(n.cancelado_sefaz,'N') AS cancelado_sefaz, COALESCE(n.denegada,'N') AS denegada,
                       COALESCE(n.numeroprotocolo_nf_e,'') AS protocolo, COALESCE(n.numero_nf_e,'') AS chave_nfe,
                       COALESCE(to_char(n.datahora_nf_e,'DD/MM/YYYY HH24:MI:SS'),'') AS datahora_sefaz,
                       COALESCE(n.pedidos,'') AS pedidos, COALESCE(n.idembarque,0) AS idembarque,
                       COALESCE(n.observacao,'') AS observacao,
                       COALESCE(n.observacao_fisco,'') AS observacao_fisco, COALESCE(n.observacao_pedido,'') AS observacao_origem,
                       f.razao AS filial_razao, f.nome AS filial_nome, f.cnpj AS filial_cnpj, f.ie AS filial_ie,
                       f.endereco AS filial_end, f.bairro AS filial_bairro, f.uf AS filial_uf,
                       c.idcliforemp, COALESCE(c.razao,'CONSUMIDOR') AS parc_razao,
                       COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS parc_doc,
                       COALESCE(c.ie,'') AS parc_ie, COALESCE(c.endereco,'') AS parc_end,
                       COALESCE(c.bairro,'') AS parc_bairro, COALESCE(cid.descricao,'') AS parc_cidade,
                       COALESCE(c.uf, n.uf_destino, '') AS parc_uf,
                       COALESCE(c.cep,'') AS parc_cep, COALESCE(c.fone,'') AS parc_fone,
                       COALESCE(tr.razao,'') AS transp_razao, COALESCE(NULLIF(TRIM(tr.cnpj),''), TRIM(tr.cpf), '') AS transp_doc,
                       COALESCE(tr.ie,'') AS transp_ie, COALESCE(tr.endereco,'') AS transp_end,
                       COALESCE(cidtr.descricao,'') AS transp_cidade, COALESCE(tr.uf,'') AS transp_uf,
                       COALESCE(n.tipofrete,0) AS tipofrete, COALESCE(n.placa,'') AS placa,
                       COALESCE(n.quant_mercadorias,0) AS quant_volumes,
                       COALESCE(n.pesobruto,0)::numeric(14,3) AS pesobruto,
                       COALESCE(n.pesoliquido,0)::numeric(14,3) AS pesoliquido,
                       COALESCE(n.valortotalitens,0)::numeric(14,2) AS valortotalitens,
                       COALESCE(n.valortotaldesconto,0)::numeric(14,2) AS valortotaldesconto,
                       COALESCE(n.valorfrete,0)::numeric(14,2) AS valorfrete,
                       COALESCE(n.valorseguro,0)::numeric(14,2) AS valorseguro,
                       COALESCE(n.valoroutrasdespesas,0)::numeric(14,2) AS valoroutras,
                       COALESCE(n.valortotalnota,0)::numeric(14,2) AS valortotalnota,
                       COALESCE(n.baseicms,0)::numeric(14,2) AS baseicms,
                       COALESCE(n.valoricms,0)::numeric(14,2) AS valoricms,
                       COALESCE(n.base_substituicaotrib,0)::numeric(14,2) AS base_st,
                       COALESCE(n.valor_substituicaotrib,0)::numeric(14,2) AS valor_st,
                       COALESCE(n.valoripi,0)::numeric(14,2) AS valoripi,
                       COALESCE(n.valor_base_pis,0)::numeric(14,2) AS base_pis,
                       COALESCE(n.valorpis,0)::numeric(14,2) AS valorpis,
                       COALESCE(n.valor_base_cofins,0)::numeric(14,2) AS base_cofins,
                       COALESCE(n.valorcofins,0)::numeric(14,2) AS valorcofins
                FROM nfs n
                LEFT JOIN filial f ON f.idfilial = n.idfilial
                LEFT JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                LEFT JOIN cliforemp tr ON tr.idcliforemp = n.idtransportadora
                LEFT JOIN cidade cidtr ON cidtr.idcidade = tr.idcidade
                LEFT JOIN nfs_transacao t ON t.idtransacao = n.idtransacao
                LEFT JOIN cfop cf ON cf.idcfop = n.idcfop
                WHERE n.idfilial = :f AND n.idnfs = :id
            ";
        } elseif ($tipo === 'ENTRADA') {
            $sql = "
                SELECT 'ENTRADA'::text AS tipo, n.idfilial, n.idnfe AS idnota, n.numero AS numeronf, n.idserie,
                       n.dataemissao, n.dataentrada AS data_mov, n.datahora AS datahora_geracao,
                       n.usuario AS usuario_geracao, n.idtransacao, COALESCE(t.descricao,'') AS transacao_desc,
                       COALESCE((SELECT xi.idcfop FROM nfe_item xi WHERE xi.idnfe=n.idnfe AND xi.idfilial=n.idfilial LIMIT 1),0) AS cfop_principal,
                       COALESCE((SELECT cf.descricao FROM nfe_item xi JOIN cfop cf ON cf.idcfop=xi.idcfop WHERE xi.idnfe=n.idnfe AND xi.idfilial=n.idfilial LIMIT 1),'') AS natureza_operacao,
                       n.status, 'N'::text AS cancelado_sefaz, 'N'::text AS denegada,
                       COALESCE(n.numeroprotocolo_nf_e,'') AS protocolo, COALESCE(n.chave_nf_e,'') AS chave_nfe,
                       ''::text AS datahora_sefaz,
                       COALESCE((SELECT string_agg(DISTINCT xi.idoc::text, ', ') FROM nfe_item xi WHERE xi.idnfe = n.idnfe AND xi.idfilial = n.idfilial AND xi.idoc > 0), '') AS pedidos,
                       0 AS idembarque,
                       COALESCE(n.observacao,'') AS observacao,
                       COALESCE(n.observacao_fisco,'') AS observacao_fisco, COALESCE(n.motivo_diferenca_oc,'') AS observacao_origem,
                       f.razao AS filial_razao, f.nome AS filial_nome, f.cnpj AS filial_cnpj, f.ie AS filial_ie,
                       f.endereco AS filial_end, f.bairro AS filial_bairro, f.uf AS filial_uf,
                       c.idcliforemp, COALESCE(c.razao,'FORNECEDOR') AS parc_razao,
                       COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS parc_doc,
                       COALESCE(c.ie,'') AS parc_ie, COALESCE(c.endereco,'') AS parc_end,
                       COALESCE(c.bairro,'') AS parc_bairro, COALESCE(cid.descricao,'') AS parc_cidade,
                       COALESCE(c.uf, '') AS parc_uf,
                       ''::text AS parc_cep, ''::text AS parc_fone,
                       ''::text AS transp_razao, ''::text AS transp_doc, ''::text AS transp_ie,
                       ''::text AS transp_end, ''::text AS transp_cidade, ''::text AS transp_uf,
                       0 AS tipofrete, ''::text AS placa, 0 AS quant_volumes, 0::numeric(14,3) AS pesobruto, 0::numeric(14,3) AS pesoliquido,
                       COALESCE(n.valortotalitens,0)::numeric(14,2) AS valortotalitens,
                       COALESCE(n.valortotaldesconto,0)::numeric(14,2) AS valortotaldesconto,
                       COALESCE(n.valorfrete,0)::numeric(14,2) AS valorfrete,
                       COALESCE(n.valorseguro,0)::numeric(14,2) AS valorseguro,
                       COALESCE(n.valoroutrasdespesas,0)::numeric(14,2) AS valoroutras,
                       COALESCE(n.valortotalnota,0)::numeric(14,2) AS valortotalnota,
                       COALESCE(n.valorbaseicms,0)::numeric(14,2) AS baseicms,
                       COALESCE(n.valoricms,0)::numeric(14,2) AS valoricms,
                       COALESCE(n.valorbaseicmssubst,0)::numeric(14,2) AS base_st,
                       COALESCE(n.valoricmssubst,0)::numeric(14,2) AS valor_st,
                       COALESCE(n.valoripi,0)::numeric(14,2) AS valoripi,
                       COALESCE(n.valorbasepis,0)::numeric(14,2) AS base_pis,
                       COALESCE(n.valorpis,0)::numeric(14,2) AS valorpis,
                       COALESCE(n.valorbasecofins,0)::numeric(14,2) AS base_cofins,
                       COALESCE(n.valorcofins,0)::numeric(14,2) AS valorcofins
                FROM nfe n
                LEFT JOIN filial f ON f.idfilial = n.idfilial
                LEFT JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                LEFT JOIN nfe_transacao t ON t.idtransacao = n.idtransacao
                WHERE n.idfilial = :f AND n.idnfe = :id
            ";
        } else {
            return $this->json($response, ['success' => false, 'error' => 'Tipo inválido.'], 400);
        }

        $st = $this->pdo->prepare($sql);
        $st->execute(['f' => $idfilial, 'id' => $idnota]);
        $cab = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cab) {
            return $this->json($response, ['success' => false, 'error' => 'Nota fiscal não encontrada.'], 404);
        }

        foreach ($cab as $k => $v) {
            if (is_string($v)) {
                $cab[$k] = $this->utf8($v);
            }
        }

        $duplicatas = [];
        if ($tipo === 'SAIDA') {
            $stDup = $this->pdo->prepare("
                SELECT documento, parcela, vencimento, COALESCE(valororiginal, valor, 0)::numeric(14,2) AS valor
                FROM receber
                WHERE idfilial = :f AND idnfs = :id
                ORDER BY vencimento, parcela
            ");
            $stDup->execute(['f' => $idfilial, 'id' => $idnota]);
            foreach ($stDup->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $duplicatas[] = [
                    'documento' => $this->utf8($d['documento']),
                    'parcela' => (string)$d['parcela'],
                    'vencimento' => $d['vencimento'],
                    'valor' => (float)$d['valor'],
                ];
            }
        }

        $itens = $this->buscarItensNota($tipo, $idfilial, $idnota) ?? [];

        return $this->json($response, [
            'success' => true,
            'nota' => $cab,
            'duplicatas' => $duplicatas,
            'itens' => $itens,
        ]);
    }

    private function buscarItensNota(string $tipo, int $idfilial, int $idnota): ?array
    {
        if ($tipo === 'SAIDA') {
            $sql = "
                SELECT ni.iditemnfs AS seq, ni.iditem, i.referencia, i.descricao,
                       COALESCE(i.descricaotecnica,'') AS descricaotecnica,
                       COALESCE(NULLIF(TRIM(i.codigobarrastributavel),''), i.referencia, ni.iditem::text) AS ean,
                       COALESCE(NULLIF(TRIM(u_nf.sigla),''), NULLIF(TRIM(u_it.sigla),''), 'UN') AS unidade,
                       COALESCE(NULLIF(NULLIF(TRIM(ni.classificacao),''),'00000000'), NULLIF(NULLIF(TRIM(cf_nf.classificacao),''),'00000000'), NULLIF(NULLIF(TRIM(cf_it.classificacao),''),'00000000'), '') AS ncm,
                       ni.idcfop, c.descricao AS cfop_desc,
                       LPAD(COALESCE(ni.idsituacaotributaria,0)::text, 3, '0') AS cst_icms, ni.idsittrib_pis AS cst_pis, ni.idsittrib_cofins AS cst_cofins,
                       ni.qt AS quantidade, CASE WHEN ni.qt > 0 THEN (ni.valor_total / ni.qt)::numeric(14,4) ELSE 0 END AS valor_unitario, ni.valor_total,
                       ni.valor_basecalculo AS base_icms, ni.perc_icms AS aliq_icms, ni.valoricms AS icms_dest, ni.valoricms AS icms_cred,
                       ni.valorbase_pis AS base_pis, ni.perc_pis AS aliq_pis, ni.valor_pis AS pis_dest, ni.valor_pis AS pis_cred,
                       ni.valorbase_cofins AS base_cofins, ni.perc_cofins AS aliq_cofins, ni.valor_cofins AS cofins_dest, ni.valor_cofins AS cofins_cred,
                       ni.valor_ipi AS ipi, ni.valorsubsttrib AS st
                FROM nfs_item ni
                LEFT JOIN item i ON i.iditem = ni.iditem
                LEFT JOIN classificacaofiscal cf_nf ON cf_nf.idclassificacaofiscal = ni.idclassfiscal
                LEFT JOIN classificacaofiscal cf_it ON cf_it.idclassificacaofiscal = i.idclassfiscal
                LEFT JOIN unidade u_nf ON u_nf.idunidade = COALESCE(NULLIF(ni.idunidade_impressao,0), ni.idunidade)
                LEFT JOIN unidade u_it ON u_it.idunidade = i.idunidadebasica
                LEFT JOIN cfop c ON c.idcfop = ni.idcfop
                WHERE ni.idfilial = :f AND ni.idnfs = :id
                ORDER BY ni.iditemnfs
            ";
        } elseif ($tipo === 'ENTRADA') {
            $sql = "
                SELECT ni.iditemnfe AS seq, ni.iditem, i.referencia, i.descricao,
                       COALESCE(i.descricaotecnica,'') AS descricaotecnica,
                       COALESCE(NULLIF(TRIM(i.codigobarrastributavel),''), i.referencia, ni.iditem::text) AS ean,
                       COALESCE(NULLIF(TRIM(u_nf.sigla),''), NULLIF(TRIM(u_it.sigla),''), 'UN') AS unidade,
                       COALESCE(NULLIF(NULLIF(TRIM(ni.classificacao),''),'00000000'), NULLIF(NULLIF(TRIM(cf_nf.classificacao),''),'00000000'), NULLIF(NULLIF(TRIM(cf_it.classificacao),''),'00000000'), '') AS ncm,
                       ni.idcfop, c.descricao AS cfop_desc,
                       LPAD(COALESCE(ni.idsituacaotributaria,0)::text, 3, '0') AS cst_icms, ni.idsittrib_pis_entrada AS cst_pis, ni.idsittrib_cofins_entrada AS cst_cofins,
                       ni.qt AS quantidade, ni.valorunitariobruto AS valor_unitario, ni.valortotal AS valor_total,
                       ni.valorbase_icms AS base_icms, ni.perc_icms AS aliq_icms, ni.valoricms AS icms_dest, ni.valoricmscreditado AS icms_cred,
                       ni.valorbase_pis AS base_pis, ni.perc_pis AS aliq_pis, ni.valorpis AS pis_dest, ni.valorpiscreditado AS pis_cred,
                       ni.valorbase_cofins AS base_cofins, ni.perc_cofins AS aliq_cofins, ni.valorcofins AS cofins_dest, ni.valorcofinscreditado AS cofins_cred,
                       ni.valoripi AS ipi, ni.valorsubsttribicms AS st
                FROM nfe_item ni
                LEFT JOIN item i ON i.iditem = ni.iditem
                LEFT JOIN classificacaofiscal cf_nf ON cf_nf.idclassificacaofiscal = ni.idclassfiscal
                LEFT JOIN classificacaofiscal cf_it ON cf_it.idclassificacaofiscal = i.idclassfiscal
                LEFT JOIN unidade u_nf ON u_nf.idunidade = ni.idunidade
                LEFT JOIN unidade u_it ON u_it.idunidade = i.idunidadebasica
                LEFT JOIN cfop c ON c.idcfop = ni.idcfop
                WHERE ni.idfilial = :f AND ni.idnfe = :id
                ORDER BY ni.iditemnfe
            ";
        } else {
            return null;
        }

        $st = $this->pdo->prepare($sql);
        $st->execute(['f' => $idfilial, 'id' => $idnota]);
        $itens = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = [
                'seq' => (int)$r['seq'],
                'iditem' => (int)$r['iditem'],
                'referencia' => trim((string)$r['referencia']),
                'ean' => trim((string)$r['ean']),
                'unidade' => trim((string)$r['unidade']),
                'descricao' => $this->utf8($r['descricao']),
                'descricaotecnica' => $this->utf8($r['descricaotecnica']),
                'ncm' => trim((string)$r['ncm']),
                'idcfop' => (int)$r['idcfop'],
                'cfop_desc' => $this->utf8($r['cfop_desc']),
                'cst_icms' => trim((string)$r['cst_icms']),
                'cst_pis' => trim((string)$r['cst_pis']),
                'cst_cofins' => trim((string)$r['cst_cofins']),
                'quantidade' => (float)$r['quantidade'],
                'valor_unitario' => (float)$r['valor_unitario'],
                'valor_total' => (float)$r['valor_total'],
                'base_icms' => (float)$r['base_icms'],
                'aliq_icms' => (float)$r['aliq_icms'],
                'icms_dest' => (float)$r['icms_dest'],
                'icms_cred' => (float)$r['icms_cred'],
                'base_pis' => (float)$r['base_pis'],
                'aliq_pis' => (float)$r['aliq_pis'],
                'pis_dest' => (float)$r['pis_dest'],
                'pis_cred' => (float)$r['pis_cred'],
                'base_cofins' => (float)$r['base_cofins'],
                'aliq_cofins' => (float)$r['aliq_cofins'],
                'cofins_dest' => (float)$r['cofins_dest'],
                'cofins_cred' => (float)$r['cofins_cred'],
                'ipi' => (float)$r['ipi'],
                'st' => (float)$r['st'],
            ];
        }
        return $itens;
    }

    /**
     * GET /v1/contabil/itens
     */
    public function relatorioItens(Request $request, Response $response): Response
    {
        $ctx = $this->resolveContextoUsuario($request);
        $q = $request->getQueryParams();
        $comp = $this->parsePeriodo($q);
        $filiais = $this->filtrarFiliais($ctx, (int)($q['filial'] ?? 0));
        $inFiliais = implode(',', $filiais);

        $tipo = strtoupper(trim((string)($q['tipo'] ?? $q['tipo_nota'] ?? 'TODAS')));
        $cfop = (int)($q['cfop'] ?? 0);
        $busca = trim((string)($q['busca'] ?? ''));
        $exportar = strtolower(trim((string)($q['exportar'] ?? '')));
        $page = max(1, (int)($q['page'] ?? 1));
        $limit = $exportar === 'csv' ? 20000 : min(100, max(15, (int)($q['limit'] ?? 40)));
        $offset = $exportar === 'csv' ? 0 : ($page - 1) * $limit;

        $params = ['inicio' => $comp['inicio'], 'fim' => $comp['fim']];
        $adv = $this->buildFiltrosAvancados($q, $params);
        $wS = $adv['wS'];
        $wE = $adv['wE'];

        if ($cfop > 0) {
            $params['cfop'] = $cfop;
            $wS .= ' AND ni.idcfop = :cfop';
            $wE .= ' AND ni.idcfop = :cfop';
        }
        if ($busca !== '') {
            $params['busca'] = '%' . $busca . '%';
            $wS .= " AND (n.numeronf::text ILIKE :busca OR ni.iditem::text ILIKE :busca OR COALESCE(i.referencia,'') ILIKE :busca OR COALESCE(i.descricao,'') ILIKE :busca OR COALESCE(ni.classificacao,'') ILIKE :busca OR COALESCE(cf_it.classificacao,'') ILIKE :busca OR COALESCE(p.razao,'') ILIKE :busca)";
            $wE .= " AND (n.numero::text ILIKE :busca OR ni.iditem::text ILIKE :busca OR COALESCE(i.referencia,'') ILIKE :busca OR COALESCE(i.descricao,'') ILIKE :busca OR COALESCE(ni.classificacao,'') ILIKE :busca OR COALESCE(cf_it.classificacao,'') ILIKE :busca OR COALESCE(p.razao,'') ILIKE :busca)";
        }

        $sqlSaida = "
            SELECT 'SAIDA'::text AS tipo, n.idfilial, COALESCE(f.nome, 'Filial '||n.idfilial) AS filial_nome,
                   n.idnfs AS idnota, n.numeronf, n.dataemissao AS data_doc,
                   COALESCE(p.razao, 'CONSUMIDOR') AS parceiro, COALESCE(p.uf, n.uf_destino, '') AS uf,
                   ni.iditem, COALESCE(i.referencia,'') AS referencia, COALESCE(i.descricao,'') AS item_desc,
                   COALESCE(g.descricao,'') AS grupo_desc, COALESCE(m.descricao,'') AS marca_desc,
                   COALESCE(NULLIF(NULLIF(TRIM(ni.classificacao),''),'00000000'), NULLIF(NULLIF(TRIM(cf_it.classificacao),''),'00000000'), '') AS ncm, ni.idcfop,
                   LPAD(COALESCE(ni.idsituacaotributaria,0)::text, 3, '0') AS cst_icms,
                   ni.qt AS quantidade, ni.valor_total,
                   ni.valor_basecalculo AS base_icms, ni.perc_icms AS aliq_icms, ni.valoricms AS icms_dest, ni.valoricms AS icms_cred,
                   ni.valorbase_pis AS base_pis, ni.perc_pis AS aliq_pis, ni.valor_pis AS pis_dest, ni.valor_pis AS pis_cred,
                   ni.valorbase_cofins AS base_cofins, ni.perc_cofins AS aliq_cofins, ni.valor_cofins AS cofins_dest, ni.valor_cofins AS cofins_cred,
                   ni.valor_ipi AS ipi, ni.valorsubsttrib AS st
            FROM nfs_item ni
            JOIN nfs n ON n.idnfs = ni.idnfs AND n.idfilial = ni.idfilial
            LEFT JOIN item i ON i.iditem = ni.iditem
            LEFT JOIN classificacaofiscal cf_it ON cf_it.idclassificacaofiscal = i.idclassfiscal
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            LEFT JOIN cliforemp p ON p.idcliforemp = n.idcliforemp
            WHERE n.idfilial IN ($inFiliais)
              AND n.dataemissao >= :inicio AND n.dataemissao < :fim
              AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N' AND COALESCE(n.denegada,'N') = 'N'
              $wS
        ";

        $sqlEntrada = "
            SELECT 'ENTRADA'::text AS tipo, n.idfilial, COALESCE(f.nome, 'Filial '||n.idfilial) AS filial_nome,
                   n.idnfe AS idnota, n.numero AS numeronf, n.dataentrada AS data_doc,
                   COALESCE(p.razao, 'FORNECEDOR') AS parceiro, COALESCE(p.uf, '') AS uf,
                   ni.iditem, COALESCE(i.referencia,'') AS referencia, COALESCE(i.descricao,'') AS item_desc,
                   COALESCE(g.descricao,'') AS grupo_desc, COALESCE(m.descricao,'') AS marca_desc,
                   COALESCE(NULLIF(NULLIF(TRIM(ni.classificacao),''),'00000000'), NULLIF(NULLIF(TRIM(cf_it.classificacao),''),'00000000'), '') AS ncm, ni.idcfop,
                   LPAD(COALESCE(ni.idsituacaotributaria,0)::text, 3, '0') AS cst_icms,
                   ni.qt AS quantidade, ni.valortotal AS valor_total,
                   ni.valorbase_icms AS base_icms, ni.perc_icms AS aliq_icms, ni.valoricms AS icms_dest, ni.valoricmscreditado AS icms_cred,
                   ni.valorbase_pis AS base_pis, ni.perc_pis AS aliq_pis, ni.valorpis AS pis_dest, ni.valorpiscreditado AS pis_cred,
                   ni.valorbase_cofins AS base_cofins, ni.perc_cofins AS aliq_cofins, ni.valorcofins AS cofins_dest, ni.valorcofinscreditado AS cofins_cred,
                   ni.valoripi AS ipi, ni.valorsubsttribicms AS st
            FROM nfe_item ni
            JOIN nfe n ON n.idnfe = ni.idnfe AND n.idfilial = ni.idfilial
            LEFT JOIN item i ON i.iditem = ni.iditem
            LEFT JOIN classificacaofiscal cf_it ON cf_it.idclassificacaofiscal = i.idclassfiscal
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            LEFT JOIN cliforemp p ON p.idcliforemp = n.idcliforemp
            WHERE n.idfilial IN ($inFiliais)
              AND n.dataentrada >= :inicio AND n.dataentrada < :fim
              AND n.status = 2
              $wE
        ";

        $union = match ($tipo) {
            'SAIDA' => $sqlSaida,
            'ENTRADA' => $sqlEntrada,
            default => "($sqlSaida) UNION ALL ($sqlEntrada)",
        };
        $execParams = $this->filtrarParamsSql($union, $params);

        if ($exportar === 'csv') {
            $stCsv = $this->pdo->prepare("SELECT * FROM ($union) t ORDER BY data_doc DESC, numeronf DESC, iditem ASC LIMIT $limit");
            $stCsv->execute($execParams);
            $fh = fopen('php://temp', 'r+');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, [
                'Tipo', 'Filial', 'NF', 'Data', 'Parceiro', 'UF', 'ID Item', 'Referencia', 'Produto', 'Grupo', 'Marca', 'NCM',
                'CFOP', 'CST ICMS', 'Qtd', 'Valor Total', 'Base ICMS', 'Aliq ICMS', 'ICMS Destacado', 'ICMS Creditado',
                'PIS Destacado', 'PIS Creditado', 'COFINS Destacado', 'COFINS Creditado', 'IPI', 'ICMS ST'
            ], ';');
            $fmt = static fn($v) => number_format((float)$v, 2, ',', '');
            foreach ($stCsv->fetchAll(PDO::FETCH_ASSOC) as $r) {
                fputcsv($fh, [
                    $r['tipo'], $this->utf8($r['filial_nome']), $r['numeronf'], $r['data_doc'],
                    $this->utf8($r['parceiro']), $r['uf'], $r['iditem'], $r['referencia'],
                    $this->utf8($r['item_desc']), $this->utf8($r['grupo_desc']), $this->utf8($r['marca_desc']),
                    $r['ncm'], $r['idcfop'], $r['cst_icms'], $fmt($r['quantidade']), $fmt($r['valor_total']),
                    $fmt($r['base_icms']), $fmt($r['aliq_icms']), $fmt($r['icms_dest']), $fmt($r['icms_cred']),
                    $fmt($r['pis_dest']), $fmt($r['pis_cred']), $fmt($r['cofins_dest']), $fmt($r['cofins_cred']),
                    $fmt($r['ipi']), $fmt($r['st']),
                ], ';');
            }
            rewind($fh);
            $csv = stream_get_contents($fh);
            fclose($fh);

            $response->getBody()->write($csv);
            return $response
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="relatorio_contabil_itens_' . $comp['inicio'] . '.csv"');
        }

        $stTot = $this->pdo->prepare("
            SELECT COUNT(*) AS qtd,
                   COALESCE(SUM(valor_total),0)::numeric(14,2) AS vtot,
                   COALESCE(SUM(icms_dest),0)::numeric(14,2) AS icms_dest,
                   COALESCE(SUM(icms_cred),0)::numeric(14,2) AS icms_cred,
                   COALESCE(SUM(pis_cred),0)::numeric(14,2) AS pis_cred,
                   COALESCE(SUM(cofins_cred),0)::numeric(14,2) AS cofins_cred
            FROM ($union) t
        ");
        $stTot->execute($execParams);
        $totais = $stTot->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int)($totais['qtd'] ?? 0);

        $stRows = $this->pdo->prepare("SELECT * FROM ($union) t ORDER BY data_doc DESC, numeronf DESC, iditem ASC LIMIT $limit OFFSET $offset");
        $stRows->execute($execParams);
        $itens = [];
        foreach ($stRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = [
                'tipo' => $r['tipo'],
                'idfilial' => (int)$r['idfilial'],
                'filial_nome' => $this->utf8($r['filial_nome']),
                'idnota' => (int)$r['idnota'],
                'numeronf' => (int)$r['numeronf'],
                'data_doc' => $r['data_doc'],
                'parceiro' => $this->utf8($r['parceiro']),
                'uf' => trim((string)$r['uf']),
                'iditem' => (int)$r['iditem'],
                'referencia' => trim((string)$r['referencia']),
                'item_desc' => $this->utf8($r['item_desc']),
                'grupo_desc' => $this->utf8($r['grupo_desc']),
                'marca_desc' => $this->utf8($r['marca_desc']),
                'ncm' => trim((string)$r['ncm']),
                'idcfop' => (int)$r['idcfop'],
                'cst_icms' => trim((string)$r['cst_icms']),
                'quantidade' => (float)$r['quantidade'],
                'valor_total' => (float)$r['valor_total'],
                'base_icms' => (float)$r['base_icms'],
                'aliq_icms' => (float)$r['aliq_icms'],
                'icms_dest' => (float)$r['icms_dest'],
                'icms_cred' => (float)$r['icms_cred'],
                'pis_dest' => (float)$r['pis_dest'],
                'pis_cred' => (float)$r['pis_cred'],
                'cofins_dest' => (float)$r['cofins_dest'],
                'cofins_cred' => (float)$r['cofins_cred'],
                'ipi' => (float)$r['ipi'],
                'st' => (float)$r['st'],
            ];
        }

        return $this->json($response, [
            'success' => true,
            'total' => $total,
            'page' => $page,
            'pages' => (int)ceil($total / $limit),
            'totais' => [
                'valor_total' => (float)($totais['vtot'] ?? 0),
                'icms_dest' => (float)($totais['icms_dest'] ?? 0),
                'icms_cred' => (float)($totais['icms_cred'] ?? 0),
                'pis_cred' => (float)($totais['pis_cred'] ?? 0),
                'cofins_cred' => (float)($totais['cofins_cred'] ?? 0),
            ],
            'itens' => $itens,
        ]);
    }

    /**
     * Filtra o array associativo de parâmetros para conter apenas placeholders presentes na query.
     */
    private function filtrarParamsSql(string $sql, array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            if (preg_match('/:' . preg_quote($k, '/') . '\b/', $sql)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function novoAcumulador(): array
    {
        return [
            'qtd_notas' => 0,
            'qtd_itens' => 0,
            'valor_contabil' => 0.0,
            'base_icms' => 0.0,
            'icms_destacado' => 0.0,
            'icms_apurado' => 0.0,
            'valor_st' => 0.0,
            'ipi_destacado' => 0.0,
            'ipi_apurado' => 0.0,
            'base_pis' => 0.0,
            'pis_destacado' => 0.0,
            'pis_apurado' => 0.0,
            'base_cofins' => 0.0,
            'cofins_destacado' => 0.0,
            'cofins_apurado' => 0.0,
            'valor_difal' => 0.0,
            'valor_fcp' => 0.0,
        ];
    }

    private function somarAcumulador(array &$acc, array $item): void
    {
        $acc['qtd_itens'] += $item['qtd_itens'];
        foreach ([
            'valor_contabil', 'base_icms', 'icms_destacado', 'icms_apurado', 'valor_st',
            'ipi_destacado', 'ipi_apurado', 'base_pis', 'pis_destacado', 'pis_apurado',
            'base_cofins', 'cofins_destacado', 'cofins_apurado', 'valor_difal', 'valor_fcp'
        ] as $k) {
            $acc[$k] = round($acc[$k] + ($item[$k] ?? 0.0), 2);
        }
    }
}
