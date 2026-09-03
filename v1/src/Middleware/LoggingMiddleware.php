<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class LoggingMiddleware
{
    private $logger;
    private $startTime;

    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $this->startTime = microtime(true);
        
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $ip = $this->getClientIp($request);
        
        // Log de requisição (apenas em desenvolvimento ou se logger disponível)
        if ($this->logger && ($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $this->logger->info("Request iniciada", [
                'method' => $method,
                'path' => $path,
                'ip' => $ip,
                'user_agent' => $request->getHeaderLine('User-Agent'),
            ]);
        } else {
            // Fallback para log em arquivo
            $logDir = __DIR__ . '/../../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $log = sprintf(
                "[%s] REQ %s %s - IP: %s\n",
                date('Y-m-d H:i:s'),
                $method,
                $path,
                $ip
            );
            @file_put_contents($logDir . '/requests.log', $log, FILE_APPEND);
        }
        
        // Processar requisição
        $response = $handler->handle($request);
        
        // Calcular tempo de execução
        $duration = (microtime(true) - $this->startTime) * 1000;
        $statusCode = $response->getStatusCode();
        
        // Log de resposta
        $logLevel = $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'warning' : 'info');
        
        if ($this->logger) {
            $this->logger->$logLevel("Request finalizada", [
                'method' => $method,
                'path' => $path,
                'status' => $statusCode,
                'duration_ms' => round($duration, 2),
                'ip' => $ip,
            ]);
        } else {
            // Fallback para log em arquivo
            $logDir = __DIR__ . '/../../../logs';
            $logFile = $statusCode >= 400 ? 'errors.log' : 'access.log';
            
            $log = sprintf(
                "[%s] %s %s - %d (%dms) - IP: %s\n",
                date('Y-m-d H:i:s'),
                $method,
                $path,
                $statusCode,
                round($duration, 2),
                $ip
            );
            @file_put_contents($logDir . '/' . $logFile, $log, FILE_APPEND);
        }
        
        // Adicionar header de tempo de resposta
        return $response->withHeader('X-Response-Time', round($duration, 2) . 'ms');
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        // Verificar headers de proxy
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
}