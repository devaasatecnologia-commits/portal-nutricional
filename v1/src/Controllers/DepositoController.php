<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class DepositoController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/deposito/secoes
     */
    public function getSecoes(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->query("
                SELECT s.*, 
                    (SELECT COUNT(*) FROM secao_enderecos se WHERE se.idsecao = s.idsecao) as total_enderecos,
                    (SELECT COUNT(*) FROM secao_enderecos se WHERE se.idsecao = s.idsecao AND se.ocupado > 0) as enderecos_ocupados,
                    (SELECT COUNT(*) FROM lote_endereco le WHERE le.idsecao = s.idsecao) as total_lotes
                FROM secao s
                WHERE s.inativo = 'N' AND s.idsecao > 0
                ORDER BY s.descricao
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/deposito/enderecos/{idsecao}
     */
    public function getEnderecos(Request $request, Response $response, array $args): Response
    {
        $idsecao = (int)($args['idsecao'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT se.*,
                    (SELECT COUNT(*) FROM lote_endereco le WHERE le.idendereco = se.idendereco) as lotes_armazenados,
                    (SELECT STRING_AGG(DISTINCT le.lote, ', ') FROM lote_endereco le WHERE le.idendereco = se.idendereco) as lotes_lista
                FROM secao_enderecos se
                WHERE se.idsecao = :idsecao
                ORDER BY se.linhas, se.colunas
            ");
            $stmt->execute(['idsecao' => $idsecao]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/deposito/lotes-endereco/{idendereco}
     */
    public function getLotesPorEndereco(Request $request, Response $response, array $args): Response
    {
        $idendereco = (int)($args['idendereco'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT le.*, i.descricao as nome_item, i.referencia
                FROM lote_endereco le
                JOIN item i ON i.iditem = le.iditem
                WHERE le.idendereco = :idendereco
                ORDER BY le.data_entrada DESC
            ");
            $stmt->execute(['idendereco' => $idendereco]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data ?: []);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * POST /v1/deposito/endereco
     */
    public function salvarEndereco(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $idsecao = (int)($input['idsecao'] ?? 0);
        $linha = strtoupper($input['linha'] ?? '');
        $coluna = strtoupper($input['coluna'] ?? '');
        $numLinha = (int)($input['num_linha'] ?? 1);
        $numColuna = (int)($input['num_coluna'] ?? 1);
        $capacidade = (int)($input['capacidade'] ?? 100);
        $sigla = $linha . $coluna;
        
        if ($idsecao <= 0 || empty($linha) || empty($coluna)) {
            $response->getBody()->write(json_encode(['error' => 'Dados inválidos']));
            return $response->withStatus(400);
        }
        
        try {
            // Verificar se já existe
            $stmtCheck = $this->pdo->prepare("
                SELECT idendereco FROM secao_enderecos 
                WHERE idsecao = :idsecao AND linhasigla = :linha AND colunasigla = :coluna
            ");
            $stmtCheck->execute(['idsecao' => $idsecao, 'linha' => $linha, 'coluna' => $coluna]);
            
            if ($stmtCheck->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Este endereço já existe nesta seção']));
                return $response->withStatus(400);
            }
            
            // Buscar próximo idendereco
            $stmtMax = $this->pdo->query("SELECT COALESCE(MAX(idendereco), 0) + 1 FROM secao_enderecos");
            $nextId = (int)$stmtMax->fetchColumn();
            
            // Inserir
            $this->pdo->prepare("
                INSERT INTO secao_enderecos (idsecao, idendereco, linhas, colunas, linhasigla, colunasigla, capacidade, ocupado, saldo)
                VALUES (:idsecao, :idendereco, :linhas, :colunas, :linhasigla, :colunasigla, :capacidade, 0, :capacidade)
            ")->execute([
                'idsecao' => $idsecao,
                'idendereco' => $nextId,
                'linhas' => $numLinha,
                'colunas' => $numColuna,
                'linhasigla' => $linha,
                'colunasigla' => $coluna,
                'capacidade' => $capacidade
            ]);
            
            $payload = json_encode(['success' => true, 'idendereco' => $nextId, 'sigla' => $sigla]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
 * POST /v1/deposito/secao
 * Criar ou editar uma seção
 */
public function salvarSecao(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    $idsecao = (int)($input['idsecao'] ?? 0);
    $descricao = $input['descricao'] ?? '';
    $sigla = strtoupper($input['sigla'] ?? '');
    
    if (empty($descricao)) {
        $response->getBody()->write(json_encode(['error' => 'Descrição é obrigatória']));
        return $response->withStatus(400);
    }
    
    try {
        if ($idsecao > 0) {
            // Editar seção existente
            $this->pdo->prepare("
                UPDATE secao SET descricao = :desc, sigla = :sigla, datahoraultimaatualizacao = NOW()
                WHERE idsecao = :id
            ")->execute(['desc' => $descricao, 'sigla' => $sigla, 'id' => $idsecao]);
            
            $payload = json_encode(['success' => true, 'idsecao' => $idsecao, 'acao' => 'editado']);
        } else {
            // Criar nova seção
            $stmtMax = $this->pdo->query("SELECT COALESCE(MAX(idsecao), 0) + 1 FROM secao");
            $nextId = (int)$stmtMax->fetchColumn();
            
            $this->pdo->prepare("
                INSERT INTO secao (idsecao, descricao, sigla, inativo, datahora, usuario, linhas, colunas, ocupaenderecolotevencido)
                VALUES (:id, :desc, :sigla, 'N', NOW(), :usuario, 0, 0, 'N')
            ")->execute([
                'id' => $nextId,
                'desc' => $descricao,
                'sigla' => $sigla,
                'usuario' => $input['usuario'] ?? 'SISTEMA'
            ]);
            
            $payload = json_encode(['success' => true, 'idsecao' => $nextId, 'acao' => 'criado']);
        }
        
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}

/**
 * GET /v1/deposito/secao/{idsecao}
 * Buscar dados de uma seção
 */
public function getSecao(Request $request, Response $response, array $args): Response
{
    $idsecao = (int)($args['idsecao'] ?? 0);
    
    try {
        $stmt = $this->pdo->prepare("SELECT * FROM secao WHERE idsecao = :id");
        $stmt->execute(['id' => $idsecao]);
        $secao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$secao) {
            $response->getBody()->write(json_encode(['error' => 'Seção não encontrada']));
            return $response->withStatus(404);
        }
        
        $payload = json_encode($secao);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500);
    }
}
    /**
     * DELETE /v1/deposito/endereco/{idsecao}/{idendereco}
     */
    public function deletarEndereco(Request $request, Response $response, array $args): Response
    {
        $idsecao = (int)($args['idsecao'] ?? 0);
        $idendereco = (int)($args['idendereco'] ?? 0);
        
        try {
            // Verificar se tem lotes
            $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM lote_endereco WHERE idendereco = :id");
            $stmtCheck->execute(['id' => $idendereco]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $response->getBody()->write(json_encode(['error' => 'Este endereço possui lotes armazenados. Remova-os primeiro.']));
                return $response->withStatus(400);
            }
            
            $this->pdo->prepare("DELETE FROM secao_enderecos WHERE idsecao = :idsecao AND idendereco = :idendereco")
                ->execute(['idsecao' => $idsecao, 'idendereco' => $idendereco]);
            
            $payload = json_encode(['success' => true]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
    
    /**
     * GET /v1/deposito/resumo
     */
    public function getResumo(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT 
                    (SELECT COUNT(*) FROM secao WHERE inativo = 'N' AND idsecao > 0) as total_secoes,
                    (SELECT COUNT(*) FROM secao_enderecos) as total_enderecos,
                    (SELECT COUNT(*) FROM secao_enderecos WHERE ocupado > 0) as ocupados,
                    (SELECT COUNT(*) FROM lote_endereco) as total_lotes
            ";
            $data = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
}