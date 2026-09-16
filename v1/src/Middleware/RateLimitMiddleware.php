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

    /**
     * Limites específicos por rota.
     * A chave é o path exato. O default é aplicado a qualquer rota não listada.
     */
    private $limitesPorRota = [
        '/v1/auth/login'            => 5,
        '/v1/auth/alterar-senha'    => 3,
        '/v1/marketing/clientes'    => 100,
        '/v1/financeiro/dashboard'  => 50,
        'default'                   => 300,   // 300 req/min por padrão (antes era 60)
    ];

    public function __construct(int $maxRequests = 300, int $timeWindow = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->pdo = \getPDO();
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = rtrim($request->getUri()->getPath(), '/') ?: '/';
        $ip = $this->getClientIp($request);

        if ($path === '/v1/auth/login' && strtoupper($request->getMethod()) === 'POST') {
            return $this->handleLogin($request, $handler, $ip);
        }

        $user = $request->getAttribute('user');
        $userId = (int)($user['uid'] ?? $user['idusuario'] ?? 0);

        // ================================================================
        // Chave do rate limit:
        //   - Usuário autenticado → por user_id ou token
        //   - Anônimo             → por IP e rota
        // ================================================================
        $authorization = $request->getHeaderLine('Authorization');
        if ($userId > 0) {
            $chaveBase = "user:{$userId}";
        } elseif (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $chaveBase = 'token:' . hash('sha256', $matches[1]);
        } else {
            $chaveBase = "ip:{$ip}:path:{$path}";
        }
        $identifier = md5($chaveBase);

        // Limite específico por rota, senão usa o padrão
        $limit = $this->limitesPorRota[$path] ?? $this->limitesPorRota['default'];

        // Limpa registros antigos em ~1% das requests
        $this->cleanOldRecords();

        $current = $this->getRequestCount($identifier, $this->timeWindow);

        if ($current >= $limit) {
            $retryAfter = $this->getRetryAfter($identifier);
            $response = new \Slim\Psr7\Response();
            return $this->rateLimitExceeded($response, $retryAfter);
        }

        $this->incrementRequestCount($identifier);

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$limit)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $limit - $current - 1))
            ->withHeader('X-RateLimit-Reset', (string)(time() + $this->getResetTime($identifier)));
    }

    private function handleLogin(Request $request, RequestHandler $handler, string $ip): Response
    {
        $rawBody = (string)$request->getBody();
        $request->getBody()->rewind();
        $input = json_decode($rawBody, true) ?: [];
        $username = mb_strtolower(trim((string)($input['user'] ?? '')), 'UTF-8');
        $usernameKey = $username !== '' ? hash('sha256', $username) : 'vazio';

        $userIdentifier = md5("login:ip:{$ip}:user:{$usernameKey}");
        $ipIdentifier = md5("login:ip:{$ip}:all");
        $userLimit = $this->limitesPorRota['/v1/auth/login'];
        $ipLimit = 30;

        $userCount = $this->getRequestCount($userIdentifier, $this->timeWindow);
        $ipCount = $this->getRequestCount($ipIdentifier, $this->timeWindow);

        if ($userCount >= $userLimit) {
            return $this->rateLimitExceeded(new \Slim\Psr7\Response(), $this->getRetryAfter($userIdentifier));
        }
        if ($ipCount >= $ipLimit) {
            return $this->rateLimitExceeded(new \Slim\Psr7\Response(), $this->getRetryAfter($ipIdentifier));
        }

        $response = $handler->handle($request);

        if (in_array($response->getStatusCode(), [400, 401, 403], true)) {
            $this->incrementRequestCount($userIdentifier);
            $this->incrementRequestCount($ipIdentifier);
            $userCount++;
            $ipCount++;
        } elseif ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->clearRequestCount($userIdentifier);
            $userCount = 0;
        }

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$userLimit)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $userLimit - $userCount))
            ->withHeader('X-RateLimit-Reset', (string)(time() + $this->getResetTime($userIdentifier)));
    }

    private function clearRequestCount(string $identifier): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM rate_limit WHERE identifier = :identifier');
            $stmt->execute(['identifier' => $identifier]);
        } catch (\Exception $e) {
            error_log('[RateLimit] Erro ao limpar contador: ' . $e->getMessage());
        }
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        $headers = [
            'HTTP_CF_CONNECTING_IP',
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
                AND last_request > NOW() - make_interval(secs => :tw)
            ");
            $stmt->execute(['identifier' => $identifier, 'tw' => $timeWindow]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (\Exception $e) {
            error_log('[RateLimit] Erro ao contar: ' . $e->getMessage());
            return 0;
        }
    }

    private function incrementRequestCount(string $identifier): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM rate_limit
                WHERE identifier = :identifier
                AND last_request > NOW() - INTERVAL '1 minute'
                ORDER BY last_request DESC
                LIMIT 1
            ");
            $stmt->execute(['identifier' => $identifier]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $this->pdo->prepare("
                    UPDATE rate_limit 
                    SET requests = requests + 1,
                        last_request = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $existing['id']]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO rate_limit (identifier, requests, first_request, last_request)
                    VALUES (:identifier, 1, NOW(), NOW())
                ");
                $stmt->execute(['identifier' => $identifier]);
            }
        } catch (\Exception $e) {
            error_log('[RateLimit] Erro ao registrar: ' . $e->getMessage());
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
        // Roda em ~1% das requests para não virar gargalo em cada hit
        if (random_int(1, 100) > 1) {
            return;
        }

        try {
            $this->pdo->exec("
                DELETE FROM rate_limit 
                WHERE last_request < NOW() - INTERVAL '1 hour'
            ");
        } catch (\Exception $e) {
            error_log('[RateLimit] Erro ao limpar: ' . $e->getMessage());
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
            ->withHeader('Retry-After', (string)$retryAfter);
    }
}