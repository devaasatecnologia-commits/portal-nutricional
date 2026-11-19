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
        $temTabelaAdmin = (bool)$this->pdo->query("SELECT to_regclass('usuarios_admin') IS NOT NULL")->fetchColumn();
        $sqlAdminCol = $temTabelaAdmin
            ? "EXISTS(SELECT 1 FROM usuarios_admin ua WHERE ua.idusuario = u.idusuario AND ua.ativo = true)"
            : "false";

        $rows = $this->pdo->query("
            SELECT u.idusuario, u.username,
                   COALESCE(u.idcliforemp, 0) AS idcliforemp,
                   COALESCE(s.exige_2fa_login, false) AS exige_2fa_login,
                   COALESCE(s.tipo_2fa, 'totp') AS tipo_2fa,
                   COALESCE(s.inatividade_min, " . SegurancaUsuario::PADRAO_INATIVIDADE_MIN . ") AS inatividade_min,
                   COALESCE(s.token_min, " . SegurancaUsuario::PADRAO_TOKEN_MIN . ") AS token_min,
                   COALESCE(a.ativo, false) AS tem_2fa,
                   {$sqlAdminCol} AS is_admin,
                   (COALESCE(TRIM(u.dash_gestores), '') <> '') AS eh_gestor,
                   ((COALESCE(TRIM(u.dash_representantes), '') <> '') OR EXISTS(SELECT 1 FROM cliente cl WHERE cl.idvendedor = u.idcliforemp AND COALESCE(u.idcliforemp, 0) > 0)) AS eh_rep,
                   s.atualizado_por, s.atualizado_em, COALESCE(s.whatsapp, '') AS whatsapp
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

        $tipo2fa = strtolower(trim((string)($in['tipo_2fa'] ?? '')));
        if ($tipo2fa !== '' && !in_array($tipo2fa, ['totp', 'whatsapp', 'ambos'], true)) {
            return $this->json($response, ['error' => 'Metodo 2FA invalido. Use totp, whatsapp ou ambos.'], 400);
        }
        $stExist = $this->pdo->prepare("SELECT COALESCE(whatsapp, ''), COALESCE(tipo_2fa, 'totp') FROM usuario_seguranca WHERE idusuario = :id");
        $stExist->execute(['id' => $alvo]);
        $existRow = $stExist->fetch(\PDO::FETCH_NUM);
        $whatsAtual = $existRow ? trim((string)$existRow[0]) : '';
        if ($tipo2fa === '') {
            $tipo2fa = ($existRow && in_array($existRow[1], ['totp', 'whatsapp', 'ambos'], true)) ? $existRow[1] : 'totp';
        }
        $whatsFinal = $temWhats ? trim((string)$whats) : $whatsAtual;
        if ($tipo2fa === 'whatsapp' && $whatsFinal === '') {
            return $this->json($response, ['error' => 'Informe um numero de WhatsApp antes de definir o 2FA por WhatsApp.'], 400);
        }

        $this->pdo->prepare("
            INSERT INTO usuario_seguranca (idusuario, exige_2fa_login, tipo_2fa, inatividade_min, token_min, atualizado_por, atualizado_em)
            VALUES (:id, :e, :tipo, :i, :t, :p, NOW())
            ON CONFLICT (idusuario) DO UPDATE
               SET exige_2fa_login = EXCLUDED.exige_2fa_login,
                   tipo_2fa = EXCLUDED.tipo_2fa,
                   inatividade_min = EXCLUDED.inatividade_min,
                   token_min = EXCLUDED.token_min,
                   atualizado_por = EXCLUDED.atualizado_por,
                   atualizado_em = NOW()
        ")->execute(['id' => $alvo, 'e' => $exige ? 'true' : 'false', 'tipo' => $tipo2fa, 'i' => $inat, 't' => $tok, 'p' => $u['username']]);
        if ($temWhats) {
            $this->pdo->prepare("UPDATE usuario_seguranca SET whatsapp = :w WHERE idusuario = :id")->execute(['w' => $whats, 'id' => $alvo]);
        }

        // Sincroniza WhatsApp/preferencia do Representante no Portal B2B (/representante) se houver conta vinculada
        try {
            if ($this->pdo->query("SELECT to_regclass('b2b_portal_contas') IS NOT NULL")->fetchColumn()) {
                $stCli = $this->pdo->prepare("SELECT COALESCE(idcliforemp, 0) FROM usuario WHERE idusuario = :id");
                $stCli->execute(['id' => $alvo]);
                $idCliEmp = (int)$stCli->fetchColumn();
                if ($idCliEmp > 0) {
                    if ($temWhats && $whatsFinal === '') {
                        $this->pdo->prepare("UPDATE b2b_portal_contas SET whatsapp = '', preferencia_2fa = 'email', atualizado_em = NOW() WHERE idcliforemp = :cli AND UPPER(perfil) = 'REPRESENTANTE'")
                            ->execute(['cli' => $idCliEmp]);
                    } elseif ($temWhats && in_array($tipo2fa, ['whatsapp', 'ambos'], true)) {
                        $this->pdo->prepare("UPDATE b2b_portal_contas SET whatsapp = :w, preferencia_2fa = 'whatsapp', atualizado_em = NOW() WHERE idcliforemp = :cli AND UPPER(perfil) = 'REPRESENTANTE'")
                            ->execute(['w' => $whatsFinal, 'cli' => $idCliEmp]);
                    } elseif ($temWhats) {
                        $this->pdo->prepare("UPDATE b2b_portal_contas SET whatsapp = :w, atualizado_em = NOW() WHERE idcliforemp = :cli AND UPPER(perfil) = 'REPRESENTANTE'")
                            ->execute(['w' => $whatsFinal, 'cli' => $idCliEmp]);
                    } elseif ($tipo2fa === 'whatsapp' && $whatsFinal !== '') {
                        $this->pdo->prepare("UPDATE b2b_portal_contas SET preferencia_2fa = 'whatsapp', atualizado_em = NOW() WHERE idcliforemp = :cli AND UPPER(perfil) = 'REPRESENTANTE'")
                            ->execute(['cli' => $idCliEmp]);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminSegurancaUsuarios] sync b2b rep ignorado: ' . $e->getMessage());
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

    /**
     * GET /v1/admin/seguranca-b2b
     * Lista Representantes ativos do ERP e Contas B2B com configuracao 2FA (WhatsApp / E-mail).
     */
    public function listarB2b(Request $request, Response $response): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;

        $temB2b = (bool)$this->pdo->query("SELECT to_regclass('b2b_portal_contas') IS NOT NULL")->fetchColumn();
        if (!$temB2b) {
            return $this->json($response, [
                'contas' => [],
                'whatsapp_provedor' => \Nutricional\Services\WhatsAppService::provedor(),
            ]);
        }

        $rows = $this->pdo->query("
            WITH contas AS (
                SELECT id AS conta_id,
                       UPPER(COALESCE(perfil, 'CLIENTE')) AS perfil,
                       COALESCE(idcliforemp, 0) AS idcliforemp,
                       COALESCE(nome, '') AS nome,
                       COALESCE(email, '') AS email,
                       COALESCE(cnpj_cpf, '') AS cnpj_cpf,
                       COALESCE(whatsapp, '') AS whatsapp,
                       COALESCE(NULLIF(preferencia_2fa, ''), 'email') AS preferencia_2fa,
                       COALESCE(status, 'ATIVO') AS status,
                       ultimo_login
                FROM b2b_portal_contas
            ),
            reps_erp AS (
                SELECT DISTINCT ON (c.idcliforemp)
                       c.idcliforemp,
                       c.razao AS nome,
                       COALESCE(NULLIF(TRIM(c.email), ''), (SELECT TRIM(um.email) FROM usuarios_mercos um WHERE um.idcolaborador = c.idcliforemp AND COALESCE(TRIM(um.email), '') <> '' LIMIT 1), '') AS email,
                       COALESCE(NULLIF(REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g'), ''), REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g'), '') AS cnpj_cpf,
                       COALESCE(c.fone, '') AS fone_erp
                FROM cliforemp c
                WHERE COALESCE(c.inativo, 'N') = 'N'
                  AND EXISTS (SELECT 1 FROM cliente cl WHERE cl.idvendedor = c.idcliforemp)
                ORDER BY c.idcliforemp
            )
            SELECT COALESCE(ct.conta_id, 0) AS conta_id,
                   'REPRESENTANTE' AS perfil,
                   r.idcliforemp,
                   COALESCE(NULLIF(ct.nome, ''), r.nome) AS nome,
                   COALESCE(NULLIF(ct.email, ''), r.email) AS email,
                   COALESCE(NULLIF(ct.cnpj_cpf, ''), r.cnpj_cpf) AS cnpj_cpf,
                   COALESCE(ct.whatsapp, '') AS whatsapp,
                   r.fone_erp,
                   COALESCE(ct.preferencia_2fa, 'email') AS preferencia_2fa,
                   COALESCE(ct.status, 'NAO_ATIVADO') AS status,
                   (ct.conta_id IS NOT NULL) AS tem_conta,
                   ct.ultimo_login
            FROM reps_erp r
            LEFT JOIN contas ct ON ct.idcliforemp = r.idcliforemp AND ct.perfil = 'REPRESENTANTE'

            UNION ALL

            SELECT ct.conta_id, ct.perfil, ct.idcliforemp, ct.nome, ct.email, ct.cnpj_cpf,
                   ct.whatsapp, COALESCE(c.fone, '') AS fone_erp,
                   ct.preferencia_2fa, ct.status, true AS tem_conta, ct.ultimo_login
            FROM contas ct
            LEFT JOIN cliforemp c ON c.idcliforemp = ct.idcliforemp
            WHERE ct.perfil <> 'REPRESENTANTE'
               OR NOT EXISTS (SELECT 1 FROM reps_erp r2 WHERE r2.idcliforemp = ct.idcliforemp)

            ORDER BY perfil DESC, nome ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $utf8 = static function ($s): string {
            $str = (string)$s;
            return mb_check_encoding($str, 'UTF-8') ? $str : mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
        };

        foreach ($rows as &$r) {
            $r['conta_id'] = (int)$r['conta_id'];
            $r['idcliforemp'] = (int)$r['idcliforemp'];
            $r['tem_conta'] = (bool)$r['tem_conta'];
            $r['nome'] = trim($utf8($r['nome']));
            $r['email'] = trim($utf8($r['email']));
            $r['fone_erp'] = trim($utf8($r['fone_erp'] ?? ''));
        }
        unset($r);

        return $this->json($response, [
            'contas' => $rows,
            'whatsapp_provedor' => \Nutricional\Services\WhatsAppService::provedor(),
        ]);
    }

    /**
     * POST /v1/admin/seguranca-b2b/{id}
     * Salva WhatsApp e canal 2FA (email/whatsapp) de Representante ou Conta B2B.
     */
    public function salvarB2b(Request $request, Response $response, array $args): Response
    {
        if ($r = $this->exigirAdmin($request, $response)) return $r;
        $u = $this->usuario($request);
        $idCli = (int)($args['id'] ?? 0);
        $in = $this->body($request);
        $contaId = (int)($in['conta_id'] ?? 0);

        $pref = strtolower(trim((string)($in['preferencia_2fa'] ?? 'email')));
        if (!in_array($pref, ['email', 'whatsapp'], true)) {
            return $this->json($response, ['error' => 'Canal 2FA invalido. Use email ou whatsapp.'], 400);
        }

        $rawWhats = trim((string)($in['whatsapp'] ?? ''));
        $whatsNorm = '';
        if ($rawWhats !== '') {
            $whatsNorm = \Nutricional\Services\WhatsAppService::normalizarNumero($rawWhats) ?: '';
            if ($whatsNorm === '') {
                return $this->json($response, ['error' => 'Numero de WhatsApp invalido (use DDD + numero).'], 400);
            }
        }
        if ($pref === 'whatsapp' && $whatsNorm === '') {
            return $this->json($response, ['error' => 'Informe um numero de WhatsApp antes de definir o 2FA por WhatsApp.'], 400);
        }

        if ($contaId <= 0 && $idCli <= 0) {
            return $this->json($response, ['error' => 'Registro B2B/Representante invalido'], 400);
        }

        if ($r = $this->validarDoisFatores($request, $response, $u, 'b2b:' . ($contaId > 0 ? $contaId : $idCli), 'politica')) {
            return $r;
        }

        if ($contaId > 0) {
            $this->pdo->prepare("
                UPDATE b2b_portal_contas
                   SET whatsapp = :w, preferencia_2fa = :p, atualizado_em = NOW()
                 WHERE id = :id
            ")->execute(['w' => $whatsNorm, 'p' => $pref, 'id' => $contaId]);
        } else {
            $stExist = $this->pdo->prepare("SELECT id FROM b2b_portal_contas WHERE idcliforemp = :cli AND UPPER(perfil) = 'REPRESENTANTE' ORDER BY id DESC LIMIT 1");
            $stExist->execute(['cli' => $idCli]);
            $idExist = (int)$stExist->fetchColumn();
            if ($idExist > 0) {
                $this->pdo->prepare("
                    UPDATE b2b_portal_contas
                       SET whatsapp = :w, preferencia_2fa = :p, atualizado_em = NOW()
                     WHERE id = :id
                ")->execute(['w' => $whatsNorm, 'p' => $pref, 'id' => $idExist]);
            } else {
                $stRep = $this->pdo->prepare("
                    SELECT c.idcliforemp, c.razao,
                           COALESCE(NULLIF(TRIM(c.email), ''), (SELECT TRIM(um.email) FROM usuarios_mercos um WHERE um.idcolaborador = c.idcliforemp AND COALESCE(TRIM(um.email), '') <> '' LIMIT 1), '') AS email,
                           COALESCE(NULLIF(REGEXP_REPLACE(COALESCE(c.cnpj,''), '\D', '', 'g'), ''), REGEXP_REPLACE(COALESCE(c.cpf,''), '\D', '', 'g'), '') AS doc
                    FROM cliforemp c
                    WHERE c.idcliforemp = :cli AND COALESCE(c.inativo, 'N') = 'N'
                    LIMIT 1
                ");
                $stRep->execute(['cli' => $idCli]);
                $rep = $stRep->fetch(\PDO::FETCH_ASSOC);
                if (!$rep) {
                    return $this->json($response, ['error' => 'Representante nao encontrado no ERP'], 404);
                }
                $emailRep = mb_strtolower(trim((string)$rep['email']), 'UTF-8');
                $nomeRep = mb_check_encoding((string)$rep['razao'], 'UTF-8')
                    ? trim((string)$rep['razao'])
                    : trim(mb_convert_encoding((string)$rep['razao'], 'UTF-8', 'Windows-1252'));
                $docRep = trim((string)$rep['doc']) !== '' ? trim((string)$rep['doc']) : (string)$idCli;

                $this->pdo->prepare("
                    INSERT INTO b2b_portal_contas (perfil, idcliforemp, cnpj_cpf, email, nome, senha_hash, status, idvendedor, whatsapp, preferencia_2fa)
                    VALUES ('REPRESENTANTE', :cli, :doc, :em, :nome, '', 'ATIVO', :cli, :w, :p)
                ")->execute([
                    'cli' => $idCli,
                    'doc' => $docRep,
                    'em' => $emailRep,
                    'nome' => $nomeRep,
                    'w' => $whatsNorm,
                    'p' => $pref,
                ]);
            }
        }

        $this->auditar('b2b:' . ($contaId > 0 ? $contaId : $idCli), 'politica', $u, $request, true);
        return $this->json($response, ['success' => true, 'message' => 'Configuracao 2FA do representante/B2B salva com sucesso']);
    }
}
