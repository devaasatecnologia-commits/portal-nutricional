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
        $secret = trim((string)($_ENV['TURNSTILE_SECRET_KEY'] ?? ''));
        if ($secret === '') {
            // Chave ainda não configurada no .env (ambiente local/homologação)
            return true;
        }
        if ($token === '') {
            return false;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query(['secret' => $secret, 'response' => $token]),
                'timeout' => 5,
            ],
        ]);
        $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
        $res = $raw ? json_decode($raw, true) : null;
        return !empty($res['success']);
    }

    /**
     * Envia e-mail transacional via PHPMailer usando credenciais do .env.
     */
    private function enviarEmail(string $para, string $nomePara, string $assunto, string $html): bool
    {
        $host = trim((string)($_ENV['MAIL_HOST'] ?? ''));
        $user = trim((string)($_ENV['MAIL_USER'] ?? ''));
        $pass = trim((string)($_ENV['MAIL_PASS'] ?? ''));
        $port = (int)($_ENV['MAIL_PORT'] ?? 465);

        if ($host === '' || $user === '' || $pass === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->Port = $port;
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->setFrom($user, 'Portal do Cliente - Nutricional');
            $mail->addAddress($para, $nomePara ?: $para);
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = $html;
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
        $siteKey = trim((string)($_ENV['TURNSTILE_SITE_KEY'] ?? ''));
        return $this->json($response, [
            'success' => true,
            'turnstile_ativo' => $siteKey !== '',
            'turnstile_site_key' => $siteKey,
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
            $prov = $this->localizarOuProvisionarContaErp($identificador, false);
            if (!empty($prov['conta'])) {
                return $this->json($response, [
                    'success' => false,
                    'error' => 'Primeiro acesso detectado! Clique em "Primeiro Acesso / Esqueceu a Senha" para receber o código no seu e-mail cadastrado e criar sua senha.',
                ], 403);
            }
            return $this->json($response, ['success' => false, 'error' => 'Credenciais inválidas.'], 401);
        }
        if (trim((string)$conta['senha_hash']) === '') {
            return $this->json($response, [
                'success' => false,
                'error' => 'Senha ainda não definida. Use "Primeiro Acesso / Esqueceu a Senha" para receber o código no e-mail cadastrado e criar sua senha.',
            ], 403);
        }
        if (!password_verify($senha, (string)$conta['senha_hash'])) {
            return $this->json($response, ['success' => false, 'error' => 'Credenciais inválidas.'], 401);
        }
        if (strtoupper((string)$conta['status']) !== 'ATIVO') {
            return $this->json($response, ['success' => false, 'error' => 'Cadastro aguardando aprovação ou bloqueado (' . $conta['status'] . ').'], 403);
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

        return $this->json($response, [
            'success' => true,
            'token' => $jwt,
            'usuario' => [
                'perfil' => strtoupper((string)$conta['perfil']),
                'nome' => $conta['nome'],
                'email' => $conta['email'],
                'idcliforemp' => (int)$conta['idcliforemp'],
            ],
        ]);
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
        $this->enviarEmail(
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
            'message' => 'Código enviado para o e-mail cadastrado: ' . $this->mascararEmail((string)$conta['email']),
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
                'gestores' => array_values(array_filter(array_map('intval', explode(',', (string)($u['dash_gestores'] ?? ''))))),
                'is_admin' => !empty($u['is_admin']),
            ];
        }

        // Usuário autenticado via Portal Interno (Admin / Gestor / Representante)
        $idusuario = (int)($u['idusuario'] ?? 0);
        $isAdmin = !empty($u['is_admin']) || in_array('admin', (array)($u['permissoes'] ?? []), true);
        $st = $this->pdo->prepare("SELECT idcliforemp, username, dash_gestores FROM usuario WHERE idusuario = :id");
        $st->execute(['id' => $idusuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $gestores = array_values(array_filter(array_map('intval', explode(',', (string)($row['dash_gestores'] ?? '')))));

        if ($isAdmin) {
            $perfil = 'ADMIN';
        } elseif ($gestores !== []) {
            $perfil = 'GESTOR';
        } else {
            $perfil = 'REPRESENTANTE';
        }

        return [
            'perfil' => $perfil,
            'nome' => $this->utf8($row['username'] ?? ($u['username'] ?? '')),
            'idcliforemp' => (int)($row['idcliforemp'] ?? 0),
            'idvendedor' => (int)($row['idcliforemp'] ?? 0),
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
        if (in_array($esc['perfil'], ['ADMIN', 'GESTOR'], true)) {
            $whereRep = "COALESCE(c.inativo,'N')='N' AND EXISTS (SELECT 1 FROM cliente cl WHERE cl.idvendedor = c.idcliforemp)";
            if ($esc['perfil'] === 'GESTOR' && $esc['gestores'] !== []) {
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
            $whereCli[] = 'cl.idvendedor = :idrep_lock';
            $params['idrep_lock'] = $esc['idvendedor'];
        } else {
            if ($repFiltro > 0) {
                $whereCli[] = 'cl.idvendedor = :idrep_f';
                $params['idrep_f'] = $repFiltro;
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
            $st = $this->pdo->prepare("SELECT 1 FROM cliente WHERE idcliforemp = :c AND idvendedor = :v");
            $st->execute(['c' => $idCliente, 'v' => $esc['idvendedor']]);
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

        // 1. Dados do Cliente
        $stC = $this->pdo->prepare("
            SELECT c.idcliforemp, c.razao, COALESCE(NULLIF(TRIM(c.cnpj),''), TRIM(c.cpf), '') AS doc,
                   COALESCE(c.email,'') AS email, COALESCE(c.fone,'') AS fone,
                   COALESCE(cid.descricao,'') AS cidade, COALESCE(c.uf,'') AS uf
            FROM cliforemp c
            LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
            WHERE c.idcliforemp = :id
        ");
        $stC->execute(['id' => $idCli]);
        $cli = $stC->fetch(PDO::FETCH_ASSOC);
        if (!$cli) {
            return $this->json($response, ['success' => false, 'error' => 'Cliente não encontrado.'], 404);
        }
        foreach ($cli as $k => $v) {
            if (is_string($v)) $cli[$k] = $this->utf8($v);
        }

        // 2. Boletos em Aberto (receber + receber_boleto)
        $stBol = $this->pdo->prepare("
            SELECT r.idreceber, r.idfilial, r.documento, r.parcela, r.dataemissao, r.vencimento,
                   COALESCE(r.valororiginal, r.valor, 0)::numeric(14,2) AS valororiginal,
                   COALESCE(r.valorsaldo, 0)::numeric(14,2) AS valorsaldo,
                   COALESCE(NULLIF(TRIM(rb.nossonumeroatual::text),''), NULLIF(TRIM(rb.nossonumero::text),''), r.nossonumero::text, '') AS nossonumero,
                   COALESCE(rb.codigobarra, '') AS codigobarra,
                   (CURRENT_DATE - r.vencimento) AS dias_atraso
            FROM receber r
            LEFT JOIN LATERAL (
                SELECT nossonumero, nossonumeroatual, codigobarra
                FROM receber_boleto b
                WHERE b.idreceber = r.idreceber AND b.idfilial = r.idfilial AND COALESCE(b.excluido,'N') = 'N'
                ORDER BY b.idboleto DESC LIMIT 1
            ) rb ON TRUE
            WHERE r.idcliforemp = :id
              AND r.status IN (2, 3)
              AND COALESCE(r.excluido, 'N') = 'N'
              AND r.valorsaldo > 0
            ORDER BY r.vencimento ASC
            LIMIT 100
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
                'codigobarra' => trim((string)$b['codigobarra']),
                'dias_atraso' => $atraso,
            ];
        }

        // 3. Notas Fiscais Emitidas (nfs)
        $stNf = $this->pdo->prepare("
            SELECT n.idfilial, n.idnfs, n.numeronf, n.dataemissao, n.valortotalnota::numeric(14,2) AS valortotal,
                   COALESCE(n.numero_nf_e,'') AS chave_nfe, COALESCE(n.pedidos,'') AS pedidos
            FROM nfs n
            WHERE n.idcliforemp = :id
              AND n.status = 2 AND COALESCE(n.cancelado_sefaz,'N') = 'N' AND COALESCE(n.denegada,'N') = 'N'
            ORDER BY n.dataemissao DESC, n.idnfs DESC
            LIMIT 50
        ");
        $stNf->execute(['id' => $idCli]);
        $notas = [];
        foreach ($stNf->fetchAll(PDO::FETCH_ASSOC) as $n) {
            $notas[] = [
                'idfilial' => (int)$n['idfilial'],
                'idnfs' => (int)$n['idnfs'],
                'numeronf' => (int)$n['numeronf'],
                'dataemissao' => $n['dataemissao'],
                'valortotal' => (float)$n['valortotal'],
                'chave_nfe' => trim((string)$n['chave_nfe']),
                'pedidos' => $this->utf8($n['pedidos']),
            ];
        }

        // 4. Pedidos (pedido)
        $stPed = $this->pdo->prepare("
            SELECT p.idfilial, p.idpedido, p.data, p.status, p.valortotalpedido::numeric(14,2) AS valortotal,
                   COALESCE(v.razao,'') AS vendedor
            FROM pedido p
            LEFT JOIN cliforemp v ON v.idcliforemp = p.idvendrepre
            WHERE p.idcliforemp = :id
            ORDER BY p.data DESC, p.idpedido DESC
            LIMIT 50
        ");
        $stPed->execute(['id' => $idCli]);
        $pedidos = [];
        foreach ($stPed->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $pedidos[] = [
                'idfilial' => (int)$p['idfilial'],
                'idpedido' => (int)$p['idpedido'],
                'data' => $p['data'],
                'status' => (int)$p['status'],
                'valortotal' => (float)$p['valortotal'],
                'vendedor' => $this->utf8($p['vendedor']),
            ];
        }

        return $this->json($response, [
            'success' => true,
            'cliente' => $cli,
            'resumo' => [
                'boletos_qtd' => count($boletos),
                'boletos_aberto' => round($totalAberto, 2),
                'boletos_vencido' => round($totalVencido, 2),
                'notas_qtd' => count($notas),
                'pedidos_qtd' => count($pedidos),
            ],
            'boletos' => $boletos,
            'notas' => $notas,
            'pedidos' => $pedidos,
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
