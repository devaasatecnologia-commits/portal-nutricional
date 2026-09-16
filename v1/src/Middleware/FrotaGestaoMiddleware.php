<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class FrotaGestaoMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user') ?? [];
        $permissoes = $user['permissoes'] ?? [];
        $autorizado = (bool)($user['is_admin'] ?? false)
            || in_array('admin', $permissoes, true)
            || in_array('frota', $permissoes, true)
            || in_array('gestao-cargas', $permissoes, true);

        if ($autorizado) {
            return $handler->handle($request);
        }

        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Acesso não autorizado para gestão de frota'
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}