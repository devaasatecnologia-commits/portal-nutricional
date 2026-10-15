<?php
// Dispatcher dos jobs cadastrados em Admin > Motor de Crons.
// Agende UMA unica vez no cPanel (Tarefas Cron), a cada minuto:
//   * * * * * /usr/local/bin/php /home/USUARIO/caminho/v1/cron/run.php >> /home/USUARIO/caminho/logs/cron_run.log 2>&1
// A linha exata para o seu servidor aparece no painel (botao "Como agendar no servidor").
// Depois disso, criar/editar/desativar jobs e horarios e feito pelo Admin.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/app.php';

use Cron\CronExpression;
use Nutricional\Controllers\CronController;

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logDir . '/cron_heartbeat.txt', (string)time());

$pdo = getPDO();

// Evita duas instancias simultaneas (ex.: job demorado + proximo minuto)
if (!$pdo->query("SELECT pg_try_advisory_lock(727001)")->fetchColumn()) {
    fwrite(STDOUT, date('Y-m-d H:i:s') . " dispatcher ja em execucao\n");
    exit(0);
}

$controller = new CronController();
$agora = new DateTime('now');

$jobs = $pdo->query("
    SELECT * FROM cron_jobs
    WHERE ativo = true AND schedule IS NOT NULL AND schedule <> ''
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($jobs as $job) {
    try {
        if (!(new CronExpression($job['schedule']))->isDue($agora)) {
            continue;
        }
        // Nao repete o job se ja rodou neste mesmo minuto
        if (!empty($job['ultima_execucao']) && date('Y-m-d H:i', strtotime($job['ultima_execucao'])) === $agora->format('Y-m-d H:i')) {
            continue;
        }
        $r = $controller->executarJobAgendado($job);
        fwrite(STDOUT, sprintf(
            "%s job=%s %s %ss %s\n",
            date('Y-m-d H:i:s'),
            $job['comando'],
            !empty($r['success']) ? 'OK' : 'FALHA',
            $r['duracao'] ?? '?',
            $r['message'] ?? ''
        ));
    } catch (Throwable $e) {
        fwrite(STDERR, date('Y-m-d H:i:s') . " job={$job['comando']} erro: " . $e->getMessage() . "\n");
    }
}

$pdo->query("SELECT pg_advisory_unlock(727001)");
