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
            
            $proxima = null;
            if (!empty($input['ativo']) && !empty($input['schedule'])) {
                try {
                    $proxima = (new CronExpression($input['schedule']))->getNextRunDate()->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $proxima = null;
                }
            }

            if (isset($input['id']) && $input['id']) {
                // Atualizar
                $stmt = $this->pdo->prepare("
                    UPDATE cron_jobs 
                    SET nome = ?, descricao = ?, categoria_id = ?, comando = COALESCE(NULLIF(?, ''), comando),
                        ativo = ?, schedule = ?, proxima_execucao = ?,
                        notificar_email = ?, notificar_sucesso = ?, notificar_falha = ?, parametros = ?::jsonb,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $input['nome'],
                    $input['descricao'] ?? '',
                    $input['categoria_id'] ?? null,
                    $input['comando'] ?? '',
                    $input['ativo'] ? 'true' : 'false',
                    $input['schedule'] ?? null,
                    $proxima,
                    $input['notificar_email'] ?? null,
                    $input['notificar_sucesso'] ? 'true' : 'false',
                    $input['notificar_falha'] ? 'true' : 'false',
                    json_encode($input['parametros'] ?? []),
                    $input['id']
                ]);
                $message = 'Job atualizado com sucesso!';
            } else {
                if (!isset(CronRotinasController::ROTINAS[$input['comando'] ?? ''])) {
                    return $this->jsonResponse($response, [
                        'success' => false,
                        'message' => 'Comando inválido. Escolha uma das rotinas disponíveis.'
                    ], 400);
                }
                // Criar
                $stmt = $this->pdo->prepare("
                    INSERT INTO cron_jobs (nome, descricao, categoria_id, comando, ativo, schedule, proxima_execucao,
                                          notificar_email, notificar_sucesso, notificar_falha, parametros)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb)
                    RETURNING id
                ");
                $stmt->execute([
                    $input['nome'],
                    $input['descricao'] ?? '',
                    $input['categoria_id'] ?? null,
                    $input['comando'],
                    $input['ativo'] ? 'true' : 'false',
                    $input['schedule'] ?? null,
                    $proxima,
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

    /**
     * POST /cron/jobs/{id}/toggle - Alterna Ativo/Inativo com 1 clique no painel Admin
     */
    public function toggleJob(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $input = json_decode($request->getBody()->getContents(), true) ?? [];

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM cron_jobs WHERE id = ?");
            $stmt->execute([$id]);
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Job não encontrado'
                ], 404);
            }

            $novoStatus = array_key_exists('ativo', $input) ? (bool)$input['ativo'] : !((bool)$job['ativo']);
            $proxima = null;

            if ($novoStatus && !empty($job['schedule'])) {
                try {
                    $proxima = (new CronExpression($job['schedule']))->getNextRunDate()->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $proxima = null;
                }
            }

            $upd = $this->pdo->prepare("
                UPDATE cron_jobs
                SET ativo = ?, proxima_execucao = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$novoStatus ? 'true' : 'false', $proxima, $id]);

            return $this->jsonResponse($response, [
                'success' => true,
                'id' => $id,
                'ativo' => $novoStatus,
                'proxima_execucao' => $proxima,
                'message' => $novoStatus
                    ? "Job \"{$job['nome']}\" ATIVADO! Próxima execução: " . ($proxima ? date('d/m/Y H:i', strtotime($proxima)) : 'Não agendado')
                    : "Job \"{$job['nome']}\" INATIVADO (pausado)."
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
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
            if (isset(CronRotinasController::ROTINAS[$comando])) {
                [$nomeDef, $descDef] = CronRotinasController::ROTINAS[$comando];
                $ins = $this->pdo->prepare("
                    INSERT INTO cron_jobs (nome, descricao, categoria_id, comando, ativo, schedule, parametros)
                    VALUES (?, ?, 2, ?, false, '0 6 * * *', '{}'::jsonb)
                    RETURNING *
                ");
                $ins->execute([$nomeDef, $descDef, $comando]);
                $jobData = $ins->fetch(\PDO::FETCH_ASSOC);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Job não encontrado'
                ], 404);
            }
        }
        
        // Criar registro de execução
        $execId = $this->criarExecucao($jobData['id'], $usuario, $ip, $origem);
        
        $startTime = microtime(true);
        
        try {
            // ✅ Delegar para o CronRotinasController
            $rotinasController = new CronRotinasController();
            $resultado = $rotinasController->executarRotina($comando, $this->parametrosDoJob($jobData), $usuario, $ip);
            
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
                'email_html' => $resultado['email_html'] ?? null,
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

    /**
     * POST /cron/testar - Testa configuração do Cron + executa Simulação (Dry-Run) sem alterar dados
     */
    public function testar(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $comando = trim((string)($input['comando'] ?? ''));
        $jobId = isset($input['id']) && $input['id'] ? (int)$input['id'] : null;

        try {
            $jobBanco = null;
            if ($jobId) {
                $st = $this->pdo->prepare("SELECT * FROM cron_jobs WHERE id = ?");
                $st->execute([$jobId]);
                $jobBanco = $st->fetch(\PDO::FETCH_ASSOC);
            } elseif ($comando !== '') {
                $st = $this->pdo->prepare("SELECT * FROM cron_jobs WHERE comando = ?");
                $st->execute([$comando]);
                $jobBanco = $st->fetch(\PDO::FETCH_ASSOC);
            }

            if ($comando === '' && $jobBanco) {
                $comando = (string)$jobBanco['comando'];
            }

            $checks = [];
            $temErro = false;

            // 1. Verificar se a rotina existe no motor
            if (isset(CronRotinasController::ROTINAS[$comando])) {
                [$rotinaNome, $rotinaDesc] = CronRotinasController::ROTINAS[$comando];
                $checks[] = [
                    'etapa' => 'Rotina / Comando',
                    'status' => 'ok',
                    'detalhe' => "Comando '{$comando}' reconhecido ({$rotinaNome}: {$rotinaDesc})."
                ];
            } else {
                $temErro = true;
                $checks[] = [
                    'etapa' => 'Rotina / Comando',
                    'status' => 'error',
                    'detalhe' => "Comando '{$comando}' não existe nas rotinas disponíveis."
                ];
            }

            // 2. Validar Expressão Cron (Schedule) e calcular próximas 3 execuções
            $schedule = array_key_exists('schedule', $input)
                ? trim((string)$input['schedule'])
                : trim((string)($jobBanco['schedule'] ?? ''));

            $proximasExecucoes = [];
            if ($schedule !== '') {
                try {
                    $cronExpr = new CronExpression($schedule);
                    for ($i = 0; $i < 3; $i++) {
                        $proximasExecucoes[] = $cronExpr->getNextRunDate('now', $i)->format('d/m/Y H:i:s');
                    }
                    $checks[] = [
                        'etapa' => 'Expressão Cron (Schedule)',
                        'status' => 'ok',
                        'detalhe' => "Expressão '{$schedule}' válida. Próxima execução: " . $proximasExecucoes[0]
                    ];
                } catch (\Throwable $e) {
                    $temErro = true;
                    $checks[] = [
                        'etapa' => 'Expressão Cron (Schedule)',
                        'status' => 'error',
                        'detalhe' => "Expressão '{$schedule}' inválida: " . $e->getMessage()
                    ];
                }
            } else {
                $checks[] = [
                    'etapa' => 'Expressão Cron (Schedule)',
                    'status' => 'warn',
                    'detalhe' => 'Nenhum horário (Schedule) definido — este job só rodará manualmente.'
                ];
            }

            // 3. Validar E-mails de Notificação
            $notificarEmail = array_key_exists('notificar_email', $input)
                ? trim((string)$input['notificar_email'])
                : trim((string)($jobBanco['notificar_email'] ?? ''));

            if ($notificarEmail !== '') {
                $lista = array_filter(array_map('trim', explode(',', $notificarEmail)));
                $invalidos = [];
                foreach ($lista as $em) {
                    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
                        $invalidos[] = $em;
                    }
                }
                if (!empty($invalidos)) {
                    $temErro = true;
                    $checks[] = [
                        'etapa' => 'Notificação por E-mail',
                        'status' => 'error',
                        'detalhe' => 'E-mail(s) com formato inválido: ' . implode(', ', $invalidos)
                    ];
                } else {
                    $checks[] = [
                        'etapa' => 'Notificação por E-mail',
                        'status' => 'ok',
                        'detalhe' => count($lista) . ' destinatário(s) válido(s): ' . implode(', ', $lista)
                    ];
                }
            } else {
                $checks[] = [
                    'etapa' => 'Notificação por E-mail',
                    'status' => 'warn',
                    'detalhe' => 'Nenhum e-mail configurado no job (usará padrão da rotina se aplicável).'
                ];
            }

            // 4. Validar JSON de Parâmetros
            $parametros = $input['parametros'] ?? ($jobBanco ? $this->parametrosDoJob($jobBanco) : []);
            if (is_string($parametros)) {
                $decoded = json_decode($parametros, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $temErro = true;
                    $checks[] = [
                        'etapa' => 'Parâmetros (JSON)',
                        'status' => 'error',
                        'detalhe' => 'JSON de parâmetros inválido: ' . json_last_error_msg()
                    ];
                    $parametros = [];
                } else {
                    $parametros = is_array($decoded) ? $decoded : [];
                }
            }

            // 5. Simulação (Dry-Run) específica da rotina (100% somente leitura)
            $simulacao = null;
            if (!$temErro && isset(CronRotinasController::ROTINAS[$comando])) {
                $inicioSim = microtime(true);
                $rotinasController = new CronRotinasController();
                $simulacao = $rotinasController->testarRotina($comando, is_array($parametros) ? $parametros : []);
                $simulacao['duracao_segundos'] = round(microtime(true) - $inicioSim, 2);

                if (!empty($simulacao['checks']) && is_array($simulacao['checks'])) {
                    foreach ($simulacao['checks'] as $c) {
                        $checks[] = $c;
                        if (($c['status'] ?? '') === 'error') {
                            $temErro = true;
                        }
                    }
                }
            }

            return $this->jsonResponse($response, [
                'success' => !$temErro && ($simulacao['success'] ?? true),
                'comando' => $comando,
                'ativo_no_banco' => $jobBanco ? (bool)$jobBanco['ativo'] : null,
                'message' => !$temErro && ($simulacao['success'] ?? true)
                    ? ($simulacao['message'] ?? 'Configuração e simulação validadas com sucesso!')
                    : ($simulacao['message'] ?? 'Foram encontrados alertas ou erros na validação.'),
                'checks' => $checks,
                'proximas_execucoes' => $proximasExecucoes,
                'simulacao' => $simulacao
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao testar configuração: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ======================================================================
    // MÉTODOS AUXILIARES
    // ======================================================================

    /** JSONB volta do PDO como string: converte para array. */
    private function parametrosDoJob(array $job): array
    {
        $p = $job['parametros'] ?? [];
        if (is_string($p)) {
            $p = json_decode($p, true);
        }
        if (!is_array($p)) {
            $p = [];
        }
        if (!empty($job['notificar_email']) && empty($p['email_notificacao'])) {
            $p['email_notificacao'] = $job['notificar_email'];
        }
        $p['notificar_sucesso'] = (bool)($job['notificar_sucesso'] ?? false);
        $p['notificar_falha'] = (bool)($job['notificar_falha'] ?? true);
        return $p;
    }

    /**
     * Executa um job (usado pelo dispatcher cron/run.php). Registra a execucao
     * e atualiza ultima/proxima execucao.
     */
    public function executarJobAgendado(array $job): array
    {
        $execId = $this->criarExecucao($job['id'], 'SISTEMA', 'CLI', 'AGENDADO');
        $inicio = microtime(true);
        try {
            $resultado = (new CronRotinasController())->executarRotina(
                $job['comando'],
                $this->parametrosDoJob($job),
                'SISTEMA',
                'CLI'
            );
        } catch (\Throwable $e) {
            $resultado = ['success' => false, 'message' => $e->getMessage()];
        }
        $duracao = round(microtime(true) - $inicio, 2);
        $this->finalizarExecucao($execId, !empty($resultado['success']) ? 'sucesso' : 'falha', $resultado, $duracao);

        $proxima = null;
        try {
            $proxima = (new CronExpression($job['schedule']))->getNextRunDate()->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
        }
        $this->pdo->prepare("
            UPDATE cron_jobs
            SET ultima_execucao = NOW(), proxima_execucao = ?,
                tempo_medio_execucao = (tempo_medio_execucao * 0.7 + ? * 0.3)
            WHERE id = ?
        ")->execute([$proxima, $duracao, $job['id']]);

        return $resultado + ['duracao' => $duracao];
    }

    /** GET /cron/rotinas - comandos disponiveis para criar jobs */
    public function getRotinas(Request $request, Response $response): Response
    {
        $lista = [];
        foreach (CronRotinasController::ROTINAS as $comando => [$nome, $desc]) {
            $lista[] = ['comando' => $comando, 'nome' => $nome, 'descricao' => $desc];
        }
        return $this->jsonResponse($response, ['success' => true, 'rotinas' => $lista]);
    }

    /**
     * GET /cron/servidor - como agendar o dispatcher na hospedagem.
     * A hospedagem desabilita exec(), entao o painel nao consegue editar o crontab:
     * mostramos a linha exata para colar em cPanel > Tarefas Cron.
     */
    public function getServidor(Request $request, Response $response): Response
    {
        $script = realpath(__DIR__ . '/../../cron/run.php') ?: (dirname(__DIR__, 2) . '/cron/run.php');
        $logDir = dirname(__DIR__, 3) . '/logs';
        $php = '/usr/local/bin/ea-php82';
        $linha = "{$php} {$script} >> {$logDir}/cron_run.log 2>&1";
        $rawToken = defined('API_TOKEN') ? (string)API_TOKEN : (string)($_ENV['API_TOKEN'] ?? '');
        $escapedKey = str_replace(['%', ')'], ['\\%', '\\)'], urlencode($rawToken));
        $linhaCurlDispatcher = '/usr/bin/curl -k -s -A "CronJob" "https://api.nutricionalbr.com/index.php?acao=m0t0rCr0nP0rt4l&key=' . $escapedKey . '" > /dev/null 2>&1';
        $linhaCurlBonif = '/usr/bin/curl -k -s -A "CronJob" "https://api.nutricionalbr.com/index.php?acao=b0n1f1c4c03sFl3x&key=' . $escapedKey . '" > /dev/null 2>&1';

        $hb = $logDir . '/cron_heartbeat.txt';
        $ultimo = is_file($hb) ? (int)trim((string)@file_get_contents($hb)) : 0;
        $ativo = $ultimo > 0 && (time() - $ultimo) <= 180;
        $ultimoFormatado = $ultimo ? date('Y-m-d H:i:s', $ultimo) : null;

        try {
            $hbRow = $this->pdo->query("
                SELECT ultimo_batimento,
                       EXTRACT(EPOCH FROM (NOW() - ultimo_batimento))::int AS segundos_atras
                FROM cron_dispatcher_status
                WHERE id = 1
            ")->fetch(\PDO::FETCH_ASSOC);
            if ($hbRow && !empty($hbRow['ultimo_batimento'])) {
                $segAtras = (int)$hbRow['segundos_atras'];
                if ($segAtras <= 180) {
                    $ativo = true;
                }
                $ultimoFormatado = date('Y-m-d H:i:s', strtotime($hbRow['ultimo_batimento']));
            }
        } catch (\Throwable $e) {
            // tabela opcional
        }

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        $execOk = function_exists('exec') && !in_array('exec', $disabled, true);

        $cronToken = defined('CRON_TOKEN') ? (string)CRON_TOKEN : (string)($_ENV['CRON_TOKEN'] ?? '');
        $linhaCurlModerna = '/usr/bin/curl -k -s -H "X-Cron-Token: ' . $cronToken . '" "https://api.nutricionalbr.com/v1/cron/dispatcher" > /dev/null 2>&1';
        $linhaCurlModernaQuery = '/usr/bin/curl -k -s "https://api.nutricionalbr.com/v1/cron/dispatcher?token=' . urlencode($cronToken) . '" > /dev/null 2>&1';

        return $this->jsonResponse($response, [
            'success' => true,
            'linha_cron' => $linhaCurlModerna,
            'linha_cron_header' => $linhaCurlModerna,
            'linha_cron_query' => $linhaCurlModernaQuery,
            'linha_cli' => $linha,
            'linha_curl_dispatcher' => $linhaCurlModerna,
            'linha_curl_bonificacao' => $linhaCurlBonif,
            'cron_token' => $cronToken,
            'script' => $script,
            'php_sugerido' => $php,
            'edicao_direta' => $execOk,
            'dispatcher_ativo' => $ativo,
            'ultimo_batimento' => $ultimoFormatado,
            'instrucoes' => [
                'Abra o cPanel > Avançado > Trabalhos Cron (Cron Jobs).',
                'NOVA ROTA ESPECIAL E SEGURA (Recomendado): Em "Configurações comuns" escolha "Uma vez por minuto (* * * * *)" e cole a linha moderna do Dispatcher Slim (usando Header ou Token).',
                'Com essa linha única, você NÃO precisa mais de várias entradas no cPanel: todos os horários e ativações são controlados 100% pelo painel do Portal!',
                'As URLs legadas antigas continuam funcionando durante o período de transição para evitar interrupções.',
            ],
        ]);
    }

    // ======================================================================
    // DISPATCHER PÚBLICO SEGURO (Chamado a cada minuto pelo cPanel)
    // Rota: GET|POST /v1/cron/dispatcher (Protegida por CronAuthMiddleware)
    // ======================================================================
    public function dispatcherPublico(Request $request, Response $response): Response
    {
        try {
            $logDir = dirname(__DIR__, 2) . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @file_put_contents($logDir . '/cron_heartbeat.txt', (string)time());

            // Advisory lock para evitar corrida entre instâncias do dispatcher
            $lockStmt = $this->pdo->query("SELECT pg_try_advisory_lock(727001)");
            if (!$lockStmt || !$lockStmt->fetchColumn()) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'status' => 'busy',
                    'message' => 'Dispatcher já em execução em outro processo.',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }

            $agora = new \DateTime('now');
            $jobsAtivos = $this->pdo->query("
                SELECT * FROM cron_jobs
                WHERE ativo = true AND schedule IS NOT NULL AND schedule <> ''
                ORDER BY id
            ")->fetchAll(\PDO::FETCH_ASSOC);

            $executados = [];
            foreach ($jobsAtivos as $jobItem) {
                try {
                    $expr = new \Cron\CronExpression($jobItem['schedule']);
                    $deveRodar = $expr->isDue($agora);

                    if (!$deveRodar) {
                        try {
                            $prevRun = $expr->getPreviousRunDate($agora);
                            $difSeg = $agora->getTimestamp() - $prevRun->getTimestamp();
                            // Tolerância para atraso de até 5 minutos no cPanel
                            if ($difSeg >= 0 && $difSeg <= 300) {
                                $jaRodouRecente = !empty($jobItem['ultima_execucao']) && 
                                    (strtotime($jobItem['ultima_execucao']) >= $prevRun->getTimestamp() - 60);
                                if (!$jaRodouRecente) {
                                    $deveRodar = true;
                                }
                            }
                        } catch (\Throwable $ePrev) {}
                    }

                    if (!$deveRodar) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                // Evita rodar duas vezes no mesmo minuto
                if (!empty($jobItem['ultima_execucao']) && strtotime($jobItem['ultima_execucao']) >= strtotime(date('Y-m-d H:i:00'))) {
                    continue;
                }

                $resJob = $this->executarJobAgendado($jobItem);
                $executados[] = [
                    'job_id' => $jobItem['id'],
                    'comando' => $jobItem['comando'],
                    'nome' => $jobItem['nome'],
                    'status' => !empty($resJob['success']) ? 'sucesso' : 'falha',
                    'duracao' => $resJob['duracao'] ?? 0,
                    'message' => $resJob['message'] ?? ''
                ];
            }

            $this->pdo->query("SELECT pg_advisory_unlock(727001)");

            // Atualiza status do heartbeat
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS cron_dispatcher_status (
                        id INT PRIMARY KEY DEFAULT 1,
                        ultimo_batimento TIMESTAMP NOT NULL DEFAULT NOW(),
                        ip VARCHAR(45),
                        user_agent VARCHAR(255),
                        jobs_ativos INT DEFAULT 0,
                        jobs_disparados INT DEFAULT 0
                    )
                ");
                $stHb = $this->pdo->prepare("
                    INSERT INTO cron_dispatcher_status (id, ultimo_batimento, ip, user_agent, jobs_ativos, jobs_disparados)
                    VALUES (1, NOW(), ?, ?, ?, ?)
                    ON CONFLICT (id) DO UPDATE SET
                        ultimo_batimento = NOW(),
                        ip = EXCLUDED.ip,
                        user_agent = EXCLUDED.user_agent,
                        jobs_ativos = EXCLUDED.jobs_ativos,
                        jobs_disparados = EXCLUDED.jobs_disparados
                ");
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
                $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 250);
                $stHb->execute([$ip, $ua, count($jobsAtivos), count($executados)]);
            } catch (\Throwable $eHb) {}

            return $this->jsonResponse($response, [
                'success' => true,
                'status' => 'completed',
                'timestamp' => date('Y-m-d H:i:s'),
                'jobs_ativos' => count($jobsAtivos),
                'jobs_disparados' => count($executados),
                'executados' => $executados
            ]);
        } catch (\Throwable $e) {
            try { $this->pdo->query("SELECT pg_advisory_unlock(727001)"); } catch (\Throwable $e2) {}
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ======================================================================
    // EXECUÇÃO DIRETA DE COMANDO ESPECÍFICO VIA CRON/WEBHOOK
    // Rota: GET|POST /v1/cron/executar/{comando} (Protegida por CronAuthMiddleware)
    // ======================================================================
    public function executarComandoPublico(Request $request, Response $response, array $args): Response
    {
        $comando = trim((string)($args['comando'] ?? ''));
        if ($comando === '') {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Comando não informado.'], 400);
        }

        $params = $request->getQueryParams();
        $body = json_decode($request->getBody()->getContents(), true) ?? [];
        $mergedParams = array_merge($params, $body);

        $jobStmt = $this->pdo->prepare("SELECT * FROM cron_jobs WHERE comando = ?");
        $jobStmt->execute([$comando]);
        $jobData = $jobStmt->fetch(\PDO::FETCH_ASSOC);

        $jobId = $jobData ? $jobData['id'] : null;
        $usuario = 'CRON_REMOTO';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $execId = $this->criarExecucao($jobId, $usuario, $ip, 'REMOTO_SEGURO');
        $inicio = microtime(true);

        try {
            $resultado = (new CronRotinasController())->executarRotina(
                $comando,
                $mergedParams,
                $usuario,
                $ip
            );
        } catch (\Throwable $e) {
            $resultado = ['success' => false, 'message' => $e->getMessage()];
        }

        $duracao = round(microtime(true) - $inicio, 2);
        $status = !empty($resultado['success']) ? 'sucesso' : 'falha';
        $this->finalizarExecucao($execId, $status, $resultado, $duracao);

        if ($jobId) {
            $this->pdo->prepare("UPDATE cron_jobs SET ultima_execucao = NOW() WHERE id = ?")->execute([$jobId]);
        }

        return $this->jsonResponse($response, [
            'success' => !empty($resultado['success']),
            'comando' => $comando,
            'duracao' => $duracao,
            'timestamp' => date('Y-m-d H:i:s'),
            'resultado' => $resultado
        ]);
    }

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
        
        // Inserir / Sincronizar jobs padrão
        $categorias = $this->pdo->query("SELECT id, nome FROM cron_categorias")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $catEmail = array_search('Email', $categorias) ?: 1;
        $catProc = array_search('Processamento', $categorias) ?: 2;
        $catInteg = array_search('Integração', $categorias) ?: 3;
        $catFin = array_search('Financeiro', $categorias) ?: 4;

        $jobsPadrao = [
            ['Representantes (Alterações)', 'Envia relatório diário de alterações em pedidos para representantes', 'representantes', '0 6 * * *', $catEmail],
            ['Representantes (Mensal)', 'Envia relatório mensal de inadimplência individual por representante', 'representantes_mensal', '0 4 1 * *', $catEmail],
            ['Gestores (Inadimplência)', 'Envia relatório de inadimplência dos gestores no último dia útil do mês', 'gestores', '0 7 * * 1-5', $catEmail],
            ['Histórico KPI Financeiro', 'Registra histórico diário de performance e inadimplência no banco', 'historico_kpi', '26 23 * * *', $catProc],
            ['Clientes Isentos ST', 'Gera planilha Excel e envia relatório de clientes com IE Isento/Nulo', 'clientes_isentos', '0 10 * * *', $catEmail],
            ['Notas Nutrire (GoFind)', 'Envia notas fiscais (marca 8 Nutrire) faturadas para a API GoFind', 'notas_nutrire', '0 20 * * *', $catInteg],
            ['Flex Mínimo Gestor', 'Gera empréstimo automático de verba flex quando saldo do gestor estiver baixo', 'flex_minimo_gestor', '0 8 * * *', $catFin],
            ['Bonificações Flex', 'Processa débito automático de notas bonificadas na API Mercos Flex', 'bonificacoes_flex', '0 22 * * *', $catFin],
            ['Alerta Certificado Digital', 'Verifica vencimento dos certificados digitais NFe das filiais ativas', 'alerta_certificado', '0 8 * * 1', $catProc],
            ['Atualizar Cubo de Vendas', 'Atualiza a materialized view / cubo de vendas no banco de dados', 'refresh_cubo_vendas', '0 */2 * * *', $catProc],
        ];

        $insStmt = $this->pdo->prepare("
            INSERT INTO cron_jobs (nome, descricao, comando, schedule, categoria_id, ativo)
            VALUES (?, ?, ?, ?, ?, false)
            ON CONFLICT (comando) DO NOTHING
        ");

        foreach ($jobsPadrao as $jp) {
            $insStmt->execute([$jp[0], $jp[1], $jp[2], $jp[3], $jp[4]]);
        }
    }
    
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}