<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class ModulePermissionMiddleware
{
    private $module;

    public function __construct(string $module)
    {
        $this->module = $module;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user') ?? [];
        $permissions = $user['permissoes'] ?? [];
        $authorized = (bool)($user['is_admin'] ?? false)
            || in_array('admin', $permissions, true)
            || in_array($this->module, $permissions, true);

        if ($authorized) {
            return $handler->handle($request);
        }

        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Acesso não autorizado ao módulo ' . $this->module,
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}