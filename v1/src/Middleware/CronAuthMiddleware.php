<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware para autenticação segura de chamadas de Cron via cPanel ou Webhook.
 * 
 * Aceita autenticação via:
 * 1. Header HTTP: X-Cron-Token: <token>
 * 2. Header HTTP: Authorization: Bearer <token>
 * 3. Query string: ?token=<token> ou ?key=<token>
 * 
 * Compara com a chave CRON_TOKEN configurada no .env com proteção contra Timing Attacks.
 */
class CronAuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $tokenEsperado = (string)($_ENV['CRON_TOKEN'] ?? (defined('CRON_TOKEN') ? CRON_TOKEN : ''));
        if ($tokenEsperado === '') {
            $tokenEsperado = (string)($_ENV['API_TOKEN'] ?? (defined('API_TOKEN') ? API_TOKEN : ''));
        }

        $tokenFornecido = '';

        // 1. Header X-Cron-Token
        if ($request->hasHeader('X-Cron-Token')) {
            $tokenFornecido = trim($request->getHeaderLine('X-Cron-Token'));
        }
        // 2. Header Authorization: Bearer <token>
        elseif ($request->hasHeader('Authorization')) {
            $auth = trim($request->getHeaderLine('Authorization'));
            if (preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
                $tokenFornecido = $matches[1];
            }
        }

        // 3. Query String (?token=... ou ?key=...)
        if ($tokenFornecido === '') {
            $params = $request->getQueryParams();
            $tokenFornecido = trim((string)($params['token'] ?? $params['key'] ?? ''));
        }

        // Validação com hash_equals (timing-safe)
        if ($tokenEsperado === '' || $tokenFornecido === '' || !hash_equals($tokenEsperado, $tokenFornecido)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Acesso negado: Cron Token ausente ou inválido.',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE));

            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withHeader('Cache-Control', 'no-store');
        }

        return $handler->handle($request);
    }
}

