<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MonitorController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/monitor/embarques
     * Retorna o painel de monitoramento logístico (igual ao get_monitoramento_logistico do legado)
     */
    public function getMonitoramento(Request $request, Response $response): Response
    {
        try {
            $sql = "SELECT 
                        COALESCE(
                            (SELECT u.username FROM pedido_item_carregamento c4 JOIN usuario u ON u.idcliforemp = c4.id_conferente WHERE c4.idembarque = ep.idembarque ORDER BY c4.data_carregamento DESC LIMIT 1),
                            (SELECT u.username FROM pedido_item_logistica l4 JOIN usuario u ON u.idcliforemp = l4.id_separador WHERE l4.idembarque = ep.idembarque ORDER BY l4.data_separacao DESC LIMIT 1),
                            u_log.username, '---'
                        ) AS operador,
                        ep.idembarque, 
                        ep.observacao AS rota, 
                        ep.placa,
                        ep.totalpesobruto as peso,
                        (SELECT fantasia FROM cliforemp WHERE idcliforemp = ep.identregador) as motorista,
                        CASE 
                            WHEN ep.pex_embarque_carregamento = 'S' OR ep.pex_conferido = 'S' THEN 'CARREGADO'
                            WHEN ep.pex_embarque_pronto = 'S' THEN 'CONCLUIDO'
                            WHEN (SELECT COUNT(*) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) > 0 THEN 'SEPARACAO'
                            ELSE 'PENDENTE'
                        END as status_atual,
                        (SELECT COUNT(DISTINCT pi.iditem) FROM pedido_item pi JOIN pedido p ON p.idpedido = pi.idpedido WHERE p.idembarque = ep.idembarque AND pi.ativo = 'S') AS total_itens_unicos,
                        (SELECT COUNT(DISTINCT l2.iditem) FROM pedido_item_logistica l2 WHERE l2.idembarque = ep.idembarque AND l2.status_separacao = 2) AS itens_concluidos_sep,
                        (SELECT COUNT(DISTINCT c2.iditem) FROM pedido_item_carregamento c2 WHERE c2.idembarque = ep.idembarque AND (SELECT SUM(qt_carregada) FROM pedido_item_carregamento WHERE iditem = c2.iditem AND idembarque = ep.idembarque) >= (SELECT SUM(qt) FROM pedido_item pi JOIN pedido p ON p.idpedido = pi.idpedido WHERE p.idembarque = ep.idembarque AND pi.iditem = c2.iditem AND pi.ativo = 'S') - 0.01) AS itens_concluidos_car,
                        (SELECT i.descricao || '|' || i.path_foto_master || '|' || to_char(l3.data_separacao, 'HH24:MI:SS') || '|' || 
                                (SELECT SUM(qt_separada) FROM pedido_item_logistica WHERE iditem = l3.iditem AND idembarque = ep.idembarque) || '|' ||
                                (SELECT SUM(pi2.qt) FROM pedido_item pi2 JOIN pedido p2 ON p2.idpedido = pi2.idpedido WHERE pi2.iditem = l3.iditem AND p2.idembarque = ep.idembarque AND pi2.ativo = 'S') || '|' ||
                                u_sep.username
                         FROM pedido_item_logistica l3 JOIN item i ON i.iditem = l3.iditem JOIN usuario u_sep ON u_sep.idcliforemp = l3.id_separador
                         WHERE l3.idembarque = ep.idembarque ORDER BY l3.data_separacao DESC LIMIT 1) as last_sep_info,
                        (SELECT i.descricao || '|' || i.path_foto_master || '|' || to_char(c3.data_carregamento, 'HH24:MI:SS') || '|' || 
                                (SELECT SUM(qt_carregada) FROM pedido_item_carregamento WHERE iditem = c3.iditem AND idembarque = ep.idembarque) || '|' ||
                                (SELECT SUM(pi3.qt) FROM pedido_item pi3 JOIN pedido p3 ON p3.idpedido = pi3.idpedido WHERE pi3.iditem = c3.iditem AND p3.idembarque = ep.idembarque AND pi3.ativo = 'S') || '|' ||
                                u_car.username
                         FROM pedido_item_carregamento c3 JOIN item i ON i.iditem = c3.iditem JOIN usuario u_car ON u_car.idcliforemp = c3.id_conferente
                         WHERE c3.idembarque = ep.idembarque ORDER BY c3.data_carregamento DESC LIMIT 1) as last_car_info,
                        GREATEST(COALESCE(s.data_inicio, '1900-01-01'), COALESCE((SELECT MAX(data_separacao) FROM pedido_item_logistica WHERE idembarque = ep.idembarque), '1900-01-01')) AS ultima_atividade
                    FROM embarque_pedido ep
                    LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    LEFT JOIN usuario u_log ON u_log.idcliforemp = s.idusuario
                    WHERE ep.data >= (CURRENT_DATE - INTERVAL '30 days') AND ep.idfilial IN (1,6)
                    GROUP BY 
                        ep.idembarque, ep.observacao, ep.placa, ep.identregador, ep.pex_embarque_carregamento, 
                        ep.pex_conferido, ep.pex_embarque_pronto, u_log.username, s.data_inicio
                    ORDER BY ultima_atividade DESC, ep.idembarque DESC";
            
            $stmt = $this->pdo->prepare($sql);
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
    
    /**
     * GET /v1/monitor/detalhes/{idembarque}
     * Retorna o log de bips (separação e carga) de um embarque específico
     */
    public function getDetalhes(Request $request, Response $response, array $args): Response
    {
        $idembarque = (int)$args['idembarque'];
        
        try {
            $sql = "SELECT i.descricao as produto, i.path_foto_master as foto, l.qt_separada as qtd, u.username as operador, 
                           to_char(l.data_separacao, 'HH24:MI:SS') as hora, 'SEPARAÇÃO' as etapa
                    FROM pedido_item_logistica l
                    JOIN item i ON i.iditem = l.iditem
                    JOIN usuario u ON u.idcliforemp = l.id_separador
                    WHERE l.idembarque = ?
                    UNION ALL
                    SELECT i.descricao, i.path_foto_master, c.qt_carregada, u.username, 
                           to_char(c.data_carregamento, 'HH24:MI:SS'), 'CARGA'
                    FROM pedido_item_carregamento c
                    JOIN item i ON i.iditem = c.iditem
                    JOIN usuario u ON u.idcliforemp = c.id_conferente
                    WHERE c.idembarque = ?
                    ORDER BY hora DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idembarque, $idembarque]);
            $data = $stmt->fetchAll();
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    // No MonitorController.php

/**
 * GET /v1/monitor/historico?inicio=YYYY-MM-DD&fim=YYYY-MM-DD
 */
public function getHistorico(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $inicio = $params['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fim = $params['fim'] ?? date('Y-m-d');
    $filtroUser = (int)($params['usuario'] ?? 0);

    try {
        $sql = "SELECT 
                    ep.idembarque, ep.observacao as rota, ep.placa,
                    (SELECT fantasia FROM cliforemp WHERE idcliforemp = ep.identregador) as motorista,
                    u.username as operador_principal,
                    s.status_atual,
                    s.data_inicio as inicio_op,
                    s.data_fim as fim_op,
                    (SELECT COUNT(*) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) as total_bips
                FROM embarque_pedido ep
                JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                LEFT JOIN usuario u ON u.idcliforemp = s.idusuario
                WHERE s.data_inicio::date BETWEEN :ini AND :fim ";
        if ($filtroUser > 0) $sql .= " AND s.idusuario = :user ";
        $sql .= " ORDER BY s.data_inicio DESC";

        $stmt = $this->pdo->prepare($sql);
        $params = [':ini' => $inicio, ':fim' => $fim];
        if ($filtroUser > 0) $params[':user'] = $filtroUser;
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
}