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
     * GET /v1/auditoria/carregamentos
     * Lista embarques com atividade de carga no período, inclusive finalizados.
     */
    public function getCarregamentos($request, $response) {
        $params = $request->getQueryParams();
        $inicio = $params['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $fim = $params['fim'] ?? date('Y-m-d');

        foreach ([$inicio, $fim] as $data) {
            if (!is_string($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data)) {
                $response->getBody()->write(json_encode(['error' => 'Período inválido']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $partes = explode('-', (string)$data);
            if (count($partes) !== 3
                || !checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0])
                || sprintf('%04d-%02d-%02d', (int)$partes[0], (int)$partes[1], (int)$partes[2]) !== $data
            ) {
                $response->getBody()->write(json_encode(['error' => 'Período inválido']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        return $this->getCarregamentosValidated($inicio, $fim, $response);
    }

        /**
         * GET /v1/auditoria/dossie/{idembarque}
         * Retorna previsao, separacao, saldo atual e evidencias por subida.
         */
        public function getDossieEmbarque($request, $response, $args) {
            $idembarque = (int)($args['idembarque'] ?? 0);
            if ($idembarque <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Embarque inválido']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            try {
                $schemaAtual = $this->pdo->query('SELECT current_schema()')->fetchColumn();
                $tabelasObrigatorias = [
                    'carregamento_estornos',
                    'carregamento_subida_alocacoes'
                ];
                $stmtSchema = $this->pdo->prepare("
                    SELECT table_name
                    FROM information_schema.tables
                    WHERE table_schema = ?
                      AND table_name = ANY(CAST(? AS text[]))
                ");
                $stmtSchema->execute([
                    $schemaAtual,
                    '{' . implode(',', $tabelasObrigatorias) . '}'
                ]);
                $tabelasExistentes = $stmtSchema->fetchAll(PDO::FETCH_COLUMN);
                $tabelasAusentes = array_values(array_diff($tabelasObrigatorias, $tabelasExistentes));

                $stmtFotoSchema = $this->pdo->prepare("
                    SELECT EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_schema = ?
                          AND table_name = 'carregamento_fotos'
                          AND column_name = 'id_subida'
                    )
                ");
                $stmtFotoSchema->execute([$schemaAtual]);
                if (!$stmtFotoSchema->fetchColumn()) {
                    $tabelasAusentes[] = 'carregamento_fotos.id_subida';
                }

                if ($tabelasAusentes) {
                    $response->getBody()->write(json_encode([
                        'error' => 'A migração da Auditoria de Carregamento está incompleta.',
                        'codigo' => 'SCHEMA_AUDITORIA_INCOMPLETO',
                        'faltando' => $tabelasAusentes
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withStatus(503)
                        ->withHeader('Content-Type', 'application/json; charset=utf-8')
                        ->withHeader('Cache-Control', 'no-store');
                }

                $stmt = $this->pdo->prepare("
                    WITH previsto AS (
                        SELECT pi.iditem, SUM(pi.qt) AS quantidade
                        FROM pedido_item pi
                        JOIN pedido p ON p.idpedido = pi.idpedido
                        WHERE p.idembarque = ? AND pi.ativo = 'S'
                        GROUP BY pi.iditem
                    ),
                    separado AS (
                        SELECT l.iditem, SUM(l.qt_separada) AS quantidade
                        FROM pedido_item_logistica l
                        WHERE l.idembarque = ?
                        GROUP BY l.iditem
                    ),
                    carregado AS (
                        SELECT c.iditem, SUM(c.qt_carregada) AS quantidade
                        FROM pedido_item_carregamento c
                        WHERE c.idembarque = ?
                        GROUP BY c.iditem
                    ),
                    ids_item AS (
                        SELECT iditem FROM previsto
                        UNION SELECT iditem FROM separado
                        UNION SELECT iditem FROM carregado
                    )
                    SELECT
                        i.iditem,
                        i.referencia,
                        i.descricao AS produto,
                        COALESCE(p.quantidade, 0)::float AS quantidade_prevista,
                        COALESCE(s.quantidade, 0)::float AS quantidade_separada,
                        COALESCE(c.quantidade, 0)::float AS quantidade_carregada,
                        (COALESCE(s.quantidade, 0) - COALESCE(c.quantidade, 0))::float AS saldo_pendente,
                        (
                            COALESCE(c.quantidade, 0) > 0
                            AND (
                                EXISTS (
                                    SELECT 1
                                    FROM carregamento_subidas cs
                                    WHERE cs.idembarque = ?
                                      AND cs.iditem = i.iditem
                                      AND cs.qt_subida > COALESCE((
                                          SELECT SUM(e.quantidade_estornada)
                                          FROM carregamento_estornos e
                                          WHERE e.id_subida = cs.id
                                      ), 0) + 0.0001
                                      AND NULLIF(COALESCE(
                                          NULLIF(cs.path_foto, ''),
                                          (
                                              SELECT cf.caminho_foto
                                              FROM carregamento_fotos cf
                                              WHERE cf.id_subida = cs.id
                                              ORDER BY cf.data_hora DESC
                                              LIMIT 1
                                          )
                                      ), '') IS NULL
                                )
                                OR (
                                    NOT EXISTS (
                                        SELECT 1
                                        FROM carregamento_subidas cs
                                        WHERE cs.idembarque = ?
                                          AND cs.iditem = i.iditem
                                          AND cs.qt_subida > COALESCE((
                                              SELECT SUM(e.quantidade_estornada)
                                              FROM carregamento_estornos e
                                              WHERE e.id_subida = cs.id
                                          ), 0) + 0.0001
                                    )
                                    AND NOT EXISTS (
                                        SELECT 1
                                        FROM pedido_item_carregamento c_foto
                                        WHERE c_foto.idembarque = ?
                                          AND c_foto.iditem = i.iditem
                                          AND NULLIF(c_foto.path_foto_conferencia, '') IS NOT NULL
                                    )
                                )
                            )
                        ) AS sem_foto,
                        CASE
                            WHEN COALESCE(c.quantidade, 0) > COALESCE(s.quantidade, 0) + 0.0001 THEN 'ACIMA_DO_SEPARADO'
                            WHEN COALESCE(c.quantidade, 0) < COALESCE(s.quantidade, 0) - 0.0001 THEN 'PARCIAL_OU_PENDENTE'
                            ELSE 'CONFERIDO'
                        END AS status_conferencia
                    FROM ids_item ii
                    JOIN item i ON i.iditem = ii.iditem
                    LEFT JOIN previsto p ON p.iditem = i.iditem
                    LEFT JOIN separado s ON s.iditem = i.iditem
                    LEFT JOIN carregado c ON c.iditem = i.iditem
                    ORDER BY i.descricao, i.iditem
                ");
                $stmt->execute([
                    $idembarque,
                    $idembarque,
                    $idembarque,
                    $idembarque,
                    $idembarque,
                    $idembarque
                ]);
                $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->pdo->prepare("
                    SELECT
                        cs.id AS id_subida,
                        cs.id_carregamento,
                        cs.iditem,
                        i.descricao AS produto,
                        i.referencia,
                        cs.numero_subida,
                        cs.qt_subida::float AS quantidade_original,
                        GREATEST(0, cs.qt_subida - COALESCE(estornos.quantidade, 0))::float AS quantidade_disponivel,
                        COALESCE(estornos.quantidade, 0)::float AS quantidade_estornada,
                        cs.qt_acumulada::float AS quantidade_acumulada_registrada,
                        cs.doca,
                        cs.idusuario,
                        COALESCE(u.username, 'Usuário não identificado') AS operador,
                        cs.data_hora,
                        COALESCE(NULLIF(cs.path_foto, ''), foto.caminho_foto) AS foto,
                        CASE WHEN alocacoes.dados IS NULL THEN FALSE ELSE TRUE END AS alocacao_exata,
                        COALESCE(alocacoes.dados, '[]'::jsonb) AS alocacoes,
                        COALESCE(fotos.dados, '[]'::jsonb) AS fotos
                    FROM carregamento_subidas cs
                    JOIN item i ON i.iditem = cs.iditem
                    LEFT JOIN usuario u ON u.idusuario = cs.idusuario
                    LEFT JOIN LATERAL (
                        SELECT SUM(e.quantidade_estornada) AS quantidade
                        FROM carregamento_estornos e
                        WHERE e.id_subida = cs.id
                    ) estornos ON TRUE
                    LEFT JOIN LATERAL (
                        SELECT cf.caminho_foto
                        FROM carregamento_fotos cf
                        WHERE cf.id_subida = cs.id
                        ORDER BY cf.data_hora DESC
                        LIMIT 1
                    ) foto ON TRUE
                    LEFT JOIN LATERAL (
                        SELECT jsonb_agg(jsonb_build_object(
                            'id_carregamento', sa.id_carregamento,
                            'idpedido', sa.idpedido,
                            'iditempedido', sa.iditempedido,
                            'quantidade', sa.quantidade
                        ) ORDER BY sa.idpedido, sa.iditempedido) AS dados
                        FROM carregamento_subida_alocacoes sa
                        WHERE sa.id_subida = cs.id
                    ) alocacoes ON TRUE
                    LEFT JOIN LATERAL (
                        SELECT jsonb_agg(jsonb_build_object(
                            'caminho_foto', cf.caminho_foto,
                            'data_hora', cf.data_hora,
                            'doca', cf.doca,
                            'operador', COALESCE(uf.username, 'Usuário não identificado')
                        ) ORDER BY cf.data_hora, cf.id) AS dados
                        FROM carregamento_fotos cf
                        LEFT JOIN usuario uf ON uf.idusuario = cf.idusuario
                        WHERE cf.id_subida = cs.id
                    ) fotos ON TRUE
                    WHERE cs.idembarque = ?
                    ORDER BY cs.data_hora, cs.numero_subida, cs.id
                ");
                $stmt->execute([$idembarque]);
                $subidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($subidas as &$subida) {
                    $subida['alocacoes'] = is_string($subida['alocacoes'])
                        ? json_decode($subida['alocacoes'], true, 512, JSON_THROW_ON_ERROR)
                        : $subida['alocacoes'];
                    $subida['fotos'] = is_string($subida['fotos'])
                        ? json_decode($subida['fotos'], true, 512, JSON_THROW_ON_ERROR)
                        : $subida['fotos'];
                }
                unset($subida);

                $stmt = $this->pdo->prepare("
                    SELECT
                        e.id,
                        e.iditem,
                        i.descricao AS produto,
                        e.id_subida,
                        e.tipo,
                        e.numero_subida_original,
                        e.quantidade_estornada::float AS quantidade_estornada,
                        e.caminho_foto_snapshot,
                        e.motivo,
                        e.criado_em,
                        e.alocacoes_snapshot,
                        COALESCE(u.username, 'Usuário não identificado') AS operador
                    FROM carregamento_estornos e
                    LEFT JOIN item i ON i.iditem = e.iditem
                    LEFT JOIN usuario u ON u.idusuario = e.idusuario
                    WHERE e.idembarque = ?
                    ORDER BY e.criado_em, e.id
                ");
                $stmt->execute([$idembarque]);
                $estornos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($estornos as &$estorno) {
                    $estorno['alocacoes_snapshot'] = is_string($estorno['alocacoes_snapshot'])
                        ? json_decode($estorno['alocacoes_snapshot'], true, 512, JSON_THROW_ON_ERROR)
                        : $estorno['alocacoes_snapshot'];
                }
                unset($estorno);

                $payload = json_encode([
                    'idembarque' => $idembarque,
                    'itens' => $itens,
                    'subidas' => $subidas,
                    'estornos' => $estornos
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withHeader('Cache-Control', 'no-store');
            } catch (\Throwable $e) {
                error_log('[AuditoriaCarregamento] ' . $e->getMessage());
                $response->getBody()->write(json_encode(['error' => 'Não foi possível carregar o dossiê do embarque.']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json; charset=utf-8');
            }
        }

        private function getCarregamentosValidated($inicio, $fim, $response) {
            if ($inicio > $fim) {
            $response->getBody()->write(json_encode(['error' => 'A data inicial deve ser anterior à data final']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $sql = "
                WITH cargas_periodo AS (
                    SELECT
                        c.idembarque,
                        MAX(c.data_carregamento) AS ultima_carga,
                        COUNT(DISTINCT c.iditem) AS itens_carregados,
                        COALESCE(SUM(c.qt_carregada), 0)::float AS quantidade_carregada
                    FROM pedido_item_carregamento c
                    WHERE c.data_carregamento >= CAST(:inicio_cargas AS date)
                      AND c.data_carregamento < CAST(:fim_cargas AS date) + INTERVAL '1 day'
                      AND c.qt_carregada > 0.0001
                    GROUP BY c.idembarque
                ),
                subidas_periodo AS (
                    SELECT
                        cs.idembarque,
                        COUNT(*) AS total_subidas,
                        COUNT(*) FILTER (WHERE NULLIF(cs.path_foto, '') IS NOT NULL) AS total_fotos
                    FROM carregamento_subidas cs
                    WHERE cs.data_hora >= CAST(:inicio_subidas AS date)
                      AND cs.data_hora < CAST(:fim_subidas AS date) + INTERVAL '1 day'
                    GROUP BY cs.idembarque
                )
                SELECT
                    ep.idembarque,
                    ep.observacao AS rota,
                    ep.placa,
                    motorista.fantasia AS motorista,
                    ep.totalpesobruto,
                    COALESCE(s.status_atual, CASE
                        WHEN ep.pex_conferido = 'S' THEN 'CONCLUIDO'
                        ELSE 'CARREGADO'
                    END) AS status_logistico,
                    cp.ultima_carga,
                    cp.itens_carregados,
                    cp.quantidade_carregada,
                    COALESCE(sp.total_subidas, 0) AS total_subidas,
                    COALESCE(sp.total_fotos, 0) AS total_fotos,
                    (
                        SELECT u.username
                        FROM pedido_item_carregamento c
                        LEFT JOIN usuario u ON u.idusuario = c.id_conferente
                        WHERE c.idembarque = ep.idembarque
                          AND c.data_carregamento >= CAST(:inicio_operador AS date)
                          AND c.data_carregamento < CAST(:fim_operador AS date) + INTERVAL '1 day'
                        ORDER BY c.data_carregamento DESC, c.id DESC
                        LIMIT 1
                    ) AS operador_principal
                FROM cargas_periodo cp
                JOIN embarque_pedido ep ON ep.idembarque = cp.idembarque
                LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                LEFT JOIN cliforemp motorista ON motorista.idcliforemp = ep.identregador
                LEFT JOIN subidas_periodo sp ON sp.idembarque = ep.idembarque
                WHERE ep.idfilial IN (1, 6)
                ORDER BY cp.ultima_carga DESC, ep.idembarque DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':inicio_cargas' => $inicio,
                ':fim_cargas' => $fim,
                ':inicio_subidas' => $inicio,
                ':fim_subidas' => $fim,
                ':inicio_operador' => $inicio,
                ':fim_operador' => $fim,
            ]);
            $response->getBody()->write(json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            error_log('[Auditoria] Falha ao listar carregamentos: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Não foi possível carregar os embarques carregados']));
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
                    JOIN usuario u ON u.idusuario = l.id_separador
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
                        JOIN usuario u ON u.idusuario = l.id_separador
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
                        JOIN usuario u ON u.idusuario = c.id_conferente
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
                        LEFT JOIN usuario u ON u.idusuario = s.idusuario
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
                        (SELECT COUNT(DISTINCT iditem) FROM pedido_item_carregamento
                         WHERE idembarque = ep.idembarque AND qt_carregada > 0.0001) as itens_carregados,
                        (SELECT COUNT(DISTINCT pi.iditem) FROM pedido_item pi 
                         JOIN pedido p ON p.idpedido = pi.idpedido 
                         WHERE p.idembarque = ep.idembarque AND pi.ativo = 'S') as total_itens
                    FROM embarque_pedido ep
                    LEFT JOIN embarque_status_log s ON s.idembarque = ep.idembarque
                    LEFT JOIN usuario u ON u.idusuario = s.idusuario
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
 * Retorna eventos individuais de separação e subidas de carregamento.
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
                    JOIN usuario u ON u.idusuario = l.id_separador
                    WHERE l.idembarque = ?
                    ORDER BY l.data_separacao DESC";
            
            $stmt = $this->pdo->prepare($sqlSep);
            $stmt->execute([$idembarque, $idembarque]);
            $itens = array_merge($itens, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if ($tipo == 'carregamento' || $tipo == 'todos') {
            $sqlCar = "SELECT 
                        'carregamento' as tipo,
                        i.iditem,
                        i.descricao as produto,
                        i.referencia,
                        i.path_foto_master as foto_master,
                        CASE WHEN cs.id IS NULL
                            THEN c.path_foto_conferencia
                            ELSE cs.path_foto
                        END as foto_carregamento,
                        COALESCE(cs.qt_subida, c.qt_carregada) as quantidade,
                        (SELECT SUM(qt_carregada) FROM pedido_item_carregamento 
                         WHERE iditem = i.iditem AND idembarque = ?) as quantidade_total,
                        COALESCE(u.username, 'Sistema') as operador,
                        to_char(COALESCE(cs.data_hora, c.data_carregamento), 'DD/MM/YYYY HH24:MI:SS') as data_hora,
                        2 as status_separacao,
                        'Carregado' as status_desc,
                        c.id as id_carregamento,
                        cs.id as id_subida,
                        cs.numero_subida,
                        cs.qt_acumulada as quantidade_acumulada,
                        COALESCE(cs.doca, c.doca) as doca,
                        c.idpedido,
                        c.iditempedido
                    FROM pedido_item_carregamento c
                    JOIN item i ON i.iditem = c.iditem
                    LEFT JOIN carregamento_subidas cs
                        ON cs.id_carregamento = c.id
                       AND cs.idembarque = c.idembarque
                       AND cs.iditem = c.iditem
                    LEFT JOIN usuario u
                        ON u.idusuario = COALESCE(cs.idusuario, c.id_conferente)
                    WHERE c.idembarque = ?
                    ORDER BY COALESCE(cs.data_hora, c.data_carregamento) DESC, cs.numero_subida DESC NULLS LAST, c.id DESC";
            
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
        
        $payload = json_encode($itens ?: []);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
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
                    LEFT JOIN usuario u ON u.idusuario = s.idusuario
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
                    JOIN usuario u ON u.idusuario = l.id_separador
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
                    JOIN usuario u ON u.idusuario = c.id_conferente
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
                    LEFT JOIN usuario u ON u.idusuario = s.idusuario
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