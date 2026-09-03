<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BlacklistMiddleware
{
    private $pdo;
    private $jwtSecret;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->jwtSecret = CHAVE_SECRETA;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $handler->handle($request);
        }
        
        $token = $matches[1];
        
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $jti = $decoded->jti ?? null;
            
            if ($jti) {
                // Verificar se token está na blacklist (PostgreSQL)
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM token_blacklist 
                    WHERE jti = :jti AND expiracao > NOW()
                    LIMIT 1
                ");
                $stmt->execute(['jti' => $jti]);
                
                if ($stmt->fetchColumn()) {
                    error_log("🚫 Token revogado detectado - JTI: {$jti}");
                    return $this->unauthorized('Token revogado. Faça login novamente.');
                }
            }
            
        } catch (\Exception $e) {
            error_log('BlacklistMiddleware erro: ' . $e->getMessage());
        }
        
        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new \Slim\Psr7\Response();
        $payload = json_encode(['error' => $message, 'code' => 401]);
        $response->getBody()->write($payload);
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}