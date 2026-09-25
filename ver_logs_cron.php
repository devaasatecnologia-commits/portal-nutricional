<?php
echo "<h2>📋 Logs do Motor de Crons</h2>";
echo "<pre>";

$logFile = __DIR__ . '/cron_motor.log';

if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    echo htmlspecialchars(implode('', $lastLines));
} else {
    echo "Arquivo de log não encontrado. Aguarde a primeira execução...\n";
    echo "Caminho: $logFile\n";
    
    // Verificar se a pasta logs existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        echo "\n❌ Pasta de logs não existe. Execute primeiro: mkdir -p $logDir && chmod 755 $logDir";
    }
}

echo "</pre>";

echo "<h2>📊 Execuções Agendadas no Banco</h2>";
echo "<pre>";

try {
    require_once __DIR__ . '/api.nutricionalbr.com/v1/bootstrap/app.php';
    $pdo = \getPDO();
    
    $stmt = $pdo->query("
        SELECT e.id, j.nome, e.status, e.iniciado_em, e.duracao_segundos, e.origem
        FROM cron_execucoes e
        JOIN cron_jobs j ON j.id = e.job_id
        WHERE e.origem = 'AGENDADO'
        ORDER BY e.iniciado_em DESC
        LIMIT 10
    ");
    
    $execucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($execucoes) {
        foreach ($execucoes as $e) {
            echo sprintf(
                "[%s] %s - %s - %s (%.2fs)\n",
                $e['iniciado_em'],
                $e['nome'],
                $e['status'],
                $e['origem'],
                $e['duracao_segundos']
            );
        }
    } else {
        echo "Nenhuma execução agendada ainda.\n";
    }
    
} catch (Exception $e) {
    echo "Erro ao consultar banco: " . $e->getMessage();
}

echo "</pre>";
?>