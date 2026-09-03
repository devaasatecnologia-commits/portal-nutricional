<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Cron\CronExpression;

class CronController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->criarTabelasSeNecessario();
    }
    
    // ======================================================================
    // DASHBOARD
    // ======================================================================
    public function getDashboard(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $dias = (int)($params['dias'] ?? 7);
            $dias = min(max($dias, 1), 90); // Limita entre 1 e 90 dias
            
            // Estatísticas gerais
            $stats = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN ativo = true THEN 1 ELSE 0 END) as jobs_ativos,
                    (SELECT COUNT(*) FROM cron_execucoes WHERE iniciado_em::date = CURRENT_DATE) as execucoes_hoje,
                    (SELECT COUNT(*) FROM cron_execucoes WHERE status = 'falha' AND iniciado_em::date = CURRENT_DATE) as falhas_hoje
                FROM cron_jobs
            ")->fetch(\PDO::FETCH_ASSOC);
            
            // Execuções recentes
            $recentes = $this->pdo->prepare("
                SELECT e.*, j.nome, c.cor as cor_categoria
                FROM cron_execucoes e
                JOIN cron_jobs j ON j.id = e.job_id
                LEFT JOIN cron_categorias c ON c.id = j.categoria_id
                ORDER BY e.iniciado_em DESC
                LIMIT 10
            ");
            $recentes->execute();
            $recentes = $recentes->fetchAll(\PDO::FETCH_ASSOC);
            
            // Próximas execuções
            $proximas = $this->pdo->query("
                SELECT j.*, c.cor as cor_categoria
                FROM cron_jobs j
                LEFT JOIN cron_categorias c ON c.id = j.categoria_id
                WHERE j.ativo = true AND j.schedule IS NOT NULL
                ORDER BY j.proxima_execucao ASC
                LIMIT 10
            ")->fetchAll(\PDO::FETCH_ASSOC);
            
            // Gráfico de execuções por dia
            $sqlGrafico = "SELECT 
                iniciado_em::date as data,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
                SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas
            FROM cron_execucoes
            WHERE iniciado_em >= CURRENT_DATE - INTERVAL '{$dias} days'
            GROUP BY iniciado_em::date
            ORDER BY data ASC";
            
            $grafico = $this->pdo->query($sqlGrafico)->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'stats' => $stats,
                'recentes' => $recentes,
                'proximas' => $proximas,
                'grafico' => $grafico
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    // Adicionar no CronController.php

public function refreshCuboVendas(Request $request, Response $response): Response
{
    try {
        $stmt = $this->pdo->prepare("SELECT refresh_cubo_vendas()");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $result
        ]);
    } catch (\Exception $e) {
        return $this->jsonResponse($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * POST /v1/cron/lembretes
 * Job CRON para verificar lembretes
 */
public function verificarLembretes($request, $response)
{
    try {
        $pdo = \getPDO();
        
        // Buscar lembretes que estão próximos (15 min)
        $sql = "
            SELECT l.*, c.nome as cliente_nome, c.telefone, u.idusuario
            FROM mkt_lembretes l
            JOIN mkt_clientes c ON c.id = l.id_cliente
            LEFT JOIN usuario u ON u.idcliforemp = c.usuario_criacao::int
            WHERE l.concluido = false 
            AND l.data_lembrete = CURRENT_DATE
            AND (
                (EXTRACT(HOUR FROM CURRENT_TIME) * 60 + EXTRACT(MINUTE FROM CURRENT_TIME)) 
                BETWEEN 
                (EXTRACT(HOUR FROM l.hora_lembrete) * 60 + EXTRACT(MINUTE FROM l.hora_lembrete) - 15)
                AND 
                (EXTRACT(HOUR FROM l.hora_lembrete) * 60 + EXTRACT(MINUTE FROM l.hora_lembrete) + 5)
            )
            AND NOT EXISTS (
                SELECT 1 FROM crm_notificacoes n 
                WHERE n.tipo = 'lembrete' 
                AND n.id_referencia = l.id 
                AND n.created_at > CURRENT_DATE
            )
        ";
        $stmt = $pdo->query($sql);
        $lembretes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $criadas = 0;
        foreach ($lembretes as $lembrete) {
            // Criar notificação
            $stmtInsert = $pdo->prepare("
                INSERT INTO crm_notificacoes (
                    titulo, mensagem, tipo, id_referencia, id_usuario, link, created_at
                ) VALUES (
                    '⏰ Lembrete pendente!',
                    :mensagem,
                    'lembrete',
                    :id_referencia,
                    :id_usuario,
                    :link,
                    NOW()
                )
            ");
            
            $mensagem = "Lembrete para {$lembrete['cliente_nome']}: {$lembrete['descricao']} às {$lembrete['hora_lembrete']}";
            
            $stmtInsert->execute([
                'mensagem' => $mensagem,
                'id_referencia' => $lembrete['id'],
                'id_usuario' => $lembrete['idusuario'] ?? null,
                'link' => "/portal/modules/marketing/clientes.php?id={$lembrete['id_cliente']}"
            ]);
            
            $criadas++;
        }
        
        return $this->json($response, [
            'success' => true,
            'lembretes_processados' => $criadas,
            'total_encontrados' => count($lembretes)
        ]);
        
    } catch (Exception $e) {
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    // ======================================================================
    // GERENCIAMENTO DE JOBS (CRUD)
    // ======================================================================
    
    public function getJobs(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->query("
                SELECT j.*, c.nome as categoria_nome, c.cor, c.icone,
                       (SELECT COUNT(*) FROM cron_execucoes WHERE job_id = j.id) as total_execucoes,
                       (SELECT COUNT(*) FROM cron_execucoes WHERE job_id = j.id AND status = 'falha') as total_falhas
                FROM cron_jobs j
                LEFT JOIN cron_categorias c ON c.id = j.categoria_id
                ORDER BY j.ativo DESC, j.nome
            ");
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calcular próxima execução para cada job
            foreach ($jobs as &$job) {
                if ($job['ativo'] && $job['schedule']) {
                    try {
                        $cron = new CronExpression($job['schedule']);
                        $job['proxima_execucao'] = $cron->getNextRunDate()->format('Y-m-d H:i:s');
                        $this->pdo->prepare("UPDATE cron_jobs SET proxima_execucao = ? WHERE id = ?")
                            ->execute([$job['proxima_execucao'], $job['id']]);
                    } catch (\Exception $e) {
                        $job['proxima_execucao'] = null;
                    }
                }
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'jobs' => $jobs
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    public function salvarJob(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        try {
            // Validar expressão cron
            if (!empty($input['schedule'])) {
                try {
                    new CronExpression($input['schedule']);
                } catch (\Exception $e) {
                    return $this->jsonResponse($response, [
                        'success' => false,
                        'message' => 'Expressão cron inválida: ' . $e->getMessage()
                    ], 400);
                }
            }
            
            if (isset($input['id']) && $input['id']) {
                // Atualizar
                $stmt = $this->pdo->prepare("
                    UPDATE cron_jobs 
                    SET nome = ?, descricao = ?, categoria_id = ?, ativo = ?, schedule = ?, 
                        notificar_email = ?, notificar_sucesso = ?, notificar_falha = ?, parametros = ?::jsonb
                    WHERE id = ?
                ");
                $stmt->execute([
                    $input['nome'],
                    $input['descricao'] ?? '',
                    $input['categoria_id'] ?? null,
                    $input['ativo'] ? 'true' : 'false',
                    $input['schedule'] ?? null,
                    $input['notificar_email'] ?? null,
                    $input['notificar_sucesso'] ? 'true' : 'false',
                    $input['notificar_falha'] ? 'true' : 'false',
                    json_encode($input['parametros'] ?? []),
                    $input['id']
                ]);
                $message = 'Job atualizado com sucesso!';
            } else {
                // Criar
                $stmt = $this->pdo->prepare("
                    INSERT INTO cron_jobs (nome, descricao, categoria_id, comando, ativo, schedule, 
                                          notificar_email, notificar_sucesso, notificar_falha, parametros)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb)
                    RETURNING id
                ");
                $stmt->execute([
                    $input['nome'],
                    $input['descricao'] ?? '',
                    $input['categoria_id'] ?? null,
                    $input['comando'],
                    $input['ativo'] ? 'true' : 'false',
                    $input['schedule'] ?? null,
                    $input['notificar_email'] ?? null,
                    $input['notificar_sucesso'] ? 'true' : 'false',
                    $input['notificar_falha'] ? 'true' : 'false',
                    json_encode($input['parametros'] ?? [])
                ]);
                $message = 'Job criado com sucesso!';
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    public function deletarJob(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cron_jobs WHERE id = ?");
            $stmt->execute([$id]);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Job removido com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    // ======================================================================
    // AUDITORIA (Histórico de Execuções)
    // ======================================================================
    
    public function getAuditoria(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $tipo = $params['tipo'] ?? '';
            $limit = min((int)($params['limit'] ?? 50), 200);
            
            $sql = "SELECT e.*, j.nome, j.comando, c.cor as cor_categoria
                    FROM cron_execucoes e
                    JOIN cron_jobs j ON j.id = e.job_id
                    LEFT JOIN cron_categorias c ON c.id = j.categoria_id";
            
            $conditions = [];
            $bindings = [];
            
            if (!empty($tipo)) {
                $conditions[] = "j.comando = :tipo";
                $bindings['tipo'] = $tipo;
            }
            
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            
            $sql .= " ORDER BY e.iniciado_em DESC LIMIT :limit";
            $bindings['limit'] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($bindings as $key => $value) {
                $stmt->bindValue($key, $value, $key === 'limit' ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $auditoria = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'auditoria' => $auditoria
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    public function getAuditoriaDetalhes(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
            
            $stmt = $this->pdo->prepare("
                SELECT e.*, j.nome, j.comando
                FROM cron_execucoes e
                JOIN cron_jobs j ON j.id = e.job_id
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $execucao = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$execucao) {
                return $this->jsonResponse($response, ['error' => 'Execução não encontrada'], 404);
            }
            
            if (!empty($execucao['resultado'])) {
                $execucao['resultado'] = json_decode($execucao['resultado'], true);
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'execucao' => $execucao
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    // ======================================================================
    // EXECUÇÃO DE JOBS (Apenas dispara, a lógica está no CronRotinasController)
    // ======================================================================
    
    public function executar(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $comando = $input['comando'] ?? '';
        $user = $request->getAttribute('user') ?? [];
        $usuario = $user['username'] ?? 'SISTEMA';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $origem = $input['origem'] ?? 'MANUAL';
        
        // Buscar job
        $job = $this->pdo->prepare("SELECT * FROM cron_jobs WHERE comando = ?");
        $job->execute([$comando]);
        $jobData = $job->fetch(\PDO::FETCH_ASSOC);
        
        if (!$jobData) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Job não encontrado'
            ], 404);
        }
        
        // Criar registro de execução
        $execId = $this->criarExecucao($jobData['id'], $usuario, $ip, $origem);
        
        $startTime = microtime(true);
        
        try {
            // ✅ Delegar para o CronRotinasController
            $rotinasController = new CronRotinasController();
            $resultado = $rotinasController->executarRotina($comando, $jobData['parametros'] ?? [], $usuario, $ip);
            
            $duracao = round(microtime(true) - $startTime, 2);
            
            // Atualizar execução
            $this->finalizarExecucao($execId, $resultado['success'] ? 'sucesso' : 'falha', $resultado, $duracao);
            
            // Atualizar job
            $this->pdo->prepare("
                UPDATE cron_jobs 
                SET ultima_execucao = NOW(), tempo_medio_execucao = (tempo_medio_execucao * 0.7 + ? * 0.3)
                WHERE id = ?
            ")->execute([$duracao, $jobData['id']]);
            
            return $this->jsonResponse($response, [
                'success' => $resultado['success'],
                'message' => $resultado['message'],
                'detalhes' => $resultado['detalhes'] ?? [],
                'duracao' => $duracao,
                'execucao_id' => $execId
            ]);
            
        } catch (\Exception $e) {
            $duracao = round(microtime(true) - $startTime, 2);
            $this->finalizarExecucao($execId, 'falha', ['error' => $e->getMessage()], $duracao);
            
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // ======================================================================
    // MÉTODOS AUXILIARES
    // ======================================================================
    
    private function criarExecucao($jobId, $usuario, $ip, $origem)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cron_execucoes (job_id, status, usuario, origem, ip, iniciado_em)
            VALUES (?, 'executando', ?, ?, ?, NOW())
            RETURNING id
        ");
        $stmt->execute([$jobId, $usuario, $origem, $ip]);
        return $stmt->fetchColumn();
    }
    
    private function finalizarExecucao($execId, $status, $resultado, $duracao)
    {
        $stmt = $this->pdo->prepare("
            UPDATE cron_execucoes 
            SET status = ?, finalizado_em = NOW(), duracao_segundos = ?, resultado = ?::jsonb
            WHERE id = ?
        ");
        $stmt->execute([$status, $duracao, json_encode($resultado), $execId]);
    }
    
    private function criarTabelasSeNecessario()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cron_categorias (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(50) NOT NULL,
                cor VARCHAR(20) DEFAULT '#6c757d',
                icone VARCHAR(50) DEFAULT 'fa-cog'
            )
        ");
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM cron_categorias");
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->exec("
                INSERT INTO cron_categorias (nome, cor, icone) VALUES
                ('Email', '#0d6efd', 'fa-envelope'),
                ('Processamento', '#10b981', 'fa-gear'),
                ('Integração', '#f59e0b', 'fa-cloud-upload-alt'),
                ('Financeiro', '#ef4444', 'fa-dollar-sign')
            ");
        }
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cron_jobs (
                id SERIAL PRIMARY KEY,
                categoria_id INT REFERENCES cron_categorias(id),
                nome VARCHAR(100) NOT NULL,
                descricao TEXT,
                comando VARCHAR(100) NOT NULL UNIQUE,
                ativo BOOLEAN DEFAULT true,
                schedule VARCHAR(50),
                ultima_execucao TIMESTAMP,
                proxima_execucao TIMESTAMP,
                tempo_medio_execucao INT DEFAULT 0,
                notificar_email VARCHAR(255),
                notificar_sucesso BOOLEAN DEFAULT false,
                notificar_falha BOOLEAN DEFAULT true,
                parametros JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cron_execucoes (
                id SERIAL PRIMARY KEY,
                job_id INT REFERENCES cron_jobs(id) ON DELETE CASCADE,
                status VARCHAR(20) NOT NULL,
                iniciado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                finalizado_em TIMESTAMP,
                duracao_segundos DECIMAL(10,2),
                resultado JSONB,
                log TEXT,
                usuario VARCHAR(100),
                origem VARCHAR(20) DEFAULT 'AGENDADO',
                ip VARCHAR(45)
            )
        ");
        
        // Inserir jobs padrão
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM cron_jobs");
        if ($stmt->fetchColumn() == 0) {
            $categorias = $this->pdo->query("SELECT id, nome FROM cron_categorias")->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $jobs = [
                ['Representantes', 'Envia relatório de alterações em pedidos', 'representantes', '0 8 * * *', 'Email'],
                ['Gestores', 'Envia relatório de inadimplência', 'gestores', '0 8 * * *', 'Email'],
                ['Histórico KPI', 'Processa histórico financeiro', 'historico_kpi', '0 23 * * *', 'Processamento'],
                ['Notas Nutrire', 'Envia notas para API Nutrire', 'notas_nutrire', '0 9 * * *', 'Integração'],
                ['Flex Mínimo Gestor', 'Gera Flex para gestores', 'flex_minimo_gestor', '0 1 * * *', 'Financeiro'],
                ['Bonificações Flex', 'Processa bonificações', 'bonificacoes_flex', '0 2 * * *', 'Financeiro']
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO cron_jobs (nome, descricao, comando, schedule, categoria_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($jobs as $job) {
                $categoriaId = array_search($job[4], $categorias) ?: null;
                $stmt->execute([$job[0], $job[1], $job[2], $job[3], $categoriaId]);
            }
        }
    }
    
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}