<?php
// ==========================================================================
// ERROR CATCHER GLOBAL — Nutricional
// ==========================================================================
// Regras:
//   1. Sempre loga o erro em error_log.
//   2. Se for requisição de API/JSON (?api_route=, /v1/, Accept: json, XHR)
//      → responde com JSON.
//   3. Se for requisição HTML → NUNCA injeta nada. Deixa o PHP seguir.
//
// Motivo: injetar JSON no meio de HTML quebra a página inteira (raiz /).
// ==========================================================================

if (!function_exists('_ec_isJsonRequest')) {
    function _ec_isJsonRequest(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // API v1
        if (strpos($uri, '/v1/') !== false) return true;
        if (strpos($uri, '/api/') !== false) return true;

        // Gateway api_route
        if (isset($_GET['api_route'])) return true;

        // Cron autenticado (sempre responde JSON)
        if (isset($_GET['key']) && isset($_GET['acao'])) return true;

        // Header Accept
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) return true;

        // AJAX
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') return true;

        // Content-Type de entrada
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) return true;

        return false;
    }
}

if (!function_exists('_ec_responderErro')) {
    function _ec_responderErro(): void {
        if (headers_sent()) {
            return;
        }

        if (_ec_isJsonRequest()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Erro interno do servidor'
            ]);
            exit;
        }

        // HTML: não injeta nada. Loga e segue.
    }
}

// Handler de warnings/notices
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("[error-catcher] [$errno] $errstr em $errfile:$errline");
    // Retorna false para o PHP seguir o fluxo normal (o warning é suprimido
    // do output em produção porque display_errors = 0)
    return false;
});

// Handler de exceções não capturadas
set_exception_handler(function ($e) {
    error_log("[error-catcher] Exceção: " . $e->getMessage()
        . " em " . $e->getFile() . ":" . $e->getLine());

    if (_ec_isJsonRequest()) {
        _ec_responderErro();
    }
    // HTML: não injeta nada — deixa o PHP imprimir erro padrão (não há mais)
    // Se quiser uma página de erro dedicada, redirecione aqui.
});

// Handler de erros fatais (parse, core, compile)
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;

    $tiposFatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'], $tiposFatais, true)) return;

    error_log("[error-catcher] FATAL: {$err['message']} em {$err['file']}:{$err['line']}");

    if (_ec_isJsonRequest()) {
        _ec_responderErro();
    }
    // HTML: apenas log
});