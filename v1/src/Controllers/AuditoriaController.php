<?php
namespace Nutricional\Controllers;

use PDO;
use Exception;

class AuditoriaController {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = \getPDO();  
        
        if (!$this->pdo) {
            error_log("=== ERRO: PDO NÃO INICIALIZADO NO AUDITORIACONTROLLER ===");
        }
    }
    
    /**
     * GET /v1/auditoria/resumo
     * Retorna resumo estatístico da auditoria
     */
    public function getResumo($request, $response) {
        $params = $request->getQueryParams();
        $data_inicio = $params['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $data_fim = $params['fim'] ?? date('Y-m-d');
        
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT ep.idembarque) as total_embarques,
                        COUNT(DISTINCT CASE WHEN s.status_atual = 'CARREGADO' THEN ep.idembarque END) as finalizados,
                        COUNT(DISTINCT CASE WHEN s.status_atual IN ('SEPARACAO','CONCLUIDO') THEN ep.idembarque END) as em_andamento,
                        COUNT(DISTINCT CASE WHEN s.status_atual = 'PENDENTE' THEN ep.idembarque END) as pendentes,
                        COALESCE(SUM(ep.totalpesobruto), 0)::float as total_peso,
                        (SELECT COUNT(*) FROM pedido_item_logistica l2 
                         JOIN embarque_status_log s2 ON s2.idembarque = l2.idembarque
                         WHERE s2.data_inicio::date BETWEEN :ini AND :fim) as total_bips_separacao,
                        (SELECT COUNT(*) FROM pedido_item_carregamento c2 
                         JOIN embarque_status_log s2 ON s2.idembarque = c2.idembarque
                         WHERE s2.data_inicio::date BETWEEN :ini AND :fim) as total_bips_carregamento
                    FROM embarque_pedido ep
                    LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    WHERE s.data_inicio::date BETWEEN :ini AND :fim
                    AND ep.idfilial IN (1,6)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':ini' => $data_inicio, ':fim' => $data_fim]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * GET /v1/auditoria/ranking
     * Retorna ranking de operadores
     */
    public function getRankingOperadores($request, $response) {
        $params = $request->getQueryParams();
        $data_inicio = $params['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $data_fim = $params['fim'] ?? date('Y-m-d');
        
        try {
            $sql = "SELECT 
                        u.username as operador,
                        COUNT(DISTINCT l.idembarque) as embarques_trabalhados,
                        COUNT(*) as total_bips,
                        COALESCE(SUM(l.qt_separada), 0)::float as total_separado,
                        MAX(l.data_separacao) as ultima_atividade
                    FROM pedido_item_logistica l
                    JOIN usuario u ON u.idcliforemp = l.id_separador
                    JOIN embarque_status_log s ON s.idembarque = l.idembarque
                    WHERE s.data_inicio::date BETWEEN :ini AND :fim
                    GROUP BY u.idcliforemp, u.username
                    ORDER BY total_bips DESC
                    LIMIT 10";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':ini' => $data_inicio, ':fim' => $data_fim]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * GET /v1/auditoria/timeline/{idembarque}
     * Retorna timeline completa do embarque
     */
    public function getTimeline($request, $response, $args) {
        $idembarque = intval($args['idembarque'] ?? 0);
        
        try {
            $sql = "(
                        SELECT 
                            'separacao' as tipo,
                            i.descricao as produto,
                            i.path_foto_master as foto,
                            l.qt_separada as quantidade,
                            u.username as operador,
                            to_char(l.data_separacao, 'HH24:MI:SS') as hora,
                            to_char(l.data_separacao, 'YYYY-MM-DD HH24:MI:SS') as timestamp_completo,
                            CASE 
                                WHEN l.status_separacao = 2 THEN 'Concluído'
                                WHEN l.status_separacao = 1 THEN 'Parcial'
                                ELSE 'Iniciado'
                            END as status_desc
                        FROM pedido_item_logistica l
                        JOIN item i ON i.iditem = l.iditem
                        JOIN usuario u ON u.idcliforemp = l.id_separador
                        WHERE l.idembarque = ?
                    )
                    UNION ALL
                    (
                        SELECT 
                            'carregamento' as tipo,
                            i.descricao as produto,
                            i.path_foto_master as foto,
                            c.qt_carregada as quantidade,
                            u.username as operador,
                            to_char(c.data_carregamento, 'HH24:MI:SS') as hora,
                            to_char(c.data_carregamento, 'YYYY-MM-DD HH24:MI:SS') as timestamp_completo,
                            'Carregado' as status_desc
                        FROM pedido_item_carregamento c
                        JOIN item i ON i.iditem = c.iditem
                        JOIN usuario u ON u.idcliforemp = c.id_conferente
                        WHERE c.idembarque = ?
                    )
                    UNION ALL
                    (
                        SELECT 
                            'status' as tipo,
                            'Mudança de Status' as produto,
                            NULL as foto,
                            NULL as quantidade,
                            COALESCE(u.username, 'Sistema') as operador,
                            to_char(s.data_inicio, 'HH24:MI:SS') as hora,
                            to_char(s.data_inicio, 'YYYY-MM-DD HH24:MI:SS') as timestamp_completo,
                            s.status_atual as status_desc
                        FROM embarque_status_log s
                        LEFT JOIN usuario u ON u.idcliforemp = s.idusuario
                        WHERE s.idembarque = ?
                    )
                    ORDER BY timestamp_completo ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idembarque, $idembarque, $idembarque]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * GET /v1/auditoria/embarque/{idembarque}
     * Retorna detalhes de um embarque específico
     */
    public function getDetalhesEmbarque($request, $response, $args) {
        $idembarque = intval($args['idembarque'] ?? 0);
        
        try {
            $sql = "SELECT 
                        ep.idembarque,
                        ep.observacao as rota,
                        ep.placa,
                        ep.totalpesobruto as peso_total,
                        (SELECT fantasia FROM cliforemp WHERE idcliforemp = ep.identregador) as motorista,
                        s.status_atual,
                        to_char(s.data_inicio, 'DD/MM/YYYY HH24:MI') as data_inicio,
                        to_char(s.data_fim, 'DD/MM/YYYY HH24:MI') as data_fim,
                        u.username as operador_responsavel,
                        (SELECT COUNT(DISTINCT iditem) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) as itens_separados,
                        (SELECT COUNT(DISTINCT iditem) FROM pedido_item_carregamento WHERE idembarque = ep.idembarque) as itens_carregados,
                        (SELECT COUNT(DISTINCT pi.iditem) FROM pedido_item pi 
                         JOIN pedido p ON p.idpedido = pi.idpedido 
                         WHERE p.idembarque = ep.idembarque AND pi.ativo = 'S') as total_itens
                    FROM embarque_pedido ep
                    LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    LEFT JOIN usuario u ON u.idcliforemp = s.idusuario
                    WHERE ep.idembarque = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idembarque]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
/**
 * GET /v1/auditoria/itens/{idembarque}[/{tipo}]
 * Retorna itens de um embarque com fotos - AGRUPADO POR ITEM E DATA/HORA
 */
public function getItensEmbarque($request, $response, $args) {
    $idembarque = intval($args['idembarque'] ?? 0);
    $tipo = $args['tipo'] ?? 'todos';
    
    try {
        $itens = [];
        
        if ($tipo == 'separacao' || $tipo == 'todos') {
            $sqlSep = "SELECT 
                        'separacao' as tipo,
                        i.iditem,
                        i.descricao as produto,
                        i.referencia,
                        i.path_foto_master as foto,
                        l.qt_separada as quantidade,
                        (SELECT SUM(qt_separada) FROM pedido_item_logistica WHERE iditem = i.iditem AND idembarque = ?) as quantidade_total,
                        u.username as operador,
                        to_char(l.data_separacao, 'DD/MM/YYYY HH24:MI:SS') as data_hora,
                        l.status_separacao,
                        CASE 
                            WHEN l.status_separacao = 2 THEN 'Concluído'
                            WHEN l.status_separacao = 1 THEN 'Parcial'
                            ELSE 'Iniciado'
                        END as status_desc,
                        l.idpedido,
                        l.iditempedido
                    FROM pedido_item_logistica l
                    JOIN item i ON i.iditem = l.iditem
                    JOIN usuario u ON u.idcliforemp = l.id_separador
                    WHERE l.idembarque = ?
                    ORDER BY l.data_separacao DESC";
            
            $stmt = $this->pdo->prepare($sqlSep);
            $stmt->execute([$idembarque, $idembarque]);
            $itens = array_merge($itens, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if ($tipo == 'carregamento' || $tipo == 'todos') {
            // 🔥 BUSCA TODOS OS REGISTROS (sem agrupar no SQL)
            $sqlCar = "SELECT 
                        'carregamento' as tipo,
                        i.iditem,
                        i.descricao as produto,
                        i.referencia,
                        i.path_foto_master as foto_master,
                        c.path_foto_conferencia as foto_carregamento,
                        c.qt_carregada as quantidade,
                        (SELECT SUM(qt_carregada) FROM pedido_item_carregamento 
                         WHERE iditem = i.iditem AND idembarque = ?) as quantidade_total,
                        u.username as operador,
                        to_char(c.data_carregamento, 'DD/MM/YYYY HH24:MI:SS') as data_hora,
                        2 as status_separacao,
                        'Carregado' as status_desc,
                        c.id as id_carregamento,
                        c.idpedido,
                        c.iditempedido
                    FROM pedido_item_carregamento c
                    JOIN item i ON i.iditem = c.iditem
                    JOIN usuario u ON u.idcliforemp = c.id_conferente
                    WHERE c.idembarque = ?
                    ORDER BY c.data_carregamento DESC";
            
            $stmt = $this->pdo->prepare($sqlCar);
            $stmt->execute([$idembarque, $idembarque]);
            $itensCarregamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Processar URLs das fotos
            foreach ($itensCarregamento as &$item) {
                $item['foto_master'] = $this->processarFotoUrl($item['foto_master']);
                $item['foto_carregamento'] = $this->processarFotoUrl($item['foto_carregamento']);
                $item['foto'] = $item['foto_master'];
            }
            
            $itens = array_merge($itens, $itensCarregamento);
        }
        
        // Processar URLs das fotos para separação
        foreach ($itens as &$item) {
            if (!isset($item['foto_carregamento'])) {
                $item['foto'] = $this->processarFotoUrl($item['foto']);
            }
        }
        
        // 🔥 AGRUPAR REGISTROS DE CARREGAMENTO DO MESMO ITEM E MESMA DATA/HORA
        $itensAgrupados = $this->agruparCarregamentos($itens);
        
        $payload = json_encode($itensAgrupados ?: []);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}

/**
 * 🔥 FUNÇÃO AUXILIAR: Agrupa carregamentos do mesmo item com mesma data/hora
 */
private function agruparCarregamentos($itens) {
    $agrupados = [];
    $grupos = [];
    
    // Separar separação e carregamento
    $separacao = array_filter($itens, function($item) {
        return $item['tipo'] === 'separacao';
    });
    $carregamento = array_filter($itens, function($item) {
        return $item['tipo'] === 'carregamento';
    });
    
    // 🔥 AGRUPAR CARREGAMENTOS POR (iditem + data_hora + foto_carregamento)
    foreach ($carregamento as $item) {
        // Criar chave única: iditem + data (apenas hora:minuto:segundo)
        $dataKey = substr($item['data_hora'], 0, 19); // "DD/MM/YYYY HH24:MI:SS"
        $fotoKey = $item['foto_carregamento'] ?? 'sem_foto';
        $chave = $item['iditem'] . '|' . $dataKey . '|' . $fotoKey;
        
        if (!isset($grupos[$chave])) {
            $grupos[$chave] = [
                'tipo' => 'carregamento',
                'iditem' => $item['iditem'],
                'produto' => $item['produto'],
                'referencia' => $item['referencia'],
                'foto_master' => $item['foto_master'],
                'foto_carregamento' => $item['foto_carregamento'],
                'foto' => $item['foto'],
                'operador' => $item['operador'],
                'data_hora' => $item['data_hora'],
                'status_desc' => $item['status_desc'],
                'quantidade_total' => $item['quantidade_total'],
                // 🔥 Acumular quantidades
                'quantidade' => 0,
                // 🔥 Guardar detalhes dos pedidos (para futuro)
                'pedidos' => []
            ];
        }
        
        // Acumular quantidade
        $grupos[$chave]['quantidade'] += (float)$item['quantidade'];
        
        // Guardar detalhes do pedido (para futuro)
        $grupos[$chave]['pedidos'][] = [
            'idpedido' => $item['idpedido'] ?? null,
            'iditempedido' => $item['iditempedido'] ?? null,
            'qt_carregada' => (float)$item['quantidade']
        ];
    }
    
    // Converter grupos para array
    $carregamentoAgrupado = array_values($grupos);
    
    // 🔥 MESCLAR separação + carregamento agrupado
    return array_merge($separacao, $carregamentoAgrupado);
}

// ========== NOVA FUNÇÃO AUXILIAR ==========
private function processarFotoUrl($foto) {
    if (empty($foto)) {
        return null;
    }
    
    // Se já for URL completa
    if (strpos($foto, 'http') === 0) {
        // Substituir barras invertidas por barras normais
        return str_replace('\\', '/', $foto);
    }
    
    // Se for caminho do servidor de fotos (padrão Nutricional)
    if (strpos($foto, 'Fotos para o Site\\') !== false) {
        $imgPath = explode('Fotos para o Site\\', $foto)[1];
        // Substituir barras invertidas por barras normais
        $imgPath = str_replace('\\', '/', $imgPath);
        return 'https://acesso.nutricionalbr.com:2053/fotos/' . str_replace(' ', '%20', $imgPath);
    }
    
    // Substituir barras invertidas por barras normais
    $foto = str_replace('\\', '/', $foto);
    
    // Se for caminho de upload (carregamento_fotos)
    if (strpos($foto, '/uploads/') === 0) {
        return 'https://api.nutricionalbr.com' . $foto;
    }
    
    // Se for caminho de portal assets
    if (strpos($foto, '/portal/assets/') === 0) {
        return 'https://api.nutricionalbr.com' . $foto;
    }
    
    // Se for caminho relativo começando com /
    if (strpos($foto, '/') === 0) {
        return 'https://api.nutricionalbr.com' . $foto;
    }
    
    // Fallback
    return 'https://api.nutricionalbr.com/portal/assets/produtos/' . $foto;
}
    
    /**
     * GET /v1/auditoria/historico
     * Retorna histórico gerencial filtrado por data
     */
    public function getHistoricoGerencial($request, $response) {
        $params = $request->getQueryParams();
        $data_inicio = $params['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $data_fim = $params['fim'] ?? date('Y-m-d');
        $filtro_user = intval($params['usuario'] ?? 0);
        
        try {
            $sql = "SELECT 
                        ep.idembarque, 
                        ep.observacao as rota, 
                        ep.placa,
                        (SELECT fantasia FROM cliforemp WHERE idcliforemp = ep.identregador) as motorista,
                        u.username as operador_principal,
                        ep.pex_embarque_pronto, 
                        ep.pex_embarque_carregamento,
                        s.status_atual,
                        s.data_inicio as inicio_op,
                        s.data_fim as fim_op,
                        (SELECT COUNT(*) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) as total_bips
                    FROM embarque_pedido ep
                    JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    LEFT JOIN usuario u ON u.idcliforemp = s.idusuario
                    WHERE s.data_inicio::date BETWEEN :ini AND :fim";
            
            $params = [':ini' => $data_inicio, ':fim' => $data_fim];
            
            if ($filtro_user > 0) {
                $sql .= " AND s.idusuario = :user";
                $params[':user'] = $filtro_user;
            }
            
            $sql .= " ORDER BY s.data_inicio DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * GET /v1/auditoria/conferencia/{idembarque}
     * Retorna detalhes da conferência (bips)
     */
    public function getDetalhesConferencia($request, $response, $args) {
        $idembarque = intval($args['idembarque'] ?? 0);
        
        try {
            $sql = "SELECT i.descricao as produto, 
                           i.path_foto_master as foto, 
                           l.qt_separada as qtd, 
                           u.username as operador, 
                           to_char(l.data_separacao, 'HH24:MI:SS') as hora, 
                           'SEPARAÇÃO' as etapa
                    FROM pedido_item_logistica l
                    JOIN item i ON i.iditem = l.iditem
                    JOIN usuario u ON u.idcliforemp = l.id_separador
                    WHERE l.idembarque = ?
                    UNION ALL
                    SELECT i.descricao, 
                           i.path_foto_master, 
                           c.qt_carregada, 
                           u.username, 
                           to_char(c.data_carregamento, 'HH24:MI:SS'), 
                           'CARGA'
                    FROM pedido_item_carregamento c
                    JOIN item i ON i.iditem = c.iditem
                    JOIN usuario u ON u.idcliforemp = c.id_conferente
                    WHERE c.idembarque = ?
                    ORDER BY hora DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idembarque, $idembarque]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * GET /v1/auditoria/exportar
     * Exporta relatório em CSV
     */
    public function exportarRelatorio($request, $response) {
        $params = $request->getQueryParams();
        $data_inicio = $params['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $data_fim = $params['fim'] ?? date('Y-m-d');
        
        try {
            $sql = "SELECT 
                        ep.idembarque as \"Embarque\",
                        ep.observacao as \"Rota\",
                        s.status_atual as \"Status\",
                        u.username as \"Operador\",
                        to_char(s.data_inicio, 'DD/MM/YYYY HH24:MI') as \"Início\",
                        to_char(s.data_fim, 'DD/MM/YYYY HH24:MI') as \"Fim\",
                        (SELECT COUNT(*) FROM pedido_item_logistica WHERE idembarque = ep.idembarque) as \"Bips Separação\",
                        (SELECT COUNT(*) FROM pedido_item_carregamento WHERE idembarque = ep.idembarque) as \"Bips Carregamento\",
                        ep.totalpesobruto as \"Peso Total (kg)\"
                    FROM embarque_pedido ep
                    JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    LEFT JOIN usuario u ON u.idcliforemp = s.idusuario
                    WHERE s.data_inicio::date BETWEEN :ini AND :fim
                    AND ep.idfilial IN (1,6)
                    ORDER BY s.data_inicio DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':ini' => $data_inicio, ':fim' => $data_fim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Gerar CSV
            $output = fopen('php://temp', 'r+');
            fprintf($output, "\xEF\xBB\xBF"); // BOM para UTF-8
            
            if (!empty($dados)) {
                fputcsv($output, array_keys($dados[0]), ';');
                foreach ($dados as $row) {
                    fputcsv($output, $row, ';');
                }
            }
            
            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);
            
            $response->getBody()->write($csvContent);
            return $response
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="auditoria_' . date('Ymd') . '.csv"');
                
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}