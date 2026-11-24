<?php

namespace Nutricional\Controllers;

use Nutricional\Services\Programa365Service;
use Nutricional\Utils\UserScope;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Programa365Controller
{
    private PDO $pdo;
    private Programa365Service $service;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->service = new Programa365Service($this->pdo);
    }

    private function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function podeAdministrar(UserScope $scope): bool
    {
        return $scope->isAdmin;
    }

    private function resolverParticipantesPermitidos(UserScope $scope, int $idcampanha): ?array
    {
        if ($scope->isAdmin || $scope->temAcessoTotal()) {
            return null; // Admin e gerencia master (ex: permite_ver_usuarios=S) visualizam todos
        }

        $filiaisPermitidas = $scope->resolverFiliais();
        $stTodos = $this->pdo->prepare("
            SELECT p.id, p.idvendedor, p.filiais, e.idgestor
            FROM p365_participantes p
            LEFT JOIN p365_equipes e ON e.id = p.idequipe
            WHERE p.idcampanha = ? AND p.ativo = TRUE
        ");
        $stTodos->execute([$idcampanha]);
        $todos = $stTodos->fetchAll(PDO::FETCH_ASSOC);

        $gestoresValid = array_values(array_filter(array_map('intval', $scope->gestores), fn($g) => $g > 0));
        $repsValid = array_values(array_filter(array_map('intval', $scope->representantes), fn($r) => $r > 0));

        // Se nao tem gestor nem representante explicitado e nao esta bloqueado (-1), usa o proprio uid caso seja vendedor
        if (empty($gestoresValid) && empty($repsValid) && !in_array(-1, $scope->gestores, true) && $scope->uid > 0) {
            $repsValid = [$scope->uid];
        }

        $permitidos = [];
        foreach ($todos as $p) {
            $fils = array_map('intval', explode(',', (string)($p['filiais'] ?? '')));
            if (!empty($filiaisPermitidas) && !array_intersect($filiaisPermitidas, $fils)) {
                continue;
            }
            $idVend = (int)$p['idvendedor'];
            $idGest = (int)($p['idgestor'] ?? 0);
            $okGestor = !empty($gestoresValid) && ($idGest > 0 && in_array($idGest, $gestoresValid, true) || in_array($idVend, $gestoresValid, true));
            $okRep = !empty($repsValid) && in_array($idVend, $repsValid, true);
            if ($okGestor || $okRep) {
                $permitidos[] = (int)$p['id'];
            }
        }
        return array_values(array_unique($permitidos));
    }

    public function contexto(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $campanhas = $this->pdo->query("SELECT * FROM p365_campanhas ORDER BY ativo DESC, temporada_ano DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $campanhaAtiva = $this->service->getCampanhaAtiva();
            $permitidos = $campanhaAtiva ? $this->resolverParticipantesPermitidos($scope, (int)$campanhaAtiva['id']) : [];

            return $this->json($response, [
                'is_admin' => $this->podeAdministrar($scope),
                'uid' => $scope->uid,
                'campanhas' => $campanhas,
                'campanha_ativa' => $campanhaAtiva,
                'participantes_permitidos' => $permitidos,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function dashboard(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            if (!$campanha) {
                return $this->json($response, ['error' => 'Nenhuma campanha cadastrada'], 404);
            }
            $cid = (int)$campanha['id'];
            $ano = (int)($q['ano'] ?? $campanha['temporada_ano']);
            $mes = max(1, min(12, (int)($q['mes'] ?? (int)date('n'))));
            $partId = !empty($q['participante_id']) ? (int)$q['participante_id'] : 0;
            $filialFiltro = !empty($q['filial']) ? (int)$q['filial'] : 0;
            $gestorFiltro = !empty($q['idgestor']) ? (int)$q['idgestor'] : 0;

            $permitidos = $this->resolverParticipantesPermitidos($scope, $cid);
            $roundRowsTodos = $this->service->calcularRound($cid, $ano, $mes, $permitidos);

            // Extrair filiais e gestores disponiveis dentro do escopo do usuario
            $filiaisSet = [];
            $gestoresMap = [];
            foreach ($roundRowsTodos as $r) {
                foreach (explode(',', (string)($r['filiais'] ?? '')) as $f) {
                    $fi = (int)trim($f);
                    if ($fi > 0) $filiaisSet[$fi] = $fi;
                }
                if (!empty($r['idgestor'])) {
                    $gestoresMap[(int)$r['idgestor']] = $r['equipe'];
                }
            }

            // Aplicar filtros de Filial e Gestor
            $roundRows = array_values(array_filter($roundRowsTodos, function ($r) use ($filialFiltro, $gestorFiltro) {
                if ($filialFiltro > 0) {
                    $fs = array_map('intval', explode(',', (string)($r['filiais'] ?? '')));
                    if (!in_array($filialFiltro, $fs, true)) return false;
                }
                if ($gestorFiltro > 0 && (int)($r['idgestor'] ?? 0) !== $gestorFiltro) {
                    return false;
                }
                return true;
            }));

            $rankingAnual = $this->service->calcularRankingAnual($cid, $ano, $mes, $permitidos);
            $faixaPorPart = [];
            $rkMap = [];
            foreach ($rankingAnual as $rk) {
                $faixaPorPart[(int)$rk['idparticipante']] = $rk['faixa'];
                $rkMap[(int)$rk['idparticipante']] = $rk;
            }

            $ehAdmin = $this->podeAdministrar($scope);
            $ehGestor = !$ehAdmin && ($scope->temAcessoTotal() || $scope->gestorRestrito());
            $perfil = $ehAdmin ? 'ADMIN' : ($ehGestor ? 'GESTOR' : 'REPRESENTANTE');

            $cfgVis = $this->lerConfigVisibilidade($campanha);
            $rankingGeralOculto = !$this->podeVerRankingGeral($ehAdmin, $ehGestor, $cfgVis);

            // Se apenas 1 participante disponivel (ex: Representante), seleciona ele;
            // Se > 1 e partId == 0 ("Todos"), monta a AUTO-SOMA consolidada do grupo!
            $selecionado = null;
            $xpAnual = 0;
            $bonusAnual = 0.0;
            $posicaoAnual = null;

            if ($partId > 0) {
                foreach ($roundRows as $r) {
                    if ((int)$r['idparticipante'] === $partId) {
                        $selecionado = $r;
                        break;
                    }
                }
            } elseif (count($roundRows) === 1) {
                $selecionado = $roundRows[0];
            } elseif (count($roundRows) > 1) {
                $selecionado = $this->montarAutoSoma($roundRows, $ano, $mes);
            }

            if ($selecionado) {
                if (!empty($selecionado['eh_consolidado'])) {
                    // Auto-soma anual do grupo filtrado (XP medio para faixa representativa + soma de bonus anual)
                    $somaXp = 0;
                    foreach ($roundRows as $r) {
                        $pid = (int)$r['idparticipante'];
                        if (isset($rkMap[$pid])) {
                            $somaXp += (int)$rkMap[$pid]['xp_total'];
                            $bonusAnual += (float)$rkMap[$pid]['bonus_acumulado_rs'];
                        }
                    }
                    $xpAnual = count($roundRows) > 0 ? (int)round($somaXp / count($roundRows)) : 0;
                    $bonusAnual = round($bonusAnual, 2);
                } else {
                    $pid = (int)$selecionado['idparticipante'];
                    if (isset($rkMap[$pid])) {
                        $xpAnual = (int)$rkMap[$pid]['xp_total'];
                        $bonusAnual = (float)$rkMap[$pid]['bonus_acumulado_rs'];
                        $posicaoAnual = $rankingGeralOculto ? null : (int)$rkMap[$pid]['posicao'];
                    }
                }
            }

            // Desafios configurados no mes
            $stD = $this->pdo->prepare("SELECT * FROM p365_desafios_mes WHERE idcampanha = ? AND ano = ? AND mes = ? ORDER BY numero_desafio");
            $stD->execute([$cid, $ano, $mes]);
            $desafios = $stD->fetchAll(PDO::FETCH_ASSOC);

            // Resumo da equipe (visao gerencial / mobile para Gestores e Admins)
            $equipeResumo = array_map(function ($r) use ($faixaPorPart) {
                $r['faixa'] = $faixaPorPart[(int)$r['idparticipante']] ?? Programa365Service::calcularFaixa(0);
                return $r;
            }, $roundRows);

            $filiaisMeta = [];
            if (!empty($filiaisSet)) {
                $inF = implode(',', array_values($filiaisSet));
                $filiaisMeta = $this->pdo->query("SELECT idfilial, nome FROM filial WHERE idfilial IN ($inF) ORDER BY idfilial")->fetchAll(PDO::FETCH_ASSOC);
            }

            $gestoresLista = [];
            foreach ($gestoresMap as $gid => $gnome) {
                $gestoresLista[] = ['idgestor' => $gid, 'nome' => $gnome];
            }

            return $this->json($response, [
                'campanha' => $campanha,
                'ano' => $ano,
                'mes' => $mes,
                'is_admin' => $ehAdmin,
                'perfil' => $perfil,
                'config_visibilidade' => $cfgVis,
                'ranking_geral_oculto' => $rankingGeralOculto,
                'filiais_disponiveis' => $filiaisMeta,
                'gestores_disponiveis' => $gestoresLista,
                'participantes_disponiveis' => array_map(fn($r) => [
                    'id' => $r['idparticipante'],
                    'nome' => $r['nome'],
                    'equipe' => $r['equipe'],
                    'pts_total_mes' => $r['pts_total_mes'],
                ], $roundRows),
                'equipe_resumo' => $equipeResumo,
                'selecionado' => $selecionado,
                'faixa' => Programa365Service::calcularFaixa($xpAnual),
                'xp_anual' => $xpAnual,
                'bonus_anual' => $bonusAnual,
                'posicao_anual' => $posicaoAnual,
                'desafios' => $desafios,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function ranking(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            if (!$campanha) {
                return $this->json($response, ['error' => 'Campanha nao encontrada'], 404);
            }
            $cid = (int)$campanha['id'];
            $ano = (int)($q['ano'] ?? $campanha['temporada_ano']);
            $mes = max(1, min(12, (int)($q['mes'] ?? (int)date('n'))));
            $filialFiltro = !empty($q['filial']) ? (int)$q['filial'] : 0;
            $gestorFiltro = !empty($q['idgestor']) ? (int)$q['idgestor'] : 0;

            $ehAdmin = $this->podeAdministrar($scope);
            $ehGestor = !$ehAdmin && ($scope->temAcessoTotal() || $scope->gestorRestrito());
            $cfgVis = $this->lerConfigVisibilidade($campanha);
            $rankingGeralOculto = !$this->podeVerRankingGeral($ehAdmin, $ehGestor, $cfgVis);

            $permitidos = $this->resolverParticipantesPermitidos($scope, $cid);
            $round = $this->service->calcularRound($cid, $ano, $mes, $permitidos);
            $anual = $rankingGeralOculto ? [] : $this->service->calcularRankingAnual($cid, $ano, $mes, $permitidos);

            $filtrar = function (array $lista) use ($filialFiltro, $gestorFiltro): array {
                return array_values(array_filter($lista, function ($r) use ($filialFiltro, $gestorFiltro) {
                    if ($filialFiltro > 0) {
                        $fs = array_map('intval', explode(',', (string)($r['filiais'] ?? '')));
                        if (!in_array($filialFiltro, $fs, true)) return false;
                    }
                    if ($gestorFiltro > 0 && (int)($r['idgestor'] ?? 0) !== $gestorFiltro) {
                        return false;
                    }
                    return true;
                }));
            };

            return $this->json($response, [
                'campanha' => $campanha,
                'ano' => $ano,
                'mes' => $mes,
                'ranking_geral_oculto' => $rankingGeralOculto,
                'config_visibilidade' => $cfgVis,
                'ranking_round' => $filtrar($round),
                'ranking_anual' => $filtrar($anual),
                'premiacao_anual' => Programa365Service::catalogoPremiacaoAnual(),
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function pins(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            if (!$campanha) {
                return $this->json($response, [
                    'faixas_detalhadas' => Programa365Service::catalogoFaixasDetalhado(),
                    'catalogo' => Programa365Service::catalogoPins(),
                    'desbloqueados' => [],
                ]);
            }
            $cid = (int)$campanha['id'];
            $ano = (int)($q['ano'] ?? $campanha['temporada_ano']);
            $mes = max(1, min(12, (int)($q['mes'] ?? (int)date('n'))));
            $partId = (int)($q['participante_id'] ?? 0);

            $permitidos = $this->resolverParticipantesPermitidos($scope, $cid);
            if ($partId > 0) {
                if ($permitidos !== null && !in_array($partId, $permitidos, true)) {
                    $partId = $permitidos[0] ?? 0;
                }
                $alvoIds = $partId > 0 ? [$partId] : [];
            } else {
                if ($permitidos === null) {
                    $stAll = $this->pdo->prepare("SELECT id FROM p365_participantes WHERE idcampanha = ? AND ativo = TRUE");
                    $stAll->execute([$cid]);
                    $alvoIds = array_map('intval', $stAll->fetchAll(PDO::FETCH_COLUMN));
                } else {
                    $alvoIds = $permitidos;
                }
            }

            $desbloqueados = $this->service->avaliarPinsParticipante($cid, $ano, $mes, $alvoIds);

            return $this->json($response, [
                'faixas_detalhadas' => Programa365Service::catalogoFaixasDetalhado(),
                'catalogo' => Programa365Service::catalogoPins(),
                'desbloqueados' => $desbloqueados,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function extrato(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            if (!$campanha) return $this->json($response, ['rounds' => [], 'extras' => []]);

            $cid = (int)$campanha['id'];
            $ano = (int)($q['ano'] ?? $campanha['temporada_ano']);
            $partId = (int)($q['participante_id'] ?? 0);

            $permitidos = $this->resolverParticipantesPermitidos($scope, $cid);
            if ($permitidos !== null && !in_array($partId, $permitidos, true)) {
                $partId = $permitidos[0] ?? 0;
            }
            if ($partId <= 0) {
                return $this->json($response, ['rounds' => [], 'extras' => []]);
            }

            $stR = $this->pdo->prepare("SELECT * FROM p365_round_fechamento WHERE idcampanha = ? AND idparticipante = ? AND ano = ? ORDER BY mes");
            $stR->execute([$cid, $partId, $ano]);

            $stE = $this->pdo->prepare("SELECT * FROM p365_lancamentos_extras WHERE idcampanha = ? AND idparticipante = ? AND ano = ? ORDER BY mes, id DESC");
            $stE->execute([$cid, $partId, $ano]);

            return $this->json($response, [
                'rounds' => $stR->fetchAll(PDO::FETCH_ASSOC),
                'extras' => $stE->fetchAll(PDO::FETCH_ASSOC),
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // AREA ADMINISTRATIVA
    // =========================================================================

    public function adminEstrutura(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) {
            return $this->json($response, ['error' => 'Acesso restrito a administradores'], 403);
        }

        try {
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            $cid = $campanha ? (int)$campanha['id'] : 0;

            $campanhas = $this->pdo->query("SELECT * FROM p365_campanhas ORDER BY temporada_ano DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $equipes = [];
            $participantes = [];
            if ($cid > 0) {
                $stE = $this->pdo->prepare("
                    SELECT e.*, COALESCE(NULLIF(TRIM(c.fantasia), ''), c.razao) AS gestor_nome
                    FROM p365_equipes e
                    LEFT JOIN cliforemp c ON c.idcliforemp = e.idgestor
                    WHERE e.idcampanha = ?
                    ORDER BY e.nome
                ");
                $stE->execute([$cid]);
                $equipes = $stE->fetchAll(PDO::FETCH_ASSOC);

                $stP = $this->pdo->prepare("SELECT p.*, e.nome AS equipe_nome FROM p365_participantes p LEFT JOIN p365_equipes e ON e.id = p.idequipe WHERE p.idcampanha = ? ORDER BY p.nome_exibicao");
                $stP->execute([$cid]);
                $participantes = $stP->fetchAll(PDO::FETCH_ASSOC);
            }

            // Vendedores/Representantes ativos no ERP (vw_gestor_repre)
            $vendedoresErp = $this->pdo->query("
                SELECT DISTINCT idrepresentante AS idvendedor,
                       TRIM(LEADING '* ' FROM representante) AS nome,
                       idgestor, gestor, idfilial
                FROM vw_gestor_repre
                WHERE COALESCE(inativo, 'N') = 'N'
                  AND idrepresentante > 0
                ORDER BY nome
            ")->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'campanhas' => $campanhas,
                'equipes' => $equipes,
                'participantes' => $participantes,
                'vendedores_erp' => $vendedoresErp,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function sincronizarErp(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) {
            return $this->json($response, ['error' => 'Acesso restrito a administradores'], 403);
        }
        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        if ($cid <= 0) {
            $camp = $this->service->getCampanhaAtiva();
            $cid = $camp ? (int)$camp['id'] : 0;
        }
        if ($cid <= 0) return $this->json($response, ['error' => 'Campanha nao encontrada'], 404);

        try {
            $res = $this->service->sincronizarComErp($cid);
            return $this->json($response, array_merge(['success' => true], $res));
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function salvarCampanha(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $id = (int)($in['id'] ?? 0);
        $codigo = strtoupper(trim((string)($in['codigo'] ?? '')));
        $nome = trim((string)($in['nome'] ?? ''));
        $ano = (int)($in['temporada_ano'] ?? 2027);
        $tema = trim((string)($in['tema'] ?? 'JIU-JITSU'));
        $subtitulo = trim((string)($in['subtitulo'] ?? 'O Caminho da Faixa Preta'));
        $di = $in['data_inicio'] ?? "$ano-01-01";
        $df = $in['data_fim'] ?? "$ano-12-31";
        $piso = max(1, (int)($in['piso_carteira_positivacao'] ?? 60));
        $ativo = !isset($in['ativo']) || (bool)$in['ativo'];

        if ($codigo === '' || $nome === '') {
            return $this->json($response, ['error' => 'Codigo e nome sao obrigatorios'], 400);
        }

        try {
            if ($id > 0) {
                $this->pdo->prepare("
                    UPDATE p365_campanhas SET codigo=?, nome=?, temporada_ano=?, tema=?, subtitulo=?, data_inicio=?, data_fim=?, piso_carteira_positivacao=?, ativo=?, atualizado_em=NOW()
                    WHERE id=?
                ")->execute([$codigo, $nome, $ano, $tema, $subtitulo, $di, $df, $piso, $ativo ? 't' : 'f', $id]);
            } else {
                $st = $this->pdo->prepare("
                    INSERT INTO p365_campanhas (codigo, nome, temporada_ano, tema, subtitulo, data_inicio, data_fim, piso_carteira_positivacao, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
                ");
                $st->execute([$codigo, $nome, $ano, $tema, $subtitulo, $di, $df, $piso, $ativo ? 't' : 'f']);
                $id = (int)$st->fetchColumn();
            }
            return $this->json($response, ['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function salvarEquipe(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $id = (int)($in['id'] ?? 0);
        $cid = (int)($in['idcampanha'] ?? 0);
        $nome = trim((string)($in['nome'] ?? ''));
        $idgestor = !empty($in['idgestor']) ? (int)$in['idgestor'] : null;
        $cor = trim((string)($in['cor'] ?? '#2F5D50'));
        if ($cid <= 0 || $nome === '') return $this->json($response, ['error' => 'Dados invalidos'], 400);

        try {
            if ($id > 0) {
                $this->pdo->prepare("UPDATE p365_equipes SET nome=?, idgestor=?, cor=? WHERE id=?")->execute([$nome, $idgestor, $cor, $id]);
            } else {
                $st = $this->pdo->prepare("INSERT INTO p365_equipes (idcampanha, nome, idgestor, cor) VALUES (?, ?, ?, ?) RETURNING id");
                $st->execute([$cid, $nome, $idgestor, $cor]);
                $id = (int)$st->fetchColumn();
            }
            return $this->json($response, ['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function salvarParticipante(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        $idvend = (int)($in['idvendedor'] ?? 0);
        $idequipe = !empty($in['idequipe']) ? (int)$in['idequipe'] : null;
        $nome = trim((string)($in['nome_exibicao'] ?? ''));
        $apelido = trim((string)($in['apelido_tatame'] ?? ''));
        $categoria = strtoupper(trim((string)($in['categoria'] ?? 'REPRESENTANTE')));
        $carteiraBase = max(1, (int)($in['carteira_base'] ?? 60));
        $ativo = !isset($in['ativo']) || (bool)$in['ativo'];

        if ($cid <= 0 || $idvend <= 0 || $nome === '') {
            return $this->json($response, ['error' => 'Campanha, vendedor ERP e nome sao obrigatorios'], 400);
        }

        try {
            $st = $this->pdo->prepare("
                INSERT INTO p365_participantes (idcampanha, idequipe, idvendedor, nome_exibicao, apelido_tatame, categoria, carteira_base, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (idcampanha, idvendedor)
                DO UPDATE SET idequipe=EXCLUDED.idequipe, nome_exibicao=EXCLUDED.nome_exibicao,
                              apelido_tatame=EXCLUDED.apelido_tatame, categoria=EXCLUDED.categoria,
                              carteira_base=EXCLUDED.carteira_base, ativo=EXCLUDED.ativo
                RETURNING id
            ");
            $st->execute([$cid, $idequipe, $idvend, $nome, $apelido ?: $nome, $categoria, $carteiraBase, $ativo ? 't' : 'f']);
            return $this->json($response, ['success' => true, 'id' => (int)$st->fetchColumn()]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function getMetas(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $q = $request->getQueryParams();
        $cid = (int)($q['campanha_id'] ?? 0);
        $ano = (int)($q['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($q['mes'] ?? 1)));

        $st = $this->pdo->prepare("
            SELECT p.id AS idparticipante, p.nome_exibicao, p.carteira_base,
                   COALESCE(m.meta_faturamento, 0)::float AS meta_faturamento,
                   COALESCE(m.carteira_ativa, p.carteira_base)::int AS carteira_ativa,
                   COALESCE(m.observacao, '') AS observacao
            FROM p365_participantes p
            LEFT JOIN p365_metas_round m ON m.idparticipante = p.id AND m.idcampanha = p.idcampanha AND m.ano = ? AND m.mes = ?
            WHERE p.idcampanha = ? AND p.ativo = TRUE
            ORDER BY p.nome_exibicao
        ");
        $st->execute([$ano, $mes, $cid]);
        return $this->json($response, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function salvarMetas(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        $ano = (int)($in['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($in['mes'] ?? 1)));
        $metas = $in['metas'] ?? [];
        $usuario = (string)(($request->getAttribute('user') ?? [])['username'] ?? 'ADMIN');

        if ($cid <= 0 || !is_array($metas)) {
            return $this->json($response, ['error' => 'Dados invalidos'], 400);
        }

        try {
            $this->pdo->beginTransaction();
            $st = $this->pdo->prepare("
                INSERT INTO p365_metas_round (idcampanha, idparticipante, ano, mes, meta_faturamento, carteira_ativa, observacao, atualizado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (idcampanha, idparticipante, ano, mes)
                DO UPDATE SET meta_faturamento = EXCLUDED.meta_faturamento,
                              carteira_ativa = EXCLUDED.carteira_ativa,
                              observacao = EXCLUDED.observacao,
                              atualizado_por = EXCLUDED.atualizado_por,
                              atualizado_em = NOW()
            ");
            foreach ($metas as $m) {
                $pid = (int)($m['idparticipante'] ?? 0);
                if ($pid <= 0) continue;
                $st->execute([
                    $cid,
                    $pid,
                    $ano,
                    $mes,
                    max(0, (float)($m['meta_faturamento'] ?? 0)),
                    max(1, (int)($m['carteira_ativa'] ?? 60)),
                    trim((string)($m['observacao'] ?? '')),
                    $usuario,
                ]);
            }
            $this->pdo->commit();
            return $this->json($response, ['success' => true, 'salvos' => count($metas)]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function getDesafios(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $q = $request->getQueryParams();
        $cid = (int)($q['campanha_id'] ?? 0);
        $ano = (int)($q['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($q['mes'] ?? 1)));

        $st = $this->pdo->prepare("SELECT * FROM p365_desafios_mes WHERE idcampanha = ? AND ano = ? AND mes = ? ORDER BY numero_desafio");
        $st->execute([$cid, $ano, $mes]);
        return $this->json($response, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function salvarDesafio(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        $ano = (int)($in['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($in['mes'] ?? 1)));
        $num = (int)($in['numero_desafio'] ?? 1);
        $golpe = strtoupper(trim((string)($in['codigo_golpe'] ?? ($num === 1 ? 'KIMONO' : ($num === 2 ? 'ARMLOCK' : 'RNC')))));
        $titulo = trim((string)($in['titulo'] ?? "Desafio 0$num ($golpe)"));
        $desc = trim((string)($in['descricao'] ?? ''));
        $regraTipo = strtoupper(trim((string)($in['regra_tipo'] ?? ($num === 3 ? 'REATIVACAO_ABERTURA' : 'PCT_CARTEIRA'))));
        $metaPct = (float)($in['meta_percentual'] ?? ($num === 1 ? 15 : ($num === 2 ? 20 : 25)));
        $pts = (int)($in['pontos'] ?? 10);
        $itens = array_values(array_unique(array_filter(array_map('intval', (array)($in['itens'] ?? [])))));

        if ($cid <= 0 || !in_array($num, [1, 2, 3], true)) {
            return $this->json($response, ['error' => 'Desafio invalido'], 400);
        }

        try {
            $this->pdo->prepare("
                INSERT INTO p365_desafios_mes (idcampanha, ano, mes, numero_desafio, codigo_golpe, titulo, descricao, regra_tipo, meta_percentual, pontos, itens_json, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ON CONFLICT (idcampanha, ano, mes, numero_desafio)
                DO UPDATE SET codigo_golpe=EXCLUDED.codigo_golpe, titulo=EXCLUDED.titulo, descricao=EXCLUDED.descricao,
                              regra_tipo=EXCLUDED.regra_tipo, meta_percentual=EXCLUDED.meta_percentual,
                              pontos=EXCLUDED.pontos, itens_json=EXCLUDED.itens_json, atualizado_em=NOW()
            ")->execute([$cid, $ano, $mes, $num, $golpe, $titulo, $desc, $regraTipo, $metaPct, $pts, json_encode($itens)]);
            return $this->json($response, ['success' => true]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function buscarItens(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $q = trim((string)($request->getQueryParams()['q'] ?? ''));
        if (mb_strlen($q) < 2) return $this->json($response, []);

        $st = $this->pdo->prepare("
            SELECT iditem, referencia, descricao
            FROM item
            WHERE COALESCE(inativo, 'N') = 'N'
              AND (iditem::text = :ex OR referencia = :ex OR descricao ILIKE :lk)
            ORDER BY descricao
            LIMIT 25
        ");
        $st->execute(['ex' => $q, 'lk' => "%$q%"]);
        return $this->json($response, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function lancarExtra(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        $pid = (int)($in['idparticipante'] ?? 0);
        $ano = (int)($in['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($in['mes'] ?? 1)));
        $tipo = strtoupper(trim((string)($in['tipo'] ?? 'EXTRA_5')));
        $pts = (int)($in['pontos'] ?? 5);
        $val = (float)($in['valor_ajuste'] ?? 0);
        $motivo = trim((string)($in['motivo'] ?? ''));
        $usuario = (string)(($request->getAttribute('user') ?? [])['username'] ?? 'ADMIN');

        if ($cid <= 0 || $pid <= 0 || $motivo === '') {
            return $this->json($response, ['error' => 'Participante e motivo sao obrigatorios'], 400);
        }

        $this->pdo->prepare("
            INSERT INTO p365_lancamentos_extras (idcampanha, idparticipante, ano, mes, tipo, pontos, valor_ajuste, motivo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$cid, $pid, $ano, $mes, $tipo, $pts, $val, $motivo, $usuario]);

        return $this->json($response, ['success' => true]);
    }

    public function fecharRound(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        $ano = (int)($in['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($in['mes'] ?? 1)));
        $usuario = (string)(($request->getAttribute('user') ?? [])['username'] ?? 'ADMIN');

        if ($cid <= 0) return $this->json($response, ['error' => 'Campanha obrigatoria'], 400);

        try {
            $res = $this->service->fecharRound($cid, $ano, $mes, $usuario);
            return $this->json($response, ['success' => true, 'fechados' => $res['fechados']]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function reabrirRound(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) return $this->json($response, ['error' => 'Acesso negado'], 403);

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        $ano = (int)($in['ano'] ?? 2027);
        $mes = max(1, min(12, (int)($in['mes'] ?? 1)));

        if ($cid <= 0) return $this->json($response, ['error' => 'Campanha obrigatoria'], 400);

        $this->pdo->prepare("UPDATE p365_round_fechamento SET fechado = FALSE WHERE idcampanha = ? AND ano = ? AND mes = ?")
            ->execute([$cid, $ano, $mes]);
        return $this->json($response, ['success' => true]);
    }

    public function salvarConfigVisibilidade(Request $request, Response $response): Response
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if (!$this->podeAdministrar($scope)) {
            return $this->json($response, ['error' => 'Acesso restrito ao Administrador'], 403);
        }

        $in = json_decode($request->getBody()->getContents(), true) ?? [];
        $cid = (int)($in['idcampanha'] ?? 0);
        if ($cid <= 0) {
            $camp = $this->service->getCampanhaAtiva();
            $cid = $camp ? (int)$camp['id'] : 0;
        }
        if ($cid <= 0) return $this->json($response, ['error' => 'Campanha nao encontrada'], 404);

        $st = $this->pdo->prepare("SELECT config_json FROM p365_campanhas WHERE id = ?");
        $st->execute([$cid]);
        $atual = json_decode((string)($st->fetchColumn() ?: '{}'), true) ?: [];

        $atual['ocultar_ranking_geral'] = !empty($in['ocultar_ranking_geral']);
        $atual['liberar_ranking_gestor'] = !empty($in['liberar_ranking_gestor']);

        $this->pdo->prepare("UPDATE p365_campanhas SET config_json = ?::jsonb, atualizado_em = NOW() WHERE id = ?")
            ->execute([json_encode($atual, JSON_UNESCAPED_UNICODE), $cid]);

        return $this->json($response, ['success' => true, 'config_visibilidade' => $atual]);
    }

    private function lerConfigVisibilidade(array $campanha): array
    {
        $cfg = json_decode((string)($campanha['config_json'] ?? '{}'), true) ?: [];
        return [
            'ocultar_ranking_geral' => !empty($cfg['ocultar_ranking_geral']),
            'liberar_ranking_gestor' => !empty($cfg['liberar_ranking_gestor']),
        ];
    }

    private function podeVerRankingGeral(bool $ehAdmin, bool $ehGestor, array $cfgVis): bool
    {
        if ($ehAdmin) return true;
        if (empty($cfgVis['ocultar_ranking_geral'])) return true;
        if ($ehGestor && !empty($cfgVis['liberar_ranking_gestor'])) return true;
        return false;
    }

    private function montarAutoSoma(array $rows, int $ano, int $mes): array
    {
        $qtd = count($rows);
        $metaFat = 0.0;
        $fatD10 = 0.0;
        $fatD20 = 0.0;
        $fatD30 = 0.0;
        $devMes = 0.0;
        $fatLiq = 0.0;
        $cartAtiva = 0;
        $cartEfetiva = 0;
        $cliPos = 0;
        $somaBonus = 0.0;
        $somaPts = 0;
        $somaExtras = 0;
        $somaPctD1 = 0.0; $somaPctD2 = 0.0; $somaPctD3 = 0.0;
        $somaPtsD1 = 0;   $somaPtsD2 = 0;   $somaPtsD3 = 0;
        $todosFechados = true;

        foreach ($rows as $r) {
            $metaFat += (float)$r['meta_faturamento'];
            $fatD10 += (float)$r['fat_dia10'];
            $fatD20 += (float)$r['fat_dia20'];
            $fatD30 += (float)$r['fat_dia30'];
            $devMes += (float)$r['devolucoes_mes'];
            $fatLiq += (float)$r['fat_liquido'];
            $cartAtiva += (int)$r['carteira_ativa'];
            $cartEfetiva += (int)$r['carteira_efetiva'];
            $cliPos += (int)$r['clientes_positivados'];
            $somaBonus += (float)$r['bonus_mes_rs'];
            $somaPts += (int)$r['pts_total_mes'];
            $somaExtras += (int)$r['pts_extras'];
            $somaPctD1 += (float)$r['pct_desafio1'];
            $somaPctD2 += (float)$r['pct_desafio2'];
            $somaPctD3 += (float)$r['pct_desafio3'];
            $somaPtsD1 += (int)$r['pts_desafio1'];
            $somaPtsD2 += (int)$r['pts_desafio2'];
            $somaPtsD3 += (int)$r['pts_desafio3'];
            if (empty($r['fechado'])) $todosFechados = false;
        }

        $pctD10 = $metaFat > 0 ? round(($fatD10 / $metaFat) * 100, 2) : 0.0;
        $pctD20 = $metaFat > 0 ? round(($fatD20 / $metaFat) * 100, 2) : 0.0;
        $pctD30 = $metaFat > 0 ? round(($fatLiq / $metaFat) * 100, 2) : 0.0;
        $pctPos = $cartEfetiva > 0 ? round(($cliPos / $cartEfetiva) * 100, 2) : 0.0;

        $ptsD10 = Programa365Service::calcularPontosDia10($pctD10);
        $ptsD20 = Programa365Service::calcularPontosDia20($pctD20);
        $ptsD30 = Programa365Service::calcularPontosDia30($pctD30);
        $ptsPos = Programa365Service::calcularPontosPositivacao($pctPos);
        $mediaPtsMes = $qtd > 0 ? (int)round($somaPts / $qtd) : 0;

        return [
            'idparticipante' => 0,
            'idvendedor' => 0,
            'eh_consolidado' => true,
            'qtd_representantes' => $qtd,
            'nome' => "Todos os Representantes ($qtd)",
            'apelido' => "Auto-Soma ($qtd)",
            'equipe' => 'Consolidado do Filtro / Escopo',
            'equipe_cor' => '#2F5D50',
            'categoria' => 'CONSOLIDADO',
            'ano' => $ano,
            'mes' => $mes,
            'fechado' => $todosFechados,
            'meta_faturamento' => round($metaFat, 2),
            'fat_dia10' => round($fatD10, 2),
            'fat_dia20' => round($fatD20, 2),
            'fat_dia30' => round($fatD30, 2),
            'devolucoes_mes' => round($devMes, 2),
            'fat_liquido' => round($fatLiq, 2),
            'pct_dia10' => $pctD10,
            'pct_dia20' => $pctD20,
            'pct_dia30' => $pctD30,
            'pts_dia10' => $ptsD10,
            'pts_dia20' => $ptsD20,
            'pts_dia30' => $ptsD30,
            'carteira_ativa' => $cartAtiva,
            'carteira_efetiva' => $cartEfetiva,
            'clientes_positivados' => $cliPos,
            'pct_positivacao' => $pctPos,
            'pts_positivacao' => $ptsPos,
            'pct_desafio1' => $qtd > 0 ? round($somaPctD1 / $qtd, 2) : 0.0,
            'pts_desafio1' => $qtd > 0 ? (int)round($somaPtsD1 / $qtd) : 0,
            'pct_desafio2' => $qtd > 0 ? round($somaPctD2 / $qtd, 2) : 0.0,
            'pts_desafio2' => $qtd > 0 ? (int)round($somaPtsD2 / $qtd) : 0,
            'pct_desafio3' => $qtd > 0 ? round($somaPctD3 / $qtd, 2) : 0.0,
            'pts_desafio3' => $qtd > 0 ? (int)round($somaPtsD3 / $qtd) : 0,
            'pts_extras' => $somaExtras,
            'pts_total_mes' => $mediaPtsMes,
            'pts_soma_equipe' => $somaPts,
            'bonus_mes_rs' => round($somaBonus, 2),
        ];
    }
}
