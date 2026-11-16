<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\ChamadosIaService;
use PDO;

/**
 * Controller da Central de Chamados & Suporte Inteligente do Portal Nutricional.
 * Integração bidirecional com a tabela nativa 'chamado' e 'chamado_operacao' do ERP.
 */
class ChamadosController
{
    private PDO $pdo;
    private ChamadosIaService $ia;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->ia = new ChamadosIaService($this->pdo);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function erro(Response $response, string $msg, int $status = 400): Response
    {
        return $this->json($response, ['success' => false, 'error' => $msg], $status);
    }

    private function limpaWin1252(string $str): string
    {
        // Remove emojis de 4 bytes e caracteres incompatíveis com WIN1252 do Postgres legado
        return (string)preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $str);
    }

    private function parsedBody(Request $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body) && !empty($body)) {
            return $body;
        }
        $raw = (string)$request->getBody();
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private function usuario(Request $request): array
    {
        $user = $request->getAttribute('user') ?? [];
        $idusuario = (int)($user['idusuario'] ?? 0);
        $idcliforemp = (int)($user['uid'] ?? $user['idcliforemp'] ?? 0);
        $username = strtoupper((string)($user['username'] ?? 'USUARIO'));
        
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);
        if (!$isAdmin && $idusuario > 0) {
            $stAdm = $this->pdo->prepare("SELECT 1 FROM usuarios_admin WHERE idusuario = :id AND ativo = true LIMIT 1");
            $stAdm->execute(['id' => $idusuario]);
            if ($stAdm->fetchColumn()) {
                $isAdmin = true;
            }
        }
        if (!$isAdmin && in_array($username, ['ALAN', 'MARCON', 'GERENTE', 'ROBSON'], true)) {
            $isAdmin = true;
        }

        $podeVerTodos = $isAdmin;
        $tiposPermitidos = 'TODOS';
        $permissoesModulos = (array)($user['permissoes'] ?? []);
        $idFilialPadrao = 1;
        $filiaisArr = [];

        if ($idusuario > 0 || $idcliforemp > 0 || ($username !== '' && $username !== 'USUARIO')) {
            try {
                $conds = [];
                $paramsPol = [];
                if ($idusuario > 0) {
                    $conds[] = "idusuario = :id";
                    $paramsPol['id'] = $idusuario;
                }
                if ($idcliforemp > 0) {
                    $conds[] = "idcliforemp = :idcliforemp";
                    $paramsPol['idcliforemp'] = $idcliforemp;
                }
                if ($username !== '' && $username !== 'USUARIO') {
                    $conds[] = "UPPER(TRIM(username)) = :uname";
                    $paramsPol['uname'] = $username;
                }

                $orderParts = [];
                if ($idusuario > 0) {
                    $orderParts[] = "CASE WHEN idusuario = " . (int)$idusuario . " THEN 0 ELSE 1 END";
                }
                if ($idcliforemp > 0) {
                    $orderParts[] = "CASE WHEN idcliforemp = " . (int)$idcliforemp . " THEN 0 ELSE 1 END";
                }
                $orderSql = !empty($orderParts) ? (' ORDER BY ' . implode(', ', $orderParts)) : '';

                $stmtPol = $this->pdo->prepare("
                    SELECT idusuario, chamados_ver_todos, chamados_tipos_permitidos, dash_filiais 
                    FROM usuario 
                    WHERE " . implode(' OR ', $conds) . $orderSql . "
                    LIMIT 1
                ");
                $stmtPol->execute($paramsPol);
                $pol = $stmtPol->fetch(\PDO::FETCH_ASSOC);
                if ($pol) {
                    $idReal = (int)($pol['idusuario'] ?? $idusuario);
                    if ($idReal > 0) {
                        $idusuario = $idReal;
                    }
                    if (!$isAdmin && $idReal > 0) {
                        $stAdm2 = $this->pdo->prepare("SELECT 1 FROM usuarios_admin WHERE idusuario = :id AND ativo = true LIMIT 1");
                        $stAdm2->execute(['id' => $idReal]);
                        if ($stAdm2->fetchColumn()) {
                            $isAdmin = true;
                            $podeVerTodos = true;
                        }
                    }
                    if (!$podeVerTodos && ($pol['chamados_ver_todos'] ?? 'N') === 'S') {
                        $podeVerTodos = true;
                    }
                    if (!$isAdmin && !empty($pol['chamados_tipos_permitidos'])) {
                        $tiposPermitidos = trim($pol['chamados_tipos_permitidos']);
                    }

                    if (!empty($pol['dash_filiais'])) {
                        $filiaisArr = array_values(array_filter(array_map('intval', explode(',', (string)$pol['dash_filiais'])), fn($f) => $f > 0));
                        if (!empty($filiaisArr)) {
                            $idFilialPadrao = $filiaisArr[0];
                        }
                    }

                    // Se não for admin, obtém os módulos exatos liberados em usuario_permissoes
                    if (!$isAdmin && $idReal > 0) {
                        $stP = $this->pdo->prepare("
                            SELECT sm.slug 
                            FROM usuario_permissoes up
                            JOIN sistema_modulos sm ON sm.id = up.idmodulo
                            WHERE up.idusuario = :id AND sm.ativo = true
                        ");
                        $stP->execute(['id' => $idReal]);
                        $slugsBanco = $stP->fetchAll(\PDO::FETCH_COLUMN);
                        if (!empty($slugsBanco)) {
                            $permissoesModulos = array_unique(array_merge($permissoesModulos, $slugsBanco));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silencioso se tabela não responder
            }
        }

        return [
            'idusuario' => $idusuario,
            'idcliforemp' => $idcliforemp,
            'username' => $username,
            'is_admin' => $isAdmin,
            'pode_ver_todos' => $podeVerTodos,
            'pode_gerenciar_ti' => $podeVerTodos,
            'tipos_permitidos' => $tiposPermitidos,
            'permissoes' => $permissoesModulos,
            'idfilial' => $idFilialPadrao,
            'filiais' => $filiaisArr
        ];
    }

    private function mapaStatus(): array
    {
        return [
            0 => ['label' => 'Aberto', 'cor' => 'bg-amber-100 text-amber-800 border-amber-300'],
            1 => ['label' => 'Em Análise', 'cor' => 'bg-blue-100 text-blue-800 border-blue-300'],
            2 => ['label' => 'Em Correção', 'cor' => 'bg-purple-100 text-purple-800 border-purple-300'],
            3 => ['label' => 'Concluído', 'cor' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
            4 => ['label' => 'Cancelado', 'cor' => 'bg-slate-100 text-slate-700 border-slate-300'],
        ];
    }

    private function podeEditarChamado(array $chamado, array $u): bool
    {
        if (!empty($u['pode_ver_todos'])) {
            return true;
        }
        $idCli = (int)($chamado['idcliforemp'] ?? 0);
        if ((int)$u['idcliforemp'] > 0 && $idCli === (int)$u['idcliforemp']) {
            return true;
        }
        $uname = strtoupper(trim((string)$u['username']));
        return strtoupper(trim((string)($chamado['solicitante'] ?? ''))) === $uname
            || strtoupper(trim((string)($chamado['usuario'] ?? ''))) === $uname;
    }

    private function resolverDadosSolicitante(string $solicitanteInput, array $usuarioLogado): array
    {
        $padraoNome = strtoupper(trim((string)($usuarioLogado['username'] ?? 'ALAN')));
        $padraoId = (int)($usuarioLogado['idcliforemp'] ?? 0) ?: 5166;

        $solNome = strtoupper(trim($solicitanteInput));
        if (empty($usuarioLogado['pode_ver_todos']) || $solNome === '') {
            return ['solicitante' => $padraoNome, 'idcliforemp' => $padraoId];
        }

        try {
            $st = $this->pdo->prepare("
                SELECT idcliforemp, UPPER(TRIM(username)) AS username
                FROM usuario
                WHERE UPPER(TRIM(username)) = :u1
                   OR UPPER(TRIM(usuario)) = :u2
                ORDER BY CASE WHEN UPPER(TRIM(username)) = :u3 THEN 0 ELSE 1 END
                LIMIT 1
            ");
            $st->execute(['u1' => $solNome, 'u2' => $solNome, 'u3' => $solNome]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $uname = strtoupper(trim((string)($row['username'] ?: $solNome)));
                $idCli = (int)($row['idcliforemp'] ?? 0) ?: $padraoId;
                return ['solicitante' => $uname, 'idcliforemp' => $idCli];
            }
        } catch (\Throwable $e) {
            // fallback para o texto informado
        }

        return ['solicitante' => $solNome, 'idcliforemp' => $padraoId];
    }

    private function userWhereClause(array $u, string $alias = ''): array
    {
        $p = $alias !== '' ? $alias . '.' : '';
        $uname = strtoupper(trim((string)$u['username']));
        return [
            'sql' => "({$p}idcliforemp = :uid_usr OR UPPER(TRIM(COALESCE({$p}solicitante, ''))) = :u_usr1 OR UPPER(TRIM(COALESCE({$p}usuario, ''))) = :u_usr2)",
            'params' => [
                'uid_usr' => ((int)$u['idcliforemp'] > 0 ? (int)$u['idcliforemp'] : -1),
                'u_usr1'  => $uname,
                'u_usr2'  => $uname,
            ],
        ];
    }

    private function execScalar(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    private function notificarEventoChamado(int $idchamado, string $tituloNotif, string $mensagemNotif, array $ator): void
    {
        try {
            $st = $this->pdo->prepare("SELECT idchamado, titulo, solicitante, usuario, idcliforemp, atendente FROM chamado WHERE idchamado = :id");
            $st->execute(['id' => $idchamado]);
            $ch = $st->fetch(PDO::FETCH_ASSOC);
            if (!$ch) {
                return;
            }

            $destIds = [];
            $nomesAlvo = array_values(array_unique(array_filter([
                strtoupper(trim((string)($ch['solicitante'] ?? ''))),
                strtoupper(trim((string)($ch['usuario'] ?? ''))),
                strtoupper(trim((string)($ch['atendente'] ?? ''))),
                'ALAN',
                'MARCON',
            ])));

            if (!empty($nomesAlvo)) {
                $placeholders = [];
                $params = [];
                foreach ($nomesAlvo as $idx => $nm) {
                    $k = 'nm_' . $idx;
                    $placeholders[] = "UPPER(TRIM(username)) = :{$k}";
                    $params[$k] = $nm;
                }
                $stU = $this->pdo->prepare("SELECT idusuario FROM usuario WHERE " . implode(' OR ', $placeholders));
                $stU->execute($params);
                foreach ($stU->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                    $destIds[(int)$uid] = true;
                }
            }

            $idCli = (int)($ch['idcliforemp'] ?? 0);
            if ($idCli > 0) {
                $stC = $this->pdo->prepare("SELECT idusuario FROM usuario WHERE idcliforemp = :cli");
                $stC->execute(['cli' => $idCli]);
                foreach ($stC->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                    $destIds[(int)$uid] = true;
                }
            }

            $atorId = (int)($ator['idusuario'] ?? 0);
            unset($destIds[$atorId], $destIds[0]);
            if (empty($destIds)) {
                return;
            }

            $ins = $this->pdo->prepare("
                INSERT INTO notificacoes (idusuario, tipo, id_referencia, titulo, mensagem, link, lida, created_at)
                VALUES (:uid, 'chamado', :ref, :tit, :msg, :link, FALSE, NOW())
            ");
            $titLimpo = $this->limpaWin1252(mb_substr($tituloNotif, 0, 180));
            $msgLimpo = $this->limpaWin1252(mb_substr($mensagemNotif, 0, 400));
            $link = "/portal/modules/chamados.php?id={$idchamado}";

            foreach (array_keys($destIds) as $uid) {
                $ins->execute([
                    'uid'  => $uid,
                    'ref'  => $idchamado,
                    'tit'  => $titLimpo,
                    'msg'  => $msgLimpo,
                    'link' => $link,
                ]);
            }
        } catch (\Throwable $e) {
            // Não interrompe operação principal por falha de notificação
        }
    }

    /**
     * GET /v1/chamados/dashboard
     * Retorna KPIs globais, lista dos módulos do Portal para filtros, solicitantes e avisos recentes.
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            $u = $this->usuario($request);
            $uw = $this->userWhereClause($u);
            $userFilterSql = $u['pode_ver_todos'] ? '1=1' : $uw['sql'];
            $userParams = $u['pode_ver_todos'] ? [] : $uw['params'];

            // KPIs
            $kpiTotal = $this->execScalar("SELECT COUNT(*) FROM chamado WHERE {$userFilterSql}", $userParams);
            $kpiAbertos = $this->execScalar("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1) AND {$userFilterSql}", $userParams);
            $kpiEmCorrecao = $this->execScalar("SELECT COUNT(*) FROM chamado WHERE status = 2 AND {$userFilterSql}", $userParams);
            $kpiCriticos = $this->execScalar("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1, 2) AND criticidade = 'critica' AND {$userFilterSql}", $userParams);
            $kpiConcluidosMes = $this->execScalar("SELECT COUNT(*) FROM chamado WHERE status = 3 AND (concluido_em >= DATE_TRUNC('month', CURRENT_DATE) OR data >= DATE_TRUNC('month', CURRENT_DATE)) AND {$userFilterSql}", $userParams);
            $kpiConcluidosTotal = $this->execScalar("SELECT COUNT(*) FROM chamado WHERE status = 3 AND {$userFilterSql}", $userParams);
            $taxaResolucao = $kpiTotal > 0 ? round(($kpiConcluidosTotal / $kpiTotal) * 100, 1) : 0;

            $stTempo = $this->pdo->prepare("
                SELECT ROUND(AVG(EXTRACT(EPOCH FROM (concluido_em - datahora)) / 3600.0)::numeric, 1)
                FROM chamado
                WHERE status = 3
                  AND concluido_em IS NOT NULL
                  AND datahora IS NOT NULL
                  AND concluido_em >= datahora
                  AND {$userFilterSql}
            ");
            $stTempo->execute($userParams);
            $tempoMedioRaw = $stTempo->fetchColumn();
            $tempoMedioHoras = ($tempoMedioRaw !== false && $tempoMedioRaw !== null) ? (float)$tempoMedioRaw : 0.0;

            // Distribuição por Sistema
            $stS = $this->pdo->prepare("
                SELECT COALESCE(tipo_sistema, 'OUTROS') AS sistema, COUNT(*) AS total
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY COALESCE(tipo_sistema, 'OUTROS')
                ORDER BY total DESC
            ");
            $stS->execute($userParams);
            $porSistema = $stS->fetchAll(PDO::FETCH_ASSOC);

            // Distribuição por Criticidade
            $stCr = $this->pdo->prepare("
                SELECT COALESCE(criticidade, 'media') AS criticidade, COUNT(*) AS total
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY COALESCE(criticidade, 'media')
                ORDER BY CASE COALESCE(criticidade, 'media') WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END
            ");
            $stCr->execute($userParams);
            $porCriticidade = $stCr->fetchAll(PDO::FETCH_ASSOC);

            // Top Módulos com mais Demandas
            $stM = $this->pdo->prepare("
                SELECT COALESCE(modulo_nome, modulo_slug, 'Geral') AS modulo, COUNT(*) AS total
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY COALESCE(modulo_nome, modulo_slug, 'Geral')
                ORDER BY total DESC
                LIMIT 6
            ");
            $stM->execute($userParams);
            $topModulos = $stM->fetchAll(PDO::FETCH_ASSOC);

            // Ranking & Rastreamento por Solicitante (quem mais abre chamados)
            $stRank = $this->pdo->prepare("
                SELECT
                    CASE
                        WHEN UPPER(TRIM(COALESCE(NULLIF(TRIM(solicitante), ''), NULLIF(TRIM(usuario), ''), 'SISTEMA'))) = '.' THEN 'INTERNO'
                        ELSE UPPER(TRIM(COALESCE(NULLIF(TRIM(solicitante), ''), NULLIF(TRIM(usuario), ''), 'SISTEMA')))
                    END AS username,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 0) AS abertos,
                    COUNT(*) FILTER (WHERE status = 1) AS em_analise,
                    COUNT(*) FILTER (WHERE status = 2) AS em_correcao,
                    COUNT(*) FILTER (WHERE status = 3) AS concluidos,
                    COUNT(*) FILTER (WHERE status = 4) AS cancelados,
                    COUNT(*) FILTER (WHERE status IN (0, 1, 2)) AS pendentes,
                    COUNT(*) FILTER (WHERE COALESCE(criticidade, 'media') = 'critica') AS criticos,
                    COUNT(*) FILTER (WHERE COALESCE(criticidade, 'media') = 'alta') AS altos,
                    COUNT(*) FILTER (WHERE COALESCE(criticidade, 'media') = 'media') AS medios,
                    COUNT(*) FILTER (WHERE COALESCE(criticidade, 'media') = 'baixa') AS baixos,
                    MODE() WITHIN GROUP (ORDER BY COALESCE(tipo_sistema, 'PORTAL')) AS sistema_principal,
                    MODE() WITHIN GROUP (ORDER BY COALESCE(modulo_nome, modulo_slug, 'Geral')) AS modulo_principal,
                    MAX(datahora) AS ultimo_chamado
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY 1
                ORDER BY total DESC, criticos DESC, username ASC
            ");
            $stRank->execute($userParams);
            $rankingSolicitantes = $stRank->fetchAll(PDO::FETCH_ASSOC);
            $maxSolTotal = !empty($rankingSolicitantes) ? max(1, (int)$rankingSolicitantes[0]['total']) : 1;
            foreach ($rankingSolicitantes as &$rs) {
                $rs['total'] = (int)$rs['total'];
                $rs['abertos'] = (int)$rs['abertos'];
                $rs['em_analise'] = (int)$rs['em_analise'];
                $rs['em_correcao'] = (int)$rs['em_correcao'];
                $rs['concluidos'] = (int)$rs['concluidos'];
                $rs['cancelados'] = (int)$rs['cancelados'];
                $rs['pendentes'] = (int)$rs['pendentes'];
                $rs['criticos'] = (int)$rs['criticos'];
                $rs['altos'] = (int)$rs['altos'];
                $rs['medios'] = (int)$rs['medios'];
                $rs['baixos'] = (int)$rs['baixos'];
                $rs['percentual'] = $kpiTotal > 0 ? round(($rs['total'] / $kpiTotal) * 100, 1) : 0;
                $rs['percentual_barra'] = round(($rs['total'] / $maxSolTotal) * 100, 1);
                $rs['taxa_conclusao'] = $rs['total'] > 0 ? round(($rs['concluidos'] / $rs['total']) * 100, 1) : 0;
                $rs['ultimo_chamado_fmt'] = !empty($rs['ultimo_chamado']) ? date('d/m/Y H:i', strtotime($rs['ultimo_chamado'])) : '-';
            }
            unset($rs);

            // Distribuição por Estágio (Status 0..4)
            $stStatusDist = $this->pdo->prepare("
                SELECT COALESCE(status, 0) AS status, COUNT(*) AS total
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY COALESCE(status, 0)
                ORDER BY status ASC
            ");
            $stStatusDist->execute($userParams);
            $rowsSt = $stStatusDist->fetchAll(PDO::FETCH_KEY_PAIR);
            $mapaSt = $this->mapaStatus();
            $porStatus = [];
            foreach ([0, 1, 2, 3, 4] as $stCode) {
                $totSt = (int)($rowsSt[$stCode] ?? 0);
                $porStatus[] = [
                    'status' => $stCode,
                    'label' => $mapaSt[$stCode]['label'] ?? 'Outro',
                    'total' => $totSt,
                    'percentual' => $kpiTotal > 0 ? round(($totSt / $kpiTotal) * 100, 1) : 0,
                ];
            }

            // Distribuição por Categoria
            $stCat = $this->pdo->prepare("
                SELECT COALESCE(NULLIF(TRIM(categoria), ''), 'bug') AS categoria, COUNT(*) AS total
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY 1
                ORDER BY total DESC
            ");
            $stCat->execute($userParams);
            $porCategoria = $stCat->fetchAll(PDO::FETCH_ASSOC);
            foreach ($porCategoria as &$pc) {
                $pc['total'] = (int)$pc['total'];
                $pc['percentual'] = $kpiTotal > 0 ? round(($pc['total'] / $kpiTotal) * 100, 1) : 0;
            }
            unset($pc);

            // Performance por Atendente / Técnico TI
            $stAt = $this->pdo->prepare("
                SELECT
                    CASE
                        WHEN TRIM(COALESCE(atendente, '')) IN ('', '.') THEN 'ALAN'
                        ELSE UPPER(TRIM(atendente))
                    END AS atendente,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 3) AS concluidos,
                    COUNT(*) FILTER (WHERE status IN (0, 1, 2)) AS em_andamento
                FROM chamado
                WHERE {$userFilterSql}
                GROUP BY 1
                ORDER BY total DESC, concluidos DESC
            ");
            $stAt->execute($userParams);
            $performanceAtendentes = $stAt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($performanceAtendentes as &$pa) {
                $pa['total'] = (int)$pa['total'];
                $pa['concluidos'] = (int)$pa['concluidos'];
                $pa['em_andamento'] = (int)$pa['em_andamento'];
                $pa['taxa_resolucao'] = $pa['total'] > 0 ? round(($pa['concluidos'] / $pa['total']) * 100, 1) : 0;
            }
            unset($pa);

            // Últimos Chamados Resolvidos (Entregas Recentes)
            $stR = $this->pdo->prepare("
                SELECT idchamado, titulo, tipo_sistema, modulo_nome, atendente, versao_entrega, concluido_em, datahora
                FROM chamado
                WHERE status = 3 AND {$userFilterSql}
                ORDER BY COALESCE(concluido_em, datahora) DESC
                LIMIT 6
            ");
            $stR->execute($userParams);
            $resolvidosRecentes = $stR->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resolvidosRecentes as &$r) {
                $r['concluido_fmt'] = !empty($r['concluido_em']) ? date('d/m H:i', strtotime($r['concluido_em'])) : date('d/m', strtotime($r['datahora']));
            }
            unset($r);

            // Lista de Solicitantes para filtro ("Quem solicitou")
            $stSol = $this->pdo->prepare("
                SELECT UPPER(TRIM(solicitante)) AS username, COUNT(*) AS total
                FROM chamado
                WHERE solicitante IS NOT NULL AND TRIM(solicitante) <> '' AND {$userFilterSql}
                GROUP BY UPPER(TRIM(solicitante))
                ORDER BY username ASC
            ");
            $stSol->execute($userParams);
            $solicitantes = $stSol->fetchAll(PDO::FETCH_ASSOC);

            // Lista de Atendentes disponíveis
            $atendentesBanco = $this->pdo->query("
                SELECT DISTINCT UPPER(TRIM(atendente)) AS username
                FROM chamado
                WHERE atendente IS NOT NULL AND TRIM(atendente) <> '' AND TRIM(atendente) <> '.'
                ORDER BY username ASC
            ")->fetchAll(PDO::FETCH_COLUMN);
            $atendentes = array_values(array_unique(array_merge(['ALAN', 'ROBSON'], $atendentesBanco ?: [])));
            sort($atendentes);

            // Lista completa de usuários para seleção de Solicitante pelo Admin/TI
            $usuariosSolicitantes = [];
            try {
                $rowsUsrSol = $this->pdo->query("
                    SELECT DISTINCT
                        UPPER(TRIM(username)) AS username,
                        COALESCE(NULLIF(TRIM(usuario), ''), UPPER(TRIM(username))) AS nome
                    FROM usuario
                    WHERE COALESCE(inativo, 'N') = 'N'
                      AND username IS NOT NULL
                      AND TRIM(username) <> ''
                    ORDER BY username ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
                $mapaSol = [];
                foreach ($rowsUsrSol as $ru) {
                    $un = strtoupper(trim((string)$ru['username']));
                    if ($un !== '' && $un !== '.') {
                        $mapaSol[$un] = ['username' => $un, 'nome' => trim((string)$ru['nome']) ?: $un];
                    }
                }
                foreach ($solicitantes as $rs) {
                    $un = strtoupper(trim((string)($rs['username'] ?? '')));
                    if ($un !== '' && $un !== '.' && !isset($mapaSol[$un])) {
                        $mapaSol[$un] = ['username' => $un, 'nome' => $un];
                    }
                }
                ksort($mapaSol);
                $usuariosSolicitantes = array_values($mapaSol);
            } catch (\Throwable $e) {
                $usuariosSolicitantes = $solicitantes;
            }

            // Notificações Não Lidas de Chamados (sincroniza novos chamados das últimas 72h e lê apenas lida = FALSE)
            $avisosRecentes = [];
            $notifNaoLidas = 0;
            $uid = (int)($u['idusuario'] ?? 0);
            try {
                if ($uid > 0) {
                    if ($u['pode_ver_todos']) {
                        $stSync = $this->pdo->prepare("
                            SELECT ch.idchamado, ch.titulo, ch.modulo_nome, ch.solicitante, ch.criticidade, ch.datahora
                            FROM chamado ch
                            WHERE ch.status IN (0, 1)
                              AND ch.datahora >= NOW() - INTERVAL '72 hours'
                              AND NOT EXISTS (
                                  SELECT 1 FROM notificacoes n
                                  WHERE n.tipo = 'chamado' AND n.id_referencia = ch.idchamado AND n.idusuario = :uid_check
                              )
                            ORDER BY ch.datahora DESC
                            LIMIT 10
                        ");
                        $stSync->execute(['uid_check' => $uid]);
                    } else {
                        $uwCh = $this->userWhereClause($u, 'ch');
                        $stSync = $this->pdo->prepare("
                            SELECT ch.idchamado, ch.titulo, ch.modulo_nome, ch.solicitante, ch.criticidade, ch.datahora
                            FROM chamado ch
                            WHERE ch.datahora >= NOW() - INTERVAL '72 hours'
                              AND {$uwCh['sql']}
                              AND NOT EXISTS (
                                  SELECT 1 FROM notificacoes n
                                  WHERE n.tipo = 'chamado' AND n.id_referencia = ch.idchamado AND n.idusuario = :uid_check
                              )
                            ORDER BY ch.datahora DESC
                            LIMIT 10
                        ");
                        $stSync->execute(array_merge($uwCh['params'], ['uid_check' => $uid]));
                    }

                    $novosNotif = $stSync->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($novosNotif)) {
                        $insNotif = $this->pdo->prepare("
                            INSERT INTO notificacoes (idusuario, tipo, id_referencia, titulo, mensagem, link, lida, created_at)
                            VALUES (:uid, 'chamado', :ref, :tit, :msg, :link, FALSE, :datahora)
                        ");
                        foreach ($novosNotif as $nv) {
                            $critPrefix = in_array(strtolower((string)($nv['criticidade'] ?? '')), ['critica', 'alta'], true) ? '[ALTA] ' : '';
                            $insNotif->execute([
                                'uid'      => $uid,
                                'ref'      => (int)$nv['idchamado'],
                                'tit'      => $this->limpaWin1252("Chamado #{$nv['idchamado']}: " . $critPrefix . ($nv['titulo'] ?: 'Suporte TI')),
                                'msg'      => $this->limpaWin1252("Módulo: " . ($nv['modulo_nome'] ?: 'Geral') . " | Solicitante: " . ($nv['solicitante'] ?: 'Equipe')),
                                'link'     => "/portal/modules/chamados.php?id={$nv['idchamado']}",
                                'datahora' => $nv['datahora'] ?: date('Y-m-d H:i:s'),
                            ]);
                        }
                    }

                    $stAv = $this->pdo->prepare("
                        SELECT
                            n.id AS idnotificacao,
                            COALESCE(n.id_referencia, 0) AS idchamado,
                            COALESCE(NULLIF(TRIM(n.titulo), ''), COALESCE(c.titulo, c.motivo, 'Chamado')) AS titulo,
                            COALESCE(n.mensagem, '') AS descricao,
                            COALESCE(NULLIF(TRIM(c.solicitante), ''), NULLIF(TRIM(c.usuario), ''), 'TI') AS usuario,
                            COALESCE(c.criticidade, 'media') AS criticidade,
                            n.created_at
                        FROM notificacoes n
                        LEFT JOIN chamado c ON c.idchamado = n.id_referencia
                        WHERE n.tipo = 'chamado'
                          AND (n.idusuario = :uid OR n.idusuario IS NULL)
                          AND n.lida = FALSE
                        ORDER BY n.created_at DESC, n.id DESC
                        LIMIT 15
                    ");
                    $stAv->execute(['uid' => $uid]);
                    $rowsAv = $stAv->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rowsAv as $rowAv) {
                        $avisosRecentes[] = [
                            'idnotificacao' => (int)$rowAv['idnotificacao'],
                            'idchamado'     => (int)$rowAv['idchamado'],
                            'titulo'        => $rowAv['titulo'],
                            'descricao'     => $rowAv['descricao'],
                            'usuario'       => $rowAv['usuario'],
                            'criticidade'   => $rowAv['criticidade'],
                            'data_fmt'      => !empty($rowAv['created_at']) ? date('d/m H:i', strtotime($rowAv['created_at'])) : '',
                        ];
                    }
                    $notifNaoLidas = count($avisosRecentes);
                }
            } catch (\Throwable $e) {}

            // Módulos para os selects
            $modulos = $this->pdo->query("SELECT slug, nome, setor, icon FROM sistema_modulos WHERE ativo = true ORDER BY setor, nome")->fetchAll(PDO::FETCH_ASSOC);

            // Filtragem de módulos por permissão caso não seja admin
            if (!$u['is_admin']) {
                $perms = $u['permissoes'];
                $modulos = array_values(array_filter($modulos, function($m) use ($perms) {
                    return in_array($m['slug'], $perms, true);
                }));
            }

            // Sistemas corporativos suportados
            $sistemas = [
                ['id' => 'PORTAL',            'nome' => 'Portal Nutricional (Módulos Web)', 'icon' => 'fa-globe', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                ['id' => 'SUPORTE_COMERCIAL', 'nome' => 'Suporte Comercial & Vendas',       'icon' => 'fa-headset', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200'],
                ['id' => 'MERCOS',            'nome' => 'Mercos (Força de Vendas / App)',   'icon' => 'fa-handshake', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                ['id' => 'ERP_EMA',           'nome' => 'ERP Ema Desktop',                 'icon' => 'fa-database', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200'],
                ['id' => 'RELATORIOS',        'nome' => 'Relatórios Gerenciais & Cubos',   'icon' => 'fa-chart-pie', 'badge' => 'bg-violet-50 text-violet-700 border-violet-200'],
                ['id' => 'EMAIL',             'nome' => 'Email Corporativo / Webmail',     'icon' => 'fa-envelope', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200'],
                ['id' => 'COBLI',             'nome' => 'Cobli (Telemetria & Frotas)',     'icon' => 'fa-truck', 'badge' => 'bg-yellow-50 text-yellow-700 border-yellow-200'],
                ['id' => 'LOGISOFT',          'nome' => 'Logisoft (CTe / MDFe / CIOT)',    'icon' => 'fa-file-invoice', 'badge' => 'bg-teal-50 text-teal-700 border-teal-200'],
                ['id' => 'EQUIPAMENTO',       'nome' => 'Equipamentos (Impressora, Ramal, PC)', 'icon' => 'fa-print', 'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200'],
                ['id' => 'PLANILHAS',         'nome' => 'Planilhas Excel / Dados',         'icon' => 'fa-file-excel', 'badge' => 'bg-green-50 text-green-700 border-green-200'],
                ['id' => 'SITE',              'nome' => 'Site Institucional / E-commerce', 'icon' => 'fa-cart-shopping', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200'],
                ['id' => 'SERVIDOR',          'nome' => 'Servidores, Rede & VPN',          'icon' => 'fa-server', 'badge' => 'bg-slate-100 text-slate-700 border-slate-300'],
                ['id' => 'OUTROS',            'nome' => 'Outros Assuntos / Geral',         'icon' => 'fa-circle-question', 'badge' => 'bg-gray-50 text-gray-700 border-gray-200']
            ];

            // Filtragem de sistemas pelo perfil de abertura permitido
            if ($u['tipos_permitidos'] !== 'TODOS') {
                $tiposPermitidosArr = array_map('trim', explode(',', $u['tipos_permitidos']));
                $sistemas = array_values(array_filter($sistemas, function($s) use ($tiposPermitidosArr) {
                    return in_array($s['id'], $tiposPermitidosArr, true);
                }));
            }

            // Filiais liberadas para o usuário (respeita restrição do cadastro)
            $filiaisLiberadas = [];
            if (!empty($u['filiais'])) {
                $inFiliais = implode(',', array_map('intval', $u['filiais']));
                $filiaisLiberadas = $this->pdo->query("
                    SELECT idfilial, razao 
                    FROM filial 
                    WHERE idfilial IN ($inFiliais) 
                    ORDER BY idfilial
                ")->fetchAll(\PDO::FETCH_ASSOC);
            }
            if (empty($filiaisLiberadas)) {
                $filiaisLiberadas = $this->pdo->query("
                    SELECT idfilial, razao 
                    FROM filial 
                    WHERE inativo = 'N' AND idfilial IN (1, 6)
                    ORDER BY idfilial
                ")->fetchAll(\PDO::FETCH_ASSOC);
            }

            foreach ($filiaisLiberadas as &$fl) {
                $fl['idfilial'] = (int)$fl['idfilial'];
                $fl['nome'] = $fl['idfilial'] === 1 ? 'Filial 1 - Matriz SC' : ($fl['idfilial'] === 6 ? 'Filial 6 - Paraná (PR)' : "Filial {$fl['idfilial']} - " . $fl['razao']);
            }
            unset($fl);

            return $this->json($response, [
                'success' => true,
                'kpis' => [
                    'total' => $kpiTotal,
                    'abertos' => $kpiAbertos,
                    'em_correcao' => $kpiEmCorrecao,
                    'criticos' => $kpiCriticos,
                    'concluidos_mes' => $kpiConcluidosMes,
                    'concluidos_total' => $kpiConcluidosTotal,
                    'taxa_resolucao' => $taxaResolucao,
                    'tempo_medio_horas' => $tempoMedioHoras
                ],
                'metricas' => [
                    'por_sistema' => $porSistema,
                    'por_criticidade' => $porCriticidade,
                    'top_modulos' => $topModulos,
                    'resolvidos_recentes' => $resolvidosRecentes,
                    'ranking_solicitantes' => $rankingSolicitantes,
                    'por_status' => $porStatus,
                    'por_categoria' => $porCategoria,
                    'performance_atendentes' => $performanceAtendentes
                ],
                'modulos' => $modulos,
                'sistemas' => $sistemas,
                'filiais' => $filiaisLiberadas,
                'solicitantes' => $solicitantes,
                'usuarios_solicitantes' => $usuariosSolicitantes,
                'atendentes' => $atendentes,
                'avisos_recentes' => $avisosRecentes,
                'notificacoes_nao_lidas' => $notifNaoLidas,
                'usuario' => $u
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar dashboard de chamados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/chamados/listar
     * Lista com filtros por status, módulo, criticidade, solicitante, ordenação e termo de busca.
     */
    public function listar(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $status = isset($q['status']) && $q['status'] !== '' ? (int)$q['status'] : null;
            $modulo = trim((string)($q['modulo'] ?? ''));
            $criticidade = trim((string)($q['criticidade'] ?? ''));
            $categoria = trim((string)($q['categoria'] ?? ''));
            $tipoSistema = trim((string)($q['tipo_sistema'] ?? ''));
            $busca = trim((string)($q['busca'] ?? ''));
            $dataInicio = trim((string)($q['data_inicio'] ?? ''));
            $dataFim = trim((string)($q['data_fim'] ?? ''));
            $atendente = trim((string)($q['atendente'] ?? ''));
            $solicitante = trim((string)($q['solicitante'] ?? ''));
            $ordenacao = trim((string)($q['ordenacao'] ?? 'prioridade'));

            $u = $this->usuario($request);
            $where = ['1=1'];
            $params = [];

            if (!$u['pode_ver_todos']) {
                $uw = $this->userWhereClause($u, 'c');
                $where[] = $uw['sql'];
                $params = array_merge($params, $uw['params']);
            } elseif ($atendente !== '' && $atendente !== 'todos') {
                if ($atendente === 'mim') {
                    $where[] = "(c.atendente ILIKE :atendente1 OR (c.atendente IS NULL AND :atendente2 = 'ALAN'))";
                    $params['atendente1'] = $u['username'];
                    $params['atendente2'] = $u['username'];
                } elseif ($atendente === 'sem_atendente') {
                    $where[] = "(c.atendente IS NULL OR c.atendente = '' OR c.atendente = '.')";
                } else {
                    $where[] = 'c.atendente ILIKE :atendente';
                    $params['atendente'] = '%' . $atendente . '%';
                }
            }

            if ($solicitante !== '' && $solicitante !== 'todos') {
                if ($solicitante === 'mim') {
                    $uname = strtoupper(trim((string)$u['username']));
                    $where[] = "(c.idcliforemp = :uid_sol_f OR UPPER(TRIM(COALESCE(c.solicitante, ''))) = :u_sol_f1 OR UPPER(TRIM(COALESCE(c.usuario, ''))) = :u_sol_f2)";
                    $params['uid_sol_f'] = ((int)$u['idcliforemp'] > 0 ? (int)$u['idcliforemp'] : -1);
                    $params['u_sol_f1'] = $uname;
                    $params['u_sol_f2'] = $uname;
                } else {
                    $where[] = "UPPER(TRIM(COALESCE(c.solicitante, ''))) = UPPER(TRIM(:solicitante_filtro))";
                    $params['solicitante_filtro'] = $solicitante;
                }
            }

            if ($status !== null) {
                $where[] = 'c.status = :status';
                $params['status'] = $status;
            }
            if ($tipoSistema !== '' && $tipoSistema !== 'todos') {
                $where[] = 'c.tipo_sistema = :tipoSistema';
                $params['tipoSistema'] = $tipoSistema;
            }
            if ($modulo !== '' && $modulo !== 'todos') {
                $where[] = 'c.modulo_slug = :modulo';
                $params['modulo'] = $modulo;
            }
            if ($criticidade !== '' && $criticidade !== 'todas') {
                $where[] = 'c.criticidade = :criticidade';
                $params['criticidade'] = $criticidade;
            }
            if ($categoria !== '' && $categoria !== 'todas') {
                $where[] = 'c.categoria = :categoria';
                $params['categoria'] = $categoria;
            }
            if ($dataInicio !== '') {
                $where[] = 'c.data >= :dataInicio::date';
                $params['dataInicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $where[] = 'c.data <= :dataFim::date';
                $params['dataFim'] = $dataFim;
            }
            if ($busca !== '') {
                $where[] = '(c.titulo ILIKE :busca1 OR c.motivo ILIKE :busca2 OR c.solicitante ILIKE :busca3 OR c.observacao ILIKE :busca4 OR CAST(c.idchamado AS TEXT) ILIKE :busca5)';
                $likeBusca = '%' . $busca . '%';
                $params['busca1'] = $likeBusca;
                $params['busca2'] = $likeBusca;
                $params['busca3'] = $likeBusca;
                $params['busca4'] = $likeBusca;
                $params['busca5'] = $likeBusca;
            }

            $critCase = "CASE COALESCE(c.criticidade, 'media') WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END";
            if ($ordenacao === 'recentes') {
                $orderBySql = "c.idchamado DESC";
            } elseif ($ordenacao === 'criticidade') {
                $orderBySql = "{$critCase}, COALESCE(c.prioridade, 10) ASC, c.idchamado DESC";
            } else {
                $orderBySql = "COALESCE(c.prioridade, 10) ASC, {$critCase}, c.idchamado DESC";
            }

            $sql = "
                SELECT 
                    c.idchamado,
                    COALESCE(c.titulo, c.motivo, 'Sem título') AS titulo,
                    c.motivo,
                    c.observacao,
                    COALESCE(c.tipo_sistema, 'PORTAL') AS tipo_sistema,
                    COALESCE(c.subtipo_item, 'Geral') AS subtipo_item,
                    c.equipamento_detalhe,
                    c.modulo_slug,
                    COALESCE(c.modulo_nome, sm.nome, 'Portal Geral') AS modulo_nome,
                    COALESCE(c.categoria, 'bug') AS categoria,
                    COALESCE(c.criticidade, 'media') AS criticidade,
                    COALESCE(c.prioridade, 10) AS prioridade,
                    c.status,
                    c.solicitante,
                    c.atendente,
                    c.idfilial,
                    c.datahora,
                    c.atualizado_em,
                    c.concluido_em,
                    c.versao_entrega,
                    c.anexo_url,
                    (SELECT COUNT(*) FROM chamado_operacao op WHERE op.idchamado = c.idchamado) AS total_interacoes,
                    ((SELECT COUNT(*) FROM chamado_operacao op WHERE op.idchamado = c.idchamado AND op.anexo_url IS NOT NULL AND op.anexo_url <> '') + (CASE WHEN c.anexo_url IS NOT NULL AND c.anexo_url <> '' THEN 1 ELSE 0 END)) AS total_anexos
                FROM chamado c
                LEFT JOIN sistema_modulos sm ON sm.slug = c.modulo_slug
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBySql}
                LIMIT 200
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapaStatus = $this->mapaStatus();

            foreach ($itens as &$it) {
                $st = (int)$it['status'];
                $it['status'] = $st;
                $it['prioridade'] = (int)($it['prioridade'] ?? 10);
                $it['total_interacoes'] = (int)($it['total_interacoes'] ?? 0);
                $it['total_anexos'] = (int)($it['total_anexos'] ?? 0);
                $it['status_info'] = $mapaStatus[$st] ?? ['label' => 'Desconhecido', 'cor' => 'bg-gray-100 text-gray-700'];
                $it['data_fmt'] = !empty($it['datahora']) ? date('d/m/Y H:i', strtotime($it['datahora'])) : '';
                $it['atualizado_fmt'] = !empty($it['atualizado_em']) ? date('d/m H:i', strtotime($it['atualizado_em'])) : '';
                $it['concluido_fmt'] = !empty($it['concluido_em']) ? date('d/m/Y H:i', strtotime($it['concluido_em'])) : '';
            }
            unset($it);

            return $this->json($response, ['success' => true, 'chamados' => $itens]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao listar chamados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/chamados/detalhes/{id}
     * Carrega o chamado completo com o Dossiê IA, anexos consolidados e timeline de interações.
     */
    public function detalhes(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            $stmt = $this->pdo->prepare("
                SELECT c.*, sm.nome AS modulo_oficial, sm.icon AS modulo_icon
                FROM chamado c
                LEFT JOIN sistema_modulos sm ON sm.slug = c.modulo_slug
                WHERE c.idchamado = :id
            ");
            $stmt->execute(['id' => $id]);
            $chamado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }

            $u = $this->usuario($request);
            if (!$this->podeEditarChamado($chamado, $u)) {
                return $this->erro($response, 'Acesso não autorizado aos detalhes deste chamado.', 403);
            }

            // Marca notificações deste chamado como lidas para o usuário logado
            try {
                if ((int)$u['idusuario'] > 0) {
                    $stRead = $this->pdo->prepare("
                        UPDATE notificacoes
                        SET lida = TRUE, lida_em = NOW()
                        WHERE tipo = 'chamado'
                          AND id_referencia = :id
                          AND (idusuario = :uid OR idusuario IS NULL)
                          AND lida = FALSE
                    ");
                    $stRead->execute(['id' => $id, 'uid' => (int)$u['idusuario']]);
                }
            } catch (\Throwable $e) {}

            // Timeline de operacoes
            $stmtOp = $this->pdo->prepare("
                SELECT 
                    idoperacao,
                    descricao,
                    datahora,
                    usuario,
                    acao,
                    COALESCE(tipo, 'comentario') AS tipo,
                    detalhes,
                    anexo_url
                FROM chamado_operacao
                WHERE idchamado = :id
                ORDER BY datahora ASC, idoperacao ASC
            ");
            $stmtOp->execute(['id' => $id]);
            $timeline = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

            $anexos = [];
            $chamado['data_fmt'] = !empty($chamado['datahora']) ? date('d/m/Y H:i', strtotime($chamado['datahora'])) : '';
            $chamado['concluido_fmt'] = !empty($chamado['concluido_em']) ? date('d/m/Y H:i', strtotime($chamado['concluido_em'])) : '';
            $chamado['prioridade'] = (int)($chamado['prioridade'] ?? 10);
            $chamado['status'] = (int)($chamado['status'] ?? 0);

            if (!empty($chamado['anexo_url'])) {
                $anexos[] = [
                    'url' => $chamado['anexo_url'],
                    'autor' => $chamado['solicitante'] ?: ($chamado['usuario'] ?: 'Usuário'),
                    'data_fmt' => $chamado['data_fmt'],
                    'origem' => 'abertura',
                    'idoperacao' => 0
                ];
            }

            foreach ($timeline as &$op) {
                $op['data_fmt'] = !empty($op['datahora']) ? date('d/m/Y H:i', strtotime($op['datahora'])) : '';
                if (!empty($op['anexo_url'])) {
                    $anexos[] = [
                        'url' => $op['anexo_url'],
                        'autor' => $op['usuario'] ?: 'Usuário',
                        'data_fmt' => $op['data_fmt'],
                        'origem' => 'interacao',
                        'idoperacao' => (int)$op['idoperacao']
                    ];
                }
            }
            unset($op);

            return $this->json($response, [
                'success' => true,
                'chamado' => $chamado,
                'timeline' => $timeline,
                'anexos' => $anexos
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar detalhes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/triagem-ia
     * Realiza a pré-análise e formatação com IA antes da submissão final.
     */
    public function triarIa(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $relato = trim((string)($body['relato'] ?? ''));
            $modulo = !empty($body['modulo']) ? trim((string)$body['modulo']) : null;
            $filial = (int)($body['filial'] ?? 1);
            $tipoSistema = !empty($body['tipo_sistema']) ? trim((string)$body['tipo_sistema']) : null;

            if (mb_strlen($relato) < 4) {
                return $this->erro($response, 'Por favor, descreva o chamado com mais detalhes para a IA analisar.');
            }

            $resultado = $this->ia->triar($relato, $modulo, $filial, $tipoSistema);

            return $this->json($response, [
                'success' => true,
                'sugestao' => $resultado
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro na triagem de IA: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/criar
     * Cria um novo chamado no banco e registra a abertura na timeline.
     */
    public function criar(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $u = $this->usuario($request);

            $relato = trim((string)($body['motivo'] ?? $body['relato'] ?? ''));
            if (mb_strlen($relato) < 4) {
                return $this->erro($response, 'Informe a descrição do chamado.');
            }

            $modulo = trim((string)($body['modulo_slug'] ?? 'geral'));
            $filial = (int)($body['idfilial'] ?? 1);
            $tipoSistema = !empty($body['tipo_sistema']) ? trim((string)$body['tipo_sistema']) : null;
            $subtipoItem = !empty($body['subtipo_item']) ? trim((string)$body['subtipo_item']) : null;
            $equipamentoDetalhe = !empty($body['equipamento_detalhe']) ? trim((string)$body['equipamento_detalhe']) : null;

            // Se o usuário não fez a triagem antes, a IA tria automaticamente no ato do salvamento
            $triagem = !empty($body['titulo']) ? [
                'titulo' => trim((string)$body['titulo']),
                'tipo_sistema' => $tipoSistema ?: 'PORTAL',
                'subtipo_item' => $subtipoItem ?: 'Geral',
                'modulo_slug' => $modulo,
                'modulo_nome' => trim((string)($body['modulo_nome'] ?? $modulo)),
                'categoria' => trim((string)($body['categoria'] ?? 'bug')),
                'criticidade' => trim((string)($body['criticidade'] ?? 'media')),
                'diagnostico_ia' => trim((string)($body['diagnostico_ia'] ?? '')),
            ] : $this->ia->triar($relato, $modulo, $filial, $tipoSistema);

            // Se o usuário escolheu manualmente criticidade/categoria (ex: Abertura Expressa), respeita a escolha dele
            if (!empty($body['criticidade_manual'])) {
                $critBody = strtolower(trim((string)($body['criticidade'] ?? '')));
                if (in_array($critBody, ['critica', 'alta', 'media', 'baixa'], true)) {
                    $triagem['criticidade'] = $critBody;
                }
                if (!empty($body['categoria'])) {
                    $triagem['categoria'] = trim((string)$body['categoria']);
                }
            }

            $tipoSistemaFinal = !empty($triagem['tipo_sistema']) ? $triagem['tipo_sistema'] : ($tipoSistema ?: 'PORTAL');
            $subtipoFinal = !empty($triagem['subtipo_item']) ? $triagem['subtipo_item'] : ($subtipoItem ?: 'Geral');
            $equipDetalheFinal = $equipamentoDetalhe ?: ($tipoSistemaFinal === 'EQUIPAMENTO' ? $subtipoFinal : null);

            // Validação de Permissão por Tipo de Sistema
            if ($u['tipos_permitidos'] !== 'TODOS') {
                $tiposPermitidosArr = array_map('trim', explode(',', $u['tipos_permitidos']));
                if (!in_array($tipoSistemaFinal, $tiposPermitidosArr, true)) {
                    return $this->erro($response, "Você não possui permissão para abrir chamados do tipo '{$tipoSistemaFinal}'.");
                }
            }

            // Se for Portal, validar se tem acesso ao módulo escolhido
            if ($tipoSistemaFinal === 'PORTAL' && !$u['is_admin']) {
                $moduloEscolhido = $triagem['modulo_slug'] ?? $modulo;
                if (!empty($moduloEscolhido) && $moduloEscolhido !== 'geral') {
                    if (!in_array($moduloEscolhido, $u['permissoes'], true)) {
                        return $this->erro($response, "Você não possui permissão no módulo '{$moduloEscolhido}' do Portal.");
                    }
                }
            }

            // Validação de Permissão de Filial (garante integridade conforme o cadastro do usuário)
            if (!$u['is_admin'] && !empty($u['filiais'])) {
                if (!in_array($filial, $u['filiais'], true)) {
                    $filial = (int)$u['filiais'][0];
                }
            }

            // Responsável técnico padrão para solução dos chamados: 'ALAN'
            $atendentePadrao = !empty($body['atendente']) && $u['pode_ver_todos'] ? strtoupper(trim((string)$body['atendente'])) : 'ALAN';

            $mapaPrioridadeDefault = ['critica' => 1, 'alta' => 3, 'media' => 5, 'baixa' => 8];
            $critFinal = strtolower((string)($triagem['criticidade'] ?? 'media'));
            $prioridadeInicial = !empty($body['prioridade']) && (int)$body['prioridade'] > 0
                ? (int)$body['prioridade']
                : ($mapaPrioridadeDefault[$critFinal] ?? 5);

            // Próximo ID de chamado
            $idNovo = (int)$this->pdo->query("SELECT COALESCE(MAX(idchamado), 0) + 1 FROM chamado")->fetchColumn();

            $stmt = $this->pdo->prepare("
                INSERT INTO chamado (
                    idchamado, idtransacao, idfilial, idempresa, idcliforemp,
                    status, prioridade, data, hora, datahora,
                    solicitante, atendente, usuario, motivo, observacao,
                    tipo_sistema, subtipo_item, equipamento_detalhe,
                    modulo_slug, modulo_nome, categoria, criticidade,
                    titulo, diagnostico_ia, anexo_url
                ) VALUES (
                    :idchamado, 3, :idfilial, 1, :idcliforemp,
                    0, :prioridade, CURRENT_DATE, NOW(), NOW(),
                    :solicitante, :atendente, :usuario, :motivo, :observacao,
                    :tipo_sistema, :subtipo_item, :equipamento_detalhe,
                    :modulo_slug, :modulo_nome, :categoria, :criticidade,
                    :titulo, :diagnostico_ia, :anexo_url
                )
            ");

            $dadosSol = $this->resolverDadosSolicitante((string)($body['solicitante'] ?? ''), $u);
            $solicitanteFinal = $dadosSol['solicitante'];
            $idCliSolicitante = $dadosSol['idcliforemp'];

            $tituloFinal = (string)($triagem['titulo'] ?? 'Novo Chamado');
            $stmt->execute([
                'idchamado' => $idNovo,
                'idfilial' => $filial,
                'idcliforemp' => $idCliSolicitante,
                'prioridade' => $prioridadeInicial,
                'solicitante' => $this->limpaWin1252($solicitanteFinal),
                'atendente' => $atendentePadrao,
                'usuario' => $this->limpaWin1252($solicitanteFinal),
                'motivo' => $this->limpaWin1252($relato),
                'observacao' => $this->limpaWin1252($relato),
                'tipo_sistema' => $this->limpaWin1252($tipoSistemaFinal),
                'subtipo_item' => $this->limpaWin1252($subtipoFinal),
                'equipamento_detalhe' => $equipDetalheFinal ? $this->limpaWin1252($equipDetalheFinal) : null,
                'modulo_slug' => $this->limpaWin1252($triagem['modulo_slug'] ?? 'geral'),
                'modulo_nome' => $this->limpaWin1252($triagem['modulo_nome'] ?? 'Geral'),
                'categoria' => $this->limpaWin1252($triagem['categoria']),
                'criticidade' => $this->limpaWin1252($critFinal),
                'titulo' => $this->limpaWin1252($tituloFinal),
                'diagnostico_ia' => $this->limpaWin1252($triagem['diagnostico_ia']),
                'anexo_url' => $this->limpaWin1252(trim((string)($body['anexo_url'] ?? '')))
            ]);

            $emNomeDe = $solicitanteFinal !== strtoupper(trim((string)$u['username']))
                ? " aberto por {$u['username']} em nome de {$solicitanteFinal}"
                : '';

            // Registra primeira operação na timeline
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:idchamado, 1, :desc, NOW(), :usuario, 1, 'abertura', :detalhes)
            ");
            $stmtOp->execute([
                'idchamado' => $idNovo,
                'desc' => mb_substr("Chamado aberto{$emNomeDe} -> TI ({$atendentePadrao})", 0, 80),
                'usuario' => $u['username'],
                'detalhes' => "Chamado [{$tipoSistemaFinal}] classificado como [{$triagem['categoria']}] com criticidade [{$critFinal}] (Prioridade #{$prioridadeInicial}). Solicitante vinculado: {$solicitanteFinal}. Atendente responsável: {$atendentePadrao}."
            ]);

            $this->notificarEventoChamado(
                $idNovo,
                "Novo Chamado #{$idNovo}: {$tituloFinal}",
                "Sistema: {$tipoSistemaFinal} | Criticidade: " . strtoupper($critFinal) . " | Solicitante: {$solicitanteFinal}",
                $u
            );

            return $this->json($response, [
                'success' => true,
                'message' => 'Chamado #' . $idNovo . ' registrado com sucesso!',
                'idchamado' => $idNovo
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao criar chamado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/assumir
     * O atendente/técnico assume a responsabilidade pelo chamado.
     */
    public function assumir(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $u = $this->usuario($request);

            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            if (!$u['pode_ver_todos']) {
                return $this->erro($response, 'Apenas membros da equipe técnica podem assumir chamados.', 403);
            }

            $stmtCham = $this->pdo->prepare("SELECT idchamado, status, atendente, titulo FROM chamado WHERE idchamado = :id");
            $stmtCham->execute(['id' => $id]);
            $chamado = $stmtCham->fetch(PDO::FETCH_ASSOC);

            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }

            $novoStatus = (int)$chamado['status'];
            // Se estiver Aberto (0), move automaticamente para Em Análise (1)
            if ($novoStatus === 0) {
                $novoStatus = 1;
            }

            $stmtUp = $this->pdo->prepare("UPDATE chamado SET atendente = :at, status = :st, atualizado_em = NOW() WHERE idchamado = :id");
            $stmtUp->execute([
                'at' => $u['username'],
                'st' => $novoStatus,
                'id' => $id
            ]);

            // Grava operação na timeline
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, 'Chamado assumido para atendimento técnico', NOW(), :user, 2, 'atendimento', :det)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'user' => $u['username'],
                'det' => "O técnico {$u['username']} assumiu a responsabilidade pela condução deste chamado."
            ]);

            $this->notificarEventoChamado(
                $id,
                "Chamado #{$id} assumido por {$u['username']}",
                "O chamado '" . ($chamado['titulo'] ?: 'Suporte') . "' entrou em atendimento por {$u['username']}.",
                $u
            );

            return $this->json($response, [
                'success' => true,
                'message' => "Chamado #{$id} atribuído com sucesso para {$u['username']}!"
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao assumir chamado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/atualizar-status
     * Move o chamado para outro estágio (Kanban).
     */
    public function atualizarStatus(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $novoStatus = (int)($body['status'] ?? 0);
            $u = $this->usuario($request);

            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            $stCh = $this->pdo->prepare("SELECT idchamado, status, idcliforemp, solicitante, usuario, titulo FROM chamado WHERE idchamado = :id");
            $stCh->execute(['id' => $id]);
            $chamado = $stCh->fetch(PDO::FETCH_ASSOC);
            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }
            if (empty($u['pode_ver_todos'])) {
                return $this->erro($response, 'Apenas a equipe de TI liberada pode alterar o estágio do chamado.', 403);
            }

            $concluidoSql = $novoStatus === 3 ? ', concluido_em = NOW()' : '';

            $stmt = $this->pdo->prepare("UPDATE chamado SET status = :st, atualizado_em = NOW() {$concluidoSql} WHERE idchamado = :id");
            $stmt->execute(['st' => $novoStatus, 'id' => $id]);

            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();

            $mapaStatus = $this->mapaStatus();
            $nomeStatus = $mapaStatus[$novoStatus]['label'] ?? 'Alteração de Status';

            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, :desc, NOW(), :user, 2, 'mudanca_status', :det)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'desc' => "Status alterado para: {$nomeStatus}",
                'user' => $u['username'],
                'det' => $this->limpaWin1252(trim((string)($body['observacao'] ?? 'Movido pelo painel de chamados')))
            ]);

            $this->notificarEventoChamado(
                $id,
                "Chamado #{$id} atualizado: {$nomeStatus}",
                "{$u['username']} alterou o status para {$nomeStatus}.",
                $u
            );

            return $this->json($response, [
                'success' => true,
                'message' => "Chamado #{$id} atualizado para '{$nomeStatus}'!"
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao atualizar status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/atualizar-prioridade
     * Atualiza prioridade (ordem na coluna), criticidade e opção de enviar ao topo da fila.
     */
    public function atualizarPrioridade(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $u = $this->usuario($request);

            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            $stCh = $this->pdo->prepare("SELECT idchamado, status, prioridade, criticidade, idcliforemp, solicitante, usuario, titulo FROM chamado WHERE idchamado = :id");
            $stCh->execute(['id' => $id]);
            $chamado = $stCh->fetch(PDO::FETCH_ASSOC);
            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }
            if (empty($u['pode_ver_todos'])) {
                return $this->erro($response, 'Apenas a equipe de TI liberada pode alterar a prioridade ou criticidade após a criação.', 403);
            }

            $novaPrioridade = isset($body['prioridade']) ? (int)$body['prioridade'] : null;
            $novaCriticidade = isset($body['criticidade']) ? strtolower(trim((string)$body['criticidade'])) : null;
            $enviarTopo = !empty($body['topo']);

            if ($novaCriticidade !== null && !in_array($novaCriticidade, ['critica', 'alta', 'media', 'baixa'], true)) {
                return $this->erro($response, 'Criticidade inválida.');
            }
            if (!$enviarTopo && $novaPrioridade === null && $novaCriticidade === null) {
                return $this->erro($response, 'Informe prioridade, criticidade ou ação de topo.');
            }

            $this->pdo->beginTransaction();

            $sets = ['atualizado_em = NOW()'];
            $params = ['id' => $id];
            $alteracoes = [];

            if ($enviarTopo) {
                $stBump = $this->pdo->prepare("
                    UPDATE chamado
                    SET prioridade = LEAST(COALESCE(prioridade, 10) + 1, 999)
                    WHERE status = :st AND idchamado <> :id
                ");
                $stBump->execute(['st' => (int)$chamado['status'], 'id' => $id]);
                $novaPrioridade = 1;
            }

            if ($novaPrioridade !== null && $novaPrioridade > 0) {
                $sets[] = 'prioridade = :prio';
                $params['prio'] = $novaPrioridade;
                $prioAnt = (int)($chamado['prioridade'] ?? 10);
                if ($enviarTopo) {
                    $alteracoes[] = "Enviado para o topo da fila (Prioridade #1)";
                } elseif ($prioAnt !== $novaPrioridade) {
                    $alteracoes[] = "Prioridade: #{$prioAnt} -> #{$novaPrioridade}";
                }
            }

            if ($novaCriticidade !== null) {
                $sets[] = 'criticidade = :crit';
                $params['crit'] = $novaCriticidade;
                $critAnt = (string)($chamado['criticidade'] ?? 'media');
                if ($critAnt !== $novaCriticidade) {
                    $alteracoes[] = "Criticidade: " . strtoupper($critAnt) . " -> " . strtoupper($novaCriticidade);
                }
            }

            $stmt = $this->pdo->prepare('UPDATE chamado SET ' . implode(', ', $sets) . ' WHERE idchamado = :id');
            $stmt->execute($params);

            $descOp = !empty($alteracoes) ? implode(' | ', $alteracoes) : 'Priorização atualizada';
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, :desc, NOW(), :user, 2, 'mudanca_prioridade', :det)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'desc' => $this->limpaWin1252(mb_substr($descOp, 0, 200)),
                'user' => $u['username'],
                'det' => $this->limpaWin1252(trim((string)($body['observacao'] ?? 'Ajuste de prioridade pelo painel')))
            ]);

            $this->pdo->commit();

            $this->notificarEventoChamado(
                $id,
                "Prioridade ajustada no Chamado #{$id}",
                "{$u['username']}: {$descOp}",
                $u
            );

            return $this->json($response, [
                'success' => true,
                'message' => "Chamado #{$id} atualizado: {$descOp}"
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->erro($response, 'Erro ao atualizar prioridade: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/editar
     * Edição completa de título, texto/relato, categoria, sistema/módulo, atendente, prioridade e anexo.
     */
    public function editar(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? $body['id'] ?? 0);
            $u = $this->usuario($request);

            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            $stCh = $this->pdo->prepare("SELECT * FROM chamado WHERE idchamado = :id");
            $stCh->execute(['id' => $id]);
            $chamado = $stCh->fetch(PDO::FETCH_ASSOC);

            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }
            if (!$this->podeEditarChamado($chamado, $u)) {
                return $this->erro($response, 'Sem permissão para editar este chamado.', 403);
            }

            $ehTiLiberado = !empty($u['pode_ver_todos']);
            if (!$ehTiLiberado && (int)($chamado['status'] ?? 0) >= 3) {
                return $this->erro($response, 'Chamados concluídos ou cancelados não podem ser editados pelo solicitante.', 403);
            }

            $sets = [];
            $params = ['id' => $id];
            $alteracoes = [];

            if (array_key_exists('titulo', $body)) {
                $novoTitulo = trim((string)$body['titulo']);
                if (mb_strlen($novoTitulo) >= 3 && $novoTitulo !== trim((string)($chamado['titulo'] ?? ''))) {
                    $sets[] = 'titulo = :titulo';
                    $params['titulo'] = $this->limpaWin1252($novoTitulo);
                    $alteracoes[] = 'Título atualizado';
                }
            }

            if (array_key_exists('motivo', $body)) {
                $novoMotivo = trim((string)$body['motivo']);
                if (mb_strlen($novoMotivo) >= 3 && $novoMotivo !== trim((string)($chamado['motivo'] ?? ''))) {
                    $sets[] = 'motivo = :motivo';
                    $params['motivo'] = $this->limpaWin1252($novoMotivo);
                    if (!array_key_exists('observacao', $body)) {
                        $sets[] = 'observacao = :observacao_sync';
                        $params['observacao_sync'] = $this->limpaWin1252($novoMotivo);
                    }
                    $alteracoes[] = 'Texto da solicitação atualizado';
                }
            }

            if (array_key_exists('observacao', $body)) {
                $novaObs = trim((string)$body['observacao']);
                if ($novaObs !== trim((string)($chamado['observacao'] ?? ''))) {
                    $sets[] = 'observacao = :observacao';
                    $params['observacao'] = $this->limpaWin1252($novaObs);
                    if (!in_array('Texto da solicitação atualizado', $alteracoes, true)) {
                        $alteracoes[] = 'Observação técnica atualizada';
                    }
                }
            }

            if ($ehTiLiberado) {
                if (array_key_exists('criticidade', $body)) {
                    $novaCrit = strtolower(trim((string)$body['criticidade']));
                    if (in_array($novaCrit, ['critica', 'alta', 'media', 'baixa'], true) && $novaCrit !== (string)($chamado['criticidade'] ?? '')) {
                        $sets[] = 'criticidade = :criticidade';
                        $params['criticidade'] = $novaCrit;
                        $alteracoes[] = 'Criticidade: ' . strtoupper((string)($chamado['criticidade'] ?? 'media')) . ' -> ' . strtoupper($novaCrit);
                    }
                }

                if (array_key_exists('prioridade', $body)) {
                    $novaPrio = (int)$body['prioridade'];
                    if ($novaPrio > 0 && $novaPrio !== (int)($chamado['prioridade'] ?? 10)) {
                        $sets[] = 'prioridade = :prioridade';
                        $params['prioridade'] = $novaPrio;
                        $alteracoes[] = 'Prioridade: #' . (int)($chamado['prioridade'] ?? 10) . ' -> #' . $novaPrio;
                    }
                }

                if (array_key_exists('categoria', $body)) {
                    $novaCat = trim((string)$body['categoria']);
                    if ($novaCat !== '' && $novaCat !== (string)($chamado['categoria'] ?? '')) {
                        $sets[] = 'categoria = :categoria';
                        $params['categoria'] = $this->limpaWin1252($novaCat);
                        $alteracoes[] = "Categoria: {$novaCat}";
                    }
                }

                if (array_key_exists('tipo_sistema', $body)) {
                    $novoSis = trim((string)$body['tipo_sistema']);
                    if ($novoSis !== '' && $novoSis !== (string)($chamado['tipo_sistema'] ?? '')) {
                        $sets[] = 'tipo_sistema = :tipo_sistema';
                        $params['tipo_sistema'] = $this->limpaWin1252($novoSis);
                        $alteracoes[] = "Sistema: {$novoSis}";
                    }
                }

                if (array_key_exists('subtipo_item', $body)) {
                    $novoSub = trim((string)$body['subtipo_item']);
                    if ($novoSub !== (string)($chamado['subtipo_item'] ?? '')) {
                        $sets[] = 'subtipo_item = :subtipo_item';
                        $params['subtipo_item'] = $this->limpaWin1252($novoSub);
                    }
                }

                if (array_key_exists('equipamento_detalhe', $body)) {
                    $novoEquip = trim((string)$body['equipamento_detalhe']);
                    if ($novoEquip !== (string)($chamado['equipamento_detalhe'] ?? '')) {
                        $sets[] = 'equipamento_detalhe = :equipamento_detalhe';
                        $params['equipamento_detalhe'] = $novoEquip !== '' ? $this->limpaWin1252($novoEquip) : null;
                    }
                }

                if (array_key_exists('modulo_slug', $body)) {
                    $novoSlug = trim((string)$body['modulo_slug']);
                    if ($novoSlug !== (string)($chamado['modulo_slug'] ?? '')) {
                        $sets[] = 'modulo_slug = :modulo_slug';
                        $params['modulo_slug'] = $this->limpaWin1252($novoSlug ?: 'geral');

                        $novoNomeMod = trim((string)($body['modulo_nome'] ?? ''));
                        if ($novoNomeMod === '' && $novoSlug !== '' && $novoSlug !== 'geral') {
                            $stMod = $this->pdo->prepare("SELECT nome FROM sistema_modulos WHERE slug = :slug LIMIT 1");
                            $stMod->execute(['slug' => $novoSlug]);
                            $novoNomeMod = (string)($stMod->fetchColumn() ?: $novoSlug);
                        }
                        $sets[] = 'modulo_nome = :modulo_nome';
                        $params['modulo_nome'] = $this->limpaWin1252($novoNomeMod ?: 'Portal Geral');
                        $alteracoes[] = 'Módulo alterado para ' . ($novoNomeMod ?: 'Portal Geral');
                    }
                } elseif (array_key_exists('modulo_nome', $body)) {
                    $novoNomeMod = trim((string)$body['modulo_nome']);
                    if ($novoNomeMod !== '' && $novoNomeMod !== (string)($chamado['modulo_nome'] ?? '')) {
                        $sets[] = 'modulo_nome = :modulo_nome';
                        $params['modulo_nome'] = $this->limpaWin1252($novoNomeMod);
                    }
                }

                if (array_key_exists('atendente', $body)) {
                    $novoAtendente = strtoupper(trim((string)$body['atendente']));
                    if ($novoAtendente !== '' && $novoAtendente !== strtoupper(trim((string)($chamado['atendente'] ?? '')))) {
                        $sets[] = 'atendente = :atendente';
                        $params['atendente'] = $this->limpaWin1252($novoAtendente);
                        $alteracoes[] = "Atendente: {$novoAtendente}";
                    }
                }

                if (array_key_exists('solicitante', $body)) {
                    $novoSolInput = strtoupper(trim((string)$body['solicitante']));
                    $solAtual = strtoupper(trim((string)($chamado['solicitante'] ?? '')));
                    if ($novoSolInput !== '' && $novoSolInput !== $solAtual) {
                        $dadosSol = $this->resolverDadosSolicitante($novoSolInput, $u);
                        $sets[] = 'solicitante = :solicitante';
                        $params['solicitante'] = $this->limpaWin1252($dadosSol['solicitante']);
                        $sets[] = 'usuario = :usuario_sol';
                        $params['usuario_sol'] = $this->limpaWin1252($dadosSol['solicitante']);
                        if ($dadosSol['idcliforemp'] > 0) {
                            $sets[] = 'idcliforemp = :idcliforemp_sol';
                            $params['idcliforemp_sol'] = $dadosSol['idcliforemp'];
                        }
                        $alteracoes[] = "Solicitante: {$solAtual} -> {$dadosSol['solicitante']}";
                    }
                }
            }

            if (array_key_exists('anexo_url', $body)) {
                $novoAnexo = trim((string)$body['anexo_url']);
                if ($novoAnexo !== (string)($chamado['anexo_url'] ?? '')) {
                    $sets[] = 'anexo_url = :anexo_url';
                    $params['anexo_url'] = $this->limpaWin1252($novoAnexo);
                    $alteracoes[] = $novoAnexo !== '' ? 'Anexo principal atualizado' : 'Anexo principal removido';
                }
            }

            if (empty($sets)) {
                return $this->json($response, ['success' => true, 'message' => 'Nenhuma alteração detectada.']);
            }

            $sets[] = 'atualizado_em = NOW()';
            $sql = 'UPDATE chamado SET ' . implode(', ', $sets) . ' WHERE idchamado = :id';
            $stUp = $this->pdo->prepare($sql);
            $stUp->execute($params);

            $resumo = !empty($alteracoes) ? implode(' | ', $alteracoes) : 'Edição realizada no painel';
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();
            $stOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, 'Dados do chamado atualizados', NOW(), :user, 2, 'edicao', :det)
            ");
            $stOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'user' => $u['username'],
                'det' => $this->limpaWin1252($resumo)
            ]);

            $this->notificarEventoChamado(
                $id,
                "Chamado #{$id} editado por {$u['username']}",
                $resumo,
                $u
            );

            return $this->json($response, [
                'success' => true,
                'message' => "Chamado #{$id} atualizado com sucesso!"
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao editar chamado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/comentario
     * Adiciona um comentário ao chamado.
     */
    public function adicionarComentario(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $body['tipo'] = 'comentario';
            return $this->adicionarInteracao($request, $response);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao adicionar comentário: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/ordenar
     * Ordena múltiplos chamados e/ou move entre colunas (Kanban drag-and-drop).
     */
    public function ordenarChamados(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $ordens = $body['ordem'] ?? [];
            if (!is_array($ordens) || empty($ordens)) {
                return $this->erro($response, 'Payload de ordem inválido.');
            }

            $u = $this->usuario($request);
            if (empty($u['pode_ver_todos'])) {
                return $this->erro($response, 'Apenas a equipe de TI liberada pode mover ou reordenar chamados no Kanban.', 403);
            }

            $mapaStatus = $this->mapaStatus();
            $mudancasNotificar = [];

            $this->pdo->beginTransaction();

            $stBusca = $this->pdo->prepare("SELECT idchamado, status, prioridade, idcliforemp, solicitante, usuario, titulo FROM chamado WHERE idchamado = :id");

            foreach ($ordens as $idx => $item) {
                $id = (int)($item['idchamado'] ?? 0);
                $novoStatus = (int)($item['status'] ?? 0);
                $novaPrioridade = (int)($item['prioridade'] ?? ($idx + 1));
                if ($id <= 0) {
                    continue;
                }
                if ($novaPrioridade <= 0) {
                    $novaPrioridade = $idx + 1;
                }

                $stBusca->execute(['id' => $id]);
                $chAtual = $stBusca->fetch(PDO::FETCH_ASSOC);
                if (!$chAtual || !$this->podeEditarChamado($chAtual, $u)) {
                    continue;
                }

                $statusAtual = (int)($chAtual['status'] ?? 0);
                $concluidoSql = ($novoStatus === 3 && $statusAtual !== 3) ? ', concluido_em = NOW()' : '';

                $stmt = $this->pdo->prepare("UPDATE chamado SET status = :st, prioridade = :prio, atualizado_em = NOW() {$concluidoSql} WHERE idchamado = :id");
                $stmt->execute(['st' => $novoStatus, 'prio' => $novaPrioridade, 'id' => $id]);

                if ($statusAtual !== $novoStatus) {
                    $nomeStatus = $mapaStatus[$novoStatus]['label'] ?? 'Outro Estágio';
                    $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();
                    $stmtOp = $this->pdo->prepare("
                        INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                        VALUES (:id, :op, :desc, NOW(), :user, 2, 'mudanca_status', :det)
                    ");
                    $stmtOp->execute([
                        'id' => $id,
                        'op' => $nextOp,
                        'desc' => "Status alterado para: {$nomeStatus}",
                        'user' => $u['username'],
                        'det' => "Movido no quadro Kanban (posição #{$novaPrioridade})"
                    ]);
                    $mudancasNotificar[] = ['id' => $id, 'status_nome' => $nomeStatus];
                }
            }

            $this->pdo->commit();

            foreach ($mudancasNotificar as $m) {
                $this->notificarEventoChamado(
                    $m['id'],
                    "Chamado #{$m['id']} movido para {$m['status_nome']}",
                    "{$u['username']} moveu o chamado para '{$m['status_nome']}' no Kanban.",
                    $u
                );
            }

            return $this->json($response, [
                'success' => true,
                'message' => 'Ordenação aplicada com sucesso.'
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->erro($response, 'Erro ao ordenar chamados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/adicionar-interacao
     * Insere comentário, progresso, evidência/print ou resolução na timeline.
     */
    public function adicionarInteracao(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $mensagem = trim((string)($body['mensagem'] ?? ''));
            $tipo = trim((string)($body['tipo'] ?? 'comentario'));
            $ehResolucao = !empty($body['eh_resolucao']);
            $versaoEntrega = trim((string)($body['versao_entrega'] ?? ''));
            $anexoUrl = trim((string)($body['anexo_url'] ?? ''));
            $u = $this->usuario($request);

            if ($id <= 0 || (mb_strlen($mensagem) < 2 && empty($anexoUrl))) {
                return $this->erro($response, 'Informe o chamado e o conteúdo da mensagem ou anexo.');
            }

            $stCh = $this->pdo->prepare("SELECT idchamado, status, idcliforemp, solicitante, usuario, titulo FROM chamado WHERE idchamado = :id");
            $stCh->execute(['id' => $id]);
            $chamado = $stCh->fetch(PDO::FETCH_ASSOC);
            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }
            if (!$this->podeEditarChamado($chamado, $u)) {
                return $this->erro($response, 'Sem permissão para comentar neste chamado.', 403);
            }
            if (empty($u['pode_ver_todos'])) {
                $ehResolucao = false;
                if (!in_array($tipo, ['comentario', 'evidencia', 'duvida'], true)) {
                    $tipo = 'comentario';
                }
            }

            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();

            $mapaDescTipo = [
                'progresso' => 'Atualização de Progresso Técnico',
                'evidencia' => 'Evidência / Print Anexado',
                'duvida'    => 'Solicitação de Retorno / Dúvida',
                'resolucao' => 'Resolução Técnica Aplicada',
            ];
            if ($ehResolucao) {
                $desc = 'Resolução Técnica Aplicada';
                $tipo = 'resolucao';
            } elseif (!empty($anexoUrl) && empty($mensagem)) {
                $desc = 'Anexo / Print adicionado';
            } else {
                $desc = $mapaDescTipo[$tipo] ?? 'Novo Comentário';
            }

            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes, anexo_url)
                VALUES (:id, :op, :desc, NOW(), :user, 3, :tipo, :det, :anexo_url)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'desc' => $desc,
                'user' => $u['username'],
                'tipo' => $tipo,
                'det' => $this->limpaWin1252($mensagem),
                'anexo_url' => $this->limpaWin1252($anexoUrl)
            ]);

            if ($ehResolucao) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE chamado 
                    SET status = 3,
                        concluido_em = NOW(),
                        solucao_tecnica = :sol,
                        versao_entrega = :ver,
                        atualizado_em = NOW()
                    WHERE idchamado = :id
                ");
                $stmtUp->execute([
                    'sol' => $this->limpaWin1252($mensagem ?: 'Chamado resolvido.'),
                    'ver' => $versaoEntrega ?: 'v3.5',
                    'id' => $id
                ]);
            } else {
                $stmtUp = $this->pdo->prepare("UPDATE chamado SET atualizado_em = NOW() WHERE idchamado = :id");
                $stmtUp->execute(['id' => $id]);
            }

            $msgNotif = $mensagem !== '' ? mb_substr($mensagem, 0, 140) : 'Novo print/anexo enviado no chamado.';
            $this->notificarEventoChamado(
                $id,
                $ehResolucao ? "Chamado #{$id} Concluído!" : "Nova interação no Chamado #{$id}",
                "{$u['username']}: {$msgNotif}",
                $u
            );

            return $this->json($response, [
                'success' => true,
                'message' => $ehResolucao ? 'Chamado marcado como Concluído com sucesso!' : 'Interação registrada com sucesso!'
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao registrar interação: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/limpar-notificacoes
     * Marca uma notificação específica, todas de um chamado ou todas as notificações de chamados como lidas.
     */
    public function limparNotificacoes(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $idNotificacao = (int)($body['idnotificacao'] ?? 0);
            $idChamado = (int)($body['idchamado'] ?? 0);
            $u = $this->usuario($request);
            $uid = (int)($u['idusuario'] ?? 0);

            if ($uid <= 0) {
                return $this->json($response, ['success' => true, 'message' => 'Nenhuma notificação pendente.']);
            }

            if ($idNotificacao > 0) {
                $st = $this->pdo->prepare("
                    UPDATE notificacoes
                    SET lida = TRUE, lida_em = NOW()
                    WHERE id = :id
                      AND tipo = 'chamado'
                      AND (idusuario = :uid OR idusuario IS NULL)
                      AND lida = FALSE
                ");
                $st->execute(['id' => $idNotificacao, 'uid' => $uid]);
            } elseif ($idChamado > 0) {
                $st = $this->pdo->prepare("
                    UPDATE notificacoes
                    SET lida = TRUE, lida_em = NOW()
                    WHERE id_referencia = :idchamado
                      AND tipo = 'chamado'
                      AND (idusuario = :uid OR idusuario IS NULL)
                      AND lida = FALSE
                ");
                $st->execute(['idchamado' => $idChamado, 'uid' => $uid]);
            } else {
                $st = $this->pdo->prepare("
                    UPDATE notificacoes
                    SET lida = TRUE, lida_em = NOW()
                    WHERE tipo = 'chamado'
                      AND (idusuario = :uid OR idusuario IS NULL)
                      AND lida = FALSE
                ");
                $st->execute(['uid' => $uid]);
            }

            return $this->json($response, [
                'success' => true,
                'message' => 'Notificações limpas com sucesso.'
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao limpar notificações: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/upload-anexo
     * Upload de imagens, prints (clipboard/base64) ou arquivos anexos.
     */
    public function uploadAnexo(Request $request, Response $response): Response
    {
        try {
            $u = $this->usuario($request);
            $uploadDir = __DIR__ . '/../../../portal/uploads/chamados';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $extensoesPermitidas = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt', 'log'];
            $extImagens = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $maxBytes = 15 * 1024 * 1024; // 15MB
            $appBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/API/') !== false) ? '/API' : '';

            $body = $this->parsedBody($request);
            $rawBase64 = (string)($body['imagem_base64'] ?? $body['arquivo_base64'] ?? '');

            // 1. Caso base64 (Print Screen / Ctrl+V ou FileReader JSON)
            if ($rawBase64 !== '') {
                $nomeOriginal = trim((string)($body['nome_original'] ?? $body['nome_arquivo'] ?? ''));
                $ext = $nomeOriginal !== '' ? strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION)) : '';

                if (preg_match('/^data:([^;]+);base64,/', $rawBase64, $m)) {
                    $mime = strtolower(trim($m[1]));
                    $mapaMime = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                        'application/pdf' => 'pdf',
                        'text/plain' => 'txt',
                        'text/x-log' => 'log',
                    ];
                    if ($ext === '' || !in_array($ext, $extensoesPermitidas, true)) {
                        $ext = $mapaMime[$mime] ?? '';
                    }
                    $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
                }

                if ($ext === '') {
                    $ext = 'png';
                }
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    return $this->erro($response, 'Extensão de arquivo não permitida.');
                }

                $data = base64_decode($rawBase64, true);
                if ($data === false) {
                    $data = base64_decode($rawBase64);
                }
                if ($data === false || strlen($data) === 0) {
                    return $this->erro($response, 'Dados do arquivo/print em base64 inválidos.');
                }
                if (strlen($data) > $maxBytes) {
                    return $this->erro($response, 'Arquivo excede o limite de 15MB.');
                }

                $prefixo = in_array($ext, $extImagens, true) ? 'print_' : 'anexo_';
                $nomeArquivo = $prefixo . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $caminhoFisico = $uploadDir . '/' . $nomeArquivo;

                if (file_put_contents($caminhoFisico, $data) === false) {
                    return $this->erro($response, 'Falha ao salvar arquivo no servidor.', 500);
                }

                $urlRelativa = $appBase . '/portal/uploads/chamados/' . $nomeArquivo;

                return $this->json($response, [
                    'success' => true,
                    'url' => $urlRelativa,
                    'nome' => $nomeOriginal !== '' ? $nomeOriginal : $nomeArquivo,
                    'tamanho' => strlen($data),
                    'tipo' => in_array($ext, $extImagens, true) ? 'imagem' : 'documento'
                ]);
            }

            // 2. Caso Multipart / Upload tradicional ($_FILES ou PSR-7)
            $files = $request->getUploadedFiles();
            $uploadedFile = $files['anexo'] ?? $files['file'] ?? null;

            if (!$uploadedFile && !empty($_FILES['anexo']['tmp_name'])) {
                $tmp = $_FILES['anexo']['tmp_name'];
                $origName = (string)$_FILES['anexo']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    return $this->erro($response, 'Extensão de arquivo não permitida.');
                }
                if ((int)$_FILES['anexo']['size'] > $maxBytes) {
                    return $this->erro($response, 'Arquivo excede o limite de 15MB.');
                }
                $nomeArquivo = 'anexo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $nomeArquivo;
                if (!move_uploaded_file($tmp, $dest)) {
                    return $this->erro($response, 'Erro ao mover anexo.', 500);
                }
                return $this->json($response, [
                    'success' => true,
                    'url' => $appBase . '/portal/uploads/chamados/' . $nomeArquivo,
                    'nome' => $origName,
                    'tipo' => in_array($ext, $extImagens, true) ? 'imagem' : 'documento'
                ]);
            }

            if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
                $origName = (string)$uploadedFile->getClientFilename();
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    return $this->erro($response, 'Extensão de arquivo não permitida.');
                }
                if ($uploadedFile->getSize() > $maxBytes) {
                    return $this->erro($response, 'Arquivo excede o limite de 15MB.');
                }
                $nomeArquivo = 'anexo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $nomeArquivo;
                $uploadedFile->moveTo($dest);

                return $this->json($response, [
                    'success' => true,
                    'url' => $appBase . '/portal/uploads/chamados/' . $nomeArquivo,
                    'nome' => $origName,
                    'tipo' => in_array($ext, $extImagens, true) ? 'imagem' : 'documento'
                ]);
            }

            return $this->erro($response, 'Nenhum anexo ou print foi enviado.');
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro no upload: ' . $e->getMessage(), 500);
        }
    }
}
