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
        $path = $request->getUri()->getPath();
        $ip = $this->getClientIp($request);
        $user = $request->getAttribute('user');
        $userId = (int)($user['uid'] ?? $user['idusuario'] ?? 0);

        // ================================================================
        // Chave do rate limit:
        //   - Usuário autenticado → por user_id (não compartilha com colegas)
        //   - Anônimo            → por IP       (protege contra DDoS)
        // Não inclui a rota — o limite é por cliente, não por endpoint.
        // ================================================================
        $chaveBase = $userId > 0 ? "user:{$userId}" : "ip:{$ip}";
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