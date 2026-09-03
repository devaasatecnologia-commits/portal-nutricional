<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class InventarioController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
/**
 * GET /v1/inventario/filiais
 */
public function getFiliais(Request $request, Response $response): Response
{
    try {
        // Buscar apenas filiais ativas com idfilial 1 e 6 (como no padrão do sistema)
        $sql = "SELECT idfilial, nome, uf, idcidade 
                FROM filial 
                WHERE inativo = 'N' 
                AND idfilial IN (1, 6)
                ORDER BY idfilial";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, $data);
        
    } catch (\Exception $e) {
        error_log("Erro em getFiliais: " . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
/**
 * GET /v1/inventario/marcas
 */
public function getMarcas(Request $request, Response $response): Response
{
    try {
        // Buscar apenas marcas que têm itens ativos com estoque
        $sql = "SELECT DISTINCT m.idmarca, m.descricao 
                FROM marca m
                INNER JOIN item i ON i.idmarca = m.idmarca
                INNER JOIN lote l ON l.iditem = i.iditem
                WHERE m.inativo = 'N' 
                  AND i.inativo = 'N'
                ORDER BY m.descricao";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, $data);
        
    } catch (\Exception $e) {
        error_log("Erro em getMarcas: " . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
    
   /**
 * GET /v1/inventario/grupos
 */
public function getGrupos(Request $request, Response $response): Response
{
    try {
        // Buscar apenas grupos que têm itens ativos com estoque
        $sql = "SELECT DISTINCT g.idgrupo, g.descricao 
                FROM grupo g
                INNER JOIN item i ON i.idgrupo = g.idgrupo
                INNER JOIN lote l ON l.iditem = i.iditem
                WHERE g.inativo = 'N' 
                  AND i.inativo = 'N'
                ORDER BY g.descricao";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, $data);
        
    } catch (\Exception $e) {
        error_log("Erro em getGrupos: " . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
   /**
 * GET /v1/inventario/buscar-itens
 */
public function buscarItens(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $termo = $params['termo'] ?? '';
    
    if (empty($termo)) {
        return $this->jsonResponse($response, ['data' => []]);
    }
    
    try {
        $sql = "SELECT DISTINCT 
                    i.iditem, 
                    i.referencia, 
                    i.descricao,
                    cb.idbarra as ean
                FROM item i
                LEFT JOIN codigo_barra cb ON cb.iditem = i.iditem AND cb.principal = 'S'
                WHERE i.inativo = 'N'
                  AND (i.referencia ILIKE :termo 
                       OR i.descricao ILIKE :termo2
                       OR EXISTS (SELECT 1 FROM codigo_barra cb2 WHERE cb2.iditem = i.iditem AND cb2.idbarra = :termo3))
                ORDER BY i.descricao
                LIMIT 50";
        
        $stmt = $this->pdo->prepare($sql);
        $termoLike = '%' . $termo . '%';
        $stmt->execute([
            'termo' => $termoLike,
            'termo2' => $termoLike,
            'termo3' => $termo
        ]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, ['data' => $data]);
        
    } catch (\Exception $e) {
        error_log("Erro em buscarItens: " . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
    
/**
 * POST /v1/inventario/consultar
 * Consulta principal de inventário com filtros
 */
public function consultarInventario(Request $request, Response $response): Response
{
    $params = json_decode($request->getBody()->getContents(), true) ?? [];
    
    $filtroFiliais = $params['filiais'] ?? [];
    $filtroMarcas = $params['marcas'] ?? [];
    $filtroGrupos = $params['grupos'] ?? [];
    $filtroItens = $params['itens'] ?? [];
    
    try {
        // Construção dinâmica dos filtros
        $whereConditions = [];
        $paramsBind = [];
        $paramCounter = 1;
        
        if (!empty($filtroFiliais)) {
            $placeholders = [];
            foreach ($filtroFiliais as $f) {
                $key = "filial_{$paramCounter}";
                $placeholders[] = ":{$key}";
                $paramsBind[$key] = (int)$f;
                $paramCounter++;
            }
            $whereConditions[] = "l.idfilial IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($filtroMarcas)) {
            $placeholders = [];
            foreach ($filtroMarcas as $m) {
                $key = "marca_{$paramCounter}";
                $placeholders[] = ":{$key}";
                $paramsBind[$key] = (int)$m;
                $paramCounter++;
            }
            $whereConditions[] = "m.idmarca IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($filtroGrupos)) {
            $placeholders = [];
            foreach ($filtroGrupos as $g) {
                $key = "grupo_{$paramCounter}";
                $placeholders[] = ":{$key}";
                $paramsBind[$key] = (int)$g;
                $paramCounter++;
            }
            $whereConditions[] = "g.idgrupo IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($filtroItens)) {
            $placeholders = [];
            foreach ($filtroItens as $item) {
                $key = "item_{$paramCounter}";
                $placeholders[] = ":{$key}";
                $paramsBind[$key] = (int)$item;
                $paramCounter++;
            }
            $whereConditions[] = "l.iditem IN (" . implode(',', $placeholders) . ")";
        }
        
        $whereClause = !empty($whereConditions) ? "AND " . implode(" AND ", $whereConditions) : "AND 1=1";
        
        // QUERY CORRIGIDA COM A ESTRUTURA REAL DO BANCO
        $sql = "
            WITH devolucoes AS (
                SELECT 
                    lote,
                    iditem,
                    COUNT(*) AS count_devolucoes,
                    COALESCE(SUM(quantidade), 0) AS qtDevolvida
                FROM 
                    lote_extrato
                WHERE 
                    origem = 7
                GROUP BY 
                    lote, iditem
            ),
            lotes AS (
                SELECT 
                    lote,
                    iditem,
                    validade,
                    data,
                    quantidade,
                    idfilial
                FROM 
                    lote
            )
            SELECT DISTINCT
                CASE 
                    WHEN d.count_devolucoes > 1 THEN 'D' 
                    ELSE 'N' 
                END AS status_devolucao_lote,
                CASE 
                    WHEN d.count_devolucoes = 0 THEN 'SEM DEVOLUÇÃO'
                    ELSE CAST(d.count_devolucoes AS text)
                END AS devolucao_lote,
                COALESCE(d.qtDevolvida, 0) AS qt_devolvida,
                l.lote,
                TO_CHAR(l.data, 'DD/MM/YYYY') AS data_entrada,
                TO_CHAR(l.validade, 'DD/MM/YYYY') AS validade,    
                CASE 
                    WHEN l.validade BETWEEN l.data AND (l.data + INTERVAL '1 year') THEN 'Ruim'
                    WHEN l.validade > (l.data + INTERVAL '1 year') AND l.validade <= (l.data + INTERVAL '1 year 6 months') THEN 'Regular'
                    WHEN l.validade > (l.data + INTERVAL '1 year 6 months') THEN 'Ótimo'
                    ELSE 'Sem classificação'
                END AS status_validade,
                CAST(
                    COALESCE((
                        SELECT SUM(CASE le.entradasaida 
                            WHEN 1 THEN (le.quantidade * -1) 
                            ELSE le.quantidade 
                        END)
                        FROM lote_extrato le
                        WHERE le.iditem = l.iditem 
                            AND le.lote = l.lote 
                            AND le.validade = l.validade
                    ), 0) AS NUMERIC(10, 0)
                ) AS quant_lote,
                l.iditem,
                cb.idbarra,
                i.referencia,
                i.descricao,
                u.sigla AS unidade,
                g.descricao AS grupo,
                m.descricao AS marca,
                f.nome AS filial,
                f.uf,
                c.descricao AS cidade,
                COALESCE((
                    SELECT SUM(CASE xe.entradasaida 
                        WHEN 1 THEN (xe.quantidade * -1) 
                        ELSE xe.quantidade 
                    END)
                    FROM lote_extrato xe
                    WHERE xe.iditem = l.iditem AND xe.quantidade > 0
                ), 0) AS saldo_total
            FROM
                lotes l
            LEFT JOIN
                devolucoes d ON d.lote = l.lote AND d.iditem = l.iditem
            JOIN
                item i ON i.iditem = l.iditem AND i.inativo = 'N'
            JOIN
                grupo g ON g.idgrupo = i.idgrupo
            JOIN
                marca m ON m.idmarca = i.idmarca
            JOIN
                unidade u ON u.idunidade = i.idunidadebasica  
            LEFT JOIN
                codigo_barra cb ON cb.iditem = i.iditem AND cb.principal = 'S'
            JOIN
                filial f ON f.idfilial = l.idfilial
            LEFT JOIN
                cidade c ON c.idcidade = f.idcidade
            WHERE 
                i.inativo = 'N'
                {$whereClause}
            GROUP BY 
                l.data,
                l.lote,
                l.iditem,
                l.validade, 
                cb.idbarra,
                i.referencia,
                i.descricao,
                u.sigla,
                g.descricao,
                m.descricao,
                f.nome,
                f.uf,
                c.descricao,
                d.count_devolucoes,
                d.qtDevolvida
            ORDER BY 
                i.descricao ASC, l.lote ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($paramsBind as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, [
            'data' => $data,
            'total_registros' => count($data)
        ]);
        
    } catch (\Exception $e) {
        error_log("Erro no inventário: " . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
    
/**
 * GET /v1/inventario/detalhes-lote/{iditem}/{lote}
 */
public function getDetalhesLote(Request $request, Response $response, array $args): Response
{
    $iditem = (int)($args['iditem'] ?? 0);
    $lote = $args['lote'] ?? '';
    
    if (!$iditem || empty($lote)) {
        return $this->jsonResponse($response, ['error' => 'Parâmetros inválidos'], 400);
    }
    
    try {
        $sql = "
            SELECT 
                le.idmovto as id_movimento,
                le.datahora as data_movimento,
                TO_CHAR(le.datahora, 'DD/MM/YYYY HH24:MI:SS') as data_movimento_formatada,
                le.quantidade,
                le.entradasaida,
                CASE 
                    WHEN le.entradasaida = 1 THEN 'SAÍDA'
                    ELSE 'ENTRADA'
                END AS tipo_movimento,
                le.origem,
                CASE 
                    WHEN le.origem = 1 THEN 'COMPRA'
                    WHEN le.origem = 2 THEN 'VENDA'
                    WHEN le.origem = 3 THEN 'TRANSFERÊNCIA'
                    WHEN le.origem = 4 THEN 'AJUSTE'
                    WHEN le.origem = 5 THEN 'PRODUÇÃO'
                    WHEN le.origem = 6 THEN 'INVENTÁRIO'
                    WHEN le.origem = 7 THEN 'DEVOLUÇÃO'
                    ELSE 'OUTROS'
                END AS descricao_origem,
                le.documento,
                le.observacao,
                le.usuario
            FROM 
                lote_extrato le
            WHERE 
                le.iditem = :iditem 
                AND le.lote = :lote
            ORDER BY 
                le.datahora DESC
            LIMIT 100
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['iditem' => $iditem, 'lote' => $lote]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, ['data' => $data]);
        
    } catch (\Exception $e) {
        error_log("Erro detalhes lote: " . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}

public function exportarExcel(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    
    // Por enquanto, retorna um CSV simples
    $csv = "iditem;referencia;descricao;lote;validade;quantidade;unidade;filial\n";
    $csv .= ";;Nenhum dado disponível;;;0;;\n";
    
    $response->getBody()->write($csv);
    return $response
        ->withHeader('Content-Type', 'text/csv; charset=utf-8')
        ->withHeader('Content-Disposition', 'attachment; filename="inventario_' . date('Ymd_His') . '.csv"');
}
    
   private function jsonResponse($response, array $data, int $status = 200): Response
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}
}