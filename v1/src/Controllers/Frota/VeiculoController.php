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
    
    $stmt = $this->pdo->prepare("
        INSERT INTO frota_veiculo (
            placa, modelo, marca, tipo, ano, cor, capacidade_peso, status, created_at, updated_at
        ) VALUES (
            :placa, :modelo, :marca, :tipo, :ano, :cor, :capacidade_peso, :status, NOW(), NOW()
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
     * Deletar veículo
     */
    public function deletar(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM frota_veiculo WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Veículo deletado com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log('Erro em deletar veiculo: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
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