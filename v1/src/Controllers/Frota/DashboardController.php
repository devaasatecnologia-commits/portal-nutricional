<?php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class DashboardController
{
    private $pdo;
    private $cacheTime = 60; // segundos para cache
    
    public function __construct()
    {
        try {
            if (function_exists('getPDO')) {
                $this->pdo = \getPDO();
            } else {
                error_log('Funcao getPDO nao encontrada');
                $this->pdo = null;
            }
        } catch (\Exception $e) {
            error_log('Erro ao conectar ao banco no DashboardController: ' . $e->getMessage());
            $this->pdo = null;
        }
    }
    
    /**
     * GET /v1/frota/dashboard/kpis
     * Retorna KPIs reais do sistema
     */
    public function kpis(Request $request, Response $response): Response
    {
        try {
            $data = [
                'total_veiculos' => 0,
                'veiculos_em_rota' => 0,
                'veiculos_disponiveis' => 0,
                'veiculos_manutencao' => 0,
                'entregas_hoje' => 0,
                'entregas_concluidas_hoje' => 0,
                'entregas_pendentes_hoje' => 0,
                'taxa_entrega_hoje' => 0,
                'motoristas_ativos' => 0,
                'motoristas_em_rota' => 0,
                'motoristas_disponiveis' => 0,
                'embarques_ativos' => 0,
                'embarques_finalizados_hoje' => 0,
                'total_entregas_mes' => 0,
                'faturamento_mes' => 0,
                'total_km_rodados_hoje' => 0,
                'tempo_medio_entrega' => 0,
                'entregas_atrasadas' => 0,
                'percentual_entrega_no_prazo' => 0,
                'total_peso_transportado_hoje' => 0
            ];
            
            if ($this->pdo) {
                // ============================================================
                // 1. VEÍCULOS
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'em_rota' THEN 1 END) as em_rota,
                        COUNT(CASE WHEN status = 'disponivel' THEN 1 END) as disponivel,
                        COUNT(CASE WHEN status = 'manutencao' THEN 1 END) as manutencao
                    FROM frota_veiculo
                    WHERE status != 'inativo'
                ");
                $veiculos = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['total_veiculos'] = (int)$veiculos['total'];
                $data['veiculos_em_rota'] = (int)$veiculos['em_rota'];
                $data['veiculos_disponiveis'] = (int)$veiculos['disponivel'];
                $data['veiculos_manutencao'] = (int)$veiculos['manutencao'];
                
                // ============================================================
                // 2. ENTREGAS DE HOJE
                // ============================================================
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as concluidas,
                        COUNT(CASE WHEN status IN ('pendente', 'em_andamento') THEN 1 END) as pendentes
                    FROM frota_entrega
                    WHERE DATE(created_at) = CURRENT_DATE
                    AND status != 'cancelada'
                ");
                $stmt->execute();
                $entregas = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['entregas_hoje'] = (int)$entregas['total'];
                $data['entregas_concluidas_hoje'] = (int)$entregas['concluidas'];
                $data['entregas_pendentes_hoje'] = (int)$entregas['pendentes'];
                $data['taxa_entrega_hoje'] = $data['entregas_hoje'] > 0 
                    ? round(($data['entregas_concluidas_hoje'] / $data['entregas_hoje']) * 100, 1) 
                    : 0;
                
                // ============================================================
                // 3. MOTORISTAS
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'em_rota' THEN 1 END) as em_rota,
                        COUNT(CASE WHEN status = 'disponivel' THEN 1 END) as disponivel
                    FROM frota_motorista
                    WHERE status != 'inativo'
                ");
                $motoristas = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['motoristas_ativos'] = (int)$motoristas['total'];
                $data['motoristas_em_rota'] = (int)$motoristas['em_rota'];
                $data['motoristas_disponiveis'] = (int)$motoristas['disponivel'];
                
                // ============================================================
                // 4. EMBARQUES
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(CASE WHEN status = 'em_andamento' THEN 1 END) as ativos,
                        COUNT(CASE WHEN DATE(finalizado_em) = CURRENT_DATE THEN 1 END) as finalizados_hoje
                    FROM frota_embarque
                ");
                $embarques = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['embarques_ativos'] = (int)$embarques['ativos'];
                $data['embarques_finalizados_hoje'] = (int)$embarques['finalizados_hoje'];
                
                // ============================================================
                // 5. ENTREGAS DO MÊS
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(*) as total,
                        COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)), 0) as faturamento
                    FROM frota_entrega e
                    WHERE EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
                    AND status = 'entregue'
                ");
                $mes = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['total_entregas_mes'] = (int)$mes['total'];
                $data['faturamento_mes'] = (float)$mes['faturamento'];
                
                // ============================================================
                // 6. MÉTRICAS DE ROTA (PESO + KM)
                // ============================================================
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COALESCE(SUM(distancia_percorrida), 0) as total_km,
                        COALESCE(SUM(peso_total), 0) as total_peso
                    FROM frota_embarque
                    WHERE DATE(data_saida) = CURRENT_DATE
                    AND status = 'finalizado'
                ");
                $stmt->execute();
                $metricas = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['total_km_rodados_hoje'] = (float)$metricas['total_km'];
                $data['total_peso_transportado_hoje'] = (float)$metricas['total_peso'];
                
                // ============================================================
                // 7. TEMPO MÉDIO DE ENTREGA
                // ============================================================
                $stmt = $this->pdo->prepare("
                    SELECT 
                        AVG(EXTRACT(EPOCH FROM (checkout - checkin))/60) as tempo_medio
                    FROM frota_entrega
                    WHERE DATE(created_at) = CURRENT_DATE
                    AND status = 'entregue'
                    AND checkin IS NOT NULL 
                    AND checkout IS NOT NULL
                ");
                $stmt->execute();
                $tempo = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['tempo_medio_entrega'] = (float)($tempo['tempo_medio'] ?? 0);
                
                // ============================================================
                // 8. ENTREGAS ATRASADAS E PERCENTUAL NO PRAZO
                // ============================================================
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COUNT(CASE WHEN status = 'pendente' AND data_prevista < CURRENT_DATE THEN 1 END) as atrasadas,
                        COUNT(CASE WHEN status = 'entregue' AND DATE(checkout) <= data_prevista THEN 1 END) as no_prazo,
                        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as total_entregues
                    FROM frota_entrega
                    WHERE DATE(created_at) >= CURRENT_DATE - INTERVAL '30 days'
                    AND status != 'cancelada'
                ");
                $stmt->execute();
                $prazos = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['entregas_atrasadas'] = (int)($prazos['atrasadas'] ?? 0);
                $data['percentual_entrega_no_prazo'] = ($prazos['total_entregues'] ?? 0) > 0
                    ? round((($prazos['no_prazo'] ?? 0) / ($prazos['total_entregues'] ?? 1)) * 100, 1)
                    : 0;
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro no kpis: ' . $e->getMessage());
            return $this->json($response, [
                'success' => true,
                'data' => $this->getDefaultKPIs(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * GET /v1/frota/dashboard/graficos
     * Retorna dados para os gráficos
     */
    public function graficos(Request $request, Response $response): Response
    {
        try {
            $result = [
                'dias' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'],
                'concluidas' => [0, 0, 0, 0, 0, 0, 0],
                'pendentes' => [0, 0, 0, 0, 0, 0, 0],
                'faturamento_diario' => [0, 0, 0, 0, 0, 0, 0],
                'entregas_por_motorista' => [],
                'top_motoristas' => [],
                'status_distribution' => [
                    'concluidas' => 0,
                    'pendentes' => 0,
                    'em_andamento' => 0,
                    'falha' => 0,
                    'canceladas' => 0
                ],
                'entregas_por_hora' => array_fill(0, 24, 0),
                'veiculos_por_status' => [
                    'disponivel' => 0,
                    'em_rota' => 0,
                    'manutencao' => 0
                ]
            ];
            
            if ($this->pdo) {
                // ============================================================
                // 1. ENTREGAS ÚLTIMOS 7 DIAS
                // ============================================================
                for ($i = 6; $i >= 0; $i--) {
                    $data = date('Y-m-d', strtotime("-$i days"));
                    $result['dias'][6 - $i] = date('D', strtotime($data));
                    
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            COUNT(CASE WHEN status = 'entregue' THEN 1 END) as concluidas,
                            COUNT(CASE WHEN status IN ('pendente', 'em_andamento') THEN 1 END) as pendentes,
                            COALESCE(SUM(CASE WHEN status = 'entregue' THEN COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0) ELSE 0 END), 0) as faturamento
                        FROM frota_entrega e
                        WHERE DATE(e.created_at) = :data
                        AND e.status != 'cancelada'
                    ");
                    $stmt->execute(['data' => $data]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $result['concluidas'][6 - $i] = (int)($row['concluidas'] ?? 0);
                    $result['pendentes'][6 - $i] = (int)($row['pendentes'] ?? 0);
                    $result['faturamento_diario'][6 - $i] = (float)($row['faturamento'] ?? 0);
                }
                
                // ============================================================
                // 2. STATUS DAS ENTREGAS (ÚLTIMOS 30 DIAS)
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as concluidas,
                        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pendentes,
                        COUNT(CASE WHEN status = 'em_andamento' THEN 1 END) as em_andamento,
                        COUNT(CASE WHEN status = 'falha' THEN 1 END) as falha,
                        COUNT(CASE WHEN status = 'cancelada' THEN 1 END) as canceladas
                    FROM frota_entrega
                    WHERE DATE(created_at) >= CURRENT_DATE - INTERVAL '30 days'
                ");
                $status = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['status_distribution'] = [
                    'concluidas' => (int)($status['concluidas'] ?? 0),
                    'pendentes' => (int)($status['pendentes'] ?? 0),
                    'em_andamento' => (int)($status['em_andamento'] ?? 0),
                    'falha' => (int)($status['falha'] ?? 0),
                    'canceladas' => (int)($status['canceladas'] ?? 0)
                ];
                
                // ============================================================
                // 3. TOP MOTORISTAS (ENTREGUES)
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        m.id,
                        m.nome,
                        COUNT(e.id) as total_entregas,
                        COALESCE(SUM(COALESCE((SELECT SUM(pi.valortotal) FROM pedido_item pi WHERE pi.idpedido IN (SELECT value::integer FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value WHERE value ~ '^[0-9]+$')), e.valor_total, 0)), 0) as total_faturado
                    FROM frota_motorista m
                    JOIN frota_entrega e ON e.motorista_id = m.id
                    WHERE DATE(e.created_at) >= CURRENT_DATE - INTERVAL '30 days'
                    AND e.status = 'entregue'
                    GROUP BY m.id, m.nome
                    ORDER BY total_entregas DESC
                    LIMIT 5
                ");
                $result['top_motoristas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // ============================================================
                // 4. VEÍCULOS POR STATUS
                // ============================================================
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(CASE WHEN status = 'disponivel' THEN 1 END) as disponivel,
                        COUNT(CASE WHEN status = 'em_rota' THEN 1 END) as em_rota,
                        COUNT(CASE WHEN status = 'manutencao' THEN 1 END) as manutencao
                    FROM frota_veiculo
                ");
                $veiculos = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['veiculos_por_status'] = [
                    'disponivel' => (int)($veiculos['disponivel'] ?? 0),
                    'em_rota' => (int)($veiculos['em_rota'] ?? 0),
                    'manutencao' => (int)($veiculos['manutencao'] ?? 0)
                ];
                
                // ============================================================
                // 5. ENTREGAS POR HORA (HOJE)
                // ============================================================
                for ($h = 0; $h < 24; $h++) {
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) as total
                        FROM frota_entrega
                        WHERE DATE(created_at) = CURRENT_DATE
                        AND EXTRACT(HOUR FROM created_at) = :hora
                        AND status = 'entregue'
                    ");
                    $stmt->execute(['hora' => $h]);
                    $result['entregas_por_hora'][$h] = (int)$stmt->fetchColumn();
                }
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro no graficos: ' . $e->getMessage());
            return $this->json($response, [
                'success' => true,
                'data' => $this->getDefaultGraficos(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * GET /v1/frota/dashboard/alertas
     * Retorna alertas do sistema
     */
    public function alertas(Request $request, Response $response): Response
    {
        try {
            $alertas = [];
            
            if ($this->pdo) {
                // 1. Veículos em manutenção há mais de 7 dias
                $stmt = $this->pdo->query("
                    SELECT id, placa, modelo, 
                           EXTRACT(DAY FROM (NOW() - updated_at)) as dias_parado
                    FROM frota_veiculo
                    WHERE status = 'manutencao'
                    AND updated_at < NOW() - INTERVAL '7 days'
                    LIMIT 5
                ");
                $manutencao = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($manutencao as $v) {
                    $alertas[] = [
                        'tipo' => 'critico',
                        'titulo' => 'Veiculo em manutencao por ' . round($v['dias_parado']) . ' dias',
                        'mensagem' => "O veiculo {$v['placa']} ({$v['modelo']}) esta em manutencao ha " . round($v['dias_parado']) . " dias",
                        'id_referencia' => $v['id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
                
                // 2. Entregas atrasadas (pendentes com data prevista vencida)
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) as total
                    FROM frota_entrega
                    WHERE status = 'pendente'
                    AND data_prevista < CURRENT_DATE
                ");
                $atrasadas = (int)$stmt->fetchColumn();
                if ($atrasadas > 0) {
                    $alertas[] = [
                        'tipo' => 'atencao',
                        'titulo' => $atrasadas . ' entregas atrasadas',
                        'mensagem' => "Existem {$atrasadas} entregas com data prevista vencida",
                        'id_referencia' => null,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $alertas
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => true,
                'data' => []
            ]);
        }
    }
    
    /**
     * GET /v1/frota/dashboard/entregas-hoje
     * Retorna entregas de hoje
     */
    public function entregasHoje(Request $request, Response $response): Response
    {
        try {
            $entregas = [];
            
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        e.*,
                        c.nome as cliente_nome,
                        c.telefone as cliente_telefone,
                        m.nome as motorista_nome,
                        v.placa as veiculo_placa
                    FROM frota_entrega e
                    LEFT JOIN frota_cliente c ON c.id = e.cliente_id
                    LEFT JOIN frota_motorista m ON m.id = e.motorista_id
                    LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
                    WHERE DATE(e.created_at) = CURRENT_DATE
                    ORDER BY e.status ASC, e.created_at DESC
                    LIMIT 20
                ");
                $stmt->execute();
                $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $entregas
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => true,
                'data' => []
            ]);
        }
    }
    
    /**
     * GET /v1/frota/dashboard/mapa
     * Retorna posições dos veículos para o mapa
     */
    public function mapa(Request $request, Response $response): Response
    {
        try {
            $veiculos = [];
            
            if ($this->pdo) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        v.status,
                        hp.latitude,
                        hp.longitude,
                        hp.velocidade,
                        hp.data_hora as ultima_posicao,
                        m.nome as motorista_nome
                    FROM frota_veiculo v
                    LEFT JOIN frota_motorista m ON m.veiculo_atual_id = v.id
                    LEFT JOIN (
                        SELECT DISTINCT ON (veiculo_id) 
                            veiculo_id,
                            latitude,
                            longitude,
                            velocidade,
                            data_hora
                        FROM frota_historico_posicao
                        ORDER BY veiculo_id, data_hora DESC
                    ) hp ON hp.veiculo_id = v.id
                    WHERE v.status != 'inativo'
                    ORDER BY v.status ASC
                ");
                $stmt->execute();
                $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $veiculos
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => true,
                'data' => []
            ]);
        }
    }
    
    /**
     * Dados padrão para fallback
     */
    private function getDefaultKPIs(): array
    {
        return [
            'total_veiculos' => 0,
            'veiculos_em_rota' => 0,
            'veiculos_disponiveis' => 0,
            'veiculos_manutencao' => 0,
            'entregas_hoje' => 0,
            'entregas_concluidas_hoje' => 0,
            'entregas_pendentes_hoje' => 0,
            'taxa_entrega_hoje' => 0,
            'motoristas_ativos' => 0,
            'motoristas_em_rota' => 0,
            'motoristas_disponiveis' => 0,
            'embarques_ativos' => 0,
            'embarques_finalizados_hoje' => 0,
            'total_entregas_mes' => 0,
            'faturamento_mes' => 0,
            'total_km_rodados_hoje' => 0,
            'tempo_medio_entrega' => 0,
            'entregas_atrasadas' => 0,
            'percentual_entrega_no_prazo' => 0,
            'total_peso_transportado_hoje' => 0
        ];
    }
    
    private function getDefaultGraficos(): array
    {
        return [
            'dias' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'],
            'concluidas' => [0, 0, 0, 0, 0, 0, 0],
            'pendentes' => [0, 0, 0, 0, 0, 0, 0],
            'faturamento_diario' => [0, 0, 0, 0, 0, 0, 0],
            'entregas_por_motorista' => [],
            'top_motoristas' => [],
            'status_distribution' => [
                'concluidas' => 0,
                'pendentes' => 0,
                'em_andamento' => 0,
                'falha' => 0,
                'canceladas' => 0
            ],
            'entregas_por_hora' => array_fill(0, 24, 0),
            'veiculos_por_status' => [
                'disponivel' => 0,
                'em_rota' => 0,
                'manutencao' => 0
            ]
        ];
    }
    // ================================================================
// ADICIONAR NO FINAL DO DASHBOARDCONTROLLER.PHP
// ================================================================

/**
 * GET /v1/frota/dashboard/kpis-problemas
 * Retorna KPIs específicos para análise de problemas
 */
public function kpisProblemas(Request $request, Response $response): Response
{
    try {
        $data = [
            'total_problemas' => 0,
            'pendentes' => 0,
            'em_analise' => 0,
            'resolvidos' => 0,
            'cancelados' => 0,
            'faltantes' => 0,
            'devolucoes' => 0,
            'avarias' => 0,
            'extraviados' => 0,
            'valor_total_afetado' => 0,
            'quantidade_total_afetada' => 0,
            'problemas_criticos' => 0,
            'problemas_alta' => 0,
            'problemas_media' => 0,
            'problemas_baixa' => 0,
            'taxa_resolucao' => 0,
            'tempo_medio_resolucao' => 0 // horas
        ];

        if ($this->pdo) {
            // ============================================================
            // 1. STATUS DOS PROBLEMAS
            // ============================================================
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status_problema = 'pendente' THEN 1 END) as pendentes,
                    COUNT(CASE WHEN status_problema = 'em_analise' THEN 1 END) as em_analise,
                    COUNT(CASE WHEN status_problema = 'resolvido' THEN 1 END) as resolvidos,
                    COUNT(CASE WHEN status_problema = 'cancelado' THEN 1 END) as cancelados
                FROM frota_entrega_problema
            ");
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $data['total_problemas'] = (int)($status['total'] ?? 0);
            $data['pendentes'] = (int)($status['pendentes'] ?? 0);
            $data['em_analise'] = (int)($status['em_analise'] ?? 0);
            $data['resolvidos'] = (int)($status['resolvidos'] ?? 0);
            $data['cancelados'] = (int)($status['cancelados'] ?? 0);
            
            $data['taxa_resolucao'] = $data['total_problemas'] > 0 
                ? round(($data['resolvidos'] / $data['total_problemas']) * 100, 1) 
                : 0;

            // ============================================================
            // 2. TIPOS DE PROBLEMA
            // ============================================================
            $stmt = $this->pdo->query("
                SELECT 
                    tipo_problema,
                    COUNT(*) as total,
                    COALESCE(SUM(quantidade_afetada), 0) as quantidade,
                    COALESCE(SUM(valor_afetado), 0) as valor
                FROM frota_entrega_problema
                GROUP BY tipo_problema
            ");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tipos as $tipo) {
                $key = $tipo['tipo_problema'] . 's'; // faltante -> faltantes
                if (isset($data[$key])) {
                    $data[$key] = (int)$tipo['total'];
                }
                $data['quantidade_total_afetada'] += (float)$tipo['quantidade'];
                $data['valor_total_afetado'] += (float)$tipo['valor'];
            }

            // ============================================================
            // 3. PRIORIDADES
            // ============================================================
            $stmt = $this->pdo->query("
                SELECT 
                    prioridade,
                    COUNT(*) as total
                FROM frota_entrega_problema
                WHERE status_problema != 'resolvido' AND status_problema != 'cancelado'
                GROUP BY prioridade
            ");
            $prioridades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($prioridades as $p) {
                $key = 'problemas_' . $p['prioridade'];
                if (isset($data[$key])) {
                    $data[$key] = (int)$p['total'];
                }
            }

            // ============================================================
            // 4. TEMPO MÉDIO DE RESOLUÇÃO (em horas)
            // ============================================================
            $stmt = $this->pdo->query("
                SELECT 
                    AVG(EXTRACT(EPOCH FROM (data_resolucao - created_at))/3600) as tempo_medio
                FROM frota_entrega_problema
                WHERE status_problema = 'resolvido'
                AND data_resolucao IS NOT NULL
            ");
            $tempo = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['tempo_medio_resolucao'] = round((float)($tempo['tempo_medio'] ?? 0), 1);
        }

        return $this->json($response, [
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (\Exception $e) {
        error_log('Erro no kpisProblemas: ' . $e->getMessage());
        return $this->json($response, [
            'success' => true,
            'data' => [
                'total_problemas' => 0,
                'pendentes' => 0,
                'em_analise' => 0,
                'resolvidos' => 0,
                'cancelados' => 0,
                'taxa_resolucao' => 0,
                'tempo_medio_resolucao' => 0
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

/**
 * GET /v1/frota/dashboard/problemas
 * Retorna lista de problemas com filtros
 */
public function problemas(Request $request, Response $response): Response
{
    try {
        $params = $request->getQueryParams();
        $pagina = (int)($params['pagina'] ?? 1);
        $limite = (int)($params['limite'] ?? 25);
        $status = $params['status'] ?? null;
        $prioridade = $params['prioridade'] ?? null;
        $busca = $params['busca'] ?? null;
        
        $offset = ($pagina - 1) * $limite;
        
        $where = [];
        $bind = [];
        
        if ($status && $status !== 'todos') {
            $where[] = "ep.status_problema = :status";
            $bind['status'] = $status;
        }
        
        if ($prioridade && $prioridade !== 'todas') {
            $where[] = "ep.prioridade = :prioridade";
            $bind['prioridade'] = $prioridade;
        }
        
        if ($busca) {
            $where[] = "(e.cliente_nome ILIKE :busca OR e.id::text ILIKE :busca OR ep.referencia ILIKE :busca)";
            $bind['busca'] = "%{$busca}%";
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Query principal
        $sql = "
            SELECT 
                ep.id,
                ep.entrega_id,
                ep.embarque_id,
                ep.pedido_id,
                ep.tipo_problema,
                ep.referencia,
                ep.descricao_problema,
                COALESCE(NULLIF(ep.quantidade_afetada, 0), erp.quantidade, 0) as quantidade_afetada,
                COALESCE(NULLIF(ep.valor_afetado, 0), erp.valor, 0) as valor_afetado,
                ep.status_problema,
                ep.prioridade,
                ep.created_at as data_problema,
                ep.data_resolucao,
                e.cliente_nome,
                e.cidade,
                e.uf,
                e.status as entrega_status,
                em.numero_embarque,
                mo.nome as motorista_nome,
                ve.placa as veiculo_placa
            FROM frota_entrega_problema ep
            INNER JOIN frota_entrega e ON e.id = ep.entrega_id
            INNER JOIN frota_embarque em ON em.id = ep.embarque_id
            LEFT JOIN frota_motorista mo ON mo.id = em.motorista_id
            LEFT JOIN frota_veiculo ve ON ve.id = em.veiculo_id
            LEFT JOIN LATERAL (
                SELECT SUM(pi.qt) as quantidade, SUM(pi.valortotal) as valor
                FROM pedido_item pi
                WHERE pi.idpedido IN (
                    SELECT value::integer
                    FROM regexp_split_to_table(COALESCE(e.pedidos_ids, ''), ',') value
                    WHERE value ~ '^[0-9]+$'
                )
            ) erp ON true
            {$whereClause}
            ORDER BY 
                CASE ep.prioridade 
                    WHEN 'critica' THEN 1
                    WHEN 'alta' THEN 2
                    WHEN 'media' THEN 3
                    WHEN 'baixa' THEN 4
                END,
                ep.created_at DESC
            LIMIT :limite OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Total de registros
        $countSql = "
            SELECT COUNT(*) as total
            FROM frota_entrega_problema ep
            INNER JOIN frota_entrega e ON e.id = ep.entrega_id
            {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($bind as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'data' => $dados,
            'pagination' => [
                'pagina' => $pagina,
                'limite' => $limite,
                'total' => $total,
                'total_paginas' => ceil($total / $limite)
            ]
        ]);
        
    } catch (\Exception $e) {
        error_log('Erro em problemas: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao carregar problemas'
        ], 500);
    }
}

/**
 * GET /v1/frota/entregas/{id}/analise
 * Retorna análise completa de uma entrega
 */
public function analiseEntrega(Request $request, Response $response, array $args): Response
{
    try {
        $id = (int)($args['id'] ?? 0);
        
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID inválido'
            ], 400);
        }
        
        // Buscar dados da entrega
        $sql = "
            SELECT 
                e.*,
                em.numero_embarque,
                mo.nome as motorista_nome,
                mo.telefone as motorista_telefone,
                ve.placa as veiculo_placa,
                ve.modelo as veiculo_modelo
            FROM frota_entrega e
            LEFT JOIN frota_embarque em ON em.id = e.embarque_id
            LEFT JOIN frota_motorista mo ON mo.id = em.motorista_id
            LEFT JOIN frota_veiculo ve ON ve.id = em.veiculo_id
            WHERE e.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $entrega = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$entrega) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada'
            ], 404);
        }
        
        // Buscar checklist
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_checklist_entrega 
            WHERE entrega_id = :id
            ORDER BY id
        ");
        $stmt->execute(['id' => $id]);
        $entrega['checklist'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar problemas
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_entrega_problema 
            WHERE entrega_id = :id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['id' => $id]);
        $entrega['problemas'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar timeline
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_entrega_timeline 
            WHERE entrega_id = :id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['id' => $id]);
        $entrega['timeline'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar fotos
        $stmt = $this->pdo->prepare("
            SELECT * FROM frota_entrega_foto 
            WHERE entrega_id = :id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['id' => $id]);
        $entrega['fotos'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->json($response, [
            'success' => true,
            'data' => $entrega
        ]);
        
    } catch (\Exception $e) {
        error_log('Erro em analiseEntrega: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao carregar análise'
        ], 500);
    }
}

/**
 * PUT /v1/frota/problemas/{id}/resolver
 * Resolve um problema
 */
public function resolverProblema(Request $request, Response $response, array $args): Response
{
    try {
        $id = (int)($args['id'] ?? 0);
        $body = json_decode($request->getBody()->getContents(), true);
        $solucao = $body['solucao'] ?? 'Resolvido pelo gestor';
        $usuarioId = (int)($body['usuario_id'] ?? 0);
        $usuarioNome = $body['usuario_nome'] ?? 'Sistema';
        
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID inválido'
            ], 400);
        }
        
        // Atualizar problema
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega_problema 
            SET 
                status_problema = 'resolvido',
                solucao = :solucao,
                data_resolucao = NOW(),
                updated_at = NOW()
            WHERE id = :id
            RETURNING entrega_id, embarque_id
        ");
        $stmt->execute([
            'solucao' => $solucao,
            'id' => $id
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Problema não encontrado'
            ], 404);
        }
        
        // Registrar na timeline
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_entrega_timeline 
            (entrega_id, acao, descricao, usuario_id, usuario_nome, dados_novos)
            VALUES 
            (:entrega_id, 'resolvido', :descricao, :usuario_id, :usuario_nome, :dados_novos)
        ");
        $stmt->execute([
            'entrega_id' => $result['entrega_id'],
            'descricao' => "Problema resolvido: {$solucao}",
            'usuario_id' => $usuarioId,
            'usuario_nome' => $usuarioNome,
            'dados_novos' => json_encode(['status' => 'resolvido', 'solucao' => $solucao])
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Problema resolvido com sucesso'
        ]);
        
    } catch (\Exception $e) {
        error_log('Erro em resolverProblema: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao resolver problema'
        ], 500);
    }
}

/**
 * PUT /v1/frota/problemas/{id}/iniciar-analise
 * Inicia análise de um problema
 */
public function iniciarAnalise(Request $request, Response $response, array $args): Response
{
    try {
        $id = (int)($args['id'] ?? 0);
        $usuarioId = (int)($body['usuario_id'] ?? 0);
        $usuarioNome = $body['usuario_nome'] ?? 'Sistema';
        
        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID inválido'
            ], 400);
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE frota_entrega_problema 
            SET 
                status_problema = 'em_analise',
                updated_at = NOW()
            WHERE id = :id
            AND status_problema = 'pendente'
            RETURNING entrega_id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Problema não encontrado ou já está em análise'
            ], 400);
        }
        
        // Registrar na timeline
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_entrega_timeline 
            (entrega_id, acao, descricao, usuario_id, usuario_nome, dados_novos)
            VALUES 
            (:entrega_id, 'problema', 'Análise iniciada', :usuario_id, :usuario_nome, :dados_novos)
        ");
        $stmt->execute([
            'entrega_id' => $result['entrega_id'],
            'usuario_id' => $usuarioId,
            'usuario_nome' => $usuarioNome,
            'dados_novos' => json_encode(['status' => 'em_analise'])
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Análise iniciada com sucesso'
        ]);
        
    } catch (\Exception $e) {
        error_log('Erro em iniciarAnalise: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao iniciar análise'
        ], 500);
    }
}

/**
 * POST /v1/frota/entregas/{id}/analise
 * Adiciona análise do gestor
 */
public function adicionarAnalise(Request $request, Response $response, array $args): Response
{
    try {
        $entregaId = (int)($args['id'] ?? 0);
        $body = json_decode($request->getBody()->getContents(), true);
        
        if ($entregaId <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID da entrega inválido'
            ], 400);
        }
        
        // Buscar embarque_id
        $stmt = $this->pdo->prepare("SELECT embarque_id FROM frota_entrega WHERE id = :id");
        $stmt->execute(['id' => $entregaId]);
        $embarqueId = (int)$stmt->fetchColumn();
        
        if (!$embarqueId) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Entrega não encontrada'
            ], 404);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_entrega_analise 
            (entrega_id, embarque_id, gestor_id, gestor_nome, tipo_analise, titulo, descricao, nota, recomendacoes)
            VALUES 
            (:entrega_id, :embarque_id, :gestor_id, :gestor_nome, :tipo, :titulo, :descricao, :nota, :recomendacoes)
        ");
        $stmt->execute([
            'entrega_id' => $entregaId,
            'embarque_id' => $embarqueId,
            'gestor_id' => $body['gestor_id'] ?? 0,
            'gestor_nome' => $body['gestor_nome'] ?? 'Gestor',
            'tipo' => $body['tipo'] ?? 'checklist',
            'titulo' => $body['titulo'] ?? 'Análise da entrega',
            'descricao' => $body['descricao'] ?? '',
            'nota' => (int)($body['nota'] ?? 0),
            'recomendacoes' => $body['recomendacoes'] ?? ''
        ]);
        
        // Registrar na timeline
        $stmt = $this->pdo->prepare("
            INSERT INTO frota_entrega_timeline 
            (entrega_id, acao, descricao, usuario_id, usuario_nome, dados_novos)
            VALUES 
            (:entrega_id, 'analise', :descricao, :usuario_id, :usuario_nome, :dados_novos)
        ");
        $stmt->execute([
            'entrega_id' => $entregaId,
            'descricao' => $body['titulo'] ?? 'Análise adicionada',
            'usuario_id' => $body['gestor_id'] ?? 0,
            'usuario_nome' => $body['gestor_nome'] ?? 'Gestor',
            'dados_novos' => json_encode([
                'nota' => $body['nota'] ?? 0,
                'recomendacoes' => $body['recomendacoes'] ?? ''
            ])
        ]);
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Análise adicionada com sucesso'
        ]);
        
    } catch (\Exception $e) {
        error_log('Erro em adicionarAnalise: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao adicionar análise'
        ], 500);
    }
}

/**
 * GET /v1/frota/gestao-cargas/resumo-motorista
 * Resumo de problemas por motorista
 */
public function resumoMotorista(Request $request, Response $response): Response
{
    try {
        $params = $request->getQueryParams();
        $dias = (int)($params['dias'] ?? 30);

        $sql = "
            SELECT 
                mo.id,
                mo.nome as motorista_nome,
                COUNT(DISTINCT ep.id) as total_problemas,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'pendente' THEN ep.id END) as pendentes,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'resolvido' THEN ep.id END) as resolvidos,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'faltante' THEN ep.id END) as faltantes,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'devolucao' THEN ep.id END) as devolucoes,
                COALESCE(SUM(ep.quantidade_afetada), 0) as total_quantidade,
                COALESCE(SUM(ep.valor_afetado), 0) as total_valor
            FROM frota_motorista mo
            LEFT JOIN frota_embarque em ON em.motorista_id = mo.id
            LEFT JOIN frota_entrega e ON e.embarque_id = em.id
            LEFT JOIN frota_entrega_problema ep ON ep.entrega_id = e.id
            WHERE ep.created_at >= CURRENT_DATE - INTERVAL :dias DAY
            GROUP BY mo.id, mo.nome
            HAVING COUNT(DISTINCT ep.id) > 0
            ORDER BY total_problemas DESC
            LIMIT 20
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dias' => $dias]);
        $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json($response, [
            'success' => true,
            'data' => $dados,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (\Exception $e) {
        error_log('Erro em resumoMotorista: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao carregar resumo por motorista'
        ], 500);
    }
}

/**
 * GET /v1/frota/gestao-cargas/resumo-veiculo
 * Resumo de problemas por veículo
 */
public function resumoVeiculo(Request $request, Response $response): Response
{
    try {
        $params = $request->getQueryParams();
        $dias = (int)($params['dias'] ?? 30);

        $sql = "
            SELECT 
                ve.id,
                ve.placa,
                ve.modelo,
                COUNT(DISTINCT ep.id) as total_problemas,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'pendente' THEN ep.id END) as pendentes,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'resolvido' THEN ep.id END) as resolvidos,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'faltante' THEN ep.id END) as faltantes,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'devolucao' THEN ep.id END) as devolucoes,
                COALESCE(SUM(ep.quantidade_afetada), 0) as total_quantidade,
                COALESCE(SUM(ep.valor_afetado), 0) as total_valor
            FROM frota_veiculo ve
            LEFT JOIN frota_embarque em ON em.veiculo_id = ve.id
            LEFT JOIN frota_entrega e ON e.embarque_id = em.id
            LEFT JOIN frota_entrega_problema ep ON ep.entrega_id = e.id
            WHERE ep.created_at >= CURRENT_DATE - INTERVAL :dias DAY
            GROUP BY ve.id, ve.placa, ve.modelo
            HAVING COUNT(DISTINCT ep.id) > 0
            ORDER BY total_problemas DESC
            LIMIT 20
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dias' => $dias]);
        $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json($response, [
            'success' => true,
            'data' => $dados,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (\Exception $e) {
        error_log('Erro em resumoVeiculo: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao carregar resumo por veículo'
        ], 500);
    }
}

/**
 * POST /v1/frota/gestao-cargas/exportar
 * Exporta relatório de problemas para CSV
 */
public function exportarProblemas(Request $request, Response $response): Response
{
    try {
        $body = json_decode($request->getBody()->getContents(), true);
        $filtros = $body['filtros'] ?? [];

        $where = [];
        $bind = [];

        if (!empty($filtros['status']) && $filtros['status'] !== 'todos') {
            $where[] = "ep.status_problema = :status";
            $bind['status'] = $filtros['status'];
        }

        if (!empty($filtros['prioridade']) && $filtros['prioridade'] !== 'todas') {
            $where[] = "ep.prioridade = :prioridade";
            $bind['prioridade'] = $filtros['prioridade'];
        }

        if (!empty($filtros['tipo']) && $filtros['tipo'] !== 'todos') {
            $where[] = "ep.tipo_problema = :tipo";
            $bind['tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(ep.created_at) >= :data_inicio";
            $bind['data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(ep.created_at) <= :data_fim";
            $bind['data_fim'] = $filtros['data_fim'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT 
                ep.id,
                ep.entrega_id,
                ep.tipo_problema,
                ep.referencia,
                ep.descricao_problema,
                ep.quantidade_afetada,
                ep.valor_afetado,
                ep.status_problema,
                ep.prioridade,
                ep.created_at as data_problema,
                ep.data_resolucao,
                e.cliente_nome,
                e.cidade,
                e.uf,
                em.numero_embarque,
                mo.nome as motorista_nome,
                ve.placa as veiculo_placa
            FROM frota_entrega_problema ep
            INNER JOIN frota_entrega e ON e.id = ep.entrega_id
            INNER JOIN frota_embarque em ON em.id = ep.embarque_id
            LEFT JOIN frota_motorista mo ON mo.id = em.motorista_id
            LEFT JOIN frota_veiculo ve ON ve.id = em.veiculo_id
            {$whereClause}
            ORDER BY ep.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Gerar CSV
        $headers = [
            'ID', 'Entrega', 'Cliente', 'Cidade/UF', 'Motorista', 'Veículo', 
            'Embarque', 'Tipo', 'Referência', 'Qtd Afetada', 'Valor Afetado',
            'Prioridade', 'Status', 'Data Problema', 'Data Resolução'
        ];

        $output = fopen('php://temp', 'w');
        fputcsv($output, $headers, ';');

        foreach ($dados as $row) {
            fputcsv($output, [
                $row['id'],
                $row['entrega_id'],
                $row['cliente_nome'] ?? '',
                ($row['cidade'] ?? '') . '/' . ($row['uf'] ?? ''),
                $row['motorista_nome'] ?? '',
                $row['veiculo_placa'] ?? '',
                $row['numero_embarque'] ?? '',
                $row['tipo_problema'] ?? '',
                $row['referencia'] ?? '',
                $row['quantidade_afetada'] ?? 0,
                number_format($row['valor_afetado'] ?? 0, 2, ',', '.'),
                $row['prioridade'] ?? '',
                $row['status_problema'] ?? '',
                date('d/m/Y H:i', strtotime($row['data_problema'])),
                $row['data_resolucao'] ? date('d/m/Y H:i', strtotime($row['data_resolucao'])) : ''
            ], ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="problemas_' . date('Y-m-d') . '.csv"')
            ->getBody()
            ->write($csv);

    } catch (\Exception $e) {
        error_log('Erro em exportarProblemas: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false,
            'error' => 'Erro ao exportar relatório'
        ], 500);
    }
}
    
    /**
     * Resposta JSON
     */
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}