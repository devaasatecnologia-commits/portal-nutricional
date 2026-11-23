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
        return $scope->isAdmin || $scope->temAcessoTotal();
    }

    private function resolverParticipantesPermitidos(UserScope $scope, int $idcampanha): ?array
    {
        if ($this->podeAdministrar($scope)) {
            return null; // sem restricao
        }
        $ids = [];
        // Gestor: participantes das equipes onde ele eh gestor
        if (!empty($scope->gestores)) {
            $in = implode(',', array_map('intval', $scope->gestores));
            $st = $this->pdo->prepare("
                SELECT p.id FROM p365_participantes p
                LEFT JOIN p365_equipes e ON e.id = p.idequipe
                WHERE p.idcampanha = ? AND p.ativo = TRUE AND (e.idgestor IN ($in) OR p.idvendedor IN ($in))
            ");
            $st->execute([$idcampanha]);
            $ids = array_merge($ids, $st->fetchAll(PDO::FETCH_COLUMN));
        }
        // Representante ou usuario proprio
        $vends = !empty($scope->representantes) ? $scope->representantes : [$scope->uid];
        $vends = array_values(array_filter(array_map('intval', $vends), fn($v) => $v > 0));
        if (!empty($vends)) {
            $inV = implode(',', $vends);
            $st2 = $this->pdo->prepare("SELECT id FROM p365_participantes WHERE idcampanha = ? AND ativo = TRUE AND idvendedor IN ($inV)");
            $st2->execute([$idcampanha]);
            $ids = array_merge($ids, $st2->fetchAll(PDO::FETCH_COLUMN));
        }
        return array_values(array_unique(array_map('intval', $ids)));
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

            $permitidos = $this->resolverParticipantesPermitidos($scope, $cid);
            $roundRows = $this->service->calcularRound($cid, $ano, $mes, $permitidos);
            $rankingAnual = $this->service->calcularRankingAnual($cid, $ano, $mes);

            // Escolher participante selecionado
            $selecionado = null;
            if ($partId > 0) {
                foreach ($roundRows as $r) {
                    if ((int)$r['idparticipante'] === $partId) {
                        $selecionado = $r;
                        break;
                    }
                }
            }
            if (!$selecionado && !empty($roundRows)) {
                $selecionado = $roundRows[0];
            }

            // Enriquecer selecionado com XP anual e faixa
            $xpAnual = 0;
            $bonusAnual = 0.0;
            $posicaoAnual = null;
            if ($selecionado) {
                foreach ($rankingAnual as $rk) {
                    if ((int)$rk['idparticipante'] === (int)$selecionado['idparticipante']) {
                        $xpAnual = (int)$rk['xp_total'];
                        $bonusAnual = (float)$rk['bonus_acumulado_rs'];
                        $posicaoAnual = (int)$rk['posicao'];
                        break;
                    }
                }
            }

            // Desafios configurados no mes
            $stD = $this->pdo->prepare("SELECT * FROM p365_desafios_mes WHERE idcampanha = ? AND ano = ? AND mes = ? ORDER BY numero_desafio");
            $stD->execute([$cid, $ano, $mes]);
            $desafios = $stD->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'campanha' => $campanha,
                'ano' => $ano,
                'mes' => $mes,
                'is_admin' => $this->podeAdministrar($scope),
                'participantes_disponiveis' => array_map(fn($r) => [
                    'id' => $r['idparticipante'],
                    'nome' => $r['nome'],
                    'equipe' => $r['equipe'],
                    'pts_total_mes' => $r['pts_total_mes'],
                ], $roundRows),
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
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            if (!$campanha) {
                return $this->json($response, ['error' => 'Campanha nao encontrada'], 404);
            }
            $cid = (int)$campanha['id'];
            $ano = (int)($q['ano'] ?? $campanha['temporada_ano']);
            $mes = max(1, min(12, (int)($q['mes'] ?? (int)date('n'))));

            $round = $this->service->calcularRound($cid, $ano, $mes);
            $anual = $this->service->calcularRankingAnual($cid, $ano, $mes);

            return $this->json($response, [
                'campanha' => $campanha,
                'ano' => $ano,
                'mes' => $mes,
                'ranking_round' => $round,
                'ranking_anual' => $anual,
                'premiacao_anual' => Programa365Service::catalogoPremiacaoAnual(),
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function pins(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $campanha = $this->service->getCampanhaAtiva(!empty($q['campanha_id']) ? (int)$q['campanha_id'] : null);
            if (!$campanha) {
                return $this->json($response, ['catalogo' => Programa365Service::catalogoPins(), 'desbloqueados' => []]);
            }
            $cid = (int)$campanha['id'];
            $partId = (int)($q['participante_id'] ?? 0);

            $desbloqueados = [];
            if ($partId > 0) {
                $st = $this->pdo->prepare("SELECT pin_codigo, ano, mes, desbloqueado_em FROM p365_pins_desbloqueados WHERE idcampanha = ? AND idparticipante = ? ORDER BY ano, mes");
                $st->execute([$cid, $partId]);
                $desbloqueados = $st->fetchAll(PDO::FETCH_ASSOC);
            }

            return $this->json($response, [
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
                $stE = $this->pdo->prepare("SELECT e.*, c.nome AS gestor_nome FROM p365_equipes e LEFT JOIN cliforemp c ON c.idcliforemp = e.idgestor WHERE e.idcampanha = ? ORDER BY e.nome");
                $stE->execute([$cid]);
                $equipes = $stE->fetchAll(PDO::FETCH_ASSOC);

                $stP = $this->pdo->prepare("SELECT p.*, e.nome AS equipe_nome FROM p365_participantes p LEFT JOIN p365_equipes e ON e.id = p.idequipe WHERE p.idcampanha = ? ORDER BY p.nome_exibicao");
                $stP->execute([$cid]);
                $participantes = $stP->fetchAll(PDO::FETCH_ASSOC);
            }

            // Vendedores/Representantes ativos no ERP para vincular facilmente
            $vendedoresErp = $this->pdo->query("
                SELECT DISTINCT c.idcliforemp AS idvendedor, c.nome
                FROM cliforemp c
                WHERE c.ehvendedor = 'S' AND COALESCE(c.inativo, 'N') = 'N'
                ORDER BY c.nome
                LIMIT 200
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
}
