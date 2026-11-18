<?php
$base = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
header('Location: ' . $base . '/portal/modules/portal-cliente.php', true, 302);
exit;
