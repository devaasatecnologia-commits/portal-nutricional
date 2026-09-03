<?php
namespace Nutricional\Controllers;

use PDO;
use Exception;

class ConsultaController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    // ======================================================================
    // GET /v1/embarques-ativos
    // ======================================================================
    public function getEmbarquesAtivos($request, $response)
    {
        try {
            $sql = "SELECT     
                    ep.idembarque, ep.idfilial, ep.qt_pedido, ep.totalpesobruto,
                    ep.valortotal, ep.data, ep.placa, ep.observacao as rota,
                    cliforemp.fantasia as entregador,
                    cli_transp.fantasia as fantasia_transportadora,
                    cli_transp.razao as razao_transportadora                 
                FROM embarque_pedido ep
                JOIN cliforemp ON (cliforemp.idcliforemp = ep.identregador)
                JOIN cliforemp cli_transp ON (cli_transp.idcliforemp = ep.idtransportadora)
                WHERE ep.idfilial IN (1, 6)
                  AND ep.gerou_nf IN ('N')
                  AND ep.data >= (CURRENT_DATE - INTERVAL '30 days')
                  AND pex_conf_pedido = 'N'
                  AND pex_conferido = 'N'
                ORDER BY ep.idembarque DESC
                LIMIT 100";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

// ======================================================================
// GET /v1/consulta/saldos/{idembarque}
// ======================================================================
public function getSaldos($request, $response, $args)
{
    $idembarque = (int)($args['idembarque'] ?? 0);
    $alerta = (int)($request->getQueryParams()['alerta'] ?? 0);
    
    if ($idembarque <= 0) {
        return $this->json($response, ['error' => 'ID do embarque inválido'], 400);
    }
    
    try {
        $sql = "
            SELECT 
                i.referencia,
                i.iditem,
                i.descricao,
                COALESCE(estoque.saldo, 0) as estoque_fisico,
                COALESCE(emb.qtd_embarque, 0) as qtd_embarque,
                COALESCE(pedidos_especificos.qtd_reservada, 0) as qtd_reservada,
                (COALESCE(estoque.saldo, 0) - COALESCE(pedidos_especificos.qtd_reservada, 0)) as estoque_disponivel,
                (COALESCE(estoque.saldo, 0) - COALESCE(pedidos_especificos.qtd_reservada, 0) - COALESCE(emb.qtd_embarque, 0)) as saldo_final
            FROM item i
            -- SUBQUERY: Itens do embarque atual
            JOIN (
                SELECT 
                    pi.iditem, 
                    SUM(pi.qt) as qtd_embarque
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idembarque = :emb
                  AND pi.ativo = 'S'
                GROUP BY pi.iditem
            ) emb ON emb.iditem = i.iditem
            -- SUBQUERY: Estoque físico
            LEFT JOIN (
                SELECT iditem, SUM(saldoatual) as saldo
                FROM estoque_filial
                WHERE idfilial IN (1, 6)
                GROUP BY iditem
            ) estoque ON estoque.iditem = i.iditem
            -- SUBQUERY: Pedidos dos clientes específicos (RESERVA)
            LEFT JOIN (
                SELECT 
                    pi.iditem, 
                    SUM(pi.qt) as qtd_reservada
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                WHERE p.idcliforemp IN (10117, 16595, 16596)
                  AND pi.ativo = 'S'
                  AND p.status  IN (1) 
                GROUP BY pi.iditem
            ) pedidos_especificos ON pedidos_especificos.iditem = i.iditem
            
            WHERE i.inativo = 'N'
              -- Filtra onde o saldo final (estoque - reserva - embarque) é negativo
              AND (COALESCE(estoque.saldo, 0) - COALESCE(pedidos_especificos.qtd_reservada, 0) - COALESCE(emb.qtd_embarque, 0)) < 0
            ORDER BY (COALESCE(estoque.saldo, 0) - COALESCE(pedidos_especificos.qtd_reservada, 0) - COALESCE(emb.qtd_embarque, 0)) ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['emb' => $idembarque]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->json($response, $result);
    } catch (Exception $e) {
        return $this->json($response, ['error' => $e->getMessage()], 500);
    }
}

    // ======================================================================
    // GET /v1/consulta/pedidos-item/{idembarque}/{iditem}
    // ======================================================================
    public function getPedidosItem($request, $response, $args)
    {
        $idembarque = (int)($args['idembarque'] ?? 0);
        $iditem = (int)($args['iditem'] ?? 0);
        
        if ($idembarque <= 0 || $iditem <= 0) {
            return $this->json($response, ['error' => 'Parâmetros inválidos'], 400);
        }
        
        try {
            $sql = "
                SELECT p.idpedido, pi.iditempedido, pi.qt, c.fantasia as cliente
                FROM pedido_item pi
                JOIN pedido p ON p.idpedido = pi.idpedido
                LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp
                WHERE p.idembarque = :emb AND pi.iditem = :item AND pi.ativo = 'S'
                ORDER BY p.idpedido
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['emb' => $idembarque, 'item' => $iditem]);
            return $this->json($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // POST /v1/consulta/editar-pedido (COM LOG + RECÁLCULO COMPLETO)
    // ======================================================================
    public function editarPedido($request, $response)
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $idpedido = (int)($input['idpedido'] ?? 0);
        $iditempedido = (int)($input['iditempedido'] ?? 0);
        $idembarque = (int)($input['idembarque'] ?? 0);
        $novaQtd = round((float)($input['nova_qtd'] ?? 0), 4);
        $idusuario = (int)($input['idusuario'] ?? 0);
        
        if ($idpedido <= 0 || $iditempedido <= 0 || $idembarque <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Buscar dados atuais do item
            $stmtInfo = $this->pdo->prepare("
                SELECT pi.*, i.descricao as nome_item
                FROM pedido_item pi
                JOIN item i ON i.iditem = pi.iditem
                WHERE pi.idpedido = :ped AND pi.iditempedido = :itemped
            ");
            $stmtInfo->execute(['ped' => $idpedido, 'itemped' => $iditempedido]);
            $info = $stmtInfo->fetch(\PDO::FETCH_ASSOC);
            
            if (!$info) throw new \Exception("Item não encontrado no pedido");
            
            $qtdAtual = (float)$info['qt'];
            
            // Nome do usuário para o log
            $stmtUser = $this->pdo->prepare("SELECT username FROM usuario WHERE idcliforemp = :uid");
            $stmtUser->execute(['uid' => $idusuario]);
            $username = $stmtUser->fetchColumn() ?: 'SISTEMA';
            
            // ═══════════════════════════════════════
            // GERAR LOG
            // ═══════════════════════════════════════
            $stmtMaxLog = $this->pdo->query("SELECT COALESCE(MAX(idlog), 0) + 1 FROM pedido_item_log WHERE idpedido = " . $idpedido);
            $idlog = (int)$stmtMaxLog->fetchColumn();
            
            $excluido = ($novaQtd <= 0.0001) ? 'S' : 'N';
            $motivo = ($novaQtd <= 0.0001) ? 'Item removido via consulta saldo' : 'Editou qt via consulta saldo';
            $novoValorTotal = ($novaQtd > 0 && $qtdAtual > 0) ? round(($novaQtd / $qtdAtual) * (float)$info['valortotal'], 2) : 0;
            
            $stmtLog = $this->pdo->prepare("
                INSERT INTO pedido_item_log (
                    idlog, idpedido, iditemold, iditemnew, iditempedido,
                    idempresa, idfilial, excluido, motivo,
                    quant_old, quant_new, quant_reserva_old, quant_reserva_new,
                    valorunit_old, valorunit_new, valortotal_old, valortotal_new,
                    percdesc_old, percdesc_new, perccomissao_old, perccomissao_new,
                    usuario, datahora, editandopedido,
                    percacresc_old, percacresc_new, qt_saldo_old, qt_saldo_new
                ) VALUES (
                    :idlog, :idpedido, :iditem, :iditem, :iditempedido,
                    :idempresa, :idfilial, :excluido, :motivo,
                    :qtd_old, :qtd_new, :reserva_old, :reserva_new,
                    :valorunit_old, :valorunit_new, :valortotal_old, :valortotal_new,
                    :percdesc, :percdesc, :perccomissao, :perccomissao,
                    :usuario, NOW(), 'S',
                    :percacresc, :percacresc, :qtd_old, :qtd_new
                )
            ");
            $stmtLog->execute([
                'idlog' => $idlog, 'idpedido' => $idpedido,
                'iditem' => (int)$info['iditem'], 'iditempedido' => $iditempedido,
                'idempresa' => (int)$info['idcodempresa'], 'idfilial' => (int)$info['idfilial'],
                'excluido' => $excluido, 'motivo' => $motivo,
                'qtd_old' => $qtdAtual, 'qtd_new' => $novaQtd,
                'reserva_old' => (float)$info['quant_reserva'], 'reserva_new' => 0,
                'valorunit_old' => $qtdAtual > 0 ? round((float)$info['valor'] / $qtdAtual, 6) : 0,
                'valorunit_new' => $qtdAtual > 0 ? round((float)$info['valor'] / $qtdAtual, 6) : 0,
                'valortotal_old' => round((float)$info['valortotal'], 2),
                'valortotal_new' => $novoValorTotal,
                'percdesc' => (float)$info['perc_desconto'],
                'perccomissao' => (float)$info['perc_comissao'],
                'usuario' => $username,
                'percacresc' => (float)$info['perc_acrescimo'],
            ]);
            
            // ═══════════════════════════════════════
            // ATUALIZAR ITEM
            // ═══════════════════════════════════════
            if ($novaQtd <= 0.0001) {
                $stmt = $this->pdo->prepare("
                    UPDATE pedido_item SET ativo = 'N', qt = 0, qt_embarque = 0
                    WHERE idpedido = :ped AND iditempedido = :itemped
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE pedido_item SET 
                        qt = :qtd, qt_embarque = :qtd,
                        valortotal = ROUND((valortotal / NULLIF(qt, 0)) * :qtd, 4),
                        valoricms = ROUND((valoricms / NULLIF(qt, 0)) * :qtd, 4),
                        valorpis = ROUND((valorpis / NULLIF(qt, 0)) * :qtd, 4),
                        valorcofins = ROUND((valorcofins / NULLIF(qt, 0)) * :qtd, 4),
                        valoripi = ROUND((valoripi / NULLIF(qt, 0)) * :qtd, 4),
                        valoriss = ROUND((valoriss / NULLIF(qt, 0)) * :qtd, 4),
                        valorbaseicms = ROUND((valorbaseicms / NULLIF(qt, 0)) * :qtd, 4),
                        valorbasepis = ROUND((valorbasepis / NULLIF(qt, 0)) * :qtd, 4),
                        valorbasecofins = ROUND((valorbasecofins / NULLIF(qt, 0)) * :qtd, 4),
                        valorbaseipi = ROUND((valorbaseipi / NULLIF(qt, 0)) * :qtd, 4),
                        valorbaseiss = ROUND((valorbaseiss / NULLIF(qt, 0)) * :qtd, 4),
                        valoricmsufdestino = ROUND((valoricmsufdestino / NULLIF(qt, 0)) * :qtd, 4),
                        valoricmsuforigem = ROUND((valoricmsuforigem / NULLIF(qt, 0)) * :qtd, 4),
                        valorfcp = ROUND((valorfcp / NULLIF(qt, 0)) * :qtd, 4),
                        qtpesobruto = ROUND((qtpesobruto / NULLIF(qt, 0)) * :qtd, 4),
                        qtpesoliquido = ROUND((qtpesoliquido / NULLIF(qt, 0)) * :qtd, 4),
                        valorfrete = ROUND((valorfrete / NULLIF(qt, 0)) * :qtd, 4),
                        valorseguro = ROUND((valorseguro / NULLIF(qt, 0)) * :qtd, 4),
                        valoroutrasdespesas = ROUND((valoroutrasdespesas / NULLIF(qt, 0)) * :qtd, 4)
                    WHERE idpedido = :ped AND iditempedido = :itemped
                ");
            }
            $stmt->execute(['qtd' => $novaQtd, 'ped' => $idpedido, 'itemped' => $iditempedido]);
            
            // ═══════════════════════════════════════
            // RECALCULAR TUDO (Pedido + Parcelas + Embarque)
            // ═══════════════════════════════════════
            $this->recalcularPedido($idpedido);
            $this->recalcularParcelas($idpedido);
            $this->recalcularEmbarque($idembarque, $idpedido);
            
            $this->pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'iditem' => (int)$info['iditem'],
                'message' => 'Pedido atualizado com sucesso. Log registrado.'
            ]);
            
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // POST /v1/consulta/remover-item-pedido (DELETE FÍSICO + RECÁLCULO)
    // ======================================================================
    public function removerItemPedido($request, $response)
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $idpedido = (int)($input['idpedido'] ?? 0);
        $iditempedido = (int)($input['iditempedido'] ?? 0);
        $idembarque = (int)($input['idembarque'] ?? 0);
        
        if ($idpedido <= 0 || $iditempedido <= 0 || $idembarque <= 0) {
            return $this->json($response, ['error' => 'Dados inválidos'], 400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // DELETE físico
            $stmt = $this->pdo->prepare("
                DELETE FROM pedido_item 
                WHERE idpedido = :ped AND iditempedido = :itemped
            ");
            $stmt->execute(['ped' => $idpedido, 'itemped' => $iditempedido]);
            
            if ($stmt->rowCount() === 0) {
                throw new \Exception("Item não encontrado para remover");
            }
            
            // Recalcular tudo
            $this->recalcularPedido($idpedido);
            $this->recalcularParcelas($idpedido);
            $this->recalcularEmbarque($idembarque, $idpedido);
            
            $this->pdo->commit();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Item removido permanentemente. Pedido, parcelas e embarque recalculados.'
            ]);
            
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ======================================================================
    
    /**
     * Recalcular totais do pedido (compatível com ERP)
     */
    private function recalcularPedido($idpedido)
    {
        $this->pdo->prepare("
            UPDATE pedido SET 
                valortotalitens = (SELECT COALESCE(SUM(pi.valortotal), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valortotalcalculado = (SELECT COALESCE(SUM(pi.valortotal), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valortotalsaldo = (SELECT COALESCE(SUM(pi.valortotal), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                pesobruto = (SELECT COALESCE(SUM(pi.qtpesobruto), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                pesoliquido = (SELECT COALESCE(SUM(pi.qtpesoliquido), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valorbaseicms = (SELECT COALESCE(SUM(pi.valorbaseicms), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valoricms = (SELECT COALESCE(SUM(pi.valoricms), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valorpis = (SELECT COALESCE(SUM(pi.valorpis), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valorcofins = (SELECT COALESCE(SUM(pi.valorcofins), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S'),
                valortotalipi = (SELECT COALESCE(SUM(pi.valoripi), 0) FROM pedido_item pi WHERE pi.idpedido = :ped AND pi.ativo = 'S')
            WHERE idpedido = :ped2
        ")->execute(['ped' => $idpedido, 'ped2' => $idpedido]);
    }
    
    /**
     * Recalcular parcelas do pedido (redistribui o valor total)
     */
    private function recalcularParcelas($idpedido)
    {
        // Buscar o novo valor total
        $stmtValor = $this->pdo->prepare("SELECT valortotalcalculado FROM pedido WHERE idpedido = :ped");
        $stmtValor->execute(['ped' => $idpedido]);
        $valorTotal = (float)$stmtValor->fetchColumn();
        
        if ($valorTotal <= 0) {
            $this->pdo->prepare("DELETE FROM pedido_parcela WHERE idpedido = :ped")
                ->execute(['ped' => $idpedido]);
            return;
        }
        
        // Contar parcelas
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM pedido_parcela WHERE idpedido = :ped");
        $stmtCount->execute(['ped' => $idpedido]);
        $qtdParcelas = (int)$stmtCount->fetchColumn();
        
        if ($qtdParcelas === 0) return;
        
        // Calcular valor por parcela
        $valorParcela = round($valorTotal / $qtdParcelas, 4);
        $somaParcelas = round($valorParcela * ($qtdParcelas - 1), 4);
        $ultimaParcela = round($valorTotal - $somaParcelas, 4);
        
        // Atualizar todas com valor base
        $this->pdo->prepare("UPDATE pedido_parcela SET valor = :valor WHERE idpedido = :ped")
            ->execute(['valor' => $valorParcela, 'ped' => $idpedido]);
        
        // Ajustar última parcela
        $this->pdo->prepare("
            UPDATE pedido_parcela SET valor = :valor 
            WHERE idpedido = :ped AND idparcela = (
                SELECT MAX(idparcela) FROM pedido_parcela WHERE idpedido = :ped2
            )
        ")->execute(['valor' => $ultimaParcela, 'ped' => $idpedido, 'ped2' => $idpedido]);
    }
    
    /**
     * Recalcular embarque (peso e valor)
     */
    private function recalcularEmbarque($idembarque, $idpedido)
    {
        // Atualizar peso do embarque
        $this->pdo->prepare("
            UPDATE embarque_pedido SET totalpesobruto = (
                SELECT COALESCE(SUM(p.pesobruto), 0) FROM pedido p WHERE p.idembarque = :emb
            ) WHERE idembarque = :emb
        ")->execute(['emb' => $idembarque]);
        
        // Atualizar valor na tabela de ligação
        $this->pdo->prepare("
            UPDATE embarque_pedido_item SET valortotalembarque = (
                SELECT COALESCE(SUM(p.valortotalitens), 0) FROM pedido p WHERE p.idembarque = :emb
            ) WHERE idembarque = :emb AND idpedido = :ped
        ")->execute(['emb' => $idembarque, 'ped' => $idpedido]);
    }

    // ======================================================================
    // MÉTODO AUXILIAR: Resposta JSON
    // ======================================================================
    private function json($response, $data, $status = 200)
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}