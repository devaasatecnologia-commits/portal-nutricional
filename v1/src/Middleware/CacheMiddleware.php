<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class CacheMiddleware
{
    private $cache;
    private $ttl;
    
    // Rotas que NÃO devem ser cacheadas
    private $noCacheRoutes = [
        '/v1/auth/login',
        '/v1/auth/alterar-senha',
        '/v1/marketing/leads',
        '/v1/marketing/clientes',
        '/v1/carregamento/confirmar',
        '/v1/separacao/confirmar',
    ];
    
    // Métodos que NÃO devem ser cacheados
    private $noCacheMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];

    public function __construct($cache, array $ttl = [])
    {
        $this->cache = $cache;
        $this->ttl = $ttl['short'] ?? 300;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        // Verificar se deve pular cache
        if ($this->shouldSkipCache($method, $path)) {
            return $handler->handle($request);
        }
        
        // Se cache não está disponível
        if (!$this->cache['available']) {
            return $handler->handle($request);
        }
        
        // Gerar chave de cache baseada na URL + query params + user ID
        $user = $request->getAttribute('user');
        $userId = $user['uid'] ?? 0;
        $queryString = $request->getUri()->getQuery();
        $cacheKey = 'route:' . md5($path . '?' . $queryString . ':' . $userId);
        
        // Tentar obter do cache
        $cachedResponse = $this->cache['cache']->get($cacheKey);
        
        if ($cachedResponse !== null) {
            // Retornar resposta do cache
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write($cachedResponse);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Cache', 'HIT')
                ->withHeader('X-Cache-TTL', $this->ttl);
        }
        
        // Processar requisição
        $response = $handler->handle($request);
        
        // Apenas cachear respostas bem-sucedidas
        if ($response->getStatusCode() === 200) {
            $body = $response->getBody();
            $body->rewind();
            $content = $body->getContents();
            
            // Salvar no cache
            $this->cache['cache']->set($cacheKey, $content, $this->ttl);
        }
        
        return $response->withHeader('X-Cache', 'MISS');
    }

    private function shouldSkipCache(string $method, string $path): bool
    {
        // Métodos de escrita não devem ser cacheados
        if (in_array($method, $this->noCacheMethods)) {
            return true;
        }
        
        // Rotas específicas não devem ser cacheadas
        foreach ($this->noCacheRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return true;
            }
        }
        
        return false;
    }
}