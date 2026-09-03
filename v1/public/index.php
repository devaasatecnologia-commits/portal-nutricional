<?php

// Carregar configuração do app
$app = require __DIR__ . '/../bootstrap/app.php';

// Carregar rotas
require_once __DIR__ . '/../routes/api.php';

// Executar app
$app->run();