<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class MetaBuilderController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/meta-builder/tipos
     * Lista todos os tipos de meta (Admin)
     */
    public function getTiposMeta(Request $request, Response $response): Response
    {
        try {
            // Verificar se a tabela existe
            $stmt = $this->pdo->query("
                SELECT id, nome, descricao, icone, cor, ativo, created_at 
                FROM mkt_tipos_meta 
                ORDER BY id
                ");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, ['success' => true, 'data' => $tipos]);
        } catch (\Exception $e) {
            // Se a tabela não existir, retorna lista vazia
            return $this->json($response, ['success' => true, 'data' => []]);
        }
    }
    
/**
 * POST /v1/meta-builder/instancias
 * Cria uma instância de meta (Admin define valores)
 */
public function criarInstanciaMeta(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    try {
        $idTipoMeta = (int)($input['id_tipo_meta'] ?? 0);
        $titulo = trim($input['titulo'] ?? '');
        $descricao = $input['descricao'] ?? '';
        $dataInicio = $input['data_inicio'] ?? date('Y-m-d');
        $dataFim = $input['data_fim'] ?? null;
        $status = $input['status'] ?? 'ativa';
        $campos = $input['campos'] ?? [];
        
        // ✅ PEGAR USUÁRIO CRIADOR
        $usuarioId = $this->getUserIdFromToken($request);
        
        if (!$idTipoMeta || !$titulo) {
            return $this->json($response, [
                'success' => false, 
                'error' => 'Tipo de meta e título são obrigatórios'
            ], 400);
        }
        
        // Montar objeto de valores
        $valoresMeta = [];
        foreach ($campos as $campo) {
            $nome = $campo['nome'] ?? '';
            $valor = $campo['valor'] ?? 0;
            if ($nome) {
                $valoresMeta[$nome] = $valor;
            }
        }
        
        // Inserir no banco com usuário criador
        $sql = "INSERT INTO mkt_metas_instancias (id_tipo_meta, titulo, descricao, data_inicio, data_fim, status, valores, created_by, created_at) 
        VALUES (:id_tipo, :titulo, :descricao, :inicio, :fim, :status, :valores::jsonb, :created_by, NOW())
        RETURNING id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_tipo' => $idTipoMeta,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'inicio' => $dataInicio,
            'fim' => $dataFim,
            'status' => $status,
            'valores' => json_encode($valoresMeta),
            'created_by' => $usuarioId
        ]);
        
        $id = $stmt->fetchColumn();
        
        return $this->json($response, [
            'success' => true,
            'id' => (int)$id,
            'message' => 'Meta criada com sucesso!'
        ]);
    } catch (\Exception $e) {
        error_log('Erro em criarInstanciaMeta: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false, 
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * DELETE /v1/meta-builder/tipos/{id}
 * Exclui um tipo de meta e seus campos
 */
public function deletarTipoMeta(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    
    try {
        // Verificar se existem instâncias usando este tipo
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM mkt_metas_instancias WHERE id_tipo_meta = :id");
        $stmtCheck->execute(['id' => $id]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            return $this->json($response, [
                'success' => false, 
                'error' => 'Existem metas vinculadas a este tipo. Remova-as primeiro.'
            ], 400);
        }
        
        // Excluir campos
        $this->pdo->prepare("DELETE FROM mkt_tipos_campos WHERE id_tipo_meta = :id")
        ->execute(['id' => $id]);
        
        // Excluir tipo
        $this->pdo->prepare("DELETE FROM mkt_tipos_meta WHERE id = :id")
        ->execute(['id' => $id]);
        
        return $this->json($response, ['success' => true, 'message' => 'Tipo excluído!']);
    } catch (\Exception $e) {
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * POST /v1/meta-builder/tipos/campos
 * Adiciona um campo ao tipo de meta
 */
public function adicionarCampo(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    try {
        $idTipoMeta = (int)($input['id_tipo_meta'] ?? 0);
        $nomeCampo = trim($input['nome_campo'] ?? '');
        $rotulo = trim($input['rotulo'] ?? '');
        $tipoCampo = $input['tipo_campo'] ?? 'number';
        $obrigatorio = (bool)($input['obrigatorio'] ?? true);
        $unidade = trim($input['unidade'] ?? '');
        $editavel = (bool)($input['editavel'] ?? true); // ✅ NOVO: se o usuário pode editar na alimentação
        $ordem = (int)($input['ordem'] ?? 0);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO mkt_tipos_campos (id_tipo_meta, nome_campo, rotulo, tipo_campo, obrigatorio, ordem, unidade, editavel) 
            VALUES (:id_tipo, :nome_campo, :rotulo, :tipo_campo, :obrigatorio, :ordem, :unidade, :editavel)
            ");
        
        $stmt->execute([
            'id_tipo' => $idTipoMeta,
            'nome_campo' => $nomeCampo,
            'rotulo' => $rotulo,
            'tipo_campo' => $tipoCampo,
            'obrigatorio' => $obrigatorio ? 'true' : 'false',
            'ordem' => $ordem,
            'unidade' => $unidade,
            'editavel' => $editavel ? 'true' : 'false'
        ]);
        
        return $this->json($response, ['success' => true, 'id' => $this->pdo->lastInsertId()]);
    } catch (\Exception $e) {
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * GET /v1/meta-builder/alimentacao/{id}
 * Busca registros de alimentação de uma meta específica
 */
public function getAlimentacaoPorMeta(Request $request, Response $response, array $args): Response
{
    $idMeta = (int)($args['id'] ?? 0);
    
    try {
        $stmt = $this->pdo->prepare("
            SELECT * FROM mkt_alimentacao_registros 
            WHERE id_meta_instancia = :id_meta
            ORDER BY data_registro DESC
            ");
        $stmt->execute(['id_meta' => $idMeta]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->json($response, ['success' => true, 'data' => $registros]);
    } catch (\Exception $e) {
        return $this->json($response, ['success' => true, 'data' => []]);
    }
}

/**
 * GET /v1/meta-builder/tipos/{id}
 * Busca um tipo de meta específico
 */
public function getTipoMeta(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    
    try {
        $stmt = $this->pdo->prepare("
            SELECT id, nome, descricao, icone, cor, ativo, created_at 
            FROM mkt_tipos_meta 
            WHERE id = :id
            ");
        $stmt->execute(['id' => $id]);
        $tipo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tipo) {
            return $this->json($response, ['success' => false, 'error' => 'Tipo não encontrado'], 404);
        }
        
        // Buscar campos do tipo
        $stmtCampos = $this->pdo->prepare("
            SELECT * FROM mkt_tipos_campos 
            WHERE id_tipo_meta = :id_tipo
            ORDER BY ordem ASC
            ");
        $stmtCampos->execute(['id_tipo' => $id]);
        $campos = $stmtCampos->fetchAll(PDO::FETCH_ASSOC);
        
        $tipo['campos'] = $campos;
        
        return $this->json($response, ['success' => true, 'data' => $tipo]);
    } catch (\Exception $e) {
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * DELETE /v1/meta-builder/instancias/{id}
 * Exclui uma instância de meta
 */
public function deletarInstanciaMeta(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    
    try {
        // Verificar se a meta existe
        $stmtCheck = $this->pdo->prepare("SELECT id FROM mkt_metas_instancias WHERE id = :id");
        $stmtCheck->execute(['id' => $id]);
        
        if (!$stmtCheck->fetch()) {
            return $this->json($response, ['success' => false, 'error' => 'Meta não encontrada'], 404);
        }
        
        // Excluir registros de alimentação vinculados
        $this->pdo->prepare("DELETE FROM mkt_alimentacao_registros WHERE id_meta_instancia = :id")
        ->execute(['id' => $id]);
        
        // Excluir da alimentação diária
        $this->pdo->prepare("DELETE FROM mkt_alimentacao_diaria WHERE id_meta = :id")
        ->execute(['id' => $id]);
        
        // Excluir a meta
        $this->pdo->prepare("DELETE FROM mkt_metas_instancias WHERE id = :id")
        ->execute(['id' => $id]);
        
        return $this->json($response, ['success' => true, 'message' => 'Meta excluída com sucesso!']);
    } catch (\Exception $e) {
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}
/**
 * PUT /v1/meta-builder/tipos/{id}
 * Atualiza um tipo de meta
 */
public function atualizarTipoMeta(Request $request, Response $response, array $args): Response
{
    $id = (int)($args['id'] ?? 0);
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    try {
        // Atualizar dados básicos
        $stmt = $this->pdo->prepare("
            UPDATE mkt_tipos_meta 
            SET nome = :nome, 
            descricao = :descricao, 
            icone = :icone, 
            cor = :cor,
            created_at = NOW()
            WHERE id = :id
            ");
        $stmt->execute([
            'id' => $id,
            'nome' => $input['nome'] ?? '',
            'descricao' => $input['descricao'] ?? '',
            'icone' => $input['icone'] ?? 'fa-chart-line',
            'cor' => $input['cor'] ?? 'blue'
        ]);
        
        // Remover campos antigos
        $this->pdo->prepare("DELETE FROM mkt_tipos_campos WHERE id_tipo_meta = :id_tipo")
        ->execute(['id_tipo' => $id]);
        
        // Inserir novos campos
        $campos = $input['campos'] ?? [];
        $stmtCampo = $this->pdo->prepare("
            INSERT INTO mkt_tipos_campos (id_tipo_meta, nome_campo, rotulo, tipo_campo, obrigatorio, ordem, unidade, editavel, tipo_comparacao) 
            VALUES (:id_tipo, :nome_campo, :rotulo, :tipo_campo, :obrigatorio, :ordem, :unidade, :editavel, :tipo_comparacao)
            ");

        foreach ($campos as $index => $campo) {
            $stmtCampo->execute([
                'id_tipo' => $id,
                'nome_campo' => $campo['nome_campo'] ?? '',
                'rotulo' => $campo['rotulo'] ?? '',
                'tipo_campo' => $campo['tipo_campo'] ?? 'number',
                'obrigatorio' => ($campo['obrigatorio'] ?? true) ? 'true' : 'false',
                'ordem' => $index,
                'unidade' => $campo['unidade'] ?? '',
                'editavel' => ($campo['editavel'] ?? true) ? 'true' : 'false',
        'tipo_comparacao' => $campo['tipo_comparacao'] ?? null  // ✅ NOVO
    ]);
        }
        
        return $this->json($response, [
            'success' => true,
            'message' => 'Tipo de meta atualizado com sucesso!'
        ]);
    } catch (\Exception $e) {
        return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
    }
}

    /**
     * GET /v1/meta-builder/instancias/ativas
     * Lista metas ativas para o operador alimentar
     */
    public function getMetasAtivas(Request $request, Response $response): Response
    {
        try {
            // Verificar se a tabela existe
            $stmt = $this->pdo->query("
                SELECT mi.*, 
                COALESCE(tm.nome, 'Meta Padrão') as tipo_nome, 
                COALESCE(tm.icone, 'fa-bullseye') as icone,
                COALESCE(tm.cor, 'emerald') as cor
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.status = 'ativa'
                ORDER BY mi.data_fim ASC NULLS LAST
                ");
            $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada meta, buscar seus campos
            foreach ($metas as &$meta) {
                if ($meta['id_tipo_meta']) {
                    $stmtCampos = $this->pdo->prepare("
                        SELECT * FROM mkt_tipos_campos 
                        WHERE id_tipo_meta = :id_tipo_meta 
                        ORDER BY ordem ASC
                        ");
                    $stmtCampos->execute(['id_tipo_meta' => $meta['id_tipo_meta']]);
                    $meta['campos'] = $stmtCampos->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Meta sem tipo definido - usar campos padrão do JSON
                    $valores = json_decode($meta['valores'], true) ?: [];
                    $meta['campos'] = [];
                    foreach ($valores as $key => $value) {
                        $meta['campos'][] = [
                            'nome_campo' => $key,
                            'rotulo' => ucfirst(str_replace('_', ' ', $key)),
                            'tipo_campo' => 'number',
                            'unidade' => $key === 'meta_faturamento' ? 'R$' : '',
                            'obrigatorio' => true
                        ];
                    }
                }
            }
            
            return $this->json($response, ['success' => true, 'data' => $metas]);
        } catch (\Exception $e) {
            // Em caso de erro, retorna array vazio para não quebrar o front
            error_log('Erro em getMetasAtivas: ' . $e->getMessage());
            return $this->json($response, ['success' => true, 'data' => []]);
        }
    }
    
    /**
     * GET /v1/meta-builder/tipos/{id}/campos
     * Lista os campos de um tipo de meta
     */
    public function getCamposTipo(Request $request, Response $response, array $args): Response
    {
        $idTipo = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mkt_tipos_campos 
                WHERE id_tipo_meta = :id_tipo
                ORDER BY ordem ASC
                ");
            $stmt->execute(['id_tipo' => $idTipo]);
            $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, ['success' => true, 'data' => $campos]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => true, 'data' => []]);
        }
    }


    
    /**
     * GET /v1/meta-builder/dashboard
     * Dashboard com progresso de todas as metas
     */
    public function getDashboard(Request $request, Response $response): Response
    {
        try {
            $sql = "
            SELECT 
            mi.id,
            mi.titulo,
            mi.status,
            mi.data_inicio,
            mi.data_fim,
            mi.valores,
            COALESCE(tm.nome, 'Meta Padrão') as tipo_nome,
            COALESCE(tm.icone, 'fa-bullseye') as icone,
            COALESCE(tm.cor, 'emerald') as cor
            FROM mkt_metas_instancias mi
            LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
            ORDER BY mi.data_fim ASC NULLS LAST
            ";
            $stmt = $this->pdo->query($sql);
            $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->json($response, ['success' => true, 'data' => $metas]);
        } catch (\Exception $e) {
            error_log('Erro em getDashboard: ' . $e->getMessage());
            return $this->json($response, ['success' => true, 'data' => []]);
        }
    }
    
/**
 * POST /v1/meta-builder/alimentar
 * Alimenta uma meta com novos valores
 */
public function alimentarMeta(Request $request, Response $response): Response
{
    $input = json_decode($request->getBody()->getContents(), true) ?? [];
    
    try {
        $idMeta = (int)($input['id_meta_instancia'] ?? 0);
        $dataRegistro = $input['data_registro'] ?? date('Y-m-d');
        $valores = $input['valores'] ?? [];
        $usuarioId = (int)($input['usuario_id'] ?? 0);
        
        if ($usuarioId === 0) {
            $usuarioId = $this->getUserIdFromToken($request);
        }
        
        if (!$idMeta) {
            return $this->json($response, [
                'success' => false, 
                'error' => 'Meta não informada'
            ], 400);
        }
        
        $service = new \Nutricional\Services\MarketingService($this->pdo);
        $result = $service->registrarAlimentacao($idMeta, $dataRegistro, $valores, $usuarioId);
        
        if ($result['success']) {
            return $this->json($response, $result);
        } else {
            return $this->json($response, $result, 500);
        }
    } catch (\Exception $e) {
        error_log('Erro em alimentarMeta: ' . $e->getMessage());
        return $this->json($response, [
            'success' => false, 
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * GET /v1/meta-builder/progresso/{id}
 * Retorna o progresso detalhado de uma meta
 */
public function getProgressoMeta(Request $request, Response $response, array $args): Response
{
    $idMeta = (int)($args['id'] ?? 0);
    
    if (!$idMeta) {
        return $this->json($response, [
            'success' => false, 
            'error' => 'ID da meta não informado'
        ], 400);
    }
    
    $service = new \Nutricional\Services\MarketingService($this->pdo);
    $result = $service->calcularProgressoMeta($idMeta);
    
    return $this->json($response, $result);
}

/**
 * GET /v1/meta-builder/campos/{id}
 * Retorna campos editáveis de uma meta
 */
public function getCamposEditaveis(Request $request, Response $response, array $args): Response
{
    $idMeta = (int)($args['id'] ?? 0);
    
    if (!$idMeta) {
        return $this->json($response, [
            'success' => false, 
            'error' => 'ID da meta não informado'
        ], 400);
    }
    
    $service = new \Nutricional\Services\MarketingService($this->pdo);
    $campos = $service->getCamposEditaveisMeta($idMeta);
    
    return $this->json($response, [
        'success' => true, 
        'data' => $campos
    ]);
}

/**
 * Helper para extrair user ID do token
 */
private function getUserIdFromToken($request): int
{
    $authHeader = $request->getHeaderLine('Authorization');
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? 'chave_super_secreta', 'HS256'));
            return $decoded->uid ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    return 0;
}
/**
 * ✅ NOVO: Registrar log de alimentação
 */
private function registrarLogAlimentacao($idMeta, $usuarioId, $valores)
{
    try {
        // Verificar se tabela de logs existe
        $stmt = $this->pdo->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_name = 'mkt_alimentacao_logs'
            )
        ");
        $existe = $stmt->fetchColumn();
        
        if (!$existe) {
            $this->pdo->exec("
                CREATE TABLE mkt_alimentacao_logs (
                    id SERIAL PRIMARY KEY,
                    id_meta_instancia INTEGER,
                    usuario_id INTEGER,
                    valores JSONB,
                    data_registro DATE,
                    created_at TIMESTAMP DEFAULT NOW()
                )
            ");
        }
        
        $stmtLog = $this->pdo->prepare("
            INSERT INTO mkt_alimentacao_logs (id_meta_instancia, usuario_id, valores, data_registro)
            VALUES (:id_meta, :usuario_id, :valores::jsonb, :data_registro)
        ");
        $stmtLog->execute([
            'id_meta' => $idMeta,
            'usuario_id' => $usuarioId,
            'valores' => json_encode($valores),
            'data_registro' => date('Y-m-d')
        ]);
    } catch (\Exception $e) {
        error_log('Erro ao registrar log: ' . $e->getMessage());
    }
}


    
    /**
 * GET /v1/meta-builder/instancias/{id}
 * Busca uma instância de meta específica
 */
    public function getInstanciaMeta(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT mi.*, tm.nome as tipo_nome, tm.icone, tm.cor
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.id = :id
                ");
            $stmt->execute(['id' => $id]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$meta) {
                return $this->json($response, ['success' => false, 'error' => 'Meta não encontrada'], 404);
            }
            
            return $this->json($response, ['success' => true, 'data' => $meta]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * Atualiza a tabela de alimentação diária para compatibilidade
     */
    private function atualizarAlimentacaoDiaria($idMeta, $dataRegistro, $valores)
    {
        try {
            $leads = $valores['meta_leads'] ?? $valores['leads'] ?? $valores['leads_recebidos'] ?? 0;
            $vendas = $valores['vendas'] ?? $valores['vendas_fechadas'] ?? 0;
            $faturamento = $valores['meta_faturamento'] ?? $valores['faturamento'] ?? $valores['valor_faturado'] ?? 0;
            $investimento = $valores['investimento'] ?? $valores['investimento_dia'] ?? 0;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO mkt_alimentacao_diaria (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, id_meta, idusuario_mkt)
                VALUES (:data, :leads, :vendas, :faturamento, :investimento, :id_meta, 0)
                ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                leads_recebidos = mkt_alimentacao_diaria.leads_recebidos + EXCLUDED.leads_recebidos,
                vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + EXCLUDED.vendas_fechadas,
                valor_faturado = mkt_alimentacao_diaria.valor_faturado + EXCLUDED.valor_faturado,
                investimento_dia = mkt_alimentacao_diaria.investimento_dia + EXCLUDED.investimento_dia
                ");
            
            $stmt->execute([
                'data' => $dataRegistro,
                'leads' => (int)$leads,
                'vendas' => (int)$vendas,
                'faturamento' => (float)$faturamento,
                'investimento' => (float)$investimento,
                'id_meta' => $idMeta
            ]);
        } catch (\Exception $e) {
            // Não falha se não conseguir atualizar
            error_log('Erro ao atualizar alimentacao_diaria: ' . $e->getMessage());
        }
    }
    
    /**
     * Verifica e cria a tabela de registros se não existir
     */
    private function verificarTabelaRegistros()
    {
        try {
            // Verificar se a tabela existe
            $stmt = $this->pdo->query("
                SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_name = 'mkt_alimentacao_registros'
                )
                ");
            $existe = $stmt->fetchColumn();
            
            if (!$existe) {
                // Criar tabela
                $this->pdo->exec("
                    CREATE TABLE mkt_alimentacao_registros (
                    id SERIAL PRIMARY KEY,
                    id_meta_instancia INTEGER,
                    data_registro DATE DEFAULT CURRENT_DATE,
                    valores JSONB,
                    usuario_id INTEGER,
                    created_at TIMESTAMP DEFAULT NOW()
                    )
                    ");
            }
        } catch (\Exception $e) {
            error_log('Erro ao verificar tabela: ' . $e->getMessage());
        }
    }
    
    private function json($response, $data, $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}