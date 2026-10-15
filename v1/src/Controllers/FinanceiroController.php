<?php
namespace Nutricional\Controllers;

use Nutricional\Utils\UserScope;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FinanceiroController
{
    private $pdo;

    private const NIVEIS = ['filial', 'gestor', 'representante'];
    private const NIVEIS_DETALHE = ['filial', 'gestor', 'representante', 'cliente'];
    private const LIMITE_RELATORIO = 5000;
    private const LIMITE_CENARIO = 500;

    /** Títulos renegociados/cancelados (status 5) não entram na taxa de recuperação. */
    private const SEM_STATUS_5 = "NOT EXISTS (SELECT 1 FROM receber rx
        WHERE rx.idcliforemp = vfe.idcliforemp AND rx.idfilial = vfe.idfilial
          AND rx.documento = vfe.documento AND rx.vencimento = vfe.vencimento
          AND rx.excluido = 'N' AND rx.status = 5)";

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    // ======================================================================
    // INFRAESTRUTURA
    // ======================================================================

    private function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function fail(Response $response, \Throwable $e, string $contexto): Response
    {
        if ($e instanceof \InvalidArgumentException) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
        if ($e instanceof \DomainException) {
            return $this->json($response, ['error' => $e->getMessage()], 403);
        }
        if ($e instanceof \OutOfBoundsException) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
        error_log("Financeiro::$contexto - " . $e->getMessage());
        return $this->json($response, ['error' => 'Erro interno ao processar a solicitação.'], 500);
    }

    private function input(Request $request): array
    {
        $dados = json_decode((string)$request->getBody(), true);
        return is_array($dados) ? $dados : [];
    }

    private function inteiroEntre($valor, int $padrao, int $min, int $max, string $campo): int
    {
        if ($valor === null || $valor === '') {
            return $padrao;
        }
        $n = filter_var($valor, FILTER_VALIDATE_INT);
        if ($n === false || $n < $min || $n > $max) {
            throw new \InvalidArgumentException("Parâmetro '$campo' deve ser um inteiro entre $min e $max.");
        }
        return $n;
    }

    private function validarNivel($nivel, array $permitidos): string
    {
        $nivel = is_string($nivel) ? $nivel : 'filial';
        if (!in_array($nivel, $permitidos, true)) {
            throw new \InvalidArgumentException('Nível inválido.');
        }
        return $nivel;
    }

    private function ints(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }

    // ======================================================================
    // ESCOPO / AUTORIZAÇÃO
    // ======================================================================

    private function carregarUsuario(int $uid): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT idcliforemp, idusuario, username, dash_filiais, dash_gestores, permite_ver_usuarios
               FROM usuario WHERE idcliforemp = :uid'
        );
        $st->execute(['uid' => $uid]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Usuários que o solicitante pode usar como "Usuário (Visão)". Fonte única da regra:
     * a lista exibida na tela e a validação do idusuario recebido usam este método.
     *
     * @return array{lista: array<int, array{id:int,nome:string}>, derivada: bool}
     *         derivada = visão por filiais/gestores (regras 3 e 4): o alvo é sempre
     *         interseccionado com o escopo do próprio solicitante.
     */
    private function usuariosPermitidos(array $eu, bool $isAdmin): array
    {
        $uid = (int)$eu['idcliforemp'];
        $filiais = UserScope::lista($eu['dash_filiais'] ?? '');
        $gestores = UserScope::lista($eu['dash_gestores'] ?? '');
        $derivada = false;
        $usuarios = [];

        $vinculados = [];
        if (!$isAdmin && ($eu['permite_ver_usuarios'] ?? 'N') !== 'S') {
            $st = $this->pdo->prepare('SELECT idusuario_visualizar FROM usuario_visualizacao WHERE idusuario = :id');
            $st->execute(['id' => (int)$eu['idusuario']]);
            $vinculados = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
        }

        if ($isAdmin || ($eu['permite_ver_usuarios'] ?? 'N') === 'S') {
            $st = $this->pdo->query(
                "SELECT u.idcliforemp AS id, u.username AS nome FROM usuario u
                  WHERE u.idcliforemp > 0 AND u.inativo = 'N' ORDER BY u.username"
            );
            $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (!empty($vinculados)) {
            $st = $this->pdo->query(
                "SELECT u.idcliforemp AS id, u.username AS nome FROM usuario u
                  WHERE u.idusuario IN (" . $this->ints($vinculados) . ") AND u.idcliforemp > 0 AND u.inativo = 'N'
                  ORDER BY u.username"
            );
            $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (!empty($filiais)) {
            $derivada = true;
            $st = $this->pdo->query(
                "SELECT u.idcliforemp AS id, u.username AS nome FROM usuario u
                  WHERE u.idcliforemp > 0 AND u.inativo = 'N'
                    AND EXISTS (SELECT 1 FROM vw_analise_receber_geral_cliente v
                                 WHERE v.idvendrepre = u.idcliforemp AND v.idfilial IN (" . $this->ints($filiais) . "))
                  ORDER BY u.username"
            );
            $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (!empty($gestores)) {
            $derivada = true;
            $st = $this->pdo->query(
                "SELECT u.idcliforemp AS id, u.username AS nome FROM usuario u
                  WHERE u.idcliforemp > 0 AND u.inativo = 'N'
                    AND EXISTS (SELECT 1 FROM vw_analise_receber_geral_cliente v
                                 WHERE v.idvendrepre = u.idcliforemp AND v.idsupervisor IN (" . $this->ints($gestores) . "))
                  ORDER BY u.username"
            );
            $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC);
        }

        foreach ($usuarios as &$u) {
            $u['id'] = (int)$u['id'];
        }
        unset($u);

        // O próprio usuário sempre está na lista, em primeiro lugar.
        $usuarios = array_values(array_filter($usuarios, fn($u) => $u['id'] !== $uid));
        array_unshift($usuarios, ['id' => $uid, 'nome' => (string)$eu['username']]);

        return ['lista' => $usuarios, 'derivada' => $derivada];
    }

    /**
     * Resolve o usuário-alvo da consulta. A identidade vem do JWT; o idusuario do corpo só
     * é aceito se estiver entre os usuários que o solicitante pode visualizar.
     *
     * @return array{uid:int, row:array, extra:array{filiais:int[],gestores:int[]}}
     */
    private function resolverAlvo(Request $request, array $input): array
    {
        $scope = UserScope::fromRequest($request, $this->pdo);
        if ($scope->uid <= 0) {
            throw new \DomainException('Acesso negado: usuário não identificado.');
        }

        $alvo = (int)($input['idusuario'] ?? 0);
        if ($alvo <= 0) {
            $alvo = $scope->uid;
        }

        $extra = ['filiais' => [], 'gestores' => []];
        if ($alvo !== $scope->uid) {
            $eu = $this->carregarUsuario($scope->uid);
            if (!$eu) {
                throw new \DomainException('Acesso negado: usuário não identificado.');
            }
            $perm = $this->usuariosPermitidos($eu, $scope->isAdmin);
            if (!in_array($alvo, array_column($perm['lista'], 'id'), true)) {
                throw new \DomainException('Acesso negado: você não tem permissão para visualizar este usuário.');
            }
            if ($perm['derivada']) {
                $extra = [
                    'filiais' => UserScope::lista($eu['dash_filiais'] ?? ''),
                    'gestores' => UserScope::lista($eu['dash_gestores'] ?? ''),
                ];
            }
        }

        $row = $this->carregarUsuario($alvo);
        if (!$row) {
            throw new \OutOfBoundsException('Usuário não encontrado.');
        }
        return ['uid' => $alvo, 'row' => $row, 'extra' => $extra];
    }

    private function interseccao(array $proprio, array $extra): array
    {
        if (empty($extra)) {
            return $proprio;
        }
        if (empty($proprio)) {
            return $extra;
        }
        $r = array_values(array_intersect($proprio, $extra));
        return $r ?: [0];
    }

    /**
     * Travas de segurança sobre a view de carteira (varg) e a de eventos (vfe).
     * Sem dash_filiais/dash_gestores o usuário enxerga apenas a própria carteira (representante).
     *
     * @return array{varg:string, vfe:string, filiais:int[], gestores:int[]}
     */
    private function travas(array $alvo): array
    {
        $row = $alvo['row'];
        $proprioF = UserScope::lista($row['dash_filiais'] ?? '');
        $proprioG = UserScope::lista($row['dash_gestores'] ?? '');
        $filiais = $this->interseccao($proprioF, $alvo['extra']['filiais']);
        $gestores = $this->interseccao($proprioG, $alvo['extra']['gestores']);

        $v = [];
        $e = [];
        if (empty($proprioF) && empty($proprioG)) {
            $uid = (int)$row['idcliforemp'];
            $v[] = "varg.idvendrepre = $uid";
            $e[] = "vfe.idrepresentante = $uid";
        }
        if (!empty($filiais)) {
            $v[] = 'varg.idfilial IN (' . $this->ints($filiais) . ')';
            $e[] = 'vfe.idfilial IN (' . $this->ints($filiais) . ')';
        }
        if (!empty($gestores)) {
            $v[] = 'varg.idsupervisor IN (' . $this->ints($gestores) . ')';
            $e[] = 'vfe.idgestor IN (' . $this->ints($gestores) . ')';
        }

        return ['varg' => implode(' AND ', $v), 'vfe' => implode(' AND ', $e), 'filiais' => $filiais, 'gestores' => $gestores];
    }

    /** @return array{0: array, 1: int} [lista de filiais disponíveis, filial padrão (só quando há uma)] */
    private function filiaisDisponiveis(array $alvo, array $tr): array
    {
        if (!empty($tr['filiais'])) {
            $st = $this->pdo->query(
                "SELECT DISTINCT idfilial, nome FROM filial
                  WHERE inativo = 'N' AND idfilial IN (" . $this->ints($tr['filiais']) . ') ORDER BY idfilial'
            );
        } else {
            $uid = (int)$alvo['uid'];
            $st = $this->pdo->query(
                "SELECT DISTINCT f.idfilial, f.nome FROM filial f
                   JOIN vw_analise_receber_geral_cliente v ON v.idfilial = f.idfilial
                  WHERE v.idvendrepre = $uid ORDER BY f.idfilial"
            );
        }
        $lista = $st->fetchAll(\PDO::FETCH_ASSOC);
        return [$lista, count($lista) === 1 ? (int)$lista[0]['idfilial'] : 0];
    }

    /**
     * Filial efetiva da consulta. No nível "filial" o filtro_id é a própria filial; nos demais
     * níveis a filial selecionada na tela (idfilial) continua restringindo cards, taxa e tabela.
     */
    private function filialEfetiva(string $nivel, int $fid, int $idfilial, array $lista, int $padrao): int
    {
        $sel = ($nivel === 'filial' && $fid > 0) ? $fid : $idfilial;
        if ($sel <= 0) {
            $sel = $padrao;
        }
        if ($sel > 0 && !in_array($sel, array_map('intval', array_column($lista, 'idfilial')), true)) {
            throw new \DomainException('Acesso negado: filial fora do seu escopo.');
        }
        return $sel;
    }

    /** @return array{0:string,1:string} condições [varg, vfe] do nível/filial selecionados */
    private function filtroNivel(string $nivel, int $fid, int $filial): array
    {
        $v = [];
        $e = [];
        if ($filial > 0) {
            $v[] = "varg.idfilial = $filial";
            $e[] = "vfe.idfilial = $filial";
        }
        if ($nivel !== 'filial' && $fid <= 0) {
            throw new \InvalidArgumentException("Parâmetro 'filtro_id' é obrigatório para o nível informado.");
        }
        switch ($nivel) {
            case 'gestor':
                $v[] = "varg.idsupervisor = $fid";
                $e[] = "vfe.idgestor = $fid";
                break;
            case 'representante':
                $v[] = "varg.idvendrepre = $fid";
                $e[] = "vfe.idrepresentante = $fid";
                break;
            case 'cliente':
                $v[] = "varg.idcliforemp = $fid";
                $e[] = "vfe.idcliforemp = $fid";
                break;
        }
        return [$v ? implode(' AND ', $v) : '1=1', $e ? implode(' AND ', $e) : '1=1'];
    }

    /**
     * Regra única da taxa de recuperação (dashboard, cenários e detalhes):
     *  - janela: títulos vencidos nos últimos N dias (dias_recup), sem status 5 (renegociado/cancelado);
     *  - saldo "aberto" = > 0,01; "quitado" = entre -0,01 e 0,01 (saldo negativo não entra);
     *  - cenário 1: sem apontamento, aberto e com atraso >= corte_atraso;
     *  - cenário 2: com apontamento e ainda aberto;
     *  - cenário 3 (recuperado): com apontamento, quitado, e o apontamento é anterior ou igual
     *    à data da quitação (vencimento + dias_atraso, pois dias_atraso = data_ult_cobranca - vencimento nos quitados).
     */
    private function regrasRecup(int $dias, int $corte): array
    {
        return [
            1 => "vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= $corte AND vfe.valorsaldo > 0.01",
            2 => "vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0.01",
            3 => "vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo BETWEEN -0.01 AND 0.01
                  AND (vfe.dias_atraso IS NULL OR vfe.ultimo_evento <= vfe.vencimento + vfe.dias_atraso)",
            'janela' => "vfe.vencimento >= (CURRENT_DATE - INTERVAL '$dias days') AND " . self::SEM_STATUS_5,
        ];
    }

    // ======================================================================
    // ENDPOINTS
    // ======================================================================

    /**
     * POST /v1/financeiro/dashboard
     */
    public function getDashboard(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $nivel = $this->validarNivel($input['nivel'] ?? 'filial', self::NIVEIS);
            $diasRecup = $this->inteiroEntre($input['dias_recup'] ?? null, 120, 1, 365, 'dias_recup');
            $corte = $this->inteiroEntre($input['corte_atraso'] ?? null, 8, 1, 365, 'corte_atraso');
            $fid = (int)($input['filtro_id'] ?? 0);
            $idfilial = (int)($input['idfilial'] ?? 0);

            $alvo = $this->resolverAlvo($request, $input);
            $tr = $this->travas($alvo);
            [$listaFiliais, $filialPadrao] = $this->filiaisDisponiveis($alvo, $tr);
            $filial = $this->filialEfetiva($nivel, $fid, $idfilial, $listaFiliais, $filialPadrao);
            [$fVarg, $fVfe] = $this->filtroNivel($nivel, $fid, $filial);

            // Tabela: o nível seguinte ao selecionado
            switch ($nivel) {
                case 'filial':
                    $select = 'varg.idsupervisor AS id, MAX(varg.nomegestor) AS nome';
                    $groupBy = 'varg.idsupervisor';
                    $drill = 'varg.idsupervisor IS NOT NULL';
                    $cards = 'varg.idsupervisor IS NOT NULL';
                    break;
                case 'gestor':
                    $select = 'varg.idvendrepre AS id, MAX(varg.nomerepresentante) AS nome';
                    $groupBy = 'varg.idvendrepre';
                    $drill = 'varg.idvendrepre IS NOT NULL';
                    $cards = 'varg.idvendrepre IS NOT NULL';
                    break;
                default: // representante -> clientes com vencidos
                    $select = 'varg.idcliforemp AS id, MAX(varg.cliente) AS nome';
                    $groupBy = 'varg.idcliforemp';
                    $drill = 'varg.vencidos > 0';
                    $cards = '1=1';
                    break;
            }

            $sql = "SELECT $select,
                           COALESCE(SUM(varg.vencidos), 0)::float AS vencidos,
                           COALESCE(SUM(varg.total_receber), 0)::float AS total_receber,
                           COALESCE(SUM(COALESCE(varg.dias_60, 0) + COALESCE(varg.mais_60_dias, 0)), 0)::float AS valor_iap
                      FROM vw_analise_receber_geral_cliente varg
                     WHERE ({$tr['varg']}) AND ($fVarg) AND ($drill)
                     GROUP BY $groupBy ORDER BY vencidos DESC";
            $tabela = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($tabela as &$r) {
                $r['iag'] = $r['total_receber'] > 0 ? round(($r['vencidos'] * 100) / $r['total_receber'], 2) : 0;
                if ($r['iag'] > 10) {
                    $r['performance'] = 'CRÍTICO';
                } elseif ($r['iag'] >= 5) {
                    $r['performance'] = 'ATENÇÃO';
                } else {
                    $r['performance'] = 'SAUDÁVEL';
                }
            }
            unset($r);

            // Cards da carteira. Faixas: d30 = vencido 1-30d, d60 = 31-60d, d90 = >60d.
            $sqlSum = "SELECT COALESCE(SUM(varg.total_receber), 0)::float AS total,
                              COALESCE(SUM(varg.vencidos), 0)::float AS vencidos,
                              COALESCE(SUM(varg.a_vencer), 0)::float AS a_vencer,
                              COALESCE(SUM(COALESCE(varg.dias_60, 0) + COALESCE(varg.mais_60_dias, 0)), 0)::float AS valor_iap,
                              COALESCE(SUM(varg.dias_30), 0)::float AS d30,
                              COALESCE(SUM(varg.dias_60), 0)::float AS d60,
                              COALESCE(SUM(varg.mais_60_dias), 0)::float AS d90
                         FROM vw_analise_receber_geral_cliente varg
                        WHERE ({$tr['varg']}) AND ($fVarg) AND ($cards)";
            $resumo = $this->pdo->query($sqlSum)->fetch(\PDO::FETCH_ASSOC);

            // Taxa de recuperação
            $rg = $this->regrasRecup($diasRecup, $corte);
            $sqlRec = "SELECT
                    COALESCE(SUM(CASE WHEN {$rg[1]} THEN 1 ELSE 0 END), 0) AS c1,
                    COALESCE(SUM(CASE WHEN {$rg[2]} THEN 1 ELSE 0 END), 0) AS c2,
                    COALESCE(SUM(CASE WHEN {$rg[3]} THEN 1 ELSE 0 END), 0) AS c3
                  FROM vw_financeiro_eventos_geral vfe
                 WHERE ({$tr['vfe']}) AND ($fVfe) AND {$rg['janela']}";
            $rec = $this->pdo->query($sqlRec)->fetch(\PDO::FETCH_ASSOC);
            $c1 = (int)$rec['c1'];
            $c2 = (int)$rec['c2'];
            $c3 = (int)$rec['c3'];
            $base = $c1 + $c2 + $c3;

            return $this->json($response, [
                'config' => [
                    'usuario' => $alvo['row']['username'] ?? 'USUÁRIO',
                    'filiais' => $listaFiliais,
                    'filial_padrao' => $filialPadrao,
                    'filial_efetiva' => $filial,
                ],
                'resumo_filial' => $resumo,
                'tabela' => $tabela,
                'taxa_recup' => $base > 0 ? round(($c3 * 100) / $base, 2) : 0,
                'recup_detalhe' => [
                    'pagos' => $c3,
                    'total' => $base,
                    'cenario_1' => $c1,
                    'cenario_2' => $c2,
                    'cenario_3' => $c3,
                    'dias_recup' => $diasRecup,
                    'corte_atraso' => $corte,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail($response, $e, 'getDashboard');
        }
    }

    /**
     * POST /v1/financeiro/historico-kpi
     * Série do snapshot kpi_financeiro_historico (consolidado por filial).
     */
    public function getHistoricoKpi(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $tipo = $input['tipo'] ?? 'iag_calculado';
            $fid = (int)($input['filtro_id'] ?? 0);
            $dia = (int)($input['dia_semana'] ?? date('w'));
            if ($dia < 0 || $dia > 6) {
                $dia = (int)date('w');
            }
            if (!in_array($tipo, ['iag_calculado', 'iap_calculado', 'taxa_recuperacao'], true)) {
                $tipo = 'iag_calculado';
            }

            $alvo = $this->resolverAlvo($request, $input);
            $permitidas = $this->interseccao(
                UserScope::lista($alvo['row']['dash_filiais'] ?? ''),
                $alvo['extra']['filiais']
            );

            $whereFilial = '';
            if ($fid > 0) {
                if (!empty($permitidas) && !in_array($fid, $permitidas, true)) {
                    $whereFilial = ' AND 1 = 0';
                } else {
                    $whereFilial = ' AND idfilial = ' . $fid;
                }
            } elseif (!empty($permitidas)) {
                $whereFilial = ' AND idfilial IN (' . $this->ints($permitidas) . ')';
            }

            $calculoValor = match ($tipo) {
                'iap_calculado' => 'ROUND(SUM(COALESCE(valor_iap, 0)) * 100.0 / NULLIF(SUM(COALESCE(total_receber, 0)), 0), 2)',
                'taxa_recuperacao' => 'ROUND(SUM(COALESCE(qtd_recuperados, 0)) * 100.0 / NULLIF(SUM(COALESCE(qtd_trabalhados, 0)), 0), 2)',
                default => 'ROUND(SUM(COALESCE(vencidos, 0)) * 100.0 / NULLIF(SUM(COALESCE(total_receber, 0)), 0), 2)',
            };

            $sql = "SELECT * FROM (
                        SELECT data_registro,
                               to_char(data_registro, 'DD/MM') AS data,
                               COALESCE($calculoValor, 0)::float AS valor,
                               SUM(COALESCE(vencidos, 0))::float AS abs_iag,
                               SUM(COALESCE(valor_iap, 0))::float AS abs_iap,
                               SUM(COALESCE(total_receber, 0))::float AS abs_total,
                               SUM(COALESCE(qtd_trabalhados, 0))::int AS abs_recup_total,
                               SUM(COALESCE(qtd_recuperados, 0))::int AS abs_recup_pagos,
                               STRING_AGG(DISTINCT idfilial::text, ', ') AS filiais,
                               COUNT(*)::int AS registros_origem
                          FROM kpi_financeiro_historico
                         WHERE idusuario = :uid AND nivel_visao = 'filial'$whereFilial
                           AND EXTRACT(DOW FROM data_registro) = :dia
                         GROUP BY data_registro
                         ORDER BY data_registro DESC
                         LIMIT 10
                    ) historico
                    ORDER BY data_registro ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['uid' => $alvo['uid'], 'dia' => $dia]);

            return $this->json($response, $stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            return $this->fail($response, $e, 'getHistoricoKpi');
        }
    }

    /**
     * POST /v1/financeiro/lista-usuarios
     * Usuários que o solicitante (JWT) pode usar como visão.
     */
    public function getListaUsuariosHistorico(Request $request, Response $response): Response
    {
        try {
            $scope = UserScope::fromRequest($request, $this->pdo);
            $eu = $scope->uid > 0 ? $this->carregarUsuario($scope->uid) : null;
            if (!$eu) {
                throw new \DomainException('Acesso negado: usuário não identificado.');
            }
            $perm = $this->usuariosPermitidos($eu, $scope->isAdmin);
            return $this->json($response, $perm['lista']);
        } catch (\Throwable $e) {
            return $this->fail($response, $e, 'getListaUsuariosHistorico');
        }
    }

    /**
     * POST /v1/financeiro/detalhes-kpi
     */
    public function getDetalhesAnaliseKpi(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $tipo = (string)($input['tipo'] ?? '');
            $nivel = $this->validarNivel($input['nivel'] ?? 'filial', self::NIVEIS_DETALHE);
            $fid = (int)($input['filtro_id'] ?? 0);
            $idfilial = (int)($input['idfilial'] ?? 0);
            $cenario = (int)($input['cenario'] ?? 0);
            $diasRecup = $this->inteiroEntre($input['dias_recup'] ?? null, 120, 1, 365, 'dias_recup');
            $corte = $this->inteiroEntre($input['corte_atraso'] ?? null, 8, 1, 365, 'corte_atraso');
            if (!in_array($tipo, ['iag', 'iap', 'recup', 'titulos_cliente'], true)) {
                throw new \InvalidArgumentException('Tipo de auditoria inválido.');
            }
            if ($cenario < 0 || $cenario > 3) {
                throw new \InvalidArgumentException('Cenário inválido.');
            }

            $alvo = $this->resolverAlvo($request, $input);
            $tr = $this->travas($alvo);
            [$listaFiliais, $filialPadrao] = $this->filiaisDisponiveis($alvo, $tr);
            $filial = $this->filialEfetiva($nivel, $fid, $idfilial, $listaFiliais, $filialPadrao);

            if ($tipo === 'titulos_cliente') {
                [, $fVfe] = $this->filtroNivel('cliente', $fid, $filial);
                $sql = "SELECT vfe.documento,
                               vfe.valorsaldo::float AS valor,
                               to_char(vfe.vencimento, 'DD/MM/YYYY') AS data_vencimento,
                               vfe.dias_atraso,
                               vfe.evento AS ultimo_evento,
                               to_char(vfe.ultimo_evento, 'DD/MM/YYYY') AS data_evento,
                               vfe.usuariocriador AS responsavel,
                               vfe.descricao AS evento_registrado
                          FROM vw_financeiro_eventos_geral vfe
                         WHERE ($fVfe) AND vfe.valorsaldo > 0.01 AND ({$tr['vfe']})
                         ORDER BY vfe.vencimento ASC, vfe.ultimo_evento DESC NULLS LAST
                         LIMIT 20";
                return $this->json($response, $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC));
            }

            [$fVarg, $fVfe] = $this->filtroNivel($nivel, $fid, $filial);

            if ($tipo === 'iag' || $tipo === 'iap') {
                $campo = $tipo === 'iag'
                    ? 'COALESCE(varg.vencidos, 0)'
                    : '(COALESCE(varg.dias_60, 0) + COALESCE(varg.mais_60_dias, 0))';
                $sql = "SELECT MAX(varg.cliente) AS label, SUM($campo)::float AS valor
                          FROM vw_analise_receber_geral_cliente varg
                         WHERE ({$tr['varg']}) AND ($fVarg) AND $campo > 0
                         GROUP BY varg.idcliforemp ORDER BY valor DESC LIMIT 10";
                $rows = array_map(fn($i) => [
                    'label' => $i['label'],
                    'valor' => (float)$i['valor'],
                    'is_qtd' => false,
                ], $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC));
                return $this->json($response, $rows);
            }

            // Recuperação
            $rg = $this->regrasRecup($diasRecup, $corte);

            if ($cenario > 0) {
                $campoValor = $cenario === 3 ? 'vfe.totalrecebido' : 'vfe.valorsaldo';
                $sql = "SELECT vfe.nomefantasia AS cliente,
                               vfe.documento,
                               $campoValor::float AS valorsaldo,
                               to_char(vfe.vencimento, 'DD/MM/YYYY') AS data_vencimento,
                               to_char(vfe.ultimo_evento, 'DD/MM/YYYY') AS data_evento,
                               vfe.evento AS desc_evento,
                               vfe.dias_atraso,
                               COUNT(*) OVER() AS total_cenario
                          FROM vw_financeiro_eventos_geral vfe
                         WHERE ({$tr['vfe']}) AND ($fVfe) AND {$rg['janela']} AND ({$rg[$cenario]})
                         ORDER BY $campoValor DESC, vfe.idcliforemp, vfe.documento
                         LIMIT " . self::LIMITE_CENARIO;
                return $this->json($response, $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC));
            }

            $sql = "SELECT
                    COALESCE(SUM(CASE WHEN {$rg[1]} THEN 1 ELSE 0 END), 0) AS c1,
                    COALESCE(SUM(CASE WHEN {$rg[2]} THEN 1 ELSE 0 END), 0) AS c2,
                    COALESCE(SUM(CASE WHEN {$rg[3]} THEN 1 ELSE 0 END), 0) AS c3
                  FROM vw_financeiro_eventos_geral vfe
                 WHERE ({$tr['vfe']}) AND ($fVfe) AND {$rg['janela']}";
            $row = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);

            return $this->json($response, [
                ['label' => "1) Sem apontamento e com atraso de $corte dias ou mais (a cobrar)", 'valor' => (int)$row['c1'], 'is_qtd' => true, 'cenario_id' => 1],
                ['label' => '2) Com apontamento, ainda não pago', 'valor' => (int)$row['c2'], 'is_qtd' => true, 'cenario_id' => 2],
                ['label' => '3) Com apontamento, pago (recuperados)', 'valor' => (int)$row['c3'], 'is_qtd' => true, 'cenario_id' => 3],
            ]);
        } catch (\Throwable $e) {
            return $this->fail($response, $e, 'getDetalhesAnaliseKpi');
        }
    }

    /**
     * POST /v1/financeiro/relatorio-detalhado
     * Títulos em aberto (saldo > 0,01) para o PDF. Usa a mesma view dos demais endpoints.
     */
    public function getRelatorioDetalhado(Request $request, Response $response): Response
    {
        try {
            $input = $this->input($request);
            $nivel = $this->validarNivel($input['nivel'] ?? 'filial', self::NIVEIS_DETALHE);
            $fid = (int)($input['filtro_id'] ?? 0);
            $idfilial = (int)($input['idfilial'] ?? 0);

            $alvo = $this->resolverAlvo($request, $input);
            $tr = $this->travas($alvo);
            [$listaFiliais, $filialPadrao] = $this->filiaisDisponiveis($alvo, $tr);
            $filial = $this->filialEfetiva($nivel, $nivel === 'filial' ? 0 : $fid, $idfilial, $listaFiliais, $filialPadrao);
            if ($nivel === 'filial') {
                $fid = 0;
            }
            [, $fVfe] = $this->filtroNivel($nivel, $fid, $filial);

            $sql = "SELECT
                        vfe.idfilial,
                        COALESCE(vfe.idtipoevento, 0) AS idtipoevento,
                        vfe.dataemissao,
                        vfe.metodopagto,
                        vfe.idcliforemp,
                        vfe.nomefantasia,
                        vfe.documento,
                        vfe.vencimento,
                        vfe.valor,
                        vfe.totalrecebido,
                        vfe.valorsaldo,
                        vfe.dias_atraso,
                        vfe.nome_vendedor,
                        COALESCE(CAST(vfe.ult_evento_dias AS TEXT), 'Sem Registro') AS ult_evento_dias,
                        COALESCE(TO_CHAR(vfe.ultimo_evento, 'DD/MM/YYYY'), 'Sem Data') AS ultimo_evento,
                        COALESCE(vfe.descricao, 'Sem Registro') AS descricao,
                        COALESCE(vfe.usuario, 'Sem Registro') AS usuario,
                        COALESCE(vfe.usuariocriador, 'Sem Registro') AS usuariocriador,
                        COALESCE(vfe.evento, 'Sem Registro') AS evento,
                        cfe.fantasia AS gestor,
                        SUM(vfe.valor) OVER() AS total_geral_valor,
                        SUM(vfe.totalrecebido) OVER() AS total_geral_recebido,
                        SUM(vfe.valorsaldo) OVER() AS total_geral_saldo,
                        COUNT(*) OVER() AS total_linhas
                    FROM vw_financeiro_eventos_geral vfe
                    LEFT JOIN cliforemp cfe ON cfe.idcliforemp = vfe.idgestor
                   WHERE ({$tr['vfe']}) AND ($fVfe) AND vfe.valorsaldo > 0.01
                   ORDER BY cfe.fantasia, vfe.nome_vendedor, vfe.idcliforemp,
                            vfe.ult_evento_dias NULLS LAST, vfe.dataemissao, vfe.vencimento, vfe.evento
                   LIMIT " . self::LIMITE_RELATORIO;

            return $this->json($response, $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            return $this->fail($response, $e, 'getRelatorioDetalhado');
        }
    }
}
