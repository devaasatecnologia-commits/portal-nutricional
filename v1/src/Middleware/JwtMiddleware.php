<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtMiddleware
{
    private $pdo;
    private $jwtSecret;
    private $publicRoutes = [
        '/v1/auth/login',
        '/v1/sistema/modulos-setores',
        '/ping'
    ];

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->jwtSecret = CHAVE_SECRETA;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();
        
        // Rotas públicas não precisam de token
        if ($this->isPublicRoute($path)) {
            return $handler->handle($request);
        }

        // Extrair token do header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Token não fornecido');
        }

        $token = $matches[1];

        try {
            // Decodificar token
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // ✅ VERIFICAR BLACKLIST PRIMEIRO
            $jti = $decoded->jti ?? null;
            $tokenHash = hash('sha256', $token);
            
            if ($jti) {
               $stmt = $this->pdo->prepare("
                SELECT 1 FROM token_blacklist 
                WHERE (token_hash = :token_hash OR jti = :jti)
                AND expiracao > NOW()
                LIMIT 1
                ");
               $stmt->execute([
                ':token_hash' => $tokenHash,
                ':jti' => $jti
            ]);
               
               if ($stmt->fetchColumn()) {
                return $this->unauthorized('Token revogado. Faça login novamente.');
            }
        }
        
            // Extrair dados do usuário
        $uid = $decoded->uid ?? 0;
        $idusuario = $decoded->idusuario ?? 0;
        $username = $decoded->username ?? '';
        $permissoes = $decoded->permissoes ?? [];
        
        if ($uid === 0) {
            return $this->unauthorized('Token inválido: usuário não identificado');
        }
        
            // Verificar se o usuário ainda está ativo no banco
        $stmt = $this->pdo->prepare("
            SELECT idusuario, inativo, username 
            FROM usuario 
            WHERE idcliforemp = :uid
            ");
        $stmt->execute(['uid' => $uid]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            return $this->unauthorized('Usuário não encontrado');
        }
        
        if ($usuario['inativo'] === 'S') {
            return $this->unauthorized('Usuário inativo');
        }
        
            // Verificar se o usuário é admin
        $isAdmin = $this->isAdminUser($idusuario);
        
            // Adicionar dados do usuário no request
        $request = $request->withAttribute('user', [
            'uid' => $uid,
            'idusuario' => $idusuario,
            'username' => $username,
            'permissoes' => $permissoes,
            'is_admin' => $isAdmin,
                'jti' => $jti  // ✅ Adicionar JTI nos atributos
            ]);
        
        if ($isAdmin) {
            $request = $request->withAttribute('is_admin', true);
        }
        
        return $handler->handle($request);
        
    } catch (\Firebase\JWT\ExpiredException $e) {
        return $this->unauthorized('Token expirado', 401);
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        return $this->unauthorized('Assinatura do token inválida', 401);
    } catch (\Exception $e) {
        error_log('Erro no JwtMiddleware: ' . $e->getMessage());
        return $this->unauthorized('Token inválido', 401);
    }
}

private function isPublicRoute(string $path): bool
{
    foreach ($this->publicRoutes as $route) {
        if (strpos($path, $route) === 0) {
            return true;
        }
    }
    return false;
}

private function isAdminUser(int $idusuario): bool
{
    try {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM usuarios_admin 
            WHERE idusuario = :idusuario AND ativo = true
            ");
        $stmt->execute(['idusuario' => $idusuario]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (\Exception $e) {
        return false;
    }
}

private function unauthorized(string $message, int $code = 401): Response
{
    $response = new \Slim\Psr7\Response();
    $response->getBody()->write(json_encode([
        'error' => $message,
        'code' => $code
    ]));
    return $response
    ->withStatus($code)
    ->withHeader('Content-Type', 'application/json');
}
}