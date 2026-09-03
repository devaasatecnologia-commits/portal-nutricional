<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class EstoquePrevisaoController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * Obtém as permissões do usuário
     */
    private function getUserPermissions(int $uid): array
    {
        $stmt = $this->pdo->prepare("SELECT dash_filiais, dash_gestores, username FROM usuario WHERE idcliforemp = :uid");
        $stmt->execute(['uid' => $uid]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'username' => $userData['username'] ?? 'Usuário',
            'filiais' => !empty($userData['dash_filiais']) ? explode(',', $userData['dash_filiais']) : [],
            'gestores' => !empty($userData['dash_gestores']) ? explode(',', $userData['dash_gestores']) : []
        ];
    }
    
private function getFilialByUser(int $uid, array $permissions, int $filtroId = 0): int
{
    if (!empty($permissions['filiais'])) {
        $filiaisPermitidas = array_map('intval', $permissions['filiais']);
        
        if ($filtroId > 0 && in_array($filtroId, $filiaisPermitidas)) {
            return $filtroId;
        }
        
        return $filiaisPermitidas[0];
    }
    
    // Fallback seguro
    return 1;
}  
    
    /**
     * GET /v1/estoque-previsao/marcas
     */
    public function getMarcas(Request $request, Response $response): Response
    {
        try {
        $params = $request->getQueryParams();
        $uid = intval($params['idusuario'] ?? 0);
        $idFilial = intval($params['idfilial'] ?? 1);  // Valor padrão 1 se null
        
        // Se idFilial for 0, usa 1
        if ($idFilial <= 0) {
            $idFilial = 1;
        }
        
        $permissions = $this->getUserPermissions($uid);
        
        if (!empty($permissions['filiais']) && !in_array($idFilial, $permissions['filiais'])) {
            $idFilial = $permissions['filiais'][0];
        }
            
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
                    AND EF.IDFILIAL = :idFilial
                    AND M.IDMARCA NOT IN (60)
                ORDER BY M.DESCRICAO
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['idFilial' => $idFilial]);
            $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, ['success' => true, 'data' => $marcas]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /v1/estoque-previsao/resumo
     */
    public function getResumo(Request $request, Response $response): Response
    {
           try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $uid = intval($input['idusuario'] ?? 0);
        $filtroId = intval($input['filtro_id'] ?? 0);
        
        $permissions = $this->getUserPermissions($uid);
        $idFilial = $this->getFilialByUser($uid, $permissions, $filtroId);
        
        // Garante que idFilial é válido
        if ($idFilial <= 0) {
            $idFilial = 1;
        }
            $stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM(ef.SALDOATUAL), 0) as total_estoque,
                    COUNT(DISTINCT ef.IDITEM) as total_produtos,
                    COUNT(DISTINCT CASE WHEN ef.SALDOATUAL <= 0 THEN ef.IDITEM END) as produtos_zerados,
                    COUNT(DISTINCT CASE WHEN ef.SALDOATUAL BETWEEN 1 AND 50 THEN ef.IDITEM END) as produtos_baixo
                FROM ESTOQUE_FILIAL ef
                WHERE ef.IDFILIAL = :idFilial
            ");
            $stmt->execute(['idFilial' => $idFilial]);
            $estoque = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $sqlFiliais = "SELECT DISTINCT idfilial, nome FROM filial WHERE inativo = 'N' ORDER BY idfilial";
            if (!empty($permissions['filiais'])) {
                $inF = implode(',', array_map('intval', $permissions['filiais']));
                $sqlFiliais .= " AND idfilial IN ($inF)";
            }
            $stmtFilial = $this->pdo->query($sqlFiliais);
            $listaFiliais = $stmtFilial->fetchAll(PDO::FETCH_ASSOC);
            
            $resumo = [
                'total_estoque' => (int)($estoque['total_estoque'] ?? 0),
                'total_reservado' => 0,
                'total_previsao' => 0,
                'total_futuro' => (int)($estoque['total_estoque'] ?? 0),
                'total_produtos' => (int)($estoque['total_produtos'] ?? 0),
                'produtos_zerados' => (int)($estoque['produtos_zerados'] ?? 0),
                'produtos_baixo' => (int)($estoque['produtos_baixo'] ?? 0),
                'produtos_com_previsao' => 0
            ];
            
            return $this->json($response, [
                'success' => true,
                'data' => $resumo,
                'config' => [
                    'usuario' => $permissions['username'],
                    'filiais' => $listaFiliais,
                    'filial_padrao' => $idFilial
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /v1/estoque-previsao/produtos
     */
    public function getProdutos(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $uid = intval($input['idusuario'] ?? 0);
            $filtroId = intval($input['filtro_id'] ?? 0);
            $page = max(1, (int)($input['page'] ?? 1));
            $limit = min(100, (int)($input['limit'] ?? 20));
            $offset = ($page - 1) * $limit;
            $search = $input['search'] ?? '';
            $marca = $input['marca'] ?? '';
            $status = $input['status'] ?? '';
            $orderBy = $input['order_by'] ?? 'produto';
            $orderDir = strtoupper($input['order_dir'] ?? 'ASC');
            
            $permissions = $this->getUserPermissions($uid);
            $idFilial = $this->getFilialByUser($uid, $permissions, $filtroId);
            
            $orderMap = [
                'produto' => 'I.DESCRICAO',
                'marca' => 'M.DESCRICAO',
                'estoque' => 'estoque_qtd'
            ];
            $orderColumn = $orderMap[$orderBy] ?? 'I.DESCRICAO';
            $orderDirection = $orderDir === 'DESC' ? 'DESC' : 'ASC';
            
            $where = ["I.INATIVO = 'N'", "I.TIPO = '0'"];
            $params = [':idFilial' => $idFilial];
            
            if ($search) {
                $where[] = "(I.DESCRICAO ILIKE :search OR I.REFERENCIA ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }
            
            if ($marca) {
                $where[] = "M.DESCRICAO = :marca";
                $params[':marca'] = $marca;
            }
            
            $whereSql = implode(' AND ', $where);
            
            $sql = "
                SELECT 
                    I.IDITEM as \"ID Item\",
                    I.REFERENCIA as \"Referência\",
                    I.DESCRICAO as \"Produto\",
                    I.PATH_FOTO_MASTER as \"Foto\",
                    COALESCE(M.DESCRICAO, '') AS \"Marca\",
                    COALESCE((
                        SELECT SUM(EF.SALDOATUAL)
                        FROM ESTOQUE_FILIAL EF
                        WHERE EF.IDITEM = I.IDITEM AND EF.IDFILIAL = :idFilial
                    ), 0) AS \"Estoque Disponível\",
                    0 AS \"Quantidade em Carteira\",
                    COALESCE((
                        SELECT SUM(EF.SALDOATUAL)
                        FROM ESTOQUE_FILIAL EF
                        WHERE EF.IDITEM = I.IDITEM AND EF.IDFILIAL = :idFilial
                    ), 0) AS \"Estoque Líquido (s/ Previsão)\",
                    COALESCE((
                        SELECT SUM(OCI.QTSALDO)
                        FROM OC_ITEM OCI
                        JOIN OC ON OC.IDOC = OCI.IDOC
                        WHERE OCI.IDITEM = I.IDITEM 
                            AND OC.STATUS IN (1,4)
                            AND OC.SITUACAO IN (2,6)
                    ), 0) AS \"Previsão de Compra Total\",
                    COALESCE((
                        SELECT SUM(EF.SALDOATUAL)
                        FROM ESTOQUE_FILIAL EF
                        WHERE EF.IDITEM = I.IDITEM AND EF.IDFILIAL = :idFilial
                    ), 0) AS \"Estoque Líquido (c/ Previsão)\"
                FROM ITEM I
                LEFT JOIN MARCA M ON M.IDMARCA = I.IDMARCA
                WHERE {$whereSql}
                ORDER BY {$orderColumn} {$orderDirection}
                OFFSET :offset LIMIT :limit
            ";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($status && !empty($produtos)) {
                $produtos = array_filter($produtos, function($p) use ($status) {
                    $estoque = $p['Estoque Disponível'] ?? 0;
                    if ($status === 'critico') return $estoque > 0 && $estoque <= 10;
                    if ($status === 'baixo') return $estoque > 10 && $estoque <= 50;
                    if ($status === 'zerado') return $estoque <= 0;
                    if ($status === 'ok') return $estoque > 50;
                    return true;
                });
                $produtos = array_values($produtos);
            }
            
            $countSql = "SELECT COUNT(*) FROM ITEM I WHERE I.INATIVO = 'N' AND I.TIPO = '0'";
            $stmtCount = $this->pdo->prepare($countSql);
            $stmtCount->execute();
            $total = $stmtCount->fetchColumn();
            
            return $this->json($response, [
                'success' => true,
                'data' => $produtos,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * GET /v1/estoque-previsao/buscar-item
     */
    public function buscarItem(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $termo = $params['termo'] ?? '';
            $uid = intval($params['idusuario'] ?? 0);
            $idFilial = intval($params['idfilial'] ?? 1);
            
            if (strlen($termo) < 2) {
                return $this->json($response, ['success' => true, 'data' => []]);
            }
            
            $permissions = $this->getUserPermissions($uid);
            
            if (!empty($permissions['filiais']) && !in_array($idFilial, $permissions['filiais'])) {
                $idFilial = $permissions['filiais'][0];
            }
            
            $sql = "
                SELECT 
                    I.IDITEM as id,
                    I.DESCRICAO as nome,
                    I.REFERENCIA as referencia,
                    I.PATH_FOTO_MASTER as foto,
                    COALESCE((
                        SELECT SUM(EF.SALDOATUAL)
                        FROM ESTOQUE_FILIAL EF
                        WHERE EF.IDITEM = I.IDITEM AND EF.IDFILIAL = :idFilial
                    ), 0) as saldo
                FROM ITEM I
                WHERE I.INATIVO = 'N' 
                    AND I.TIPO = '0'
                    AND (I.DESCRICAO ILIKE :termo OR I.REFERENCIA ILIKE :termo)
                ORDER BY I.DESCRICAO
                LIMIT 20
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':idFilial' => $idFilial,
                ':termo' => "%{$termo}%"
            ]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, ['success' => true, 'data' => $itens]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * GET /v1/estoque-previsao/item/{id}
     */
    public function getItemDetalhe(Request $request, Response $response, array $args): Response
    {
        try {
            $iditem = intval($args['id'] ?? 0);
            $uid = intval($request->getQueryParams()['idusuario'] ?? 0);
            $idFilial = intval($request->getQueryParams()['idfilial'] ?? 1);
            
            if ($iditem <= 0) {
                return $this->json($response, ['success' => false, 'error' => 'ID inválido'], 400);
            }
            
            $permissions = $this->getUserPermissions($uid);
            
            if (!empty($permissions['filiais']) && !in_array($idFilial, $permissions['filiais'])) {
                $idFilial = $permissions['filiais'][0];
            }
            
            // 1. DETALHES DO ITEM
            $sqlItem = "
                SELECT 
                    I.IDITEM as id,
                    I.REFERENCIA as referencia,
                    I.DESCRICAO as nome,
                    I.PATH_FOTO_MASTER as foto,
                    COALESCE(M.DESCRICAO, '') as marca,
                    COALESCE((
                        SELECT SUM(EF.SALDOATUAL)
                        FROM ESTOQUE_FILIAL EF
                        WHERE EF.IDITEM = I.IDITEM AND EF.IDFILIAL = :idFilial1
                    ), 0) as saldo
                FROM ITEM I
                LEFT JOIN MARCA M ON M.IDMARCA = I.IDMARCA
                WHERE I.IDITEM = :iditem
            ";
            
            $stmt = $this->pdo->prepare($sqlItem);
            $stmt->execute([
                ':idFilial1' => $idFilial,
                ':iditem' => $iditem
            ]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                return $this->json($response, ['success' => false, 'error' => 'Item não encontrado'], 404);
            }
            
            // 2. ENDEREÇOS DO ITEM
            try {
                $sqlEnderecos = "
                    SELECT STRING_AGG(DISTINCT E.descricao, ', ') as enderecos
                    FROM LOTE L
                    JOIN LOTE_ENDERECO LE ON LE.LOTE = L.lote 
                    JOIN ENDERECO E ON E.idendereco = LE.IDENDERECO
                    WHERE L.iditem = :iditem
                ";
                $stmtEnd = $this->pdo->prepare($sqlEnderecos);
                $stmtEnd->execute([':iditem' => $iditem]);
                $enderecos = $stmtEnd->fetchColumn();
                $item['enderecos'] = $enderecos ?: 'Nenhum';
            } catch (\Exception $e) {
                $item['enderecos'] = 'Nenhum';
            }
            
            // 3. PREVISÃO DE COMPRAS (OC)
            $sqlPrevisao = "
                SELECT 
                    OC.dataprevisao AS \"Data Prevista\",
                    CLIFOREMP.FANTASIA as \"Fornecedor\",
                    OC_ITEM.QT as \"Quantidade\",
                    OC_ITEM.QTSALDO as \"Quantidade Saldo\",
                    (OC.dataprevisao - CURRENT_DATE) as \"Dias para Chegada\",
                    OC.IDOC as \"Número OC\"
                FROM OC
                JOIN CLIFOREMP ON CLIFOREMP.IDCLIFOREMP = OC.IDCLIFOREMP
                JOIN OC_ITEM ON OC_ITEM.IDOC = OC.IDOC
                JOIN OC_TRANSACAO ON OC_TRANSACAO.IDTRANSACAO = OC.IDTRANSACAO
                WHERE OC.STATUS IN ('1', '4')
                    AND OC.SITUACAO IN (2, 6)
                    AND OC_TRANSACAO.TIPO = 0
                    AND OC_ITEM.IDITEM = :iditem
                    AND OC_ITEM.QTSALDO > 0
                    AND OC.IDFILIAL = :idFilial2
                ORDER BY OC.dataprevisao ASC
            ";
            
            $stmtPrevisao = $this->pdo->prepare($sqlPrevisao);
            $stmtPrevisao->execute([
                ':iditem' => $iditem,
                ':idFilial2' => $idFilial
            ]);
            $previsoes = $stmtPrevisao->fetchAll(PDO::FETCH_ASSOC);
            
            // 4. HISTÓRICO DE MOVIMENTAÇÃO
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
            $historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);
            
            if (!$historico) {
                $historico = [];
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => [
                    'item' => $item,
                    'previsoes' => $previsoes,
                    'historico' => $historico
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Erro em getItemDetalhe: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false, 
                'error' => $e->getMessage()
            ], 500);
        }
    }  // <-- ESTA CHAVE ESTAVA FALTANDO!
    
    /**
     * GET /v1/estoque-previsao/filiais
     */
    public function getFiliais(Request $request, Response $response): Response
    {
        try {
            $uid = (int)($request->getQueryParams()['idusuario'] ?? 0);
            $permissions = $this->getUserPermissions($uid);
            
            $sql = "SELECT DISTINCT idfilial, nome FROM filial WHERE inativo = 'N' ORDER BY idfilial";
            if (!empty($permissions['filiais'])) {
                $inF = implode(',', array_map('intval', $permissions['filiais']));
                $sql .= " AND idfilial IN ($inF)";
            }
            $stmt = $this->pdo->query($sql);
            $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, ['success' => true, 'data' => $filiais]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => true,
                'data' => [['idfilial' => 1, 'nome' => 'MATRIZ - SÃO PAULO']]
            ]);
        }
    }
    
    /**
     * POST /v1/estoque-previsao/exportar
     */
    public function exportar(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $uid = intval($input['idusuario'] ?? 0);
            $filtroId = intval($input['filtro_id'] ?? 0);
            $search = $input['search'] ?? '';
            $marca = $input['marca'] ?? '';
            
            $permissions = $this->getUserPermissions($uid);
            $idFilial = $this->getFilialByUser($uid, $permissions, $filtroId);
            
            $where = ["I.INATIVO = 'N'", "I.TIPO = '0'"];
            $params = [':idFilial' => $idFilial];
            
            if ($search) {
                $where[] = "(I.DESCRICAO ILIKE :search OR I.REFERENCIA ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }
            
            if ($marca) {
                $where[] = "M.DESCRICAO = :marca";
                $params[':marca'] = $marca;
            }
            
            $whereSql = implode(' AND ', $where);
            
            $sql = "
                SELECT 
                    I.REFERENCIA as \"Referência\",
                    I.DESCRICAO as \"Produto\",
                    COALESCE(M.DESCRICAO, '') AS \"Marca\",
                    COALESCE((
                        SELECT SUM(EF.SALDOATUAL)
                        FROM ESTOQUE_FILIAL EF
                        WHERE EF.IDITEM = I.IDITEM AND EF.IDFILIAL = :idFilial
                    ), 0) AS \"Estoque Disponível\"
                FROM ITEM I
                LEFT JOIN MARCA M ON M.IDMARCA = I.IDMARCA
                WHERE {$whereSql}
                ORDER BY I.DESCRICAO
            ";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $output = fopen('php://temp', 'r+');
            fputcsv($output, array_keys($dados[0] ?? []), ';');
            foreach ($dados as $linha) {
                fputcsv($output, $linha, ';');
            }
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);
            
            $response->getBody()->write($csvContent);
            return $response
                ->withHeader('Content-Type', 'text/csv')
                ->withHeader('Content-Disposition', 'attachment; filename="estoque_' . date('Y-m-d') . '.csv"');
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    private function json($response, $data, $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}