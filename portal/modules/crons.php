<?php
// Redirecionamento seguro para a interface administrativa do Motor de Crons
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
header('Location: ' . $appBase . '/portal/modules/admin/crons.php');
exit;
