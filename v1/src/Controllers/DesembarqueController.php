<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class DesembarqueController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    
/**
 * GET /v1/desembarque/ordens-compra
 * Lista OCs disponíveis para conferência (filial 1 e 6, últimos 30 dias)
 */
public function getOrdensCompra(Request $request, Response $response): Response
{
    try {
        $sql = "
                 SELECT DISTINCT 
    oc.idoc, 
    TO_CHAR(oc.data, 'DD/MM/YYYY') as data_oc,
    f.razao || ' | ' || COALESCE(f.fantasia, '') as fornecedor,
    oc.valortotal,
    COALESCE(a.status, 0) as status_conferencia,
    CASE 
        WHEN a.status = 3 THEN 'FINALIZADO' 
        WHEN a.status = 2 THEN 'EM ANDAMENTO' 
        ELSE 'ABERTO' 
    END as situacao
FROM oc
JOIN cliforemp f ON f.idcliforemp = oc.idcliforemp
LEFT JOIN aps_oc_conferencia a ON a.idoc = oc.idoc
WHERE oc.status = 1
  AND oc.idfilial IN (1,6)
  AND oc.dataprevisao BETWEEN CURRENT_DATE - INTERVAL '5 days' AND CURRENT_DATE + INTERVAL '5 days'
  AND (a.status IS NULL OR a.status <> 3)
ORDER BY oc.idoc DESC
        ";
        
        $stmt = $this->pdo->query($sql);
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
     * GET /v1/desembarque/itens/{idoc}
     */
    public function getItens(Request $request, Response $response, array $args): Response
    {
        $idoc = (int)($args['idoc'] ?? 0);
        
        if ($idoc <= 0) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $sql = "
                SELECT 
                    oi.idoc AS codordem,
                    oi.iditem AS cod_item,
                    i.descricao AS nome_item,
                    i.referencia,
                    i.path_foto_master,
                    oi.qt AS quant_oc,
                    COALESCE((
                        SELECT quantidadeconferida
                        FROM aps_oc_conferencia_item ao 
                        WHERE ao.idoc = oi.idoc AND ao.iditem = oi.iditem
                    ), 0) AS qt_recebido,
                    oi.qt - COALESCE((
                        SELECT quantidadeconferida 
                        FROM aps_oc_conferencia_item ao 
                        WHERE ao.idoc = oi.idoc AND ao.iditem = oi.iditem
                    ), 0) AS saldo,
                    (SELECT idbarra FROM codigo_barra WHERE iditem = oi.iditem AND principal = 'S' LIMIT 1) AS cod_barras,
                    COALESCE((SELECT STRING_AGG(idbarra, ',') FROM codigo_barra WHERE iditem = oi.iditem), 'SEM_BARRA') AS todos_codigos
                FROM oc_item oi
                JOIN item i ON i.iditem = oi.iditem
                WHERE oi.idoc = :idoc
                  AND oi.qt - COALESCE((
                      SELECT quantidadeconferida 
                      FROM aps_oc_conferencia_item ao 
                      WHERE ao.idoc = oi.idoc AND ao.iditem = oi.iditem
                  ), 0) > 0
                ORDER BY i.descricao
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['idoc' => $idoc]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($itens ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/desembarque/buscar-item/{idoc}?codigo=xxx
     */
    public function buscarItem(Request $request, Response $response, array $args): Response
    {
        $idoc = (int)($args['idoc'] ?? 0);
        $codigo = $request->getQueryParams()['codigo'] ?? '';
        
        if ($idoc <= 0 || empty($codigo)) {
            $response->getBody()->write(json_encode(['error' => 'Parâmetros inválidos']));
            return $response->withStatus(400);
        }
        
        try {
            $sql = "
                SELECT 
                    oi.idoc AS codordem,
                    oi.iditem AS cod_item,
                    i.descricao AS nome_item,
                    i.referencia,
                    i.path_foto_master,
                    oi.qt AS quant_oc,
                    COALESCE((
                        SELECT quantidadeconferida
                        FROM aps_oc_conferencia_item ao 
                        WHERE ao.idoc = oi.idoc AND ao.iditem = oi.iditem
                    ), 0) AS qt_recebido,
                    oi.qt - COALESCE((
                        SELECT quantidadeconferida 
                        FROM aps_oc_conferencia_item ao 
                        WHERE ao.idoc = oi.idoc AND ao.iditem = oi.iditem
                    ), 0) AS saldo,
                    (SELECT idbarra FROM codigo_barra WHERE iditem = oi.iditem AND principal = 'S' LIMIT 1) AS cod_barras
                FROM oc_item oi
                JOIN item i ON i.iditem = oi.iditem
                WHERE oi.idoc = :idoc
                  AND (
                      oi.iditem::text = :codigo
                      OR oi.iditem IN (
                          SELECT iditem FROM codigo_barra WHERE idbarra = :codigo2 AND inativo = 'N'
                      )
                  )
                  AND oi.qt - COALESCE((
                      SELECT quantidadeconferida 
                      FROM aps_oc_conferencia_item ao 
                      WHERE ao.idoc = oi.idoc AND ao.iditem = oi.iditem
                  ), 0) > 0
                LIMIT 1
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['idoc' => $idoc, 'codigo' => $codigo, 'codigo2' => $codigo]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                $response->getBody()->write(json_encode(['error' => 'Item não encontrado ou sem saldo']));
                return $response->withStatus(404);
            }
            
            $payload = json_encode($item);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

    /**
 * POST /v1/desembarque/foto
 */
public function uploadFoto(Request $request, Response $response): Response
{
    $uploadedFiles = $request->getUploadedFiles();
    
    if (empty($uploadedFiles['foto'])) {
        $response->getBody()->write(json_encode(['error' => 'Nenhuma foto enviada']));
        return $response->withStatus(400);
    }

    $foto = $uploadedFiles['foto'];
    if ($foto->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode(['error' => 'Erro no upload']));
        return $response->withStatus(500);
    }

    $params = $request->getParsedBody();
    $iditem = (int)($params['iditem'] ?? 0);
    $idusuario = (int)($params['idusuario'] ?? 0);

    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $clientMediaType = $foto->getClientMediaType();
    if (!in_array($clientMediaType, $allowedTypes)) {
        $response->getBody()->write(json_encode(['error' => 'Tipo não permitido']));
        return $response->withStatus(400);
    }

    $ext = pathinfo($foto->getClientFilename(), PATHINFO_EXTENSION);
    $nomeArquivo = 'desemb_' . $iditem . '_' . date('Ymd_His') . '.' . $ext;
    $caminhoRelativo = 'uploads/desembarque/' . $nomeArquivo;
    $caminhoAbsoluto = __DIR__ . '/../../../uploads/desembarque/' . $nomeArquivo;

    $diretorio = dirname($caminhoAbsoluto);
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }

    try {
        $foto->moveTo($caminhoAbsoluto);
        
        $payload = json_encode(['success' => true, 'caminho' => $caminhoRelativo]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        if (file_exists($caminhoAbsoluto)) @unlink($caminhoAbsoluto);
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
    /**
 * GET /v1/desembarque/secoes
 */
public function getSecoes(Request $request, Response $response): Response
{
    try {
        $stmt = $this->pdo->query("
            SELECT idsecao, descricao, sigla 
            FROM secao 
            WHERE inativo = 'N' 
            ORDER BY descricao
        ");
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
     * POST /v1/desembarque/confirmar
     */
    public function confirmarItem(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $idoc = (int)($input['idoc'] ?? 0);
        $iditem = (int)($input['iditem'] ?? 0);
        $quantidade = round((float)($input['quantidade'] ?? 0), 2);
        $lote = $input['lote'] ?? '';
        $validade = $input['validade'] ?? date('Y-m-d');
        $usuario = $input['usuario'] ?? 'SISTEMA';
        
        if ($idoc <= 0 || $iditem <= 0 || $quantidade <= 0 || empty($lote)) {
            $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
            return $response->withStatus(400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // 1. Verificar/Criar registro na aps_oc_conferencia
            $stmtCheck = $this->pdo->prepare("SELECT idoc FROM aps_oc_conferencia WHERE idoc = :idoc");
            $stmtCheck->execute(['idoc' => $idoc]);
            
            if (!$stmtCheck->fetch()) {
                $this->pdo->prepare("
                    INSERT INTO aps_oc_conferencia (idoc, idempresa, idfilial, horainicio, quantidadeitens, status, data, usuario, datahora, estacaotrabalho)
                    SELECT idoc, idempresa, idfilial, CURRENT_TIME, 
                           (SELECT COUNT(*) FROM oc_item WHERE idoc = oc.idoc),
                           2, CURRENT_DATE, :usuario, NOW(), '.'
                    FROM oc WHERE idoc = :idoc
                ")->execute(['idoc' => $idoc, 'usuario' => $usuario]);
            }
            
            // 2. Conferência do item
            $stmtItem = $this->pdo->prepare("
                SELECT quantidadeconferida, quantidadesaldo 
                FROM aps_oc_conferencia_item 
                WHERE idoc = :idoc AND iditem = :iditem
            ");
            $stmtItem->execute(['idoc' => $idoc, 'iditem' => $iditem]);
            $itemExistente = $stmtItem->fetch(PDO::FETCH_ASSOC);
            
            if ($itemExistente) {
                $this->pdo->prepare("
                    UPDATE aps_oc_conferencia_item 
                    SET quantidadeconferida = quantidadeconferida + :qtd,
                        quantidadesaldo = quantidadesaldo - :qtd2
                    WHERE idoc = :idoc AND iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'idoc' => $idoc, 'iditem' => $iditem]);
            } else {
                $this->pdo->prepare("
                    INSERT INTO aps_oc_conferencia_item 
                    (idoc, idocitem, idempresa, idfilial, iditem, quantidadeoriginal, quantidadeconferida, quantidadesaldo, codigolido, usuario, estacaotrabalho, fatoritem)
                    SELECT oi.idoc, oi.iditemoc, oi.idempresa, oi.idfilial, oi.iditem,
                           oi.qt, :qtd, oi.qt - :qtd2, oi.codigolido, :usuario, '.', 0
                    FROM oc_item oi
                    WHERE oi.idoc = :idoc AND oi.iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'idoc' => $idoc, 'iditem' => $iditem, 'usuario' => $usuario]);
            }
            
            // 3. Lote na aps_oc_conferencia_item_lote
            $stmtLote = $this->pdo->prepare("
                SELECT quantsaldo FROM aps_oc_conferencia_item_lote 
                WHERE idoc = :idoc AND iditem = :iditem AND quantsaldo > 0
            ");
            $stmtLote->execute(['idoc' => $idoc, 'iditem' => $iditem]);
            
            if ($stmtLote->fetch()) {
                $this->pdo->prepare("
                    UPDATE aps_oc_conferencia_item_lote 
                    SET quantidade = quantidade + :qtd,
                        quantsaldo = quantsaldo - :qtd2,
                        validade = :validade,
                        lote = :lote
                    WHERE idoc = :idoc AND iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'validade' => $validade, 'lote' => $lote, 'idoc' => $idoc, 'iditem' => $iditem]);
            } else {
                $this->pdo->prepare("
                    INSERT INTO aps_oc_conferencia_item_lote 
                    (idoc, idocitem, sequencial, idempresa, idfilial, iditem, quantidade, quantutilizado, quantsaldo, lote, complemento, validade)
                    SELECT oi.idoc, oi.iditemoc, 1, oi.idempresa, oi.idfilial, oi.iditem,
                           :qtd, 0, oi.qt - :qtd2, :lote, '.', :validade
                    FROM oc_item oi
                    WHERE oi.idoc = :idoc AND oi.iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'lote' => $lote, 'validade' => $validade, 'idoc' => $idoc, 'iditem' => $iditem]);
            }
            
            // 4. Lote na oc_item_lote
            $stmtOCLote = $this->pdo->prepare("
                SELECT quantsaldo FROM oc_item_lote 
                WHERE idoc = :idoc AND iditem = :iditem AND quantsaldo > 0
            ");
            $stmtOCLote->execute(['idoc' => $idoc, 'iditem' => $iditem]);
            
            if ($stmtOCLote->fetch()) {
                $this->pdo->prepare("
                    UPDATE oc_item_lote 
                    SET quantidade = quantidade + :qtd,
                        quantsaldo = quantsaldo - :qtd2,
                        validade = :validade,
                        lote = :lote
                    WHERE idoc = :idoc AND iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'validade' => $validade, 'lote' => $lote, 'idoc' => $idoc, 'iditem' => $iditem]);
            } else {
                $this->pdo->prepare("
                    INSERT INTO oc_item_lote 
                    (idoc, iditemoc, sequencial, idempresa, idfilial, iditem, quantidade, quantutilizado, quantsaldo, lote, complemento, validade)
                    SELECT oi.idoc, oi.iditemoc, 1, oi.idempresa, oi.idfilial, oi.iditem,
                           :qtd, 0, oi.qt - :qtd2, :lote, '.', :validade
                    FROM oc_item oi
                    WHERE oi.idoc = :idoc AND oi.iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'lote' => $lote, 'validade' => $validade, 'idoc' => $idoc, 'iditem' => $iditem]);
            }
            
// 5. Registrar endereço no lote_endereco e atualizar secao_enderecos
if (!empty($input['idsecao']) && !empty($input['idendereco'])) {
    $idsecao = (int)$input['idsecao'];
    $idendereco = (int)$input['idendereco'];
    
    // Inserir no lote_endereco
    $this->pdo->prepare("
        INSERT INTO lote_endereco (
            idfilial, iditem, lote, idendereco, situacaoendereco, 
            quantidade, idoc, idsecao, data_entrada, usuario, observacao
        )
        VALUES (
            :idfilial, :iditem, :lote, :idendereco, 1, 
            :qtd, :idoc, :idsecao, CURRENT_DATE, :usuario, :obs
        )
    ")->execute([
        'idfilial' => 1,
        'iditem' => $iditem,
        'lote' => $lote,
        'idendereco' => $idendereco,
        'qtd' => $quantidade,
        'idoc' => $idoc,
        'idsecao' => $idsecao,
        'usuario' => $usuario,
        'obs' => 'End: ' . ($input['endereco'] ?? 'N/A')
    ]);
    
    // Atualizar ocupado e saldo na secao_enderecos
    $this->pdo->prepare("
        UPDATE secao_enderecos 
        SET ocupado = ocupado + :qtd,
            saldo = capacidade - (ocupado + :qtd2)
        WHERE idsecao = :idsecao AND idendereco = :idendereco
    ")->execute([
        'qtd' => $quantidade,
        'qtd2' => $quantidade,
        'idsecao' => $idsecao,
        'idendereco' => $idendereco
    ]);
}
            $this->pdo->commit();
            
            $payload = json_encode(['success' => true]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    /**
 * GET /v1/desembarque/enderecos/{idsecao}
 * Lista endereços disponíveis de uma seção
 */
public function getEnderecos(Request $request, Response $response, array $args): Response
{
    $idsecao = (int)($args['idsecao'] ?? 0);
    
    if ($idsecao <= 0) {
        $response->getBody()->write(json_encode([]));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    try {
        $stmt = $this->pdo->prepare("
            SELECT 
                se.idendereco,
                se.linhasigla || se.colunasigla as sigla,
                se.linhasigla,
                se.colunasigla,
                se.capacidade,
                se.ocupado,
                se.saldo,
                (se.capacidade - se.ocupado) as disponivel
            FROM secao_enderecos se
            WHERE se.idsecao = :idsecao
              AND se.capacidade > se.ocupado
            ORDER BY se.linhas, se.colunas
        ");
        $stmt->execute(['idsecao' => $idsecao]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payload = json_encode($data ?: []);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
    
    /**
     * POST /v1/desembarque/finalizar/{idoc}
     */
    public function finalizarConferencia(Request $request, Response $response, array $args): Response
    {
        $idoc = (int)($args['idoc'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $usuario = $input['usuario'] ?? 'SISTEMA';
        
        try {
            $this->pdo->beginTransaction();
            
            $this->pdo->prepare("
                UPDATE aps_oc_conferencia 
                SET status = 3, 
                    horafim = CURRENT_TIME,
                    tempototal = CEIL(EXTRACT(EPOCH FROM (CURRENT_TIME - horainicio)) / 60.0)::int
                WHERE idoc = :idoc
            ")->execute(['idoc' => $idoc]);
            
            $this->pdo->prepare("
                INSERT INTO oc_operacao (idoc, idoperacao, idempresa, idfilial, acao, descricao, datahora, usuario, estacaologistica)
                SELECT :idoc, nextval('gi_OC_operacao'), 1, 1, 7, 
                       'Conferência na expedição via PORTAL', NOW(), :usuario, 'DOX'
            ")->execute(['idoc' => $idoc, 'usuario' => $usuario]);
            
            $this->pdo->commit();
            
            $payload = json_encode(['success' => true]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
}