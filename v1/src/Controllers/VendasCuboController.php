<?php
namespace Nutricional\Controllers;

use Nutricional\Utils\UserScope;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Cubo de Vendas.
 *
 * Regras de negócio (única fonte para dados, totais, ranking, série, exportação e detalhes):
 *  - NF faturada: nfs.status = 2 e nfs.idtransacao = 1 (data = dataemissao).
 *    Valor = valor_total_com_ipi_st + frete + seguro + outras despesas do item.
 *  - Pedido em carteira: pedido.status IN (1 aberto, 4 faturado parcial) e idtransacao IN (1, 17)
 *    (data = pedido.data). Considera apenas o SALDO AINDA NÃO FATURADO (qt - qtfaturado),
 *    pois o que já foi faturado entra via NF (evita dupla contagem em pedidos parcialmente faturados).
 *  - Peso: peso LÍQUIDO (kg) = pesoliquido x quantidade, tanto em pedidos (item) quanto em NFs (nfs_item).
 *  - Comissão: apenas NFs (valor_comissao); pedidos não têm comissão apurada.
 *  - Escopo (filiais/gestores do usuário) sempre aplicado por interseção com o filtro pedido.
 */
class VendasCuboController
{
    private $pdo;

    private const MSG_ERRO_INTERNO = 'Erro interno ao processar a solicitação.';

    private const MSG_NAO_INFORMADO = '(Não informado)';

    /** Métricas oficiais: chave => [label, formato, definição, aliases aceitos] */
    private const METRICAS = [
        'valor_total' => ['label' => 'Valor (R$)', 'format' => 'currency', 'alias' => ['valor_bruto', 'valor']],
        'quantidade_total' => ['label' => 'Quantidade', 'format' => 'number', 'alias' => ['quantidade']],
        'peso_total' => ['label' => 'Peso líquido (kg)', 'format' => 'decimal', 'alias' => ['peso']],
        'comissao_total' => ['label' => 'Comissão (R$)', 'format' => 'currency', 'alias' => ['comissao']],
        'qtd_documentos' => ['label' => 'Nº Documentos', 'format' => 'number', 'alias' => []],
        'ticket_medio' => ['label' => 'Ticket médio (R$)', 'format' => 'currency', 'alias' => []],
    ];

    /** Dimensões: id/label são colunas da CTE "base" */
    private const DIMENSOES = [
        'data_documento' => ['label' => 'Data', 'type' => 'date', 'icon' => 'fa-calendar', 'id' => 'data_documento', 'txt' => "TO_CHAR(data_documento, 'YYYY-MM-DD')", 'kind' => 'date'],
        'tipo_documento' => ['label' => 'Tipo (Pedido/NF)', 'type' => 'string', 'icon' => 'fa-file-invoice', 'id' => 'tipo_documento', 'txt' => 'tipo_documento', 'kind' => 'string'],
        'status_descricao' => ['label' => 'Status', 'type' => 'string', 'icon' => 'fa-circle-info', 'id' => 'status_descricao', 'txt' => 'status_descricao', 'kind' => 'string'],
        'cliente' => ['label' => 'Cliente', 'type' => 'string', 'icon' => 'fa-user', 'id' => 'idcliente', 'txt' => 'cliente', 'kind' => 'int'],
        'representante' => ['label' => 'Representante', 'type' => 'string', 'icon' => 'fa-user-tie', 'id' => 'idrepresentante', 'txt' => 'representante', 'kind' => 'int'],
        'supervisor' => ['label' => 'Supervisor', 'type' => 'string', 'icon' => 'fa-user-check', 'id' => 'idsupervisor', 'txt' => 'supervisor', 'kind' => 'int'],
        'regiao' => ['label' => 'Região', 'type' => 'string', 'icon' => 'fa-map-marker-alt', 'id' => 'idregiao', 'txt' => 'regiao', 'kind' => 'int'],
        'estado' => ['label' => 'UF', 'type' => 'string', 'icon' => 'fa-map', 'id' => 'uf', 'txt' => 'uf', 'kind' => 'string'],
        'cidade' => ['label' => 'Cidade', 'type' => 'string', 'icon' => 'fa-city', 'id' => 'idcidade', 'txt' => 'cidade', 'kind' => 'int'],
        'grupo' => ['label' => 'Grupo', 'type' => 'string', 'icon' => 'fa-tag', 'id' => 'idgrupo', 'txt' => 'grupo', 'kind' => 'int'],
        'subgrupo' => ['label' => 'Subgrupo', 'type' => 'string', 'icon' => 'fa-tags', 'id' => 'idsubgrupo', 'txt' => 'subgrupo', 'kind' => 'int'],
        'tipo_produto' => ['label' => 'Tipo Produto', 'type' => 'string', 'icon' => 'fa-box', 'id' => 'idtipoproduto', 'txt' => 'tipo_produto', 'kind' => 'int'],
        'marca' => ['label' => 'Marca', 'type' => 'string', 'icon' => 'fa-trademark', 'id' => 'idmarca', 'txt' => 'marca', 'kind' => 'int'],
        'produto' => ['label' => 'Produto', 'type' => 'string', 'icon' => 'fa-boxes', 'id' => 'iditem', 'txt' => 'produto', 'kind' => 'int'],
        'filial' => ['label' => 'Filial', 'type' => 'string', 'icon' => 'fa-store', 'id' => 'idfilial', 'txt' => 'filial', 'kind' => 'int'],
        'tipo_de_pedido' => ['label' => 'Tipo de Pedido', 'type' => 'string', 'icon' => 'fa-file-lines', 'id' => 'idtipopedido', 'txt' => 'tipo_pedido', 'kind' => 'int'],
    ];

    private const DIM_ALIAS = [
        'data_nf' => 'data_documento',
        'vendedor' => 'representante',
        'gestor' => 'supervisor',
    ];

    /** Filtros avançados (campo => dimensão usada para listar opções) */
    private const CAMPOS_FILTRO = [
        'cliente', 'representante', 'supervisor', 'regiao', 'estado', 'cidade', 'grupo',
        'subgrupo', 'tipo_produto', 'marca', 'produto', 'filial', 'tipo_de_pedido',
    ];

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    // ======================================================================
    // CONFIG / LISTAS BÁSICAS
    // ======================================================================

    /**
     * GET /v1/vendas/cubo/config
     */
    public function getConfig(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);

            $dimensions = [];
            foreach (self::DIMENSOES as $k => $d) {
                $dimensions[$k] = ['label' => $d['label'], 'type' => $d['type'], 'icon' => $d['icon']];
            }
            $metrics = [];
            foreach (self::METRICAS as $k => $m) {
                $metrics[$k] = ['label' => $m['label'], 'format' => $m['format']];
            }

            return $this->jsonResponse($response, [
                'dimensions' => $dimensions,
                'metrics' => $metrics,
                'permissoes' => [
                    'filiais' => $scope->filialRestrita() ? $scope->filiais : [],
                    'gestores' => $scope->gestorRestrito() ? $scope->gestores : [],
                    'is_admin' => $scope->isAdmin,
                ],
                'filiais_permitidas' => $this->listarFiliais($scope),
                'default_filters' => [
                    'data_inicio' => date('Y-m-01'),
                    'data_fim' => date('Y-m-t'),
                ],
                'max_periodo_dias' => 365,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getConfig');
        }
    }

    /**
     * POST /v1/vendas/cubo/filiais
     */
    public function getFiliais(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            return $this->jsonResponse($response, ['filiais' => $this->listarFiliais($scope)]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getFiliais', ['filiais' => []]);
        }
    }

    /**
     * POST /v1/vendas/cubo/gestores - apenas gestores liberados ao usuário
     */
    public function getGestores(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $gestores = $scope->resolverGestores(null);

            $sql = "SELECT DISTINCT idgestor AS id, gestor AS nome FROM vw_gestor_repre WHERE inativo = 'N'";
            if (!empty($gestores)) {
                $sql .= ' AND idgestor IN (' . $this->inList($gestores) . ')';
            }
            $sql .= ' ORDER BY nome';

            $stmt = $this->pdo->query($sql);
            return $this->jsonResponse($response, ['gestores' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getGestores', ['gestores' => []]);
        }
    }

    /**
     * POST /v1/vendas/cubo/supervisores-por-filial  {idfilial}
     * idfilial = 0 => todos os gestores do escopo do usuário.
     */
    public function getSupervisoresPorFilial(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);
            $idfilial = (int)($input['idfilial'] ?? 0);

            $filiais = $scope->resolverFiliais($idfilial > 0 ? $idfilial : null);
            $gestores = $scope->resolverGestores(null);

            $sql = "SELECT DISTINCT idgestor AS id, gestor AS nome FROM vw_gestor_repre WHERE inativo = 'N'";
            if (!empty($filiais)) {
                $sql .= ' AND idfilial IN (' . $this->inList($filiais) . ')';
            }
            if (!empty($gestores)) {
                $sql .= ' AND idgestor IN (' . $this->inList($gestores) . ')';
            }
            $sql .= ' ORDER BY nome';

            $stmt = $this->pdo->query($sql);
            return $this->jsonResponse($response, ['supervisores' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getSupervisoresPorFilial', ['supervisores' => []]);
        }
    }

    /**
     * POST /v1/vendas/cubo/representantes-por-supervisor  {idsupervisor, idfilial}
     * idfilial = 0 => sem filtro de filial (apenas o escopo do usuário).
     */
    public function getRepresentantesPorSupervisor(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);
            $idsupervisor = (int)($input['idsupervisor'] ?? 0);
            $idfilial = (int)($input['idfilial'] ?? 0);

            $filiais = $scope->resolverFiliais($idfilial > 0 ? $idfilial : null);
            $gestores = $scope->resolverGestores($idsupervisor > 0 ? $idsupervisor : null);

            $sql = "SELECT DISTINCT idrepresentante AS id, representante AS nome FROM vw_gestor_repre WHERE inativo = 'N'";
            if (!empty($filiais)) {
                $sql .= ' AND idfilial IN (' . $this->inList($filiais) . ')';
            }
            if (!empty($gestores)) {
                $sql .= ' AND idgestor IN (' . $this->inList($gestores) . ')';
            }
            $sql .= ' ORDER BY nome LIMIT 1000';

            $stmt = $this->pdo->query($sql);
            return $this->jsonResponse($response, ['representantes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getRepresentantesPorSupervisor', ['representantes' => []]);
        }
    }

    /**
     * POST /v1/vendas/cubo/clientes-por-representante  {idrepresentante, idsupervisor, idfilial}
     * Clientes com pedido em carteira ou NF faturada nos últimos 12 meses (ou no período informado).
     */
    public function getClientesPorRepresentante(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);
            $idrepresentante = (int)($input['idrepresentante'] ?? 0);
            $idsupervisor = (int)($input['idsupervisor'] ?? 0);
            $idfilial = (int)($input['idfilial'] ?? 0);

            $filiais = $scope->resolverFiliais($idfilial > 0 ? $idfilial : null);
            $gestores = $scope->resolverGestores($idsupervisor > 0 ? $idsupervisor : null);
            [$di, $df] = $this->periodoLookup($input);

            $params = ['di' => $di, 'df' => $df];
            $wP = '';
            $wN = '';
            if (!empty($filiais)) {
                $wP .= ' AND p.idfilial IN (' . $this->inList($filiais) . ')';
                $wN .= ' AND n.idfilial IN (' . $this->inList($filiais) . ')';
            }
            if (!empty($gestores)) {
                $wP .= ' AND p.idsupervisor IN (' . $this->inList($gestores) . ')';
                $wN .= ' AND n.idsupervisor IN (' . $this->inList($gestores) . ')';
            }
            if ($idrepresentante > 0) {
                $wP .= ' AND p.idvendrepre = :rep';
                $wN .= ' AND n.idvendrepre = :rep';
                $params['rep'] = $idrepresentante;
            }

            $sql = "
                SELECT id, nome FROM (
                    SELECT p.idcliforemp AS id, c.fantasia AS nome
                    FROM pedido p
                    JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
                    WHERE p.status IN (1,4) AND p.idtransacao IN (1,17)
                      AND p.data BETWEEN :di AND :df $wP
                    UNION
                    SELECT n.idcliforemp AS id, c.fantasia AS nome
                    FROM nfs n
                    JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
                    WHERE n.status = 2 AND n.idtransacao = 1
                      AND n.dataemissao BETWEEN :di AND :df $wN
                ) x
                ORDER BY nome
                LIMIT 500
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->jsonResponse($response, ['clientes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getClientesPorRepresentante', ['clientes' => []]);
        }
    }

    /**
     * POST /v1/vendas/cubo/filtros-contextuais  {idfilial, idsupervisor, idrepresentante}
     * Saída: options[campo] = [{value: <rótulo>, label: <rótulo>}] (contrato legado, usado também pelo Dashboard).
     */
    public function getFiltrosContextuais(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);
            $idfilial = (int)($input['idfilial'] ?? 0);
            $idsupervisor = (int)($input['idsupervisor'] ?? 0);
            $idrepresentante = (int)($input['idrepresentante'] ?? 0);
            [$di, $df] = $this->periodoLookup($input);

            $f = $this->filtrosVazios($di, $df);
            $f['filiais'] = $scope->resolverFiliais($idfilial > 0 ? $idfilial : null);
            $f['gestores'] = $scope->resolverGestores($idsupervisor > 0 ? $idsupervisor : null);
            $f['representantes'] = $idrepresentante > 0 ? [$idrepresentante] : [];

            $mapa = [
                'cliente' => 'cliente', 'representante' => 'representante', 'supervisor' => 'supervisor',
                'regiao' => 'regiao', 'estado' => 'uf', 'cidade' => 'cidade', 'grupo' => 'grupo',
                'subgrupo' => 'subgrupo', 'produto' => 'produto', 'marca' => 'marca',
            ];

            $params = [];
            $base = $this->baseSql($f, $params);
            $formatted = [];
            foreach ($mapa as $key => $col) {
                $stmt = $this->pdo->prepare("WITH base AS ($base) SELECT DISTINCT $col AS v FROM base WHERE $col IS NOT NULL AND $col <> '' ORDER BY v LIMIT 500");
                $stmt->execute($params);
                $formatted[$key] = array_map(static function ($v) {
                    return ['value' => $v, 'label' => $v];
                }, $stmt->fetchAll(\PDO::FETCH_COLUMN));
            }

            return $this->jsonResponse($response, ['success' => true, 'options' => $formatted]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getFiltrosContextuais', ['success' => false, 'options' => []]);
        }
    }

    /**
     * GET /v1/vendas/cubo/filters/{field}?q=&data_inicio=&data_fim=
     * Opções (value = ID, label = descrição) dos filtros avançados, dentro do escopo do usuário.
     */
    public function getFilterOptions(Request $request, Response $response, array $args): Response
    {
        try {
            $field = $args['field'] ?? '';
            if (!in_array($field, self::CAMPOS_FILTRO, true)) {
                return $this->jsonResponse($response, ['options' => [], 'error' => 'Campo de filtro inválido.'], 400);
            }

            $scope = UserScope::fromRequest($request, $this->pdo);
            $qs = $request->getQueryParams();

            if ($field === 'filial') {
                $opts = [];
                foreach ($this->listarFiliais($scope) as $fl) {
                    $opts[] = ['value' => (string)$fl['idfilial'], 'label' => $fl['nome']];
                }
                return $this->jsonResponse($response, ['options' => $opts]);
            }

            [$di, $df] = $this->periodoLookup($qs);
            $f = $this->filtrosVazios($di, $df);
            $f['filiais'] = $scope->resolverFiliais(null);
            $f['gestores'] = $scope->resolverGestores(null);

            $dim = self::DIMENSOES[$field];
            $params = [];
            $base = $this->baseSql($f, $params);
            $where = "{$dim['id']} IS NOT NULL AND {$dim['txt']} IS NOT NULL";
            $q = trim((string)($qs['q'] ?? ''));
            if ($q !== '') {
                $where .= " AND {$dim['txt']} ILIKE :q";
                $params['q'] = '%' . $q . '%';
            }
            $stmt = $this->pdo->prepare("WITH base AS ($base) SELECT DISTINCT {$dim['id']} AS value, {$dim['txt']} AS label FROM base WHERE $where ORDER BY label LIMIT 1000");
            $stmt->execute($params);
            $options = array_map(static function ($r) {
                return ['value' => (string)$r['value'], 'label' => (string)$r['label']];
            }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

            return $this->jsonResponse($response, ['options' => $options]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getFilterOptions', ['options' => []]);
        }
    }

    // ======================================================================
    // DADOS / TOTAIS / SÉRIE / RANKING
    // ======================================================================

    /**
     * POST /v1/vendas/cubo/data
     */
    public function getData(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);

            $dimKey = $this->dimensao($input['row_dimension'] ?? 'data_documento');
            $metrics = $this->metricas($input['metrics'] ?? null);
            $limit = max(1, min(1000, (int)($input['limit'] ?? 500)));
            [$f, $warning] = $this->filtros($scope, $input['filters'] ?? []);

            $rows = $this->consultarDimensao($f, $dimKey, $metrics, $limit);
            $totals = $this->consultarTotais($f);

            $anterior = $this->periodoAnterior($f);
            $fAnt = $f;
            $fAnt['di'] = $anterior[0];
            $fAnt['df'] = $anterior[1];
            $totalsAnt = $this->consultarTotais($fAnt);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $rows,
                'totals' => $totals,
                'comparativo' => $this->comparativo($totals, $totalsAnt, $anterior),
                'time_series' => $this->consultarSerie($f),
                'row_dimension_label' => self::DIMENSOES[$dimKey]['label'],
                'periodo' => ['data_inicio' => $f['di'], 'data_fim' => $f['df']],
                'truncado' => count($rows) >= $limit,
                'warning' => $warning,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getData');
        }
    }

    /**
     * POST /v1/vendas/cubo/ranking  {dimension, metric, limit, filters}
     */
    public function getRanking(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);

            $dimKey = $this->dimensao($input['dimension'] ?? 'cliente');
            $metric = $this->metricas([$input['metric'] ?? 'valor_total'])[0];
            $limit = max(1, min(100, (int)($input['limit'] ?? 10)));
            [$f, $warning] = $this->filtros($scope, $input['filters'] ?? []);

            $rows = $this->consultarDimensao($f, $dimKey, [$metric], $limit, true);
            $totais = $this->consultarTotais($f);
            $totalMetric = $this->totalDaMetrica($totais, $metric);

            $ranking = [];
            foreach ($rows as $r) {
                $v = (float)$r[$metric];
                $ranking[] = [
                    'id' => $r['dimensao_id'],
                    'nome' => $r['dimensao'],
                    'valor' => $v,
                    'percentual' => $totalMetric > 0 ? round(($v / $totalMetric) * 100, 2) : 0,
                ];
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'ranking' => $ranking,
                'total_geral' => $totalMetric,
                'dimension' => $dimKey,
                'metric' => $metric,
                'warning' => $warning,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getRanking');
        }
    }

    /**
     * POST /v1/vendas/cubo/exportar - CSV com os mesmos filtros/dimensão/métricas da consulta principal.
     */
    public function exportar(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);

            $dimKey = $this->dimensao($input['row_dimension'] ?? 'data_documento');
            $metrics = $this->metricas($input['metrics'] ?? null);
            [$f] = $this->filtros($scope, $input['filters'] ?? []);

            $rows = $this->consultarDimensao($f, $dimKey, $metrics, 50000);

            $out = fopen('php://temp', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $headers = [self::DIMENSOES[$dimKey]['label']];
            foreach ($metrics as $m) {
                $headers[] = self::METRICAS[$m]['label'];
            }
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                $line = [$this->csvTexto($row['dimensao'])];
                foreach ($metrics as $m) {
                    $line[] = $m === 'qtd_documentos'
                        ? (string)(int)$row[$m]
                        : number_format((float)$row[$m], 2, ',', '');
                }
                fputcsv($out, $line, ';');
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);

            $response->getBody()->write($csv);
            return $response
                ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->withHeader('Content-Disposition', 'attachment; filename="cubo_vendas_' . date('Ymd_His') . '.csv"');
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'exportar');
        }
    }

    // ======================================================================
    // DETALHES / ITENS
    // ======================================================================

    /**
     * POST /v1/vendas/cubo/detalhes  {dimensao, id, valor, filters}
     * "id" é o identificador retornado em data[].dimensao_id (null => "(Não informado)").
     * Usa a mesma base (status/transação/escopo/filtros) do resumo.
     */
    public function getDetalhes(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);

            $dimKey = $this->dimensao($input['dimensao'] ?? 'data_documento');
            $dim = self::DIMENSOES[$dimKey];
            [$f] = $this->filtros($scope, $input['filters'] ?? []);

            $hasId = array_key_exists('id', $input);
            $idDrill = $hasId ? $input['id'] : ($input['valor'] ?? null);

            $params = [];
            $base = $this->baseSql($f, $params);
            $cond = '1=1';
            if ($idDrill === null || $idDrill === '') {
                if ($hasId) {
                    $cond = "{$dim['id']} IS NULL";
                } else {
                    throw new \InvalidArgumentException('Valor da dimensão não informado.');
                }
            } else {
                if ($dim['kind'] === 'int') {
                    if (!is_numeric($idDrill) || (string)(int)$idDrill !== (string)(int)$idDrill) {
                        throw new \InvalidArgumentException('Identificador inválido para a dimensão.');
                    }
                    $params['drill'] = (int)$idDrill;
                    $cond = "{$dim['id']} = :drill";
                } elseif ($dim['kind'] === 'date') {
                    $this->validarData((string)$idDrill);
                    $params['drill'] = (string)$idDrill;
                    $cond = "{$dim['id']} = CAST(:drill AS date)";
                } else {
                    $params['drill'] = (string)$idDrill;
                    $cond = "{$dim['id']} = :drill";
                }
            }

            $limite = 500;
            $sql = "
                WITH base AS ($base)
                SELECT data_documento AS data_nf,
                       documento AS numero_nf,
                       MAX(numero_fiscal) AS numero_fiscal,
                       cliente,
                       produto,
                       SUM(valor) AS valor_bruto,
                       SUM(qtd) AS quantidade,
                       SUM(peso) AS peso,
                       SUM(comissao) AS comissao,
                       tipo_documento
                FROM base
                WHERE $cond
                GROUP BY data_documento, documento, cliente, produto, tipo_documento
                ORDER BY data_documento DESC, documento DESC, produto
                LIMIT $limite
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $detalhes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $sqlTot = "
                WITH base AS ($base)
                SELECT COALESCE(SUM(valor),0) AS valor_bruto, COALESCE(SUM(qtd),0) AS quantidade,
                       COALESCE(SUM(peso),0) AS peso, COALESCE(SUM(comissao),0) AS comissao,
                       COUNT(DISTINCT tipo_documento || ':' || documento) AS qtd_documentos
                FROM base WHERE $cond
            ";
            $stmt = $this->pdo->prepare($sqlTot);
            $stmt->execute($params);
            $t = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totais = [
                'valor_bruto' => (float)$t['valor_bruto'],
                'quantidade' => (float)$t['quantidade'],
                'peso' => (float)$t['peso'],
                'comissao' => (float)$t['comissao'],
                'qtd_documentos' => (int)$t['qtd_documentos'],
            ];

            return $this->jsonResponse($response, [
                'success' => true,
                'detalhes' => $detalhes,
                'totais' => $totais,
                'truncado' => count($detalhes) >= $limite,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getDetalhes');
        }
    }

    /**
     * POST /v1/vendas/cubo/itens-documento  {tipo: PEDIDO|NF, numero_documento}
     */
    public function getItensDocumento(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $scope = UserScope::fromRequest($request, $this->pdo);

            $tipo = strtoupper((string)($input['tipo'] ?? 'PEDIDO'));
            if (!in_array($tipo, ['PEDIDO', 'NF'], true)) {
                throw new \InvalidArgumentException('Tipo de documento inválido.');
            }
            $numero = (int)($input['numero_documento'] ?? 0);
            if ($numero <= 0) {
                throw new \InvalidArgumentException('Número do documento inválido.');
            }

            if ($tipo === 'PEDIDO') {
                $stmt = $this->pdo->prepare('SELECT idfilial, idsupervisor FROM pedido WHERE idpedido = :n AND status IN (1,4) AND idtransacao IN (1,17)');
            } else {
                $stmt = $this->pdo->prepare('SELECT idfilial, idsupervisor FROM nfs WHERE idnfs = :n AND status = 2 AND idtransacao = 1');
            }
            $stmt->execute(['n' => $numero]);
            $cab = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$cab) {
                return $this->jsonResponse($response, ['success' => false, 'error' => 'Documento não encontrado.'], 404);
            }
            if ($scope->filialRestrita() && !in_array((int)$cab['idfilial'], $scope->filiais, true)) {
                return $this->jsonResponse($response, ['success' => false, 'error' => 'Acesso negado: documento fora do seu escopo.'], 403);
            }
            if ($scope->gestorRestrito() && !in_array((int)$cab['idsupervisor'], $scope->gestores, true)) {
                return $this->jsonResponse($response, ['success' => false, 'error' => 'Acesso negado: documento fora do seu escopo.'], 403);
            }

            if ($tipo === 'PEDIDO') {
                $sql = "
                    SELECT i.descricao AS produto,
                           GREATEST(pi.qt - COALESCE(pi.qtfaturado,0), 0) AS quantidade,
                           pi.valor AS valor_unitario,
                           GREATEST(pi.qt - COALESCE(pi.qtfaturado,0), 0) * pi.valor AS valor_total,
                           GREATEST(pi.qt - COALESCE(pi.qtfaturado,0), 0) * COALESCE(i.pesoliquido,0) AS peso,
                           COALESCE(gp.descricao, g.descricao) AS grupo,
                           0 AS comissao
                    FROM pedido_item pi
                    JOIN item i ON i.iditem = pi.iditem
                    LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
                    LEFT JOIN grupo gp ON gp.idgrupo = NULLIF(g.idgrupopai, 0)
                    WHERE pi.idpedido = :n AND pi.ativo = 'S'
                    ORDER BY i.descricao
                ";
            } else {
                $sql = "
                    SELECT i.descricao AS produto,
                           ni.qt AS quantidade,
                           ni.valor AS valor_unitario,
                           (ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete,0) + COALESCE(ni.valorseguro,0) + COALESCE(ni.valoroutrasdespesas,0)) AS valor_total,
                           (COALESCE(ni.pesoliquido,0) * ni.qt) AS peso,
                           COALESCE(gp.descricao, g.descricao) AS grupo,
                           COALESCE(ni.valor_comissao,0) AS comissao
                    FROM nfs_item ni
                    JOIN item i ON i.iditem = ni.iditem
                    LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
                    LEFT JOIN grupo gp ON gp.idgrupo = NULLIF(g.idgrupopai, 0)
                    WHERE ni.idnfs = :n
                    ORDER BY i.descricao
                ";
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['n' => $numero]);
            $itens = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totais = ['valor_bruto' => 0, 'quantidade' => 0, 'peso' => 0, 'comissao' => 0];
            foreach ($itens as $item) {
                $totais['valor_bruto'] += (float)$item['valor_total'];
                $totais['quantidade'] += (float)$item['quantidade'];
                $totais['peso'] += (float)$item['peso'];
                $totais['comissao'] += (float)$item['comissao'];
            }

            return $this->jsonResponse($response, ['success' => true, 'itens' => $itens, 'totais' => $totais]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e, 'getItensDocumento');
        }
    }

    // ======================================================================
    // CONSULTAS (todas partem da mesma base)
    // ======================================================================

    /**
     * Base única em nível de item: pedidos em carteira (saldo não faturado) UNION ALL NFs faturadas.
     * Escopo e filtros são aplicados aqui, em ambos os ramos.
     */
    private function baseSql(array $f, array &$params): string
    {
        $params['di'] = $f['di'];
        $params['df'] = $f['df'];

        $comum = '';
        if (!empty($f['regioes'])) {
            $comum .= ' AND cli.idregiao IN (' . $this->inList($f['regioes']) . ')';
        }
        if (!empty($f['ufs'])) {
            $ph = [];
            foreach (array_values($f['ufs']) as $i => $uf) {
                $ph[] = ":uf$i";
                $params["uf$i"] = $uf;
            }
            $comum .= ' AND cid.uf IN (' . implode(',', $ph) . ')';
        }
        if (!empty($f['cidades'])) {
            $comum .= ' AND c.idcidade IN (' . $this->inList($f['cidades']) . ')';
        }
        if (!empty($f['grupos'])) {
            $comum .= ' AND COALESCE(gp.idgrupo, g.idgrupo) IN (' . $this->inList($f['grupos']) . ')';
        }
        if (!empty($f['subgrupos'])) {
            $comum .= ' AND g.idgrupo IN (' . $this->inList($f['subgrupos']) . ')';
        }
        if (!empty($f['tipos_produto'])) {
            $comum .= ' AND i.tipo IN (' . $this->inList($f['tipos_produto']) . ')';
        }
        if (!empty($f['marcas'])) {
            $comum .= ' AND i.idmarca IN (' . $this->inList($f['marcas']) . ')';
        }
        if (!empty($f['produtos'])) {
            $comum .= ' AND i.iditem IN (' . $this->inList($f['produtos']) . ')';
        }

        $docP = '';
        $docN = '';
        if (!empty($f['filiais'])) {
            $docP .= ' AND p.idfilial IN (' . $this->inList($f['filiais']) . ')';
            $docN .= ' AND n.idfilial IN (' . $this->inList($f['filiais']) . ')';
        }
        if (!empty($f['gestores'])) {
            $docP .= ' AND p.idsupervisor IN (' . $this->inList($f['gestores']) . ')';
            $docN .= ' AND n.idsupervisor IN (' . $this->inList($f['gestores']) . ')';
        }
        if (!empty($f['representantes'])) {
            $docP .= ' AND p.idvendrepre IN (' . $this->inList($f['representantes']) . ')';
            $docN .= ' AND n.idvendrepre IN (' . $this->inList($f['representantes']) . ')';
        }
        if (!empty($f['clientes'])) {
            $docP .= ' AND p.idcliforemp IN (' . $this->inList($f['clientes']) . ')';
            $docN .= ' AND n.idcliforemp IN (' . $this->inList($f['clientes']) . ')';
        }
        if (!empty($f['tipos_pedido'])) {
            $docP .= ' AND p.idtransacao IN (' . $this->inList($f['tipos_pedido']) . ')';
            $docN .= ' AND pp.idtransacao IN (' . $this->inList($f['tipos_pedido']) . ')';
        }

        $saldo = 'GREATEST(pi.qt - COALESCE(pi.qtfaturado, 0), 0)';

        return "
            SELECT 'PEDIDO' AS tipo_documento, p.idpedido AS documento, CAST(NULL AS bigint) AS numero_fiscal,
                   p.data AS data_documento,
                   CASE p.status WHEN 1 THEN 'ABERTO' WHEN 4 THEN 'FATURADO PARCIAL' ELSE 'OUTROS' END AS status_descricao,
                   p.idfilial AS idfilial, f.nome AS filial,
                   p.idcliforemp AS idcliente, c.fantasia AS cliente,
                   p.idvendrepre AS idrepresentante, vend.fantasia AS representante,
                   p.idsupervisor AS idsupervisor, sup.razao AS supervisor,
                   cli.idregiao AS idregiao, rg.descricao AS regiao,
                   cid.uf AS uf, c.idcidade AS idcidade, cid.descricao AS cidade,
                   COALESCE(gp.idgrupo, g.idgrupo) AS idgrupo, COALESCE(gp.descricao, g.descricao) AS grupo,
                   g.idgrupo AS idsubgrupo, g.descricao AS subgrupo,
                   i.tipo AS idtipoproduto, COALESCE(tp.tipo, 'Tipo ' || i.tipo) AS tipo_produto,
                   i.idmarca AS idmarca, m.descricao AS marca,
                   i.iditem AS iditem, i.descricao AS produto,
                   p.idtransacao AS idtipopedido, pt.descricao AS tipo_pedido,
                   ($saldo * pi.valor) AS valor,
                   $saldo AS qtd,
                   ($saldo * COALESCE(i.pesoliquido, 0)) AS peso,
                   CAST(0 AS numeric) AS comissao
            FROM pedido p
            JOIN pedido_item pi ON pi.idpedido = p.idpedido AND pi.ativo = 'S'
            JOIN item i ON i.iditem = pi.iditem
            JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
            LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
            LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            LEFT JOIN grupo gp ON gp.idgrupo = NULLIF(g.idgrupopai, 0)
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            LEFT JOIN tipo_item_produto tp ON tp.idtipo = i.tipo
            LEFT JOIN cliforemp vend ON vend.idcliforemp = p.idvendrepre
            LEFT JOIN cliforemp sup ON sup.idcliforemp = p.idsupervisor
            LEFT JOIN filial f ON f.idfilial = p.idfilial
            LEFT JOIN pedido_transacao pt ON pt.idtransacao = p.idtransacao
            WHERE p.status IN (1,4)
              AND p.idtransacao IN (1,17)
              AND p.data BETWEEN :di AND :df
              AND $saldo > 0
              $docP $comum

            UNION ALL

            SELECT 'NF' AS tipo_documento, n.idnfs AS documento, CAST(n.numeronf AS bigint) AS numero_fiscal,
                   n.dataemissao AS data_documento,
                   'FATURADO' AS status_descricao,
                   n.idfilial AS idfilial, f.nome AS filial,
                   n.idcliforemp AS idcliente, c.fantasia AS cliente,
                   n.idvendrepre AS idrepresentante, vend.fantasia AS representante,
                   n.idsupervisor AS idsupervisor, sup.razao AS supervisor,
                   cli.idregiao AS idregiao, rg.descricao AS regiao,
                   cid.uf AS uf, c.idcidade AS idcidade, cid.descricao AS cidade,
                   COALESCE(gp.idgrupo, g.idgrupo) AS idgrupo, COALESCE(gp.descricao, g.descricao) AS grupo,
                   g.idgrupo AS idsubgrupo, g.descricao AS subgrupo,
                   i.tipo AS idtipoproduto, COALESCE(tp.tipo, 'Tipo ' || i.tipo) AS tipo_produto,
                   i.idmarca AS idmarca, m.descricao AS marca,
                   i.iditem AS iditem, i.descricao AS produto,
                   pp.idtransacao AS idtipopedido, pt.descricao AS tipo_pedido,
                   (ni.valor_total_com_ipi_st + COALESCE(ni.valorfrete, 0) + COALESCE(ni.valorseguro, 0) + COALESCE(ni.valoroutrasdespesas, 0)) AS valor,
                   ni.qt AS qtd,
                   (COALESCE(ni.pesoliquido, 0) * ni.qt) AS peso,
                   COALESCE(ni.valor_comissao, 0) AS comissao
            FROM nfs n
            JOIN nfs_item ni ON ni.idnfs = n.idnfs
            JOIN item i ON i.iditem = ni.iditem
            JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            LEFT JOIN cliente cli ON cli.idcliforemp = c.idcliforemp
            LEFT JOIN regiao rg ON rg.idregiao = cli.idregiao
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN grupo g ON g.idgrupo = i.idgrupo
            LEFT JOIN grupo gp ON gp.idgrupo = NULLIF(g.idgrupopai, 0)
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            LEFT JOIN tipo_item_produto tp ON tp.idtipo = i.tipo
            LEFT JOIN cliforemp vend ON vend.idcliforemp = n.idvendrepre
            LEFT JOIN cliforemp sup ON sup.idcliforemp = n.idsupervisor
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            LEFT JOIN pedido pp ON pp.idpedido = ni.idpedido
            LEFT JOIN pedido_transacao pt ON pt.idtransacao = pp.idtransacao
            WHERE n.status = 2
              AND n.idtransacao = 1
              AND n.dataemissao BETWEEN :di AND :df
              $docN $comum
        ";
    }

    private function consultarDimensao(array $f, string $dimKey, array $metrics, int $limit, bool $semNulos = false): array
    {
        $dim = self::DIMENSOES[$dimKey];
        $params = [];
        $base = $this->baseSql($f, $params);

        $selects = [];
        foreach ($metrics as $m) {
            $selects[] = $this->exprMetrica($m) . " AS $m";
        }
        $order = $dim['kind'] === 'date' ? '1 DESC' : ($metrics[0] . ' DESC, 2');
        $extra = $semNulos ? " HAVING {$dim['id']} IS NOT NULL" : '';

        $sql = "
            WITH base AS ($base)
            SELECT {$dim['id']} AS dimensao_id,
                   COALESCE(CAST({$dim['txt']} AS text), '" . self::MSG_NAO_INFORMADO . "') AS dimensao,
                   " . implode(', ', $selects) . "
            FROM base
            GROUP BY {$dim['id']}, {$dim['txt']}
            $extra
            ORDER BY $order
            LIMIT $limit
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            foreach ($metrics as $m) {
                $r[$m] = $m === 'qtd_documentos' ? (int)$r[$m] : (float)$r[$m];
            }
            if ($r['dimensao_id'] !== null && $dim['kind'] === 'int') {
                $r['dimensao_id'] = (int)$r['dimensao_id'];
            }
        }
        return $rows;
    }

    private function consultarTotais(array $f): array
    {
        $params = [];
        $base = $this->baseSql($f, $params);
        $sql = "
            WITH base AS ($base)
            SELECT COALESCE(SUM(valor), 0) AS valor_total, COALESCE(SUM(qtd), 0) AS quantidade_total,
                   COALESCE(SUM(peso), 0) AS peso_total, COALESCE(SUM(comissao), 0) AS comissao_total,
                   COUNT(DISTINCT tipo_documento || ':' || documento) AS qtd_documentos
            FROM base
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC);

        $valor = (float)$t['valor_total'];
        $docs = (int)$t['qtd_documentos'];
        return [
            'valor_bruto' => $valor,
            'quantidade' => (float)$t['quantidade_total'],
            'peso' => (float)$t['peso_total'],
            'comissao' => (float)$t['comissao_total'],
            'qtd_documentos' => $docs,
            'ticket_medio' => $docs > 0 ? $valor / $docs : 0.0,
        ];
    }

    private function consultarSerie(array $f): array
    {
        $params = [];
        $base = $this->baseSql($f, $params);
        $sql = "
            WITH base AS ($base)
            SELECT TO_CHAR(data_documento, 'YYYY-MM') AS periodo, SUM(valor) AS valor,
                   SUM(qtd) AS quantidade, SUM(peso) AS peso
            FROM base GROUP BY 1 ORDER BY 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $porMes = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $porMes[$r['periodo']] = $r;
        }

        $labels = [];
        $valor = [];
        $qtd = [];
        $peso = [];
        $cur = new \DateTime(substr($f['di'], 0, 7) . '-01');
        $fim = new \DateTime(substr($f['df'], 0, 7) . '-01');
        while ($cur <= $fim) {
            $k = $cur->format('Y-m');
            $labels[] = $k;
            $valor[] = (float)($porMes[$k]['valor'] ?? 0);
            $qtd[] = (float)($porMes[$k]['quantidade'] ?? 0);
            $peso[] = (float)($porMes[$k]['peso'] ?? 0);
            $cur->modify('+1 month');
        }

        return [
            'labels' => $labels,
            'values' => $valor,
            'series' => ['valor_total' => $valor, 'quantidade_total' => $qtd, 'peso_total' => $peso],
        ];
    }

    private function periodoAnterior(array $f): array
    {
        $di = new \DateTime($f['di']);
        $df = new \DateTime($f['df']);
        $dias = $di->diff($df)->days + 1;
        $fimAnt = (clone $di)->modify('-1 day');
        $iniAnt = (clone $fimAnt)->modify('-' . ($dias - 1) . ' days');
        return [$iniAnt->format('Y-m-d'), $fimAnt->format('Y-m-d')];
    }

    private function comparativo(array $atual, array $ant, array $periodo): array
    {
        $out = ['periodo_anterior' => ['data_inicio' => $periodo[0], 'data_fim' => $periodo[1]]];
        foreach (['valor_bruto', 'quantidade', 'peso', 'comissao', 'qtd_documentos', 'ticket_medio'] as $k) {
            $a = (float)$atual[$k];
            $b = (float)$ant[$k];
            $out[$k] = [
                'atual' => $a,
                'anterior' => $b,
                'variacao_pct' => $b > 0 ? round((($a - $b) / $b) * 100, 2) : null,
            ];
        }
        return $out;
    }

    private function totalDaMetrica(array $totais, string $metric): float
    {
        $map = [
            'valor_total' => 'valor_bruto', 'quantidade_total' => 'quantidade', 'peso_total' => 'peso',
            'comissao_total' => 'comissao', 'qtd_documentos' => 'qtd_documentos', 'ticket_medio' => 'ticket_medio',
        ];
        return (float)$totais[$map[$metric]];
    }

    private function exprMetrica(string $m): string
    {
        switch ($m) {
            case 'valor_total':
                return 'COALESCE(SUM(valor), 0)';
            case 'quantidade_total':
                return 'COALESCE(SUM(qtd), 0)';
            case 'peso_total':
                return 'COALESCE(SUM(peso), 0)';
            case 'comissao_total':
                return 'COALESCE(SUM(comissao), 0)';
            case 'qtd_documentos':
                return "COUNT(DISTINCT tipo_documento || ':' || documento)";
            case 'ticket_medio':
                return "COALESCE(SUM(valor) / NULLIF(COUNT(DISTINCT tipo_documento || ':' || documento), 0), 0)";
        }
        throw new \InvalidArgumentException('Métrica inválida.');
    }

    // ======================================================================
    // VALIDAÇÃO / ESCOPO
    // ======================================================================

    /**
     * Normaliza filtros e aplica o escopo do usuário por interseção (nunca substitui).
     * Lança DomainException (403) se filial/gestor pedidos estiverem fora do escopo.
     *
     * @return array [filtros, warning]
     */
    private function filtros(UserScope $scope, $filters): array
    {
        $filters = is_array($filters) ? $filters : [];
        $di = (string)($filters['data_inicio'] ?? date('Y-m-01'));
        $df = (string)($filters['data_fim'] ?? date('Y-m-t'));
        $this->validarData($di);
        $this->validarData($df);
        if ($di > $df) {
            throw new \InvalidArgumentException('A data inicial não pode ser maior que a data final.');
        }

        $warning = null;
        $d1 = new \DateTime($di);
        if ($d1->diff(new \DateTime($df))->days > 365) {
            $df = (clone $d1)->modify('+365 days')->format('Y-m-d');
            $warning = 'Período limitado a 365 dias para manter performance';
        }

        $f = $this->filtrosVazios($di, $df);
        $f['filiais'] = $scope->resolverFiliais($this->ids($filters['filial'] ?? null, 'filial'));
        $f['gestores'] = $scope->resolverGestores($this->ids($filters['supervisor'] ?? ($filters['gestor'] ?? null), 'supervisor'));
        $f['representantes'] = $this->ids($filters['representante'] ?? null, 'representante');
        $f['clientes'] = $this->ids($filters['cliente'] ?? null, 'cliente');
        $f['regioes'] = $this->ids($filters['regiao'] ?? null, 'região');
        $f['cidades'] = $this->ids($filters['cidade'] ?? null, 'cidade');
        $f['grupos'] = $this->ids($filters['grupo'] ?? null, 'grupo');
        $f['subgrupos'] = $this->ids($filters['subgrupo'] ?? null, 'subgrupo');
        $f['tipos_produto'] = $this->ids($filters['tipo_produto'] ?? null, 'tipo de produto');
        $f['marcas'] = $this->ids($filters['marca'] ?? null, 'marca');
        $f['produtos'] = $this->ids($filters['produto'] ?? null, 'produto');
        $f['tipos_pedido'] = $this->ids($filters['tipo_de_pedido'] ?? null, 'tipo de pedido');

        $ufs = [];
        foreach ($this->lista($filters['estado'] ?? null) as $uf) {
            $uf = strtoupper($uf);
            if (!preg_match('/^[A-Z]{2}$/', $uf)) {
                throw new \InvalidArgumentException('UF inválida.');
            }
            $ufs[] = $uf;
        }
        $f['ufs'] = $ufs;

        return [$f, $warning];
    }

    private function filtrosVazios(string $di, string $df): array
    {
        return [
            'di' => $di, 'df' => $df,
            'filiais' => [], 'gestores' => [], 'representantes' => [], 'clientes' => [],
            'regioes' => [], 'ufs' => [], 'cidades' => [], 'grupos' => [], 'subgrupos' => [],
            'tipos_produto' => [], 'marcas' => [], 'produtos' => [], 'tipos_pedido' => [],
        ];
    }

    private function periodoLookup(array $in): array
    {
        $di = (string)($in['data_inicio'] ?? date('Y-m-d', strtotime('-12 months')));
        $df = (string)($in['data_fim'] ?? date('Y-m-d'));
        $this->validarData($di);
        $this->validarData($df);
        if ($di > $df) {
            throw new \InvalidArgumentException('A data inicial não pode ser maior que a data final.');
        }
        return [$di, $df];
    }

    private function validarData(string $d): void
    {
        $dt = \DateTime::createFromFormat('!Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) {
            throw new \InvalidArgumentException('Data inválida (use AAAA-MM-DD).');
        }
    }

    private function dimensao($key): string
    {
        $key = (string)$key;
        $key = self::DIM_ALIAS[$key] ?? $key;
        if (!isset(self::DIMENSOES[$key])) {
            throw new \InvalidArgumentException('Dimensão inválida.');
        }
        return $key;
    }

    /** Exige >= 1 métrica válida; aceita aliases legados. */
    private function metricas($metrics): array
    {
        if (!is_array($metrics) || empty($metrics)) {
            throw new \InvalidArgumentException('Selecione ao menos uma métrica.');
        }
        $out = [];
        foreach ($metrics as $m) {
            $m = (string)$m;
            $canon = null;
            foreach (self::METRICAS as $k => $def) {
                if ($m === $k || in_array($m, $def['alias'], true)) {
                    $canon = $k;
                    break;
                }
            }
            if ($canon === null) {
                throw new \InvalidArgumentException('Métrica inválida.');
            }
            $out[$canon] = $canon;
        }
        return array_values($out);
    }

    /** @return string[] valores não vazios (aceita escalar, lista ou "a,b") */
    private function lista($valor): array
    {
        $itens = is_array($valor) ? $valor : explode(',', (string)$valor);
        $out = [];
        foreach ($itens as $i) {
            $i = trim((string)$i);
            if ($i !== '') {
                $out[] = $i;
            }
        }
        return $out;
    }

    /** @return int[] IDs numéricos; valor não numérico => 400. 0 é ignorado ("todos"). */
    private function ids($valor, string $rotulo): array
    {
        $out = [];
        foreach ($this->lista($valor) as $i) {
            if (!ctype_digit($i)) {
                throw new \InvalidArgumentException("Filtro inválido: $rotulo.");
            }
            if ((int)$i > 0) {
                $out[(int)$i] = (int)$i;
            }
        }
        return array_values($out);
    }

    private function inList(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }

    private function listarFiliais(UserScope $scope): array
    {
        $sql = "SELECT idfilial, nome FROM filial WHERE inativo = 'N'";
        if ($scope->filialRestrita()) {
            $sql .= ' AND idfilial IN (' . $this->inList($scope->filiais) . ')';
        }
        $sql .= ' ORDER BY idfilial';
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function csvTexto($v): string
    {
        $v = (string)$v;
        return ($v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v;
    }

    // ======================================================================
    // INFRA
    // ======================================================================

    private function input(Request $request): array
    {
        $body = (string)$request->getBody();
        $json = json_decode($body, true);
        return is_array($json) ? $json : [];
    }

    /** 403 (escopo), 400 (validação) ou 500 genérico (detalhe apenas no log). */
    private function erro(Response $response, \Throwable $e, string $contexto, array $extra = []): Response
    {
        if ($e instanceof \DomainException) {
            return $this->jsonResponse($response, array_merge($extra, ['success' => false, 'error' => $e->getMessage()]), 403);
        }
        if ($e instanceof \InvalidArgumentException) {
            return $this->jsonResponse($response, array_merge($extra, ['success' => false, 'error' => $e->getMessage()]), 400);
        }
        error_log("VendasCuboController::$contexto - " . get_class($e) . ': ' . $e->getMessage());
        return $this->jsonResponse($response, array_merge($extra, ['success' => false, 'error' => self::MSG_ERRO_INTERNO]), 500);
    }

    private function jsonResponse($response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
