<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class RateLimitMiddleware
{
    private $pdo;
    private $maxRequests;
    private $timeWindow; // em segundos
    private $whitelist = [
        '/v1/auth/login' => 5,      // 5 tentativas
        '/v1/auth/alterar-senha' => 3,
        '/v1/marketing/clientes' => 100,
        '/v1/financeiro/dashboard' => 50,
        'default' => 60              // 60 requisições por padrão
    ];

    public function __construct(int $maxRequests = 60, int $timeWindow = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->pdo = \getPDO();
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        
        // Obter IP do cliente
        $ip = $this->getClientIp($request);
        
        // Obter usuário logado (se existir)
        $user = $request->getAttribute('user');
        $userId = $user['uid'] ?? 0;
        
        // Identificador único do cliente (IP + rota + método + userId)
        $identifier = md5($ip . ':' . $path . ':' . $method . ':' . $userId);
        
        // Verificar se há limite específico para esta rota
        $limit = $this->whitelist[$path] ?? $this->whitelist['default'];
        
        // Limpar registros antigos (mais de 1 hora)
        $this->cleanOldRecords();
        
        // Contar requisições
        $current = $this->getRequestCount($identifier, $this->timeWindow);
        
        if ($current >= $limit) {
            // Limite excedido
            $retryAfter = $this->getRetryAfter($identifier);
            return $this->rateLimitExceeded($response ?? new \Slim\Psr7\Response(), $retryAfter);
        }
        
        // Registrar requisição
        $this->incrementRequestCount($identifier);
        
        // Processar requisição
        $response = $handler->handle($request);
        
        // Adicionar headers de rate limit
        return $response
            ->withHeader('X-RateLimit-Limit', $limit)
            ->withHeader('X-RateLimit-Remaining', max(0, $limit - $current - 1))
            ->withHeader('X-RateLimit-Reset', time() + $this->getResetTime($identifier));
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($serverParams[$header]) && !empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                return trim($ips[0]);
            }
        }
        
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function getRequestCount(string $identifier, int $timeWindow): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(requests) as total
                FROM rate_limit
                WHERE identifier = :identifier
                AND last_request > NOW() - INTERVAL '{$timeWindow} seconds'
            ");
            $stmt->execute(['identifier' => $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (\Exception $e) {
            error_log('Erro ao contar requisições: ' . $e->getMessage());
            return 0;
        }
    }

    private function incrementRequestCount(string $identifier): void
    {
        try {
            // Verificar se já existe registro recente
            $stmt = $this->pdo->prepare("
                SELECT id, requests FROM rate_limit
                WHERE identifier = :identifier
                AND last_request > NOW() - INTERVAL '1 minute'
                ORDER BY last_request DESC
                LIMIT 1
            ");
            $stmt->execute(['identifier' => $identifier]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Atualizar existente
                $stmt = $this->pdo->prepare("
                    UPDATE rate_limit 
                    SET requests = requests + 1,
                        last_request = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $existing['id']]);
            } else {
                // Criar novo registro
                $stmt = $this->pdo->prepare("
                    INSERT INTO rate_limit (identifier, requests, first_request, last_request)
                    VALUES (:identifier, 1, NOW(), NOW())
                ");
                $stmt->execute(['identifier' => $identifier]);
            }
        } catch (\Exception $e) {
            error_log('Erro ao registrar requisição: ' . $e->getMessage());
        }
    }

    private function getRetryAfter(string $identifier): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT EXTRACT(EPOCH FROM (last_request + INTERVAL '1 minute' - NOW())) as retry_after
                FROM rate_limit
                WHERE identifier = :identifier
                ORDER BY last_request DESC
                LIMIT 1
            ");
            $stmt->execute(['identifier' => $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return max(1, (int)($result['retry_after'] ?? 60));
        } catch (\Exception $e) {
            return 60;
        }
    }

    private function getResetTime(string $identifier): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT EXTRACT(EPOCH FROM (last_request + INTERVAL '1 minute')) as reset_time
                FROM rate_limit
                WHERE identifier = :identifier
                ORDER BY last_request DESC
                LIMIT 1
            ");
            $stmt->execute(['identifier' => $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($result['reset_time'] ?? time() + 60);
        } catch (\Exception $e) {
            return time() + 60;
        }
    }

    private function cleanOldRecords(): void
    {
        try {
            // Limpar registros com mais de 1 hora
            $this->pdo->exec("
                DELETE FROM rate_limit 
                WHERE last_request < NOW() - INTERVAL '1 hour'
            ");
        } catch (\Exception $e) {
            error_log('Erro ao limpar rate_limit: ' . $e->getMessage());
        }
    }

    private function rateLimitExceeded(Response $response, int $retryAfter): Response
    {
        $payload = json_encode([
            'error' => 'Muitas requisições. Tente novamente em ' . $retryAfter . ' segundos.',
            'retry_after' => $retryAfter,
            'code' => 429
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', $retryAfter);
    }
}