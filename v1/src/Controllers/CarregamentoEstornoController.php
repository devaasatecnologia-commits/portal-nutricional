<?php
namespace Nutricional\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CarregamentoEstornoController
{
    private PDO $pdo;

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

    private function validarMotivo($motivo): ?string
    {
        if (!is_string($motivo)) {
            return null;
        }

        $motivo = trim($motivo);
        $caracteres = preg_match_all('/./us', $motivo, $matches);
        return $caracteres !== false && $caracteres >= 3 && $caracteres <= 500
            ? $motivo
            : null;
    }

    private function iniciarTransacao(): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec("SET LOCAL lock_timeout = '5s'");
        $this->pdo->exec("SET LOCAL statement_timeout = '30s'");
    }

    private function travarEmbarque(int $idembarque): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT idembarque
            FROM embarque_pedido
            WHERE idembarque = ? AND pex_conferido = 'N'
            FOR UPDATE
        ");
        $stmt->execute([$idembarque]);
        return (bool)$stmt->fetchColumn();
    }

    private function gravarEstorno(
        int $idembarque,
        int $iditem,
        ?array $subida,
        int $idusuario,
        string $tipo,
        string $motivo,
        ?float $quantidadeSolicitada
    ): array {
        $idSubida = $subida ? (int)$subida['id'] : null;
        $quantidadeDisponivel = null;
        $alocacoes = [];

        if ($subida) {
            $stmt = $this->pdo->prepare("
                SELECT GREATEST(
                    0,
                    cs.qt_subida - COALESCE((
                        SELECT SUM(e.quantidade_estornada)
                        FROM carregamento_estornos e
                        WHERE e.id_subida = cs.id
                    ), 0)
                ) AS quantidade_disponivel
                FROM carregamento_subidas cs
                WHERE cs.id = ?
            ");
            $stmt->execute([$idSubida]);
            $quantidadeDisponivel = (float)$stmt->fetchColumn();
            if ($quantidadeDisponivel <= 0.0001) {
                return ['quantidade' => 0.0, 'id_evento' => null, 'legado' => false];
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    sa.id_carregamento,
                    sa.idpedido,
                    sa.iditempedido,
                    GREATEST(0, sa.quantidade - COALESCE((
                        SELECT SUM((alocacao->>'quantidade')::numeric)
                        FROM carregamento_estornos e
                        CROSS JOIN LATERAL jsonb_array_elements(e.alocacoes_snapshot) alocacao
                        WHERE e.id_subida = sa.id_subida
                          AND (alocacao->>'idpedido')::integer = sa.idpedido
                          AND (alocacao->>'iditempedido')::integer = sa.iditempedido
                    ), 0)) AS quantidade_disponivel
                FROM carregamento_subida_alocacoes sa
                WHERE sa.id_subida = ?
                ORDER BY sa.id DESC
            ");
            $stmt->execute([$idSubida]);
            $alocacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $legado = !$alocacoes;
        if ($quantidadeDisponivel === null && $quantidadeSolicitada === null) {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(qt_carregada), 0)
                FROM pedido_item_carregamento
                WHERE idembarque = ? AND iditem = ?
            ");
            $stmt->execute([$idembarque, $iditem]);
            $quantidadeDisponivel = (float)$stmt->fetchColumn();
        }
        $quantidadeAlvo = $quantidadeSolicitada === null
            ? (float)$quantidadeDisponivel
            : min($quantidadeSolicitada, $quantidadeDisponivel ?? $quantidadeSolicitada);
        $restante = $quantidadeAlvo;
        $snapshot = [];

        if (!$legado) {
            foreach ($alocacoes as $alocacao) {
                if ($restante <= 0.0001) {
                    break;
                }

                $disponivel = (float)$alocacao['quantidade_disponivel'];
                if ($disponivel <= 0.0001) {
                    continue;
                }

                $remover = min($restante, $disponivel);
                $carga = $this->buscarCargaParaAtualizar(
                    (int)$alocacao['id_carregamento'],
                    $idembarque,
                    $iditem
                );
                if (!$carga || (float)$carga['qt_carregada'] + 0.0001 < $remover) {
                    throw new \RuntimeException('A alocação da subida não corresponde mais ao carregamento atual.');
                }

                $snapshot[] = [
                    'id_carregamento' => (int)$alocacao['id_carregamento'],
                    'idpedido' => (int)$alocacao['idpedido'],
                    'iditempedido' => (int)$alocacao['iditempedido'],
                    'quantidade' => round($remover, 4)
                ];
                $restante = round($restante - $remover, 4);
            }
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id, idpedido, iditempedido, qt_carregada
                FROM pedido_item_carregamento
                WHERE idembarque = ? AND iditem = ?
                ORDER BY data_carregamento DESC, id DESC
                FOR UPDATE
            ");
            $stmt->execute([$idembarque, $iditem]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $carga) {
                if ($restante <= 0.0001) {
                    break;
                }

                $remover = min($restante, (float)$carga['qt_carregada']);
                if ($remover <= 0.0001) {
                    continue;
                }

                $snapshot[] = [
                    'id_carregamento' => (int)$carga['id'],
                    'idpedido' => (int)$carga['idpedido'],
                    'iditempedido' => (int)$carga['iditempedido'],
                    'quantidade' => round($remover, 4),
                    'origem_alocacao' => 'legado_sem_vinculo_historico'
                ];
                $restante = round($restante - $remover, 4);
            }
        }

        $quantidadeEstornada = round($quantidadeAlvo - $restante, 4);
        if ($quantidadeEstornada <= 0.0001) {
            if ($subida && $quantidadeAlvo > 0.0001) {
                throw new \RuntimeException('Não foi possível localizar saldo operacional para uma subida ainda ativa.');
            }
            return ['quantidade' => 0.0, 'id_evento' => null, 'legado' => $legado];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO carregamento_estornos
                (idembarque, iditem, id_subida, tipo, idusuario,
                 numero_subida_original, quantidade_estornada,
                 caminho_foto_snapshot, motivo, alocacoes_snapshot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CAST(? AS jsonb))
            RETURNING id
        ");
        $stmt->execute([
            $idembarque,
            $iditem,
            $idSubida,
            $tipo,
            $idusuario,
            $subida ? (int)$subida['numero_subida'] : null,
            $quantidadeEstornada,
            $subida['path_foto'] ?? null,
            $motivo,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        ]);
        $idEvento = (int)$stmt->fetchColumn();

        foreach ($snapshot as $alocacao) {
            $carga = $this->buscarCargaParaAtualizar(
                $alocacao['id_carregamento'],
                $idembarque,
                $iditem
            );
            if (!$carga || (float)$carga['qt_carregada'] + 0.0001 < $alocacao['quantidade']) {
                throw new \RuntimeException('O saldo mudou durante o estorno; a operação foi cancelada.');
            }

            $novaQuantidade = round((float)$carga['qt_carregada'] - $alocacao['quantidade'], 4);
            if ($novaQuantidade <= 0.0001) {
                $this->pdo->prepare("
                    UPDATE pedido_item_carregamento
                    SET qt_carregada = 0,
                        path_foto_conferencia = NULL
                    WHERE id = ?
                ")->execute([$alocacao['id_carregamento']]);
                continue;
            }

            $this->pdo->prepare("
                UPDATE pedido_item_carregamento
                SET qt_carregada = ?
                WHERE id = ?
            ")->execute([$novaQuantidade, $alocacao['id_carregamento']]);
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM pedido_item_carregamento
            WHERE idembarque = ?
              AND qt_carregada > 0.0001
        ");
        $stmt->execute([$idembarque]);
        if ((int)$stmt->fetchColumn() === 0) {
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

        return [
            'quantidade' => $quantidadeEstornada,
            'id_evento' => $idEvento,
            'legado' => $legado
        ];
    }

    private function buscarCargaParaAtualizar(int $id, int $idembarque, int $iditem): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, qt_carregada
            FROM pedido_item_carregamento
            WHERE id = ? AND idembarque = ? AND iditem = ?
            FOR UPDATE
        ");
        $stmt->execute([$id, $idembarque, $iditem]);
        $carga = $stmt->fetch(PDO::FETCH_ASSOC);
        return $carga ?: null;
    }

    private function buscarSubidasAtivas(int $idembarque, int $iditem): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cs.*
            FROM carregamento_subidas cs
            WHERE cs.idembarque = ? AND cs.iditem = ?
              AND cs.qt_subida > COALESCE((
                  SELECT SUM(e.quantidade_estornada)
                  FROM carregamento_estornos e
                  WHERE e.id_subida = cs.id
              ), 0) + 0.0001
            ORDER BY cs.numero_subida DESC, cs.id DESC
            FOR UPDATE
        ");
        $stmt->execute([$idembarque, $iditem]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function estornarSubida(Request $request, Response $response): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idSubida = (int)($input['id_subida'] ?? 0);
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
        $motivo = $this->validarMotivo($input['motivo'] ?? null);
        if ($idSubida <= 0 || $idusuario <= 0 || $motivo === null) {
            return $this->json($response, ['error' => 'Informe uma subida válida e um motivo de 3 a 500 caracteres.'], 400);
        }

        try {
            $this->iniciarTransacao();
            $stmt = $this->pdo->prepare("SELECT idembarque FROM carregamento_subidas WHERE id = ?");
            $stmt->execute([$idSubida]);
            $idembarque = (int)$stmt->fetchColumn();
            if ($idembarque <= 0) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Subida não encontrada'], 404);
            }
            if (!$this->travarEmbarque($idembarque)) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque já foi conferido. Estorno bloqueado.'], 409);
            }

            $stmt = $this->pdo->prepare("SELECT * FROM carregamento_subidas WHERE id = ? FOR UPDATE");
            $stmt->execute([$idSubida]);
            $subida = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$subida) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Subida não encontrada'], 404);
            }

            $iditem = (int)$subida['iditem'];

            $resultado = $this->gravarEstorno(
                $idembarque,
                $iditem,
                $subida,
                $idusuario,
                'SUBIDA',
                $motivo,
                null
            );
            if ($resultado['quantidade'] <= 0) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Esta subida já foi estornada integralmente.'], 409);
            }

            $this->pdo->commit();
            return $this->json($response, [
                'success' => true,
                'id_evento_estorno' => $resultado['id_evento'],
                'id_subida_estornada' => $idSubida,
                'numero_subida_original' => (int)$subida['numero_subida'],
                'qt_estornada' => $resultado['quantidade'],
                'alocacao_legada' => $resultado['legado'],
                'mensagem' => 'Estorno registrado; a subida e suas fotos foram preservadas no histórico.'
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CarregamentoEstorno] ' . $e->getMessage());
            return $this->json($response, ['error' => 'Não foi possível registrar o estorno.'], 500);
        }
    }

    public function estornarTodasSubidas(Request $request, Response $response): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idembarque = (int)($input['idembarque'] ?? 0);
        $iditem = (int)($input['iditem'] ?? 0);
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
        $motivo = $this->validarMotivo($input['motivo'] ?? null);
        if ($idembarque <= 0 || $iditem <= 0 || $idusuario <= 0 || $motivo === null) {
            return $this->json($response, ['error' => 'Informe item, embarque e motivo de 3 a 500 caracteres.'], 400);
        }

        try {
            $this->iniciarTransacao();
            if (!$this->travarEmbarque($idembarque)) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque já foi conferido. Estorno bloqueado.'], 409);
            }

            $subidas = $this->buscarSubidasAtivas($idembarque, $iditem);
            $total = 0.0;
            foreach ($subidas as $subida) {
                $resultado = $this->gravarEstorno(
                    $idembarque,
                    $iditem,
                    $subida,
                    $idusuario,
                    'TODAS',
                    $motivo,
                    null
                );
                $total += $resultado['quantidade'];
            }

            if (!$subidas) {
                $resultado = $this->gravarEstorno(
                    $idembarque,
                    $iditem,
                    null,
                    $idusuario,
                    'TODAS',
                    $motivo,
                    null
                );
                $total += $resultado['quantidade'];
            }

            if ($total <= 0.0001) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Nenhuma quantidade carregada disponível para estorno.'], 404);
            }

            $this->pdo->commit();
            return $this->json($response, [
                'success' => true,
                'quantidade_estornada' => round($total, 4),
                'subidas_preservadas' => count($subidas),
                'mensagem' => 'Estornos registrados; as subidas e fotos foram preservadas no histórico.'
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CarregamentoEstorno] ' . $e->getMessage());
            return $this->json($response, ['error' => 'Não foi possível registrar os estornos.'], 500);
        }
    }

    public function estornarItem(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $iditem = (int)($args['iditem'] ?? 0);
        $idembarque = (int)($args['idembarque'] ?? 0);
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
        $motivo = $this->validarMotivo($input['motivo'] ?? null);
        if ($idembarque <= 0 || $iditem <= 0 || $idusuario <= 0 || $motivo === null) {
            return $this->json($response, ['error' => 'Informe item, embarque e motivo de 3 a 500 caracteres.'], 400);
        }

        try {
            $this->iniciarTransacao();
            if (!$this->travarEmbarque($idembarque)) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque já foi conferido. Estorno bloqueado.'], 409);
            }

            $subidas = $this->buscarSubidasAtivas($idembarque, $iditem);
            $total = 0.0;
            foreach ($subidas as $subida) {
                $total += $this->gravarEstorno(
                    $idembarque,
                    $iditem,
                    $subida,
                    $idusuario,
                    'ITEM',
                    $motivo,
                    null
                )['quantidade'];
            }
            if (!$subidas) {
                $total = $this->gravarEstorno(
                    $idembarque,
                    $iditem,
                    null,
                    $idusuario,
                    'ITEM',
                    $motivo,
                    null
                )['quantidade'];
            }
            if ($total <= 0.0001) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Nenhuma quantidade carregada disponível para estorno.'], 404);
            }

            $this->pdo->commit();
            return $this->json($response, [
                'success' => true,
                'quantidade_estornada' => round($total, 4),
                'mensagem' => 'Item estornado; histórico e fotos foram preservados.'
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CarregamentoEstorno] ' . $e->getMessage());
            return $this->json($response, ['error' => 'Não foi possível registrar o estorno do item.'], 500);
        }
    }

    public function desfazerCarregamento(Request $request, Response $response): Response
    {
        if ($denied = $this->validarAcesso($request, $response)) return $denied;
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idembarque = (int)($input['idembarque'] ?? 0);
        $iditem = (int)($input['iditem'] ?? 0);
        $quantidade = round((float)($input['qtd'] ?? 0), 4);
        $idusuario = (int)(($request->getAttribute('user') ?? [])['idusuario'] ?? 0);
        $motivo = $this->validarMotivo($input['motivo'] ?? null);
        if ($idembarque <= 0 || $iditem <= 0 || $quantidade <= 0 || $idusuario <= 0 || $motivo === null) {
            return $this->json($response, ['error' => 'Informe item, embarque, quantidade e motivo de 3 a 500 caracteres.'], 400);
        }

        try {
            $this->iniciarTransacao();
            if (!$this->travarEmbarque($idembarque)) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Embarque já foi conferido. Desfazer bloqueado.'], 409);
            }

            $subidas = $this->buscarSubidasAtivas($idembarque, $iditem);
            $restante = $quantidade;
            $desfeita = 0.0;
            foreach ($subidas as $subida) {
                if ($restante <= 0.0001) break;
                $resultado = $this->gravarEstorno(
                    $idembarque,
                    $iditem,
                    $subida,
                    $idusuario,
                    'DESFAZER',
                    $motivo,
                    $restante
                );
                $desfeita += $resultado['quantidade'];
                $restante = round($restante - $resultado['quantidade'], 4);
            }

            if ($restante > 0.0001 && !$subidas) {
                $resultado = $this->gravarEstorno(
                    $idembarque,
                    $iditem,
                    null,
                    $idusuario,
                    'DESFAZER',
                    $motivo,
                    $restante
                );
                $desfeita += $resultado['quantidade'];
                $restante = round($restante - $resultado['quantidade'], 4);
            }

            if ($desfeita <= 0.0001) {
                $this->pdo->rollBack();
                return $this->json($response, ['error' => 'Nenhuma quantidade carregada disponível para desfazer.'], 404);
            }

            $this->pdo->commit();
            return $this->json($response, [
                'success' => true,
                'quantidade_desfeita' => round($desfeita, 4),
                'aviso' => $restante > 0.0001
                    ? 'A quantidade solicitada excede o saldo carregado; foi desfeito o máximo possível.'
                    : null
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CarregamentoEstorno] ' . $e->getMessage());
            return $this->json($response, ['error' => 'Não foi possível desfazer a quantidade carregada.'], 500);
        }
    }
}
