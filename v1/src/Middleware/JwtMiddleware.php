<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Middleware de autenticação JWT.
 *
 * 🔥 REESCRITO 2026-09-30 (Fase 1.3):
 *   - `$handler->handle($request)` MOVIDO para FORA do try/catch.
 *     Motivo: antes, qualquer exceção do Controller (PDOException,
 *     TypeError, etc) era capturada pelo catch genérico e transformada
 *     em 401 "Token inválido" — mascarando o erro real.
 *   - Agora só o JWT::decode + validação de blacklist + user ativo
 *     estão dentro do try. Erros do Controller sobem como 500 com
 *     stack trace visível no log.
 *   - Removido `debugLog()` temporário (não é mais necessário).
 */
class JwtMiddleware
{
    private $pdo;
    private $jwtSecret;

    /**
     * Rotas públicas (não exigem token).
     * Comparadas por prefixo (startsWith).
     */
    private $publicRoutes = [
        '/v1/auth/login',
        '/v1/sistema/modulos-setores',
        '/v1/frota/cobli/webhook',      // público (autenticado via HMAC)
        '/v1/frota/health',              // 🔥 NOVO: health check público
        '/ping',
    ];

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->jwtSecret = CHAVE_SECRETA;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();

        // ─────────────────────────────────────────────────────────────
        // 1. Rotas públicas: pula validação de JWT
        // ─────────────────────────────────────────────────────────────
        if ($this->isPublicRoute($path)) {
            return $handler->handle($request);
        }

        // ─────────────────────────────────────────────────────────────
        // 2. Extrai o token do header Authorization
        // ─────────────────────────────────────────────────────────────
        $authHeader = $request->getHeaderLine('Authorization');

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Token não fornecido');
        }

        $token = $matches[1];

        // ─────────────────────────────────────────────────────────────
        // 3. Tudo abaixo pode lançar exceção JWT — protegido por try
        //    EXCETO `$handler->handle()` (fica fora)
        // ─────────────────────────────────────────────────────────────
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            $uid        = (int)($decoded->uid ?? 0);
            $idusuario  = (int)($decoded->idusuario ?? 0);
            $username   = $decoded->username ?? '';
            $permissoes = $decoded->permissoes ?? [];
            $motoristaId = (int)($decoded->motorista_id ?? 0);
            $jti        = $decoded->jti ?? null;

            if ($uid === 0) {
                return $this->unauthorized('Token inválido: usuário não identificado');
            }

            // ── Blacklist (se tiver jti)
            if ($jti) {
                $tokenHash = hash('sha256', $token);
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM token_blacklist
                    WHERE (token_hash = :token_hash OR jti = :jti)
                      AND expiracao > NOW()
                    LIMIT 1
                ");
                $stmt->execute([
                    ':token_hash' => $tokenHash,
                    ':jti'        => $jti,
                ]);

                if ($stmt->fetchColumn()) {
                    return $this->unauthorized('Token revogado. Faça login novamente.');
                }
            }

            // ── Verifica se o usuário ainda está ativo
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

            // ── is_admin
            $isAdmin = $this->isAdminUser($idusuario);

            // ── Anexa dados do usuário ao request
            $request = $request->withAttribute('user', [
                'uid'          => $uid,
                'idusuario'    => $idusuario,
                'username'     => $username,
                'permissoes'   => $permissoes,
                'is_admin'     => $isAdmin,
                'motorista_id' => $motoristaId,
                'jti'          => $jti,
            ]);

            if ($isAdmin) {
                $request = $request->withAttribute('is_admin', true);
            }

        } catch (\Firebase\JWT\ExpiredException $e) {
            return $this->unauthorized('Token expirado', 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return $this->unauthorized('Assinatura do token inválida', 401);
        } catch (\Exception $e) {
            error_log('[JwtMiddleware] Erro ao decodificar JWT: ' . $e->getMessage());
            return $this->unauthorized('Token inválido', 401);
        }

        // ─────────────────────────────────────────────────────────────
        // 4. FORA do try/catch: executa o Controller.
        //    Se lançar exceção, sobe como 500 (com stack trace visível).
        // ─────────────────────────────────────────────────────────────
        return $handler->handle($request);
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
            'code'  => $code,
        ]));
        return $response
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json');
    }
}