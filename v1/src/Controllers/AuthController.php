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

            $hashBanco = $usuario['senha'];
            $hashCalculado = strtoupper(md5(strtoupper($username) . $pass));

            if (!hash_equals(strtoupper((string)$hashBanco), $hashCalculado)) {
                return $this->jsonError($response, 'Credenciais inválidas', 401);
            }

            // Politica por usuario: 2FA no login, inatividade e duracao do token
            $seg = \Nutricional\Services\SegurancaUsuario::config($this->pdo, (int)$usuario['idusuario']);
            $doisFatoresPendente = false;
            if ($seg['exige_2fa_login']) {
                if (\Nutricional\Services\SegurancaUsuario::tem2fa($this->pdo, (int)$usuario['idusuario'])) {
                    $codigo2fa = preg_replace('/\D/', '', (string)($input['codigo2fa'] ?? ''));
                    if ($codigo2fa === '') {
                        $response->getBody()->write(json_encode(['error' => 'Informe o código do aplicativo autenticador', 'requer_2fa' => true]));
                        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
                    }
                    $res = \Nutricional\Services\SegurancaUsuario::validarCodigoLogin(
                        $this->pdo, (int)$usuario['idusuario'], (string)$usuario['username'], $codigo2fa,
                        (string)($request->getServerParams()['REMOTE_ADDR'] ?? '')
                    );
                    if ($res === 'bloqueado') {
                        return $this->jsonError($response, 'Muitas tentativas. Aguarde 10 minutos.', 429);
                    }
                    if ($res !== 'ok') {
                        $response->getBody()->write(json_encode(['error' => 'Código inválido ou expirado', 'requer_2fa' => true]));
                        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
                    }
                } else {
                    // Evita trancar o usuario fora: entra e deve cadastrar o autenticador.
                    $doisFatoresPendente = true;
                }
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
                'exp' => time() + ($seg['token_min'] * 60)
            ];

            $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

            // ================================================================
            // 🔥 GRAVAR SESSÃO PHP
            // Necessário para que motorista-offline.php (que roda em PHP puro)
            // enxergue o usuário logado via $_SESSION, e não só o JWT no navegador.
            // ================================================================
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['uid']          = (int)$usuario['idcliforemp'];
            $_SESSION['idusuario']    = (int)$usuario['idusuario'];
            $_SESSION['uname']        = $usuario['username'];
            $_SESSION['username']     = $usuario['username'];
            $_SESSION['permissoes']   = $permissoes;
            $_SESSION['is_admin']     = in_array('admin', $permissoes, true);
            $_SESSION['motorista_id'] = $motoristaId;
            $_SESSION['foto_perfil']  = $usuario['foto_perfil'] ?? null;
            $_SESSION['jwt_token']    = $token;

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
                    'motorista_id' => $payload['motorista_id'],
                    'inatividade_min' => $seg['inatividade_min'],
                    'exige_2fa' => $seg['exige_2fa_login'],
                    'requer_cadastro_2fa' => $doisFatoresPendente
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

            // ================================================================
            // 🔥 LIMPAR SESSÃO PHP
            // Sem isso, o motorista-offline.php continuaria vendo o usuário
            // como logado mesmo depois do logout (o JWT some do navegador, mas
            // a sessão PHP permaneceria ativa no servidor).
            // ================================================================
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            @session_destroy();
            
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
                
                $expiresAt = time() + (4 * 3600);
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

            $erroWhats = \Nutricional\Services\WhatsAppCodigo::conferir($this->pdo, (int)($user['idusuario'] ?? 0), (string)($input['codigo_whatsapp'] ?? ''));
            if ($erroWhats !== null) {
                $response->getBody()->write(json_encode(['error' => $erroWhats, 'requer_whatsapp' => true], JSON_UNESCAPED_UNICODE));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
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
     * POST /v1/auth/inatividade
     * Registra o motivo informado ao retomar a sessao apos inatividade.
     */
    public function registrarInatividade(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user') ?? [];
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = json_decode((string)$request->getBody(), true) ?: [];
        }

        $motivo = trim((string)($body['motivo'] ?? ''));
        $motivo = preg_replace('/\s+/u', ' ', $motivo);
        if (mb_strlen($motivo) < 5) {
            return $this->jsonError($response, 'Informe o motivo da inatividade (mínimo 5 caracteres).', 422);
        }
        $motivo = mb_substr($motivo, 0, 300);

        $codigo = trim((string)($body['codigo_parada'] ?? ''));
        if ($codigo !== '' && !preg_match('/^[1-6]\.(?:[1-9]|10)$/', $codigo)) {
            return $this->jsonError($response, 'Código de parada inválido.', 422);
        }
        if ($codigo === '6.10' && !preg_match('/:\s*\S{5,}/u', $motivo)) {
            return $this->jsonError($response, 'O código 6.10 exige justificativa.', 422);
        }

        $minutos = max(0, min(1440, (int)($body['minutos_inativo'] ?? 0)));
        $pagina = mb_substr((string)($body['pagina'] ?? ''), 0, 200);
        $modulo = preg_match('~/([a-z0-9_-]+)(?:\.php)?/?$~i', (string)parse_url($pagina, PHP_URL_PATH), $mm) ? strtolower($mm[1]) : 'portal';

        try {
            $stmtCheck = $this->pdo->query(
                "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'portal_inatividade')"
            );
            if ($stmtCheck->fetchColumn()) {
                $temCodigo = (bool)$this->pdo->query(
                    "SELECT 1 FROM information_schema.columns WHERE table_name = 'portal_inatividade' AND column_name = 'codigo_parada'"
                )->fetchColumn();
                $stmt = $this->pdo->prepare(
                    "INSERT INTO portal_inatividade (idusuario, username, motivo, minutos_inativo, modulo, pagina, ip_origem, user_agent"
                    . ($temCodigo ? ", codigo_parada" : "") . ")
                     VALUES (:uid, :username, :motivo, :min, :modulo, :pagina, :ip, :ua" . ($temCodigo ? ", :cod" : "") . ")"
                );
                $stmt->execute([
                    'uid' => (int)($user['idusuario'] ?? 0),
                    'username' => mb_substr((string)($user['username'] ?? ''), 0, 100),
                    'motivo' => $motivo,
                    'min' => $minutos,
                    'modulo' => $modulo,
                    'pagina' => $pagina,
                    'ip' => mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                ] + ($temCodigo ? ['cod' => $codigo !== '' ? $codigo : null] : []));
            } else {
                error_log('[Inatividade] tabela portal_inatividade ausente; usuario=' . ($user['idusuario'] ?? 0) . ' motivo=' . $motivo);
            }
        } catch (\Throwable $e) {
            error_log('[Inatividade] Falha ao registrar: ' . $e->getMessage());
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
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