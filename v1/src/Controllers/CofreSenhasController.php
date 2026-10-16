<?php
namespace Nutricional\Controllers;

use Nutricional\Services\Totp;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Cofre pessoal de senhas do administrador.
 *
 * A chave de cifragem deriva da SENHA MESTRA (PBKDF2-SHA256, 600k) e nunca e gravada:
 * so o salt e um verificador (HMAC de outra metade da chave). Quem obtiver o banco e o
 * .env continua sem conseguir ler os itens. Senha mestra perdida = itens irrecuperaveis.
 * Abrir o cofre exige senha mestra + codigo TOTP; o "ticket" resultante vale 5 minutos
 * e a senha mestra e reenviada a cada operacao (o navegador a mantem so em memoria).
 */
class CofreSenhasController extends AdminSegredosController
{
    private const ITERACOES = 600000;
    private const TICKET_SEG = 300;
    private const MIN_MESTRA = 12;

    public function __construct()
    {
        parent::__construct();
        $this->garantirTabelasCofre();
    }

    /** GET /v1/admin/cofre/status */
    public function status(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $st = $this->pdo->prepare("SELECT 1 FROM cofre_mestre WHERE idusuario = :id");
        $st->execute(['id' => $u['idusuario']]);
        $d = $this->buscar2fa($u['idusuario']);
        return $this->semCache($this->json($response, [
            'configurado' => (bool)$st->fetchColumn(),
            'tem_2fa' => (bool)($d['ativo'] ?? false),
        ]));
    }

    /** POST /v1/admin/cofre/criar  {senha_mestra, senha, codigo} */
    public function criar(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $in = $this->body($request);
        $mestra = (string)($in['senha_mestra'] ?? '');

        if (strlen($mestra) < self::MIN_MESTRA) {
            return $this->json($response, ['error' => 'A senha mestra deve ter ao menos ' . self::MIN_MESTRA . ' caracteres'], 422);
        }
        if (!$this->senhaConfere($u, (string)($in['senha'] ?? ''))) {
            $this->auditar('cofre', 'criar', $u, $request, false);
            return $this->json($response, ['error' => 'Senha de login incorreta'], 403);
        }
        if ($r = $this->validarDoisFatores($request, $response, $u, 'cofre', 'criar')) return $r;

        $st = $this->pdo->prepare("SELECT 1 FROM cofre_mestre WHERE idusuario = :id");
        $st->execute(['id' => $u['idusuario']]);
        if ($st->fetchColumn()) {
            return $this->json($response, ['error' => 'O cofre ja foi criado'], 409);
        }

        $salt = random_bytes(16);
        [, $ver] = $this->derivar($mestra, $salt);
        $this->pdo->prepare("INSERT INTO cofre_mestre (idusuario, salt, verificador) VALUES (:id, :s, :v)")
            ->execute(['id' => $u['idusuario'], 's' => base64_encode($salt), 'v' => $this->verificador($ver)]);
        $this->auditar('cofre', 'criar', $u, $request, true);
        return $this->json($response, ['success' => true]);
    }

    /** POST /v1/admin/cofre/desbloquear  {senha_mestra, codigo} -> ticket */
    public function desbloquear(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        if ($r = $this->limiteTentativas($response, $u)) return $r;

        if ($this->chaveDoCofre($u, (string)($this->body($request)['senha_mestra'] ?? '')) === null) {
            $this->auditar('cofre', 'desbloquear', $u, $request, false);
            return $this->json($response, ['error' => 'Senha mestra incorreta'], 403);
        }
        if ($r = $this->validarDoisFatores($request, $response, $u, 'cofre', 'desbloquear')) return $r;

        $this->auditar('cofre', 'desbloquear', $u, $request, true);
        return $this->semCache($this->json($response, [
            'ticket' => $this->emitirTicket($u['idusuario']),
            'expira_em' => self::TICKET_SEG,
        ]));
    }

    /** GET /v1/admin/cofre/itens  (somente titulos; o resto fica cifrado) */
    public function listarItens(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $st = $this->pdo->prepare("SELECT id, titulo, updated_at FROM cofre_itens WHERE idusuario = :id ORDER BY titulo");
        $st->execute(['id' => $u['idusuario']]);
        return $this->semCache($this->json($response, ['itens' => $st->fetchAll(\PDO::FETCH_ASSOC)]));
    }

    /** POST /v1/admin/cofre/itens  {ticket, senha_mestra, id?, titulo, login, url, senha, notas} */
    public function salvarItem(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        $in = $this->body($request);
        [$chave, $erro] = $this->autorizar($request, $response, $u, $in, 'salvar');
        if ($erro) return $erro;

        $titulo = trim((string)($in['titulo'] ?? ''));
        if ($titulo === '' || mb_strlen($titulo) > 120) {
            return $this->json($response, ['error' => 'Titulo obrigatorio (ate 120 caracteres)'], 422);
        }
        $payload = json_encode([
            'login' => (string)($in['login'] ?? ''),
            'url' => (string)($in['url'] ?? ''),
            'senha' => (string)($in['senha'] ?? ''),
            'notas' => (string)($in['notas'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);
        $cifrado = $this->cifrar($payload, $chave, $u['idusuario']);

        $id = (int)($in['id'] ?? 0);
        if ($id > 0) {
            $st = $this->pdo->prepare("UPDATE cofre_itens SET titulo = :t, dados_cifrados = :d, updated_at = NOW() WHERE id = :id AND idusuario = :u");
            $st->execute(['t' => $titulo, 'd' => $cifrado, 'id' => $id, 'u' => $u['idusuario']]);
            if ($st->rowCount() === 0) {
                return $this->json($response, ['error' => 'Item nao encontrado'], 404);
            }
        } else {
            $st = $this->pdo->prepare("INSERT INTO cofre_itens (idusuario, titulo, dados_cifrados) VALUES (:u, :t, :d) RETURNING id");
            $st->execute(['u' => $u['idusuario'], 't' => $titulo, 'd' => $cifrado]);
            $id = (int)$st->fetchColumn();
        }
        $this->auditar('cofre:' . $id, 'salvar', $u, $request, true);
        return $this->json($response, ['success' => true, 'id' => $id]);
    }

    /** POST /v1/admin/cofre/itens/{id}/revelar  {ticket, senha_mestra} */
    public function revelarItem(Request $request, Response $response, array $args): Response
    {
        $u = $this->usuario($request);
        $in = $this->body($request);
        [$chave, $erro] = $this->autorizar($request, $response, $u, $in, 'revelar');
        if ($erro) return $erro;

        $id = (int)($args['id'] ?? 0);
        $st = $this->pdo->prepare("SELECT titulo, dados_cifrados FROM cofre_itens WHERE id = :id AND idusuario = :u");
        $st->execute(['id' => $id, 'u' => $u['idusuario']]);
        $item = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$item) {
            return $this->json($response, ['error' => 'Item nao encontrado'], 404);
        }
        $dados = json_decode($this->decifrar($item['dados_cifrados'], $chave, $u['idusuario']), true);
        if (!is_array($dados)) {
            return $this->json($response, ['error' => 'Falha ao decifrar o item'], 500);
        }
        $this->auditar('cofre:' . $id, 'revelar', $u, $request, true);
        return $this->semCache($this->json($response, ['titulo' => $item['titulo']] + $dados));
    }

    /** POST /v1/admin/cofre/itens/{id}/remover  {ticket, senha_mestra} */
    public function removerItem(Request $request, Response $response, array $args): Response
    {
        $u = $this->usuario($request);
        $in = $this->body($request);
        [, $erro] = $this->autorizar($request, $response, $u, $in, 'remover');
        if ($erro) return $erro;

        $id = (int)($args['id'] ?? 0);
        $this->pdo->prepare("DELETE FROM cofre_itens WHERE id = :id AND idusuario = :u")->execute(['id' => $id, 'u' => $u['idusuario']]);
        $this->auditar('cofre:' . $id, 'remover', $u, $request, true);
        return $this->json($response, ['success' => true]);
    }

    // ======================================================================
    // Internos
    // ======================================================================

    /** @return array{0:?string,1:?Response} [chave, resposta de erro] */
    private function autorizar(Request $request, Response $response, array $u, array $in, string $acao): array
    {
        if ($r = $this->exigirAdmin($request, $response)) return [null, $r];
        if ($r = $this->limiteTentativas($response, $u)) return [null, $r];

        if (!$this->ticketValido((string)($in['ticket'] ?? ''), $u['idusuario'])) {
            return [null, $this->json($response, ['error' => 'Cofre bloqueado. Desbloqueie novamente.', 'bloqueado' => true], 401)];
        }
        $chave = $this->chaveDoCofre($u, (string)($in['senha_mestra'] ?? ''));
        if ($chave === null) {
            $this->auditar('cofre', $acao, $u, $request, false);
            return [null, $this->json($response, ['error' => 'Senha mestra incorreta', 'bloqueado' => true], 403)];
        }
        return [$chave, null];
    }

    /** @return array{0:string,1:string} [chave de cifragem, chave do verificador] */
    private function derivar(string $mestra, string $salt): array
    {
        $k = hash_pbkdf2('sha256', $mestra, $salt, self::ITERACOES, 64, true);
        return [substr($k, 0, 32), substr($k, 32, 32)];
    }

    private function verificador(string $ver): string
    {
        return hash_hmac('sha256', 'cofre-v1', $ver);
    }

    /** Chave AES do cofre do usuario, ou null se a senha mestra nao confere. */
    private function chaveDoCofre(array $u, string $mestra): ?string
    {
        $st = $this->pdo->prepare("SELECT salt, verificador FROM cofre_mestre WHERE idusuario = :id");
        $st->execute(['id' => $u['idusuario']]);
        $m = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$m || $mestra === '') {
            return null;
        }
        [$enc, $ver] = $this->derivar($mestra, (string)base64_decode($m['salt'], true));
        return hash_equals($m['verificador'], $this->verificador($ver)) ? $enc : null;
    }

    private function cifrar(string $texto, string $chave, int $idusuario): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($texto, 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, $iv, $tag, 'cofre:' . $idusuario, 16);
        if ($ct === false) {
            throw new \RuntimeException('Falha ao cifrar');
        }
        return base64_encode($iv . $tag . $ct);
    }

    private function decifrar(string $b64, string $chave, int $idusuario): string
    {
        $raw = (string)base64_decode($b64, true);
        $pt = strlen($raw) > 28
            ? openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'cofre:' . $idusuario)
            : false;
        return $pt === false ? '' : $pt;
    }

    private function emitirTicket(int $idusuario): string
    {
        $corpo = rtrim(strtr(base64_encode(json_encode(['u' => $idusuario, 'e' => time() + self::TICKET_SEG, 'n' => bin2hex(random_bytes(8))])), '+/', '-_'), '=');
        return $corpo . '.' . hash_hmac('sha256', $corpo, 'cofre-ticket|' . CHAVE_SECRETA);
    }

    private function ticketValido(string $ticket, int $idusuario): bool
    {
        $partes = explode('.', $ticket);
        if (count($partes) !== 2 || !hash_equals(hash_hmac('sha256', $partes[0], 'cofre-ticket|' . CHAVE_SECRETA), $partes[1])) {
            return false;
        }
        $d = json_decode((string)base64_decode(strtr($partes[0], '-_', '+/'), true), true);
        return is_array($d) && (int)($d['u'] ?? 0) === $idusuario && (int)($d['e'] ?? 0) >= time();
    }

    private function garantirTabelasCofre(): void
    {
        // Sem permissao de DDL: rodar docs/segredos_migration.sql com o dono do banco.
        try {
            if ($this->pdo->query("SELECT to_regclass('cofre_mestre') IS NOT NULL AND to_regclass('cofre_itens') IS NOT NULL")->fetchColumn()) {
                return;
            }
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS cofre_mestre (
                    idusuario INT PRIMARY KEY,
                    salt TEXT NOT NULL,
                    verificador TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT NOW()
                );
                CREATE TABLE IF NOT EXISTS cofre_itens (
                    id BIGSERIAL PRIMARY KEY,
                    idusuario INT NOT NULL,
                    titulo VARCHAR(120) NOT NULL,
                    dados_cifrados TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                );
                CREATE INDEX IF NOT EXISTS idx_cofre_itens_usuario ON cofre_itens (idusuario);
            ");
        } catch (\Throwable $e) {
            error_log('[Cofre] CREATE TABLE ignorado: ' . $e->getMessage());
        }
    }
}
