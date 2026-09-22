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
                        COUNT(CASE WHEN status IN ('entregue', 'entregue_com_problema') THEN 1 END) as concluidas,
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
                        COUNT(CASE WHEN data_retorno = CURRENT_DATE THEN 1 END) as finalizados_hoje
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
        COALESCE(SUM(valor_total), 0) as faturamento
    FROM frota_entrega
    WHERE EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)
      AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
    AND status IN ('entregue', 'entregue_com_problema')
");
                $mes = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['total_entregas_mes'] = (int)$mes['total'];
                $data['faturamento_mes'] = (float)$mes['faturamento'];
                // ============================================================
                // 6. MÉTRICAS DE ROTA (PESO + KM)
                // ============================================================
                $stmt = $this->pdo->prepare("
                    SELECT
                        COALESCE(SUM(em.distancia_total_km), 0) as total_km,
                        COALESCE((
                            SELECT SUM(ent.peso_total)
                            FROM frota_entrega ent
                            INNER JOIN frota_embarque emb ON emb.id = ent.embarque_id
                            WHERE DATE(emb.data_saida) = CURRENT_DATE
                              AND emb.status = 'finalizado'
                        ), 0) as total_peso
                    FROM frota_embarque em
                    WHERE DATE(em.data_saida) = CURRENT_DATE
                    AND em.status = 'finalizado'
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
                        AVG(EXTRACT(EPOCH FROM (horario_entrega - horario_checkin))/60) as tempo_medio
                    FROM frota_entrega
                    WHERE DATE(created_at) = CURRENT_DATE
                    AND status IN ('entregue', 'entregue_com_problema')
                    AND horario_checkin IS NOT NULL
                    AND horario_entrega IS NOT NULL
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
                        COUNT(CASE WHEN status IN ('entregue', 'entregue_com_problema') AND DATE(horario_entrega) <= data_prevista THEN 1 END) as no_prazo,
                        COUNT(CASE WHEN status IN ('entregue', 'entregue_com_problema') THEN 1 END) as total_entregues
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
                'success' => false,
                'error' => 'Erro ao carregar indicadores do dashboard'
            ], 500);
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
    COUNT(CASE WHEN status IN ('entregue', 'entregue_com_problema') THEN 1 END) as concluidas,
    COUNT(CASE WHEN status IN ('pendente', 'em_andamento') THEN 1 END) as pendentes,
    COALESCE(SUM(CASE WHEN status IN ('entregue', 'entregue_com_problema') THEN valor_total ELSE 0 END), 0) as faturamento
FROM frota_entrega
WHERE DATE(created_at) = :data
AND status != 'cancelada'
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
                        COUNT(CASE WHEN status IN ('entregue', 'entregue_com_problema') THEN 1 END) as concluidas,
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
    COALESCE(SUM(e.valor_total), 0) as total_faturado
FROM frota_motorista m
JOIN frota_embarque em ON em.motorista_id = m.id
JOIN frota_entrega e ON e.embarque_id = em.id
                    WHERE DATE(e.created_at) >= CURRENT_DATE - INTERVAL '30 days'
                    AND e.status IN ('entregue', 'entregue_com_problema')
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
                'success' => false,
                'error' => 'Erro ao carregar gráficos do dashboard'
            ], 500);
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
            $body = json_decode($request->getBody()->getContents(), true) ?? [];
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
            WHERE ep.created_at >= CURRENT_DATE - (:dias || ' days')::interval
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
            WHERE ep.created_at >= CURRENT_DATE - (:dias || ' days')::interval
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
                'ID',
                'Entrega',
                'Cliente',
                'Cidade/UF',
                'Motorista',
                'Veículo',
                'Embarque',
                'Tipo',
                'Referência',
                'Qtd Afetada',
                'Valor Afetado',
                'Prioridade',
                'Status',
                'Data Problema',
                'Data Resolução'
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
     * GET /v1/frota/gestao-cargas/ranking-motoristas
     * Ranking completo de eficiência/ineficiência por motorista.
     *
     * KPIs de eficiência de trajeto:
     *  - tempo_medio_deslocamento_min
     *  - distancia_media_trajeto_km
     *  - tempo_ideal_medio_min
     *  - indice_eficiencia_trajeto   (calculado com 1+ trajetos; NULL se 0)
     *  - trajetos_analisados
     *
     * Flags de confiabilidade (para o frontend avisar o gestor):
     *  - amostra_pequena:        true se 1 ou 2 trajetos (índice existe mas é frágil)
     *  - amostra_insuficiente:   true se 0 trajetos (índice NULL)
     */
    public function rankingMotoristas(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $dias = max(1, min((int)($params['dias'] ?? 30), 365));
            $velRef = $this->getVelocidadeReferenciaKmh();

            // =================================================================
            // QUERY PRINCIPAL — usa CTEs para separar as etapas
            // =================================================================
            $sql = "
            WITH trajetos_base AS (
                SELECT
                    ent.id                    AS entrega_id,
                    ent.embarque_id,
                    em.motorista_id,
                    ent.latitude              AS lat_atual,
                    ent.longitude             AS lng_atual,
                    ent.horario_checkin       AS checkin_atual,
                    LAG(ent.latitude)         OVER w  AS lat_anterior,
                    LAG(ent.longitude)        OVER w  AS lng_anterior,
                    LAG(ent.horario_entrega)  OVER w  AS entrega_anterior
                FROM frota_entrega ent
                INNER JOIN frota_embarque em ON em.id = ent.embarque_id
                WHERE em.data_saida >= CURRENT_DATE - (:dias || ' days')::interval
                  AND em.motorista_id IS NOT NULL
                  AND ent.status IN ('entregue', 'entregue_com_problema')
                  AND ent.horario_checkin IS NOT NULL
                WINDOW w AS (PARTITION BY ent.embarque_id ORDER BY ent.horario_checkin)
            ),
            trajetos_validos AS (
                SELECT
                    tb.motorista_id,
                    tb.embarque_id,
                    tb.entrega_id,
                    tb.lat_atual, tb.lng_atual,
                    tb.lat_anterior, tb.lng_anterior,
                    tb.checkin_atual,
                    tb.entrega_anterior,
                    EXTRACT(EPOCH FROM (tb.checkin_atual - tb.entrega_anterior))/60.0 AS tempo_real_min,
                    fn_haversine_km(tb.lat_anterior, tb.lng_anterior, tb.lat_atual, tb.lng_atual) AS distancia_km
                FROM trajetos_base tb
                WHERE tb.entrega_anterior IS NOT NULL
                  AND tb.lat_atual   IS NOT NULL AND tb.lng_atual   IS NOT NULL
                  AND tb.lat_anterior IS NOT NULL AND tb.lng_anterior IS NOT NULL
                  AND tb.checkin_atual > tb.entrega_anterior
            ),
            trajetos_filtrados AS (
                SELECT
                    tv.motorista_id,
                    tv.tempo_real_min,
                    tv.distancia_km,
                    (tv.distancia_km / :vel_ref) * 60.0 AS tempo_ideal_min
                FROM trajetos_validos tv
                WHERE tv.tempo_real_min >= 1.0
                  AND tv.tempo_real_min <= 180.0
                  AND tv.distancia_km IS NOT NULL
                  AND tv.distancia_km >= 0.1
                  AND tv.distancia_km <= 500.0
                  AND (tv.distancia_km / NULLIF(tv.tempo_real_min, 0)) * 60.0 <= 120.0
            ),
            eficiencia_por_motorista AS (
                SELECT
                    tf.motorista_id,
                    COUNT(*)                                          AS trajetos_analisados,
                    ROUND(AVG(tf.tempo_real_min)::NUMERIC, 1)         AS tempo_medio_deslocamento_min,
                    ROUND(AVG(tf.distancia_km)::NUMERIC, 2)           AS distancia_media_trajeto_km,
                    ROUND(AVG(tf.tempo_ideal_min)::NUMERIC, 1)        AS tempo_ideal_medio_min,
                    -- Índice calculado com 1+ trajetos; NULL apenas quando 0
                    CASE
                        WHEN COUNT(*) >= 1 THEN
                            ROUND(
                                LEAST(100.0, (AVG(tf.tempo_ideal_min) / NULLIF(AVG(tf.tempo_real_min), 0)) * 100)::NUMERIC,
                                1
                            )
                        ELSE NULL
                    END                                               AS indice_eficiencia_trajeto
                FROM trajetos_filtrados tf
                GROUP BY tf.motorista_id
            )
            SELECT
                mo.id,
                mo.nome AS motorista_nome,
                mo.telefone AS motorista_telefone,
                mo.status AS motorista_status,
                COUNT(DISTINCT em.id)   AS total_embarques,
                COUNT(DISTINCT ent.id)  AS total_entregas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue' THEN ent.id END) AS entregas_concluidas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue_com_problema' THEN ent.id END) AS entregas_com_problema,
                COUNT(DISTINCT CASE WHEN ent.status = 'falha' THEN ent.id END) AS entregas_falha,
                COUNT(DISTINCT CASE WHEN ent.status = 'pendente' AND ent.data_prevista < CURRENT_DATE THEN ent.id END) AS entregas_atrasadas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue' AND ent.horario_entrega IS NOT NULL
                    AND ent.data_prevista IS NOT NULL AND DATE(ent.horario_entrega) <= ent.data_prevista THEN ent.id END) AS entregas_no_prazo,
                COUNT(DISTINCT ep.id) AS total_problemas,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'faltante' THEN ep.id END) AS faltantes,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'devolucao' THEN ep.id END) AS devolucoes,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'pendente' THEN ep.id END) AS problemas_pendentes,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'resolvido' THEN ep.id END) AS problemas_resolvidos,
                COALESCE(SUM(ep.valor_afetado), 0) AS valor_total_afetado,
                COALESCE(AVG(CASE WHEN ent.status = 'entregue' AND ent.horario_entrega IS NOT NULL AND ent.horario_checkin IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (ent.horario_entrega - ent.horario_checkin))/60 END), 0) AS tempo_medio_entrega_min,
                ef.trajetos_analisados,
                ef.tempo_medio_deslocamento_min,
                ef.distancia_media_trajeto_km,
                ef.tempo_ideal_medio_min,
                ef.indice_eficiencia_trajeto
            FROM frota_motorista mo
            LEFT JOIN frota_embarque em ON em.motorista_id = mo.id
                AND em.data_saida >= CURRENT_DATE - (:dias2 || ' days')::interval
            LEFT JOIN frota_entrega ent ON ent.embarque_id = em.id
            LEFT JOIN frota_entrega_problema ep ON ep.entrega_id = ent.id
            LEFT JOIN eficiencia_por_motorista ef ON ef.motorista_id = mo.id
            GROUP BY mo.id, mo.nome, mo.telefone, mo.status,
                     ef.trajetos_analisados,
                     ef.tempo_medio_deslocamento_min,
                     ef.distancia_media_trajeto_km,
                     ef.tempo_ideal_medio_min,
                     ef.indice_eficiencia_trajeto
            HAVING COUNT(DISTINCT em.id) > 0
            ORDER BY total_problemas DESC, entregas_atrasadas DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':dias',    $dias, \PDO::PARAM_INT);
            $stmt->bindValue(':dias2',   $dias, \PDO::PARAM_INT);
            $stmt->bindValue(':vel_ref', $velRef);
            $stmt->execute();
            $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // =================================================================
            // Pós-processamento
            // =================================================================
            foreach ($dados as &$m) {
                $totalEntregas = (int)$m['total_entregas'];

                $m['taxa_divergencia'] = $totalEntregas > 0
                    ? round(($m['entregas_com_problema'] + $m['entregas_falha']) / $totalEntregas * 100, 1)
                    : 0.0;

                $m['taxa_no_prazo'] = $totalEntregas > 0
                    ? round($m['entregas_no_prazo'] / $totalEntregas * 100, 1)
                    : 0.0;

                $m['tempo_medio_entrega_min'] = round((float)$m['tempo_medio_entrega_min'], 1);

                // Índice de ineficiência
                $indice = ($m['taxa_divergencia'] * 0.5)
                    + ((100 - $m['taxa_no_prazo']) * 0.3)
                    + (min((int)$m['problemas_pendentes'] * 5, 100) * 0.2);
                $m['indice_ineficiencia'] = round(min($indice, 100), 1);

                // Sanitização dos campos de eficiência
                $m['trajetos_analisados'] = $m['trajetos_analisados'] !== null
                    ? (int)$m['trajetos_analisados'] : 0;
                $m['tempo_medio_deslocamento_min'] = $m['tempo_medio_deslocamento_min'] !== null
                    ? (float)$m['tempo_medio_deslocamento_min'] : null;
                $m['distancia_media_trajeto_km'] = $m['distancia_media_trajeto_km'] !== null
                    ? (float)$m['distancia_media_trajeto_km'] : null;
                $m['tempo_ideal_medio_min'] = $m['tempo_ideal_medio_min'] !== null
                    ? (float)$m['tempo_ideal_medio_min'] : null;
                $m['indice_eficiencia_trajeto'] = $m['indice_eficiencia_trajeto'] !== null
                    ? (float)$m['indice_eficiencia_trajeto'] : null;

                // ================================================================
                // Flags de confiabilidade da amostra
                // ================================================================
                $m['amostra_insuficiente'] = ($m['trajetos_analisados'] === 0);
                $m['amostra_pequena']      = ($m['trajetos_analisados'] >= 1 && $m['trajetos_analisados'] < 3);

                // Score de desempenho (usa as novas regras 2A)
                $m['score_desempenho'] = $this->calcularScoreMotorista($m);
            }
            unset($m);

            // Reordenar pelo índice de ineficiência
            usort($dados, function ($a, $b) {
                return $b['indice_ineficiencia'] <=> $a['indice_ineficiencia'];
            });

            return $this->json($response, [
                'success' => true,
                'data' => $dados,
                'dias' => $dias,
                'velocidade_referencia_kmh' => $velRef,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro em rankingMotoristas: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar ranking de motoristas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/frota/gestao-cargas/historico-embarques
     * Busca completa de embarques (inclusive finalizados) com filtros avançados
     */
    public function historicoEmbarques(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();

            $where = [];
            $bind = [];

            if (!empty($params['status']) && $params['status'] !== 'todos') {
                $where[] = "e.status = :status";
                $bind['status'] = $params['status'];
            }

            if (!empty($params['motorista_id'])) {
                $where[] = "e.motorista_id = :motorista_id";
                $bind['motorista_id'] = (int)$params['motorista_id'];
            }

            if (!empty($params['veiculo_id'])) {
                $where[] = "e.veiculo_id = :veiculo_id";
                $bind['veiculo_id'] = (int)$params['veiculo_id'];
            }

            if (!empty($params['data_inicio'])) {
                $where[] = "e.data_saida >= :data_inicio";
                $bind['data_inicio'] = $params['data_inicio'];
            }

            if (!empty($params['data_fim'])) {
                $where[] = "e.data_saida <= :data_fim";
                $bind['data_fim'] = $params['data_fim'];
            }

            if (!empty($params['busca'])) {
                $where[] = "(e.numero_embarque ILIKE :busca OR e.nome_embarque ILIKE :busca2 
                OR m.nome ILIKE :busca3 OR v.placa ILIKE :busca4 
                OR EXISTS (SELECT 1 FROM frota_entrega fe WHERE fe.embarque_id = e.id AND fe.cliente_nome ILIKE :busca5))";
                $termo = "%{$params['busca']}%";
                $bind['busca'] = $termo;
                $bind['busca2'] = $termo;
                $bind['busca3'] = $termo;
                $bind['busca4'] = $termo;
                $bind['busca5'] = $termo;
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $limite = max(1, min((int)($params['limite'] ?? 20), 100));
            $pagina = max(1, (int)($params['pagina'] ?? 1));
            $offset = ($pagina - 1) * $limite;

            $sql = "
            SELECT
                e.id,
                e.numero_embarque,
                e.nome_embarque,
                e.status AS embarque_status,
                e.data_saida,
                e.data_retorno,
                e.horario_saida,
                e.horario_retorno,
                v.placa AS veiculo_placa,
                v.modelo AS veiculo_modelo,
                m.id AS motorista_id,
                m.nome AS motorista_nome,
                ae.id AS acerto_id,
                ae.status AS acerto_status,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id) AS total_entregas,
                (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = e.id AND status IN ('entregue', 'entregue_com_problema')) AS entregas_concluidas,
                (SELECT COUNT(*) FROM frota_entrega_problema ep2 INNER JOIN frota_entrega fe2 ON fe2.id = ep2.entrega_id WHERE fe2.embarque_id = e.id) AS total_problemas
            FROM frota_embarque e
            LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = e.motorista_id
            LEFT JOIN frota_acerto_embarque ae ON ae.embarque_id = e.id
            {$whereClause}
            ORDER BY e.data_saida DESC, e.id DESC
            LIMIT :limite OFFSET :offset
        ";

            $stmt = $this->pdo->prepare($sql);
            foreach ($bind as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $embarques = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $sqlCount = "
            SELECT COUNT(DISTINCT e.id)
            FROM frota_embarque e
            LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
            LEFT JOIN frota_motorista m ON m.id = e.motorista_id
            {$whereClause}
        ";
            $stmtCount = $this->pdo->prepare($sqlCount);
            foreach ($bind as $key => $value) {
                $stmtCount->bindValue($key, $value);
            }
            $stmtCount->execute();
            $total = (int)$stmtCount->fetchColumn();

            return $this->json($response, [
                'success' => true,
                'data' => $embarques,
                'pagination' => [
                    'total' => $total,
                    'pagina' => $pagina,
                    'limite' => $limite,
                    'total_paginas' => (int)ceil($total / $limite)
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Erro em historicoEmbarques: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar histórico de embarques'
            ], 500);
        }
    }

    /**
     * GET /v1/frota/gestao-cargas/ranking-veiculos
     * Ranking completo de eficiência/ineficiência por veículo (caminhão).
     *
     * KPIs de eficiência de trajeto (com flags de confiabilidade):
     *  - tempo_medio_deslocamento_min
     *  - distancia_media_trajeto_km
     *  - tempo_ideal_medio_min
     *  - indice_eficiencia_trajeto   (calculado com 1+ trajetos; NULL se 0)
     *  - trajetos_analisados
     *  - amostra_pequena             (1-2 trajetos)
     *  - amostra_insuficiente        (0 trajetos)
     */
    public function rankingVeiculos(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $dias = max(1, min((int)($params['dias'] ?? 30), 365));
            $velRef = $this->getVelocidadeReferenciaKmh();

            // =================================================================
            // QUERY PRINCIPAL
            // =================================================================
            $sql = "
            WITH trajetos_base AS (
                SELECT
                    ent.id                    AS entrega_id,
                    ent.embarque_id,
                    em.veiculo_id,
                    ent.latitude              AS lat_atual,
                    ent.longitude             AS lng_atual,
                    ent.horario_checkin       AS checkin_atual,
                    LAG(ent.latitude)         OVER w  AS lat_anterior,
                    LAG(ent.longitude)        OVER w  AS lng_anterior,
                    LAG(ent.horario_entrega)  OVER w  AS entrega_anterior
                FROM frota_entrega ent
                INNER JOIN frota_embarque em ON em.id = ent.embarque_id
                WHERE em.data_saida >= CURRENT_DATE - (:dias || ' days')::interval
                  AND em.veiculo_id IS NOT NULL
                  AND ent.status IN ('entregue', 'entregue_com_problema')
                  AND ent.horario_checkin IS NOT NULL
                WINDOW w AS (PARTITION BY ent.embarque_id ORDER BY ent.horario_checkin)
            ),
            trajetos_validos AS (
                SELECT
                    tb.veiculo_id,
                    tb.embarque_id,
                    tb.entrega_id,
                    EXTRACT(EPOCH FROM (tb.checkin_atual - tb.entrega_anterior))/60.0 AS tempo_real_min,
                    fn_haversine_km(tb.lat_anterior, tb.lng_anterior, tb.lat_atual, tb.lng_atual) AS distancia_km
                FROM trajetos_base tb
                WHERE tb.entrega_anterior IS NOT NULL
                  AND tb.lat_atual   IS NOT NULL AND tb.lng_atual   IS NOT NULL
                  AND tb.lat_anterior IS NOT NULL AND tb.lng_anterior IS NOT NULL
                  AND tb.checkin_atual > tb.entrega_anterior
            ),
            trajetos_filtrados AS (
                SELECT
                    tv.veiculo_id,
                    tv.tempo_real_min,
                    tv.distancia_km,
                    (tv.distancia_km / :vel_ref) * 60.0 AS tempo_ideal_min
                FROM trajetos_validos tv
                WHERE tv.tempo_real_min >= 1.0
                  AND tv.tempo_real_min <= 180.0
                  AND tv.distancia_km IS NOT NULL
                  AND tv.distancia_km >= 0.1
                  AND tv.distancia_km <= 500.0
                  AND (tv.distancia_km / NULLIF(tv.tempo_real_min, 0)) * 60.0 <= 120.0
            ),
            eficiencia_por_veiculo AS (
                SELECT
                    tf.veiculo_id,
                    COUNT(*)                                          AS trajetos_analisados,
                    ROUND(AVG(tf.tempo_real_min)::NUMERIC, 1)         AS tempo_medio_deslocamento_min,
                    ROUND(AVG(tf.distancia_km)::NUMERIC, 2)           AS distancia_media_trajeto_km,
                    ROUND(AVG(tf.tempo_ideal_min)::NUMERIC, 1)        AS tempo_ideal_medio_min,
                    CASE
                        WHEN COUNT(*) >= 1 THEN
                            ROUND(
                                LEAST(100.0, (AVG(tf.tempo_ideal_min) / NULLIF(AVG(tf.tempo_real_min), 0)) * 100)::NUMERIC,
                                1
                            )
                        ELSE NULL
                    END                                               AS indice_eficiencia_trajeto
                FROM trajetos_filtrados tf
                GROUP BY tf.veiculo_id
            )
            SELECT
                ve.id,
                ve.placa,
                ve.modelo,
                ve.marca,
                ve.tipo,
                ve.status AS veiculo_status,
                COUNT(DISTINCT em.id)   AS total_embarques,
                COUNT(DISTINCT ent.id)  AS total_entregas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue' THEN ent.id END) AS entregas_concluidas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue_com_problema' THEN ent.id END) AS entregas_com_problema,
                COUNT(DISTINCT CASE WHEN ent.status = 'falha' THEN ent.id END) AS entregas_falha,
                COUNT(DISTINCT CASE WHEN ent.status = 'pendente' AND ent.data_prevista < CURRENT_DATE THEN ent.id END) AS entregas_atrasadas,
                COUNT(DISTINCT CASE WHEN ent.status = 'entregue' AND ent.horario_entrega IS NOT NULL
                    AND ent.data_prevista IS NOT NULL AND DATE(ent.horario_entrega) <= ent.data_prevista THEN ent.id END) AS entregas_no_prazo,
                COUNT(DISTINCT ep.id) AS total_problemas,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'faltante' THEN ep.id END) AS faltantes,
                COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'devolucao' THEN ep.id END) AS devolucoes,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'pendente' THEN ep.id END) AS problemas_pendentes,
                COUNT(DISTINCT CASE WHEN ep.status_problema = 'resolvido' THEN ep.id END) AS problemas_resolvidos,
                COALESCE(SUM(ep.valor_afetado), 0) AS valor_total_afetado,
                COALESCE(SUM(ent.peso_total), 0)   AS peso_total_transportado,
                COALESCE(AVG(CASE WHEN ent.status = 'entregue' AND ent.horario_entrega IS NOT NULL AND ent.horario_checkin IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (ent.horario_entrega - ent.horario_checkin))/60 END), 0) AS tempo_medio_entrega_min,
                ef.trajetos_analisados,
                ef.tempo_medio_deslocamento_min,
                ef.distancia_media_trajeto_km,
                ef.tempo_ideal_medio_min,
                ef.indice_eficiencia_trajeto
            FROM frota_veiculo ve
            LEFT JOIN frota_embarque em ON em.veiculo_id = ve.id
                AND em.data_saida >= CURRENT_DATE - (:dias2 || ' days')::interval
            LEFT JOIN frota_entrega ent ON ent.embarque_id = em.id
            LEFT JOIN frota_entrega_problema ep ON ep.entrega_id = ent.id
            LEFT JOIN eficiencia_por_veiculo ef ON ef.veiculo_id = ve.id
            GROUP BY ve.id, ve.placa, ve.modelo, ve.marca, ve.tipo, ve.status,
                     ef.trajetos_analisados,
                     ef.tempo_medio_deslocamento_min,
                     ef.distancia_media_trajeto_km,
                     ef.tempo_ideal_medio_min,
                     ef.indice_eficiencia_trajeto
            HAVING COUNT(DISTINCT em.id) > 0
            ORDER BY total_problemas DESC, entregas_atrasadas DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':dias',    $dias, \PDO::PARAM_INT);
            $stmt->bindValue(':dias2',   $dias, \PDO::PARAM_INT);
            $stmt->bindValue(':vel_ref', $velRef);
            $stmt->execute();
            $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // =================================================================
            // Pós-processamento
            // =================================================================
            foreach ($dados as &$v) {
                $totalEntregas = (int)$v['total_entregas'];

                $v['taxa_divergencia'] = $totalEntregas > 0
                    ? round(($v['entregas_com_problema'] + $v['entregas_falha']) / $totalEntregas * 100, 1)
                    : 0.0;

                $v['taxa_no_prazo'] = $totalEntregas > 0
                    ? round($v['entregas_no_prazo'] / $totalEntregas * 100, 1)
                    : 0.0;

                $v['tempo_medio_entrega_min'] = round((float)$v['tempo_medio_entrega_min'], 1);

                // Índice de ineficiência
                $indice = ($v['taxa_divergencia'] * 0.5)
                    + ((100 - $v['taxa_no_prazo']) * 0.3)
                    + (min((int)$v['problemas_pendentes'] * 5, 100) * 0.2);
                $v['indice_ineficiencia'] = round(min($indice, 100), 1);

                // Sanitização dos campos de eficiência
                $v['trajetos_analisados'] = $v['trajetos_analisados'] !== null
                    ? (int)$v['trajetos_analisados'] : 0;
                $v['tempo_medio_deslocamento_min'] = $v['tempo_medio_deslocamento_min'] !== null
                    ? (float)$v['tempo_medio_deslocamento_min'] : null;
                $v['distancia_media_trajeto_km'] = $v['distancia_media_trajeto_km'] !== null
                    ? (float)$v['distancia_media_trajeto_km'] : null;
                $v['tempo_ideal_medio_min'] = $v['tempo_ideal_medio_min'] !== null
                    ? (float)$v['tempo_ideal_medio_min'] : null;
                $v['indice_eficiencia_trajeto'] = $v['indice_eficiencia_trajeto'] !== null
                    ? (float)$v['indice_eficiencia_trajeto'] : null;

                // Flags de confiabilidade
                $v['amostra_insuficiente'] = ($v['trajetos_analisados'] === 0);
                $v['amostra_pequena']      = ($v['trajetos_analisados'] >= 1 && $v['trajetos_analisados'] < 3);
            }
            unset($v);

            usort($dados, function ($a, $b) {
                return $b['indice_ineficiencia'] <=> $a['indice_ineficiencia'];
            });

            return $this->json($response, [
                'success' => true,
                'data' => $dados,
                'dias' => $dias,
                'velocidade_referencia_kmh' => $velRef,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro em rankingVeiculos: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar ranking de veículos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/frota/gestao-cargas/graficos
     * Séries e distribuições para os gráficos do dashboard de Gestão de Cargas
     */
    public function graficosCargas(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $dias = max(1, min((int)($params['dias'] ?? 14), 90));

            // 1. Evolução diária de problemas (criados x resolvidos)
            $evolucao = [];
            for ($i = $dias - 1; $i >= 0; $i--) {
                $data = date('Y-m-d', strtotime("-$i days"));
                $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(CASE WHEN DATE(created_at) = :data THEN 1 END) AS criados,
                    COUNT(CASE WHEN DATE(data_resolucao) = :data2 THEN 1 END) AS resolvidos
                FROM frota_entrega_problema
                WHERE DATE(created_at) = :data3 OR DATE(data_resolucao) = :data4
            ");
                $stmt->execute(['data' => $data, 'data2' => $data, 'data3' => $data, 'data4' => $data]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $evolucao[] = [
                    'data' => $data,
                    'label' => date('d/m', strtotime($data)),
                    'criados' => (int)($row['criados'] ?? 0),
                    'resolvidos' => (int)($row['resolvidos'] ?? 0)
                ];
            }

            // 2. Distribuição por tipo de problema
            $stmt = $this->pdo->query("
            SELECT tipo_problema, COUNT(*) AS total, COALESCE(SUM(valor_afetado), 0) AS valor
            FROM frota_entrega_problema
            GROUP BY tipo_problema
            ORDER BY total DESC
        ");
            $porTipo = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Distribuição por prioridade (ativos)
            $stmt = $this->pdo->query("
            SELECT prioridade, COUNT(*) AS total
            FROM frota_entrega_problema
            WHERE status_problema NOT IN ('resolvido', 'cancelado')
            GROUP BY prioridade
        ");
            $porPrioridade = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Top 5 motoristas com mais problemas (para o gráfico comparativo)
            $stmt = $this->pdo->prepare("
            SELECT mo.nome AS motorista_nome, COUNT(DISTINCT ep.id) AS total_problemas
            FROM frota_motorista mo
            INNER JOIN frota_embarque em ON em.motorista_id = mo.id
            INNER JOIN frota_entrega ent ON ent.embarque_id = em.id
            INNER JOIN frota_entrega_problema ep ON ep.entrega_id = ent.id
            WHERE ep.created_at >= CURRENT_DATE - (:dias || ' days')::interval
            GROUP BY mo.id, mo.nome
            ORDER BY total_problemas DESC
            LIMIT 5
        ");
            $stmt->execute(['dias' => $dias]);
            $topMotoristasProblemas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 5. Top 5 veículos com mais problemas
            $stmt = $this->pdo->prepare("
            SELECT ve.placa, COUNT(DISTINCT ep.id) AS total_problemas
            FROM frota_veiculo ve
            INNER JOIN frota_embarque em ON em.veiculo_id = ve.id
            INNER JOIN frota_entrega ent ON ent.embarque_id = em.id
            INNER JOIN frota_entrega_problema ep ON ep.entrega_id = ent.id
            WHERE ep.created_at >= CURRENT_DATE - (:dias || ' days')::interval
            GROUP BY ve.id, ve.placa
            ORDER BY total_problemas DESC
            LIMIT 5
        ");
            $stmt->execute(['dias' => $dias]);
            $topVeiculosProblemas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->json($response, [
                'success' => true,
                'data' => [
                    'evolucao_diaria' => $evolucao,
                    'por_tipo' => $porTipo,
                    'por_prioridade' => $porPrioridade,
                    'top_motoristas_problemas' => $topMotoristasProblemas,
                    'top_veiculos_problemas' => $topVeiculosProblemas
                ],
                'dias' => $dias,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro em graficosCargas: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar gráficos de gestão de cargas'
            ], 500);
        }
    }

    /**
     * GET /v1/frota/gestao-cargas/embarque/{id}/detalhes-completos
     * Rastreabilidade total: timeline, entregas, itens, fotos, problemas e acerto de um embarque
     */
    public function embarqueDetalhesCompletos(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)$args['id'];

            $stmt = $this->pdo->prepare("
                SELECT
                    e.*,
                    v.placa AS veiculo_placa,
                    v.modelo AS veiculo_modelo,
                    v.marca AS veiculo_marca,
                    m.id AS motorista_id,
                    m.nome AS motorista_nome,
                    m.telefone AS motorista_telefone
                FROM frota_embarque e
                LEFT JOIN frota_veiculo v ON v.id = e.veiculo_id
                LEFT JOIN frota_motorista m ON m.id = e.motorista_id
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $embarque = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$embarque) {
                return $this->json($response, ['success' => false, 'error' => 'Embarque não encontrado'], 404);
            }

            // Entregas + cliente
            $stmtEntregas = $this->pdo->prepare("
                SELECT
                    ent.*,
                    c.nome AS cliente_nome_cadastro,
                    c.telefone AS cliente_telefone,
                    c.endereco AS cliente_endereco,
                    c.cidade AS cliente_cidade,
                    c.uf AS cliente_uf
                FROM frota_entrega ent
                LEFT JOIN frota_cliente c ON c.id = ent.cliente_id
                WHERE ent.embarque_id = :id
                ORDER BY ent.ordem_entrega ASC, ent.id ASC
            ");
            $stmtEntregas->execute(['id' => $id]);
            $entregas = $stmtEntregas->fetchAll(\PDO::FETCH_ASSOC);

            $entregaIds = array_column($entregas, 'id');
            $checklistPorEntrega = [];
            $problemasPorEntrega = [];
            $timelinePorEntrega = [];

            if (!empty($entregaIds)) {
                $placeholders = implode(',', array_fill(0, count($entregaIds), '?'));

                $stmtChecklist = $this->pdo->prepare("
                    SELECT entrega_id, item_id, referencia, descricao, foto_url,
                        quantidade_prevista, quantidade_entregue, status, motivo
                    FROM frota_checklist_entrega
                    WHERE entrega_id IN ({$placeholders})
                    ORDER BY id ASC
                ");
                $stmtChecklist->execute($entregaIds);
                foreach ($stmtChecklist->fetchAll(\PDO::FETCH_ASSOC) as $item) {
                    $checklistPorEntrega[$item['entrega_id']][] = $item;
                }

                $stmtProblemas = $this->pdo->prepare("
                    SELECT entrega_id, id, tipo_problema, referencia, descricao_problema,
                        quantidade_afetada, valor_afetado, status_problema, prioridade,
                        created_at, data_resolucao
                    FROM frota_entrega_problema
                    WHERE entrega_id IN ({$placeholders})
                    ORDER BY created_at DESC
                ");
                $stmtProblemas->execute($entregaIds);
                foreach ($stmtProblemas->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                    $problemasPorEntrega[$p['entrega_id']][] = $p;
                }

                // Timeline por entrega (se a tabela existir)
                try {
                    $stmtTimeline = $this->pdo->prepare("
                        SELECT entrega_id, acao, descricao, usuario_nome, created_at
                        FROM frota_entrega_timeline
                        WHERE entrega_id IN ({$placeholders})
                        ORDER BY created_at ASC
                    ");
                    $stmtTimeline->execute($entregaIds);
                    foreach ($stmtTimeline->fetchAll(\PDO::FETCH_ASSOC) as $t) {
                        $timelinePorEntrega[$t['entrega_id']][] = $t;
                    }
                } catch (\Exception $ignored) {
                    // tabela pode não existir em algumas bases
                }
            }

            foreach ($entregas as &$ent) {
                $ent['checklist'] = $checklistPorEntrega[$ent['id']] ?? [];
                $ent['problemas'] = $problemasPorEntrega[$ent['id']] ?? [];
                $ent['timeline'] = $timelinePorEntrega[$ent['id']] ?? [];
            }
            unset($ent);
            $embarque['entregas'] = $entregas;

            // Timeline geral do embarque (logs)
            $stmtLogs = $this->pdo->prepare("
                SELECT l.acao, l.descricao, l.created_at,
                    COALESCE(u.nome, 'Sistema') AS usuario_nome
                FROM frota_log_embarque l
                LEFT JOIN usuario u ON u.id = l.usuario_id
                WHERE l.embarque_id = :id
                ORDER BY l.created_at ASC
            ");
            $stmtLogs->execute(['id' => $id]);
            $embarque['timeline_embarque'] = $stmtLogs->fetchAll(\PDO::FETCH_ASSOC);

            // Histórico de posições GPS (rota completa do embarque)
            try {
                $stmtPos = $this->pdo->prepare("
                    SELECT latitude, longitude, velocidade, created_at
                    FROM frota_historico_posicao
                    WHERE embarque_id = :id
                    ORDER BY created_at ASC
                ");
                $stmtPos->execute(['id' => $id]);
                $embarque['rota_posicoes'] = $stmtPos->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $ignored) {
                $embarque['rota_posicoes'] = [];
            }

            // Acerto/conferência
            $stmtAcerto = $this->pdo->prepare("
                SELECT id, status, data_inicio_acerto, data_fim_acerto
                FROM frota_acerto_embarque
                WHERE embarque_id = :id
                ORDER BY id DESC LIMIT 1
            ");
            $stmtAcerto->execute(['id' => $id]);
            $embarque['acerto'] = $stmtAcerto->fetch(\PDO::FETCH_ASSOC) ?: null;

            // Resumo/contadores
            $total = count($entregas);
            $concluidas = count(array_filter($entregas, fn($e) => in_array($e['status'], ['entregue', 'entregue_com_problema'])));
            $totalProblemas = array_sum(array_map(fn($e) => count($e['problemas']), $entregas));
            $embarque['resumo'] = [
                'total_entregas' => $total,
                'entregas_concluidas' => $concluidas,
                'total_problemas' => $totalProblemas,
                'percentual_concluido' => $total > 0 ? round($concluidas / $total * 100, 1) : 0
            ];

            return $this->json($response, [
                'success' => true,
                'data' => $embarque,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro em embarqueDetalhesCompletos: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar detalhes completos do embarque'
            ], 500);
        }
    }

    /**
     * GET /v1/frota/gestao-cargas/motorista/{id}/perfil
     * Perfil completo e rastreável do motorista.
     *
     * Inclui KPIs de eficiência de trajeto com flags de confiabilidade:
     *  - indice_eficiencia_trajeto (calculado com 1+ trajetos)
     *  - amostra_pequena (1-2 trajetos)
     *  - amostra_insuficiente (0 trajetos)
     */
    public function motoristaPerfilCompleto(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)$args['id'];
            $params = $request->getQueryParams();
            $dias = max(1, min((int)($params['dias'] ?? 90), 365));
            $velRef = $this->getVelocidadeReferenciaKmh();

            // -------------------------------------------------------------
            // 1) Dados cadastrais do motorista
            // -------------------------------------------------------------
            $stmtMotorista = $this->pdo->prepare("
                SELECT
                    id,
                    erp_id,
                    nome,
                    cpf,
                    cnh,
                    categoria_cnh,
                    data_validade_cnh,
                    telefone,
                    telefone_emergencia,
                    email,
                    data_nascimento,
                    data_admissao,
                    endereco,
                    bairro,
                    cidade,
                    uf,
                    cep,
                    complemento,
                    numero,
                    status,
                    veiculo_atual_id,
                    latitude,
                    longitude,
                    ultima_posicao,
                    created_at,
                    updated_at
                FROM frota_motorista
                WHERE id = :id
            ");
            $stmtMotorista->execute(['id' => $id]);
            $motorista = $stmtMotorista->fetch(\PDO::FETCH_ASSOC);

            if (!$motorista) {
                return $this->json($response, ['success' => false, 'error' => 'Motorista não encontrado'], 404);
            }

            // -------------------------------------------------------------
            // 2) Métricas de performance geral
            // -------------------------------------------------------------
            $sqlMetricas = "
                SELECT
                    COUNT(DISTINCT em.id) AS total_embarques,
                    COUNT(DISTINCT ent.id) AS total_entregas,
                    COUNT(DISTINCT CASE WHEN ent.status = 'entregue' THEN ent.id END) AS entregas_concluidas,
                    COUNT(DISTINCT CASE WHEN ent.status = 'entregue_com_problema' THEN ent.id END) AS entregas_com_problema,
                    COUNT(DISTINCT CASE WHEN ent.status = 'falha' THEN ent.id END) AS entregas_falha,
                    COUNT(DISTINCT CASE WHEN ent.status = 'pendente' AND ent.data_prevista < CURRENT_DATE THEN ent.id END) AS entregas_atrasadas,
                    COUNT(DISTINCT CASE WHEN ent.status = 'entregue' AND ent.horario_entrega IS NOT NULL
                        AND ent.data_prevista IS NOT NULL AND DATE(ent.horario_entrega) <= ent.data_prevista THEN ent.id END) AS entregas_no_prazo,
                    COUNT(DISTINCT ep.id) AS total_problemas,
                    COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'faltante' THEN ep.id END) AS faltantes,
                    COUNT(DISTINCT CASE WHEN ep.tipo_problema = 'devolucao' THEN ep.id END) AS devolucoes,
                    COUNT(DISTINCT CASE WHEN ep.status_problema = 'pendente' THEN ep.id END) AS problemas_pendentes,
                    COUNT(DISTINCT CASE WHEN ep.status_problema = 'resolvido' THEN ep.id END) AS problemas_resolvidos,
                    COALESCE(SUM(ep.valor_afetado), 0) AS valor_total_afetado,
                    COALESCE(AVG(CASE WHEN ent.status = 'entregue' AND ent.horario_entrega IS NOT NULL AND ent.horario_checkin IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (ent.horario_entrega - ent.horario_checkin))/60 END), 0) AS tempo_medio_entrega_min
                FROM frota_embarque em
                LEFT JOIN frota_entrega ent ON ent.embarque_id = em.id
                LEFT JOIN frota_entrega_problema ep ON ep.entrega_id = ent.id
                WHERE em.motorista_id = :id
                    AND em.data_saida >= CURRENT_DATE - (:dias || ' days')::interval
            ";
            $stmt = $this->pdo->prepare($sqlMetricas);
            $stmt->execute(['id' => $id, 'dias' => $dias]);
            $metricas = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totalEntregas = (int)$metricas['total_entregas'];
            $metricas['taxa_divergencia'] = $totalEntregas > 0
                ? round(($metricas['entregas_com_problema'] + $metricas['entregas_falha']) / $totalEntregas * 100, 1)
                : 0.0;
            $metricas['taxa_no_prazo'] = $totalEntregas > 0
                ? round($metricas['entregas_no_prazo'] / $totalEntregas * 100, 1)
                : 0.0;
            $metricas['tempo_medio_entrega_min'] = round((float)$metricas['tempo_medio_entrega_min'], 1);

            $indice = ($metricas['taxa_divergencia'] * 0.5)
                + ((100 - $metricas['taxa_no_prazo']) * 0.3)
                + (min($metricas['problemas_pendentes'] * 5, 100) * 0.2);
            $metricas['indice_ineficiencia'] = round(min($indice, 100), 1);

            // -------------------------------------------------------------
            // 3) KPIs de eficiência de trajeto (com filtros de sanidade)
            // -------------------------------------------------------------
            $sqlEficiencia = "
            WITH trajetos_base AS (
                SELECT
                    ent.id                    AS entrega_id,
                    ent.embarque_id,
                    ent.latitude              AS lat_atual,
                    ent.longitude             AS lng_atual,
                    ent.horario_checkin       AS checkin_atual,
                    LAG(ent.latitude)         OVER w  AS lat_anterior,
                    LAG(ent.longitude)        OVER w  AS lng_anterior,
                    LAG(ent.horario_entrega)  OVER w  AS entrega_anterior
                FROM frota_entrega ent
                INNER JOIN frota_embarque em ON em.id = ent.embarque_id
                WHERE em.motorista_id = :id
                  AND em.data_saida >= CURRENT_DATE - (:dias || ' days')::interval
                  AND ent.status IN ('entregue', 'entregue_com_problema')
                  AND ent.horario_checkin IS NOT NULL
                WINDOW w AS (PARTITION BY ent.embarque_id ORDER BY ent.horario_checkin)
            ),
            trajetos_validos AS (
                SELECT
                    tb.embarque_id,
                    tb.entrega_id,
                    EXTRACT(EPOCH FROM (tb.checkin_atual - tb.entrega_anterior))/60.0 AS tempo_real_min,
                    fn_haversine_km(tb.lat_anterior, tb.lng_anterior, tb.lat_atual, tb.lng_atual) AS distancia_km
                FROM trajetos_base tb
                WHERE tb.entrega_anterior IS NOT NULL
                  AND tb.lat_atual   IS NOT NULL AND tb.lng_atual   IS NOT NULL
                  AND tb.lat_anterior IS NOT NULL AND tb.lng_anterior IS NOT NULL
                  AND tb.checkin_atual > tb.entrega_anterior
            ),
            trajetos_filtrados AS (
                SELECT
                    tv.tempo_real_min,
                    tv.distancia_km,
                    (tv.distancia_km / :vel_ref) * 60.0 AS tempo_ideal_min
                FROM trajetos_validos tv
                WHERE tv.tempo_real_min >= 1.0
                  AND tv.tempo_real_min <= 180.0
                  AND tv.distancia_km IS NOT NULL
                  AND tv.distancia_km >= 0.1
                  AND tv.distancia_km <= 500.0
                  AND (tv.distancia_km / NULLIF(tv.tempo_real_min, 0)) * 60.0 <= 120.0
            )
            SELECT
                COUNT(*)                                          AS trajetos_analisados,
                ROUND(AVG(tempo_real_min)::NUMERIC, 1)            AS tempo_medio_deslocamento_min,
                ROUND(AVG(distancia_km)::NUMERIC, 2)              AS distancia_media_trajeto_km,
                ROUND(AVG(tempo_ideal_min)::NUMERIC, 1)           AS tempo_ideal_medio_min,
                CASE
                    WHEN COUNT(*) >= 1 THEN
                        ROUND(
                            LEAST(100.0, (AVG(tempo_ideal_min) / NULLIF(AVG(tempo_real_min), 0)) * 100)::NUMERIC,
                            1
                        )
                    ELSE NULL
                END                                               AS indice_eficiencia_trajeto
            FROM trajetos_filtrados
            ";
            $stmt = $this->pdo->prepare($sqlEficiencia);
            $stmt->bindValue(':id',      $id, \PDO::PARAM_INT);
            $stmt->bindValue(':dias',    $dias, \PDO::PARAM_INT);
            $stmt->bindValue(':vel_ref', $velRef);
            $stmt->execute();
            $eficiencia = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            // Mescla os KPIs de eficiência nas métricas
            $metricas['trajetos_analisados'] = isset($eficiencia['trajetos_analisados'])
                ? (int)$eficiencia['trajetos_analisados'] : 0;
            $metricas['tempo_medio_deslocamento_min'] = $eficiencia['tempo_medio_deslocamento_min'] !== null
                ? (float)$eficiencia['tempo_medio_deslocamento_min'] : null;
            $metricas['distancia_media_trajeto_km'] = $eficiencia['distancia_media_trajeto_km'] !== null
                ? (float)$eficiencia['distancia_media_trajeto_km'] : null;
            $metricas['tempo_ideal_medio_min'] = $eficiencia['tempo_ideal_medio_min'] !== null
                ? (float)$eficiencia['tempo_ideal_medio_min'] : null;
            $metricas['indice_eficiencia_trajeto'] = $eficiencia['indice_eficiencia_trajeto'] !== null
                ? (float)$eficiencia['indice_eficiencia_trajeto'] : null;

            // Flags de confiabilidade da amostra
            $metricas['amostra_insuficiente'] = ($metricas['trajetos_analisados'] === 0);
            $metricas['amostra_pequena']      = ($metricas['trajetos_analisados'] >= 1 && $metricas['trajetos_analisados'] < 3);

            // Score de desempenho
            $metricas['score_desempenho'] = $this->calcularScoreMotorista($metricas);

            // -------------------------------------------------------------
            // 4) Veículos utilizados historicamente
            // -------------------------------------------------------------
            $stmtVeiculos = $this->pdo->prepare("
                SELECT v.id, v.placa, v.modelo, v.marca,
                    COUNT(DISTINCT em.id) AS total_embarques,
                    MAX(em.data_saida) AS ultimo_uso
                FROM frota_embarque em
                INNER JOIN frota_veiculo v ON v.id = em.veiculo_id
                WHERE em.motorista_id = :id
                GROUP BY v.id, v.placa, v.modelo, v.marca
                ORDER BY total_embarques DESC
            ");
            $stmtVeiculos->execute(['id' => $id]);
            $veiculos = $stmtVeiculos->fetchAll(\PDO::FETCH_ASSOC);

            // -------------------------------------------------------------
            // 5) Todos os embarques do motorista
            // -------------------------------------------------------------
            $stmtEmbarques = $this->pdo->prepare("
                SELECT
                    em.id, em.numero_embarque, em.nome_embarque, em.status AS embarque_status,
                    em.data_saida, em.data_retorno,
                    v.placa AS veiculo_placa,
                    ae.status AS acerto_status,
                    (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = em.id) AS total_entregas,
                    (SELECT COUNT(*) FROM frota_entrega WHERE embarque_id = em.id AND status IN ('entregue','entregue_com_problema')) AS entregas_concluidas,
                    (SELECT COUNT(*) FROM frota_entrega_problema ep2 INNER JOIN frota_entrega fe2 ON fe2.id = ep2.entrega_id WHERE fe2.embarque_id = em.id) AS total_problemas
                FROM frota_embarque em
                LEFT JOIN frota_veiculo v ON v.id = em.veiculo_id
                LEFT JOIN frota_acerto_embarque ae ON ae.embarque_id = em.id
                WHERE em.motorista_id = :id
                ORDER BY em.data_saida DESC, em.id DESC
                LIMIT 100
            ");
            $stmtEmbarques->execute(['id' => $id]);
            $embarques = $stmtEmbarques->fetchAll(\PDO::FETCH_ASSOC);

            // -------------------------------------------------------------
            // 6) Pontos positivos e negativos
            // -------------------------------------------------------------
            $pontosPositivos = [];
            $pontosNegativos = [];

            if ($metricas['entregas_concluidas'] > 0) {
                $pontosPositivos[] = "{$metricas['entregas_concluidas']} entregas concluídas com sucesso";
            }
            if ($metricas['taxa_no_prazo'] >= 90) {
                $pontosPositivos[] = "Excelente pontualidade: {$metricas['taxa_no_prazo']}% das entregas no prazo";
            } elseif ($metricas['taxa_no_prazo'] >= 75) {
                $pontosPositivos[] = "Boa pontualidade: {$metricas['taxa_no_prazo']}% das entregas no prazo";
            }
            if ($metricas['taxa_divergencia'] <= 5 && $totalEntregas > 0) {
                $pontosPositivos[] = "Baixíssima taxa de divergência: {$metricas['taxa_divergencia']}%";
            }
            if ($metricas['problemas_resolvidos'] > 0) {
                $pontosPositivos[] = "{$metricas['problemas_resolvidos']} problemas resolvidos adequadamente";
            }
            if ($metricas['indice_eficiencia_trajeto'] !== null && $metricas['indice_eficiencia_trajeto'] >= 85) {
                $sufixo = $metricas['amostra_pequena'] ? ' (amostra pequena)' : '';
                $pontosPositivos[] = "Alta eficiência de trajeto: {$metricas['indice_eficiencia_trajeto']}%{$sufixo}";
            }

            if ($metricas['entregas_atrasadas'] > 0) {
                $pontosNegativos[] = "{$metricas['entregas_atrasadas']} entregas em atraso";
            }
            if ($metricas['taxa_divergencia'] > 15) {
                $pontosNegativos[] = "Alta taxa de divergência: {$metricas['taxa_divergencia']}%";
            }
            if ($metricas['problemas_pendentes'] > 0) {
                $pontosNegativos[] = "{$metricas['problemas_pendentes']} problemas ainda pendentes de resolução";
            }
            if ($metricas['entregas_falha'] > 0) {
                $pontosNegativos[] = "{$metricas['entregas_falha']} entregas com falha total";
            }
            if ($metricas['indice_eficiencia_trajeto'] !== null && $metricas['indice_eficiencia_trajeto'] < 60) {
                $sufixo = $metricas['amostra_pequena'] ? ' (amostra pequena)' : '';
                $pontosNegativos[] = "Baixa eficiência de trajeto: {$metricas['indice_eficiencia_trajeto']}%{$sufixo} (possíveis paradas ou desvios)";
            }

            // -------------------------------------------------------------
            // 7) Resposta
            // -------------------------------------------------------------
            return $this->json($response, [
                'success' => true,
                'data' => [
                    'motorista' => $motorista,
                    'metricas' => $metricas,
                    'veiculos_utilizados' => $veiculos,
                    'embarques' => $embarques,
                    'pontos_positivos' => $pontosPositivos,
                    'pontos_negativos' => $pontosNegativos
                ],
                'dias' => $dias,
                'velocidade_referencia_kmh' => $velRef,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro em motoristaPerfilCompleto: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao carregar perfil completo do motorista: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calcula o score de desempenho (0-100) do motorista.
     *
     * Pesos quando há eficiência de trajeto (peso cheio):
     *   - Conclusão de entregas:    25%
     *   - Pontualidade:             25%
     *   - Ausência de divergência:  20%
     *   - Eficiência de trajeto:    20%
     *   - Resolução de problemas:   10%
     *
     * Quando NÃO há eficiência (menos de 1 trajeto válido):
     *   - Mantém os 4 pesos restantes (25+25+20+10 = 80%) e renormaliza
     *   - Aplica multiplicador de 0.9 como penalização leve
     *   - Incentiva o motorista a fazer check-in/checkout corretamente
     *
     * Fator de confiança (aplicado sempre no final):
     *   - min(1.0, total_entregas / 10)
     *   - Amostra pequena (< 10 entregas) gera score proporcionalmente reduzido
     *   - Evita "score 100" com 1-2 entregas
     */
    private function calcularScoreMotorista(array $m): float
    {
        $totalEntregas = (int)($m['total_entregas'] ?? 0);
        if ($totalEntregas === 0) {
            return 0.0;
        }

        $taxaConclusao = round(
            ((int)($m['entregas_concluidas'] ?? 0) + (int)($m['entregas_com_problema'] ?? 0))
            / $totalEntregas * 100,
            1
        );
        $taxaNoPrazo     = (float)($m['taxa_no_prazo'] ?? 0);
        $taxaDivergencia = (float)($m['taxa_divergencia'] ?? 0);

        $problemasPendentes  = (int)($m['problemas_pendentes'] ?? 0);
        $problemasResolvidos = (int)($m['problemas_resolvidos'] ?? 0);
        $totalProblemas      = $problemasPendentes + $problemasResolvidos;
        $taxaResolucao       = $totalProblemas > 0
            ? ($problemasResolvidos / $totalProblemas * 100)
            : 100;

        // Ausência de divergência (quanto menor a divergência, melhor)
        $ausenciaDivergencia = 100 - min($taxaDivergencia, 100);

        // ================================================================
        // Eficiência de trajeto (só entra se existir)
        // ================================================================
        $indiceEficiencia = $m['indice_eficiencia_trajeto'] ?? null;
        $temEficiencia    = $indiceEficiencia !== null && $indiceEficiencia !== '';

        if ($temEficiencia) {
            // Fórmula com peso cheio (soma 1.00)
            $eficiencia = (float)$indiceEficiencia;

            $scoreBase = ($taxaConclusao       * 0.25)
                       + ($taxaNoPrazo         * 0.25)
                       + ($ausenciaDivergencia * 0.20)
                       + ($eficiencia          * 0.20)
                       + ($taxaResolucao       * 0.10);
        } else {
            // Renormaliza os 4 pesos restantes para somar 1.00 e aplica 0.9
            // Pesos originais: 0.25, 0.25, 0.20, 0.10 (soma 0.80)
            // Redistribuição proporcional para somar 1.00:
            //   0.25 / 0.80 = 0.3125
            //   0.25 / 0.80 = 0.3125
            //   0.20 / 0.80 = 0.25
            //   0.10 / 0.80 = 0.125
            // Multiplicador final de 0.9 penaliza levemente a ausência de dados
            $scoreBase = ($taxaConclusao       * 0.3125)
                       + ($taxaNoPrazo         * 0.3125)
                       + ($ausenciaDivergencia * 0.25)
                       + ($taxaResolucao       * 0.125);

            $scoreBase = $scoreBase * 0.9;
        }

        // ================================================================
        // Fator de confiança (amostra pequena diluí o score)
        // ================================================================
        $fatorConfianca = min(1.0, $totalEntregas / 10);

        $scoreFinal = $scoreBase * $fatorConfianca;

        return round(max(0, min($scoreFinal, 100)), 1);
    }
/**
 * Retorna a velocidade de referência (km/h) usada para calcular o tempo ideal
 * de deslocamento. Configurável via frota_configuracao (chave: velocidade_referencia_kmh).
 */
private function getVelocidadeReferenciaKmh(): float
{
    try {
        $stmt = $this->pdo->prepare("
            SELECT valor FROM frota_configuracao WHERE chave = 'velocidade_referencia_kmh'
        ");
        $stmt->execute();
        $valor = $stmt->fetchColumn();
        if ($valor !== false && is_numeric($valor) && (float)$valor > 0) {
            return (float)$valor;
        }
    } catch (\Throwable $e) {
        error_log('[Dashboard] Falha ao ler velocidade_referencia_kmh: ' . $e->getMessage());
    }
    return 40.0; // fallback seguro
}

    /**
     * GET /v1/frota/dashboard/acerto-kpis
     *
     * 🔥 NOVO 2026-09-21 (Bloco 6):
     *   KPIs consolidados de TRATAMENTO do acerto (Camada 2).
     *   Diferente de kpisProblemas() que olha o FATO (Camada 1 - frota_entrega_problema).
     *   Aqui olhamos o TRATAMENTO (Camada 2 - frota_problema_tratamento) e
     *   o DOCUMENTO (Camada 3 - frota_acerto_pedido).
     *
     * Query params opcionais:
     *   - data_inicio (Y-m-d)
     *   - data_fim    (Y-m-d)
     *   - id_filial   (int, default 1) — hoje não filtra por filial, placeholder
     */
    public function acertoKpis(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $dataInicio = $params['data_inicio'] ?? null;
            $dataFim    = $params['data_fim'] ?? null;

            $filtroData = '';
            $bindParams = [];
            if ($dataInicio) {
                $filtroData .= " AND ap.created_at >= :data_inicio";
                $bindParams['data_inicio'] = $dataInicio . ' 00:00:00';
            }
            if ($dataFim) {
                $filtroData .= " AND ap.created_at <= :data_fim";
                $bindParams['data_fim'] = $dataFim . ' 23:59:59';
            }

            $data = [
                'faltantes' => [
                    'total'        => 0,
                    'com_estoque'  => 0,
                    'sem_estoque'  => 0,
                    'pendentes'    => 0,
                    'criados_erp'  => 0,
                    'valor_total'  => 0.0,
                ],
                'devolucoes' => [
                    'total'                 => 0,
                    'aguardando_fat'        => 0,
                    'comprovantes_emitidos' => 0,
                ],
                'tempo_medio_comprovante_horas' => 0.0,
                'top_clientes' => [],
            ];

            if ($this->pdo) {
                // ============================================================
                // 1. FALTANTES — por tipo de tratamento (Camada 3)
                // ============================================================
                $sql = "
                    SELECT
                        COUNT(*) AS total,
                        COUNT(CASE WHEN tipo_tratamento = 'faltante_com_estoque' THEN 1 END) AS com_estoque,
                        COUNT(CASE WHEN tipo_tratamento = 'faltante_sem_estoque' THEN 1 END) AS sem_estoque,
                        COUNT(CASE WHEN status = 'pendente' THEN 1 END)   AS pendentes,
                        COUNT(CASE WHEN status = 'criado_erp' THEN 1 END) AS criados_erp,
                        COALESCE(SUM(valor_total), 0) AS valor_total
                    FROM frota_acerto_pedido ap
                    WHERE ap.tipo_problema = 'faltante'
                    {$filtroData}
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($bindParams);
                $faltantes = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $data['faltantes']['total']       = (int)($faltantes['total'] ?? 0);
                $data['faltantes']['com_estoque'] = (int)($faltantes['com_estoque'] ?? 0);
                $data['faltantes']['sem_estoque'] = (int)($faltantes['sem_estoque'] ?? 0);
                $data['faltantes']['pendentes']   = (int)($faltantes['pendentes'] ?? 0);
                $data['faltantes']['criados_erp'] = (int)($faltantes['criados_erp'] ?? 0);
                $data['faltantes']['valor_total'] = (float)($faltantes['valor_total'] ?? 0);

                // ============================================================
                // 2. DEVOLUÇÕES — por status (Camada 2)
                // ============================================================
                $stmtDev = $this->pdo->prepare("
                    SELECT
                        COUNT(*) AS total,
                        COUNT(CASE WHEN pt.status = 'aguardando_fat'       THEN 1 END) AS aguardando_fat,
                        COUNT(CASE WHEN pt.status = 'comprovante_emitido'  THEN 1 END) AS comprovantes_emitidos
                    FROM frota_problema_tratamento pt
                    WHERE pt.tipo_tratamento = 'devolucao_comprovante'
                ");
                $stmtDev->execute();
                $devolucoes = $stmtDev->fetch(PDO::FETCH_ASSOC) ?: [];

                $data['devolucoes']['total']                 = (int)($devolucoes['total'] ?? 0);
                $data['devolucoes']['aguardando_fat']        = (int)($devolucoes['aguardando_fat'] ?? 0);
                $data['devolucoes']['comprovantes_emitidos'] = (int)($devolucoes['comprovantes_emitidos'] ?? 0);

                // ============================================================
                // 3. TEMPO MÉDIO ATÉ EMISSÃO DO COMPROVANTE (horas)
                //    Usa COALESCE(decidido_em, created_at) como base
                // ============================================================
                $stmtTempo = $this->pdo->prepare("
                    SELECT
                        AVG(EXTRACT(EPOCH FROM (
                            pt.comprovante_emitido_em - COALESCE(pt.decidido_em, pt.created_at)
                        )) / 3600) AS media_horas,
                        COUNT(*) AS total
                    FROM frota_problema_tratamento pt
                    WHERE pt.tipo_tratamento = 'devolucao_comprovante'
                      AND pt.comprovante_emitido_em IS NOT NULL
                ");
                $stmtTempo->execute();
                $tempo = $stmtTempo->fetch(PDO::FETCH_ASSOC) ?: [];
                $data['tempo_medio_comprovante_horas'] = round((float)($tempo['media_horas'] ?? 0), 2);

                // ============================================================
                // 4. TOP 5 CLIENTES COM MAIS FALTANTES
                // ============================================================
                $sqlTop = "
                    SELECT
                        cliente_nome,
                        COUNT(*) AS total_pedidos,
                        COALESCE(SUM(valor_total), 0) AS valor_total
                    FROM frota_acerto_pedido ap
                    WHERE ap.tipo_problema = 'faltante'
                    {$filtroData}
                    GROUP BY cliente_nome
                    ORDER BY total_pedidos DESC, valor_total DESC
                    LIMIT 5
                ";
                $stmtTop = $this->pdo->prepare($sqlTop);
                $stmtTop->execute($bindParams);
                $top = $stmtTop->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($top as $row) {
                    $data['top_clientes'][] = [
                        'cliente_nome'  => $row['cliente_nome'],
                        'total_pedidos' => (int)$row['total_pedidos'],
                        'valor_total'   => (float)$row['valor_total'],
                    ];
                }
            }

            return $this->json($response, [
                'success'   => true,
                'data'      => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro no acertoKpis: ' . $e->getMessage());
            return $this->json($response, [
                'success'   => false,
                'error'     => 'Erro ao carregar KPIs do acerto',
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    }

    /**
     * GET /v1/frota/dashboard/acerto-timeline
     *
     * 🔥 NOVO 2026-09-21 (Bloco 6):
     *   Série temporal de faltantes criados e comprovantes emitidos.
     *
     * Query params:
     *   - dias        (int, default 30, max 365)
     *   - agrupamento (dia|semana|mes, default 'dia')
     */
    public function acertoTimeline(Request $request, Response $response): Response
    {
        try {
            $params      = $request->getQueryParams();
            $dias        = max(1, min((int)($params['dias'] ?? 30), 365));
            $agrupamento = strtolower($params['agrupamento'] ?? 'dia');

            $granularidade = match ($agrupamento) {
                'semana' => 'week',
                'mes'    => 'month',
                default  => 'day',
            };

            $data = [
                'periodo' => [
                    'inicio' => date('Y-m-d', strtotime("-{$dias} days")),
                    'fim'    => date('Y-m-d'),
                ],
                'agrupamento' => $agrupamento,
                'serie'       => [],
                'totais' => [
                    'faltantes'    => 0,
                    'comprovantes' => 0,
                    'valor'        => 0.0,
                ],
            ];

            if ($this->pdo) {
                // ============================================================
                // 1. Faltantes criados por período
                // ============================================================
                $sqlFaltantes = "
                    SELECT
                        TO_CHAR(DATE_TRUNC('{$granularidade}', ap.created_at), 'YYYY-MM-DD') AS periodo,
                        COUNT(*) AS total,
                        COALESCE(SUM(ap.valor_total), 0) AS valor
                    FROM frota_acerto_pedido ap
                    WHERE ap.tipo_problema = 'faltante'
                      AND ap.created_at >= NOW() - INTERVAL '{$dias} days'
                    GROUP BY DATE_TRUNC('{$granularidade}', ap.created_at)
                    ORDER BY periodo ASC
                ";
                $stmtFaltantes = $this->pdo->query($sqlFaltantes);
                $faltantesPorPeriodo = [];
                foreach ($stmtFaltantes->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $faltantesPorPeriodo[$row['periodo']] = [
                        'total' => (int)$row['total'],
                        'valor' => (float)$row['valor'],
                    ];
                }

                // ============================================================
                // 2. Comprovantes emitidos por período
                // ============================================================
                $sqlComprovantes = "
                    SELECT
                        TO_CHAR(DATE_TRUNC('{$granularidade}', pt.comprovante_emitido_em), 'YYYY-MM-DD') AS periodo,
                        COUNT(*) AS total
                    FROM frota_problema_tratamento pt
                    WHERE pt.tipo_tratamento = 'devolucao_comprovante'
                      AND pt.comprovante_emitido_em IS NOT NULL
                      AND pt.comprovante_emitido_em >= NOW() - INTERVAL '{$dias} days'
                    GROUP BY DATE_TRUNC('{$granularidade}', pt.comprovante_emitido_em)
                    ORDER BY periodo ASC
                ";
                $stmtComprovantes = $this->pdo->query($sqlComprovantes);
                $comprovantesPorPeriodo = [];
                foreach ($stmtComprovantes->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $comprovantesPorPeriodo[$row['periodo']] = (int)$row['total'];
                }

                // ============================================================
                // 3. Unifica períodos (union)
                // ============================================================
                $periodos = array_unique(array_merge(
                    array_keys($faltantesPorPeriodo),
                    array_keys($comprovantesPorPeriodo)
                ));
                sort($periodos);

                foreach ($periodos as $periodo) {
                    $faltantesInfo    = $faltantesPorPeriodo[$periodo] ?? ['total' => 0, 'valor' => 0];
                    $comprovantesInfo = $comprovantesPorPeriodo[$periodo] ?? 0;

                    $data['serie'][] = [
                        'data'                   => $periodo,
                        'faltantes_criados'      => $faltantesInfo['total'],
                        'comprovantes_emitidos'  => $comprovantesInfo,
                        'valor_total'            => $faltantesInfo['valor'],
                    ];

                    $data['totais']['faltantes']    += $faltantesInfo['total'];
                    $data['totais']['comprovantes'] += $comprovantesInfo;
                    $data['totais']['valor']        += $faltantesInfo['valor'];
                }

                $data['totais']['valor'] = round($data['totais']['valor'], 2);
            }

            return $this->json($response, [
                'success'   => true,
                'data'      => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro no acertoTimeline: ' . $e->getMessage());
            return $this->json($response, [
                'success'   => false,
                'error'     => 'Erro ao carregar timeline do acerto',
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    }

    /**
     * GET /v1/frota/dashboard/acerto-detalhado
     *
     * 🔥 NOVO 2026-09-21 (Bloco 6):
     *   Drill-down dos cards. Lista paginada de pedidos/tratamentos.
     *
     * Query params:
     *   - tipo (faltante|devolucao, default 'faltante')
     *   - status
     *   - data_inicio
     *   - data_fim
     *   - pagina (default 1)
     *   - limite (default 20, max 100)
     */
    public function acertoDetalhado(Request $request, Response $response): Response
    {
        try {
            $params     = $request->getQueryParams();
            $tipo       = strtolower($params['tipo'] ?? 'faltante');
            $status     = $params['status'] ?? null;
            $dataInicio = $params['data_inicio'] ?? null;
            $dataFim    = $params['data_fim'] ?? null;
            $pagina     = max(1, (int)($params['pagina'] ?? 1));
            $limite     = min(100, max(1, (int)($params['limite'] ?? 20)));
            $offset     = ($pagina - 1) * $limite;

            if (!in_array($tipo, ['faltante', 'devolucao'], true)) {
                return $this->json($response, [
                    'success' => false,
                    'error'   => 'Tipo inválido. Use "faltante" ou "devolucao".'
                ], 400);
            }

            $where = [];
            $bind  = [];

            if ($tipo === 'faltante') {
                $where[] = "ap.tipo_problema = 'faltante'";
                if ($status) {
                    $where[] = "ap.status = :status";
                    $bind['status'] = $status;
                }
                if ($dataInicio) {
                    $where[] = "ap.created_at >= :data_inicio";
                    $bind['data_inicio'] = $dataInicio . ' 00:00:00';
                }
                if ($dataFim) {
                    $where[] = "ap.created_at <= :data_fim";
                    $bind['data_fim'] = $dataFim . ' 23:59:59';
                }

                $whereSql = implode(' AND ', $where);

                $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) FROM frota_acerto_pedido ap WHERE {$whereSql}");
                $stmtTotal->execute($bind);
                $total = (int)$stmtTotal->fetchColumn();

                $sql = "
                    SELECT
                        ap.id,
                        ap.entrega_id,
                        ap.cliente_nome,
                        ap.tipo_problema,
                        ap.tipo_tratamento,
                        ap.id_transacao_erp,
                        ap.valor_total,
                        ap.status,
                        ap.pedido_erp_criado_id,
                        ap.numero_pedido_criado,
                        ap.created_at,
                        ap.data_criacao_erp
                    FROM frota_acerto_pedido ap
                    WHERE {$whereSql}
                    ORDER BY ap.created_at DESC
                    LIMIT :limite OFFSET :offset
                ";
                $stmt = $this->pdo->prepare($sql);
                foreach ($bind as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($itens as &$item) {
                    $item['id']              = (int)$item['id'];
                    $item['entrega_id']      = (int)$item['entrega_id'];
                    $item['id_transacao_erp'] = $item['id_transacao_erp'] !== null ? (int)$item['id_transacao_erp'] : null;
                    $item['valor_total']     = (float)$item['valor_total'];
                }
                unset($item);
            } else {
                $where[] = "pt.tipo_tratamento = 'devolucao_comprovante'";
                if ($status) {
                    $where[] = "pt.status = :status";
                    $bind['status'] = $status;
                }
                if ($dataInicio) {
                    $where[] = "pt.created_at >= :data_inicio";
                    $bind['data_inicio'] = $dataInicio . ' 00:00:00';
                }
                if ($dataFim) {
                    $where[] = "pt.created_at <= :data_fim";
                    $bind['data_fim'] = $dataFim . ' 23:59:59';
                }

                $whereSql = implode(' AND ', $where);

                $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) FROM frota_problema_tratamento pt WHERE {$whereSql}");
                $stmtTotal->execute($bind);
                $total = (int)$stmtTotal->fetchColumn();

                $sql = "
                    SELECT
                        pt.id,
                        pt.problema_id,
                        pt.tipo_tratamento,
                        pt.status,
                        pt.numero_comprovante,
                        pt.valor_afetado,
                        pt.decidido_em,
                        pt.comprovante_emitido_em,
                        pt.created_at,
                        ep.entrega_id,
                        ep.referencia,
                        ep.descricao_problema
                    FROM frota_problema_tratamento pt
                    LEFT JOIN frota_entrega_problema ep ON ep.id = pt.problema_id
                    WHERE {$whereSql}
                    ORDER BY pt.created_at DESC
                    LIMIT :limite OFFSET :offset
                ";
                $stmt = $this->pdo->prepare($sql);
                foreach ($bind as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($itens as &$item) {
                    $item['id']           = (int)$item['id'];
                    $item['problema_id']  = (int)$item['problema_id'];
                    $item['entrega_id']   = (int)($item['entrega_id'] ?? 0);
                    $item['valor_afetado'] = (float)($item['valor_afetado'] ?? 0);
                }
                unset($item);
            }

            return $this->json($response, [
                'success' => true,
                'data'    => [
                    'tipo'  => $tipo,
                    'itens' => $itens,
                    'paginacao' => [
                        'pagina'        => $pagina,
                        'limite'        => $limite,
                        'total'         => $total,
                        'total_paginas' => (int)ceil($total / $limite),
                    ],
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Erro no acertoDetalhado: ' . $e->getMessage());
            return $this->json($response, [
                'success'   => false,
                'error'     => 'Erro ao carregar detalhamento do acerto',
                'timestamp' => date('Y-m-d H:i:s')
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
