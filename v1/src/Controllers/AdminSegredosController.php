<?php
namespace Nutricional\Controllers;

use Nutricional\Services\SecretVault;
use Nutricional\Services\SegurancaUsuario;
use Nutricional\Services\Totp;
use Nutricional\Services\WhatsAppCodigo;
use Nutricional\Services\WhatsAppService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Admin: cofre de segredos (tokens/chaves cifrados no banco) e 2FA por TOTP.
 * Valores em claro so saem pelos endpoints de "revelar", que exigem codigo 2FA.
 */
class AdminSegredosController
{
    private const MAX_FALHAS = 5;
    private const JANELA_MIN = 10;

    protected $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->garantirTabelas();
        SegurancaUsuario::garantirTabela($this->pdo);
        try {
            if (!$this->pdo->query("SELECT to_regclass('admin_whatsapp_codigo') IS NOT NULL")->fetchColumn()) {
                WhatsAppCodigo::garantirTabela($this->pdo);
            }
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] tabela whatsapp: ' . $e->getMessage());
        }
    }

    // ======================================================================
    // SEGREDOS
    // ======================================================================

    /** GET /v1/admin/segredos */
    public function listar(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        try {
            return $this->json($response, [
                'segredos' => SecretVault::listar(),
                'doisfatores' => $this->statusDoisFatores($this->usuario($request)),
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] listar: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Falha ao listar segredos'], 500);
        }
    }

    /** POST /v1/admin/segredos  {chave, valor} */
    public function salvar(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $in = $this->body($request);
        $chave = (string)($in['chave'] ?? '');
        $valor = trim((string)($in['valor'] ?? ''));

        if (!SecretVault::chaveValida($chave)) {
            return $this->json($response, ['error' => 'Chave nao permitida'], 400);
        }
        if ($valor === '' || strlen($valor) > 2000) {
            return $this->json($response, ['error' => 'Valor invalido'], 400);
        }
        try {
            SecretVault::set($chave, $valor, $u['username']);
            $this->auditar($chave, 'salvar', $u, $request, true);
            return $this->json($response, ['success' => true, 'message' => 'Segredo salvo (cifrado)']);
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] salvar: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Falha ao salvar segredo'], 500);
        }
    }

    /** DELETE /v1/admin/segredos/{chave} - remove do banco (volta ao .env) */
    public function remover(Request $request, Response $response, array $args): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $chave = (string)($args['chave'] ?? '');
        if (!SecretVault::chaveValida($chave)) {
            return $this->json($response, ['error' => 'Chave nao permitida'], 400);
        }
        try {
            SecretVault::limpar($chave);
            $this->auditar($chave, 'remover', $u, $request, true);
            return $this->json($response, ['success' => true]);
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] remover: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Falha ao remover segredo'], 500);
        }
    }

    /** POST /v1/admin/segredos/{chave}/revelar  {codigo} */
    public function revelar(Request $request, Response $response, array $args): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $chave = (string)($args['chave'] ?? '');
        if (!SecretVault::chaveValida($chave)) {
            return $this->json($response, ['error' => 'Chave nao permitida'], 400);
        }
        if ($r = $this->validarDoisFatores($request, $response, $u, $chave, 'revelar')) return $r;

        $valor = SecretVault::get($chave);
        $this->auditar($chave, 'revelar', $u, $request, true);
        return $this->semCache($this->json($response, ['chave' => $chave, 'valor' => $valor]));
    }

    /** POST /v1/admin/api-tokens/{id}/revelar  {codigo} */
    public function revelarApiToken(Request $request, Response $response, array $args): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $id = (int)($args['id'] ?? 0);
        $rotulo = 'api_token:' . $id;
        if ($r = $this->validarDoisFatores($request, $response, $u, $rotulo, 'revelar')) return $r;

        $stmt = $this->pdo->prepare("SELECT token FROM api_tokens WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $token = $stmt->fetchColumn();
        if ($token === false) {
            return $this->json($response, ['error' => 'Token nao encontrado'], 404);
        }
        $this->auditar($rotulo, 'revelar', $u, $request, true);
        return $this->semCache($this->json($response, ['token' => $token]));
    }

    // ======================================================================
    // 2FA (TOTP)
    // ======================================================================

    /** POST /v1/admin/2fa/iniciar  {senha} - gera segredo pendente */
    public function iniciarDoisFatores(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $in = $this->body($request);

        if (!$this->senhaConfere($u, (string)($in['senha'] ?? ''))) {
            $this->auditar('2fa', 'iniciar', $u, $request, false);
            return $this->json($response, ['error' => 'Senha incorreta'], 403);
        }
        $atual = $this->buscar2fa($u['idusuario']);
        if ($atual && $atual['ativo']) {
            return $this->json($response, ['error' => '2FA ja esta ativo. Desative antes de gerar um novo segredo.'], 409);
        }

        $segredo = Totp::gerarSegredo();
        $this->pdo->prepare("
            INSERT INTO admin_2fa (idusuario, segredo_cifrado, ativo, ultimo_contador, created_at)
            VALUES (:id, :s, false, 0, NOW())
            ON CONFLICT (idusuario) DO UPDATE
               SET segredo_cifrado = EXCLUDED.segredo_cifrado, ativo = false, ultimo_contador = 0, created_at = NOW()
        ")->execute(['id' => $u['idusuario'], 's' => SecretVault::cifrar($segredo)]);

        $this->auditar('2fa', 'iniciar', $u, $request, true);
        return $this->semCache($this->json($response, [
            'segredo' => $segredo,
            'uri' => Totp::uri($segredo, $u['username']),
        ]));
    }

    /** POST /v1/admin/2fa/confirmar  {codigo} - ativa o 2FA */
    public function confirmarDoisFatores(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $in = $this->body($request);
        if ($r = $this->limiteTentativas($response, $u)) return $r;

        $reg = $this->buscar2fa($u['idusuario']);
        if (!$reg) {
            return $this->json($response, ['error' => 'Inicie a configuracao do 2FA primeiro'], 400);
        }
        $segredo = SecretVault::decifrar($reg['segredo_cifrado']);
        $contador = $segredo ? Totp::verificar($segredo, (string)($in['codigo'] ?? ''), (int)$reg['ultimo_contador']) : null;
        if ($contador === null) {
            $this->auditar('2fa', 'confirmar', $u, $request, false);
            return $this->json($response, ['error' => 'Codigo invalido'], 403);
        }
        $this->pdo->prepare("UPDATE admin_2fa SET ativo = true, ultimo_contador = :c WHERE idusuario = :id")
            ->execute(['c' => $contador, 'id' => $u['idusuario']]);
        $this->auditar('2fa', 'confirmar', $u, $request, true);
        return $this->json($response, ['success' => true, 'message' => '2FA ativado']);
    }

    /** POST /v1/admin/2fa/desativar  {senha, codigo} */
    public function desativarDoisFatores(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $in = $this->body($request);

        if (!$this->senhaConfere($u, (string)($in['senha'] ?? ''))) {
            $this->auditar('2fa', 'desativar', $u, $request, false);
            return $this->json($response, ['error' => 'Senha incorreta'], 403);
        }
        if ($r = $this->validarDoisFatores($request, $response, $u, '2fa', 'desativar')) return $r;

        $this->pdo->prepare("DELETE FROM admin_2fa WHERE idusuario = :id")->execute(['id' => $u['idusuario']]);
        $this->auditar('2fa', 'desativar', $u, $request, true);
        return $this->json($response, ['success' => true, 'message' => '2FA desativado']);
    }

    /** GET /v1/admin/segredos/auditoria */
    public function auditoria(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $rows = $this->pdo->query("
            SELECT chave, acao, username, ip, sucesso, created_at
            FROM admin_segredos_log ORDER BY created_at DESC LIMIT 100
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->json($response, ['logs' => $rows]);
    }

    // ======================================================================
    // Internos
    // ======================================================================

    /** Valida o codigo TOTP (exige 2FA ativo). Retorna Response de erro ou null se ok. */
    protected function validarDoisFatores(Request $request, Response $response, array $u, string $alvo, string $acao): ?Response
    {
        if ($r = $this->limiteTentativas($response, $u)) return $r;

        $reg = $this->buscar2fa($u['idusuario']);
        if (!$reg || !$reg['ativo']) {
            return $this->json($response, ['error' => 'Ative a verificacao em duas etapas para executar esta acao', 'requer_2fa_setup' => true], 403);
        }
        $codigo = (string)($this->body($request)['codigo'] ?? '');
        $segredo = SecretVault::decifrar($reg['segredo_cifrado']);
        $contador = $segredo ? Totp::verificar($segredo, $codigo, (int)$reg['ultimo_contador']) : null;
        if ($contador === null) {
            $this->auditar($alvo, $acao, $u, $request, false);
            return $this->json($response, ['error' => 'Codigo de verificacao invalido ou expirado'], 403);
        }
        // TOTP valido: confere o WhatsApp (uso unico) e so entao grava o contador
        if ($r = $this->validarWhatsapp($request, $response, $u, $alvo, $acao)) return $r;
        $this->pdo->prepare("UPDATE admin_2fa SET ultimo_contador = :c WHERE idusuario = :id")
            ->execute(['c' => $contador, 'id' => $u['idusuario']]);
        return null;
    }

    /** Exige o codigo de 5 min do WhatsApp quando o usuario tem numero cadastrado e ha provedor. */
    protected function validarWhatsapp(Request $request, Response $response, array $u, string $alvo, string $acao): ?Response
    {
        if (!WhatsAppCodigo::exigido($this->pdo, $u['idusuario'])) {
            return null;
        }
        $cod = trim((string)($this->body($request)['codigo_whatsapp'] ?? ''));
        $res = WhatsAppCodigo::verificar($this->pdo, $u['idusuario'], $cod);
        if ($res === 'ok') {
            return null;
        }
        $this->auditar($alvo, $acao, $u, $request, false);
        $msg = $res === 'expirado'
            ? 'Codigo do WhatsApp ausente ou expirado. Envie um novo codigo.'
            : 'Codigo do WhatsApp incorreto';
        return $this->json($response, ['error' => $msg, 'requer_whatsapp' => true], 403);
    }

    /** POST /v1/admin/whatsapp/enviar - envia codigo de 5 min para o numero cadastrado do usuario logado */
    public function enviarWhatsapp(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        if ($u['idusuario'] <= 0) {
            return $this->json($response, ['error' => 'Nao autenticado'], 401);
        }
        try {
            $res = WhatsAppCodigo::enviar($this->pdo, $u['idusuario'], $u['username'], $request->getServerParams()['REMOTE_ADDR'] ?? null);
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] whatsapp: ' . $e->getMessage());
            return $this->json($response, ['error' => 'Falha ao enviar codigo'], 500);
        }
        if (!$res['ok']) {
            return $this->json($response, ['error' => $res['erro']], $res['status']);
        }
        return $this->json($response, ['success' => true, 'destino' => $res['destino'], 'validade_min' => WhatsAppCodigo::VALIDADE_MIN]);
    }

    /** GET /v1/admin/whatsapp/status - indica se o codigo do WhatsApp sera exigido */
    public function statusWhatsapp(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        $numero = WhatsAppCodigo::numero($this->pdo, $u['idusuario']);
        return $this->json($response, [
            'provedor' => WhatsAppService::provedor(),
            'exigido' => WhatsAppCodigo::exigido($this->pdo, $u['idusuario']),
            'destino' => $numero ? WhatsAppService::mascarar($numero) : null,
        ]);
    }

    protected function limiteTentativas(Response $response, array $u): ?Response
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM admin_segredos_log
            WHERE idusuario = :id AND sucesso = false
              AND created_at > NOW() - INTERVAL '" . self::JANELA_MIN . " minutes'
        ");
        $stmt->execute(['id' => $u['idusuario']]);
        if ((int)$stmt->fetchColumn() >= self::MAX_FALHAS) {
            return $this->json($response, ['error' => 'Muitas tentativas. Aguarde ' . self::JANELA_MIN . ' minutos.'], 429);
        }
        return null;
    }

    protected function buscar2fa(int $idusuario): ?array
    {
        $stmt = $this->pdo->prepare("SELECT segredo_cifrado, ativo, ultimo_contador FROM admin_2fa WHERE idusuario = :id");
        $stmt->execute(['id' => $idusuario]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    private function statusDoisFatores(array $u): array
    {
        $r = $this->buscar2fa($u['idusuario']);
        return ['ativo' => (bool)($r['ativo'] ?? false), 'pendente' => $r && !$r['ativo']];
    }

    /** Confere a senha no mesmo formato usado pelo login (MD5 de USERNAME+senha). */
    protected function senhaConfere(array $u, string $senha): bool
    {
        if ($senha === '') {
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT senha FROM usuario WHERE idusuario = :id");
        $stmt->execute(['id' => $u['idusuario']]);
        $hash = (string)$stmt->fetchColumn();
        $calc = strtoupper(md5(strtoupper($u['username']) . $senha));
        return $hash !== '' && hash_equals(strtoupper($hash), $calc);
    }

    protected function auditar(string $chave, string $acao, array $u, Request $request, bool $sucesso): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO admin_segredos_log (chave, acao, idusuario, username, ip, sucesso)
                VALUES (:c, :a, :i, :u, :ip, :s)
            ")->execute([
                'c' => $chave, 'a' => $acao, 'i' => $u['idusuario'], 'u' => $u['username'],
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
                's' => $sucesso ? 'true' : 'false',
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] auditoria falhou: ' . $e->getMessage());
        }
    }

    protected function usuario(Request $request): array
    {
        $u = (array)($request->getAttribute('user') ?? []);
        return [
            'idusuario' => (int)($u['idusuario'] ?? 0),
            'username' => (string)($u['username'] ?? ''),
            'is_admin' => !empty($u['is_admin']) || in_array('admin', (array)($u['permissoes'] ?? []), true),
        ];
    }

    protected function exigirAdmin(Request $request, Response $response): ?Response
    {
        $u = $this->usuario($request);
        if (!$u['is_admin'] || $u['idusuario'] <= 0) {
            return $this->json($response, ['error' => 'Acesso restrito a administradores'], 403);
        }
        return null;
    }

    protected function body(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }
        $request->getBody()->rewind();
        $dec = json_decode((string)$request->getBody(), true);
        return is_array($dec) ? $dec : [];
    }

    private function garantirTabelas(): void
    {
        // Sem permissao de DDL: rodar docs/segredos_migration.sql com o dono do banco.
        try {
            $existem = $this->pdo->query("SELECT to_regclass('admin_2fa') IS NOT NULL AND to_regclass('admin_segredos_log') IS NOT NULL")->fetchColumn();
            if ($existem) {
                return;
            }
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_2fa (
                    idusuario INT PRIMARY KEY,
                    segredo_cifrado TEXT NOT NULL,
                    ativo BOOLEAN NOT NULL DEFAULT false,
                    ultimo_contador BIGINT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS admin_segredos_log (
                    id BIGSERIAL PRIMARY KEY,
                    chave VARCHAR(80) NOT NULL,
                    acao VARCHAR(20) NOT NULL,
                    idusuario INT,
                    username VARCHAR(100),
                    ip VARCHAR(45),
                    sucesso BOOLEAN NOT NULL DEFAULT true,
                    created_at TIMESTAMP DEFAULT NOW()
                );
            ");
        } catch (\Throwable $e) {
            error_log('[AdminSegredos] CREATE TABLE ignorado: ' . $e->getMessage());
        }
    }

    protected function semCache(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'no-store, private')->withHeader('Pragma', 'no-cache');
    }

    protected function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
