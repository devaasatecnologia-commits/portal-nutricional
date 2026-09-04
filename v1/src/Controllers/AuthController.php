<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController
{
    private $pdo;
    private $jwtSecret;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->jwtSecret = CHAVE_SECRETA;
    }

    /**
     * Gera um JTI (JWT ID) único para cada token
     */
    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * POST /v1/auth/login
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            $rawBody = $request->getBody()->getContents();
            $request->getBody()->rewind();
            
            $input = json_decode($rawBody, true);
            
            $username = trim($input['user'] ?? '');
            $pass = trim($input['pass'] ?? '');
            if (empty($username) || empty($pass)) {
                return $this->jsonError($response, 'Usuário e senha obrigatórios', 400);
            }

            $stmt = $this->pdo->prepare("
                SELECT idcliforemp, idusuario, username, senha, dash_filiais, dash_gestores, inativo, foto_perfil
                FROM usuario 
                WHERE UPPER(username) = UPPER(:username)
            ");
            $stmt->execute(['username' => $username]);
            $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$usuario) {
                return $this->jsonError($response, 'Credenciais inválidas', 401);
            }

            if ($usuario['inativo'] === 'S') {
                return $this->jsonError($response, 'Usuário inativo', 401);
            }

            // ✅ VERIFICAÇÃO DE SENHA APENAS COM MD5 LEGADO (SEM MIGRAÇÃO)
            $hashBanco = $usuario['senha'];
            $hashCalculado = strtoupper(md5(strtoupper($username) . $pass));
            
            if ($hashCalculado !== $hashBanco) {
                return $this->jsonError($response, 'Credenciais inválidas', 401);
            }

            // Buscar permissões
            $permissoes = $this->getPermissoesDoUsuario($usuario['idusuario'], $usuario['idcliforemp']);
            $motoristaId = $this->getMotoristaIdPorErp((int)$usuario['idcliforemp']);
            
            $dashFiliais = !empty($usuario['dash_filiais']) ? explode(',', $usuario['dash_filiais']) : [];
            $dashGestores = !empty($usuario['dash_gestores']) ? explode(',', $usuario['dash_gestores']) : [];

            // ✅ PAYLOAD COM JTI (ID único do token)
            $payload = [
                'jti' => $this->generateJti(),
                'uid' => (int)$usuario['idcliforemp'],
                'idusuario' => (int)$usuario['idusuario'],
                'username' => $usuario['username'],
                'foto_perfil' => $usuario['foto_perfil'] ?? null,
                'dash_filiais' => $dashFiliais,
                'dash_gestores' => $dashGestores,
                'permissoes' => $permissoes,
                'motorista_id' => $motoristaId,
                'iat' => time(),
                'exp' => time() + (2 * 3600)
            ];

            $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

            try {
                $this->registrarAcesso($usuario['idusuario'], $usuario['idcliforemp'], $usuario['username']);
            } catch (\Exception $e) {
                error_log('Erro ao registrar acesso (ignorado): ' . $e->getMessage());
            }

            $responseData = [
                'token' => $token,
                'user' => [
                    'uid' => $payload['uid'],
                    'idusuario' => $payload['idusuario'],
                    'username' => $payload['username'],
                    'foto_perfil' => $payload['foto_perfil'],
                    'dash_filiais' => $payload['dash_filiais'],
                    'dash_gestores' => $payload['dash_gestores'],
                    'permissoes' => $payload['permissoes'],
                    'motorista_id' => $payload['motorista_id']
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            error_log('Falha no login: ' . $e->getMessage());
            return $this->jsonError($response, 'Erro interno no servidor.', 500);
        }
    }

    /**
     * O usuario.idcliforemp e a mesma chave ERP de frota_motorista.erp_id.
     */
    private function getMotoristaIdPorErp(int $idcliforemp): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM frota_motorista WHERE erp_id = :idcliforemp AND status <> 'inativo' LIMIT 1");
            $stmt->execute(['idcliforemp' => $idcliforemp]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            error_log('Erro ao buscar vínculo motorista-usuário: ' . $e->getMessage());
            return 0;
        }
    }

/**
 * POST /v1/auth/logout
 * Revoga o token atual (adiciona na blacklist) - PostgreSQL version
 */
public function logout(Request $request, Response $response): Response
{
    try {
        error_log('=== LOGOUT INITIATED (PostgreSQL) ===');
        
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->jsonError($response, 'Token não fornecido', 401);
        }
        
        $token = $matches[1];
        $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
        
        $jti = $decoded->jti ?? null;
        $idusuario = $decoded->idusuario ?? 0;
        $exp = $decoded->exp ?? (time() + 3600);
        
        if (!$jti) {
            return $this->jsonError($response, 'Token inválido (sem JTI)', 400);
        }
        
        $tokenHash = hash('sha256', $token);
        
        // Verificar se a tabela existe no PostgreSQL
        $stmtCheck = $this->pdo->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_name = 'token_blacklist'
            )
        ");
        $stmtCheck->execute();
        $tableExists = $stmtCheck->fetchColumn();
        
        if (!$tableExists) {
            error_log('Creating token_blacklist table...');
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS token_blacklist (
                    id SERIAL PRIMARY KEY,
                    token_hash VARCHAR(64) NOT NULL UNIQUE,
                    idusuario INTEGER NOT NULL,
                    jti VARCHAR(100) NOT NULL,
                    expiracao TIMESTAMP NOT NULL,
                    motivo VARCHAR(50) DEFAULT 'logout',
                    revoked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    revoked_by_ip VARCHAR(45),
                    user_agent TEXT
                )
            ");
        }
        
        // Verificar se já está na blacklist
        $stmtCheck = $this->pdo->prepare("
            SELECT COUNT(*) FROM token_blacklist WHERE jti = :jti
        ");
        $stmtCheck->execute(['jti' => $jti]);
        $exists = $stmtCheck->fetchColumn();
        
        if (!$exists) {
            // Inserir na blacklist (PostgreSQL version)
            $stmt = $this->pdo->prepare("
                INSERT INTO token_blacklist (token_hash, idusuario, jti, expiracao, motivo, revoked_by_ip, user_agent)
                VALUES (:hash, :idusuario, :jti, TO_TIMESTAMP(:exp), 'logout', :ip, :ua)
            ");
            
            $stmt->execute([
                'hash' => $tokenHash,
                'idusuario' => $idusuario,
                'jti' => $jti,
                'exp' => $exp,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            error_log("✅ Token revogado - JTI: {$jti}");
        }
        
        // Limpar tokens expirados
        $this->pdo->exec("DELETE FROM token_blacklist WHERE expiracao < NOW()");
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        error_log('Erro no logout: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Erro ao fazer logout'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
}

    /**
     * POST /v1/auth/logout-all
     * Revoga todos os tokens do usuário
     */
    public function logoutAll(Request $request, Response $response): Response
    {
        try {
            $authHeader = $request->getHeaderLine('Authorization');
            
            if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $this->jsonError($response, 'Token não fornecido', 401);
            }
            
            $token = $matches[1];
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $uid = $decoded->uid ?? 0;
            
            if ($uid) {
                // Revogar todos os tokens ativos do usuário
                $stmt = $this->pdo->prepare("
                    INSERT INTO token_blacklist (token_hash, idusuario, jti, expiracao, motivo, revoked_by_ip, user_agent)
                    VALUES (:hash, :uid, :jti, TO_TIMESTAMP(:exp), 'logout_all', :ip, :ua)
                ");
                
                $expiresAt = time() + (2 * 3600);
                $stmt->execute([
                    'hash' => hash('sha256', 'bulk_' . $uid . '_' . time()),
                    'uid' => $uid,
                    'jti' => 'bulk_' . $uid . '_' . time(),
                    'exp' => $expiresAt,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                error_log("✅ Todos os tokens revogados - Usuário: {$uid}");
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Todos os dispositivos foram desconectados'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erro no logoutAll: ' . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * POST /v1/auth/alterar-senha
     */
    public function alterarSenha(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user') ?? [];
            $uid = $user['uid'] ?? 0;
            $username = $user['username'] ?? '';
            
            if (!$uid) {
                return $this->jsonError($response, 'Usuário não autenticado', 401);
            }
            
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            $novaSenha = trim($input['senha'] ?? '');
            
            if (empty($novaSenha)) {
                return $this->jsonError($response, 'Nova senha é obrigatória', 400);
            }
            
            // ✅ MANTÉM O MESMO FORMATO MD5 LEGADO
            $novaHash = strtoupper(md5(strtoupper($username) . $novaSenha));
            
            $stmt = $this->pdo->prepare("UPDATE usuario SET senha = :senha WHERE idcliforemp = :uid");
            $stmt->execute(['senha' => $novaHash, 'uid' => $uid]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Senha alterada com sucesso!'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Throwable $e) {
            error_log('Erro alterarSenha: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withStatus(500);
        }
    }

    /**
     * GET /v1/perfil/dados
     */
    public function getDadosPerfil($request, $response) 
    {
        $user = $request->getAttribute('user');
        $uid = $user['uid'] ?? 0;
        
        try {
            $sql = "SELECT DISTINCT 
                        u.idusuario,
                        u.username, 
                        u.foto_perfil,
                        c.fantasia,
                        c.email,
                        c.fone,
                        c.endereco,
                        c.bairro,
                        (SELECT descricao FROM cidade WHERE idcidade = c.idcidade LIMIT 1) as cidade,
                        c.uf,
                        c.cep,
                        e.cargo,
                        e.datanascimento
                    FROM usuario u
                    JOIN cliforemp c ON c.idcliforemp = u.idcliforemp
                    JOIN empregado e ON e.idcliforemp = c.idcliforemp
                    WHERE u.idcliforemp = :uid AND u.inativo = 'N'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['uid' => $uid]);
            $dados = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($dados && !empty($dados['foto_perfil'])) {
                $dados['foto_url'] = (strpos($dados['foto_perfil'], 'http') === 0) 
                    ? $dados['foto_perfil'] 
                    : 'https://api.nutricionalbr.com/' . $dados['foto_perfil'];
            }
            
            $response->getBody()->write(json_encode($dados ?: []));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('Erro getDadosPerfil: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

    /**
     * Busca permissões do banco de dados
     */
    private function getPermissoesDoUsuario(int $idusuario, int $idcliforemp): array
    {
        try {
            // Verifica se a tabela usuarios_admin existe
            $stmtCheck = $this->pdo->query(
                "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'usuarios_admin')"
            );
            
            if ($stmtCheck->fetchColumn()) {
                $stmtAdmin = $this->pdo->prepare(
                    "SELECT nivel FROM usuarios_admin WHERE idusuario = :idusuario AND ativo = true"
                );
                $stmtAdmin->execute(['idusuario' => $idusuario]);
                $isAdmin = $stmtAdmin->fetchColumn();
                
                if ($isAdmin) {
                    // Admin: busca todos os módulos
                    try {
                        $stmt = $this->pdo->query(
                            "SELECT slug FROM sistema_modulos WHERE ativo = true ORDER BY ordem"
                        );
                        $permissoes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                        
                        if (!in_array('admin', $permissoes)) {
                            $permissoes[] = 'admin';
                        }
                        
                        return $permissoes ?: $this->getPermissoesFallback(true);
                    } catch (\Exception $e) {
                        error_log('Erro ao buscar módulos: ' . $e->getMessage());
                        return $this->getPermissoesFallback(true);
                    }
                }
            }
            
            // Usuário comum: busca permissões
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT sm.slug 
                     FROM usuario_permissoes up 
                     JOIN sistema_modulos sm ON sm.id = up.idmodulo 
                     WHERE up.idusuario = :idusuario AND sm.ativo = true
                     ORDER BY sm.ordem"
                );
                $stmt->execute(['idusuario' => $idusuario]);
                $permissoes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                
                if (!empty($permissoes)) {
                    return $permissoes;
                }
            } catch (\Exception $e) {
                error_log('Erro ao buscar permissões: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            error_log('Erro geral em getPermissoesDoUsuario: ' . $e->getMessage());
        }
        
        return $this->getPermissoesFallback(false);
    }

    /**
     * Fallback de permissões
     */
    private function getPermissoesFallback(bool $isAdmin): array
    {
        if ($isAdmin) {
            return ['separacao', 'carregamento', 'monitor', 'xml', 'financeiro', 'marketing', 'auditoria', 'crons', 'admin'];
        }
        return ['separacao', 'carregamento'];
    }

    /**
     * Registra acesso no log
     */
    private function registrarAcesso(int $idusuario, int $idcliforemp, string $username): void
    {
        try {
            $stmtCheck = $this->pdo->query(
                "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'portal_acessos')"
            );
            $tabelaExiste = $stmtCheck->fetchColumn();
            
            if ($tabelaExiste) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO portal_acessos (idusuario, idcliforemp, username, ip_origem, user_agent, acao) 
                     VALUES (:idusuario, :idcliforemp, :username, :ip, :agent, 'LOGIN')"
                );
                $stmt->execute([
                    'idusuario' => $idusuario,
                    'idcliforemp' => $idcliforemp,
                    'username' => $username,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
            }
        } catch (\Exception $e) {
            error_log('Erro ao registrar acesso: ' . $e->getMessage());
        }
    }

    /**
     * Resposta de erro JSON
     */
    private function jsonError(Response $response, string $msg, int $code): Response
    {
        $response->getBody()->write(json_encode(['error' => $msg]));
        return $response->withStatus($code)->withHeader('Content-Type', 'application/json');
    }
}