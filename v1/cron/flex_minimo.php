<?php
// Cron do cPanel (a cada minuto):
//   * * * * * /usr/local/bin/php /home/USUARIO/caminho/v1/cron/flex_minimo.php
// Use --simular para apenas consultar, sem emprestar.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/app.php';

// Historico de disponibilidade: um teste por filial a cada rodada (nao depende de haver gestor ativo).
$monitor = new Nutricional\Services\FlexApiMonitor();
foreach ([1, 6] as $filial) {
    $s = $monitor->testar($filial, 'cron');
    fwrite(STDOUT, date('Y-m-d H:i:s') . " api f$filial " . ($s['ok'] ? 'ok' : 'FORA: ' . $s['erro']) . " {$s['ms']}ms" . PHP_EOL);
}
if ((int)date('i') === 0 && (int)date('G') === 3) {
    $monitor->limpar(30);
}

$simular = in_array('--simular', $argv ?? [], true);
$resultado = (new Nutricional\Services\FlexMinimoService())->executar($simular);
foreach ($resultado as $r) {
    fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL);
}
