<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Nutricional\Services\EnderecoItemService;

class DesembarqueController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    private function validarAcesso(Request $request, Response $response): ?Response
    {
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];
        $permitido = (bool)($user['is_admin'] ?? false)
            || in_array('admin', $permissoes, true)
            || in_array('desembarque', $permissoes, true);

        return $permitido
            ? null
            : $this->json($response, ['error' => 'Acesso não autorizado ao módulo Desembarque'], 403);
    }
    
    
    private function sincronizarLoteConferencia(
        string $tabela,
        string $colunaItemOc,
        int $idoc,
        int $iditem,
        float $quantidade,
        string $lote,
        string $validade,
        bool $somarSaldoExistente
    ): void {
        // Apenas tabelas internas conhecidas sao permitidas no identificador SQL
        if (!in_array($tabela, ['aps_oc_conferencia_item_lote', 'oc_item_lote'], true)
            || !in_array($colunaItemOc, ['idocitem', 'iditemoc'], true)) {
            throw new \InvalidArgumentException('Tabela ou coluna de lote invalida');
        }

        $stmtLote = $this->pdo->prepare("
            SELECT quantsaldo FROM {$tabela}
            WHERE idoc = :idoc AND iditem = :iditem
            FOR UPDATE
        ");
        $stmtLote->execute(['idoc' => $idoc, 'iditem' => $iditem]);

        if ($stmtLote->fetch()) {
            if (!$somarSaldoExistente) {
                $this->pdo->prepare("
                    UPDATE {$tabela}
                    SET quantidade = :qtd,
                        quantsaldo = GREATEST(0, (SELECT qt FROM oc_item WHERE idoc = :idoc_qt AND iditem = :iditem_qt) - :qtd2),
                        validade = :validade,
                        lote = :lote
                    WHERE idoc = :idoc AND iditem = :iditem
                ")->execute([
                    'qtd' => $quantidade,
                    'idoc_qt' => $idoc,
                    'iditem_qt' => $iditem,
                    'qtd2' => $quantidade,
                    'validade' => $validade,
                    'lote' => $lote,
                    'idoc' => $idoc,
                    'iditem' => $iditem,
                ]);
                return;
            }

            $this->pdo->prepare("
                UPDATE {$tabela}
                SET quantidade = quantidade + :qtd,
                    quantsaldo = GREATEST(0, quantsaldo - :qtd2),
                    validade = :validade,
                    lote = :lote
                WHERE idoc = :idoc AND iditem = :iditem
            ")->execute([
                'qtd' => $quantidade,
                'qtd2' => $quantidade,
                'validade' => $validade,
                'lote' => $lote,
                'idoc' => $idoc,
                'iditem' => $iditem,
            ]);
            return;
        }

        $this->pdo->prepare("
            INSERT INTO {$tabela}
            (idoc, {$colunaItemOc}, sequencial, idempresa, idfilial, iditem, quantidade, quantutilizado, quantsaldo, lote, complemento, validade)
            SELECT oi.idoc, oi.iditemoc, 1, oi.idempresa, oi.idfilial, oi.iditem,
                   :qtd, 0, GREATEST(0, oi.qt - :qtd2), :lote, '.', :validade
            FROM oc_item oi
            WHERE oi.idoc = :idoc AND oi.iditem = :iditem
        ")->execute([
            'qtd' => $quantidade,
            'qtd2' => $quantidade,
            'lote' => $lote,
            'validade' => $validade,
            'idoc' => $idoc,
            'iditem' => $iditem,
        ]);
    }

    private function isConferenciaXmlConcluida(int $idoc): bool
    {
        if ($idoc <= 0) {
            return false;
        }

        $sql = "
            SELECT 1
            FROM oc
            LEFT JOIN aps_oc_conferencia a ON a.idoc = oc.idoc
            WHERE oc.idoc = :idoc
              AND (
                  COALESCE(a.conferido_portal, 'N') = 'S'
                  OR EXISTS (
                      SELECT 1 FROM oc_operacao op
                      WHERE op.idoc = oc.idoc
                        AND op.acao = 7
                        AND (
                            op.estacaologistica = 'PORTAL'
                            OR op.usuario = 'PORTAL'
                            OR op.descricao ILIKE '%Conferência finalizada via Portal%'
                            OR op.descricao ILIKE '%conferência XML%'
                        )
                  )
              )
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['idoc' => $idoc]);
        return (bool)$stmt->fetchColumn();
    }

/**
 * GET /v1/desembarque/ordens-compra
 * Lista OCs disponíveis para conferência (filial 1 e 6, somente após Conferência XML concluída)
 */
public function getOrdensCompra(Request $request, Response $response): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    try {
        $sql = "
                 SELECT DISTINCT 
    oc.idoc, 
    TO_CHAR(oc.data, 'DD/MM/YYYY') as data_oc,
    f.razao || ' | ' || COALESCE(f.fantasia, '') as fornecedor,
    oc.valortotal,
    COALESCE(a.status, 0) as status_conferencia,
    'S' as xml_conferido,
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
  AND (oc.data >= CURRENT_DATE - INTERVAL '120 days' OR oc.dataprevisao >= CURRENT_DATE - INTERVAL '30 days')
  AND (a.status IS NULL OR a.status <> 3)
  AND (
      COALESCE(a.conferido_portal, 'N') = 'S'
      OR EXISTS (
          SELECT 1 FROM oc_operacao op
          WHERE op.idoc = oc.idoc
            AND op.acao = 7
            AND (
                op.estacaologistica = 'PORTAL'
                OR op.usuario = 'PORTAL'
                OR op.descricao ILIKE '%Conferência finalizada via Portal%'
                OR op.descricao ILIKE '%conferência XML%'
            )
      )
  )
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
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idoc = (int)($args['idoc'] ?? 0);
        
        if ($idoc <= 0) {
            $response->getBody()->write(json_encode([]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        if (!$this->isConferenciaXmlConcluida($idoc)) {
            return $this->json($response, [
                'error' => "A Ordem de Compra #{$idoc} ainda não passou pela Conferência XML. Conclua a Conferência XML da Nota Fiscal antes de iniciar o desembarque."
            ], 409);
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
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idoc = (int)($args['idoc'] ?? 0);
        $codigo = trim((string)($request->getQueryParams()['codigo'] ?? ($_GET['codigo'] ?? '')));
        
        if ($idoc <= 0 || $codigo === '') {
            $response->getBody()->write(json_encode(['error' => 'Parâmetros inválidos']));
            return $response->withStatus(400);
        }

        if (!$this->isConferenciaXmlConcluida($idoc)) {
            return $this->json($response, [
                'error' => "A Ordem de Compra #{$idoc} ainda não passou pela Conferência XML. Conclua a Conferência XML da Nota Fiscal antes de iniciar o desembarque."
            ], 409);
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
                      OR UPPER(TRIM(i.referencia)) = UPPER(:codigo_ref)
                      OR (
                          LTRIM(UPPER(:codigo_ref_check), '0') <> ''
                          AND LTRIM(UPPER(TRIM(i.referencia)), '0') = LTRIM(UPPER(:codigo_ref_sem_zero), '0')
                      )
                      OR oi.iditem IN (
                          SELECT iditem FROM codigo_barra WHERE TRIM(idbarra) = :codigo2 AND inativo = 'N'
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
            $stmt->execute([
                'idoc' => $idoc,
                'codigo' => $codigo,
                'codigo_ref' => $codigo,
                'codigo_ref_check' => $codigo,
                'codigo_ref_sem_zero' => $codigo,
                'codigo2' => $codigo,
            ]);
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
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

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
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

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
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $idoc = (int)($input['idoc'] ?? 0);
        $iditem = (int)($input['iditem'] ?? 0);
        $quantidade = round((float)($input['quantidade'] ?? 0), 2);
        $lote = $input['lote'] ?? '';
        $validade = $input['validade'] ?? date('Y-m-d');
        $usuario = (string)(($request->getAttribute('user') ?? [])['username'] ?? '');
        $idsecao = (int)($input['idsecao'] ?? 0);
        $idendereco = (int)($input['idendereco'] ?? 0);
        
        // Validação de dados essenciais (seção e endereço são opcionais no modo rápido DOX)
        if ($idoc <= 0 || $iditem <= 0 || $quantidade <= 0 || empty($lote) || empty($usuario)) {
            $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
            return $response->withStatus(400);
        }

        if (!$this->isConferenciaXmlConcluida($idoc)) {
            return $this->json($response, [
                'error' => "A Ordem de Compra #{$idoc} ainda não passou pela Conferência XML. Conclua a Conferência XML da Nota Fiscal antes de iniciar o desembarque."
            ], 409);
        }
        
        try {
            $this->pdo->beginTransaction();

            $stmtOc = $this->pdo->prepare("
                SELECT idoc
                FROM oc
                WHERE idoc = ? AND status = 1 AND idfilial IN (1, 6)
                FOR UPDATE
            ");
            $stmtOc->execute([$idoc]);
            if (!$stmtOc->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Ordem de compra não disponível para conferência'], 409);
            }

            $stmtOcItem = $this->pdo->prepare("
                SELECT qt, idfilial
                FROM oc_item
                WHERE idoc = ? AND iditem = ?
                FOR UPDATE
            ");
            $stmtOcItem->execute([$idoc, $iditem]);
            $ocItem = $stmtOcItem->fetch(PDO::FETCH_ASSOC);
            if (!$ocItem) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Item não encontrado na ordem de compra'], 404);
            }

            // Validação de endereço WMS (apenas se informado pelo conferente)
            if ($idsecao > 0 && $idendereco > 0) {
                $stmtEndereco = $this->pdo->prepare("
                    SELECT capacidade - ocupado AS disponivel
                    FROM secao_enderecos
                    WHERE idsecao = ? AND idendereco = ?
                    FOR UPDATE
                ");
                $stmtEndereco->execute([$idsecao, $idendereco]);
                $capacidadeDisponivel = $stmtEndereco->fetchColumn();
                if ($capacidadeDisponivel === false) {
                    $this->pdo->rollBack();
                    return $this->json($response, ['error' => 'Endereço não encontrado'], 404);
                }
                if ($quantidade > (float)$capacidadeDisponivel + 0.01) {
                    $this->pdo->rollBack();
                    return $this->json($response, [
                        'error' => 'Quantidade excede a capacidade disponível do endereço',
                        'capacidade_disponivel' => round((float)$capacidadeDisponivel, 2),
                    ], 422);
                }
            }
            
            // 1. Verificar/Criar/Atualizar registro na aps_oc_conferencia
            $stmtCheck = $this->pdo->prepare("SELECT status FROM aps_oc_conferencia WHERE idoc = :idoc FOR UPDATE");
            $stmtCheck->execute(['idoc' => $idoc]);
            $statusAtualConf = $stmtCheck->fetchColumn();
            
            if ($statusAtualConf === false) {
                $this->pdo->prepare("
                    INSERT INTO aps_oc_conferencia (idoc, idempresa, idfilial, horainicio, quantidadeitens, status, data, usuario, datahora, estacaotrabalho, conferido_portal)
                    SELECT idoc, idempresa, idfilial, CURRENT_TIME, 
                           (SELECT COUNT(*) FROM oc_item WHERE idoc = oc.idoc),
                           2, CURRENT_DATE, :usuario, NOW(), '.', 'S'
                    FROM oc WHERE idoc = :idoc
                ")->execute(['idoc' => $idoc, 'usuario' => $usuario]);
            } elseif ((int)$statusAtualConf === 1) {
                $this->pdo->prepare("
                    UPDATE aps_oc_conferencia
                    SET status = 2,
                        horainicio = CURRENT_TIME,
                        data = CURRENT_DATE,
                        usuario = :usuario,
                        datahora = NOW(),
                        quantidadeitens = (SELECT COUNT(*) FROM oc_item WHERE idoc = :idoc_itens),
                        conferido_portal = 'S'
                    WHERE idoc = :idoc
                ")->execute([
                    'usuario' => $usuario,
                    'idoc_itens' => $idoc,
                    'idoc' => $idoc,
                ]);
            }
            
            // 2. Conferência do item
            $stmtItem = $this->pdo->prepare("
                SELECT quantidadeconferida, quantidadesaldo 
                FROM aps_oc_conferencia_item 
                WHERE idoc = :idoc AND iditem = :iditem
                FOR UPDATE
            ");
            $stmtItem->execute(['idoc' => $idoc, 'iditem' => $iditem]);
            $itemExistente = $stmtItem->fetch(PDO::FETCH_ASSOC);

            $quantidadeConferida = (float)($itemExistente['quantidadeconferida'] ?? 0);
            $saldoPendente = max(0, round((float)$ocItem['qt'] - $quantidadeConferida, 2));
            if ($saldoPendente <= 0.01) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Item já foi totalmente conferido'], 409);
            }
            if ($quantidade > $saldoPendente + 0.01) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Quantidade informada é maior que o saldo pendente',
                    'saldo_pendente' => $saldoPendente,
                ], 422);
            }
            
            if ($itemExistente) {
                $this->pdo->prepare("
                    UPDATE aps_oc_conferencia_item 
                    SET quantidadeconferida = quantidadeconferida + :qtd,
                        quantidadesaldo = GREATEST(0, quantidadesaldo - :qtd2)
                    WHERE idoc = :idoc AND iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'idoc' => $idoc, 'iditem' => $iditem]);
            } else {
                $this->pdo->prepare("
                    INSERT INTO aps_oc_conferencia_item 
                    (idoc, idocitem, idempresa, idfilial, iditem, quantidadeoriginal, quantidadeconferida, quantidadesaldo, codigolido, usuario, estacaotrabalho, fatoritem)
                    SELECT oi.idoc, oi.iditemoc, oi.idempresa, oi.idfilial, oi.iditem,
                           oi.qt, :qtd, GREATEST(0, oi.qt - :qtd2), oi.codigolido, :usuario, '.', 0
                    FROM oc_item oi
                    WHERE oi.idoc = :idoc AND oi.iditem = :iditem
                ")->execute(['qtd' => $quantidade, 'qtd2' => $quantidade, 'idoc' => $idoc, 'iditem' => $iditem, 'usuario' => $usuario]);
            }
            
            // 3. Lote na aps_oc_conferencia_item_lote
            $this->sincronizarLoteConferencia(
                'aps_oc_conferencia_item_lote',
                'idocitem',
                $idoc,
                $iditem,
                $quantidade,
                $lote,
                $validade,
                (bool)$itemExistente
            );

            // 4. Lote na oc_item_lote
            $this->sincronizarLoteConferencia(
                'oc_item_lote',
                'iditemoc',
                $idoc,
                $iditem,
                $quantidade,
                $lote,
                $validade,
                (bool)$itemExistente
            );
            
// 5. Registrar endereço no lote_endereco e atualizar secao_enderecos
if (!empty($input['idsecao']) && !empty($input['idendereco'])) {
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
        ON CONFLICT (idfilial, iditem, lote, idendereco) DO UPDATE
        SET quantidade = lote_endereco.quantidade + EXCLUDED.quantidade,
            idoc = EXCLUDED.idoc,
            usuario = EXCLUDED.usuario,
            observacao = EXCLUDED.observacao
    ")->execute([
        'idfilial' => (int)$ocItem['idfilial'],
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

            // 6. Verificar se todos os itens da OC foram 100% conferidos (conclusão automática estilo DOX)
            $stmtPendencias = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM oc_item item_oc
                WHERE item_oc.idoc = :idoc
                  AND COALESCE((
                      SELECT conferencia.quantidadeconferida
                      FROM aps_oc_conferencia_item conferencia
                      WHERE conferencia.idoc = item_oc.idoc
                        AND conferencia.iditem = item_oc.iditem
                  ), 0) < item_oc.qt - 0.01
            ");
            $stmtPendencias->execute(['idoc' => $idoc]);
            $pendentes = (int)$stmtPendencias->fetchColumn();
            $ocFinalizada = false;

            if ($pendentes === 0) {
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

                $ocFinalizada = true;
            }

            $this->pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'oc_finalizada' => $ocFinalizada,
                'pendentes' => $pendentes
            ]);
            
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    /**
     * GET /v1/desembarque/sugestao-endereco/{iditem}
     * Onde o item já está guardado e a seção padrão dele, para o conferente endereçar sem procurar.
     */
    public function getSugestaoEndereco(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $iditem = (int)($args['iditem'] ?? 0);
        if ($iditem <= 0) {
            return $this->json($response, ['error' => 'Item inválido'], 422);
        }

        try {
            $svc = new EnderecoItemService($this->pdo);
            $existentes = array_map(function ($e) {
                $stmt = $this->pdo->prepare("SELECT capacidade - ocupado FROM secao_enderecos WHERE idsecao = ? AND idendereco = ?");
                $stmt->execute([$e['idsecao'], $e['idendereco']]);
                $e['disponivel'] = round((float)$stmt->fetchColumn(), 2);
                return $e;
            }, $svc->porItem($iditem));

            return $this->json($response, [
                'enderecos_atuais' => $existentes,
                'secao_padrao' => $svc->secaoPadrao($iditem),
            ]);
        } catch (\Throwable $e) {
            error_log('[Desembarque] sugestao-endereco: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Erro ao consultar endereços do item'], 500);
        }
    }

    /**
 * GET /v1/desembarque/enderecos/{idsecao}
 * Lista endereços disponíveis de uma seção
 */
public function getEnderecos(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

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
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idoc = (int)($args['idoc'] ?? 0);
        $usuario = (string)(($request->getAttribute('user') ?? [])['username'] ?? '');

        if ($idoc <= 0 || empty($usuario)) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        if (!$this->isConferenciaXmlConcluida($idoc)) {
            return $this->json($response, [
                'error' => "A Ordem de Compra #{$idoc} ainda não passou pela Conferência XML. Conclua a Conferência XML da Nota Fiscal antes de iniciar o desembarque."
            ], 409);
        }
        
        try {
            $this->pdo->beginTransaction();

            $stmtOc = $this->pdo->prepare("
                SELECT idoc
                FROM oc
                WHERE idoc = ? AND status = 1 AND idfilial IN (1, 6)
                FOR UPDATE
            ");
            $stmtOc->execute([$idoc]);
            if (!$stmtOc->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Ordem de compra não disponível para finalização'], 409);
            }

            $stmtConferencia = $this->pdo->prepare("
                SELECT status
                FROM aps_oc_conferencia
                WHERE idoc = ?
                FOR UPDATE
            ");
            $stmtConferencia->execute([$idoc]);
            $statusConferencia = $stmtConferencia->fetchColumn();
            if ($statusConferencia === false) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Conferência ainda não foi iniciada'], 409);
            }
            if ((int)$statusConferencia === 3) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Conferência já foi finalizada'], 409);
            }

            $stmtLockItens = $this->pdo->prepare("SELECT iditemoc FROM oc_item WHERE idoc = ? FOR UPDATE");
            $stmtLockItens->execute([$idoc]);
            if (!$stmtLockItens->fetchAll()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Ordem de compra não possui itens'], 409);
            }

            $stmtPendencias = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM oc_item item_oc
                WHERE item_oc.idoc = :idoc
                  AND COALESCE((
                      SELECT conferencia.quantidadeconferida
                      FROM aps_oc_conferencia_item conferencia
                      WHERE conferencia.idoc = item_oc.idoc
                        AND conferencia.iditem = item_oc.iditem
                  ), 0) < item_oc.qt - 0.01
            ");
            $stmtPendencias->execute(['idoc' => $idoc]);
            $itensPendentes = (int)$stmtPendencias->fetchColumn();
            if ($itensPendentes > 0) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Existem itens pendentes de conferência',
                    'itens_pendentes' => $itensPendentes,
                ], 409);
            }
            
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