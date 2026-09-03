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
/**
 * GET /v1/carregamento/embarques
 * Lista embarques que já têm NF gerada e separação concluída, prontos para carregar
 */
public function getEmbarques(Request $request, Response $response): Response
{
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
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    $iditem = (int)($input['iditem'] ?? 0);
    $idembarque = (int)($input['idembarque'] ?? 0);
    $qt_lida = round((float)($input['qtd'] ?? 0), 4);
    $idusuario = (int)($input['idusuario'] ?? 0);
    $doca = $input['doca'] ?? null;
    
    if ($iditem <= 0 || $idembarque <= 0 || $qt_lida <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
        return $response->withStatus(400);
    }
    
    try {
        $this->pdo->beginTransaction();
        
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
            throw new \Exception("Item não encontrado no embarque.");
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
            'quantidade_registrada' => $qt_lida
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
    $iditem = (int)$args['iditem'];
    $idembarque = (int)$args['idembarque'];
    
    try {
        $this->pdo->beginTransaction();
        
        // Buscar fotos para deletar
        $stmt = $this->pdo->prepare("
            SELECT path_foto_conferencia FROM pedido_item_carregamento 
            WHERE iditem = ? AND idembarque = ?
        ");
        $stmt->execute([$iditem, $idembarque]);
        $fotos = $stmt->fetchAll();
        
        foreach ($fotos as $foto) {
            if (!empty($foto['path_foto_conferencia'])) {
                $caminho = __DIR__ . '/../../../' . $foto['path_foto_conferencia'];
                if (file_exists($caminho)) {
                    @unlink($caminho);
                }
            }
        }
        
        $sql = "DELETE FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$iditem, $idembarque]);
        
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
     * POST /v1/carregamento/finalizar/{idembarque}
     * Marca o embarque como carregado
     */
    public function finalizarEmbarque(Request $request, Response $response, array $args): Response
    {
        $idembarque = (int)$args['idembarque'];
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idusuario = (int)($input['idusuario'] ?? 0);
        
        try {
            $this->pdo->beginTransaction();
            
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
    $idusuario = (int)($params['idusuario'] ?? 0);
    $doca = $params['doca'] ?? null;
    $idCarregamento = (int)($params['id_carregamento'] ?? 0);

    if ($idembarque <= 0 || $iditem <= 0) {
        $response->getBody()->write(json_encode(['error' => 'ID do embarque ou item inválido']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Valida o tipo de arquivo
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $clientMediaType = $foto->getClientMediaType();
    if (!in_array($clientMediaType, $allowedTypes)) {
        $response->getBody()->write(json_encode(['error' => 'Tipo de arquivo não permitido. Use JPEG, PNG ou WEBP.']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 🔥 GERAR NOME ÚNICO COM ID DO CARREGAMENTO
    $ext = pathinfo($foto->getClientFilename(), PATHINFO_EXTENSION);
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
        $foto->moveTo($caminhoAbsoluto);

        // 🔥 ATUALIZAR O REGISTRO ESPECÍFICO COM A FOTO
        if ($idCarregamento > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE pedido_item_carregamento 
                SET path_foto_conferencia = ?
                WHERE id = ? AND idembarque = ? AND iditem = ?
            ");
            $stmt->execute([$caminhoRelativo, $idCarregamento, $idembarque, $iditem]);
        } else {
            // Fallback: atualizar o registro mais recente sem foto
            $stmt = $this->pdo->prepare("
                UPDATE pedido_item_carregamento 
                SET path_foto_conferencia = ?
                WHERE idembarque = ? AND iditem = ? 
                  AND (path_foto_conferencia IS NULL OR path_foto_conferencia = '')
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$caminhoRelativo, $idembarque, $iditem]);
        }

        // Salvar na tabela de auditoria de fotos
        $stmt2 = $this->pdo->prepare("
            INSERT INTO carregamento_fotos 
            (idembarque, iditem, id_carregamento, caminho_foto, idusuario, doca, data_hora)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt2->execute([$idembarque, $iditem, $idCarregamento, $caminhoRelativo, $idusuario, $doca]);

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
        if (file_exists($caminhoAbsoluto)) {
            @unlink($caminhoAbsoluto);
        }
        $response->getBody()->write(json_encode(['error' => 'Erro ao salvar foto: ' . $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}




}