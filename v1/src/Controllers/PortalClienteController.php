<?php

namespace Nutricional\Controllers;

use Firebase\JWT\JWT;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Portal do Cliente & Hierarquia Comercial (B2B + Auth 2FA).
 *
 * - Login unificado por E-mail ou CNPJ/CPF + Cloudflare Turnstile + 2FA por e-mail (OTP 6 dígitos).
 * - Solicitação de cadastro por CNPJ e recuperação de senha com código por e-mail.
 * - RBAC Hierárquico no Backend:
 *   * GESTOR / ADMIN: escolhe Representante e visualiza clientes liberados.
 *   * REPRESENTANTE: restrito estritamente aos seus clientes (cliente.idvendedor).
 *   * CLIENTE: travado estritamente no próprio idcliforemp (boletos, notas e pedidos).
 */
class PortalClienteController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function utf8($val): string
    {
        if ($val === null) {
            return '';
        }
        $s = (string)$val;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        return $s;
    }

    private function body(Request $request): array
    {
        $raw = (string)$request->getBody();
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    private function somenteDigitos(string $v): string
    {
        return preg_replace('/\D+/', '', $v) ?: '';
    }

    private function mascararEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '***';
        }
        [$user, $dom] = explode('@', $email, 2);
        $prefix = mb_substr($user, 0, min(2, mb_strlen($user)));
        return $prefix . '***@' . $dom;
    }

    /**
     * Valida Cloudflare Turnstile quando TURNSTILE_SECRET_KEY estiver configurada.
     */
    private function validarTurnstile(string $token): bool
    {
        $secret = trim(\Nutricional\Services\SecretVault::get('TURNSTILE_SECRET_KEY'));
        if ($secret === '') {
            // Chave ainda não configurada no Cofre de Segredos nem no .env (ambiente local/homologação)
            return true;
        }
        if ($token === '') {
            return false;
        }
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
            CURLOPT_TIMEOUT => 8,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $res = $raw ? json_decode($raw, true) : null;
        if (empty($res['success'])) {
            error_log('[PortalCliente][Turnstile] Falha siteverify: err=' . $err . ' resp=' . ($raw ?: 'empty'));
            return false;
        }
        return true;
    }

    /**
     * Envia e-mail transacional via PHPMailer usando credenciais do .env.
     */
    private function enviarEmail(string $para, string $nomePara, string $assunto, string $html): bool
    {
        $host = trim((string)($_ENV['MAIL_HOST'] ?? ($_SERVER['MAIL_HOST'] ?? (getenv('MAIL_HOST') ?: 'mail.nutricionalbr.com'))));
        $user = trim((string)($_ENV['MAIL_USER'] ?? ($_SERVER['MAIL_USER'] ?? (getenv('MAIL_USER') ?: 'nao-responder@nutricionalbr.com'))));
        $pass = trim(\Nutricional\Services\SecretVault::get('MAIL_PASS'));
        $port = (int)($_ENV['MAIL_PORT'] ?? ($_SERVER['MAIL_PORT'] ?? (getenv('MAIL_PORT') ?: 465)));

        if ($host === '' || $user === '' || $pass === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
            error_log("[PortalCliente] SMTP config incompleta: host=" . ($host ?: 'VAZIO') . " user=" . ($user ?: 'VAZIO') . " pass_len=" . strlen($pass) . " para={$para}");
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Hostname = 'nutricionalbr.com';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->Port = $port;
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->Sender = $user;
            $mail->setFrom($user, 'Portal do Cliente - Nutricional');
            $mail->addAddress($para, $nomePara ?: $para);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>', '</h2>'], "\n", $html)));
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[PortalCliente] Erro SMTP: ' . $e->getMessage());
            return false;
        }
    }

    private function gerarOtp(int $idconta, string $email, string $tipo): string
    {
        $this->pdo->prepare("UPDATE b2b_portal_tokens SET usado = TRUE WHERE idconta = :id AND tipo = :t AND usado = FALSE")
            ->execute(['id' => $idconta, 't' => $tipo]);

        $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->pdo->prepare("
            INSERT INTO b2b_portal_tokens (idconta, email, tipo, codigo, expira_em)
            VALUES (:id, :email, :t, :cod, NOW() + INTERVAL '10 minutes')
        ")->execute([
            'id' => $idconta,
            'email' => $email,
            't' => $tipo,
            'cod' => $codigo,
        ]);
        return $codigo;
    }

    private function emitirJwtB2B(array $conta): string
    {
        $secret = defined('CHAVE_SECRETA') ? CHAVE_SECRETA : ($_ENV['CHAVE_SECRETA'] ?? '');
        $agora = time();
        $payload = [
            'iss' => 'nutricional-b2b',
            'iat' => $agora,
            'exp' => $agora + (8 * 3600),
            'b2b' => true,
            'b2b_id' => (int)$conta['id'],
            'idconta' => (int)$conta['id'],
            'uid' => (int)$conta['idcliforemp'],
            'idusuario' => (int)($conta['idusuario_erp'] ?? 0),
            'username' => $conta['nome'],
            'perfil_b2b' => strtoupper((string)$conta['perfil']),
            'idcliforemp' => (int)$conta['idcliforemp'],
            'idvendedor' => (int)($conta['idvendedor'] ?? 0),
            'dash_gestores' => (string)($conta['dash_gestores'] ?? ''),
            'is_admin' => strtoupper((string)$conta['perfil']) === 'ADMIN',
            'permissoes' => ['portal_cliente'],
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * GET /v1/portal-cliente/auth/config (Pública)
     */
    public function authConfig(Request $request, Response $response): Response
    {
        $siteKey = trim(\Nutricional\Services\SecretVault::get('TURNSTILE_SITE_KEY'));
        return $this->json($response, [
            'success' => true,
            'turnstile_ativo' => $siteKey !== '',
            'turnstile_site_key' => $siteKey,
            'whatsapp_ativo' => \Nutricional\Services\WhatsAppService::provedor() !== null,
        ]);
    }

    /**
     * POST /v1/portal-cliente/auth/login (Pública)
     * Aceita E-mail ou CNPJ/CPF + Senha + Turnstile e dispara OTP 2FA por e-mail.
     */
    public function login(Request $request, Response $response): Response
    {
        $b = $this->body($request);
        $identificador = trim((string)($b['identificador'] ?? ''));
        $senha = (string)($b['senha'] ?? '');
        $turnstile = trim((string)($b['cf_turnstile_response'] ?? ''));

        if ($identificador === '' || $senha === '') {
            return $this->json($response, ['success' => false, 'error' => 'Informe E-mail ou CNPJ/CPF e a senha.'], 400);
        }
        if (!$this->validarTurnstile($turnstile)) {
            return $this->json($response, ['success' => false, 'error' => 'Falha na verificação de segurança (Cloudflare Turnstile).'], 403);
        }

        $digits = $this->somenteDigitos($identificador);
        $emailLower = mb_strtolower($identificador, 'UTF-8');

        $st = $this->pdo->prepare("
            SELECT * FROM b2b_portal_contas
            WHERE (LOWER(email) = :em OR (:dig <> '' AND cnpj_cpf = :dig))
            ORDER BY id DESC LIMIT 1
        ");
        $st->execute(['em' => $emailLower, 'dig' => $digits]);
        $conta = $st->fetch(PDO::FETCH_ASSOC);

        if (!$conta) {
            // Verifica também se o identificador corresponde a um usuário interno do Portal (Admin, Gestor ou Representante com usuário)
            $stUsr = $this->pdo->prepare("
                SELECT idusuario, idcliforemp, username, senha, dash_filiais, dash_gestores,
                       COALESCE(dash_representantes, '') AS dash_representantes, foto_perfil
                FROM usuario
                WHERE UPPER(username) = UPPER(:u) AND COALESCE(inativo, 'N') <> 'S'
                LIMIT 1
            ");
            $stUsr->execute(['u' => $identificador]);
            $usrPortal = $stUsr->fetch(PDO::FETCH_ASSOC);
            if ($usrPortal) {
                $hashCalc = strtoupper(md5(strtoupper((string)$usrPortal['username']) . $senha));
                if (!hash_equals(strtoupper((string)$usrPortal['senha']), $hashCalc)) {
                    return $this->json($response, ['success' => false, 'error' => 'Credenciais inválidas.'], 401);
                }
                // Verifica se possui conta B2B vinculada ao idcliforemp; caso contrário, autentica via sessão do Portal Interno
                if ((int)$usrPortal['idcliforemp'] > 0) {
                    $stB2b = $this->pdo->prepare("SELECT * FROM b2b_portal_contas WHERE idcliforemp = :cli ORDER BY id DESC LIMIT 1");
                    $stB2b->execute(['cli' => (int)$usrPortal['idcliforemp']]);
                    $conta = $stB2b->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$conta) {
                    $portalAuth = $this->emitirSessaoPortalInterno((int)$usrPortal['idcliforemp'], (int)$usrPortal['idusuario']);
                    if ($portalAuth) {
                        return $this->json($response, [
                            'success' => true,
                            'direct_token' => $portalAuth['token'],
                            'portal_user' => $portalAuth['user'],
                        ]);
                    }
                }
            }
        }

        if (!$conta) {
            $prov = $this->localizarOuProvisionarContaErp($identificador, false);
            if (!empty($prov['conta'])) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Primeiro acesso detectado! Clique em "Primeiro Acesso / Esqueceu a Senha" para receber o código no seu e-mail cadastrado e criar sua senha.',
                ], 403);
            }
            return $this->json($response, ['success' => false, 'error' => 'Credenciais inválidas.'], 401);
        }
        $senhaValidaB2b = trim((string)$conta['senha_hash']) !== '' && password_verify($senha, (string)$conta['senha_hash']);
        if (!$senhaValidaB2b && (int)$conta['idcliforemp'] > 0) {
            // Permite também a senha do usuário interno vinculado ao mesmo idcliforemp (quando Representante possui login no Portal)
            $stUP = $this->pdo->prepare("SELECT username, senha FROM usuario WHERE idcliforemp = :cli AND COALESCE(inativo, 'N') <> 'S' LIMIT 1");
            $stUP->execute(['cli' => (int)$conta['idcliforemp']]);
            $uRow = $stUP->fetch(PDO::FETCH_ASSOC);
            if ($uRow && hash_equals(strtoupper((string)$uRow['senha']), strtoupper(md5(strtoupper((string)$uRow['username']) . $senha)))) {
                $senhaValidaB2b = true;
            }
        }
        if (!$senhaValidaB2b && trim((string)$conta['senha_hash']) === '') {
            return $this->json($response, [
                'success' => false,
                'error' => 'Senha ainda não definida. Use "Primeiro Acesso / Esqueceu a Senha" para receber o código no e-mail cadastrado e criar sua senha.',
            ], 403);
        }
        if (!$senhaValidaB2b) {
            return $this->json($response, ['success' => false, 'error' => 'Credenciais inválidas.'], 401);
        }
        if (strtoupper((string)$conta['status']) !== 'ATIVO') {
            return $this->json($response, ['success' => false, 'error' => 'Cadastro aguardando aprovação ou bloqueado (' . $conta['status'] . ').'], 403);
        }

        $canalReq = strtolower(trim((string)($b['canal_2fa'] ?? 'auto')));
        $canal = ($canalReq === '' || $canalReq === 'auto')
            ? strtolower(trim((string)($conta['preferencia_2fa'] ?? 'email')))
            : $canalReq;
        if ($canal === 'whatsapp') {
            if (\Nutricional\Services\WhatsAppService::provedor() === null) {
                return $this->json($response, ['success' => false, 'error' => 'Envio de 2FA por WhatsApp ainda não configurado no servidor. Selecione E-mail.'], 400);
            }
            $rawFone = trim((string)($conta['whatsapp'] ?? ''));
            if ($rawFone === '') {
                $stF = $this->pdo->prepare("SELECT fone FROM cliforemp WHERE idcliforemp = :id");
                $stF->execute(['id' => (int)$conta['idcliforemp']]);
                $rawFone = trim((string)$stF->fetchColumn());
            }
            $numNorm = \Nutricional\Services\WhatsAppService::normalizarNumero($rawFone);
            if (!$numNorm) {
                return $this->json($response, ['success' => false, 'error' => 'Nenhum WhatsApp válido cadastrado. Entre por E-mail e cadastre seu WhatsApp no painel.'], 400);
            }
            $codigo = $this->gerarOtp((int)$conta['id'], $numNorm, 'OTP_LOGIN');
            $enviado = \Nutricional\Services\WhatsAppService::enviarCodigo($numNorm, $codigo);
            $masc = \Nutricional\Services\WhatsAppService::mascarar($numNorm);
            $detErro = !$enviado && \Nutricional\Services\WhatsAppService::$ultimoErro
                ? ' (' . \Nutricional\Services\WhatsAppService::$ultimoErro . ')'
                : '';
            return $this->json($response, [
                'success' => true,
                'requires_2fa' => true,
                'canal_2fa' => 'whatsapp',
                'desafio_id' => (int)$conta['id'],
                'email_mascarado' => $masc,
                'email_enviado' => $enviado,
                'message' => $enviado ? "Código de 6 dígitos enviado via WhatsApp para {$masc}" : "Falha ao enviar WhatsApp{$detErro}. Tente por E-mail.",
            ]);
        }

        $emailDestino = trim((string)$conta['email']);
        $codigo = $this->gerarOtp((int)$conta['id'], $emailDestino, 'OTP_LOGIN');
        $enviado = $this->enviarEmail(
            $emailDestino,
            $conta['nome'],
            "Código de Verificação 2FA ({$codigo}) — Portal do Cliente Nutricional",
            "<p>Olá, <strong>" . htmlspecialchars($conta['nome']) . "</strong>.</p>"
            . "<p>Seu código de verificação em 2 etapas para acessar o Portal do Cliente Nutricional é:</p>"
            . "<h2 style='letter-spacing:4px;color:#1e3a8a;'>{$codigo}</h2>"
            . "<p>Válido por 10 minutos. Se você não solicitou, ignore este e-mail.</p>"
        );

        return $this->json($response, [
            'success' => true,
            'requires_2fa' => true,
            'canal_2fa' => 'email',
            'desafio_id' => (int)$conta['id'],
            'email_mascarado' => $this->mascararEmail($emailDestino),
            'email_enviado' => $enviado,
            'message' => 'Código de 6 dígitos enviado para ' . $this->mascararEmail($emailDestino),
        ]);
    }

    /**
     * POST /v1/portal-cliente/auth/verificar-2fa (Pública)
     */
    public function verificar2fa(Request $request, Response $response): Response
    {
        $b = $this->body($request);
        $idconta = (int)($b['desafio_id'] ?? 0);
        $codigo = trim((string)($b['codigo'] ?? ''));

        if ($idconta <= 0 || !preg_match('/^\d{6}$/', $codigo)) {
            return $this->json($response, ['success' => false, 'error' => 'Informe o código de 6 dígitos.'], 400);
        }

        $stTok = $this->pdo->prepare("
            SELECT * FROM b2b_portal_tokens
            WHERE idconta = :id AND tipo = 'OTP_LOGIN' AND usado = FALSE AND expira_em >= NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stTok->execute(['id' => $idconta]);
        $tok = $stTok->fetch(PDO::FETCH_ASSOC);

        if (!$tok) {
            return $this->json($response, ['success' => false, 'error' => 'Código expirado ou inválido. Faça login novamente.'], 401);
        }
        if ((int)$tok['tentativas'] >= 5) {
            return $this->json($response, ['success' => false, 'error' => 'Limite de tentativas excedido. Solicite um novo código.'], 429);
        }

        if (!hash_equals((string)$tok['codigo'], $codigo)) {
            $this->pdo->prepare("UPDATE b2b_portal_tokens SET tentativas = tentativas + 1 WHERE id = :id")
                ->execute(['id' => $tok['id']]);
            return $this->json($response, ['success' => false, 'error' => 'Código incorreto.'], 401);
        }

        $this->pdo->prepare("UPDATE b2b_portal_tokens SET usado = TRUE WHERE id = :id")->execute(['id' => $tok['id']]);
        $this->pdo->prepare("UPDATE b2b_portal_contas SET ultimo_login = NOW() WHERE id = :id")->execute(['id' => $idconta]);

        $stC = $this->pdo->prepare("SELECT * FROM b2b_portal_contas WHERE id = :id");
        $stC->execute(['id' => $idconta]);
        $conta = $stC->fetch(PDO::FETCH_ASSOC);

        $jwt = $this->emitirJwtB2B($conta);
        $portalSessao = null;
        if (strtoupper((string)$conta['perfil']) !== 'CLIENTE' && (int)$conta['idcliforemp'] > 0) {
            $portalSessao = $this->emitirSessaoPortalInterno((int)$conta['idcliforemp'], 0);
        }

        $resPayload = [
            'success' => true,
            'token' => $jwt,
            'usuario' => [
                'perfil' => strtoupper((string)$conta['perfil']),
                'nome' => $conta['nome'],
                'email' => $conta['email'],
                'idcliforemp' => (int)$conta['idcliforemp'],
            ],
        ];
        if ($portalSessao) {
            $resPayload['portal_token'] = $portalSessao['token'];
            $resPayload['portal_user'] = $portalSessao['user'];
        }

        return $this->json($response, $resPayload);
    }

    /**
     * Emite token e payload de sessão do Portal Interno quando o Representante/Gestor/Admin possui registro ativo na tabela `usuario`.
     */
    private function emitirSessaoPortalInterno(int $idcliforemp, int $idusuario = 0): ?array
    {
        try {
            if ($idusuario > 0) {
                $st = $this->pdo->prepare("SELECT idcliforemp, idusuario, username, dash_filiais, dash_gestores, COALESCE(dash_representantes, '') AS dash_representantes, foto_perfil FROM usuario WHERE idusuario = :id AND COALESCE(inativo, 'N') <> 'S' LIMIT 1");
                $st->execute(['id' => $idusuario]);
            } elseif ($idcliforemp > 0) {
                $st = $this->pdo->prepare("SELECT idcliforemp, idusuario, username, dash_filiais, dash_gestores, COALESCE(dash_representantes, '') AS dash_representantes, foto_perfil FROM usuario WHERE (idcliforemp = :cli OR ',' || COALESCE(dash_representantes, '') || ',' LIKE '%,' || :cli_txt || ',%') AND COALESCE(inativo, 'N') <> 'S' ORDER BY CASE WHEN idcliforemp = :cli THEN 0 ELSE 1 END LIMIT 1");
                $st->execute(['cli' => $idcliforemp, 'cli_txt' => (string)$idcliforemp]);
            } else {
                return null;
            }
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if (!$u) {
                return null;
            }
            $idUsr = (int)$u['idusuario'];
            $isAdmin = false;
            if ($this->pdo->query("SELECT to_regclass('usuarios_admin') IS NOT NULL")->fetchColumn()) {
                $stA = $this->pdo->prepare("SELECT 1 FROM usuarios_admin WHERE idusuario = :id AND ativo = true LIMIT 1");
                $stA->execute(['id' => $idUsr]);
                $isAdmin = (bool)$stA->fetchColumn();
            }
            $permissoes = [];
            if ($isAdmin) {
                $permissoes = $this->pdo->query("SELECT slug FROM sistema_modulos WHERE ativo = true")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (!in_array('admin', $permissoes, true)) {
                    $permissoes[] = 'admin';
                }
            } else {
                $stP = $this->pdo->prepare("SELECT sm.slug FROM usuario_permissoes up JOIN sistema_modulos sm ON sm.id = up.idmodulo WHERE up.idusuario = :id AND sm.ativo = true");
                $stP->execute(['id' => $idUsr]);
                $permissoes = $stP->fetchAll(PDO::FETCH_COLUMN) ?: ['portal-cliente'];
            }
            $seg = \Nutricional\Services\SegurancaUsuario::config($this->pdo, $idUsr);
            $dashFiliais = !empty($u['dash_filiais']) ? explode(',', (string)$u['dash_filiais']) : [];
            $dashGestores = !empty($u['dash_gestores']) ? explode(',', (string)$u['dash_gestores']) : [];
            $dashReps = !empty($u['dash_representantes']) ? explode(',', (string)$u['dash_representantes']) : [];
            $secret = defined('CHAVE_SECRETA') ? CHAVE_SECRETA : ($_ENV['CHAVE_SECRETA'] ?? '');
            $payload = [
                'jti' => bin2hex(random_bytes(16)),
                'uid' => (int)$u['idcliforemp'],
                'idusuario' => $idUsr,
                'username' => $u['username'],
                'foto_perfil' => $u['foto_perfil'] ?? null,
                'dash_filiais' => $dashFiliais,
                'dash_gestores' => $dashGestores,
                'dash_representantes' => $dashReps,
                'permissoes' => $permissoes,
                'motorista_id' => null,
                'iat' => time(),
                'exp' => time() + ($seg['token_min'] * 60),
            ];
            $tok = JWT::encode($payload, $secret, 'HS256');
            return [
                'token' => $tok,
                'user' => [
                    'uid' => $payload['uid'],
                    'idusuario' => $payload['idusuario'],
                    'username' => $payload['username'],
                    'foto_perfil' => $payload['foto_perfil'],
                    'dash_filiais' => $payload['dash_filiais'],
                    'dash_gestores' => $payload['dash_gestores'],
                    'dash_representantes' => $payload['dash_representantes'],
                    'permissoes' => $payload['permissoes'],
                    'inatividade_min' => $seg['inatividade_min'],
                ],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * POST /v1/portal-cliente/auth/solicitar-cadastro (Pública)
     * Pré-vincula cliente pelo CNPJ/CPF existente em cliforemp.
     */
    public function solicitarCadastro(Request $request, Response $response): Response
    {
        $b = $this->body($request);
        $cnpjCpf = $this->somenteDigitos((string)($b['cnpj_cpf'] ?? ''));
        $email = mb_strtolower(trim((string)($b['email'] ?? '')), 'UTF-8');
        $senha = (string)($b['senha'] ?? '');
        $turnstile = trim((string)($b['cf_turnstile_response'] ?? ''));

        if (!$this->validarTurnstile($turnstile)) {
            return $this->json($response, ['success' => false, 'error' => 'Falha na validação Cloudflare Turnstile.'], 403);
        }
        if (strlen($cnpjCpf) < 11 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
            return $this->json($response, ['success' => false, 'error' => 'Informe CNPJ/CPF válido, E-mail válido e senha com mínimo 6 caracteres.'], 400);
        }

        // Busca cliente ativo no ERP
        $stCli = $this->pdo->prepare("
            SELECT c.idcliforemp, c.razao, c.email, COALESCE(cl.idvendedor, 0) AS idvendedor
            FROM cliforemp c
            LEFT JOIN cliente cl ON cl.idcliforemp = c.idcliforemp
            WHERE COALESCE(c.inativo, 'N') = 'N'
              AND (REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g') = :doc OR REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g') = :doc)
            LIMIT 1
        ");
        $stCli->execute(['doc' => $cnpjCpf]);
        $cli = $stCli->fetch(PDO::FETCH_ASSOC);

        if (!$cli) {
            return $this->json($response, ['success' => false, 'error' => 'CNPJ/CPF não encontrado na base de clientes ativos da Nutricional.'], 404);
        }

        // Verifica se já existe conta B2B
        $stEx = $this->pdo->prepare("SELECT id, status FROM b2b_portal_contas WHERE cnpj_cpf = :doc OR LOWER(email) = :em LIMIT 1");
        $stEx->execute(['doc' => $cnpjCpf, 'em' => $email]);
        if ($stEx->fetch()) {
            return $this->json($response, ['success' => false, 'error' => 'Já existe uma solicitação ou conta cadastrada para este CNPJ/E-mail.'], 409);
        }

        $emailErp = mb_strtolower(trim((string)$cli['email']), 'UTF-8');
        $emailConfere = ($emailErp !== '' && $emailErp === $email);
        $statusInicial = $emailConfere ? 'ATIVO' : 'PENDENTE';
        $razao = $this->utf8($cli['razao']);

        $this->pdo->prepare("
            INSERT INTO b2b_portal_contas (perfil, idcliforemp, cnpj_cpf, email, nome, senha_hash, status, idvendedor)
            VALUES ('CLIENTE', :idcli, :doc, :em, :nome, :hash, :st, :vend)
        ")->execute([
            'idcli' => (int)$cli['idcliforemp'],
            'doc' => $cnpjCpf,
            'em' => $email,
            'nome' => $razao,
            'hash' => password_hash($senha, PASSWORD_BCRYPT),
            'st' => $statusInicial,
            'vend' => (int)$cli['idvendedor'],
        ]);

        // Notifica admin/ti se pendente de validação
        $adminMail = trim((string)($_ENV['ADMIN_EMAIL'] ?? ''));
        if ($adminMail !== '') {
            $this->enviarEmail(
                $adminMail,
                'Admin Nutricional',
                "Solicitação de Acesso B2B: {$razao} ({$statusInicial})",
                "<p>Cliente: <strong>{$razao}</strong> (ID {$cli['idcliforemp']})<br>CNPJ/CPF: {$cnpjCpf}<br>E-mail informado: {$email}<br>Status inicial: <strong>{$statusInicial}</strong></p>"
            );
        }

        return $this->json($response, [
            'success' => true,
            'status' => $statusInicial,
            'message' => $statusInicial === 'ATIVO'
                ? 'Cadastro ativado automaticamente (e-mail conferido com o ERP)! Já pode entrar usando seu CNPJ ou E-mail com 2FA.'
                : 'Solicitação registrada com sucesso! Nossa equipe comercial/TI validará o e-mail informado para liberar o acesso.',
        ]);
    }

    /**
     * POST /v1/portal-cliente/auth/esqueceu-senha (Pública)
     */
    public function esqueceuSenha(Request $request, Response $response): Response
    {
        $b = $this->body($request);
        $ident = trim((string)($b['identificador'] ?? ''));
        $turnstile = trim((string)($b['cf_turnstile_response'] ?? ''));

        if (!$this->validarTurnstile($turnstile)) {
            return $this->json($response, ['success' => false, 'error' => 'Falha na validação Cloudflare Turnstile.'], 403);
        }

        $res = $this->localizarOuProvisionarContaErp($ident, true);
        if (empty($res['conta'])) {
            return $this->json($response, ['success' => false, 'error' => $res['error'] ?? 'Conta não encontrada para este E-mail ou CNPJ/CPF.'], 404);
        }
        $conta = $res['conta'];
        if (strtoupper((string)$conta['status']) === 'BLOQUEADO') {
            return $this->json($response, ['success' => false, 'error' => 'Esta conta está bloqueada. Entre em contato com o suporte.'], 403);
        }

        $codigo = $this->gerarOtp((int)$conta['id'], (string)$conta['email'], 'RESET_SENHA');
        $tituloEmail = trim((string)($conta['senha_hash'] ?? '')) === ''
            ? "Primeiro Acesso ({$codigo}) — Portal do Cliente Nutricional"
            : "Redefinição de Senha ({$codigo}) — Portal do Cliente Nutricional";
        $enviado = $this->enviarEmail(
            (string)$conta['email'],
            (string)$conta['nome'],
            $tituloEmail,
            "<p>Olá, <strong>" . htmlspecialchars((string)$conta['nome']) . "</strong>.</p>"
            . "<p>Use o código abaixo para definir sua senha de acesso ao Portal Nutricional (válido por 10 minutos):</p>"
            . "<h2 style='letter-spacing:4px;color:#1e3a8a;'>{$codigo}</h2>"
        );

        return $this->json($response, [
            'success' => true,
            'desafio_id' => (int)$conta['id'],
            'perfil' => $conta['perfil'],
            'email_mascarado' => $this->mascararEmail((string)$conta['email']),
            'email_enviado' => $enviado,
            'message' => $enviado
                ? 'Código enviado para o e-mail cadastrado: ' . $this->mascararEmail((string)$conta['email'])
                : 'Não foi possível enviar o e-mail SMTP agora. Contate o suporte TI.',
        ]);
    }

    /**
     * Localiza conta B2B existente ou provisiona automaticamente a partir de Representante ou Cliente ativo no ERP.
     */
    private function localizarOuProvisionarContaErp(string $ident, bool $provisionar): array
    {
        $digits = $this->somenteDigitos($ident);
        $emailLower = mb_strtolower(trim($ident), 'UTF-8');

        $st = $this->pdo->prepare("
            SELECT * FROM b2b_portal_contas
            WHERE (LOWER(email) = :em OR (:dig <> '' AND cnpj_cpf = :dig))
            ORDER BY id DESC LIMIT 1
        ");
        $st->execute(['em' => $emailLower, 'dig' => $digits]);
        $conta = $st->fetch(PDO::FETCH_ASSOC);
        if ($conta) {
            return ['conta' => $conta];
        }

        // 1. Verifica se é Representante ativo no ERP (possui clientes vinculados em cliente.idvendedor)
        $stRep = $this->pdo->prepare("
            SELECT c.idcliforemp, c.razao, c.email, um.email AS email_mercos,
                   COALESCE(NULLIF(REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g'), ''), REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g'), '') AS doc
            FROM cliforemp c
            LEFT JOIN usuarios_mercos um ON um.idcolaborador = c.idcliforemp
            WHERE COALESCE(c.inativo, 'N') = 'N'
              AND EXISTS (SELECT 1 FROM cliente cl WHERE cl.idvendedor = c.idcliforemp)
              AND (
                    LOWER(TRIM(COALESCE(c.email,''))) = :em
                 OR LOWER(TRIM(COALESCE(um.email,''))) = :em
                 OR (:dig <> '' AND (REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g') = :dig OR REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g') = :dig))
              )
            LIMIT 1
        ");
        $stRep->execute(['em' => $emailLower, 'dig' => $digits]);
        $rep = $stRep->fetch(PDO::FETCH_ASSOC);
        if ($rep) {
            $emailRep = trim((string)$rep['email']);
            if (!filter_var($emailRep, FILTER_VALIDATE_EMAIL)) {
                $emailRep = trim((string)($rep['email_mercos'] ?? ''));
            }
            if (!filter_var($emailRep, FILTER_VALIDATE_EMAIL)) {
                return ['error' => 'Representante encontrado no ERP, mas sem e-mail válido cadastrado. Solicite atualização cadastral ao Comercial/TI.'];
            }
            $emailRep = mb_strtolower($emailRep, 'UTF-8');
            $razaoRep = $this->utf8($rep['razao']);
            if (!$provisionar) {
                return ['conta' => ['id' => 0, 'perfil' => 'REPRESENTANTE', 'email' => $emailRep, 'nome' => $razaoRep]];
            }
            $this->pdo->prepare("
                INSERT INTO b2b_portal_contas (perfil, idcliforemp, cnpj_cpf, email, nome, senha_hash, status, idvendedor)
                VALUES ('REPRESENTANTE', :idrep, :doc, :em, :nome, '', 'ATIVO', :idrep)
            ")->execute([
                'idrep' => (int)$rep['idcliforemp'],
                'doc' => $rep['doc'] !== '' ? $rep['doc'] : (string)$rep['idcliforemp'],
                'em' => $emailRep,
                'nome' => $razaoRep,
            ]);
            $idNova = (int)$this->pdo->lastInsertId('b2b_portal_contas_id_seq');
            $nova = $this->pdo->query("SELECT * FROM b2b_portal_contas WHERE id = {$idNova}")->fetch(PDO::FETCH_ASSOC);
            return ['conta' => $nova];
        }

        // 2. Verifica se é Cliente ativo no ERP (cliforemp + cliente)
        $stCli = $this->pdo->prepare("
            SELECT c.idcliforemp, c.razao, c.email, COALESCE(cl.idvendedor, 0) AS idvendedor,
                   COALESCE(NULLIF(REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g'), ''), REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g'), '') AS doc
            FROM cliforemp c
            JOIN cliente cl ON cl.idcliforemp = c.idcliforemp
            WHERE COALESCE(c.inativo, 'N') = 'N'
              AND (
                    LOWER(TRIM(COALESCE(c.email,''))) = :em
                 OR (:dig <> '' AND (REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g') = :dig OR REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g') = :dig))
              )
            LIMIT 1
        ");
        $stCli->execute(['em' => $emailLower, 'dig' => $digits]);
        $cli = $stCli->fetch(PDO::FETCH_ASSOC);
        if ($cli) {
            $emailCli = mb_strtolower(trim((string)$cli['email']), 'UTF-8');
            if (!filter_var($emailCli, FILTER_VALIDATE_EMAIL)) {
                return ['error' => 'Cliente encontrado no ERP, porém sem e-mail válido cadastrado. Use a aba "Solicitar Cadastro" para informar seu e-mail.'];
            }
            $razaoCli = $this->utf8($cli['razao']);
            if (!$provisionar) {
                return ['conta' => ['id' => 0, 'perfil' => 'CLIENTE', 'email' => $emailCli, 'nome' => $razaoCli]];
            }
            $this->pdo->prepare("
                INSERT INTO b2b_portal_contas (perfil, idcliforemp, cnpj_cpf, email, nome, senha_hash, status, idvendedor)
                VALUES ('CLIENTE', :idcli, :doc, :em, :nome, '', 'ATIVO', :vend)
            ")->execute([
                'idcli' => (int)$cli['idcliforemp'],
                'doc' => $cli['doc'] !== '' ? $cli['doc'] : (string)$cli['idcliforemp'],
                'em' => $emailCli,
                'nome' => $razaoCli,
                'vend' => (int)$cli['idvendedor'],
            ]);
            $idNova = (int)$this->pdo->lastInsertId('b2b_portal_contas_id_seq');
            $nova = $this->pdo->query("SELECT * FROM b2b_portal_contas WHERE id = {$idNova}")->fetch(PDO::FETCH_ASSOC);
            return ['conta' => $nova];
        }

        return ['error' => 'Nenhum cliente ou representante ativo encontrado para este E-mail ou CNPJ/CPF.'];
    }

    /**
     * POST /v1/portal-cliente/auth/redefinir-senha (Pública)
     */
    public function redefinirSenha(Request $request, Response $response): Response
    {
        $b = $this->body($request);
        $idconta = (int)($b['desafio_id'] ?? 0);
        $codigo = trim((string)($b['codigo'] ?? ''));
        $novaSenha = (string)($b['nova_senha'] ?? '');

        if ($idconta <= 0 || !preg_match('/^\d{6}$/', $codigo) || strlen($novaSenha) < 6) {
            return $this->json($response, ['success' => false, 'error' => 'Código de 6 dígitos e nova senha (mín. 6 caracteres) são obrigatórios.'], 400);
        }

        $stTok = $this->pdo->prepare("
            SELECT * FROM b2b_portal_tokens
            WHERE idconta = :id AND tipo = 'RESET_SENHA' AND usado = FALSE AND expira_em >= NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stTok->execute(['id' => $idconta]);
        $tok = $stTok->fetch(PDO::FETCH_ASSOC);

        if (!$tok || !hash_equals((string)$tok['codigo'], $codigo)) {
            return $this->json($response, ['success' => false, 'error' => 'Código inválido ou expirado.'], 401);
        }

        $this->pdo->prepare("UPDATE b2b_portal_tokens SET usado = TRUE WHERE id = :id")->execute(['id' => $tok['id']]);
        $this->pdo->prepare("UPDATE b2b_portal_contas SET senha_hash = :h, status = CASE WHEN status = 'BLOQUEADO' THEN 'BLOQUEADO' ELSE 'ATIVO' END, atualizado_em = NOW() WHERE id = :id")
            ->execute(['h' => password_hash($novaSenha, PASSWORD_BCRYPT), 'id' => $idconta]);

        return $this->json($response, ['success' => true, 'message' => 'Senha redefinida com sucesso! Já pode acessar sua conta.']);
    }

    /**
     * Resolve o escopo hierárquico do usuário autenticado (B2B ou Portal Interno).
     */
    private function resolverEscopo(Request $request): array
    {
        $u = (array)($request->getAttribute('user') ?? []);
        if (!empty($u['b2b'])) {
            return [
                'perfil' => strtoupper((string)($u['perfil_b2b'] ?? 'CLIENTE')),
                'nome' => (string)($u['username'] ?? ''),
                'idcliforemp' => (int)($u['idcliforemp'] ?? 0),
                'idvendedor' => (int)($u['idvendedor'] ?? 0),
                'representantes' => (int)($u['idvendedor'] ?? 0) > 0 ? [(int)$u['idvendedor']] : [],
                'gestores' => array_values(array_filter(array_map('intval', explode(',', (string)($u['dash_gestores'] ?? ''))))),
                'is_admin' => !empty($u['is_admin']),
            ];
        }

        // Usuário autenticado via Portal Interno (Admin / Gestor / Representante)
        $idusuario = (int)($u['idusuario'] ?? 0);
        $isAdmin = !empty($u['is_admin']) || in_array('admin', (array)($u['permissoes'] ?? []), true);
        $st = $this->pdo->prepare("SELECT idcliforemp, username, dash_gestores, COALESCE(dash_representantes, '') AS dash_representantes FROM usuario WHERE idusuario = :id");
        $st->execute(['id' => $idusuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $gestores = array_values(array_filter(array_map('intval', explode(',', (string)($row['dash_gestores'] ?? '')))));
        $reps = array_values(array_filter(array_map('intval', explode(',', (string)($row['dash_representantes'] ?? '')))));
        $idCliUser = (int)($row['idcliforemp'] ?? 0);

        if ($isAdmin) {
            $perfil = 'ADMIN';
        } elseif ($gestores !== [] && $reps === []) {
            $perfil = 'GESTOR';
        } else {
            $perfil = ($gestores !== []) ? 'GESTOR' : 'REPRESENTANTE';
            if ($reps === [] && $idCliUser > 0) {
                $reps = [$idCliUser];
            }
        }

        return [
            'perfil' => $perfil,
            'nome' => $this->utf8($row['username'] ?? ($u['username'] ?? '')),
            'idcliforemp' => $idCliUser,
            'idvendedor' => $reps[0] ?? $idCliUser,
            'representantes' => $reps,
            'gestores' => $gestores,
            'is_admin' => $isAdmin,
        ];
    }

    /**
     * GET /v1/portal-cliente/contexto (Protegida)
     * Retorna perfil, representantes permitidos (se Gestor/Admin) e clientes liberados na hierarquia.
     */
    public function contexto(Request $request, Response $response): Response
    {
        $esc = $this->resolverEscopo($request);
        $q = $request->getQueryParams();
        $repFiltro = (int)($q['idvendedor'] ?? 0);
        $busca = trim((string)($q['busca'] ?? ''));

        $representantes = [];
        if (in_array($esc['perfil'], ['ADMIN', 'GESTOR'], true) || count($esc['representantes'] ?? []) > 1) {
            $whereRep = "COALESCE(c.inativo,'N')='N' AND EXISTS (SELECT 1 FROM cliente cl WHERE cl.idvendedor = c.idcliforemp)";
            if (!empty($esc['representantes'])) {
                $inR = implode(',', array_map('intval', $esc['representantes']));
                $whereRep .= " AND c.idcliforemp IN ($inR)";
            } elseif ($esc['perfil'] === 'GESTOR' && $esc['gestores'] !== []) {
                $inG = implode(',', $esc['gestores']);
                $whereRep .= " AND EXISTS (SELECT 1 FROM pedido p WHERE p.idvendrepre = c.idcliforemp AND p.idsupervisor IN ($inG))";
            }
            foreach ($this->pdo->query("SELECT c.idcliforemp AS id, c.razao AS nome FROM cliforemp c WHERE $whereRep ORDER BY c.razao LIMIT 150", PDO::FETCH_ASSOC) as $r) {
                $representantes[] = ['id' => (int)$r['id'], 'nome' => $this->utf8($r['nome'])];
            }
        }

        // Lista de clientes conforme hierarquia estrita
        $params = [];
        $whereCli = ["COALESCE(c.inativo,'N') = 'N'"];
        if ($esc['perfil'] === 'CLIENTE') {
            $whereCli[] = 'c.idcliforemp = :idcli_lock';
            $params['idcli_lock'] = $esc['idcliforemp'];
        } elseif ($esc['perfil'] === 'REPRESENTANTE') {
            $repsLock = !empty($esc['representantes']) ? array_map('intval', $esc['representantes']) : [(int)$esc['idvendedor']];
            if ($repFiltro > 0 && in_array($repFiltro, $repsLock, true)) {
                $repsLock = [$repFiltro];
            }
            $inReps = implode(',', $repsLock ?: [-1]);
            $whereCli[] = "cl.idvendedor IN ($inReps)";
        } else {
            if ($repFiltro > 0) {
                if (!empty($esc['representantes']) && !in_array($repFiltro, array_map('intval', $esc['representantes']), true)) {
                    $whereCli[] = '1 = 0';
                } else {
                    $whereCli[] = 'cl.idvendedor = :idrep_f';
                    $params['idrep_f'] = $repFiltro;
                }
            } elseif (!empty($esc['representantes'])) {
                $inReps = implode(',', array_map('intval', $esc['representantes']));
                $whereCli[] = "cl.idvendedor IN ($inReps)";
            } elseif ($esc['perfil'] === 'GESTOR' && $esc['gestores'] !== []) {
                $inG = implode(',', $esc['gestores']);
                $whereCli[] = "EXISTS (SELECT 1 FROM pedido p WHERE p.idcliforemp = c.idcliforemp AND p.idsupervisor IN ($inG))";
            }
        }

        if ($busca !== '') {
            $whereCli[] = "(c.razao ILIKE :b OR COALESCE(c.fantasia,'') ILIKE :b OR COALESCE(c.cnpj,'') ILIKE :b OR c.idcliforemp::text = :b_ex)";
            $params['b'] = '%' . $busca . '%';
            $params['b_ex'] = $busca;
        }

        $sqlCli = "
            SELECT c.idcliforemp, c.razao, COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS doc,
                   COALESCE(cid.descricao,'') AS cidade, COALESCE(c.uf,'') AS uf,
                   COALESCE(cl.idvendedor,0) AS idvendedor, COALESCE(v.razao,'') AS vendedor_nome
            FROM cliforemp c
            JOIN cliente cl ON cl.idcliforemp = c.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN cliforemp v ON v.idcliforemp = cl.idvendedor
            WHERE " . implode(' AND ', $whereCli) . "
            ORDER BY c.razao
            LIMIT 80
        ";
        $stCli = $this->pdo->prepare($sqlCli);
        $stCli->execute($params);
        $clientes = [];
        foreach ($stCli->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $clientes[] = [
                'idcliforemp' => (int)$c['idcliforemp'],
                'razao' => $this->utf8($c['razao']),
                'doc' => trim((string)$c['doc']),
                'cidade' => $this->utf8($c['cidade']),
                'uf' => trim((string)$c['uf']),
                'idvendedor' => (int)$c['idvendedor'],
                'vendedor_nome' => $this->utf8($c['vendedor_nome']),
            ];
        }

        return $this->json($response, [
            'success' => true,
            'escopo' => $esc,
            'representantes' => $representantes,
            'clientes' => $clientes,
        ]);
    }

    /**
     * Verifica se o cliente solicitado pertence estritamente à hierarquia do usuário.
     */
    private function validarAcessoCliente(array $esc, int $idCliente): bool
    {
        if ($idCliente <= 0) {
            return false;
        }
        if ($esc['perfil'] === 'ADMIN') {
            return true;
        }
        if ($esc['perfil'] === 'CLIENTE') {
            return $idCliente === $esc['idcliforemp'];
        }
        if ($esc['perfil'] === 'REPRESENTANTE') {
            $repsLock = !empty($esc['representantes']) ? array_map('intval', $esc['representantes']) : [(int)$esc['idvendedor']];
            $inReps = implode(',', $repsLock ?: [-1]);
            $st = $this->pdo->prepare("SELECT 1 FROM cliente WHERE idcliforemp = :c AND idvendedor IN ($inReps)");
            $st->execute(['c' => $idCliente]);
            return (bool)$st->fetchColumn();
        }
        if (!empty($esc['representantes'])) {
            $inReps = implode(',', array_map('intval', $esc['representantes']));
            $st = $this->pdo->prepare("SELECT 1 FROM cliente WHERE idcliforemp = :c AND idvendedor IN ($inReps)");
            $st->execute(['c' => $idCliente]);
            return (bool)$st->fetchColumn();
        }
        if ($esc['perfil'] === 'GESTOR' && $esc['gestores'] !== []) {
            $inG = implode(',', $esc['gestores']);
            $st = $this->pdo->prepare("SELECT 1 FROM pedido WHERE idcliforemp = :c AND idsupervisor IN ($inG) LIMIT 1");
            $st->execute(['c' => $idCliente]);
            return (bool)$st->fetchColumn();
        }
        return true;
    }

    /**
     * GET /v1/portal-cliente/cliente/{id}/resumo (Protegida)
     * Retorna dados cadastrais, Boletos em Aberto, Notas Fiscais e Pedidos do cliente autorizado.
     */
    public function clienteResumo(Request $request, Response $response, array $args): Response
    {
        $esc = $this->resolverEscopo($request);
        $idCli = (int)($args['id'] ?? 0);

        if (!$this->validarAcessoCliente($esc, $idCli)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso negado: cliente fora da sua hierarquia comercial.'], 403);
        }

        // 1. Dados Cadastrais & Limite de Crédito do Cliente
        $stC = $this->pdo->prepare("
            SELECT c.idcliforemp, c.razao, COALESCE(c.fantasia,'') AS fantasia,
                   COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS doc,
                   COALESCE(c.ie,'') AS ie, COALESCE(c.email,'') AS email, COALESCE(c.fone,'') AS fone,
                   COALESCE(c.endereco,'') AS endereco, COALESCE(c.bairro,'') AS bairro,
                   COALESCE(c.cep,'') AS cep, COALESCE(cid.descricao,'') AS cidade, COALESCE(c.uf,'') AS uf,
                   COALESCE(cl.valorlimitecredito, 0)::numeric(14,2) AS limite_credito,
                   COALESCE(sc.descricao, 'Venda liberada') AS situacao_credito,
                   COALESCE(sc.bloqueia, 'N') AS credito_bloqueado,
                   COALESCE(NULLIF(TRIM(v.fantasia),''), v.razao, '') AS vendedor_nome,
                   COALESCE(v.razao, '') AS vendedor_razao,
                   COALESCE(NULLIF(TRIM(v.fone),'.'), '') AS vendedor_fone,
                   COALESCE(NULLIF(TRIM(v.fone),'.'), '') AS vendedor_celular,
                   COALESCE(NULLIF(TRIM(v.email),'.'), '') AS vendedor_email
            FROM cliforemp c
            LEFT JOIN cliente cl ON cl.idcliforemp = c.idcliforemp
            LEFT JOIN situacaocredito sc ON sc.idsituacao = cl.idsituacaocredito
            LEFT JOIN cliforemp v ON v.idcliforemp = cl.idvendedor
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            WHERE c.idcliforemp = :id
        ");
        $stC->execute(['id' => $idCli]);
        $cli = $stC->fetch(PDO::FETCH_ASSOC);
        if (!$cli) {
            return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado.'], 404);
        }
        foreach ($cli as $k => $v) {
            if (is_string($v)) $cli[$k] = $this->utf8(trim($v));
        }

        // 2. Títulos & Boletos em Aberto (receber + receber_boleto + portador + vínculo NF/Pedido)
        $stBol = $this->pdo->prepare("
            SELECT r.idreceber, r.idfilial, r.documento, r.parcela, r.dataemissao, r.vencimento,
                   COALESCE(r.valororiginal, r.valor, 0)::numeric(14,2) AS valororiginal,
                   COALESCE(r.valorsaldo, 0)::numeric(14,2) AS valorsaldo,
                   COALESCE(NULLIF(TRIM(rb.nossonumeroatual::text),''), NULLIF(TRIM(rb.nossonumero::text),''), r.nossonumero::text, '') AS nossonumero,
                   COALESCE(rb.codigobarra, '') AS codigobarra,
                   COALESCE(rb.numeroboleto, '') AS linha_digitavel,
                   COALESCE(NULLIF(TRIM(rb.shipayqrcode),'.'), '') AS shipayqrcode,
                   COALESCE(rb.idbanco, 0) AS idbanco,
                   COALESCE(p.descricao, 'Outros / Carteira') AS portador_nome,
                   COALESCE(NULLIF(TRIM(r.observacao),'.'), '') AS observacao,
                   COALESCE(NULLIF(r.idnfs, 0), nf.idnfs, 0) AS idnfs,
                   COALESCE(NULLIF(r.numeronf, 0), nf.numeronf, 0) AS numeronf,
                   COALESCE(NULLIF(NULLIF(TRIM(r.pedidos), '.'), ''), NULLIF(NULLIF(TRIM(nf.pedidos), '.'), ''), '') AS pedidos,
                   (CURRENT_DATE - r.vencimento) AS dias_atraso
            FROM receber r
            LEFT JOIN portador p ON p.idportador = r.idportador
            LEFT JOIN nfs nf ON nf.idfilial = r.idfilial AND nf.idnfs = r.idnfs
            LEFT JOIN LATERAL (
                SELECT nossonumero, nossonumeroatual, codigobarra, numeroboleto, shipayqrcode, idbanco
                FROM receber_boleto b
                WHERE b.idreceber = r.idreceber AND b.idfilial = r.idfilial AND COALESCE(b.excluido,'N') = 'N'
                ORDER BY b.idboleto DESC LIMIT 1
            ) rb ON TRUE
            WHERE r.idcliforemp = :id
              AND r.status IN (2, 3)
              AND COALESCE(r.excluido, 'N') = 'N'
              AND r.valorsaldo > 0
            ORDER BY r.vencimento ASC
            LIMIT 120
        ");
        $stBol->execute(['id' => $idCli]);
        $boletos = [];
        $totalAberto = 0.0;
        $totalVencido = 0.0;
        foreach ($stBol->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $saldo = (float)$b['valorsaldo'];
            $atraso = (int)$b['dias_atraso'];
            $totalAberto += $saldo;
            if ($atraso > 0) $totalVencido += $saldo;
            $codBar = trim((string)$b['codigobarra']);
            $boletos[] = [
                'idreceber' => (int)$b['idreceber'],
                'idfilial' => (int)$b['idfilial'],
                'documento' => $this->utf8($b['documento']),
                'parcela' => (string)$b['parcela'],
                'dataemissao' => $b['dataemissao'],
                'vencimento' => $b['vencimento'],
                'valororiginal' => (float)$b['valororiginal'],
                'valorsaldo' => $saldo,
                'nossonumero' => trim((string)$b['nossonumero']),
                'codigobarra' => $codBar,
                'linha_digitavel' => trim((string)$b['linha_digitavel']),
                'shipayqrcode' => $this->utf8(trim((string)$b['shipayqrcode'])),
                'idbanco' => (int)$b['idbanco'],
                'portador_nome' => $this->utf8(trim((string)$b['portador_nome'])),
                'observacao' => $this->utf8(trim((string)$b['observacao'])),
                'idnfs' => (int)$b['idnfs'],
                'numeronf' => (int)$b['numeronf'],
                'pedidos' => $this->utf8(trim((string)$b['pedidos'])),
                'tem_boleto' => ($codBar !== '' && $codBar !== '.'),
                'dias_atraso' => $atraso,
            ];
        }

        // 3. Notas Fiscais (emitidas e canceladas; identifica Bonificação e Pedido vinculado)
        $stNf = $this->pdo->prepare("
            SELECT n.idfilial, n.idnfs, n.numeronf, n.dataemissao, n.valortotalnota::numeric(14,2) AS valortotal,
                   n.status, COALESCE(n.cancelado_sefaz,'N') AS cancelado_sefaz,
                   COALESCE(n.idtransacao, 0) AS idtransacao,
                   COALESCE(nt.descricao, '') AS transacao_nome,
                   COALESCE(n.numero_nf_e,'') AS chave_nfe,
                   COALESCE(NULLIF(TRIM(n.pedidos), '.'), '') AS pedidos
            FROM nfs n
            LEFT JOIN nfs_transacao nt ON nt.idtransacao = n.idtransacao
            WHERE n.idcliforemp = :id
              AND n.status IN (2, 3) AND COALESCE(n.denegada,'N') = 'N'
            ORDER BY n.dataemissao DESC, n.idnfs DESC
            LIMIT 80
        ");
        $stNf->execute(['id' => $idCli]);
        $notas = [];
        foreach ($stNf->fetchAll(PDO::FETCH_ASSOC) as $n) {
            $stNfCode = (int)$n['status'];
            $canc = ($stNfCode === 3 || strtoupper((string)$n['cancelado_sefaz']) === 'S');
            $idTransNf = (int)$n['idtransacao'];
            $transNfNome = $this->utf8(trim((string)$n['transacao_nome']));
            $ehBonif = (stripos($transNfNome, 'BONIF') !== false || in_array($idTransNf, [25, 1001], true));
            $notas[] = [
                'idfilial' => (int)$n['idfilial'],
                'idnfs' => (int)$n['idnfs'],
                'numeronf' => (int)$n['numeronf'],
                'dataemissao' => $n['dataemissao'],
                'valortotal' => (float)$n['valortotal'],
                'status' => $stNfCode,
                'cancelada' => $canc,
                'idtransacao' => $idTransNf,
                'transacao_nome' => $transNfNome,
                'eh_bonificacao' => $ehBonif,
                'chave_nfe' => trim((string)$n['chave_nfe']),
                'pedidos' => $this->utf8(trim((string)$n['pedidos'])),
            ];
        }

        // 4. Pedidos (pedido + transação bonificação + status financeiro + vínculo NF / Embarque)
        $stPed = $this->pdo->prepare("
            SELECT p.idfilial, p.idpedido, p.data, p.status, p.valortotalpedido::numeric(14,2) AS valortotal,
                   COALESCE(p.idtransacao, 0) AS idtransacao,
                   COALESCE(pt.descricao, '') AS transacao_nome,
                   COALESCE(p.aprovado_financeiro, 'S') AS aprovado_financeiro,
                   COALESCE(p.aprovado_comercial, 'S') AS aprovado_comercial,
                   COALESCE(p.aguardandoaprovacaoweb, 'N') AS aguardandoaprovacaoweb,
                   COALESCE(NULLIF(TRIM(p.motivobloqueio), '.'), '') AS motivobloqueio,
                   COALESCE(NULLIF(TRIM(v.fantasia),''), v.razao, '') AS vendedor,
                   COALESCE(nf.idnfs, 0) AS idnfs, COALESCE(nf.numeronf, 0) AS numeronf,
                   COALESCE(nf.idembarque, 0) AS idembarque
            FROM pedido p
            LEFT JOIN pedido_transacao pt ON pt.idtransacao = p.idtransacao
            LEFT JOIN cliforemp v ON v.idcliforemp = p.idvendrepre
            LEFT JOIN LATERAL (
                SELECT n.idnfs, n.numeronf, n.idembarque
                FROM nfs n
                WHERE n.idfilial = p.idfilial AND n.idcliforemp = p.idcliforemp
                  AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N'
                  AND n.pedidos ~ ('\\y' || p.idpedido::text || '\\y')
                ORDER BY n.idnfs DESC LIMIT 1
            ) nf ON TRUE
            WHERE p.idcliforemp = :id
            ORDER BY p.data DESC, p.idpedido DESC
            LIMIT 80
        ");
        $stPed->execute(['id' => $idCli]);
        $pedidos = [];
        foreach ($stPed->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $stCode = (int)$p['status'];
            $idTransPed = (int)$p['idtransacao'];
            $transPedNome = $this->utf8(trim((string)$p['transacao_nome']));
            $ehBonifPed = (stripos($transPedNome, 'BONIF') !== false || in_array($idTransPed, [2, 6, 18], true));
            $motivoBloq = $this->utf8(trim((string)$p['motivobloqueio']));
            $aprovFin = strtoupper((string)$p['aprovado_financeiro']);
            $aprovCom = strtoupper((string)$p['aprovado_comercial']);
            $aguardWeb = strtoupper((string)$p['aguardandoaprovacaoweb']);
            $emAnaliseFin = in_array($stCode, [1, 4], true) && ($aprovFin === 'N' || $aguardWeb === 'S' || $motivoBloq !== '');

            if ($stCode === 5) {
                $stLabel = 'Faturado';
            } elseif ($stCode === 3) {
                $stLabel = 'Cancelado';
            } elseif ($emAnaliseFin) {
                $stLabel = 'Em Análise Financeiro';
            } elseif ($stCode === 1 && $aprovCom === 'N') {
                $stLabel = 'Em Aprovação Comercial';
            } elseif ($stCode === 1) {
                $stLabel = 'Em Aberto';
            } else {
                $stLabel = 'Em Processamento';
            }

            $pedidos[] = [
                'idfilial' => (int)$p['idfilial'],
                'idpedido' => (int)$p['idpedido'],
                'data' => $p['data'],
                'status' => $stCode,
                'status_label' => $stLabel,
                'cancelado' => ($stCode === 3),
                'eh_bonificacao' => $ehBonifPed,
                'em_analise_financeiro' => $emAnaliseFin,
                'motivo_bloqueio' => $motivoBloq,
                'idtransacao' => $idTransPed,
                'transacao_nome' => $transPedNome,
                'valortotal' => (float)$p['valortotal'],
                'vendedor' => $this->utf8(trim((string)$p['vendedor'])),
                'idnfs' => (int)$p['idnfs'],
                'numeronf' => (int)$p['numeronf'],
                'idembarque' => (int)$p['idembarque'],
            ];
        }

        // 5. Itens Vendidos ao Cliente (com foto do produto) + Total 12M
        $stItensCli = $this->pdo->prepare("
            SELECT i.iditem, COALESCE(i.referencia,'') AS referencia, COALESCE(i.descricao,'') AS descricao,
                   COALESCE(m.descricao,'') AS marca, COALESCE(NULLIF(TRIM(i.path_foto_master),'.'), '') AS foto,
                   SUM(ni.qt)::numeric(14,2) AS qtd_total,
                   SUM(ni.valor_total)::numeric(14,2) AS valor_total,
                   MAX(n.dataemissao) AS ultima_compra
            FROM nfs_item ni
            JOIN nfs n ON n.idfilial = ni.idfilial AND n.idnfs = ni.idnfs
            JOIN item i ON i.iditem = ni.iditem
            LEFT JOIN marca m ON m.idmarca = i.idmarca
            WHERE n.idcliforemp = :id
              AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N' AND COALESCE(n.denegada,'N') = 'N'
            GROUP BY i.iditem, i.referencia, i.descricao, m.descricao, i.path_foto_master
            ORDER BY ultima_compra DESC, valor_total DESC
            LIMIT 80
        ");
        $stItensCli->execute(['id' => $idCli]);
        $itensComprados = [];
        foreach ($stItensCli->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $itensComprados[] = [
                'iditem' => (int)$it['iditem'],
                'referencia' => trim((string)$it['referencia']),
                'descricao' => $this->utf8($it['descricao']),
                'marca' => $this->utf8($it['marca']),
                'foto' => $this->utf8(trim((string)$it['foto'])),
                'qtd_total' => (float)$it['qtd_total'],
                'valor_total' => (float)$it['valor_total'],
                'ultima_compra' => $it['ultima_compra'],
            ];
        }

        $total12m = (float)$this->pdo->query("
            SELECT COALESCE(SUM(valortotalnota), 0)
            FROM nfs
            WHERE idcliforemp = {$idCli} AND status = 2 AND COALESCE(cancelado_sefaz,'N')='N' AND COALESCE(denegada,'N')='N'
              AND dataemissao >= (CURRENT_DATE - INTERVAL '12 months')
        ")->fetchColumn();

        $limiteTotal = (float)$cli['limite_credito'];
        $limiteDisponivel = max(0.0, round($limiteTotal - $totalAberto, 2));

        return $this->json($response, [
            'success' => true,
            'cliente' => $cli,
            'resumo' => [
                'boletos_qtd' => count($boletos),
                'boletos_aberto' => round($totalAberto, 2),
                'boletos_vencido' => round($totalVencido, 2),
                'notas_qtd' => count($notas),
                'pedidos_qtd' => count($pedidos),
                'itens_qtd' => count($itensComprados),
                'limite_credito' => round($limiteTotal, 2),
                'limite_utilizado' => round($totalAberto, 2),
                'limite_disponivel' => $limiteDisponivel,
                'total_comprado_12m' => round($total12m, 2),
                'ultima_compra' => $notas[0]['dataemissao'] ?? null,
            ],
            'boletos' => $boletos,
            'notas' => $notas,
            'pedidos' => $pedidos,
            'itens_comprados' => $itensComprados,
        ]);
    }

    /**
     * GET /v1/portal-cliente/nota/{idfilial}/{idnfs}/itens
     */
    public function notaItens(Request $request, Response $response, array $args): Response
    {
        $esc = $this->resolverEscopo($request);
        $f = (int)($args['idfilial'] ?? 0);
        $id = (int)($args['idnfs'] ?? 0);

        $st = $this->pdo->prepare("SELECT idcliforemp FROM nfs WHERE idfilial = :f AND idnfs = :id");
        $st->execute(['f' => $f, 'id' => $id]);
        $idCli = (int)$st->fetchColumn();
        if (!$this->validarAcessoCliente($esc, $idCli)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso negado.'], 403);
        }

        $stI = $this->pdo->prepare("
            SELECT ni.iditem, COALESCE(i.referencia,'') AS referencia, COALESCE(i.descricao,'') AS descricao,
                   ni.qt AS quantidade, ni.valor_total::numeric(14,2) AS valor_total, ni.idcfop
            FROM nfs_item ni
            LEFT JOIN item i ON i.iditem = ni.iditem
            WHERE ni.idfilial = :f AND ni.idnfs = :id
            ORDER BY ni.iditemnfs
        ");
        $stI->execute(['f' => $f, 'id' => $id]);
        $itens = array_map(fn($r) => [
            'iditem' => (int)$r['iditem'],
            'referencia' => trim((string)$r['referencia']),
            'descricao' => $this->utf8($r['descricao']),
            'quantidade' => (float)$r['quantidade'],
            'valor_total' => (float)$r['valor_total'],
            'idcfop' => (int)$r['idcfop'],
        ], $stI->fetchAll(PDO::FETCH_ASSOC));

        return $this->json($response, ['success' => true, 'itens' => $itens]);
    }

    /**
     * POST /v1/portal-cliente/preferencias-2fa (Protegida)
     * Permite que a conta B2B logada cadastre seu WhatsApp e escolha o canal padrão de 2FA (email/whatsapp).
     */
    public function atualizarPreferencias2fa(Request $request, Response $response): Response
    {
        $u = (array)($request->getAttribute('user') ?? []);
        $b2bId = (int)($u['b2b_id'] ?? 0);
        if (empty($u['b2b']) || $b2bId <= 0) {
            return $this->json($response, ['success' => false, 'error' => 'Disponível apenas para contas B2B.'], 400);
        }

        $b = $this->body($request);
        $canal = strtolower(trim((string)($b['preferencia_2fa'] ?? 'email')));
        if (!in_array($canal, ['email', 'whatsapp'], true)) {
            $canal = 'email';
        }
        $rawFone = trim((string)($b['whatsapp'] ?? ''));
        $numNorm = '';
        if ($rawFone !== '') {
            $numNorm = \Nutricional\Services\WhatsAppService::normalizarNumero($rawFone) ?: '';
            if ($numNorm === '') {
                return $this->json($response, ['success' => false, 'error' => 'Número de WhatsApp inválido. Informe DDD + número (ex: 48999998888).'], 400);
            }
        }
        if ($canal === 'whatsapp' && $numNorm === '') {
            return $this->json($response, ['success' => false, 'error' => 'Informe um número de WhatsApp para ativar o 2FA por WhatsApp.'], 400);
        }

        $this->pdo->prepare("UPDATE b2b_portal_contas SET whatsapp = :w, preferencia_2fa = :p, atualizado_em = NOW() WHERE id = :id")
            ->execute(['w' => $numNorm, 'p' => $canal, 'id' => $b2bId]);

        return $this->json($response, [
            'success' => true,
            'whatsapp' => $numNorm,
            'preferencia_2fa' => $canal,
            'message' => 'Preferências de 2FA atualizadas com sucesso!',
        ]);
    }

    /**
     * GET /v1/portal-cliente/nota/{idfilial}/{idnfs}/danfe (Protegida)
     * Retorna o espelho completo da NF-e de saída para impressão/PDF (mesmo layout DANFE do Contábil).
     */
    public function notaDanfe(Request $request, Response $response, array $args): Response
    {
        $esc = $this->resolverEscopo($request);
        $f = (int)($args['idfilial'] ?? 0);
        $id = (int)($args['idnfs'] ?? 0);

        $sql = "
            SELECT n.idfilial, n.idnfs AS idnota, n.numeronf, n.idserie,
                   n.dataemissao, n.datasaida AS data_mov,
                   n.idcfop AS cfop_principal, COALESCE(cf.descricao,'') AS natureza_operacao,
                   n.status, COALESCE(n.cancelado_sefaz,'N') AS cancelado_sefaz, COALESCE(n.denegada,'N') AS denegada,
                   COALESCE(n.numeroprotocolo_nf_e,'') AS protocolo, COALESCE(n.numero_nf_e,'') AS chave_nfe,
                   COALESCE(to_char(n.datahora_nf_e,'DD/MM/YYYY HH24:MI:SS'),'') AS datahora_sefaz,
                   COALESCE(n.pedidos,'') AS pedidos, COALESCE(n.observacao,'') AS observacao,
                   f.razao AS filial_razao, f.nome AS filial_nome, f.cnpj AS filial_cnpj, f.ie AS filial_ie,
                   f.endereco AS filial_end, f.bairro AS filial_bairro, f.uf AS filial_uf,
                   c.idcliforemp, COALESCE(c.razao,'CONSUMIDOR') AS parc_razao,
                   COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS parc_doc,
                   COALESCE(c.ie,'') AS parc_ie, COALESCE(c.endereco,'') AS parc_end,
                   COALESCE(c.bairro,'') AS parc_bairro, COALESCE(cid.descricao,'') AS parc_cidade,
                   COALESCE(c.uf, n.uf_destino, '') AS parc_uf,
                   COALESCE(c.cep,'') AS parc_cep, COALESCE(c.fone,'') AS parc_fone,
                   COALESCE(tr.razao,'') AS transp_razao, COALESCE(NULLIF(TRIM(tr.cnpj),''), TRIM(tr.cpf), '') AS transp_doc,
                   COALESCE(n.tipofrete,0) AS tipofrete, COALESCE(n.placa,'') AS placa,
                   COALESCE(n.quant_mercadorias,0) AS quant_volumes,
                   COALESCE(n.pesobruto,0)::numeric(14,3) AS pesobruto,
                   COALESCE(n.pesoliquido,0)::numeric(14,3) AS pesoliquido,
                   COALESCE(n.valortotalitens,0)::numeric(14,2) AS valortotalitens,
                   COALESCE(n.valortotaldesconto,0)::numeric(14,2) AS valortotaldesconto,
                   COALESCE(n.valorfrete,0)::numeric(14,2) AS valorfrete,
                   COALESCE(n.valorseguro,0)::numeric(14,2) AS valorseguro,
                   COALESCE(n.valoroutrasdespesas,0)::numeric(14,2) AS valoroutras,
                   COALESCE(n.valortotalnota,0)::numeric(14,2) AS valortotalnota,
                   COALESCE(n.baseicms,0)::numeric(14,2) AS baseicms,
                   COALESCE(n.valoricms,0)::numeric(14,2) AS valoricms,
                   COALESCE(n.base_substituicaotrib,0)::numeric(14,2) AS base_st,
                   COALESCE(n.valor_substituicaotrib,0)::numeric(14,2) AS valor_st,
                   COALESCE(n.valoripi,0)::numeric(14,2) AS valoripi
            FROM nfs n
            LEFT JOIN filial f ON f.idfilial = n.idfilial
            LEFT JOIN cliforemp c ON c.idcliforemp = n.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            LEFT JOIN cliforemp tr ON tr.idcliforemp = n.idtransportadora
            LEFT JOIN cfop cf ON cf.idcfop = n.idcfop
            WHERE n.idfilial = :f AND n.idnfs = :id
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute(['f' => $f, 'id' => $id]);
        $nota = $st->fetch(PDO::FETCH_ASSOC);
        if (!$nota) {
            return $this->json($response, ['success' => false, 'error' => 'Nota fiscal não encontrada.'], 404);
        }
        if (!$this->validarAcessoCliente($esc, (int)$nota['idcliforemp'])) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        foreach ($nota as $k => $v) {
            if (is_string($v)) $nota[$k] = $this->utf8($v);
        }

        $stI = $this->pdo->prepare("
            SELECT ni.iditem, COALESCE(i.referencia,'') AS referencia, COALESCE(i.descricao,'') AS descricao,
                   'UN'::text AS unidade, COALESCE(i.idclassfiscal::text,'') AS ncm,
                   ni.idcfop, COALESCE(ni.idsituacaotributaria::text,'') AS cst_icms,
                   ni.qt AS quantidade,
                   CASE WHEN ni.qt > 0 THEN (ni.valor_total / ni.qt)::numeric(14,4) ELSE 0 END AS valor_unitario,
                   ni.valor_total::numeric(14,2) AS valor_total,
                   COALESCE(ni.valoricms,0)::numeric(14,2) AS icms_dest,
                   COALESCE(ni.valor_ipi,0)::numeric(14,2) AS ipi
            FROM nfs_item ni
            LEFT JOIN item i ON i.iditem = ni.iditem
            WHERE ni.idfilial = :f AND ni.idnfs = :id
            ORDER BY ni.iditemnfs
        ");
        $stI->execute(['f' => $f, 'id' => $id]);
        $itens = [];
        foreach ($stI->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = [
                'iditem' => (int)$r['iditem'],
                'referencia' => trim((string)$r['referencia']),
                'descricao' => $this->utf8($r['descricao']),
                'unidade' => 'UN',
                'ncm' => trim((string)$r['ncm']),
                'idcfop' => (int)$r['idcfop'],
                'cst_icms' => trim((string)$r['cst_icms']),
                'quantidade' => (float)$r['quantidade'],
                'valor_unitario' => (float)$r['valor_unitario'],
                'valor_total' => (float)$r['valor_total'],
                'icms_dest' => (float)$r['icms_dest'],
                'ipi' => (float)$r['ipi'],
            ];
        }

        return $this->json($response, ['success' => true, 'nota' => $nota, 'itens' => $itens]);
    }

    /**
     * GET /v1/portal-cliente/boleto/{idfilial}/{idreceber}/detalhe (Protegida)
     * Retorna dados bancários completos para renderização do Boleto FEBRABAN conforme o banco registrador.
     */
    public function boletoDetalhe(Request $request, Response $response, array $args): Response
    {
        $esc = $this->resolverEscopo($request);
        $f = (int)($args['idfilial'] ?? 0);
        $id = (int)($args['idreceber'] ?? 0);

        $sql = "
            SELECT r.idreceber, r.idfilial, r.idcliforemp, r.documento, r.parcela, r.dataemissao, r.vencimento,
                   COALESCE(r.valororiginal, r.valor, 0)::numeric(14,2) AS valororiginal,
                   COALESCE(r.valorsaldo, 0)::numeric(14,2) AS valorsaldo,
                   COALESCE(rb.idbanco, 0) AS idbanco,
                   COALESCE(b.nome, '') AS banco_nome,
                   COALESCE(rb.nragencia, '') AS nragencia, COALESCE(rb.digitoagencia, '') AS digitoagencia,
                   COALESCE(rb.nrconta, '') AS nrconta, COALESCE(rb.digitoconta, '') AS digitoconta,
                   COALESCE(rb.carteira, '') AS carteira, COALESCE(rb.convenio, '') AS convenio,
                   COALESCE(NULLIF(TRIM(rb.cedente),''), f.razao, '') AS cedente,
                   COALESCE(NULLIF(TRIM(rb.nossonumero),''), NULLIF(TRIM(rb.nossonumeroatual::text),''), r.nossonumero::text, '') AS nossonumero,
                   COALESCE(rb.numeroboleto, '') AS linha_digitavel,
                   COALESCE(rb.codigobarra, '') AS codigobarra,
                   COALESCE(NULLIF(TRIM(rb.mensagem),'.'), '') AS mensagem,
                   COALESCE(NULLIF(TRIM(rb.shipayqrcode),'.'), '') AS shipayqrcode,
                   f.cnpj AS cedente_cnpj, f.endereco AS cedente_end, f.bairro AS cedente_bairro, f.uf AS cedente_uf,
                   c.razao AS sacado_nome, COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS sacado_doc,
                   COALESCE(c.endereco,'') AS sacado_end, COALESCE(c.bairro,'') AS sacado_bairro,
                   COALESCE(cid.descricao,'') AS sacado_cidade, COALESCE(c.uf,'') AS sacado_uf, COALESCE(c.cep,'') AS sacado_cep
            FROM receber r
            LEFT JOIN LATERAL (
                SELECT * FROM receber_boleto rb
                WHERE rb.idreceber = r.idreceber AND rb.idfilial = r.idfilial AND COALESCE(rb.excluido,'N') = 'N'
                ORDER BY rb.idboleto DESC LIMIT 1
            ) rb ON TRUE
            LEFT JOIN banco b ON b.idbanco = rb.idbanco
            LEFT JOIN filial f ON f.idfilial = r.idfilial
            LEFT JOIN cliforemp c ON c.idcliforemp = r.idcliforemp
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            WHERE r.idfilial = :f AND r.idreceber = :id AND COALESCE(r.excluido,'N') = 'N'
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute(['f' => $f, 'id' => $id]);
        $bol = $st->fetch(PDO::FETCH_ASSOC);
        if (!$bol) {
            return $this->json($response, ['success' => false, 'error' => 'Boleto não encontrado.'], 404);
        }
        if (!$this->validarAcessoCliente($esc, (int)$bol['idcliforemp'])) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        foreach ($bol as $k => $v) {
            if (is_string($v)) $bol[$k] = $this->utf8(trim($v));
        }

        // Metadados visuais FEBRABAN por banco registrador
        $idBanco = (int)$bol['idbanco'];
        if ($idBanco === 0 && strlen(preg_replace('/\D/', '', (string)$bol['codigobarra'])) >= 3) {
            $idBanco = (int)substr(preg_replace('/\D/', '', (string)$bol['codigobarra']), 0, 3);
        }
        $mapaBancos = [
            237 => ['codigo_dv' => '237-2', 'sigla' => 'BRADESCO', 'nome' => 'Banco Bradesco S.A.', 'cor' => '#cc092f', 'local_pagamento' => 'Pagável preferencialmente na Rede Bradesco ou Bradesco Expresso'],
            748 => ['codigo_dv' => '748-X', 'sigla' => 'SICREDI', 'nome' => 'Banco Cooperativo Sicredi S.A.', 'cor' => '#33820d', 'local_pagamento' => 'Pagável preferencialmente nas cooperativas de crédito do Sicredi'],
            1   => ['codigo_dv' => '001-9', 'sigla' => 'BANCO DO BRASIL', 'nome' => 'Banco do Brasil S.A.', 'cor' => '#003882', 'local_pagamento' => 'Pagável em qualquer banco até o vencimento'],
            341 => ['codigo_dv' => '341-7', 'sigla' => 'ITAÚ', 'nome' => 'Itaú Unibanco S.A.', 'cor' => '#ec7000', 'local_pagamento' => 'Até o vencimento, preferencialmente no Itaú'],
            756 => ['codigo_dv' => '756-0', 'sigla' => 'SICOOB', 'nome' => 'Banco Cooperativo Sicoob S.A.', 'cor' => '#003641', 'local_pagamento' => 'Pagável preferencialmente no Sicoob'],
            33  => ['codigo_dv' => '033-7', 'sigla' => 'SANTANDER', 'nome' => 'Banco Santander (Brasil) S.A.', 'cor' => '#ec0000', 'local_pagamento' => 'Pagável preferencialmente no Banco Santander'],
            104 => ['codigo_dv' => '104-0', 'sigla' => 'CAIXA', 'nome' => 'Caixa Econômica Federal', 'cor' => '#005ca9', 'local_pagamento' => 'Pagável em qualquer banco ou lotérica até o vencimento'],
        ];
        $meta = $mapaBancos[$idBanco] ?? [
            'codigo_dv' => str_pad((string)$idBanco, 3, '0', STR_PAD_LEFT),
            'sigla' => $bol['banco_nome'] ?: 'BANCO',
            'nome' => $bol['banco_nome'] ?: 'Instituição Bancária',
            'cor' => '#0f172a',
            'local_pagamento' => 'Pagável em qualquer banco até o vencimento',
        ];
        $bol['idbanco'] = $idBanco;
        $bol['banco_codigo_dv'] = $meta['codigo_dv'];
        $bol['banco_sigla'] = $meta['sigla'];
        $bol['banco_cor'] = $meta['cor'];
        $bol['local_pagamento'] = $meta['local_pagamento'];

        return $this->json($response, ['success' => true, 'boleto' => $bol]);
    }

    /**
     * GET /v1/portal-cliente/pedido/{idfilial}/{idpedido}/itens
     */
    public function pedidoItens(Request $request, Response $response, array $args): Response
    {
        $esc = $this->resolverEscopo($request);
        $f = (int)($args['idfilial'] ?? 0);
        $id = (int)($args['idpedido'] ?? 0);

        $st = $this->pdo->prepare("SELECT idcliforemp FROM pedido WHERE idfilial = :f AND idpedido = :id");
        $st->execute(['f' => $f, 'id' => $id]);
        $idCli = (int)$st->fetchColumn();
        if (!$this->validarAcessoCliente($esc, $idCli)) {
            return $this->json($response, ['success' => false, 'error' => 'Acesso negado.'], 403);
        }

        $stI = $this->pdo->prepare("
            SELECT pi.iditem, COALESCE(i.referencia,'') AS referencia, COALESCE(i.descricao,'') AS descricao,
                   pi.qt AS quantidade, pi.valortotal::numeric(14,2) AS valor_total
            FROM pedido_item pi
            LEFT JOIN item i ON i.iditem = pi.iditem
            WHERE pi.idfilial = :f AND pi.idpedido = :id
            ORDER BY pi.iditempedido
        ");
        $stI->execute(['f' => $f, 'id' => $id]);
        $itens = array_map(fn($r) => [
            'iditem' => (int)$r['iditem'],
            'referencia' => trim((string)$r['referencia']),
            'descricao' => $this->utf8($r['descricao']),
            'quantidade' => (float)$r['quantidade'],
            'valor_total' => (float)$r['valor_total'],
        ], $stI->fetchAll(PDO::FETCH_ASSOC));

        return $this->json($response, ['success' => true, 'itens' => $itens]);
    }
}
