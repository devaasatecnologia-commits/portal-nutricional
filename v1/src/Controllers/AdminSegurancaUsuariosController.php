<?php
namespace Nutricional\Controllers;

use Nutricional\Services\SegurancaUsuario;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Admin: politica de seguranca por usuario (2FA no login, inatividade, duracao do token). */
class AdminSegurancaUsuariosController extends AdminSegredosController
{
    public function __construct()
    {
        parent::__construct();
        SegurancaUsuario::garantirTabela($this->pdo);
    }

    /** GET /v1/admin/seguranca-usuarios */
    public function listarPolitica(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $rows = $this->pdo->query("
            SELECT u.idusuario, u.username,
                   COALESCE(s.exige_2fa_login, false) AS exige_2fa_login,
                   COALESCE(s.inatividade_min, " . SegurancaUsuario::PADRAO_INATIVIDADE_MIN . ") AS inatividade_min,
                   COALESCE(s.token_min, " . SegurancaUsuario::PADRAO_TOKEN_MIN . ") AS token_min,
                   COALESCE(a.ativo, false) AS tem_2fa,
                   s.atualizado_por, s.atualizado_em,                    COALESCE(s.whatsapp, '') AS whatsapp
            FROM usuario u
            LEFT JOIN usuario_seguranca s ON s.idusuario = u.idusuario
            LEFT JOIN admin_2fa a ON a.idusuario = u.idusuario
            WHERE COALESCE(u.inativo, 'N') <> 'S'
            ORDER BY u.username
        ")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['idusuario'] = (int)$r['idusuario'];
            $r['exige_2fa_login'] = (bool)$r['exige_2fa_login'];
            $r['tem_2fa'] = (bool)$r['tem_2fa'];
            $r['inatividade_min'] = (int)$r['inatividade_min'];
            $r['token_min'] = (int)$r['token_min'];
        }
        return $this->json($response, ['usuarios' => $rows]);
    }

    /** POST /v1/admin/seguranca-usuarios/{id}  {exige_2fa_login, inatividade_min, token_min} */
    public function salvarPolitica(Request $request, Response $response, array $args): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $alvo = (int)($args['id'] ?? 0);
        $in = $this->body($request);

        $st = $this->pdo->prepare("SELECT username FROM usuario WHERE idusuario = :id");
        $st->execute(['id' => $alvo]);
        $alvoNome = $st->fetchColumn();
        if ($alvo <= 0 || $alvoNome === false) {
            return $this->json($response, ['error' => 'Usuario nao encontrado'], 404);
        }

        $inat = (int)($in['inatividade_min'] ?? 0);
        $tok = (int)($in['token_min'] ?? 0);
        if ($inat < 1 || $inat > 480) {
            return $this->json($response, ['error' => 'Inatividade deve ficar entre 1 e 480 minutos'], 422);
        }
        if ($tok < 15 || $tok > 1440) {
            return $this->json($response, ['error' => 'Duracao do token deve ficar entre 15 e 1440 minutos'], 422);
        }
        $exige = !empty($in['exige_2fa_login']);

        $temWhats = array_key_exists('whatsapp', $in);
        $whats = null;
        if ($temWhats && trim((string)$in['whatsapp']) !== '') {
            $whats = \Nutricional\Services\WhatsAppService::normalizarNumero((string)$in['whatsapp']);
            if ($whats === null) {
                return $this->json($response, ['error' => 'Numero de WhatsApp invalido. Use DDD + numero (ex.: 11999998888)'], 422);
            }
        }

        // Alterar politica/numero exige TOTP + WhatsApp (se o admin tiver) de quem executa
        if ($r = $this->validarDoisFatores($request, $response, $u, 'seguranca:' . substr((string)$alvoNome, 0, 60), 'politica')) return $r;

        $this->pdo->prepare("
            INSERT INTO usuario_seguranca (idusuario, exige_2fa_login, inatividade_min, token_min, atualizado_por, atualizado_em)
            VALUES (:id, :e, :i, :t, :p, NOW())
            ON CONFLICT (idusuario) DO UPDATE
               SET exige_2fa_login = EXCLUDED.exige_2fa_login, inatividade_min = EXCLUDED.inatividade_min,
                   token_min = EXCLUDED.token_min, atualizado_por = EXCLUDED.atualizado_por, atualizado_em = NOW()
        ")->execute(['id' => $alvo, 'e' => $exige ? 'true' : 'false', 'i' => $inat, 't' => $tok, 'p' => $u['username']]);
        if ($temWhats) {
            $this->pdo->prepare("UPDATE usuario_seguranca SET whatsapp = :w WHERE idusuario = :id")->execute(['w' => $whats, 'id' => $alvo]);
        }

        $this->auditar('seguranca:' . substr((string)$alvoNome, 0, 60), 'politica', $u, $request, true);
        return $this->json($response, ['success' => true]);
    }

    /** POST /v1/admin/seguranca-usuarios/{id}/2fa/resetar  {codigo} */
    public function resetar2fa(Request $request, Response $response, array $args): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $alvo = (int)($args['id'] ?? 0);

        $st = $this->pdo->prepare("SELECT username FROM usuario WHERE idusuario = :id");
        $st->execute(['id' => $alvo]);
        $alvoNome = $st->fetchColumn();
        if ($alvo <= 0 || $alvoNome === false) {
            return $this->json($response, ['error' => 'Usuario nao encontrado'], 404);
        }

        // Resetar 2FA de usuario e acao sensivel: exige 2FA do admin executor
        if ($r = $this->validarDoisFatores($request, $response, $u, '2fa:' . substr((string)$alvoNome, 0, 60), 'reset_admin')) {
            return $r;
        }

        $this->pdo->prepare("DELETE FROM admin_2fa WHERE idusuario = :id")->execute(['id' => $alvo]);
        $this->auditar('2fa:' . substr((string)$alvoNome, 0, 60), 'reset_admin', $u, $request, true);

        return $this->json($response, [
            'success' => true,
            'message' => 'Autenticador 2FA do usuario resetado com sucesso'
        ]);
    }
}
