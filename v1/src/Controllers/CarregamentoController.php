<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CarregamentoController
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
            || in_array('carregamento', $permissoes, true);

        return $permitido
            ? null
            : $this->json($response, ['error' => 'Acesso não autorizado ao módulo Carregamento'], 403);
    }
/**
 * GET /v1/carregamento/embarques
 * Lista embarques que já têm NF gerada e separação concluída, prontos para carregar
 */
public function getEmbarques(Request $request, Response $response): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    // Log 1: Método foi chamado
    error_log('[Carregamento] getEmbarques chamado');
    
    try {
        $sql = "SELECT DISTINCT 
                    ep.idembarque, 
                    ep.observacao as rota, 
                    ep.placa,
                    COALESCE(s.status_atual, 'PENDENTE') as status_logistico
                FROM embarque_pedido ep
                LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                WHERE ep.pex_conferido = 'N' 
                  AND ep.gerou_nf = 'S'
                                    AND ep.pex_embarque_pronto = 'S'
                  AND ep.idfilial IN (1,6)
                  AND ep.data >= (CURRENT_DATE - INTERVAL '30 days')
                ORDER BY ep.idembarque DESC";
        
        // Log 2: Query SQL
        error_log('[Carregamento] Query: ' . $sql);
        
        $stmt = $this->pdo->prepare($sql);
        
        // Log 3: Tentando executar
        error_log('[Carregamento] Executando query...');
        $stmt->execute();
        
        // Log 4: Buscando resultados
        error_log('[Carregamento] Buscando resultados...');
        $data = $stmt->fetchAll();
        
        // Log 5: Resultados encontrados
        error_log('[Carregamento] Encontrados ' . count($data) . ' registros');
        
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
            
    } catch (\Exception $e) {
        // Log 6: Erro capturado
        error_log('[Carregamento] ERRO: ' . $e->getMessage());
        error_log('[Carregamento] Stack trace: ' . $e->getTraceAsString());
        
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
    
    /**
     * GET /v1/carregamento/itens/{idembarque}
     * Lista itens já separados, prontos para serem carregados
     */
public function getItens(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $idembarque = $args['idembarque'] ?? 0;
    $ordem = $request->getQueryParams()['ordem'] ?? 'ASC';
    
    if (empty($idembarque)) {
        $response->getBody()->write(json_encode([]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    try {
        $sql = "SELECT 
                    COALESCE((SELECT STRING_AGG(idbarra, ',') FROM codigo_barra WHERE iditem = pi.iditem), 'SEM_BARRA') AS todos_codigos,
                    (SELECT idbarra FROM codigo_barra WHERE iditem = pi.iditem AND principal = 'S' LIMIT 1) AS cod_barras,
                    i.referencia, 
                    pi.iditem AS cod_item, 
                    i.descricao AS nome_item, 
                    i.descricao,
                    i.path_foto_master AS foto,
                    i.path_foto_master,
                    i.idsecao,
                    SUM(pi.qt) AS quant_embarque,
                    COALESCE((
                        SELECT SUM(qt_separada) FROM pedido_item_logistica 
                        WHERE idembarque = :emb AND iditem = pi.iditem
                    ), 0) AS ja_separado,
                    COALESCE((
                        SELECT SUM(qt_carregada) FROM pedido_item_carregamento 
                        WHERE idembarque = :emb AND iditem = pi.iditem
                    ), 0) AS ja_carregado
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                JOIN item i ON i.iditem = pi.iditem
                WHERE p.idembarque = :emb AND pi.ativo = 'S'
                GROUP BY i.referencia, pi.iditem, i.descricao, i.path_foto_master, i.idsecao
                ORDER BY i.idsecao " . ($ordem === 'DESC' ? 'DESC' : 'ASC');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['emb' => $idembarque]);
        $itens = $stmt->fetchAll();
        
        foreach ($itens as &$item) {
            // Extrai o primeiro código da lista para exibição visual no card
            $lista = explode(',', $item['todos_codigos']);
            $item['cod_barras'] = $lista[0];
            
            $sep = (float)$item['ja_separado'];
            $total = (float)$item['quant_embarque'];
            $item['pode_carregar'] = ($sep >= ($total - 0.01)) ? 1 : 0;
            
            $item['quant_embarque'] = round($total, 4);
            $item['ja_separado'] = round($sep, 4);
            $item['ja_carregado'] = round((float)$item['ja_carregado'], 4);
        }
        
        $payload = json_encode($itens ?: []);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
            
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
/**
 * GET /v1/carregamento/resumo/{idembarque}
 * Retorna resumo do embarque (total de itens, pedidos e peso bruto)
 */
public function getResumo(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $idembarque = (int)($args['idembarque'] ?? 0);
    
    if ($idembarque <= 0) {
        $response->getBody()->write(json_encode(['error' => 'ID do embarque inválido']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    try {
        $sql = "SELECT 
                    COUNT(DISTINCT pi.iditem) as total_itens,
                    COUNT(DISTINCT p.idpedido) as qt_pedido,
                    COALESCE(SUM(pi.qt * i.pesobruto), 0) as totalpesobruto
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                JOIN item i ON i.iditem = pi.iditem
                WHERE p.idembarque = ? AND pi.ativo = 'S'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idembarque]);
        $data = $stmt->fetch();
        
        $resumo = [
            'total_itens' => (int)($data['total_itens'] ?? 0),
            'qt_pedido' => (int)($data['qt_pedido'] ?? 0),
            'totalpesobruto' => (float)($data['totalpesobruto'] ?? 0)
        ];
        
        $payload = json_encode($resumo);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}
    
  /**
 * POST /v1/carregamento/confirmar
 * Confirma a quantidade carregada - CRIA UM REGISTRO POR CARGA e RETORNA O ID
 */
public function confirmarItem(Request $request, Response $response): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    $iditem = (int)($input['iditem'] ?? 0);
    $idembarque = (int)($input['idembarque'] ?? 0);
    $qt_lida = round((float)($input['qtd'] ?? 0), 4);
    $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
    $doca = $input['doca'] ?? null;
    
    if ($iditem <= 0 || $idembarque <= 0 || $qt_lida <= 0 || $idusuario <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    try {
        $this->pdo->beginTransaction();

        $stmtEmbarque = $this->pdo->prepare("
            SELECT idembarque
            FROM embarque_pedido
            WHERE idembarque = ?
              AND pex_conferido = 'N'
              AND gerou_nf = 'S'
              AND pex_embarque_pronto = 'S'
              AND idfilial IN (1, 6)
            FOR UPDATE
        ");
        $stmtEmbarque->execute([$idembarque]);
        if (!$stmtEmbarque->fetchColumn()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Embarque não disponível para carregamento'], 409);
        }

        $stmtLockItens = $this->pdo->prepare("
            SELECT pi.iditempedido
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
            FOR UPDATE OF pi
        ");
        $stmtLockItens->execute([$idembarque, $iditem]);
        if (!$stmtLockItens->fetchAll()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item não encontrado no embarque'], 404);
        }

        $stmtLockCargas = $this->pdo->prepare("
            SELECT id
            FROM pedido_item_carregamento
            WHERE idembarque = ? AND iditem = ?
            FOR UPDATE
        ");
        $stmtLockCargas->execute([$idembarque, $iditem]);
        $stmtLockCargas->fetchAll();
        
        // Busca os pedidos que contém este item
        $stmt = $this->pdo->prepare("
            SELECT pi.idpedido, pi.iditempedido, pi.qt, 
                   COALESCE(l.qt_separada, 0) as qt_separada, 
                   COALESCE(SUM(c.qt_carregada), 0) as ja_carregado_total
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            LEFT JOIN pedido_item_logistica l ON l.idpedido = pi.idpedido 
                 AND l.iditempedido = pi.iditempedido 
                 AND l.idembarque = p.idembarque
            LEFT JOIN pedido_item_carregamento c ON c.idpedido = pi.idpedido 
                 AND c.iditempedido = pi.iditempedido 
                 AND c.idembarque = p.idembarque
            WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
            GROUP BY pi.idpedido, pi.iditempedido, pi.qt, l.qt_separada
            ORDER BY pi.idpedido ASC
        ");
        $stmt->execute([$idembarque, $iditem]);
        $pedidos = $stmt->fetchAll();
        
        if (!$pedidos) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item não encontrado no embarque'], 404);
        }

        $quantidadePendente = array_reduce($pedidos, function ($total, $pedido) {
            return $total + max(0, round(
                (float)$pedido['qt_separada'] - (float)$pedido['ja_carregado_total'],
                4
            ));
        }, 0.0);

        if ($quantidadePendente <= 0.0001) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item já foi totalmente carregado'], 409);
        }
        if ($qt_lida > $quantidadePendente + 0.0001) {
            $this->pdo->rollBack();
            return $this->json($response, [
                'error' => 'Quantidade informada é maior que o saldo separado pendente',
                'saldo_pendente' => round($quantidadePendente, 4),
            ], 422);
        }
        
        $resto = $qt_lida;
        $idsCarregamentoCriados = [];
        
        foreach ($pedidos as $p) {
            if ($resto <= 0.0001) break;
            
            $ja_carregado = (float)$p['ja_carregado_total'];
            $qt_separada = (float)$p['qt_separada'];
            $falta_no_caminhao = round($qt_separada - $ja_carregado, 4);
            
            if ($falta_no_caminhao <= 0.0001) continue;
            
            $baixar = min($resto, $falta_no_caminhao);
            
            // 🔥 INSERE UM NOVO REGISTRO (sem ON CONFLICT)
            $sqlInsert = "
                INSERT INTO pedido_item_carregamento 
                (idpedido, iditempedido, iditem, idembarque, qt_carregada, id_conferente, data_carregamento, doca)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                RETURNING id
            ";
            $insert = $this->pdo->prepare($sqlInsert);
            $insert->execute([
                (int)$p['idpedido'],
                (int)$p['iditempedido'],
                $iditem,
                $idembarque,
                $baixar,
                $idusuario,
                $doca
            ]);
            
            $resultInsert = $insert->fetch();
            $idCarregamento = $resultInsert['id'];
            $idsCarregamentoCriados[] = $idCarregamento;
            
            $resto = round($resto - $baixar, 4);
        }
        
        $this->pdo->commit();
        
        // 🔥 RETORNA O ID DO PRIMEIRO REGISTRO CRIADO
        $payload = json_encode([
            'success' => true, 
            'doca' => $doca,
            'id_carregamento' => $idsCarregamentoCriados[0] ?? null,
            'ids_carregamentos' => $idsCarregamentoCriados,
            'quantidade_registrada' => round($qt_lida - $resto, 4)
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
    
/**
 * DELETE /v1/carregamento/estornar/{iditem}/{idembarque}
 * Estorna TODOS os carregamentos de um item
 */
public function estornarItem(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $iditem = (int)($args['iditem'] ?? 0);
    $idembarque = (int)($args['idembarque'] ?? 0);

    if ($iditem <= 0 || $idembarque <= 0) {
        return $this->json($response, ['error' => 'Dados inválidos'], 400);
    }
    
    try {
        $this->pdo->beginTransaction();

        $lock = $this->pdo->prepare("SELECT idembarque FROM embarque_pedido WHERE idembarque = ? AND pex_conferido = 'N' FOR UPDATE");
        $lock->execute([$idembarque]);
        if (!$lock->fetchColumn()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Embarque não disponível para estorno'], 409);
        }
        
        // Buscar fotos para deletar
        $stmt = $this->pdo->prepare("
            SELECT path_foto_conferencia FROM pedido_item_carregamento 
            WHERE iditem = ? AND idembarque = ?
            FOR UPDATE
        ");
        $stmt->execute([$iditem, $idembarque]);
        $fotos = $stmt->fetchAll();
        if (!$fotos) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item carregado não encontrado'], 404);
        }
        
        $sql = "DELETE FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$iditem, $idembarque]);

        $this->pdo->prepare("DELETE FROM carregamento_fotos WHERE iditem = ? AND idembarque = ?")
            ->execute([$iditem, $idembarque]);
        
        $this->pdo->commit();

        foreach ($fotos as $foto) {
            if (!empty($foto['path_foto_conferencia'])) {
                $caminho = __DIR__ . '/../../../' . $foto['path_foto_conferencia'];
                if (is_file($caminho)) @unlink($caminho);
            }
        }
        
        return $this->json($response, ['success' => true]);
        
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
    
    /**
     * POST /v1/carregamento/finalizar/{idembarque}
     * Marca o embarque como carregado
     */
    public function finalizarEmbarque(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idembarque = (int)$args['idembarque'];
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);

        if ($idembarque <= 0 || $idusuario <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }
        
        try {
            $this->pdo->beginTransaction();

            $stmtEmbarque = $this->pdo->prepare("
                SELECT idembarque
                FROM embarque_pedido
                WHERE idembarque = ?
                  AND pex_conferido = 'N'
                  AND gerou_nf = 'S'
                  AND pex_embarque_pronto = 'S'
                  AND idfilial IN (1, 6)
                FOR UPDATE
            ");
            $stmtEmbarque->execute([$idembarque]);
            if (!$stmtEmbarque->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque não disponível para finalização'], 409);
            }

            $stmtLockItens = $this->pdo->prepare("
                SELECT pi.iditempedido
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idembarque = ? AND pi.ativo = 'S'
                FOR UPDATE OF pi
            ");
            $stmtLockItens->execute([$idembarque]);
            if (!$stmtLockItens->fetchAll()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque não possui itens ativos'], 409);
            }

            $stmtLockCargas = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento WHERE idembarque = ? FOR UPDATE
            ");
            $stmtLockCargas->execute([$idembarque]);
            $stmtLockCargas->fetchAll();

            $stmtPendencias = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idembarque = :idembarque
                  AND pi.ativo = 'S'
                  AND COALESCE((
                      SELECT SUM(carga.qt_carregada)
                      FROM pedido_item_carregamento carga
                      WHERE carga.idpedido = pi.idpedido
                        AND carga.iditempedido = pi.iditempedido
                        AND carga.iditem = pi.iditem
                        AND carga.idembarque = :idembarque_carga
                  ), 0) < pi.qt - 0.0001
            ");
            $stmtPendencias->execute([
                'idembarque' => $idembarque,
                'idembarque_carga' => $idembarque,
            ]);
            $itensPendentes = (int)$stmtPendencias->fetchColumn();
            if ($itensPendentes > 0) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Existem itens pendentes de carregamento',
                    'itens_pendentes' => $itensPendentes,
                ], 409);
            }

            $stmtFotos = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM pedido_item_carregamento
                WHERE idembarque = ?
                  AND (path_foto_conferencia IS NULL OR path_foto_conferencia = '')
            ");
            $stmtFotos->execute([$idembarque]);
            $fotosPendentes = (int)$stmtFotos->fetchColumn();
            if ($fotosPendentes > 0) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Existem carregamentos sem foto de conferência',
                    'fotos_pendentes' => $fotosPendentes,
                ], 409);
            }
            
            // Atualiza status
            $stmt = $this->pdo->prepare("
                INSERT INTO embarque_status_log (idembarque, status_atual, data_fim, idusuario)
                VALUES (:emb, 'CARREGADO', NOW(), :user)
                ON CONFLICT (idembarque) 
                DO UPDATE SET 
                    status_atual = 'CARREGADO', 
                    data_fim = NOW(),
                    idusuario = EXCLUDED.idusuario
            ");
            $stmt->execute(['emb' => $idembarque, 'user' => $idusuario]);
            
            // Atualiza flags
            $this->pdo->prepare("
                UPDATE embarque_pedido 
                SET pex_conferido = 'S', data_carregamento = NOW(), pex_embarque_carregamento = 'S' 
                WHERE idembarque = ?
            ")->execute([$idembarque]);
            
            $this->pdo->commit();
            
            $payload = json_encode(['success' => true]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

 
/**
 * GET /v1/carregamento/fotos/{idembarque}
 * Lista todas as fotos de um embarque (para auditoria)
 */
public function getFotos(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $idembarque = (int)($args['idembarque'] ?? 0);
    
    if ($idembarque <= 0) {
        $response->getBody()->write(json_encode(['error' => 'ID do embarque inválido']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $this->pdo->prepare("
            SELECT cf.*, i.descricao as nome_item, i.referencia, u.username as nome_usuario
            FROM carregamento_fotos cf
            JOIN item i ON i.iditem = cf.iditem
            LEFT JOIN usuario u ON u.idcliforemp = cf.idusuario
            WHERE cf.idembarque = ?
            ORDER BY cf.data_hora DESC
        ");
        $stmt->execute([$idembarque]);
        $fotos = $stmt->fetchAll();

        $payload = json_encode($fotos ?: []);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}

/**
 * GET /v1/carregamento/foto/{idfoto}
 */
public function getFoto(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $idfoto = (int)($args['idfoto'] ?? 0);
    
    try {
        // Buscar na tabela carregamento_fotos (fallback)
        $stmt = $this->pdo->prepare("SELECT caminho_foto FROM carregamento_fotos WHERE id = ?");
        $stmt->execute([$idfoto]);
        $foto = $stmt->fetch();
        
        if ($foto && $foto['caminho_foto']) {
            $caminho = __DIR__ . '/../../../' . $foto['caminho_foto'];
            if (file_exists($caminho)) {
                $response->getBody()->write(file_get_contents($caminho));
                return $response
                    ->withHeader('Content-Type', 'image/jpeg')
                    ->withHeader('Cache-Control', 'public, max-age=3600');
            }
        }
        
        return $response->withStatus(404);
    } catch (\Exception $e) {
        return $response->withStatus(500);
    }
}
/**
 * POST /v1/carregamento/foto
 * Upload de foto associada a UM carregamento específico
 */
public function uploadFoto(Request $request, Response $response): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $uploadedFiles = $request->getUploadedFiles();
    
    if (empty($uploadedFiles['foto'])) {
        $response->getBody()->write(json_encode(['error' => 'Nenhuma foto enviada']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $foto = $uploadedFiles['foto'];
    if ($foto->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode(['error' => 'Erro no upload da foto']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $params = $request->getParsedBody();
    $idembarque = (int)($params['idembarque'] ?? 0);
    $iditem = (int)($params['iditem'] ?? 0);
    $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
    $doca = $params['doca'] ?? null;
    $idCarregamento = (int)($params['id_carregamento'] ?? 0);

    if ($idembarque <= 0 || $iditem <= 0 || $idusuario <= 0 || $idCarregamento <= 0) {
        $response->getBody()->write(json_encode(['error' => 'ID do embarque ou item inválido']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (($foto->getSize() ?? 0) > 15 * 1024 * 1024) {
        return $this->json($response, ['error' => 'A foto deve ter no máximo 15 MB'], 413);
    }

    $stream = $foto->getStream();
    $conteudo = $stream->getContents();
    $stream->rewind();
    $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo);
    $extensoesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensoesPermitidas[$mimeType])) {
        $response->getBody()->write(json_encode(['error' => 'Tipo de arquivo não permitido. Use JPEG, PNG ou WEBP.']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 🔥 GERAR NOME ÚNICO COM ID DO CARREGAMENTO
    $ext = $extensoesPermitidas[$mimeType];
    $nomeArquivo = sprintf(
        'emb_%d_item_%d_carga_%d_%s.%s',
        $idembarque,
        $iditem,
        $idCarregamento,
        date('Ymd_His'),
        $ext
    );
    
    $caminhoRelativo = 'uploads/carregamento/' . $nomeArquivo;
    $caminhoAbsoluto = __DIR__ . '/../../../uploads/carregamento/' . $nomeArquivo;

    // Garante que o diretório existe
    $diretorio = dirname($caminhoAbsoluto);
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }

    try {
        $this->pdo->beginTransaction();

        $stmtCarga = $this->pdo->prepare("
            SELECT id
            FROM pedido_item_carregamento
            WHERE id = ?
              AND idembarque = ?
              AND iditem = ?
              AND id_conferente = ?
              AND (path_foto_conferencia IS NULL OR path_foto_conferencia = '')
            FOR UPDATE
        ");
        $stmtCarga->execute([$idCarregamento, $idembarque, $iditem, $idusuario]);
        if (!$stmtCarga->fetchColumn()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Registro de carregamento não encontrado ou foto já enviada'], 404);
        }

        $foto->moveTo($caminhoAbsoluto);

        // 🔥 ATUALIZAR O REGISTRO ESPECÍFICO COM A FOTO
        $stmt = $this->pdo->prepare("
            UPDATE pedido_item_carregamento
            SET path_foto_conferencia = ?
            WHERE id = ? AND idembarque = ? AND iditem = ? AND id_conferente = ?
        ");
        $stmt->execute([$caminhoRelativo, $idCarregamento, $idembarque, $iditem, $idusuario]);

        // Salvar na tabela de auditoria de fotos
        $stmt2 = $this->pdo->prepare("
            INSERT INTO carregamento_fotos 
            (idembarque, iditem, id_carregamento, caminho_foto, idusuario, doca, data_hora)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt2->execute([$idembarque, $iditem, $idCarregamento, $caminhoRelativo, $idusuario, $doca]);

        $this->pdo->commit();

        $payload = json_encode([
            'success' => true,
            'message' => 'Foto registrada com sucesso!',
            'caminho' => $caminhoRelativo,
            'id_carregamento' => $idCarregamento,
            'nome_arquivo' => $nomeArquivo
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        if (file_exists($caminhoAbsoluto)) {
            @unlink($caminhoAbsoluto);
        }
        $response->getBody()->write(json_encode(['error' => 'Erro ao salvar foto: ' . $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}




}