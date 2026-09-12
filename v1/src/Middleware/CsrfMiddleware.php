<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class CsrfMiddleware
{
    /**
     * Rotas exatas isentas de CSRF.
     */
    private $excludedRoutes = [
        '/v1/auth/login',
        '/v1/auth/alterar-senha',
        '/v1/cron/executar',
        '/v1/perfil',                // perfil do usuário (upload de foto, dados, etc)
        '/v1/admin/upload-foto',     // upload de foto de perfil
    ];

    /**
     * Prefixos de rota isentos de CSRF.
     * O app do motorista (frota) usa JWT no header Authorization,
     * não cookie de sessão, então não é vulnerável a CSRF.
     */
    private $excludedPrefixes = [
        '/v1/frota/',                // módulo Frota inteiro (embarques, entregas, motoristas, cobli, acerto, importar)
    ];

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Apenas para métodos que modificam dados
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {

            // Rota exata isenta
            foreach ($this->excludedRoutes as $excluded) {
                if (strpos($path, $excluded) === 0) {
                    return $handler->handle($request);
                }
            }

            // Prefixo isento
            foreach ($this->excludedPrefixes as $prefix) {
                if (strpos($path, $prefix) === 0) {
                    return $handler->handle($request);
                }
            }

            // Validar CSRF
            $tokenHeader = $request->getHeaderLine('X-CSRF-Token');

            $user = $request->getAttribute('user');
            $expectedToken = $user ? md5($user['uid'] . date('Y-m-d')) : null;
            $fallbackToken = $user ? substr(base64_encode($user['uid'] . date('Y-m-d')), 0, 32) : null;

            if (!$tokenHeader || ($tokenHeader !== $expectedToken && $tokenHeader !== $fallbackToken)) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['error' => 'CSRF token inválido']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }
}