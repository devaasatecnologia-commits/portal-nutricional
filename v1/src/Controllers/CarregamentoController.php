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

    // ✅ ROLLBACK AUTOMÁTICO se a requisição morrer no meio
    register_shutdown_function(function () {
        if (isset($this->pdo) && $this->pdo && $this->pdo->inTransaction()) {
            try {
                $this->pdo->rollBack();
                error_log("[SHUTDOWN] Rollback automático executado");
            } catch (\Throwable $e) {
                error_log("[SHUTDOWN] Falha no rollback: " . $e->getMessage());
            }
        }
    });
}

private function iniciarTransacaoSegura(): void
{
   $this->pdo->beginTransaction();
    $this->pdo->exec("SET LOCAL lock_timeout = '5s'");
    $this->pdo->exec("SET LOCAL statement_timeout = '30s'");
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
                      AND (ep.gerou_nf = 'S' OR ep.idfilial = 6)
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

        } catch (\Throwable $e) {
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
                        ), 0) AS ja_carregado,
                        COALESCE((
                            SELECT COUNT(*) FROM carregamento_subidas 
                            WHERE idembarque = :emb AND iditem = pi.iditem
                        ), 0) AS subidas,
                        (
                            SELECT MAX(numero_subida) FROM carregamento_subidas
                            WHERE idembarque = :emb AND iditem = pi.iditem
                        ) AS ultima_subida,
                        (
                            SELECT MAX(data_hora) FROM carregamento_subidas
                            WHERE idembarque = :emb AND iditem = pi.iditem
                        ) AS ultima_subida_hora
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

                // Status considerando subidas parciais
                if ($car >= $total - 0.0001 && $total > 0) {
                    $item['status_item'] = 'CARREGADO';
                } elseif ($sep >= $total - 0.0001) {
                    // Separação completa — pode ter subidas parciais
                    $item['status_item'] = $car > 0.0001 ? 'PARCIAL' : 'LIBERADO';
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
                $item['subidas'] = (int)($item['subidas'] ?? 0);
                $item['ultima_subida'] = (int)($item['ultima_subida'] ?? 0);
            }

            return $this->json($response, $itens ?: []);

        } catch (\Throwable $e) {
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

        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

       // =========================================================================
    // POST /v1/carregamento/confirmar
    // Registra uma subida (parcial ou total) do item no caminhão
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
           $this->iniciarTransacaoSegura();

            // TRAVA 1: Embarque disponível
            $stmtEmbarque = $this->pdo->prepare("
                SELECT idembarque FROM embarque_pedido
                WHERE idembarque = ?
                  AND pex_conferido = 'N'
                  AND (gerou_nf = 'S' OR idfilial = 6)
                  AND pex_embarque_pronto = 'S'
                  AND idfilial IN (1, 6)
                FOR UPDATE
            ");
            $stmtEmbarque->execute([$idembarque]);
            if (!$stmtEmbarque->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque não disponível para carregamento'], 409);
            }

            // TRAVA 2: Lock nos itens do embarque
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

            // TRAVA 3: Lock nos carregamentos existentes
            $stmtLockCargas = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento
                WHERE idembarque = ? AND iditem = ?
                FOR UPDATE
            ");
            $stmtLockCargas->execute([$idembarque, $iditem]);
            $stmtLockCargas->fetchAll();

            // ✅ TRAVA 3.1: Lock explícito nas subidas (evita race condition de numero_subida)
            $stmtLockSubidas = $this->pdo->prepare("
                SELECT id FROM carregamento_subidas
                WHERE idembarque = ? AND iditem = ?
                FOR UPDATE
            ");
            $stmtLockSubidas->execute([$idembarque, $iditem]);
            $stmtLockSubidas->fetchAll();

            // Busca pedidos do item com separação e carregamento
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
                LEFT JOIN (
                    SELECT idpedido, iditempedido, idembarque, SUM(qt_separada) AS qt_separada
                    FROM pedido_item_logistica
                    GROUP BY idpedido, iditempedido, idembarque
                ) l ON l.idpedido = pi.idpedido
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

            // TRAVA 4: Quantidade pendente = separado - carregado
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

            // =========================================================
            // UPSERT MANUAL — distribui quantidade entre pedidos
            // =========================================================
            $resto = $qt_lida;
            $idsCarregamentoCriados = [];
            $alocacoesSubida = [];
            $idPrincipal = null;

            foreach ($pedidos as $p) {
                if ($resto <= 0.0001) break;

                $ja_carregado = (float)$p['ja_carregado_total'];
                $qt_separada = (float)$p['qt_separada'];
                $falta_no_caminhao = round($qt_separada - $ja_carregado, 4);

                if ($falta_no_caminhao <= 0.0001) continue;

                $baixar = min($resto, $falta_no_caminhao);

                $idpedido = (int)$p['idpedido'];
                $iditempedido = (int)$p['iditempedido'];

                // Procura registro existente
                $checkExist = $this->pdo->prepare("
                    SELECT id FROM pedido_item_carregamento
                    WHERE idembarque = ? 
                      AND iditem = ? 
                      AND idpedido = ? 
                      AND iditempedido = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $checkExist->execute([$idembarque, $iditem, $idpedido, $iditempedido]);
                $existente = $checkExist->fetch();

                if ($existente) {
                    $update = $this->pdo->prepare("
                        UPDATE pedido_item_carregamento
                        SET qt_carregada = qt_carregada + ?,
                            id_conferente = ?,
                            data_carregamento = NOW(),
                            doca = COALESCE(?, doca)
                        WHERE id = ?
                    ");
                    $update->execute([$baixar, $idusuario, $doca, (int)$existente['id']]);
                    $idAtual = (int)$existente['id'];
                } else {
                    $insert = $this->pdo->prepare("
                        INSERT INTO pedido_item_carregamento 
                            (idpedido, iditempedido, iditem, idembarque, 
                             qt_carregada, id_conferente, data_carregamento, doca)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                        RETURNING id
                    ");
                    $insert->execute([
                        $idpedido, $iditempedido, $iditem, $idembarque,
                        $baixar, $idusuario, $doca
                    ]);
                    $row = $insert->fetch();
                    $idAtual = (int)$row['id'];
                }

                $idsCarregamentoCriados[] = $idAtual;
                $alocacoesSubida[] = [
                    'id_carregamento' => $idAtual,
                    'idpedido' => $idpedido,
                    'iditempedido' => $iditempedido,
                    'quantidade' => round($baixar, 4)
                ];
                if ($idPrincipal === null) $idPrincipal = $idAtual;

                $resto = round($resto - $baixar, 4);
            }

            // =========================================================
            // ✅ REGISTRA A SUBIDA NA TABELA DE HISTÓRICO
            // =========================================================
            $qtRegistrada = round($qt_lida - $resto, 4);

            $idSubida = null;
            $totalAcumulado = 0;
            $proximoNumero = 0;

            if ($qtRegistrada > 0.0001 && $idPrincipal !== null) {
                // Calcula total acumulado APÓS esta subida
                $stmtTotal = $this->pdo->prepare("
                    SELECT COALESCE(SUM(qt_carregada), 0)
                    FROM pedido_item_carregamento
                    WHERE idembarque = ? AND iditem = ?
                ");
                $stmtTotal->execute([$idembarque, $iditem]);
                $totalAcumulado = (float)$stmtTotal->fetchColumn();

                // Próximo número sequencial da subida
                // (seguro: já travamos carregamento_subidas acima com FOR UPDATE)
                $stmtNum = $this->pdo->prepare("
                    SELECT COALESCE(MAX(numero_subida), 0) + 1
                    FROM carregamento_subidas
                    WHERE idembarque = ? AND iditem = ?
                ");
                $stmtNum->execute([$idembarque, $iditem]);
                $proximoNumero = (int)$stmtNum->fetchColumn();

                $stmtSubida = $this->pdo->prepare("
                    INSERT INTO carregamento_subidas 
                        (id_carregamento, idembarque, iditem, idusuario, doca,
                         qt_subida, qt_acumulada, numero_subida, data_hora)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    RETURNING id
                ");
                $stmtSubida->execute([
                    $idPrincipal,
                    $idembarque,
                    $iditem,
                    $idusuario,
                    $doca,
                    $qtRegistrada,
                    $totalAcumulado,
                    $proximoNumero
                ]);
                $idSubida = (int)$stmtSubida->fetch()['id'];

                $stmtAlocacao = $this->pdo->prepare("
                    INSERT INTO carregamento_subida_alocacoes
                        (id_subida, id_carregamento, idembarque, iditem,
                         idpedido, iditempedido, quantidade)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($alocacoesSubida as $alocacao) {
                    $stmtAlocacao->execute([
                        $idSubida,
                        $alocacao['id_carregamento'],
                        $idembarque,
                        $iditem,
                        $alocacao['idpedido'],
                        $alocacao['iditempedido'],
                        $alocacao['quantidade']
                    ]);
                }
            }

            $this->pdo->commit();

            return $this->json($response, [
                'success' => true,
                'doca' => $doca,
                'id_carregamento' => $idPrincipal,
                'ids_carregamentos' => $idsCarregamentoCriados,
                'id_subida' => $idSubida,
                'numero_subida' => $proximoNumero,
                'quantidade_registrada' => $qtRegistrada,
                'quantidade_acumulada' => round($totalAcumulado, 4),
                'mensagem' => $proximoNumero > 1
                    ? "📦 {$proximoNumero}ª subida registrada"
                    : "✅ Primeira subida registrada"
            ]);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[Carregamento] Falha ao confirmar item: ' . $e->getMessage());
            return $this->json($response, [
                'error' => 'Não foi possível registrar a subida do item.',
                'codigo' => 'FALHA_REGISTRO_CARREGAMENTO'
            ], 500);
        }
    }
        // =========================================================================
    // DELETE /v1/carregamento/estornar/{iditem}/{idembarque}
    // Estorno TOTAL de um item — remove carregamentos, fotos e subidas
    // =========================================================================
    private function estornarItemLegado(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $iditem = (int)($args['iditem'] ?? 0);
        $idembarque = (int)($args['idembarque'] ?? 0);

        if ($iditem <= 0 || $idembarque <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        try {
            $this->iniciarTransacaoSegura();

            // TRAVA 1: Embarque disponível (não conferido)
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

            // TRAVA 2: Existe carregamento deste item?
            $stmt = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento 
                WHERE iditem = ? AND idembarque = ?
                FOR UPDATE
            ");
            $stmt->execute([$iditem, $idembarque]);
            $rows = $stmt->fetchAll();
            if (count($rows) === 0) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Item não foi carregado'], 404);
            }

            // =============================================================
            // COLETA DE FOTOS PARA DELETAR DO DISCO (antes de apagar)
            // =============================================================
            $fotos = [];
            $fotosHist = [];
            $fotosSub = [];

            $stmtFotos = $this->pdo->prepare("
                SELECT path_foto_conferencia AS p 
                FROM pedido_item_carregamento 
                WHERE iditem = ? AND idembarque = ?
            ");
            $stmtFotos->execute([$iditem, $idembarque]);
            $fotos = $stmtFotos->fetchAll();

            $stmtFotosHist = $this->pdo->prepare("
                SELECT caminho_foto AS p 
                FROM carregamento_fotos 
                WHERE iditem = ? AND idembarque = ?
            ");
            $stmtFotosHist->execute([$iditem, $idembarque]);
            $fotosHist = $stmtFotosHist->fetchAll();

            $stmtFotosSub = $this->pdo->prepare("
                SELECT path_foto AS p 
                FROM carregamento_subidas 
                WHERE iditem = ? AND idembarque = ?
            ");
            $stmtFotosSub->execute([$iditem, $idembarque]);
            $fotosSub = $stmtFotosSub->fetchAll();

            // =============================================================
            // DELEÇÃO EM CASCATA MANUAL
            // =============================================================
            $this->pdo->prepare("DELETE FROM carregamento_subidas WHERE iditem = ? AND idembarque = ?")
                      ->execute([$iditem, $idembarque]);

            $this->pdo->prepare("DELETE FROM carregamento_fotos WHERE iditem = ? AND idembarque = ?")
                      ->execute([$iditem, $idembarque]);

            $this->pdo->prepare("DELETE FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?")
                      ->execute([$iditem, $idembarque]);

            // =============================================================
            // ROLLBACK DE STATUS SE NÃO HOUVER MAIS NADA CARREGADO
            // =============================================================
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

                // ✅ CORREÇÃO CRÍTICA: NÃO setar NULL — usar sentinela '1900-01-01'
                $this->pdo->prepare("
                    UPDATE embarque_pedido 
                    SET pex_embarque_carregamento = 'N', data_carregamento = '1900-01-01'
                    WHERE idembarque = ?
                ")->execute([$idembarque]);
            }

            $this->pdo->commit();

            // =============================================================
            // LIMPEZA DE ARQUIVOS NO DISCO
            // =============================================================
            $deletados = 0;

            foreach ($fotos as $foto) {
                if (!empty($foto['p'])) {
                    $caminho = __DIR__ . '/../../../' . $foto['p'];
                    if (is_file($caminho) && @unlink($caminho)) $deletados++;
                }
            }
            foreach ($fotosHist as $foto) {
                if (!empty($foto['p'])) {
                    $caminho = __DIR__ . '/../../../' . $foto['p'];
                    if (is_file($caminho) && @unlink($caminho)) $deletados++;
                }
            }
            foreach ($fotosSub as $foto) {
                if (!empty($foto['p'])) {
                    $caminho = __DIR__ . '/../../../' . $foto['p'];
                    if (is_file($caminho) && @unlink($caminho)) $deletados++;
                }
            }

            return $this->json($response, [
                'success' => true,
                'arquivos_deletados' => $deletados,
                'mensagem' => 'Item estornado com sucesso (incluindo subidas e fotos).'
            ]);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }
        // =========================================================================
    // POST /v1/carregamento/desfazer
    // Desfaz uma quantidade específica (subtract parcial)
    // Remove subidas correspondentes e RECALCULA qt_acumulada das restantes
    // =========================================================================
    private function desfazerCarregamentoLegado(Request $request, Response $response): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        $idembarque = (int)($input['idembarque'] ?? 0);
        $iditem = (int)($input['iditem'] ?? 0);
        $qtdDesfazer = round((float)($input['qtd'] ?? 0), 4);

        if ($idembarque <= 0 || $iditem <= 0 || $qtdDesfazer <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        try {
            $this->iniciarTransacaoSegura();

            // TRAVA 1: Embarque disponível
            $lock = $this->pdo->prepare("
                SELECT idembarque FROM embarque_pedido 
                WHERE idembarque = ? AND pex_conferido = 'N' 
                FOR UPDATE
            ");
            $lock->execute([$idembarque]);
            if (!$lock->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Embarque já foi conferido. Desfazer bloqueado.',
                    'codigo' => 'EMBARQUE_FINALIZADO'
                ], 409);
            }

            // TRAVA 2: Lock nos carregamentos do item
            $stmt = $this->pdo->prepare("
                SELECT id, qt_carregada, path_foto_conferencia, data_carregamento
                FROM pedido_item_carregamento
                WHERE idembarque = ? AND iditem = ?
                ORDER BY data_carregamento DESC
                FOR UPDATE
            ");
            $stmt->execute([$idembarque, $iditem]);
            $registros = $stmt->fetchAll();

            if (!$registros) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Nenhum carregamento encontrado para este item'], 404);
            }

            // =============================================================
            // ETAPA 1: Subtrai quantidade dos carregamentos (mais recente → antigo)
            // =============================================================
            $resto = $qtdDesfazer;
            $fotosParaDeletar = [];

            foreach ($registros as $reg) {
                if ($resto <= 0.0001) break;

                $qtdAtual = (float)$reg['qt_carregada'];
                $remover = min($resto, $qtdAtual);
                $novaQtd = round($qtdAtual - $remover, 4);

                if ($novaQtd <= 0.0001) {
                    // Remove registro inteiro
                    $this->pdo->prepare("
                        DELETE FROM pedido_item_carregamento WHERE id = ?
                    ")->execute([$reg['id']]);

                    if (!empty($reg['path_foto_conferencia'])) {
                        $fotosParaDeletar[] = $reg['path_foto_conferencia'];
                    }

                    $this->pdo->prepare("
                        DELETE FROM carregamento_fotos WHERE id_carregamento = ?
                    ")->execute([$reg['id']]);
                } else {
                    // Só reduz a quantidade
                    $this->pdo->prepare("
                        UPDATE pedido_item_carregamento 
                        SET qt_carregada = ? 
                        WHERE id = ?
                    ")->execute([$novaQtd, $reg['id']]);
                }

                $resto = round($resto - $remover, 4);
            }

            // =============================================================
            // ETAPA 2: Ajusta subidas — remove/atualiza as mais recentes
            // =============================================================
            $qtdDesfeita = round($qtdDesfazer - $resto, 4);

            if ($qtdDesfeita > 0.0001) {
                $stmtSubidas = $this->pdo->prepare("
                    SELECT id, qt_subida, path_foto, numero_subida
                    FROM carregamento_subidas
                    WHERE idembarque = ? AND iditem = ?
                    ORDER BY numero_subida DESC
                    FOR UPDATE
                ");
                $stmtSubidas->execute([$idembarque, $iditem]);
                $subidas = $stmtSubidas->fetchAll();

                $restoSub = $qtdDesfeita;
                foreach ($subidas as $sub) {
                    if ($restoSub <= 0.0001) break;

                    $qtSub = (float)$sub['qt_subida'];
                    $remover = min($restoSub, $qtSub);
                    $novaQt = round($qtSub - $remover, 4);

                    if ($novaQt <= 0.0001) {
                        // Remove subida inteira + foto
                        if (!empty($sub['path_foto'])) {
                            $fotosParaDeletar[] = $sub['path_foto'];
                        }
                        $this->pdo->prepare("
                            DELETE FROM carregamento_subidas WHERE id = ?
                        ")->execute([$sub['id']]);
                    } else {
                        $this->pdo->prepare("
                            UPDATE carregamento_subidas SET qt_subida = ? WHERE id = ?
                        ")->execute([$novaQt, $sub['id']]);
                    }

                    $restoSub = round($restoSub - $remover, 4);
                }

                // ✅ ETAPA 3: RECALCULA qt_acumulada das subidas restantes
                $stmtRestantes = $this->pdo->prepare("
                    SELECT id, qt_subida 
                    FROM carregamento_subidas 
                    WHERE idembarque = ? AND iditem = ?
                    ORDER BY numero_subida ASC
                    FOR UPDATE
                ");
                $stmtRestantes->execute([$idembarque, $iditem]);
                $subidasRestantes = $stmtRestantes->fetchAll();

                $acumulada = 0;
                foreach ($subidasRestantes as $sr) {
                    $acumulada = round($acumulada + (float)$sr['qt_subida'], 4);
                    $this->pdo->prepare("
                        UPDATE carregamento_subidas SET qt_acumulada = ? WHERE id = ?
                    ")->execute([$acumulada, $sr['id']]);
                }
            }

            $this->pdo->commit();

            // =============================================================
            // LIMPEZA DE FOTOS FORA DA TRANSAÇÃO
            // =============================================================
            $arquivosDeletados = 0;
            foreach ($fotosParaDeletar as $path) {
                $caminho = __DIR__ . '/../../../' . $path;
                if (is_file($caminho) && @unlink($caminho)) $arquivosDeletados++;
            }

            return $this->json($response, [
                'success' => true,
                'quantidade_desfeita' => $qtdDesfeita,
                'arquivos_deletados' => $arquivosDeletados,
                'aviso' => $resto > 0.0001
                    ? 'Quantidade pedida maior que o carregado. Desfeito o máximo possível.'
                    : null
            ]);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // DELETE /v1/carregamento/subida/{id_subida}
    // Estorna UMA subida específica — remove a foto vinculada e ajusta acumuladas
    // =========================================================================
      private function estornarSubidaLegado(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        // ✅ Aceita ID via body (POST) ou via URL (DELETE) — compatível com ambos
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idSubida = (int)($input['id_subida'] ?? ($args['idSubida'] ?? 0));
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);

        if ($idSubida <= 0 || $idusuario <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        try {
            $this->iniciarTransacaoSegura();

            // =============================================================
            // 1) Buscar a subida e travar
            // =============================================================
            $stmt = $this->pdo->prepare("
                SELECT id, id_carregamento, idembarque, iditem, 
                       qt_subida, qt_acumulada, numero_subida, path_foto
                FROM carregamento_subidas
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSubida]);
            $subida = $stmt->fetch();

            if (!$subida) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Subida não encontrada',
                    'codigo' => 'SUBIDA_NAO_ENCONTRADA'
                ], 404);
            }

            $idembarque = (int)$subida['idembarque'];
            $iditem = (int)$subida['iditem'];
            $qtSubida = (float)$subida['qt_subida'];
            $pathFoto = $subida['path_foto'];

            // =============================================================
            // 2) Embarque pode ser alterado?
            // =============================================================
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

            // =============================================================
            // 3) Subtrair a quantidade desta subida dos carregamentos
            //    (do mais recente para o mais antigo)
            // =============================================================
            $stmtCargas = $this->pdo->prepare("
                SELECT id, qt_carregada, path_foto_conferencia
                FROM pedido_item_carregamento
                WHERE idembarque = ? AND iditem = ?
                ORDER BY data_carregamento DESC
                FOR UPDATE
            ");
            $stmtCargas->execute([$idembarque, $iditem]);
            $registros = $stmtCargas->fetchAll();

            $resto = $qtSubida;
            $fotosParaDeletar = [];

            foreach ($registros as $reg) {
                if ($resto <= 0.0001) break;

                $qtdAtual = (float)$reg['qt_carregada'];
                $remover = min($resto, $qtdAtual);
                $novaQtd = round($qtdAtual - $remover, 4);

                if ($novaQtd <= 0.0001) {
                    $this->pdo->prepare("
                        DELETE FROM pedido_item_carregamento WHERE id = ?
                    ")->execute([$reg['id']]);

                    if (!empty($reg['path_foto_conferencia'])) {
                        $fotosParaDeletar[] = $reg['path_foto_conferencia'];
                    }

                    $this->pdo->prepare("
                        DELETE FROM carregamento_fotos WHERE id_carregamento = ?
                    ")->execute([$reg['id']]);
                } else {
                    $this->pdo->prepare("
                        UPDATE pedido_item_carregamento 
                        SET qt_carregada = ? 
                        WHERE id = ?
                    ")->execute([$novaQtd, $reg['id']]);
                }

                $resto = round($resto - $remover, 4);
            }

            // =============================================================
            // 4) Remover a subida
            // =============================================================
            $this->pdo->prepare("
                DELETE FROM carregamento_subidas WHERE id = ?
            ")->execute([$idSubida]);

            if (!empty($pathFoto)) {
                $fotosParaDeletar[] = $pathFoto;
            }

            // =============================================================
            // 5) Renumerar subidas restantes + recalcular acumuladas
            // =============================================================
            $stmtRestantes = $this->pdo->prepare("
                SELECT id, qt_subida 
                FROM carregamento_subidas 
                WHERE idembarque = ? AND iditem = ?
                ORDER BY numero_subida ASC
                FOR UPDATE
            ");
            $stmtRestantes->execute([$idembarque, $iditem]);
            $subidasRestantes = $stmtRestantes->fetchAll();

            $acumulada = 0;
            $novoNumero = 1;

            foreach ($subidasRestantes as $sr) {
                $acumulada = round($acumulada + (float)$sr['qt_subida'], 4);
                $this->pdo->prepare("
                    UPDATE carregamento_subidas 
                    SET qt_acumulada = ?, numero_subida = ?
                    WHERE id = ?
                ")->execute([$acumulada, $novoNumero, $sr['id']]);
                $novoNumero++;
            }

            // =============================================================
            // 6) Se não há mais carregamentos, resetar status do embarque
            // =============================================================
            $stmtTotal = $this->pdo->prepare("
                SELECT COUNT(*) FROM pedido_item_carregamento WHERE idembarque = ?
            ");
            $stmtTotal->execute([$idembarque]);
            $restantesCargas = (int)$stmtTotal->fetchColumn();

            if ($restantesCargas === 0) {
                $this->pdo->prepare("
                    UPDATE embarque_status_log 
                    SET status_atual = 'SEPARACAO', data_fim = NULL 
                    WHERE idembarque = ?
                ")->execute([$idembarque]);

                $this->pdo->prepare("
                    UPDATE embarque_pedido 
                    SET pex_embarque_carregamento = 'N', data_carregamento = '1900-01-01'
                    WHERE idembarque = ?
                ")->execute([$idembarque]);
            }

            $this->pdo->commit();

            // =============================================================
            // 7) Limpar arquivos do disco (fora da transação)
            // =============================================================
            $arquivosDeletados = 0;
            foreach (array_unique($fotosParaDeletar) as $path) {
                $caminho = __DIR__ . '/../../../' . $path;
                if (is_file($caminho) && @unlink($caminho)) $arquivosDeletados++;
            }

            return $this->json($response, [
                'success' => true,
                'id_subida_estornada' => $idSubida,
                'numero_subida_original' => (int)$subida['numero_subida'],
                'qt_estornada' => $qtSubida,
                'arquivos_deletados' => $arquivosDeletados,
                'subidas_restantes' => count($subidasRestantes),
                'mensagem' => "Subida #{$subida['numero_subida']} estornada com sucesso."
            ]);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }


        // =========================================================================
    // DELETE /v1/carregamento/subidas-todas/{idembarque}/{iditem}
    // Estorna TODAS as subidas de um item (equivalente ao estorno total)
    // =========================================================================
       private function estornarTodasSubidasLegado(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        // ✅ Aceita IDs via body (POST) ou via URL (DELETE) — compatível com ambos
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idembarque = (int)($input['idembarque'] ?? ($args['idembarque'] ?? 0));
        $iditem = (int)($input['iditem'] ?? ($args['iditem'] ?? 0));

        if ($idembarque <= 0 || $iditem <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        try {
            $this->iniciarTransacaoSegura();

            // TRAVA 1: Embarque disponível
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

        // TRAVA 2: Existe carregamento?
$stmt = $this->pdo->prepare("
    SELECT id FROM pedido_item_carregamento
    WHERE iditem = ? AND idembarque = ?
    FOR UPDATE
");
$stmt->execute([$iditem, $idembarque]);
$rows = $stmt->fetchAll();
if (count($rows) === 0) {
    $this->pdo->rollBack();
    return $this->json($response, ['error' => 'Item não foi carregado'], 404);
}

            // Coleta de fotos para deletar do disco
            $fotosParaDeletar = [];

            $stmtFotos = $this->pdo->prepare("
                SELECT path_foto_conferencia AS p FROM pedido_item_carregamento
                WHERE iditem = ? AND idembarque = ?
                UNION ALL
                SELECT caminho_foto AS p FROM carregamento_fotos
                WHERE iditem = ? AND idembarque = ?
                UNION ALL
                SELECT path_foto AS p FROM carregamento_subidas
                WHERE iditem = ? AND idembarque = ?
            ");
            $stmtFotos->execute([$iditem, $idembarque, $iditem, $idembarque, $iditem, $idembarque]);
            foreach ($stmtFotos->fetchAll() as $f) {
                if (!empty($f['p'])) $fotosParaDeletar[] = $f['p'];
            }

            // Delete em cascata manual
            $this->pdo->prepare("DELETE FROM carregamento_subidas WHERE iditem = ? AND idembarque = ?")
                      ->execute([$iditem, $idembarque]);
            $this->pdo->prepare("DELETE FROM carregamento_fotos WHERE iditem = ? AND idembarque = ?")
                      ->execute([$iditem, $idembarque]);
            $this->pdo->prepare("DELETE FROM pedido_item_carregamento WHERE iditem = ? AND idembarque = ?")
                      ->execute([$iditem, $idembarque]);

            // Rollback status se não há mais nada carregado
            $stmtRestantes = $this->pdo->prepare("
                SELECT COUNT(*) FROM pedido_item_carregamento WHERE idembarque = ?
            ");
            $stmtRestantes->execute([$idembarque]);
            if ((int)$stmtRestantes->fetchColumn() === 0) {
                $this->pdo->prepare("
                    UPDATE embarque_status_log 
                    SET status_atual = 'SEPARACAO', data_fim = NULL 
                    WHERE idembarque = ?
                ")->execute([$idembarque]);

                $this->pdo->prepare("
                    UPDATE embarque_pedido 
                    SET pex_embarque_carregamento = 'N', data_carregamento = '1900-01-01'
                    WHERE idembarque = ?
                ")->execute([$idembarque]);
            }

            $this->pdo->commit();

            // Limpar disco
            $arquivosDeletados = 0;
            foreach (array_unique($fotosParaDeletar) as $path) {
                $caminho = __DIR__ . '/../../../' . $path;
                if (is_file($caminho) && @unlink($caminho)) $arquivosDeletados++;
            }

            return $this->json($response, [
                'success' => true,
                'arquivos_deletados' => $arquivosDeletados,
                'mensagem' => 'Todas as subidas foram estornadas.'
            ]);

        } catch (\Throwable $e) {
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
  $this->iniciarTransacaoSegura();

        // TRAVA 1: Embarque disponível para finalização
        $stmtEmbarque = $this->pdo->prepare("
            SELECT idembarque FROM embarque_pedido
            WHERE idembarque = ?
              AND pex_conferido = 'N'
              AND (gerou_nf = 'S' OR idfilial = 6)
              AND pex_embarque_pronto = 'S'
              AND idfilial IN (1, 6)
            FOR UPDATE
        ");
        $stmtEmbarque->execute([$idembarque]);
        if (!$stmtEmbarque->fetchColumn()) {
            $this->pdo->rollBack();
            return $this->json($response, ['error' => 'Embarque não disponível'], 409);
        }

        // TRAVA 2: Lock nos itens do embarque
        $stmtLockItens = $this->pdo->prepare("
            SELECT pi.iditempedido FROM pedido_item pi
            JOIN pedido p ON p.idpedido = pi.idpedido
            WHERE p.idembarque = ? AND pi.ativo = 'S'
            FOR UPDATE OF pi
        ");
        $stmtLockItens->execute([$idembarque]);
        $stmtLockItens->fetchAll();

        // TRAVA 3: Lock nos carregamentos
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

        // ✅ TRAVA 6: Fotos obrigatórias (ignora registros dispensados por histórico)
        $stmtFotos = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM pedido_item_carregamento
            WHERE idembarque = ?
              AND qt_carregada > 0.0001
              AND (path_foto_conferencia IS NULL OR path_foto_conferencia = '')
              AND COALESCE(foto_dispensada, FALSE) = FALSE
        ");
        $stmtFotos->execute([$idembarque]);
        $fotosPendentes = (int)$stmtFotos->fetchColumn();

        if ($fotosPendentes > 0) {
            $this->pdo->rollBack();
            return $this->json($response, [
                'error' => "Existem {$fotosPendentes} carregamento(s) sem foto",
                'codigo' => 'FOTOS_PENDENTES',
                'fotos_pendentes' => $fotosPendentes
            ], 409);
        }

        // Atualiza status para CARREGADO
        $stmt = $this->pdo->prepare("
            INSERT INTO embarque_status_log (idembarque, status_atual, data_fim, idusuario)
            VALUES (:emb, 'CARREGADO', NOW(), :user)
            ON CONFLICT (idembarque) 
            DO UPDATE SET status_atual = 'CARREGADO', data_fim = NOW(), idusuario = EXCLUDED.idusuario
        ");
        $stmt->execute(['emb' => $idembarque, 'user' => $idusuario]);

        // Atualiza flags do embarque
        $this->pdo->prepare("
            UPDATE embarque_pedido 
            SET pex_conferido = 'S', data_carregamento = NOW(), pex_embarque_carregamento = 'S' 
            WHERE idembarque = ?
        ")->execute([$idembarque]);

        $this->pdo->commit();

        return $this->json($response, ['success' => true]);

    } catch (\Throwable $e) {
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
    SELECT 
        cf.*, 
        i.descricao as nome_item, 
        i.referencia, 
        u.username as nome_usuario,
        cs.numero_subida,
        cs.qt_subida,
        cs.id AS id_subida
    FROM carregamento_fotos cf
    JOIN item i ON i.iditem = cf.iditem
    LEFT JOIN usuario u ON u.idusuario = cf.idusuario
    LEFT JOIN carregamento_subidas cs 
           ON cs.idembarque = cf.idembarque 
          AND cs.iditem = cf.iditem 
          AND cs.path_foto = cf.caminho_foto
    WHERE cf.idembarque = ?
    ORDER BY cf.data_hora DESC
");
            $stmt->execute([$idembarque]);
            $fotos = $stmt->fetchAll();

            return $this->json($response, $fotos ?: []);

        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            return $response->withStatus(500);
        }
    }
      // =========================================================================
    // POST /v1/carregamento/foto
    // Aceita múltiplas fotos por item e exige vínculo com a subida correspondente
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
        $idSubida = (int)($params['id_subida'] ?? 0);

        if ($idembarque <= 0 || $iditem <= 0 || $idusuario <= 0 || $idCarregamento <= 0 || $idSubida <= 0) {
            return $this->json($response, [
                'error' => 'A foto deve ser vinculada a um embarque, item, carregamento e subida válidos.'
            ], 400);
        }

        if (($foto->getSize() ?? 0) > 15 * 1024 * 1024) {
            return $this->json($response, ['error' => 'A foto deve ter no máximo 15 MB'], 413);
        }

        // Validar MIME
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
        $sufixoSubida = $idSubida > 0 ? "_s{$idSubida}" : '';
        $nomeArquivo = sprintf(
            'emb_%d_item_%d_carga_%d%s_%s.%s',
            $idembarque,
            $iditem,
            $idCarregamento,
            $sufixoSubida,
            date('Ymd_His') . '_' . substr((string)microtime(), 2, 4),
            $ext
        );

        $caminhoRelativo = 'uploads/carregamento/' . $nomeArquivo;
        $caminhoAbsoluto = __DIR__ . '/../../../uploads/carregamento/' . $nomeArquivo;

        $diretorio = dirname($caminhoAbsoluto);
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }

        try {
           $this->iniciarTransacaoSegura();

            $stmtEmbarque = $this->pdo->prepare("
                SELECT idembarque
                FROM embarque_pedido
                WHERE idembarque = ?
                FOR UPDATE
            ");
            $stmtEmbarque->execute([$idembarque]);
            if (!$stmtEmbarque->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque não encontrado'], 404);
            }

            // ✅ Valida que o registro de carregamento existe
            $stmtCarga = $this->pdo->prepare("
                SELECT id FROM pedido_item_carregamento
                WHERE id = ?
                  AND idembarque = ?
                  AND iditem = ?
                FOR UPDATE
            ");
            $stmtCarga->execute([$idCarregamento, $idembarque, $iditem]);
            if (!$stmtCarga->fetchColumn()) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Registro de carregamento não encontrado'], 404);
            }

            // Valida que a subida pertence ao carregamento, embarque e item.
            $stmtSub = $this->pdo->prepare("
                SELECT id, qt_subida FROM carregamento_subidas
                WHERE id = ?
                  AND id_carregamento = ?
                  AND idembarque = ?
                  AND iditem = ?
                FOR UPDATE
            ");
            $stmtSub->execute([$idSubida, $idCarregamento, $idembarque, $iditem]);
            $subidaFoto = $stmtSub->fetch();
            if (!$subidaFoto) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Subida não corresponde a este carregamento',
                    'codigo' => 'SUBIDA_INVALIDA'
                ], 422);
            }

            $stmtEstorno = $this->pdo->prepare("
                SELECT COALESCE(SUM(quantidade_estornada), 0)
                FROM carregamento_estornos
                WHERE id_subida = ?
            ");
            $stmtEstorno->execute([$idSubida]);
            if ((float)$stmtEstorno->fetchColumn() >= (float)$subidaFoto['qt_subida'] - 0.0001) {
                $this->pdo->rollBack();
                return $this->json($response, [
                    'error' => 'Não é possível anexar foto a uma subida já estornada.',
                    'codigo' => 'SUBIDA_ESTORNADA'
                ], 409);
            }

            $foto->moveTo($caminhoAbsoluto);

            // Atualiza path principal
            $this->pdo->prepare("
                UPDATE pedido_item_carregamento
                SET path_foto_conferencia = ?
                WHERE id = ? AND idembarque = ? AND iditem = ?
            ")->execute([$caminhoRelativo, $idCarregamento, $idembarque, $iditem]);

            // Histórico geral
            $this->pdo->prepare("
                INSERT INTO carregamento_fotos 
                (idembarque, iditem, id_carregamento, id_subida, caminho_foto, idusuario, doca, data_hora)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $idembarque,
                $iditem,
                $idCarregamento,
                $idSubida,
                $caminhoRelativo,
                $idusuario,
                $doca
            ]);

            $this->pdo->prepare("
                UPDATE carregamento_subidas
                SET path_foto = ?
                WHERE id = ? AND idembarque = ? AND iditem = ?
            ")->execute([$caminhoRelativo, $idSubida, $idembarque, $iditem]);

            $this->pdo->commit();

            return $this->json($response, [
                'success' => true,
                'message' => 'Foto registrada com sucesso!',
                'caminho' => $caminhoRelativo,
                'id_carregamento' => $idCarregamento,
                'id_subida' => $idSubida ?: null,
                'nome_arquivo' => $nomeArquivo
            ]);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (file_exists($caminhoAbsoluto)) @unlink($caminhoAbsoluto);
            return $this->json($response, ['error' => 'Erro ao salvar foto: ' . $e->getMessage()], 500);
        }
    }
        // =========================================================================
    // GET /v1/carregamento/subidas/{idembarque}/{iditem}
    // Retorna o histórico completo de subidas de um item
    // =========================================================================
    public function getSubidas(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;

        $idembarque = (int)($args['idembarque'] ?? 0);
        $iditem = (int)($args['iditem'] ?? 0);

        if ($idembarque <= 0 || $iditem <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    cs.*,
                    u.username AS nome_usuario,
                    COALESCE(estornos.quantidade_estornada, 0)::float AS quantidade_estornada,
                    GREATEST(0, cs.qt_subida - COALESCE(estornos.quantidade_estornada, 0))::float AS quantidade_disponivel,
                    CASE
                        WHEN COALESCE(estornos.quantidade_estornada, 0) >= cs.qt_subida - 0.0001 THEN 'ESTORNADA'
                        WHEN COALESCE(estornos.quantidade_estornada, 0) > 0 THEN 'PARCIALMENTE_ESTORNADA'
                        ELSE 'ATIVA'
                    END AS status_estorno,
                    estornos.ultimo_motivo,
                    COALESCE(NULLIF(cs.path_foto, ''), foto_recente.caminho_foto) AS path_foto_auditoria
                FROM carregamento_subidas cs
                LEFT JOIN usuario u ON u.idusuario = cs.idusuario
                LEFT JOIN LATERAL (
                    SELECT
                        SUM(e.quantidade_estornada) AS quantidade_estornada,
                        (ARRAY_AGG(e.motivo ORDER BY e.criado_em DESC))[1] AS ultimo_motivo
                    FROM carregamento_estornos e
                    WHERE e.id_subida = cs.id
                ) estornos ON TRUE
                LEFT JOIN LATERAL (
                    SELECT cf.caminho_foto
                    FROM carregamento_fotos cf
                    WHERE cf.id_subida = cs.id
                    ORDER BY cf.data_hora DESC
                    LIMIT 1
                ) foto_recente ON TRUE
                WHERE cs.idembarque = ? AND cs.iditem = ?
                ORDER BY cs.numero_subida ASC
            ");
            $stmt->execute([$idembarque, $iditem]);
            $subidas = $stmt->fetchAll();

            return $this->json($response, $subidas ?: []);

        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }
}