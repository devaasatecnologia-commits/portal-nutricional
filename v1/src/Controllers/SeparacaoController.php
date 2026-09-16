<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SeparacaoController
{
    private $pdo;
    
    public function __construct() {
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
            || in_array('separacao', $permissoes, true);

        return $permitido
            ? null
            : $this->json($response, ['error' => 'Acesso não autorizado ao módulo Separação'], 403);
    }
    
    public function getEmbarquesPendentes(Request $request, Response $response): Response {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
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
                ORDER BY ep.idembarque DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
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
            i.referencia,
            pi.iditem AS cod_item,
            i.descricao AS nome_item,
            i.descricao,
            i.path_foto_master,
            i.idsecao,
            (ef.saldoatual - COALESCE(pil.total_separado, 0)) AS saldoitem,
            SUM(pi.qt) AS quant_embarque,
            COALESCE(pil.total_separado, 0) AS ja_separado,
            COALESCE(pic.total_carregado, 0) AS ja_carregado
        FROM pedido_item pi
        JOIN pedido p ON p.idpedido = pi.idpedido
        JOIN item i ON i.iditem = pi.iditem
        LEFT JOIN (
            SELECT iditem, SUM(qt_separada) AS total_separado
            FROM pedido_item_logistica
            WHERE idembarque = ?
            GROUP BY iditem
        ) pil ON pil.iditem = pi.iditem
        LEFT JOIN (
            SELECT iditem, SUM(qt_carregada) AS total_carregado
            FROM pedido_item_carregamento
            WHERE idembarque = ?
            GROUP BY iditem
        ) pic ON pic.iditem = pi.iditem
        LEFT JOIN (
            SELECT idfilial, iditem, saldoatual
            FROM estoque_filial
        ) ef ON ef.idfilial = pi.idfilial AND ef.iditem = pi.iditem
        WHERE p.idembarque = ? 
          AND pi.ativo = 'S'
        GROUP BY 
            pi.iditem,
            i.referencia,
            i.descricao,
            i.path_foto_master,
            i.idsecao,
            ef.saldoatual,
            pil.total_separado,
            pic.total_carregado,
            pi.idfilial
        ORDER BY i.idsecao " . ($ordem === 'DESC' ? 'DESC' : 'ASC');
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idembarque, $idembarque, $idembarque]);
        $itens = $stmt->fetchAll();
        
        foreach ($itens as &$item) {
            // Extrai o primeiro código da lista para exibição visual no card
            $lista = explode(',', $item['todos_codigos']);
            $item['cod_barras'] = $lista[0];

            $item['quant_embarque'] = round((float)$item['quant_embarque'], 4);
            $item['ja_separado'] = round((float)$item['ja_separado'], 4);
            $item['saldoitem'] = round((float)$item['saldoitem'], 4);
            $item['ja_carregado'] = round((float)$item['ja_carregado'], 4);
            $item['saldo_restante'] = round($item['quant_embarque'] - $item['ja_separado'], 4);
            
            if ($item['ja_separado'] <= 0.0001) {
                $item['status_logistico'] = 0;
            } elseif ($item['ja_separado'] < ($item['quant_embarque'] - 0.01)) {
                $item['status_logistico'] = 1;
            } else {
                $item['status_logistico'] = 2;
            }
        }
        
        $payload = json_encode($itens ?: []);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}

    public function confirmarItem(Request $request, Response $response): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    $iditem = (int)($input['iditem'] ?? 0);
    $idembarque = (int)($input['idembarque'] ?? 0);
    $qt_lida = round((float)($input['qtd'] ?? 0), 4);
    $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);

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
              AND idfilial IN (1, 6)
            FOR UPDATE
        ");
        $stmtEmbarque->execute([$idembarque]);
        if (!$stmtEmbarque->fetchColumn()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Embarque não disponível para separação'], 409);
        }

        $stmtPedidos = $this->pdo->prepare("
            SELECT pi.idpedido, pi.iditempedido, pi.qt,
                COALESCE((
                    SELECT SUM(qt_separada) FROM pedido_item_logistica 
                    WHERE idpedido = pi.idpedido 
                      AND iditempedido = pi.iditempedido 
                      AND iditem = pi.iditem 
                      AND idembarque = ?
                ), 0) as ja_separado
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
            ORDER BY pi.idpedido ASC
            FOR UPDATE OF pi
        ");
        $stmtPedidos->execute([$idembarque, $idembarque, $iditem]);
        $pedidos = $stmtPedidos->fetchAll();

        if (!$pedidos) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item não encontrado no embarque'], 404);
        }

        $quantidadePendente = array_reduce($pedidos, function ($total, $pedido) {
            return $total + max(0, round((float)$pedido['qt'] - (float)$pedido['ja_separado'], 4));
        }, 0.0);

        if ($quantidadePendente <= 0.0001) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item já foi totalmente separado'], 409);
        }
        if ($qt_lida > $quantidadePendente + 0.0001) {
            $this->pdo->rollBack();
            return $this->json($response, [
                'error' => 'Quantidade informada é maior que o saldo pendente',
                'saldo_pendente' => round($quantidadePendente, 4),
            ], 422);
        }

        $stmtStatus = $this->pdo->prepare("
            INSERT INTO embarque_status_log (idembarque, status_atual, data_inicio, idusuario)
            VALUES (?, 'SEPARACAO', NOW(), ?)
            ON CONFLICT (idembarque)
            DO UPDATE SET status_atual = 'SEPARACAO', idusuario = EXCLUDED.idusuario
            WHERE embarque_status_log.status_atual = 'PENDENTE'
        ");
        $stmtStatus->execute([$idembarque, $idusuario]);

        $resto = $qt_lida;
        foreach ($pedidos as $p) {
            if ($resto <= 0.0001) break;
            $falta = round((float)$p['qt'] - (float)$p['ja_separado'], 4);
            if ($falta <= 0.0001) continue;

            $baixar = min($resto, $falta);
            $nova_qtd = round((float)$p['ja_separado'] + $baixar, 4);
            $status_item = ($nova_qtd >= (float)$p['qt'] - 0.0001) ? 2 : 1;

            $up = $this->pdo->prepare("
                INSERT INTO pedido_item_logistica 
                (idpedido, iditempedido, iditem, idembarque, qt_separada, status_separacao, id_separador, data_separacao)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON CONFLICT (idpedido, iditempedido, iditem, idembarque) 
                DO UPDATE SET 
                    qt_separada = EXCLUDED.qt_separada,
                    status_separacao = EXCLUDED.status_separacao,
                    id_separador = EXCLUDED.id_separador,
                    data_separacao = NOW()
            ");
            $up->execute([(int)$p['idpedido'], (int)$p['iditempedido'], $iditem, $idembarque, $nova_qtd, $status_item, $idusuario]);
            $resto = round($resto - $baixar, 4);
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

        $lock = $this->pdo->prepare("SELECT idembarque FROM embarque_pedido WHERE idembarque = ? FOR UPDATE");
        $lock->execute([$idembarque]);
        if (!$lock->fetchColumn()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Embarque não encontrado'], 404);
        }

        $check = $this->pdo->prepare("SELECT COALESCE(SUM(qt_carregada), 0) FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?");
        $check->execute([$iditem, $idembarque]);
        if ((float)$check->fetchColumn() > 0) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item já carregado, não pode estornar separação'], 409);
        }

        $sql = "DELETE FROM pedido_item_logistica WHERE iditem = ? AND idembarque = ? AND iditempedido IN (
                    SELECT pi.iditempedido FROM pedido_item pi JOIN pedido p ON p.idpedido = pi.idpedido WHERE p.idembarque = ? AND pi.iditem = ?
                )";
        $delete = $this->pdo->prepare($sql);
        $delete->execute([$iditem, $idembarque, $idembarque, $iditem]);
        if ($delete->rowCount() === 0) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Item separado não encontrado'], 404);
        }

        $this->pdo->commit();
        return $this->json($response, ['success' => true]);
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}

public function finalizarSeparacao(Request $request, Response $response, array $args): Response
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

        $stmtPendencias = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            WHERE p.idembarque = :idembarque
              AND pi.ativo = 'S'
              AND COALESCE((
                  SELECT SUM(logistica.qt_separada)
                  FROM pedido_item_logistica logistica
                  WHERE logistica.idpedido = pi.idpedido
                    AND logistica.iditempedido = pi.iditempedido
                    AND logistica.iditem = pi.iditem
                    AND logistica.idembarque = :idembarque_log
              ), 0) < pi.qt - 0.0001
        ");
        $stmtPendencias->execute([
            'idembarque' => $idembarque,
            'idembarque_log' => $idembarque,
        ]);
        $itensPendentes = (int)$stmtPendencias->fetchColumn();
        if ($itensPendentes > 0) {
            $this->pdo->rollBack();
            return $this->json($response, [
                'error' => 'Existem itens pendentes de separação',
                'itens_pendentes' => $itensPendentes,
            ], 409);
        }
        
        // ✅ Atualiza status_log para CONCLUIDO
        $stmt = $this->pdo->prepare("
            INSERT INTO embarque_status_log (idembarque, status_atual, data_fim, idusuario)
            VALUES (:emb, 'CONCLUIDO', NOW(), :user)
            ON CONFLICT (idembarque) 
            DO UPDATE SET status_atual = 'CONCLUIDO', data_fim = NOW(), idusuario = EXCLUDED.idusuario
        ");
        $stmt->execute(['emb' => $idembarque, 'user' => $idusuario]);
        
        // ✅ Marca como pronto para carregamento
        $this->pdo->prepare("UPDATE embarque_pedido SET pex_embarque_pronto = 'S' WHERE idembarque = ?")->execute([$idembarque]);
        
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
 * GET /v1/separacao/resumo/{idembarque}
 * Retorna resumo do embarque (total de itens, pedidos e peso bruto)
 */
public function getResumo(Request $request, Response $response, array $args): Response
{
    if ($denied = $this->validarAcesso($request, $response)) return $denied;

    $idembarque = (int)($args['idembarque'] ?? 0);
    
    if ($idembarque <= 0) {
        $response->getBody()->write(json_encode(['error' => 'ID do embarque inválido']));
        return $response->withStatus(400);
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
        
        // Garante que os valores sejam numéricos
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
        return $response->withStatus(500);
    }
}
}