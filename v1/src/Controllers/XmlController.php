<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class XmlController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/xml/filiais
     */
    public function getFiliais(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->query("SELECT idempresa, idfilial, razao || ' | ' || idfilial as razao FROM filial WHERE idfilial IN (1,6) ORDER BY idfilial");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/xml/fornecedores/{idfilial}
     */
    public function getFornecedores(Request $request, Response $response, array $args): Response
    {
        $idFilial = (int)$args['idfilial'];
        
        try {
            $sql = "SELECT DISTINCT c.idcliforemp as idfornecedor, c.razao || ' | '|| COALESCE(c.fantasia, '') as razao, c.cnpj 
                    FROM cliforemp c
                    JOIN fornecedor f ON f.idcliforemp = c.idcliforemp
                    WHERE c.tipocliforemp = 1 
                      AND representante = 'N' 
                      AND c.inativo = 'N'
                      AND EXISTS (
                          SELECT 1 FROM oc 
                          WHERE oc.idcliforemp = f.idcliforemp 
                            AND oc.status = 1 
                            AND oc.idfilial = :idfilial
                      )
                    ORDER BY razao";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['idfilial' => $idFilial]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
/**
 * GET /v1/xml/ordens-compra
 * 
 */
public function getOrdensCompra(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $idForn = (int)($params['idfornecedor'] ?? 0);
    $idFilial = (int)($params['idfilial'] ?? 0);
    
    if (!$idForn || !$idFilial) {
        return $this->jsonResponse($response, ['error' => 'Parâmetros obrigatórios'], 400);
    }
    
    try {
        $sql = "SELECT oc.idoc, TO_CHAR(oc.data, 'YYYY-MM-DD') as data_iso, oc.valortotal as valor_num,
           oc.idoc || ' | ' || TO_CHAR(oc.data, 'DD/MM/YYYY') || ' | R$ ' || REPLACE(TO_CHAR(oc.valortotal, 'FM999G999G990D00'), '.', ',') as descricao_select,
                COALESCE(conf.conferido_portal, 'N') as conferido_portal
                FROM oc 
                LEFT JOIN aps_oc_conferencia conf ON conf.idoc = oc.idoc
                WHERE oc.status = 1 
                  AND oc.idcliforemp = :id 
                  AND oc.idfilial = :idf
                 --and oc.idoc not in (SELECT distinct idoc FROM oc_operacao WHERE (descricao LIKE '%Conferência Digital%' OR descricao LIKE '%Conferência em lote%'))
                 AND oc.data BETWEEN current_date - INTERVAL '30 days' AND current_date + INTERVAL '30 days'
                  AND (COALESCE(conf.conferido_portal, 'N') = 'N' OR conf.conferido_portal IS NULL)
                ORDER BY oc.idoc DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idForn, 'idf' => $idFilial]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, $data);
        
    } catch (\Exception $e) {
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
    /**
     * GET /v1/xml/consulta-oc/{idoc}
     */
    public function consultaOC(Request $request, Response $response, array $args): Response
    {
        $idOrdem = (int)$args['idoc'];
        
        try {
            // Verifica se já foi conferida
            $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM oc_operacao WHERE idoc = :id AND acao = 7");
            $stmtCheck->execute(['id' => $idOrdem]);
            $jaConferida = ($stmtCheck->fetchColumn() > 0);
            
            $sql = "SELECT 
                        oc.idoc,
                        f.cnpj, 
                        item.iditem, 
						oc_item.iditemoc,
                        item.fator_conversao, 
                        item.referencia as cprod,
                        (select distinct classificacao from classificacaofiscal where idclassificacaofiscal = item.idclassfiscal) as NCM,
                        item.descricao as xprod,
                        (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = oc_item.iditem AND cb.principal = 'S' LIMIT 1) as ean_unidade,
                        (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = oc_item.iditem AND cb.principal = 'N' AND cb.gerado_auto = 'N' LIMIT 1) as ean_caixa,      
                        oc_item.qt as qCom,
                        oc_item.valor as cuncom,
                        oc_item.valortotal as vprod,
                        case 
                            when item.fator_conversao is not null and item.fator_conversao <> 0 
                            then (SELECT DESCRICAO FROM UNIDADE UN WHERE IDUNIDADE = ITEM.IDUNIDADEREFERENCIA) 
                            else unidade.descricao 
                        end as ucom
                    FROM oc 
                    JOIN oc_item ON oc_item.idoc = oc.idoc
                    JOIN cliforemp f ON f.idcliforemp = oc.idcliforemp 
                    JOIN item ON item.iditem = oc_item.iditem
                    JOIN unidade ON unidade.idunidade = oc_item.idunidade
                    WHERE oc.idoc = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $idOrdem]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($itens as &$item) {
                $item['ja_conferida'] = $jaConferida;
            }
            
            $payload = json_encode($itens);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
 /**
 * GET /v1/xml/buscar-notas
 * Busca notas do fornecedor no período (SEM FILTRO DE VALOR)
 */
public function buscarNotas(Request $request, Response $response): Response
{
    try {
        $params = $request->getQueryParams();
        $cnpj = preg_replace('/[^0-9]/', '', $params['cnpj'] ?? '');
        $dataOC = $params['data_oc'] ?? date('Y-m-d');
        
        error_log('[XML] buscarNotas - CNPJ: ' . $cnpj . ', Data OC: ' . $dataOC);
        
        if (empty($cnpj) || strlen($cnpj) < 11) {
            $dados = ['aviso' => 'CNPJ do fornecedor não informado ou inválido.'];
            $payload = json_encode($dados, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                $payload = '{"aviso":"CNPJ do fornecedor não informado ou inválido."}';
            }
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // 🔥 VERSÃO SIMPLES - APENAS CAMPOS BÁSICOS QUE COM CERTEZA EXISTEM
        $sql = "SELECT 
                    numeronf, 
                    chave, 
                    valortotal as valor, 
                    razaoemitente as razao, 
                    TO_CHAR(dataemissao, 'DD/MM/YYYY') as emissao
                FROM crm_processo_xml 
                WHERE documentoemitente = :cnpj
                AND statusmanifesto = 2
                AND gerounf = 'N'
                AND dataemissao BETWEEN CAST(:data_oc AS DATE) - INTERVAL '30 days' AND CAST(:data_oc AS DATE) + INTERVAL '30 days'
                ORDER BY dataemissao DESC
                LIMIT 20";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cnpj' => $cnpj,
            ':data_oc' => $dataOC
        ]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('[XML] buscarNotas - Encontradas ' . count($resultados) . ' notas no período');
        
        // 🔥 VALIDAR E SANITIZAR OS DADOS
        $dadosSanitizados = [];
        foreach ($resultados as $row) {
            $dadosSanitizados[] = [
                'numeronf' => (string)($row['numeronf'] ?? ''),
                'chave' => (string)($row['chave'] ?? ''),
                'valor' => (float)($row['valor'] ?? 0),
                'razao' => (string)($row['razao'] ?? ''),
                'emissao' => (string)($row['emissao'] ?? '')
            ];
        }
        
        if (empty($dadosSanitizados)) {
            $dados = ['aviso' => 'Nenhuma nota fiscal encontrada para este fornecedor no período.'];
        } else {
            $dados = $dadosSanitizados;
        }
        
        // 🔥 JSON_ENCODE COM VALIDAÇÃO
        $payload = json_encode($dados, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            // Se falhar, usar fallback manual
            if (empty($dadosSanitizados)) {
                $payload = '{"aviso":"Nenhuma nota fiscal encontrada para este fornecedor."}';
            } else {
                $payload = '[{"numeronf":"Erro","chave":"","valor":0,"razao":"Erro ao processar dados","emissao":""}]';
            }
        }
        
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log('[XML] buscarNotas - ERRO: ' . $e->getMessage());
        error_log('[XML] buscarNotas - TRACE: ' . $e->getTraceAsString());
        
        $payload = json_encode([
            'error' => 'Erro ao buscar notas: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        
        if ($payload === false) {
            $payload = '{"error":"Erro interno ao buscar notas"}';
        }
        
        $response->getBody()->write($payload);
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}
    
/**
 * POST /v1/xml/buscar-notas-multiplas
 * Busca notas específicas por chave para conferência múltipla
 */
public function buscarNotasMultiplas(Request $request, Response $response): Response
{
    try {
        $rawBody = $request->getBody()->getContents();
        error_log('[XML] buscarNotasMultiplas - Body: ' . $rawBody);

        $dados = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
            error_log('[XML] JSON inválido: ' . json_last_error_msg());
            return $this->jsonResponse($response, ['error' => 'JSON inválido'], 400);
        }

        $chaves = $dados['chaves'] ?? [];
        if (empty($chaves) || !is_array($chaves)) {
            return $this->jsonResponse($response, ['error' => 'Nenhuma chave fornecida'], 400);
        }

        error_log('[XML] Chaves recebidas: ' . implode(', ', $chaves));

        $chavesLimpas = array_filter(array_map(function($chave) {
            return preg_replace('/[^0-9]/', '', $chave);
        }, $chaves));

        if (empty($chavesLimpas)) {
            return $this->jsonResponse($response, ['error' => 'Chaves inválidas'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($chavesLimpas), '?'));
        $sql = "SELECT numeronf, chave, valortotal as valor, razaoemitente as razao, 
                       TO_CHAR(dataemissao, 'DD/MM/YYYY') as emissao, anexo
                FROM crm_processo_xml 
                WHERE chave IN ($placeholders)
                AND statusmanifesto = 2
                AND gerounf = 'N'
                ORDER BY dataemissao DESC";

        error_log('[XML] SQL: ' . $sql);
        error_log('[XML] Chaves para bind: ' . implode(', ', $chavesLimpas));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($chavesLimpas);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log('[XML] Registros encontrados: ' . count($resultados));

        foreach ($resultados as &$nota) {
            $nota['xml_conteudo'] = null; // Valor padrão

            // 🔥 CORREÇÃO CRÍTICA: Verificar se o anexo existe e é válido
            if (isset($nota['anexo']) && !empty($nota['anexo'])) {
                try {
                    $xml = $nota['anexo'];

                    // Se for resource (stream do PDO), converte para string
                    if (is_resource($xml)) {
                        $xml = stream_get_contents($xml);
                    }

                    // 🔥 GARANTIR QUE É UMA STRING, NÃO BOOLEANO
                    if ($xml !== false && $xml !== null && $xml !== '') {
                        // Converter para UTF-8 para evitar problemas com json_encode
                        $xml = mb_convert_encoding($xml, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
                        // Remover caracteres nulos ou inválidos
                        $xml = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $xml);
                        $nota['xml_conteudo'] = $xml;
                        error_log('[XML] XML extraído com sucesso para chave: ' . $nota['chave'] . ' (tamanho: ' . strlen($xml) . ')');
                    } else {
                        error_log('[XML] Anexo vazio ou inválido para chave: ' . $nota['chave']);
                    }
                } catch (Exception $e) {
                    error_log('[XML] Erro ao processar anexo da chave ' . $nota['chave'] . ': ' . $e->getMessage());
                }
            } else {
                error_log('[XML] Anexo ausente para chave: ' . $nota['chave']);
            }

            // Remove o campo bruto
            unset($nota['anexo']);
        }

        // Verifica se alguma nota não foi encontrada
        if (count($resultados) < count($chavesLimpas)) {
            error_log('[XML] Nota(s) não encontrada(s) para as chaves fornecidas.');
        }

        return $this->jsonResponse($response, $resultados);

    } catch (\PDOException $e) {
        error_log('[XML] PDOException: ' . $e->getMessage());
        error_log('[XML] SQL: ' . ($sql ?? ''));
        return $this->jsonResponse($response, ['error' => 'Erro no banco: ' . $e->getMessage()], 500);
    } catch (\Throwable $e) {
        error_log('[XML] Exceção fatal: ' . $e->getMessage());
        error_log('[XML] Trace: ' . $e->getTraceAsString());
        return $this->jsonResponse($response, ['error' => 'Erro interno: ' . $e->getMessage()], 500);
    }
}

    /**
     * GET /v1/xml/itens-xml
     */
    public function getItensXml(Request $request, Response $response): Response
    {
        $chave = preg_replace('/[^0-9]/', '', $request->getQueryParams()['chave'] ?? '');
        
        if (empty($chave)) {
            $response->getBody()->write(json_encode(['error' => 'Chave não fornecida']));
            return $response->withStatus(400);
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT anexo FROM crm_processo_xml WHERE chave = :chave LIMIT 1");
            $stmt->execute(['chave' => $chave]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($res && $res['anexo']) {
                $xml = $res['anexo'];
                if (is_resource($xml)) {
                    $xml = stream_get_contents($xml);
                }
                $response->getBody()->write(trim($xml));
                return $response->withHeader('Content-Type', 'application/xml');
            } else {
                return $response->withStatus(404);
            }
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
 /**
 * GET /v1/xml/buscar-item - Busca item por referência/EAN para adicionar à OC
 * CORRIGIDO: Colunas corretas da tabela item
 */
public function buscarItem(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $termo = trim($params['termo'] ?? '');
    $idFilial = (int)($params['idfilial'] ?? 0);
    
    // Se não tem termo, retorna array vazio
    if (empty($termo)) {
        return $this->jsonResponse($response, []);
    }
    
    try {
        // Limpa o termo para busca por EAN (apenas números)
        $termoEAN = preg_replace('/[^0-9]/', '', $termo);
        $termoLike = '%' . $termo . '%';
        
        $sql = "SELECT   
                    i.iditem, 
                    i.referencia, 
                    i.descricao, 
                    i.fator_conversao,
                    (SELECT DISTINCT classificacao FROM classificacaofiscal WHERE idclassificacaofiscal = i.idclassfiscal) as NCM,
                    (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = i.iditem AND cb.principal = 'S' LIMIT 1) as ean_unidade,
                    (SELECT cb.idbarra FROM codigo_barra cb WHERE cb.iditem = i.iditem AND cb.principal = 'N' AND cb.gerado_auto = 'N' LIMIT 1) as ean_caixa,
                    -- CORRIGIDO: usar idunidadereferencia em vez de idunidade (que não existe)
                    (SELECT u.descricao FROM unidade u WHERE u.idunidade = i.idunidadereferencia LIMIT 1) as unidade_ref,
                    -- CORRIGIDO: usar idunidadereferencia também para a unidade básica
                    (SELECT u.descricao FROM unidade u WHERE u.idunidade = i.idunidadereferencia LIMIT 1) as unidade,
                    COALESCE((
                        SELECT valorultimacompra FROM estoque_filial ef  
                        WHERE ef.iditem = i.iditem AND ef.idfilial = :idfilial 
                        LIMIT 1
                    ), 0) as preco_compra
                FROM item i
                WHERE i.inativo = 'N'
                AND (
                    -- PRIORIDADE 1: Referência exata (começa com)
                    i.referencia ILIKE :termo_exato
                    OR 
                    -- PRIORIDADE 2: Descrição contém
                    i.descricao ILIKE :termo_like
                    OR
                    -- PRIORIDADE 3: EAN exato (apenas números)
                    EXISTS (
                        SELECT 1 FROM codigo_barra cb2 
                        WHERE cb2.iditem = i.iditem 
                        AND cb2.idbarra = :termo_ean
                    )
                    OR
                    -- PRIORIDADE 4: EAN contém (para buscas parciais)
                    EXISTS (
                        SELECT 1 FROM codigo_barra cb2 
                        WHERE cb2.iditem = i.iditem 
                        AND cb2.idbarra ILIKE :termo_ean_like
                    )
                )
                ORDER BY 
                    -- Ordenar por relevância: referência exata primeiro
                    CASE WHEN i.referencia ILIKE :termo_exato THEN 1 ELSE 2 END,
                    i.referencia
                LIMIT 20";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'termo_exato' => $termo . '%',
            'termo_like' => $termoLike,
            'termo_ean' => $termoEAN,
            'termo_ean_like' => '%' . $termoEAN . '%',
            'idfilial' => $idFilial
        ]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 🔥 GARANTIR QUE RETORNA UM ARRAY, MESMO SE VAZIO
        return $this->jsonResponse($response, $data ?: []);
        
    } catch (\Exception $e) {
        error_log('[XML] Erro ao buscar item: ' . $e->getMessage());
        // 🔥 RETORNAR ARRAY VAZIO EM VEZ DE ERRO
        return $this->jsonResponse($response, []);
    }
}
  /**
 * POST /v1/xml/adicionar-item - Adiciona item do XML à OC
 * CORRIGIDO: Remove referência à tabela item_preco que não existe
 */
public function adicionarItem(Request $request, Response $response): Response
{
    $dados = json_decode($request->getBody()->getContents(), true) ?? [];
    
    if (empty($dados['idoc']) || empty($dados['iditem'])) {
        return $this->jsonResponse($response, ['success' => false, 'error' => 'Dados incompletos'], 400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Buscar dados da OC
        $stmtOC = $this->pdo->prepare("SELECT idempresa, idfilial, idtabelaprecoentrada FROM oc WHERE idoc = :idoc");
        $stmtOC->execute(['idoc' => $dados['idoc']]);
        $ocData = $stmtOC->fetch(PDO::FETCH_ASSOC);
        
        if (!$ocData) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'OC não encontrada'], 404);
        }
        
        // Buscar próximo iditemoc
        $stmtMax = $this->pdo->prepare("SELECT COALESCE(MAX(iditemoc), 0) + 1 FROM oc_item WHERE idoc = :idoc");
        $stmtMax->execute(['idoc' => $dados['idoc']]);
        $nextIdItemOC = (int)$stmtMax->fetchColumn();
        
        // CORRIGIDO: Buscar dados do item sem usar item_preco
        $stmtItem = $this->pdo->prepare("
            SELECT 
                i.iditem,
                i.referencia,
                i.descricao,
                i.fator_conversao,
                i.idunidadereferencia as idunidade,
                -- Buscar preço do estoque_filial se disponível
                COALESCE(
                    (SELECT ef.valorultimacompra 
                     FROM estoque_filial ef 
                     WHERE ef.iditem = i.iditem 
                       AND ef.idfilial = :idfilial 
                     LIMIT 1),
                    -- Fallback: buscar do preço médio do item
                    (SELECT ef.VALORCUSTOMEDIOUNITARIO 
                     FROM estoque_filial ef 
                     WHERE ef.iditem = i.iditem 
                       AND ef.idfilial = :idfilial 
                     LIMIT 1),
                    0
                ) as preco_compra
            FROM item i 
            WHERE i.iditem = :iditem
        ");
        $stmtItem->execute([
            'iditem' => $dados['iditem'],
            'idfilial' => $ocData['idfilial']
        ]);
        $itemData = $stmtItem->fetch(PDO::FETCH_ASSOC);
        
        if (!$itemData) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Item não encontrado'], 404);
        }
        
        // Usar o valor enviado pelo frontend ou o preço encontrado
        $quantidade = (float)($dados['quantidade'] ?? 1);
        $valorUnitario = (float)($dados['valor_unitario'] ?? $itemData['preco_compra'] ?? 0);
        
        // Se o valor unitário for 0, tentar buscar do XML original (já deve ter vindo do frontend)
        if ($valorUnitario <= 0) {
            $valorUnitario = (float)($dados['valor_unitario'] ?? 0);
        }
        
        // Se ainda for 0, usar um valor padrão ou lançar erro
        if ($valorUnitario <= 0) {
            // Tenta buscar do último preço de compra da OC
            $stmtUltimoPreco = $this->pdo->prepare("
                SELECT valor FROM oc_item 
                WHERE iditem = :iditem AND idoc != :idoc 
                ORDER BY iditemoc DESC LIMIT 1
            ");
            $stmtUltimoPreco->execute([
                'iditem' => $dados['iditem'],
                'idoc' => $dados['idoc']
            ]);
            $ultimoPreco = $stmtUltimoPreco->fetch(PDO::FETCH_ASSOC);
            if ($ultimoPreco) {
                $valorUnitario = (float)$ultimoPreco['valor'];
            }
        }
        
        // Último fallback
        if ($valorUnitario <= 0) $valorUnitario = 1;
        
        $valorTotal = $quantidade * $valorUnitario;
        $fatorConversao = (float)($itemData['fator_conversao'] ?? 1);
        
        // Se fator_conversao for 0 ou negativo, usar 1
        if ($fatorConversao <= 0) $fatorConversao = 1;
        
        // Usar idunidade do itemData ou fallback para 1
        $idUnidade = (int)($itemData['idunidade'] ?? 1);
        if ($idUnidade <= 0) $idUnidade = 1;
        
        // Inserir na oc_item
        $sqlInsert = "INSERT INTO oc_item (
            iditemoc, idoc, idempresa, idfilial, iditem, idunidade,
            qt, qtsaldo, valor, valortotal,
            fator_conversao, ativo
        ) VALUES (
            :iditemoc, :idoc, :idempresa, :idfilial, :iditem, :idunidade,
            :qt, :qtsaldo, :valor, :valortotal,
            :fator, 'S'
        )";
        
        $stmtInsert = $this->pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            'iditemoc' => $nextIdItemOC,
            'idoc' => $dados['idoc'],
            'idempresa' => $ocData['idempresa'],
            'idfilial' => $ocData['idfilial'],
            'iditem' => $dados['iditem'],
            'idunidade' => $idUnidade,
            'qt' => $quantidade,
            'qtsaldo' => $quantidade,
            'valor' => $valorUnitario,
            'valortotal' => $valorTotal,
            'fator' => $fatorConversao
        ]);
        
        // Registrar operação
        $stmtLog = $this->pdo->prepare("
            INSERT INTO oc_operacao (idoperacao, idoc, acao, descricao, datahora, usuario) 
            VALUES (NEXTVAL('gi_oc_operacao'), :idoc, 7, :desc, NOW(), 'PORTAL')
        ");
        $stmtLog->execute([
            'idoc' => $dados['idoc'],
            'desc' => "Item {$itemData['referencia']} - {$itemData['descricao']} (ID: {$dados['iditem']}) adicionado via conferência XML. Qtd: {$quantidade}, Valor: R$ " . number_format($valorUnitario, 2, ',', '.')
        ]);
        
        // Recalcular totais da OC
        $this->recalcularTotaisOC($dados['idoc']);
        
        // Recalcular parcelas da OC
        $this->recalcularParcelas($dados['idoc']);
        
        $this->pdo->commit();
        
        return $this->jsonResponse($response, [
            'success' => true, 
            'iditemoc' => $nextIdItemOC,
            'message' => 'Item adicionado com sucesso'
        ]);
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        error_log('[XML] Erro ao adicionar item: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        return $this->jsonResponse($response, [
            'success' => false, 
            'error' => 'Erro ao adicionar item: ' . $e->getMessage()
        ], 500);
    }
}
 /**
 * POST /v1/xml/deletar-item
 * Remove um item da OC e recalcula totais e parcelas
 * CORRIGIDO: Adicionada verificação adicional
 */
public function deletarItem(Request $request, Response $response): Response
{
    $dados = json_decode($request->getBody()->getContents(), true) ?? [];
    
    if (empty($dados['idoc']) || empty($dados['iditem'])) {
        return $this->jsonResponse($response, ['success' => false, 'error' => 'Dados incompletos'], 400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
        // Verificar se a OC existe
        $stmtCheckOC = $this->pdo->prepare("SELECT idoc FROM oc WHERE idoc = :idoc");
        $stmtCheckOC->execute(['idoc' => $dados['idoc']]);
        if (!$stmtCheckOC->fetch()) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'OC não encontrada'], 404);
        }
        
        // Buscar o iditemoc e o valor do item
        $stmtBusca = $this->pdo->prepare("
            SELECT iditemoc, valortotal 
            FROM oc_item 
            WHERE idoc = :idoc AND iditem = :iditem
        ");
        $stmtBusca->execute(['idoc' => $dados['idoc'], 'iditem' => $dados['iditem']]);
        $item = $stmtBusca->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Item não encontrado na OC'], 404);
        }
        
        $iditemoc = $item['iditemoc'];
        $valorItem = (float)$item['valortotal'];
        
        // Deletar usando iditemoc (com verificação dupla)
        $stmt = $this->pdo->prepare("
            DELETE FROM oc_item 
            WHERE idoc = :idoc AND iditemoc = :iditemoc
        ");
        $stmt->execute(['idoc' => $dados['idoc'], 'iditemoc' => $iditemoc]);
        
        // Verificar se deletou algo
        if ($stmt->rowCount() === 0) {
            throw new \Exception("Nenhum item foi deletado. Verifique os parâmetros.");
        }
        
        // Recalcular totais da OC
        $this->recalcularTotaisOC($dados['idoc']);
        
        // Recalcular parcelas da OC
        $this->recalcularParcelas($dados['idoc']);
        
        // Registrar operação
        $stmtLog = $this->pdo->prepare("
            INSERT INTO oc_operacao (idoperacao, idoc, acao, descricao, datahora, usuario) 
            VALUES (NEXTVAL('gi_oc_operacao'), :idoc, 7, :desc, NOW(), 'SISTEMA')
        ");
        $stmtLog->execute([
            'idoc' => $dados['idoc'],
            'desc' => "Item ID {$dados['iditem']} (iditemoc: {$iditemoc}) removido na conferência digital. Valor: R$ {$valorItem}"
        ]);
        
        $this->pdo->commit();
        
        error_log("[XML] Item removido e parcelas recalculadas para OC #{$dados['idoc']}");
        
        return $this->jsonResponse($response, ['success' => true, 'aviso' => 'recalcular_erp']);
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
            error_log("[XML] ERRO ao deletar item da OC #{$dados['idoc']}: " . $e->getMessage());
        }
        return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}
/**
 * POST /v1/xml/atualizar-conferencia
 * Atualiza quantidades, processa exclusões e adições, recalcula totais e parcelas
 * CORRIGIDO: Adicionadas validações e logs
 */
public function atualizarConferencia(Request $request, Response $response): Response
{
    try {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $body = $request->getBody()->getContents();
        $params = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'JSON inválido'
            ], 400);
        }

        $idoc = $params['idoc'] ?? null;
        if (!$idoc) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'ID da OC não fornecido'
            ], 400);
        }

        // 1. Buscar dados da OC
        $stmtOC = $this->pdo->prepare("SELECT idempresa, idfilial FROM oc WHERE idoc = :idoc");
        $stmtOC->execute(['idoc' => $idoc]);
        $ocData = $stmtOC->fetch(\PDO::FETCH_ASSOC);
        if (!$ocData) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'OC não encontrada'
            ], 404);
        }
        $idEmpresa = (int)$ocData['idempresa'];
        $idFilial = (int)$ocData['idfilial'];

        $this->pdo->beginTransaction();

        // 2. Processar exclusões
        $itensDeletar = isset($params['itens_deletar']) ? (array)$params['itens_deletar'] : [];
        if (!empty($itensDeletar)) {
            foreach ($itensDeletar as $iditem) {
                // Buscar iditemoc com verificação
                $stmtBusca = $this->pdo->prepare("
                    SELECT iditemoc 
                    FROM oc_item 
                    WHERE idoc = :idoc AND iditem = :iditem
                ");
                $stmtBusca->execute(['idoc' => $idoc, 'iditem' => $iditem]);
                $item = $stmtBusca->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $stmt = $this->pdo->prepare("
                        DELETE FROM oc_item 
                        WHERE idoc = :idoc AND iditemoc = :iditemoc
                    ");
                    $stmt->execute(['idoc' => $idoc, 'iditemoc' => $item['iditemoc']]);
                }
            }
        }

        // 3. Processar atualizações de quantidade
        $itens = isset($params['itens']) ? (array)$params['itens'] : [];
        $linhasAfetadas = 0;

        foreach ($itens as $item) {
            $iditem = (int)($item['iditem'] ?? 0);
            $qConf = (float)($item['quantidade'] ?? 0);

            if ($iditem <= 0 || $qConf < 0) continue;

            $stmtPreco = $this->pdo->prepare("
                SELECT valor, iditemoc 
                FROM oc_item 
                WHERE idoc = :idoc AND iditem = :iditem
            ");
            $stmtPreco->execute(['idoc' => $idoc, 'iditem' => $iditem]);
            $row = $stmtPreco->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) continue;
            
            $iditemoc = (int)$row['iditemoc'];

            $stmtUp = $this->pdo->prepare("
                UPDATE oc_item 
                SET qt = :q, 
                    qtsaldo = :q, 
                    valortotal = ROUND(CAST(:q * valor AS NUMERIC), 2) 
                WHERE idoc = :idoc AND iditemoc = :iditemoc
            ");
            $stmtUp->execute(['q' => $qConf, 'idoc' => $idoc, 'iditemoc' => $iditemoc]);
            $linhasAfetadas += $stmtUp->rowCount();
        }

        // 4. Processar adições de novos itens
        $itensAdicionar = isset($params['itens_adicionar']) ? (array)$params['itens_adicionar'] : [];
        if (!empty($itensAdicionar)) {
            foreach ($itensAdicionar as $novoItem) {
                $iditem = (int)($novoItem['iditem'] ?? 0);
                $quantidade = (float)($novoItem['quantidade'] ?? 0);
                $valorUnitario = (float)($novoItem['valor_unitario'] ?? 0);

                if ($iditem <= 0 || $quantidade <= 0) continue;

                $valorTotal = $quantidade * $valorUnitario;

                $stmtMax = $this->pdo->prepare("
                    SELECT COALESCE(MAX(iditemoc), 0) + 1 
                    FROM oc_item 
                    WHERE idoc = :idoc
                ");
                $stmtMax->execute(['idoc' => $idoc]);
                $nextIdItemOC = (int)$stmtMax->fetchColumn();

                $stmtItem = $this->pdo->prepare("
                    SELECT 
                        i.idunidadereferencia as idunidade,
                        i.fator_conversao
                    FROM item i
                    WHERE i.iditem = :iditem
                ");
                $stmtItem->execute(['iditem' => $iditem]);
                $itemData = $stmtItem->fetch(PDO::FETCH_ASSOC);
                
                $idunidade = (int)($itemData['idunidade'] ?? 1);
                if ($idunidade <= 0) $idunidade = 1;
                
                $fatorConversao = (float)($itemData['fator_conversao'] ?? 1);
                if ($fatorConversao <= 0) $fatorConversao = 1;

                $sqlInsert = "INSERT INTO oc_item (
                    iditemoc, idoc, idempresa, idfilial, iditem, idunidade,
                    qt, qtsaldo, valor, valortotal, fator_conversao, ativo
                ) VALUES (
                    :iditemoc, :idoc, :idempresa, :idfilial, :iditem, :idunidade,
                    :qt, :qtsaldo, :valor, :valortotal, :fator, 'S'
                )";
                $stmtInsert = $this->pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    'iditemoc' => $nextIdItemOC,
                    'idoc' => $idoc,
                    'idempresa' => $idEmpresa,
                    'idfilial' => $idFilial,
                    'iditem' => $iditem,
                    'idunidade' => $idunidade,
                    'qt' => $quantidade,
                    'qtsaldo' => $quantidade,
                    'valor' => $valorUnitario,
                    'valortotal' => $valorTotal,
                    'fator' => $fatorConversao
                ]);
            }
        }

        // 5. Recalcular totais da OC
        $this->recalcularTotaisOC($idoc);
        
        // 6. Recalcular parcelas da OC
        $this->recalcularParcelas($idoc);

        // 7. Registrar log
        try {
            $descLog = "Conferência finalizada via Portal. ";
            if (!empty($itensDeletar)) $descLog .= "Excluídos: " . count($itensDeletar) . " itens. ";
            if (!empty($itensAdicionar)) $descLog .= "Adicionados: " . count($itensAdicionar) . " itens. ";
            if (!empty($itens)) $descLog .= "Atualizados: " . count($itens) . " itens.";

            $stmtLog = $this->pdo->prepare("
                INSERT INTO oc_operacao (
                    idoperacao, idoc, idempresa, idfilial, acao, descricao, datahora, usuario, estacaologistica
                ) VALUES (
                    NEXTVAL('gi_oc_operacao'), :idoc, :idempresa, :idfilial, 7, :desc, NOW(), 'PORTAL', 'PORTAL'
                )
            ");
            $stmtLog->execute([
                'idoc' => $idoc,
                'idempresa' => $idEmpresa,
                'idfilial' => $idFilial,
                'desc' => $descLog
            ]);
        } catch (\Exception $e) {
            error_log('[XML] Erro ao inserir log: ' . $e->getMessage());
        }

        // 8. Commit
        $this->pdo->commit();

        return $this->jsonResponse($response, [
            'success' => true,
            'aviso' => 'recalcular_erp',
            'linhas_afetadas' => $linhasAfetadas,
            'itens_recebidos' => count($itens)
        ]);

    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
            error_log('[XML] ROLLBACK executado: ' . $e->getMessage());
        }
        return $this->jsonResponse($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
private function marcarConferidoPortal($idoc, $idEmpresa, $idFilial): void
{
    try {
        $stmtCheck = $this->pdo->prepare("SELECT idoc FROM aps_oc_conferencia WHERE idoc = :idoc");
        $stmtCheck->execute(['idoc' => $idoc]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $this->pdo->prepare("
                UPDATE aps_oc_conferencia 
                SET conferido_portal = 'S', 
                    datahora = NOW(), 
                    usuario = 'PORTAL'
                WHERE idoc = :idoc
            ")->execute(['idoc' => $idoc]);
        } else {
            $this->pdo->prepare("
                INSERT INTO aps_oc_conferencia (
                    idoc, idempresa, idfilial, horainicio, horafim, 
                    tempototal, quantidadeitens, status, data, usuario, datahora, conferido_portal
                ) VALUES (
                    :idoc, :idempresa, :idfilial, CURRENT_TIME, CURRENT_TIME, 
                    0, 0, 1, CURRENT_DATE, 'PORTAL', NOW(), 'S'
                )
            ")->execute([
                'idoc' => $idoc,
                'idempresa' => $idEmpresa,
                'idfilial' => $idFilial
            ]);
        }
    } catch (\Exception $e) {
        throw $e; // relança para ser capturado no chamador
    }
}
    /**
     * Recalcula totais da OC (valortotal, valortotalsaldo, valortotalitens)
     */
  private function recalcularTotaisOC($idoc): void
{
    if (empty($idoc) || $idoc <= 0) {
        error_log("[XML] ID da OC inválido para recalcularTotaisOC: {$idoc}");
        return;
    }
    
    try {
        // Somar itens
        $stmtSoma = $this->pdo->prepare("SELECT COALESCE(SUM(valortotal), 0) as total FROM oc_item WHERE idoc = :idoc");
        $stmtSoma->execute(['idoc' => $idoc]);
        $novoTotal = (float)$stmtSoma->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Atualizar cabeçalho
        $stmtHead = $this->pdo->prepare("UPDATE oc SET valortotal = :v, valortotalsaldo = :v, valortotalitens = :v WHERE idoc = :idoc");
        $stmtHead->execute(['v' => $novoTotal, 'idoc' => $idoc]);
        
        error_log("[XML] Totais recalculados para OC #{$idoc}: R$ {$novoTotal}");
        
    } catch (\Exception $e) {
        error_log("[XML] ERRO ao recalcular totais da OC #{$idoc}: " . $e->getMessage());
    }
}
    
 /**
 /**
 * Recalcula parcelas da OC baseado no novo valor total
 * CORRIGIDO: Garante que só atualiza as parcelas da OC específica
 */
private function recalcularParcelas($idoc): void
{
    try {
        // Buscar valor total atualizado
        $stmtTotal = $this->pdo->prepare("SELECT valortotal FROM oc WHERE idoc = :idoc");
        $stmtTotal->execute(['idoc' => $idoc]);
        $valorTotal = (float)$stmtTotal->fetchColumn();
        
        // Buscar parcelas da OC específica
        $stmtParcelas = $this->pdo->prepare("
            SELECT idparcela, percdesconto 
            FROM oc_parcelas 
            WHERE idoc = :idoc 
            ORDER BY idparcela
        ");
        $stmtParcelas->execute(['idoc' => $idoc]);
        $parcelas = $stmtParcelas->fetchAll(PDO::FETCH_ASSOC);
        
        $numParcelas = count($parcelas);
        
        if ($numParcelas === 0 || $valorTotal <= 0) {
            error_log("[XML] OC #{$idoc} - Sem parcelas ou valor total zero: {$valorTotal}");
            return;
        }
        
        // Log para debug
        error_log("[XML] Recalculando parcelas da OC #{$idoc} - Total: R$ {$valorTotal}, Parcelas: {$numParcelas}");
        
        $valorPorParcela = round($valorTotal / $numParcelas, 2);
        $diferenca = $valorTotal - ($valorPorParcela * $numParcelas);
        
        foreach ($parcelas as $index => $parcela) {
            $idparcela = (int)$parcela['idparcela'];
            $percDesconto = (float)($parcela['percdesconto'] ?? 0);
            
            $valorParcela = $valorPorParcela;
            
            // Ajustar diferença na última parcela
            if ($index === $numParcelas - 1) {
                $valorParcela += $diferenca;
            }
            
            // Aplicar desconto se houver
            $desconto = 0;
            if ($percDesconto > 0) {
                $desconto = round($valorParcela * ($percDesconto / 100), 2);
            }
            
            // 🔥 CORREÇÃO CRÍTICA: Garantir que o WHERE está correto
            $stmtUpdate = $this->pdo->prepare("
                UPDATE oc_parcelas 
                SET valor = :valor, 
                    desconto = :desconto 
                WHERE idparcela = :idparcela 
                AND idoc = :idoc
            ");
            $stmtUpdate->execute([
                'valor' => $valorParcela - $desconto,
                'desconto' => $desconto,
                'idparcela' => $idparcela,
                'idoc' => $idoc
            ]);
            
            error_log("[XML] OC #{$idoc} - Parcela {$idparcela}: R$ {$valorParcela} - Desconto R$ {$desconto} = R$ " . ($valorParcela - $desconto));
        }
        
        error_log("[XML] Parcelas recalculadas com sucesso para OC #{$idoc}");
        
    } catch (\Exception $e) {
        error_log("[XML] ERRO ao recalcular parcelas da OC #{$idoc}: " . $e->getMessage());
        // Não relança a exceção para não interromper o fluxo principal
    }
}
   /**
 * POST /v1/xml/enviar-email
 * Envia e-mail com relatório de divergências (versão completa)
 */
public function enviarEmailDivergencia(Request $request, Response $response): Response
{
    $params = $request->getParsedBody();
    $uploadedFiles = $request->getUploadedFiles();
    
    $idoc = $params['idoc'] ?? 'N/A';
    $fornecedor = $params['fornecedor'] ?? 'Não informado';
    
    // 🔧 NOVOS PARÂMETROS (opcionais - para email detalhado)
    $totalOC = $params['total_oc'] ?? '';
    $totalNF = $params['total_nf'] ?? '';
    $qtdDivergencias = $params['qtd_divergencias'] ?? '';
    $tabelaDivergencias = $params['tabela_divergencias'] ?? '';
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USER'];
        $mail->Password = $_ENV['MAIL_PASS'];
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $_ENV['MAIL_PORT'];
        $mail->CharSet = 'UTF-8';
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        
        $mail->setFrom($_ENV['MAIL_USER'], 'Portal Nutricional');
        $mail->addAddress('alan@nutricionalbr.com');
		$mail->addAddress('robson@nutricionalbr.com');
        $mail->addAddress('faturamento@nutricionalbr.com');
       
        
        $mail->isHTML(true);
        $mail->Subject = "DIVERGÊNCIA CRÍTICA OC: {$idoc} - {$fornecedor}";
        
        // 🔧 SE TIVER OS DADOS DETALHADOS, USA O HTML BONITO
        if (!empty($tabelaDivergencias)) {
            $mail->Body = $this->buildEmailBody($idoc, $fornecedor, $totalOC, $totalNF, $qtdDivergencias, $tabelaDivergencias);
        } else {
            // FALLBACK: corpo simples
            $mail->Body = $this->buildSimpleEmailBody($idoc, $fornecedor);
        }
        
        $mail->AltBody = strip_tags($mail->Body);
        
        // Anexar PDF
        if (isset($uploadedFiles['pdf']) && $uploadedFiles['pdf']->getError() === UPLOAD_ERR_OK) {
            $pdf = $uploadedFiles['pdf'];
            $mail->addAttachment($pdf->getFilePath(), "Divergencia_OC_{$idoc}.pdf");
        }
        
        $mail->send();
        
        return $this->jsonResponse($response, ['success' => true]);
        
    } catch (\Exception $e) {
        error_log("Erro ao enviar e-mail: " . $e->getMessage());
        return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}


/**
 * Monta o corpo do email com HTML detalhado
 */
private function buildEmailBody($idoc, $fornecedor, $totalOC, $totalNF, $qtdDivergencias, $tabelaDivergencias): string
{
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            .container { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; }
            .header { background: #274036; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: white; margin: 0; font-size: 24px; }
            .header p { color: #94a3b8; margin: 5px 0 0; }
            .content { padding: 20px; background: white; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px; }
            .summary { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .summary p { margin: 5px 0; }
            .divergente-title { color: #dc2626; margin-top: 20px; margin-bottom: 10px; font-size: 18px; }
            .item-card { background: #f8fafc; border-left: 4px solid #dc2626; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
            .item-header { color: #274036; font-size: 12px; font-weight: bold; }
            .item-name { font-weight: bold; font-size: 14px; margin: 8px 0; }
            .item-details { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 8px; }
            .detail-label { color: #64748b; font-size: 11px; }
            .detail-value { font-weight: bold; }
            .detail-value-oc { color: #dc2626; }
            .detail-value-nota { color: #dc2626; }
            .detail-value-conferida { color: #10b981; }
            hr { margin: 20px 0; }
            .footer { color: #64748b; font-size: 11px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📋 Relatório de Divergências</h1>
                <p>OC #{$idoc}</p>
            </div>
            <div class='content'>
                <div class='summary'>
                    <p><strong>📅 Fornecedor:</strong> {$fornecedor}</p>
                    <p><strong>📅 Data:</strong> " . date('d/m/Y H:i:s') . "</p>
                    <p><strong>💰 Total OC:</strong> {$totalOC} | <strong>Total NF:</strong> {$totalNF}</p>
                    <p><strong>⚠️ Divergências:</strong> {$qtdDivergencias} itens</p>
                </div>
                
                <h2 class='divergente-title'>⚠️ Itens Divergentes</h2>
                {$tabelaDivergencias}
                
                <hr>
                <p class='footer'>
                    📎 Este e-mail contém um PDF anexo com o relatório completo.<br>
                    Relatório gerado automaticamente pelo Portal Nutricional
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Monta o corpo simples do email (fallback)
 */
private function buildSimpleEmailBody($idoc, $fornecedor): string
{
    return "
    <h2>Relatório de Auditoria de Carga</h2>
    <p>Divergência na conferência da <b>OC #{$idoc}</b> do fornecedor <b>{$fornecedor}</b>.</p>
    <p>PDF em anexo.</p>
    <hr>
    <p style='color: #64748b; font-size: 11px;'>
        Relatório gerado automaticamente pelo Portal Nutricional em " . date('d/m/Y H:i:s') . "
    </p>
    ";
}


    /**
 * Retorna resposta JSON padronizada
 */
private function jsonResponse($response, array $data, int $status = 200): Response
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}
}