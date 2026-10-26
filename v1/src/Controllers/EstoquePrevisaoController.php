<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Controller de Estoque com Previsão de Chegada.
 *
 * Regras especializadas:
 *  - Suporte a visualização Unificada (Filial 1 Matriz + Filial 6 PR);
 *  - Previsão de chegada considera compras (OC) da Matriz (1) + Filial (6) de forma unificada;
 *  - Ambas as filiais enxergam a previsão combinada de chegada;
 *  - Cálculo real de Carteira Reservada (pedidos abertos/parciais status 1, 4);
 *  - Cálculo de Estoque Líquido (Físico - Carteira) e Estoque Futuro (Líquido + Previsão).
 */
class EstoquePrevisaoController
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

    /**
     * Obtém as permissões do usuário logado
     */
    private function getUserPermissions(Request $request, int $uidFallback = 0): array
    {
        $user = $request->getAttribute('user') ?? [];
        $uid = (int)($user['idusuario'] ?? $uidFallback);
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);

        $stmt = $this->pdo->prepare('SELECT idusuario, idcliforemp, username, dash_filiais, dash_gestores FROM usuario WHERE idusuario = :uid OR idcliforemp = :uid LIMIT 1');
        $stmt->execute(['uid' => $uid]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $filiaisRaw = !empty($userData['dash_filiais']) ? explode(',', $userData['dash_filiais']) : [];
        $filiais = array_values(array_unique(array_filter(array_map('intval', $filiaisRaw), fn($f) => $f > 0)));

        if (empty($filiais) && $isAdmin) {
            $filiais = [1, 6];
        }

        return [
            'idusuario' => (int)($userData['idusuario'] ?? $uid),
            'username' => strtoupper((string)($userData['username'] ?? ($user['username'] ?? 'Usuário'))),
            'filiais' => $filiais,
            'is_admin' => $isAdmin,
        ];
    }

    /**
     * GET /v1/estoque-previsao/filiais
     */
    public function getFiliais(Request $request, Response $response): Response
    {
        try {
            $permissions = $this->getUserPermissions($request);
            $filiais = [];

            // Opção Unificada para quem tem acesso à Matriz e Filial PR
            $filiais[] = [
                'idfilial' => 0,
                'nome' => 'UNIFICADO (MATRIZ 1 + FILIAL PR 6)',
                'razao' => 'ESTOQUE & PREVISÃO CONSOLIDADOS'
            ];

            $sql = "SELECT idfilial, razao as nome FROM filial WHERE inativo = 'N' AND idfilial IN (1, 6) ORDER BY idfilial";
            $stmt = $this->pdo->query($sql);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $filiais[] = [
                    'idfilial' => (int)$f['idfilial'],
                    'nome' => ((int)$f['idfilial'] === 1 ? 'MATRIZ 1 - SANTA CATARINA' : 'FILIAL 6 - PARANÁ (PR)'),
                    'razao' => $f['nome']
                ];
            }

            return $this->json($response, ['success' => true, 'data' => $filiais]);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/estoque-previsao/marcas
     */
    public function getMarcas(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT DISTINCT 
                    M.IDMARCA as id,
                    M.DESCRICAO as nome
                FROM MARCA M
                INNER JOIN ITEM I ON I.IDMARCA = M.IDMARCA
                INNER JOIN ESTOQUE_FILIAL EF ON EF.IDITEM = I.IDITEM
                WHERE M.INATIVO = 'N'
                    AND I.INATIVO = 'N'
                    AND I.TIPO = '0'
                    AND EF.IDFILIAL IN (1, 6)
                    AND M.IDMARCA NOT IN (60)
                ORDER BY M.DESCRICAO
            ";
            $stmt = $this->pdo->query($sql);
            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, ['success' => true, 'data' => $marcas]);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/estoque-previsao/resumo
     */
    public function getResumo(Request $request, Response $response): Response
    {
        try {
            $input = (array)($request->getParsedBody() ?? json_decode($request->getBody()->getContents(), true) ?? []);
            $uid = (int)($input['idusuario'] ?? 0);
            $filtroId = (int)($input['filtro_id'] ?? 0);

            $permissions = $this->getUserPermissions($request, $uid);

            // Resumo Estoque Físico (Unidades e Peso em KG)
            $sqlEstoque = "
                SELECT 
                    COALESCE(SUM(ef.SALDOATUAL), 0) as total_estoque,
                    COALESCE(SUM(CASE WHEN ef.IDFILIAL = 1 THEN ef.SALDOATUAL ELSE 0 END), 0) as estoque_matriz,
                    COALESCE(SUM(CASE WHEN ef.IDFILIAL = 6 THEN ef.SALDOATUAL ELSE 0 END), 0) as estoque_filial,
                    COALESCE(SUM(ef.SALDOATUAL * COALESCE(i.PESOLIQUIDO, 0)), 0) as total_estoque_kg,
                    COALESCE(SUM(CASE WHEN ef.IDFILIAL = 1 THEN ef.SALDOATUAL * COALESCE(i.PESOLIQUIDO, 0) ELSE 0 END), 0) as estoque_matriz_kg,
                    COALESCE(SUM(CASE WHEN ef.IDFILIAL = 6 THEN ef.SALDOATUAL * COALESCE(i.PESOLIQUIDO, 0) ELSE 0 END), 0) as estoque_filial_kg,
                    COUNT(DISTINCT ef.IDITEM) as total_produtos,
                    COUNT(DISTINCT CASE WHEN ef.SALDOATUAL <= 0 THEN ef.IDITEM END) as produtos_zerados,
                    COUNT(DISTINCT CASE WHEN ef.SALDOATUAL BETWEEN 1 AND 10 THEN ef.IDITEM END) as produtos_criticos,
                    COUNT(DISTINCT CASE WHEN ef.SALDOATUAL BETWEEN 11 AND 50 THEN ef.IDITEM END) as produtos_baixo
                FROM ESTOQUE_FILIAL ef
                JOIN ITEM i ON i.IDITEM = ef.IDITEM AND i.INATIVO = 'N' AND i.TIPO = '0'
                WHERE ef.IDFILIAL IN (1, 6)
            ";
            if ($filtroId === 1 || $filtroId === 6) {
                $sqlEstoque .= " AND ef.IDFILIAL = {$filtroId}";
            }
            $stmtEstoque = $this->pdo->query($sqlEstoque);
            $estoque = $stmtEstoque->fetch(PDO::FETCH_ASSOC) ?: [];

            // Resumo Carteira Reservada (pedidos status 1 e 4 - Unidades e Peso em KG)
            $sqlCarteira = "
                SELECT 
                    COALESCE(SUM(COALESCE(pi.QTSALDO, pi.QT)), 0) as total_reservado,
                    COALESCE(SUM(CASE WHEN p.IDFILIAL = 1 THEN COALESCE(pi.QTSALDO, pi.QT) ELSE 0 END), 0) as reservado_matriz,
                    COALESCE(SUM(CASE WHEN p.IDFILIAL = 6 THEN COALESCE(pi.QTSALDO, pi.QT) ELSE 0 END), 0) as reservado_filial,
                    COALESCE(SUM(COALESCE(pi.QTSALDO, pi.QT) * COALESCE(i.PESOLIQUIDO, 0)), 0) as total_reservado_kg,
                    COALESCE(SUM(CASE WHEN p.IDFILIAL = 1 THEN COALESCE(pi.QTSALDO, pi.QT) * COALESCE(i.PESOLIQUIDO, 0) ELSE 0 END), 0) as reservado_matriz_kg,
                    COALESCE(SUM(CASE WHEN p.IDFILIAL = 6 THEN COALESCE(pi.QTSALDO, pi.QT) * COALESCE(i.PESOLIQUIDO, 0) ELSE 0 END), 0) as reservado_filial_kg
                FROM PEDIDO_ITEM pi
                JOIN PEDIDO p ON p.IDPEDIDO = pi.IDPEDIDO
                JOIN ITEM i ON i.IDITEM = pi.IDITEM AND i.INATIVO = 'N' AND i.TIPO = '0'
                WHERE p.STATUS IN (1, 4) AND p.IDFILIAL IN (1, 6)
            ";
            if ($filtroId === 1 || $filtroId === 6) {
                $sqlCarteira .= " AND p.IDFILIAL = {$filtroId}";
            }
            $stmtCart = $this->pdo->query($sqlCarteira);
            $carteira = $stmtCart->fetch(PDO::FETCH_ASSOC) ?: [];

            // Resumo Previsão de Chegada (Compras / OCs pendentes Matriz + Filial PR - Unidades e Peso em KG)
            $sqlPrevisao = "
                SELECT 
                    COALESCE(SUM(oci.QTSALDO), 0) as total_previsao,
                    COALESCE(SUM(CASE WHEN oc.IDFILIAL = 1 THEN oci.QTSALDO ELSE 0 END), 0) as previsao_matriz,
                    COALESCE(SUM(CASE WHEN oc.IDFILIAL = 6 THEN oci.QTSALDO ELSE 0 END), 0) as previsao_filial,
                    COALESCE(SUM(oci.QTSALDO * COALESCE(i.PESOLIQUIDO, 0)), 0) as total_previsao_kg,
                    COALESCE(SUM(CASE WHEN oc.IDFILIAL = 1 THEN oci.QTSALDO * COALESCE(i.PESOLIQUIDO, 0) ELSE 0 END), 0) as previsao_matriz_kg,
                    COALESCE(SUM(CASE WHEN oc.IDFILIAL = 6 THEN oci.QTSALDO * COALESCE(i.PESOLIQUIDO, 0) ELSE 0 END), 0) as previsao_filial_kg,
                    COUNT(DISTINCT oci.IDITEM) as produtos_com_previsao,
                    COUNT(DISTINCT oci.IDOC) as total_ocs_abertas
                FROM OC_ITEM oci
                JOIN OC ON oc.IDOC = oci.IDOC
                JOIN OC_TRANSACAO oct ON oct.IDTRANSACAO = oc.IDTRANSACAO
                JOIN ITEM i ON i.IDITEM = oci.IDITEM AND i.INATIVO = 'N' AND i.TIPO = '0'
                WHERE oc.STATUS IN ('1', '4')
                  AND oc.SITUACAO IN (2, 6)
                  AND oct.TIPO = 0
                  AND oci.QTSALDO > 0
                  AND oc.IDFILIAL IN (1, 6)
            ";
            $stmtPrev = $this->pdo->query($sqlPrevisao);
            $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalEstoque = (float)($estoque['total_estoque'] ?? 0);
            $totalReservado = (float)($carteira['total_reservado'] ?? 0);
            $totalPrevisao = (float)($prev['total_previsao'] ?? 0);
            $totalLivre = max(0, $totalEstoque - $totalReservado);
            $totalFuturo = $totalLivre + $totalPrevisao;

            $totalEstoqueKg = (float)($estoque['total_estoque_kg'] ?? 0);
            $totalReservadoKg = (float)($carteira['total_reservado_kg'] ?? 0);
            $totalPrevisaoKg = (float)($prev['total_previsao_kg'] ?? 0);
            $totalLivreKg = max(0, $totalEstoqueKg - $totalReservadoKg);
            $totalFuturoKg = $totalLivreKg + $totalPrevisaoKg;

            $listaFiliais = [
                ['idfilial' => 0, 'nome' => 'UNIFICADO (MATRIZ + PR)'],
                ['idfilial' => 1, 'nome' => 'MATRIZ 1 (SC)'],
                ['idfilial' => 6, 'nome' => 'FILIAL 6 (PR)'],
            ];

            return $this->json($response, [
                'success' => true,
                'data' => [
                    // Métricas em Unidades
                    'total_estoque' => $totalEstoque,
                    'total_reservado' => $totalReservado,
                    'total_livre' => $totalLivre,
                    'total_previsao' => $totalPrevisao,
                    'total_futuro' => $totalFuturo,
                    // Métricas em Peso (KG)
                    'total_estoque_kg' => $totalEstoqueKg,
                    'total_reservado_kg' => $totalReservadoKg,
                    'total_livre_kg' => $totalLivreKg,
                    'total_previsao_kg' => $totalPrevisaoKg,
                    'total_futuro_kg' => $totalFuturoKg,
                    // Contadores de Produtos
                    'total_produtos' => (int)($estoque['total_produtos'] ?? 0),
                    'produtos_zerados' => (int)($estoque['produtos_zerados'] ?? 0),
                    'produtos_criticos' => (int)($estoque['produtos_criticos'] ?? 0),
                    'produtos_baixo' => (int)($estoque['produtos_baixo'] ?? 0),
                    'produtos_com_previsao' => (int)($prev['produtos_com_previsao'] ?? 0),
                    'total_ocs_abertas' => (int)($prev['total_ocs_abertas'] ?? 0),
                    'detalhes' => [
                        'estoque_matriz' => (float)($estoque['estoque_matriz'] ?? 0),
                        'estoque_filial' => (float)($estoque['estoque_filial'] ?? 0),
                        'reservado_matriz' => (float)($carteira['reservado_matriz'] ?? 0),
                        'reservado_filial' => (float)($carteira['reservado_filial'] ?? 0),
                        'previsao_matriz' => (float)($prev['previsao_matriz'] ?? 0),
                        'previsao_filial' => (float)($prev['previsao_filial'] ?? 0),
                        'estoque_matriz_kg' => (float)($estoque['estoque_matriz_kg'] ?? 0),
                        'estoque_filial_kg' => (float)($estoque['estoque_filial_kg'] ?? 0),
                        'reservado_matriz_kg' => (float)($carteira['reservado_matriz_kg'] ?? 0),
                        'reservado_filial_kg' => (float)($carteira['reservado_filial_kg'] ?? 0),
                        'previsao_matriz_kg' => (float)($prev['previsao_matriz_kg'] ?? 0),
                        'previsao_filial_kg' => (float)($prev['previsao_filial_kg'] ?? 0),
                    ]
                ],
                'config' => [
                    'usuario' => $permissions['username'],
                    'filiais' => $listaFiliais,
                    'filial_padrao' => $filtroId,
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/estoque-previsao/produtos
     */
    public function getProdutos(Request $request, Response $response): Response
    {
        try {
            $input = (array)($request->getParsedBody() ?? json_decode($request->getBody()->getContents(), true) ?? []);
            $filtroId = (int)($input['filtro_id'] ?? 0);
            $page = max(1, (int)($input['page'] ?? 1));
            $limit = min(200, (int)($input['limit'] ?? 20));
            $offset = ($page - 1) * $limit;
            $search = trim((string)($input['search'] ?? ''));
            $marca = trim((string)($input['marca'] ?? ''));
            $status = trim((string)($input['status'] ?? ''));
            $orderBy = trim((string)($input['order_by'] ?? 'produto'));
            $orderDir = strtoupper((string)($input['order_dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

            $orderMap = [
                'produto' => 'I.DESCRICAO',
                'referencia' => 'I.REFERENCIA',
                'marca' => 'M.DESCRICAO',
                'estoque' => 'estoque_total',
                'reservado' => 'qtd_reservada',
                'liquido' => 'estoque_livre',
                'previsao' => 'qtd_previsao',
                'futuro' => 'estoque_futuro',
            ];
            $orderColumn = $orderMap[$orderBy] ?? 'I.DESCRICAO';

            $where = ["I.INATIVO = 'N'", "I.TIPO = '0'"];
            $params = [];

            if ($search !== '') {
                $where[] = "(I.DESCRICAO ILIKE :search OR I.REFERENCIA ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }

            if ($marca !== '') {
                $where[] = "M.DESCRICAO = :marca";
                $params[':marca'] = $marca;
            }

            $whereSql = implode(' AND ', $where);

            // Filtro por filial para carteira:
            $filialCarteiraSql = ($filtroId === 1 || $filtroId === 6)
                ? "AND p.IDFILIAL = {$filtroId}"
                : "AND p.IDFILIAL IN (1, 6)";

            $sql = "
                SELECT 
                    I.IDITEM as \"ID Item\",
                    I.IDITEM as id_item,
                    I.REFERENCIA as \"Referência\",
                    I.REFERENCIA as referencia,
                    I.DESCRICAO as \"Produto\",
                    I.DESCRICAO as produto,
                    I.PATH_FOTO_MASTER as \"Foto\",
                    I.PATH_FOTO_MASTER as foto,
                    COALESCE(I.PESOLIQUIDO, 0) as peso_liquido,
                    COALESCE(I.PESOBRUTO, 0) as peso_bruto,
                    COALESCE(M.DESCRICAO, '') AS \"Marca\",
                    COALESCE(M.DESCRICAO, '') AS marca,
                    COALESCE(ef1.SALDOATUAL, 0) AS estoque_matriz,
                    COALESCE(ef6.SALDOATUAL, 0) AS estoque_filial,
                    " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " AS estoque_total,
                    " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " AS \"Estoque Disponível\",
                    COALESCE(res.qtd_reservada, 0) AS \"Quantidade em Carteira\",
                    COALESCE(res.qtd_reservada, 0) AS qtd_reservada,
                    GREATEST(0, " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " - COALESCE(res.qtd_reservada, 0)) AS \"Estoque Líquido (s/ Previsão)\",
                    GREATEST(0, " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " - COALESCE(res.qtd_reservada, 0)) AS estoque_livre,
                    COALESCE(oc_prev.qtd_previsao, 0) AS \"Previsão de Compra Total\",
                    COALESCE(oc_prev.qtd_previsao, 0) AS qtd_previsao,
                    COALESCE(oc_prev.qtd_prev_matriz, 0) AS qtd_prev_matriz,
                    COALESCE(oc_prev.qtd_prev_filial, 0) AS qtd_prev_filial,
                    (GREATEST(0, " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " - COALESCE(res.qtd_reservada, 0)) + COALESCE(oc_prev.qtd_previsao, 0)) AS \"Estoque Líquido (c/ Previsão)\",
                    (GREATEST(0, " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " - COALESCE(res.qtd_reservada, 0)) + COALESCE(oc_prev.qtd_previsao, 0)) AS estoque_futuro,
                    ROUND((" . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " * COALESCE(I.PESOLIQUIDO, 0))::numeric, 2) AS peso_estoque_total,
                    ROUND((COALESCE(res.qtd_reservada, 0) * COALESCE(I.PESOLIQUIDO, 0))::numeric, 2) AS peso_reservado,
                    ROUND((GREATEST(0, " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " - COALESCE(res.qtd_reservada, 0)) * COALESCE(I.PESOLIQUIDO, 0))::numeric, 2) AS peso_livre,
                    ROUND((COALESCE(oc_prev.qtd_previsao, 0) * COALESCE(I.PESOLIQUIDO, 0))::numeric, 2) AS peso_previsao,
                    ROUND(((GREATEST(0, " . ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))")) . " - COALESCE(res.qtd_reservada, 0)) + COALESCE(oc_prev.qtd_previsao, 0)) * COALESCE(I.PESOLIQUIDO, 0))::numeric, 2) AS peso_futuro
                FROM ITEM I
                LEFT JOIN MARCA M ON M.IDMARCA = I.IDMARCA
                LEFT JOIN ESTOQUE_FILIAL ef1 ON ef1.IDITEM = I.IDITEM AND ef1.IDFILIAL = 1
                LEFT JOIN ESTOQUE_FILIAL ef6 ON ef6.IDITEM = I.IDITEM AND ef6.IDFILIAL = 6
                LEFT JOIN (
                    SELECT pi.IDITEM, SUM(COALESCE(pi.QTSALDO, pi.QT)) as qtd_reservada
                    FROM PEDIDO_ITEM pi
                    JOIN PEDIDO p ON p.IDPEDIDO = pi.IDPEDIDO
                    WHERE p.STATUS IN (1, 4) {$filialCarteiraSql}
                    GROUP BY pi.IDITEM
                ) res ON res.IDITEM = I.IDITEM
                LEFT JOIN (
                    SELECT 
                        oci.IDITEM,
                        SUM(oci.QTSALDO) as qtd_previsao,
                        SUM(CASE WHEN oc.IDFILIAL = 1 THEN oci.QTSALDO ELSE 0 END) as qtd_prev_matriz,
                        SUM(CASE WHEN oc.IDFILIAL = 6 THEN oci.QTSALDO ELSE 0 END) as qtd_prev_filial
                    FROM OC_ITEM oci
                    JOIN OC ON oc.IDOC = oci.IDOC
                    JOIN OC_TRANSACAO oct ON oct.IDTRANSACAO = oc.IDTRANSACAO
                    WHERE oc.STATUS IN ('1', '4') 
                      AND oc.SITUACAO IN (2, 6)
                      AND oct.TIPO = 0
                      AND oci.QTSALDO > 0
                      AND oc.IDFILIAL IN (1, 6)
                    GROUP BY oci.IDITEM
                ) oc_prev ON oc_prev.IDITEM = I.IDITEM
                WHERE {$whereSql}
            ";

            // Filtro por status de estoque
            $saldoFisicoSql = ($filtroId === 1 ? "COALESCE(ef1.SALDOATUAL, 0)" : ($filtroId === 6 ? "COALESCE(ef6.SALDOATUAL, 0)" : "(COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0))"));
            if ($status === 'zerado') {
                $sql .= " AND ({$saldoFisicoSql}) <= 0";
            } elseif ($status === 'critico') {
                $sql .= " AND ({$saldoFisicoSql}) BETWEEN 1 AND 10";
            } elseif ($status === 'critico_zerado') {
                $sql .= " AND ({$saldoFisicoSql}) <= 10";
            } elseif ($status === 'baixo') {
                $sql .= " AND ({$saldoFisicoSql}) BETWEEN 11 AND 50";
            } elseif ($status === 'previsao') {
                $sql .= " AND COALESCE(oc_prev.qtd_previsao, 0) > 0";
            } elseif ($status === 'reservado') {
                $sql .= " AND COALESCE(res.qtd_reservada, 0) > 0";
            } elseif ($status === 'fisico') {
                $sql .= " AND ({$saldoFisicoSql}) > 0";
            } elseif ($status === 'ok') {
                $sql .= " AND ({$saldoFisicoSql}) > 50";
            }

            // Contagem total para paginação
            $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS subcount";
            $stmtCount = $this->pdo->prepare($countSql);
            foreach ($params as $k => $v) {
                $stmtCount->bindValue($k, $v);
            }
            $stmtCount->execute();
            $total = (int)$stmtCount->fetchColumn();

            // Ordenação e paginação
            $sql .= " ORDER BY {$orderColumn} {$orderDir} OFFSET :offset LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapeia status amigável em cada item
            foreach ($produtos as &$p) {
                $est = (float)($p['estoque_livre'] ?? $p['Estoque Disponível'] ?? 0);
                $prevQtd = (float)($p['qtd_previsao'] ?? 0);
                if ($est <= 0) {
                    $p['status_tag'] = 'zerado';
                    $p['status_label'] = 'Zerado';
                } elseif ($est <= 10) {
                    $p['status_tag'] = 'critico';
                    $p['status_label'] = 'Crítico';
                } elseif ($est <= 50) {
                    $p['status_tag'] = 'baixo';
                    $p['status_label'] = 'Baixo';
                } else {
                    $p['status_tag'] = 'ok';
                    $p['status_label'] = 'Adequado';
                }
                $p['tem_previsao'] = $prevQtd > 0;
            }
            unset($p);

            return $this->json($response, [
                'success' => true,
                'data' => $produtos,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / max(1, $limit)),
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/estoque-previsao/buscar-item?termo=...
     */
    public function buscarItem(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $termo = trim((string)($params['termo'] ?? ''));

            if (mb_strlen($termo) < 2) {
                return $this->json($response, ['success' => true, 'data' => []]);
            }

            $sql = "
                SELECT 
                    I.IDITEM as id,
                    I.DESCRICAO as nome,
                    I.REFERENCIA as referencia,
                    I.PATH_FOTO_MASTER as foto,
                    COALESCE(ef1.SALDOATUAL, 0) as saldo_matriz,
                    COALESCE(ef6.SALDOATUAL, 0) as saldo_filial,
                    (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) as saldo
                FROM ITEM I
                LEFT JOIN ESTOQUE_FILIAL ef1 ON ef1.IDITEM = I.IDITEM AND ef1.IDFILIAL = 1
                LEFT JOIN ESTOQUE_FILIAL ef6 ON ef6.IDITEM = I.IDITEM AND ef6.IDFILIAL = 6
                WHERE I.INATIVO = 'N' 
                    AND I.TIPO = '0'
                    AND (I.DESCRICAO ILIKE :termo OR I.REFERENCIA ILIKE :termo)
                ORDER BY I.DESCRICAO
                LIMIT 25
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':termo' => "%{$termo}%"]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, ['success' => true, 'data' => $itens]);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/estoque-previsao/item/{id}
     */
    public function getItemDetalhe(Request $request, Response $response, array $args): Response
    {
        try {
            $iditem = (int)($args['id'] ?? 0);
            if ($iditem <= 0) {
                return $this->json($response, ['success' => false, 'error' => 'ID inválido'], 400);
            }

            // 1. DADOS CADASTRAIS E SALDOS NAS FILIAIS
            $sqlItem = "
                SELECT 
                    I.IDITEM as id,
                    I.REFERENCIA as referencia,
                    I.DESCRICAO as nome,
                    I.PATH_FOTO_MASTER as foto,
                    COALESCE(I.PESOLIQUIDO, 0) as peso_liquido,
                    COALESCE(I.PESOBRUTO, 0) as peso_bruto,
                    COALESCE(M.DESCRICAO, '') as marca,
                    COALESCE(ef1.SALDOATUAL, 0) as estoque_matriz,
                    COALESCE(ef6.SALDOATUAL, 0) as estoque_filial,
                    (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) as estoque_total,
                    COALESCE(res1.qtd, 0) as reservado_matriz,
                    COALESCE(res6.qtd, 0) as reservado_filial,
                    (COALESCE(res1.qtd, 0) + COALESCE(res6.qtd, 0)) as reservado_total,
                    GREATEST(0, COALESCE(ef1.SALDOATUAL, 0) - COALESCE(res1.qtd, 0)) as livre_matriz,
                    GREATEST(0, COALESCE(ef6.SALDOATUAL, 0) - COALESCE(res6.qtd, 0)) as livre_filial,
                    GREATEST(0, (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) - (COALESCE(res1.qtd, 0) + COALESCE(res6.qtd, 0))) as livre_total
                FROM ITEM I
                LEFT JOIN MARCA M ON M.IDMARCA = I.IDMARCA
                LEFT JOIN ESTOQUE_FILIAL ef1 ON ef1.IDITEM = I.IDITEM AND ef1.IDFILIAL = 1
                LEFT JOIN ESTOQUE_FILIAL ef6 ON ef6.IDITEM = I.IDITEM AND ef6.IDFILIAL = 6
                LEFT JOIN (
                    SELECT pi.IDITEM, SUM(COALESCE(pi.QTSALDO, pi.QT)) as qtd
                    FROM PEDIDO_ITEM pi
                    JOIN PEDIDO p ON p.IDPEDIDO = pi.IDPEDIDO
                    WHERE p.STATUS IN (1, 4) AND p.IDFILIAL = 1
                    GROUP BY pi.IDITEM
                ) res1 ON res1.IDITEM = I.IDITEM
                LEFT JOIN (
                    SELECT pi.IDITEM, SUM(COALESCE(pi.QTSALDO, pi.QT)) as qtd
                    FROM PEDIDO_ITEM pi
                    JOIN PEDIDO p ON p.IDPEDIDO = pi.IDPEDIDO
                    WHERE p.STATUS IN (1, 4) AND p.IDFILIAL = 6
                    GROUP BY pi.IDITEM
                ) res6 ON res6.IDITEM = I.IDITEM
                WHERE I.IDITEM = :iditem
            ";

            $stmt = $this->pdo->prepare($sqlItem);
            $stmt->execute([':iditem' => $iditem]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return $this->json($response, ['success' => false, 'error' => 'Item não encontrado'], 404);
            }

            // 2. ENDEREÇOS NO DEPÓSITO
            try {
                $sqlEnderecos = "
                    SELECT STRING_AGG(DISTINCT E.DESCRICAO, ', ') as enderecos
                    FROM LOTE L
                    JOIN LOTE_ENDERECO LE ON LE.LOTE = L.LOTE 
                    JOIN ENDERECO E ON E.IDENDERECO = LE.IDENDERECO
                    WHERE L.IDITEM = :iditem
                ";
                $stmtEnd = $this->pdo->prepare($sqlEnderecos);
                $stmtEnd->execute([':iditem' => $iditem]);
                $item['enderecos'] = $stmtEnd->fetchColumn() ?: 'Nenhum endereço vinculado';
            } catch (\Throwable $e) {
                $item['enderecos'] = 'Nenhum endereço vinculado';
            }

            // 3. PREVISÃO DE COMPRAS (OCs) - UNIFICADO MATRIZ (1) + FILIAL (6)
            $sqlPrevisao = "
                SELECT 
                    OC.IDOC as numero_oc,
                    OC.IDOC as \"Número OC\",
                    OC.IDFILIAL as idfilial,
                    CASE WHEN OC.IDFILIAL = 1 THEN 'Matriz 1 (SC)' ELSE 'Filial 6 (PR)' END as filial_nome,
                    COALESCE(CLIFOREMP.FANTASIA, CLIFOREMP.RAZAO) as fornecedor,
                    COALESCE(CLIFOREMP.FANTASIA, CLIFOREMP.RAZAO) as \"Fornecedor\",
                    OC_ITEM.QT as quantidade,
                    OC_ITEM.QT as \"Quantidade\",
                    OC_ITEM.QTSALDO as quantidade_saldo,
                    OC_ITEM.QTSALDO as \"Quantidade Saldo\",
                    OC.DATAPREVISAO as data_prevista,
                    OC.DATAPREVISAO as \"Data Prevista\",
                    (OC.DATAPREVISAO - CURRENT_DATE) as dias_para_chegada,
                    (OC.DATAPREVISAO - CURRENT_DATE) as \"Dias para Chegada\"
                FROM OC
                JOIN CLIFOREMP ON CLIFOREMP.IDCLIFOREMP = OC.IDCLIFOREMP
                JOIN OC_ITEM ON OC_ITEM.IDOC = OC.IDOC
                JOIN OC_TRANSACAO ON OC_TRANSACAO.IDTRANSACAO = OC.IDTRANSACAO
                WHERE OC.STATUS IN ('1', '4')
                    AND OC.SITUACAO IN (2, 6)
                    AND OC_TRANSACAO.TIPO = 0
                    AND OC_ITEM.IDITEM = :iditem
                    AND OC_ITEM.QTSALDO > 0
                    AND OC.IDFILIAL IN (1, 6)
                ORDER BY OC.DATAPREVISAO ASC, OC.IDOC ASC
            ";
            $stmtPrevisao = $this->pdo->prepare($sqlPrevisao);
            $stmtPrevisao->execute([':iditem' => $iditem]);
            $previsoes = $stmtPrevisao->fetchAll(PDO::FETCH_ASSOC);

            // Soma total da previsão
            $totalPrevisaoItem = 0.0;
            $prevMatrizItem = 0.0;
            $prevFilialItem = 0.0;
            foreach ($previsoes as $p) {
                $q = (float)$p['quantidade_saldo'];
                $totalPrevisaoItem += $q;
                if ((int)$p['idfilial'] === 1) {
                    $prevMatrizItem += $q;
                } else {
                    $prevFilialItem += $q;
                }
            }

            $item['previsao_matriz'] = $prevMatrizItem;
            $item['previsao_filial'] = $prevFilialItem;
            $item['previsao_total'] = $totalPrevisaoItem;
            $item['estoque_futuro'] = (float)$item['livre_total'] + $totalPrevisaoItem;
            $item['saldo'] = (float)$item['estoque_total'];

            // 4. HISTÓRICO DE MOVIMENTAÇÃO (ÚLTIMOS 30 DIAS)
            $sqlHistorico = "
                SELECT 
                    DATE(data) as data,
                    SUM(CASE WHEN entradasaida = 'E' THEN quantidade ELSE 0 END) as entradas,
                    SUM(CASE WHEN entradasaida = 'S' THEN quantidade ELSE 0 END) as saidas
                FROM MOVTOESTOQUE
                WHERE iditem = :iditem
                    AND data >= CURRENT_DATE - INTERVAL '30 days'
                GROUP BY DATE(data)
                ORDER BY data ASC
                LIMIT 30
            ";
            $stmtHistorico = $this->pdo->prepare($sqlHistorico);
            $stmtHistorico->execute([':iditem' => $iditem]);
            $historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'item' => $item,
                    'previsoes' => $previsoes,
                    'historico' => $historico,
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/estoque-previsao/exportar
     */
    public function exportar(Request $request, Response $response): Response
    {
        try {
            $input = (array)($request->getParsedBody() ?? json_decode($request->getBody()->getContents(), true) ?? []);
            $filtroId = (int)($input['filtro_id'] ?? 0);
            $search = trim((string)($input['search'] ?? ''));
            $marca = trim((string)($input['marca'] ?? ''));
            $status = trim((string)($input['status'] ?? ''));

            $where = ["I.INATIVO = 'N'", "I.TIPO = '0'"];
            $params = [];

            if ($search !== '') {
                $where[] = "(I.DESCRICAO ILIKE :search OR I.REFERENCIA ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }

            if ($marca !== '') {
                $where[] = "M.DESCRICAO = :marca";
                $params[':marca'] = $marca;
            }

            $whereSql = implode(' AND ', $where);

            $filialCarteiraSql = ($filtroId === 1 || $filtroId === 6)
                ? "AND p.IDFILIAL = {$filtroId}"
                : "AND p.IDFILIAL IN (1, 6)";

            $sql = "
                SELECT 
                    I.REFERENCIA as \"Referência\",
                    I.DESCRICAO as \"Produto\",
                    COALESCE(M.DESCRICAO, '') AS \"Marca\",
                    COALESCE(ef1.SALDOATUAL, 0) AS \"Estoque Matriz 1\",
                    COALESCE(ef6.SALDOATUAL, 0) AS \"Estoque Filial 6 PR\",
                    (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) AS \"Estoque Total Físico\",
                    COALESCE(res.qtd_reservada, 0) AS \"Carteira Reservada\",
                    GREATEST(0, (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) - COALESCE(res.qtd_reservada, 0)) AS \"Estoque Líquido Disponível\",
                    COALESCE(oc_prev.qtd_previsao, 0) AS \"Previsão de Chegada Total (1+6)\",
                    COALESCE(oc_prev.qtd_prev_matriz, 0) AS \"Previsão Matriz 1\",
                    COALESCE(oc_prev.qtd_prev_filial, 0) AS \"Previsão Filial 6 PR\",
                    (GREATEST(0, (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) - COALESCE(res.qtd_reservada, 0)) + COALESCE(oc_prev.qtd_previsao, 0)) AS \"Estoque Futuro Projetado\"
                FROM ITEM I
                LEFT JOIN MARCA M ON M.IDMARCA = I.IDMARCA
                LEFT JOIN ESTOQUE_FILIAL ef1 ON ef1.IDITEM = I.IDITEM AND ef1.IDFILIAL = 1
                LEFT JOIN ESTOQUE_FILIAL ef6 ON ef6.IDITEM = I.IDITEM AND ef6.IDFILIAL = 6
                LEFT JOIN (
                    SELECT pi.IDITEM, SUM(COALESCE(pi.QTSALDO, pi.QT)) as qtd_reservada
                    FROM PEDIDO_ITEM pi
                    JOIN PEDIDO p ON p.IDPEDIDO = pi.IDPEDIDO
                    WHERE p.STATUS IN (1, 4) {$filialCarteiraSql}
                    GROUP BY pi.IDITEM
                ) res ON res.IDITEM = I.IDITEM
                LEFT JOIN (
                    SELECT 
                        oci.IDITEM,
                        SUM(oci.QTSALDO) as qtd_previsao,
                        SUM(CASE WHEN oc.IDFILIAL = 1 THEN oci.QTSALDO ELSE 0 END) as qtd_prev_matriz,
                        SUM(CASE WHEN oc.IDFILIAL = 6 THEN oci.QTSALDO ELSE 0 END) as qtd_prev_filial
                    FROM OC_ITEM oci
                    JOIN OC ON oc.IDOC = oci.IDOC
                    JOIN OC_TRANSACAO oct ON oct.IDTRANSACAO = oc.IDTRANSACAO
                    WHERE oc.STATUS IN ('1', '4') 
                      AND oc.SITUACAO IN (2, 6)
                      AND oct.TIPO = 0
                      AND oci.QTSALDO > 0
                      AND oc.IDFILIAL IN (1, 6)
                    GROUP BY oci.IDITEM
                ) oc_prev ON oc_prev.IDITEM = I.IDITEM
                WHERE {$whereSql}
            ";

            if ($status === 'zerado') {
                $sql .= " AND (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) <= 0";
            } elseif ($status === 'critico') {
                $sql .= " AND (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) BETWEEN 1 AND 10";
            } elseif ($status === 'baixo') {
                $sql .= " AND (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) BETWEEN 11 AND 50";
            } elseif ($status === 'previsao') {
                $sql .= " AND COALESCE(oc_prev.qtd_previsao, 0) > 0";
            } elseif ($status === 'ok') {
                $sql .= " AND (COALESCE(ef1.SALDOATUAL, 0) + COALESCE(ef6.SALDOATUAL, 0)) > 50";
            }

            $sql .= " ORDER BY I.DESCRICAO ASC LIMIT 2000";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $output = fopen('php://temp', 'r+');
            // BOM UTF-8 para Excel abrir perfeitamente acentuação
            fwrite($output, "\xEF\xBB\xBF");
            if (!empty($dados)) {
                fputcsv($output, array_keys($dados[0]), ';');
                foreach ($dados as $linha) {
                    $formatada = array_map(function($val) {
                        if (is_numeric($val) && strpos((string)$val, '.') !== false) {
                            return number_format((float)$val, 2, ',', '.');
                        }
                        return $val;
                    }, $linha);
                    fputcsv($output, $formatada, ';');
                }
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

            $response->getBody()->write($csvContent);
            return $response
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="estoque_previsao_' . date('Y-m-d') . '.csv"');
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
