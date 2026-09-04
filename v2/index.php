<?php
// Ponte de compatibilidade: V2 usa temporariamente os contratos estáveis da V1.
$_SERVER['REQUEST_URI'] = preg_replace('#/v2(?=/|$)#', '/v1', $_SERVER['REQUEST_URI'] ?? '/v2');
require __DIR__ . '/../v1/public/index.php';