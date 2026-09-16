<?php

namespace Nutricional\Controllers\Frota;

use Nutricional\Controllers\BaseController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VeiculoController extends BaseController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/frota/veiculos/disponiveis
     * Listar veículos disponíveis para embarque
     */
    public function disponiveis(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    v.id,
                    v.placa,
                    v.modelo,
                    v.marca,
                    v.ano,
                    v.cor,
                    v.tipo,
                    v.status,
                    v.capacidade_peso,
                    v.capacidade_volume,
                    v.odometro_atual
                FROM frota_veiculo v
                WHERE v.status = 'disponivel'
                ORDER BY v.placa ASC
            ");
            $stmt->execute();
            $veiculos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'data' => $veiculos
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro em disponiveis: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /v1/frota/veiculos
     * Listar todos os veículos com filtros
     */
    public function listar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $busca = $params['busca'] ?? null;
        $limite = (int)($params['limite'] ?? 20);
        $pagina = (int)($params['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        try {
            $where = [];
            $bindParams = [];
            
            if ($status) {
                $where[] = "v.status = :status";
                $bindParams['status'] = $status;
            }
            
            if ($busca) {
                $where[] = "(v.placa ILIKE :busca OR v.modelo ILIKE :busca2 OR v.marca ILIKE :busca3)";
                $bindParams['busca'] = "%{$busca}%";
                $bindParams['busca2'] = "%{$busca}%";
                $bindParams['busca3'] = "%{$busca}%";
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "
                SELECT 
                    v.id,
                    v.placa,
                    v.modelo,
                    v.marca,
                    v.ano,
                    v.cor,
                    v.tipo,
                    v.status,
                    v.capacidade_peso,
                    v.capacidade_volume,
                    v.consumo_medio_km_l,
                    v.odometro_atual,
                    v.ultima_manutencao_km,
                    v.proxima_manutencao_km,
                    v.latitude,
                    v.longitude,
                    v.velocidade_atual,
                    v.ultima_posicao,
                    v.created_at,
                    v.updated_at
                FROM frota_veiculo v
                {$whereClause}
                ORDER BY v.status ASC, v.placa ASC
                LIMIT :limite OFFSET :offset
            ";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($bindParams as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            
            $veiculos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Total
            $sqlCount = "SELECT COUNT(*) FROM frota_veiculo v {$whereClause}";
            $stmtCount = $this->pdo->prepare($sqlCount);
            foreach ($bindParams as $key => $val) {
                $stmtCount->bindValue($key, $val);
            }
            $stmtCount->execute();
            $total = (int)$stmtCount->fetchColumn();
            
            return $this->json($response, [
                'success' => true,
                'data' => $veiculos,
                'pagination' => [
                    'total' => $total,
                    'pagina' => $pagina,
                    'limite' => $limite,
                    'total_paginas' => ceil($total / $limite)
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro em listar veiculos: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /v1/frota/veiculos/{id}
     * Buscar veículo específico
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    v.id,
                    v.placa,
                    v.modelo,
                    v.marca,
                    v.ano,
                    v.cor,
                    v.tipo,
                    v.status,
                    v.capacidade_peso,
                    v.capacidade_volume,
                    v.consumo_medio_km_l,
                    v.odometro_atual,
                    v.ultima_manutencao_km,
                    v.proxima_manutencao_km,
                    v.latitude,
                    v.longitude,
                    v.velocidade_atual,
                    v.ultima_posicao,
                    v.created_at,
                    v.updated_at
                FROM frota_veiculo v
                WHERE v.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $veiculo = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$veiculo) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Veículo não encontrado'
                ], 404);
            }
            
            return $this->json($response, [
                'success' => true,
                'data' => $veiculo
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro em buscar veiculo: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
   public function criar(Request $request, Response $response): Response
{
    $data = json_decode($request->getBody()->getContents(), true) ?? [];
    
    // Verificar se já existe pela placa
    if (!empty($data['placa'])) {
        $stmt = $this->pdo->prepare("SELECT id FROM frota_veiculo WHERE placa = :placa");
        $stmt->execute(['placa' => $data['placa']]);
        if ($stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Veículo já cadastrado'
            ], 400);
        }
    }
    
    // 🔥 VALORES CORRETOS PARA O VEÍCULO
    $status = $data['status'] ?? 'disponivel';
    $tipo = $data['tipo'] ?? 'bau';  // carreta, bau
    $marca = $data['marca'] ?? 'Não Informada';
    $modelo = $data['modelo'] ?? 'Veículo ERP';
    $odometroAtual = $data['odometro_atual'] ?? null;
    if ($odometroAtual !== null && $odometroAtual !== '' && (!is_numeric($odometroAtual) || (float)$odometroAtual < 0)) {
        return $this->json($response, [
            'success' => false,
            'error' => 'Odômetro atual inválido'
        ], 400);
    }
    $odometroAtual = $odometroAtual === null || $odometroAtual === '' ? null : (int)floor((float)$odometroAtual);
    
    $stmt = $this->pdo->prepare("
        INSERT INTO frota_veiculo (
            placa, modelo, marca, tipo, ano, cor, capacidade_peso, odometro_atual, status, created_at, updated_at
        ) VALUES (
            :placa, :modelo, :marca, :tipo, :ano, :cor, :capacidade_peso, :odometro_atual, :status, NOW(), NOW()
        ) RETURNING id
    ");
    
    $stmt->execute([
        'placa' => $data['placa'],
        'modelo' => $modelo,
        'marca' => $marca,
        'tipo' => $tipo,
        'ano' => $data['ano'] ?? null,
        'cor' => $data['cor'] ?? null,
        'capacidade_peso' => $data['capacidade_peso'] ?? null,
        'odometro_atual' => $odometroAtual,
        'status' => $status
    ]);
    
    $id = $stmt->fetchColumn();
    
    return $this->json($response, [
        'success' => true,
        'message' => 'Veículo cadastrado com sucesso',
        'data' => ['id' => $id]
    ]);
}
    
    /**
     * PUT /v1/frota/veiculos/{id}
     * Atualizar veículo
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        if (array_key_exists('odometro_atual', $data) && $data['odometro_atual'] !== null &&
            (!is_numeric($data['odometro_atual']) || (float)$data['odometro_atual'] < 0)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Odômetro atual inválido'
            ], 400);
        }
        if (isset($data['odometro_atual'])) {
            $data['odometro_atual'] = (int)floor((float)$data['odometro_atual']);
        }
        
        try {
            $campos = [];
            $bindParams = ['id' => $id];
            
            $camposPermitidos = [
                'placa', 'modelo', 'marca', 'ano', 'cor', 'tipo',
                'capacidade_peso', 'capacidade_volume', 'consumo_medio_km_l',
                'odometro_atual', 'ultima_manutencao_km', 'proxima_manutencao_km',
                'status', 'latitude', 'longitude', 'velocidade_atual'
            ];
            
            foreach ($camposPermitidos as $campo) {
                if (array_key_exists($campo, $data)) {
                    $campos[] = "{$campo} = :{$campo}";
                    $bindParams[$campo] = $data[$campo];
                }
            }
            
            if (empty($campos)) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Nenhum campo para atualizar'
                ], 400);
            }
            
            $campos[] = "updated_at = NOW()";
            $sql = "UPDATE frota_veiculo SET " . implode(', ', $campos) . " WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindParams);
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Veículo atualizado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro em atualizar veiculo: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
       /**
     * DELETE /v1/frota/veiculos/{id}
     * Remover veículo — usa soft delete se houver histórico.
     *
     * Regra:
     *  - Se houver embarques ativos (planejado / em_andamento): BLOQUEIA com 400.
     *  - Se houver qualquer histórico (embarques, entregas, acertos): SOFT DELETE (status = 'inativo').
     *  - Se não houver nada: DELETE físico.
     */
    public function deletar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if ($id <= 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'ID inválido'
            ], 400);
        }

        try {
            // Confirma que o veículo existe
            $stmt = $this->pdo->prepare("SELECT id, placa FROM frota_veiculo WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $veiculo = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$veiculo) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Veículo não encontrado'
                ], 404);
            }

            // Conta dependências
            $stmt = $this->pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM frota_embarque
                        WHERE veiculo_id = :id
                          AND status IN ('planejado', 'em_andamento'))         AS embarques_ativos,
                    (SELECT COUNT(*) FROM frota_embarque
                        WHERE veiculo_id = :id2)                                AS embarques_total,
                    (SELECT COUNT(*) FROM frota_entrega e
                        INNER JOIN frota_embarque em ON em.id = e.embarque_id
                        WHERE em.veiculo_id = :id3)                             AS entregas_total,
                    (SELECT COUNT(*) FROM frota_acerto_embarque ae
                        INNER JOIN frota_embarque em ON em.id = ae.embarque_id
                        WHERE em.veiculo_id = :id4)                             AS acertos_total
            ");
            $stmt->execute(['id' => $id, 'id2' => $id, 'id3' => $id, 'id4' => $id]);
            $deps = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ((int)$deps['embarques_ativos'] > 0) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Veículo possui embarques ativos (planejado ou em andamento). Finalize ou cancele antes de remover.'
                ], 400);
            }

            $temHistorico = ((int)$deps['embarques_total'] > 0)
                         || ((int)$deps['entregas_total'] > 0)
                         || ((int)$deps['acertos_total'] > 0);

            if ($temHistorico) {
                // SOFT DELETE — preserva histórico
                $stmt = $this->pdo->prepare("
                    UPDATE frota_veiculo
                    SET status = 'inativo',
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $id]);

                return $this->json($response, [
                    'success' => true,
                    'message' => 'Veículo inativado (possui histórico — não foi deletado fisicamente)',
                    'data' => [
                        'id' => $id,
                        'placa' => $veiculo['placa'],
                        'acao' => 'soft_delete',
                        'historico' => [
                            'embarques' => (int)$deps['embarques_total'],
                            'entregas' => (int)$deps['entregas_total'],
                            'acertos' => (int)$deps['acertos_total']
                        ]
                    ]
                ]);
            }

            // Sem histórico: pode apagar fisicamente
            $stmt = $this->pdo->prepare("DELETE FROM frota_veiculo WHERE id = :id");
            $stmt->execute(['id' => $id]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Veículo removido',
                'data' => [
                    'id' => $id,
                    'placa' => $veiculo['placa'],
                    'acao' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            error_log('[VeiculoController] Erro ao remover veiculo #' . $id . ': ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao remover veículo'
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
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}