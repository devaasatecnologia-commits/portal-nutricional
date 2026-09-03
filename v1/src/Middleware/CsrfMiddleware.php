<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class CsrfMiddleware
{
    private $excludedRoutes = [
        '/v1/auth/login',
        '/v1/auth/alterar-senha',
        '/v1/cron/executar'
    ];

public function __invoke(Request $request, RequestHandler $handler): Response
{
    $method = $request->getMethod();
    $path = $request->getUri()->getPath();

    // Apenas para m├®todos que modificam dados
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {

        // Verificar se ├® rota exclu├¡da
        foreach ($this->excludedRoutes as $excluded) {
            if (strpos($path, $excluded) === 0) {
                return $handler->handle($request);
            }
        }

        // Obter token do header
        $tokenHeader = $request->getHeaderLine('X-CSRF-Token');

        // Obter token do usu├írio (baseado no JWT)
        $user = $request->getAttribute('user');
        $expectedToken = $user ? md5($user['uid'] . date('Y-m-d')) : null;
        
        // ✅ ADICIONAR LOGS
        error_log("=== CSRF DEBUG ===");
        error_log("UID: " . ($user['uid'] ?? 'null'));
        error_log("Data: " . date('Y-m-d'));
        error_log("Token recebido: " . $tokenHeader);
        error_log("Token esperado: " . $expectedToken);
        error_log("Token match: " . ($tokenHeader === $expectedToken ? 'SIM' : 'NÃO'));

        if (!$tokenHeader || $tokenHeader !== $expectedToken) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'CSRF token inv├ílido']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
    }

    return $handler->handle($request);
}
}