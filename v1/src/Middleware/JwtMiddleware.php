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

        // 🔥 DEBUG TEMPORÁRIO (Bloco 7.A.4 - investigação 401)
        $this->debugLog('INÍCIO', [
            'path' => $path,
            'method' => $request->getMethod(),
            'has_auth_header' => $request->hasHeader('Authorization'),
            'user_agent' => $request->getHeaderLine('User-Agent'),
        ]);

        // Rotas públicas não precisam de token
        if ($this->isPublicRoute($path)) {
            $this->debugLog('ROTA PÚBLICA - pulando JWT', ['path' => $path]);
            return $handler->handle($request);
        }

        // Extrair token do header Authorization
        $authHeader = $request->getHeaderLine('Authorization');

        $this->debugLog('HEADER AUTHORIZATION', [
            'tamanho' => strlen($authHeader),
            'inicio' => substr($authHeader, 0, 30),
        ]);

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->debugLog('ERRO: Header Authorization ausente ou malformado', []);
            return $this->unauthorized('Token não fornecido');
        }

        $token = $matches[1];

        $this->debugLog('TOKEN EXTRAÍDO', [
            'tamanho' => strlen($token),
            'partes' => count(explode('.', $token)),
        ]);

        try {
            // Decodificar token
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            $this->debugLog('JWT DECODE OK', [
                'uid' => $decoded->uid ?? null,
                'idusuario' => $decoded->idusuario ?? null,
            ]);

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
                    $this->debugLog('ERRO: Token na blacklist', ['jti' => $jti]);
                    return $this->unauthorized('Token revogado. Faça login novamente.');
                }
            }

            // Extrair dados do usuário
            $uid = $decoded->uid ?? 0;
            $idusuario = $decoded->idusuario ?? 0;
            $username = $decoded->username ?? '';
            $permissoes = $decoded->permissoes ?? [];
            $motoristaId = (int)($decoded->motorista_id ?? 0);

            if ($uid === 0) {
                $this->debugLog('ERRO: uid = 0', []);
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
                $this->debugLog('ERRO: Usuário não encontrado no banco', ['uid' => $uid]);
                return $this->unauthorized('Usuário não encontrado');
            }

            if ($usuario['inativo'] === 'S') {
                $this->debugLog('ERRO: Usuário inativo', ['uid' => $uid]);
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
                'motorista_id' => $motoristaId,
                'jti' => $jti
            ]);

            if ($isAdmin) {
                $request = $request->withAttribute('is_admin', true);
            }

            $this->debugLog('JWT OK - passando para handler', [
                'username' => $username,
                'is_admin' => $isAdmin,
            ]);

            return $handler->handle($request);

        } catch (\Firebase\JWT\ExpiredException $e) {
            $this->debugLog('EXCEÇÃO: Token expirado', ['msg' => $e->getMessage()]);
            return $this->unauthorized('Token expirado', 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            $this->debugLog('EXCEÇÃO: Assinatura inválida', ['msg' => $e->getMessage()]);
            return $this->unauthorized('Assinatura do token inválida', 401);
        } catch (\Exception $e) {
            $this->debugLog('EXCEÇÃO GENÉRICA', [
                'class' => get_class($e),
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->unauthorized('Token inválido', 401);
        }
    }

    /**
     * 🔥 DEBUG TEMPORÁRIO (Bloco 7.A.4)
     * Escreve log em arquivo dedicado para investigar 401.
     * REMOVER após diagnóstico.
     */
    private function debugLog(string $evento, array $contexto): void
    {
        try {
            $linha = sprintf(
                "[%s] [%s] %s %s\n",
                date('Y-m-d H:i:s'),
                $_SERVER['REQUEST_URI'] ?? 'CLI',
                $evento,
                json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            file_put_contents(
                __DIR__ . '/../../logs/jwt-debug.log',
                $linha,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            // nunca falha por log
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