<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class ModulePermissionMiddleware
{
    /** @var string[] */
    private array $modules;
    private string $module;

    /** @param string|string[] $module basta ter um dos módulos informados */
    public function __construct($module)
    {
        $this->modules = (array)$module;
        $this->module = implode('|', $this->modules);
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user') ?? [];
        $permissions = $user['permissoes'] ?? [];
        $authorized = (bool)($user['is_admin'] ?? false)
            || in_array('admin', $permissions, true)
            || array_intersect($this->modules, $permissions) !== [];

        if ($authorized) {
            return $handler->handle($request);
        }

        // 🔥 Fallback em banco caso a permissão tenha sido concedida após a emissão do token JWT
        if (!empty($user['idusuario'])) {
            try {
                $pdo = \getPDO();
                $placeholders = implode(',', array_fill(0, count($this->modules), '?'));
                $stmt = $pdo->prepare("
                    SELECT 1 
                    FROM usuario_permissoes up
                    JOIN sistema_modulos sm ON sm.id = up.idmodulo
                    WHERE up.idusuario = ? AND sm.slug IN ($placeholders) AND sm.ativo = true
                    LIMIT 1
                ");
                $stmt->execute(array_merge([$user['idusuario']], $this->modules));
                if ($stmt->fetchColumn()) {
                    return $handler->handle($request);
                }
            } catch (\Throwable $e) {
                // Silencia e segue para bloqueio 403
            }
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
