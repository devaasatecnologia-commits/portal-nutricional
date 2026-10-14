<?php
// Cron do cPanel (a cada minuto):
//   * * * * * /usr/local/bin/php /home/USUARIO/caminho/v1/cron/flex_minimo.php
// Use --simular para apenas consultar, sem emprestar.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/app.php';

$simular = in_array('--simular', $argv ?? [], true);
$resultado = (new Nutricional\Services\FlexMinimoService())->executar($simular);
foreach ($resultado as $r) {
    fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL);
}
