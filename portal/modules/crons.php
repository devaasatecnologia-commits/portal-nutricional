<?php
// Redirecionamento seguro e imediato para a interface oficial do Motor de Crons no Admin
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$destino = $appBase . '/portal/modules/admin/crons.php';

if (!headers_sent()) {
    header('Location: ' . $destino, true, 302);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($destino) ?>">
    <title>Redirecionando | Motor de Crons Nutricional</title>
    <script>
        window.location.replace(<?= json_encode($destino) ?>);
    </script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #334155; }
        .box { text-align: center; padding: 2rem; background: white; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        a { color: #4f46e5; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <p>Redirecionando para o <strong>Motor de Crons Administrativo</strong>...</p>
        <p><a href="<?= htmlspecialchars($destino) ?>">Clique aqui se não for redirecionado automaticamente</a></p>
    </div>
</body>
</html>
<?php exit; ?>
