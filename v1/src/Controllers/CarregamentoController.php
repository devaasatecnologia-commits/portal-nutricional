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

    // =========================================================================
    // GET /v1/carregamento/embarques
    // =========================================================================
    public function getEmbarques(Request $request, Response $response): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        try {
            $sql = "SELECT DISTINCT 
                        ep.idembarque, 
                        ep.observacao as rota, 
                        ep.placa,
                        COALESCE(s.status_atual, 'PENDENTE') as status_logistico,
                        (SELECT COUNT(DISTINCT pi.iditem)
                         FROM pedido_item pi
                         JOIN pedido p ON p.idpedido = pi.idpedido
                         WHERE p.idembarque = ep.idembarque
                           AND pi.ativo = 'S'
                           AND COALESCE((
                               SELECT SUM(l.qt_separada) FROM pedido_item_logistica l
                               WHERE l.idembarque = ep.idembarque AND l.iditem = pi.iditem
                           ), 0) >= pi.qt - 0.0001
                        ) as itens_prontos,
                        (SELECT COUNT(DISTINCT pi.iditem)
                         FROM pedido_item pi
                         JOIN pedido p ON p.idpedido = pi.idpedido
                         WHERE p.idembarque = ep.idembarque AND pi.ativo = 'S'
                        ) as total_itens,
                        (SELECT COUNT(DISTINCT pi.iditem)
                         FROM pedido_item pi
                         JOIN pedido p ON p.idpedido = pi.idpedido
                         WHERE p.idembarque = ep.idembarque
                           AND pi.ativo = 'S'
                           AND COALESCE((
                               SELECT SUM(c.qt_carregada) FROM pedido_item_carregamento c
                               WHERE c.idembarque = ep.idembarque AND c.iditem = pi.iditem
                           ), 0) >= pi.qt - 0.0001
                        ) as itens_carregados
                    FROM embarque_pedido ep
                    LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    WHERE ep.pex_conferido = 'N' 
                      AND ep.gerou_nf = 'S'
                      AND ep.pex_embarque_pronto = 'S'
                      AND ep.idfilial IN (1,6)
                      AND ep.data >= (CURRENT_DATE - INTERVAL '30 days')
                    ORDER BY ep.idembarque DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll();

            foreach ($data as &$row) {
                $row['itens_prontos'] = (int)($row['itens_prontos'] ?? 0);
                $row['total_itens'] = (int)($row['total_itens'] ?? 0);
                $row['itens_carregados'] = (int)($row['itens_carregados'] ?? 0);
                $row['progresso_separacao'] = $row['total_itens'] > 0
                    ? round(($row['itens_prontos'] / $row['total_itens']) * 100)
                    : 0;
            }

            return $this->json($response, $data);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GET /v1/carregamento/itens/{idembarque}
    // =========================================================================
    public function getItens(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idembarque = (int)($args['idembarque'] ?? 0);
        $ordem = $request->getQueryParams()['ordem'] ?? 'ASC';

        if ($idembarque <= 0) {
            return $this->json($response, []);
        }

        try {
            $sql = "SELECT 
                        COALESCE((SELECT STRING_AGG(idbarra, ',') FROM codigo_barra WHERE iditem = pi.iditem), 'SEM_BARRA') AS todos_codigos,
                        (SELECT idbarra FROM codigo_barra WHERE iditem = pi.iditem AND principal = 'S' LIMIT 1) AS cod_barras_principal,
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
                $lista = explode(',', $item['todos_codigos']);
                $item['cod_barras'] = $lista[0];

                $sep = (float)$item['ja_separado'];
                $total = (float)$item['quant_embarque'];
                $car = (float)$item['ja_carregado'];

                $item['quantidade_disponivel'] = max(0, round($sep - $car, 4));

                if ($car >= $total - 0.0001 && $total > 0) {
                    $item['status_item'] = 'CARREGADO';
                } elseif ($sep >= $total - 0.0001) {
                    $item['status_item'] = 'LIBERADO';
                } elseif ($sep > 0.0001) {
                    $item['status_item'] = 'PARCIAL';
                } else {
                    $item['status_item'] = 'BLOQUEADO';
                }

                $item['pode_carregar'] = ($item['quantidade_disponivel'] > 0.0001) ? 1 : 0;
                $item['separacao_completa'] = ($sep >= $total - 0.0001) ? 1 : 0;
                $item['nada_separado'] = ($sep <= 0.0001) ? 1 : 0;

                $item['quant_embarque'] = round($total, 4);
                $item['ja_separado'] = round($sep, 4);
                $item['ja_carregado'] = round($car, 4);
            }

            return $this->json($response, $itens ?: []);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GET /v1/carregamento/resumo/{idembarque}
    // =========================================================================
    public function getResumo(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idembarque = (int)($args['idembarque'] ?? 0);

        if ($idembarque <= 0) {
            return $this->json($response, ['error' => 'ID do embarque inválido'], 400);
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

            return $this->json($response, $resumo);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // POST /v1/carregamento/confirmar
    // =========================================================================
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
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        $docasValidas = ['DOCA 1', 'DOCA 2', 'DOCA 3'];
        if ($doca !== null && !in_array($doca, $docasValidas, true)) {
            return $this->json($response, ['error' => 'Doca inválida'], 400);
        }

        try {
            $this->pdo->beginTransaction();

            // TRAVA 1
            $stmtEmbarque = $this->pdo->prepare("
                SELECT idembarque FROM embarque_pedido
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

            // TRAVA 2
            $stmtLockItens = $this->pdo->prepare("
                SELECT pi.iditempedido FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
                FOR UPDATE OF pi
            ");
            $stmtLockItens->execute([$idembarque, $iditem]);
            if (!$stmtLockItens->fetchAll()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Item não encontrado no embarque'], 404);
            }

            // TRAVA 3
            $stmtLockCargas = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento
                WHERE idembarque = ? AND iditem = ?
                FOR UPDATE
            ");
            $stmtLockCargas->execute([$idembarque, $iditem]);
            $stmtLockCargas->fetchAll();

            // Busca pedidos
            $stmt = $this->pdo->prepare("
                SELECT pi.idpedido, pi.iditempedido, pi.qt, 
                       COALESCE(l.qt_separada, 0) as qt_separada, 
                       COALESCE((
                           SELECT SUM(c.qt_carregada) 
                           FROM pedido_item_carregamento c 
                           WHERE c.idpedido = pi.idpedido 
                             AND c.iditempedido = pi.iditempedido 
                             AND c.idembarque = p.idembarque
                       ), 0) as ja_carregado_total
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                LEFT JOIN pedido_item_logistica l ON l.idpedido = pi.idpedido 
                     AND l.iditempedido = pi.iditempedido 
                     AND l.idembarque = p.idembarque
                WHERE p.idembarque = ? AND pi.iditem = ? AND pi.ativo = 'S'
                ORDER BY pi.idpedido ASC
            ");
            $stmt->execute([$idembarque, $iditem]);
            $pedidos = $stmt->fetchAll();

            if (!$pedidos) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Item não encontrado'], 404);
            }

            // TRAVA 4
            $quantidadePendente = array_reduce($pedidos, function ($total, $pedido) {
                return $total + max(0, round(
                    (float)$pedido['qt_separada'] - (float)$pedido['ja_carregado_total'],
                    4
                ));
            }, 0.0);

            if ($quantidadePendente <= 0.0001) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Item já foi totalmente carregado',
                    'codigo' => 'ITEM_CARREGADO'
                ], 409);
            }

            if ($qt_lida > $quantidadePendente + 0.0001) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Quantidade maior que o saldo separado pendente',
                    'codigo' => 'QUANTIDADE_EXCEDIDA',
                    'saldo_pendente' => round($quantidadePendente, 4)
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

                $insert = $this->pdo->prepare("
                    INSERT INTO pedido_item_carregamento 
                    (idpedido, iditempedido, iditem, idembarque, qt_carregada, id_conferente, data_carregamento, doca)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                    RETURNING id
                ");
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
                $idsCarregamentoCriados[] = $resultInsert['id'];

                $resto = round($resto - $baixar, 4);
            }

            $this->pdo->commit();

            return $this->json($response, [
                'success' => true,
                'doca' => $doca,
                'id_carregamento' => $idsCarregamentoCriados[0] ?? null,
                'ids_carregamentos' => $idsCarregamentoCriados,
                'quantidade_registrada' => round($qt_lida - $resto, 4)
            ]);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // DELETE /v1/carregamento/estornar/{iditem}/{idembarque}
    // =========================================================================
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

            // TRAVA 1
            $lock = $this->pdo->prepare("
                SELECT idembarque FROM embarque_pedido 
                WHERE idembarque = ? AND pex_conferido = 'N' 
                FOR UPDATE
            ");
            $lock->execute([$idembarque]);
            if (!$lock->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Embarque já foi conferido. Estorno bloqueado.',
                    'codigo' => 'EMBARQUE_FINALIZADO'
                ], 409);
            }

            // TRAVA 2
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM pedido_item_carregamento 
                WHERE iditem = ? AND idembarque = ?
                FOR UPDATE
            ");
            $stmt->execute([$iditem, $idembarque]);
            if ((int)$stmt->fetchColumn() === 0) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Item não foi carregado'], 404);
            }

            // Buscar fotos
            $stmtFotos = $this->pdo->prepare("
                SELECT path_foto_conferencia FROM pedido_item_carregamento 
                WHERE iditem = ? AND idembarque = ?
            ");
            $stmtFotos->execute([$iditem, $idembarque]);
            $fotos = $stmtFotos->fetchAll();

            // Deleta carregamentos
            $this->pdo->prepare("
                DELETE FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?
            ")->execute([$iditem, $idembarque]);

            $this->pdo->prepare("
                DELETE FROM carregamento_fotos WHERE iditem = ? AND idembarque = ?
            ")->execute([$iditem, $idembarque]);

            // Verifica se ainda há itens carregados
            $stmtRestantes = $this->pdo->prepare("
                SELECT COUNT(*) FROM pedido_item_carregamento WHERE idembarque = ?
            ");
            $stmtRestantes->execute([$idembarque]);
            $restantes = (int)$stmtRestantes->fetchColumn();

            if ($restantes === 0) {
                $this->pdo->prepare("
                    UPDATE embarque_status_log 
                    SET status_atual = 'SEPARACAO', data_fim = NULL 
                    WHERE idembarque = ?
                ")->execute([$idembarque]);

                $this->pdo->prepare("
                    UPDATE embarque_pedido 
                    SET pex_embarque_carregamento = 'N', data_carregamento = NULL 
                    WHERE idembarque = ?
                ")->execute([$idembarque]);
            }

            $this->pdo->commit();

            // Deleta fotos do disco
            foreach ($fotos as $foto) {
                if (!empty($foto['path_foto_conferencia'])) {
                    $caminho = __DIR__ . '/../../../' . $foto['path_foto_conferencia'];
                    if (is_file($caminho)) @unlink($caminho);
                }
            }

            return $this->json($response, ['success' => true]);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // POST /v1/carregamento/finalizar/{idembarque}
    // =========================================================================
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

            // TRAVA 1
            $stmtEmbarque = $this->pdo->prepare("
                SELECT idembarque FROM embarque_pedido
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
                return $this->json($response, ['error' => 'Embarque não disponível'], 409);
            }

            // TRAVA 2
            $stmtLockItens = $this->pdo->prepare("
                SELECT pi.iditempedido FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idembarque = ? AND pi.ativo = 'S'
                FOR UPDATE OF pi
            ");
            $stmtLockItens->execute([$idembarque]);
            $stmtLockItens->fetchAll();

            // TRAVA 3
            $stmtLockCargas = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento WHERE idembarque = ? FOR UPDATE
            ");
            $stmtLockCargas->execute([$idembarque]);
            $stmtLockCargas->fetchAll();

            // TRAVA 4: Separação finalizada?
            $stmtSeparacao = $this->pdo->prepare("
                SELECT status_atual FROM embarque_status_log WHERE idembarque = ?
            ");
            $stmtSeparacao->execute([$idembarque]);
            $statusAtual = $stmtSeparacao->fetchColumn();

            if ($statusAtual !== 'CONCLUIDO' && $statusAtual !== 'CARREGADO') {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Separação ainda não foi finalizada',
                    'codigo' => 'SEPARACAO_PENDENTE',
                    'status_atual' => $statusAtual
                ], 409);
            }

            // TRAVA 5: Itens separados-mas-não-carregados
            $stmtPendencias = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idembarque = :idembarque
                  AND pi.ativo = 'S'
                  AND COALESCE((
                      SELECT SUM(log.qt_separada) FROM pedido_item_logistica log
                      WHERE log.idembarque = :idembarque_sep
                        AND log.iditem = pi.iditem
                        AND log.iditempedido = pi.iditempedido
                  ), 0) > COALESCE((
                      SELECT SUM(carga.qt_carregada) FROM pedido_item_carregamento carga
                      WHERE carga.idembarque = :idembarque_carga
                        AND carga.iditem = pi.iditem
                        AND carga.iditempedido = pi.iditempedido
                  ), 0) + 0.0001
            ");
            $stmtPendencias->execute([
                'idembarque' => $idembarque,
                'idembarque_sep' => $idembarque,
                'idembarque_carga' => $idembarque,
            ]);
            $itensPendentes = (int)$stmtPendencias->fetchColumn();

            if ($itensPendentes > 0) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Existem itens separados que ainda não foram carregados',
                    'codigo' => 'ITENS_PENDENTES',
                    'itens_pendentes' => $itensPendentes
                ], 409);
            }

            // TRAVA 6: Fotos obrigatórias
            $stmtFotos = $this->pdo->prepare("
                SELECT COUNT(*) FROM pedido_item_carregamento
                WHERE idembarque = ?
                  AND (path_foto_conferencia IS NULL OR path_foto_conferencia = '')
            ");
            $stmtFotos->execute([$idembarque]);
            $fotosPendentes = (int)$stmtFotos->fetchColumn();

            if ($fotosPendentes > 0) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Existem carregamentos sem foto',
                    'codigo' => 'FOTOS_PENDENTES',
                    'fotos_pendentes' => $fotosPendentes
                ], 409);
            }

            // Atualiza status
            $stmt = $this->pdo->prepare("
                INSERT INTO embarque_status_log (idembarque, status_atual, data_fim, idusuario)
                VALUES (:emb, 'CARREGADO', NOW(), :user)
                ON CONFLICT (idembarque) 
                DO UPDATE SET status_atual = 'CARREGADO', data_fim = NOW(), idusuario = EXCLUDED.idusuario
            ");
            $stmt->execute(['emb' => $idembarque, 'user' => $idusuario]);

            $this->pdo->prepare("
                UPDATE embarque_pedido 
                SET pex_conferido = 'S', data_carregamento = NOW(), pex_embarque_carregamento = 'S' 
                WHERE idembarque = ?
            ")->execute([$idembarque]);

            $this->pdo->commit();

            return $this->json($response, ['success' => true]);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GET /v1/carregamento/fotos/{idembarque}
    // =========================================================================
    public function getFotos(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idembarque = (int)($args['idembarque'] ?? 0);

        if ($idembarque <= 0) {
            return $this->json($response, ['error' => 'ID do embarque inválido'], 400);
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

            return $this->json($response, $fotos ?: []);

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // GET /v1/carregamento/foto/{idfoto}
    // =========================================================================
    public function getFoto(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idfoto = (int)($args['idfoto'] ?? 0);

        try {
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

    // =========================================================================
    // POST /v1/carregamento/foto
    // =========================================================================
    public function uploadFoto(Request $request, Response $response): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['foto'])) {
            return $this->json($response, ['error' => 'Nenhuma foto enviada'], 400);
        }

        $foto = $uploadedFiles['foto'];
        if ($foto->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, ['error' => 'Erro no upload da foto'], 500);
        }

        $params = $request->getParsedBody();
        $idembarque = (int)($params['idembarque'] ?? 0);
        $iditem = (int)($params['iditem'] ?? 0);
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
        $doca = $params['doca'] ?? null;
        $idCarregamento = (int)($params['id_carregamento'] ?? 0);

        if ($idembarque <= 0 || $iditem <= 0 || $idusuario <= 0 || $idCarregamento <= 0) {
            return $this->json($response, ['error' => 'ID do embarque ou item inválido'], 400);
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
            return $this->json($response, ['error' => 'Tipo não permitido. Use JPEG, PNG ou WEBP.'], 400);
        }

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

        $diretorio = dirname($caminhoAbsoluto);
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }

        try {
            $this->pdo->beginTransaction();

            $stmtCarga = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento
                WHERE id = ?
                  AND idembarque = ?
                  AND iditem = ?
                  AND (path_foto_conferencia IS NULL OR path_foto_conferencia = '')
                FOR UPDATE
            ");
            $stmtCarga->execute([$idCarregamento, $idembarque, $iditem]);
            if (!$stmtCarga->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Registro não encontrado ou foto já enviada'], 404);
            }

            $foto->moveTo($caminhoAbsoluto);

            $this->pdo->prepare("
                UPDATE pedido_item_carregamento
                SET path_foto_conferencia = ?
                WHERE id = ? AND idembarque = ? AND iditem = ?
            ")->execute([$caminhoRelativo, $idCarregamento, $idembarque, $iditem]);

            $this->pdo->prepare("
                INSERT INTO carregamento_fotos 
                (idembarque, iditem, id_carregamento, caminho_foto, idusuario, doca, data_hora)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$idembarque, $iditem, $idCarregamento, $caminhoRelativo, $idusuario, $doca]);

            $this->pdo->commit();

            return $this->json($response, [
                'success' => true,
                'message' => 'Foto registrada com sucesso!',
                'caminho' => $caminhoRelativo,
                'id_carregamento' => $idCarregamento,
                'nome_arquivo' => $nomeArquivo
            ]);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (file_exists($caminhoAbsoluto)) @unlink($caminhoAbsoluto);
            return $this->json($response, ['error' => 'Erro ao salvar foto: ' . $e->getMessage()], 500);
        }
    }
}