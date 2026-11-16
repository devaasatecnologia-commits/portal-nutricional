<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\MercosFlexService;
use Nutricional\Services\MercosIndisponivelException;
use Nutricional\Services\MercosRecusouException;
use PDO;

/**
 * Controller de Movimentação Direta/Manual de Saldo Flex no Mercos
 * com suporte à cobertura automática via Gestor da filial.
 */
class FlexMovimentacaoController
{
    private const FILIAIS_ADMIN = [1, 6];

    public const GESTORES_FILIAL = [
        1 => [
            'id' => 12335,
            'nome' => 'TIAGO (MOV)',
            'cargo' => 'Gestor Matriz (Filial 1)'
        ],
        6 => [
            'id' => 15816,
            'nome' => 'TALES FERNANDO DE JESUS BINDE',
            'cargo' => 'Gestor Filial 6 (PR)'
        ]
    ];

    private $pdo;
    /** @var array<int,MercosFlexService> */
    private array $flexInstances = [];

    public function __construct()
    {
        $this->pdo = \getPDO();
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

    private function usuario(Request $request): array
    {
        $user = $request->getAttribute('user') ?? [];
        $idusuario = (int)($user['idusuario'] ?? 0);
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);

        $stmt = $this->pdo->prepare('SELECT username, dash_filiais, dash_gestores FROM usuario WHERE idusuario = :id');
        $stmt->execute(['id' => $idusuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $ints = static fn (string $csv): array => array_values(array_unique(array_filter(
            array_map('intval', explode(',', $csv)),
            static fn (int $f): bool => $f > 0
        )));
        $filiais = $ints((string)($row['dash_filiais'] ?? ''));
        if ($filiais === [] && $isAdmin) {
            $filiais = self::FILIAIS_ADMIN;
        }

        return [
            'idusuario' => $idusuario,
            'username' => strtoupper((string)($row['username'] ?? ($user['username'] ?? 'SISTEMA'))),
            'filiais' => $filiais,
            'gestores' => $isAdmin ? [] : $ints((string)($row['dash_gestores'] ?? '')),
            'is_admin' => $isAdmin,
            'permissoes' => (array)($user['permissoes'] ?? []),
        ];
    }

    private function flex(int $filial): MercosFlexService
    {
        if (!isset($this->flexInstances[$filial])) {
            $this->flexInstances[$filial] = new MercosFlexService($filial);
        }
        return $this->flexInstances[$filial];
    }

    /**
     * Espelha os saldos lidos da API Mercos na tabela local public.saldo_flex.
     *
     * @param array<int,array> $saldosMercos
     */
    private function sincronizarSaldoFlexLocal(int $filial, array $saldosMercos): void
    {
        if (empty($saldosMercos)) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO public.saldo_flex (idfilial, idvendrepre, saldo)
                VALUES (:idfilial, :idvendrepre, :saldo)
                ON CONFLICT (idfilial, idvendrepre)
                DO UPDATE SET saldo = EXCLUDED.saldo
            ');
            foreach ($saldosMercos as $colabId => $item) {
                if ((int)$colabId <= 0 || !empty($item['excluido'])) {
                    continue;
                }
                $stmt->execute([
                    'idfilial' => $filial,
                    'idvendrepre' => (int)$colabId,
                    'saldo' => round((float)($item['saldo_atual'] ?? 0.0), 2),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[flex-movimentacao] Falha ao sincronizar saldo_flex: ' . $e->getMessage());
        }
    }

    /**
     * Retorna mapa [idcolaborador => saldo] live do Mercos com cache e fallback local.
     *
     * @return array<int,float>
     */
    private function obterMapaSaldosLive(int $filial, bool $forcar = false): array
    {
        $saldosMercos = [];
        try {
            $saldosMercos = $this->flex($filial)->saldos($forcar, true);
            $this->sincronizarSaldoFlexLocal($filial, $saldosMercos);
        } catch (\Throwable $e) {
            error_log(sprintf('[flex-movimentacao] Fallback saldo_flex f%d: %s', $filial, $e->getMessage()));
        }

        $mapa = [];
        if (!empty($saldosMercos)) {
            foreach ($saldosMercos as $id => $item) {
                if (empty($item['excluido'])) {
                    $mapa[(int)$id] = (float)($item['saldo_atual'] ?? 0.0);
                }
            }
            return $mapa;
        }

        try {
            $st = $this->pdo->prepare('SELECT idvendrepre, saldo FROM public.saldo_flex WHERE idfilial = :filial');
            $st->execute(['filial' => $filial]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $mapa[(int)$r['idvendrepre']] = (float)$r['saldo'];
            }
        } catch (\Throwable $e) {}

        return $mapa;
    }

    /** @return array<int,array{id:int,nome:string}> */
    private function gestoresFiltroDaFilial(int $filial, array $usuario): array
    {
        $sql = "
            SELECT DISTINCT cdgestor AS id, UPPER(TRIM(gestor)) AS nome
            FROM usuarios_mercos
            WHERE filial = :filial
              AND cdgestor IS NOT NULL
              AND cdgestor > 0
              AND TRIM(COALESCE(gestor, '')) <> ''
        ";
        if (!empty($usuario['gestores'])) {
            $sql .= ' AND cdgestor IN (' . implode(',', array_map('intval', $usuario['gestores'])) . ')';
        }
        $sql .= ' ORDER BY nome ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute(['filial' => $filial]);
        $lista = [];
        $vistos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int)$r['id'];
            $vistos[$id] = true;
            $lista[] = ['id' => $id, 'nome' => trim((string)$r['nome'])];
        }

        $gConfig = self::GESTORES_FILIAL[$filial] ?? null;
        if ($gConfig && empty($usuario['gestores']) && !isset($vistos[(int)$gConfig['id']])) {
            $lista[] = ['id' => (int)$gConfig['id'], 'nome' => strtoupper($gConfig['nome'])];
        }
        usort($lista, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        return $lista;
    }

    /**
     * Retorna contexto do usuário, filiais liberadas e saldo dos gestores
     */
    public function contexto(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        $filiaisLiberadas = array_values(array_intersect($u['filiais'], self::FILIAIS_ADMIN));
        if (empty($filiaisLiberadas)) {
            $filiaisLiberadas = self::FILIAIS_ADMIN;
        }

        $gestoresInfo = [];
        foreach ($filiaisLiberadas as $f) {
            $gConfig = self::GESTORES_FILIAL[$f] ?? null;
            if ($gConfig) {
                $saldoGestor = null;
                try {
                    $saldoGestor = $this->flex($f)->saldoDe($gConfig['id'], true);
                } catch (\Throwable $e) {
                    error_log(sprintf('[flex-movimentacao] Erro ao ler saldo gestor f%d: %s', $f, $e->getMessage()));
                }

                $gestoresInfo[$f] = [
                    'id' => $gConfig['id'],
                    'nome' => $gConfig['nome'],
                    'cargo' => $gConfig['cargo'],
                    'filial' => $f,
                    'saldo' => $saldoGestor ?? 0.0,
                    'online' => $saldoGestor !== null
                ];
            }
        }

        return $this->json($response, [
            'success' => true,
            'usuario' => $u,
            'filiais_disponiveis' => $filiaisLiberadas,
            'gestores' => $gestoresInfo
        ]);
    }

    /**
     * Lista representantes da filial com saldos em tempo real e gestores consolidados
     */
    public function listarRepresentantes(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        $params = $request->getQueryParams();
        $filial = (int)($params['filial'] ?? 1);
        $forcar = !empty($params['forcar']);

        if (!in_array($filial, self::FILIAIS_ADMIN, true)) {
            return $this->erro($response, 'Filial inválida');
        }

        // 1. Busca representantes ativos no ERP vinculados à filial
        $sql = 'SELECT DISTINCT ON (um.idcolaborador) 
                    um.idcolaborador, um.username, um.nome, um.filial, um.cdgestor, um.gestor,
                    c.razao, c.fantasia
                FROM usuarios_mercos um
                INNER JOIN cliforemp c ON c.idcliforemp = um.idcolaborador
                WHERE um.idcolaborador IS NOT NULL 
                  AND um.filial = :filial
                  AND COALESCE(c.inativo, \'N\') <> \'S\'';
        if ($u['gestores'] !== []) {
            $sql .= ' AND um.cdgestor IN (' . implode(',', array_map('intval', $u['gestores'])) . ')';
        }
        $sql .= ' ORDER BY um.idcolaborador, um.cdgestor DESC NULLS LAST';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['filial' => $filial]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Consulta API Mercos (reutilizando cache local/anti-burst) e espelha em saldo_flex
        $api = $this->flex($filial);
        $saldosMercos = [];
        try {
            $saldosMercos = $api->saldos($forcar, true);
            $this->sincronizarSaldoFlexLocal($filial, $saldosMercos);
        } catch (\Throwable $e) {
            error_log(sprintf('[flex-movimentacao] Falha ao consultar saldos mercos f%d: %s', $filial, $e->getMessage()));
        }

        $saldosFallback = [];
        if (empty($saldosMercos)) {
            try {
                $stFb = $this->pdo->prepare('SELECT idvendrepre, saldo FROM public.saldo_flex WHERE idfilial = :filial');
                $stFb->execute(['filial' => $filial]);
                foreach ($stFb->fetchAll(PDO::FETCH_ASSOC) as $rf) {
                    $saldosFallback[(int)$rf['idvendrepre']] = (float)$rf['saldo'];
                }
            } catch (\Throwable $e) {}
        }

        // 3. Monta lista filtrada e agrega gestores comerciais da equipe
        $lista = [];
        $gestoresEquipeMap = [];
        foreach ($rows as $r) {
            $id = (int)$r['idcolaborador'];

            if (!empty($saldosMercos)) {
                if (!isset($saldosMercos[$id])) {
                    continue; // Sem conta flex na API Mercos
                }
                $itemMercos = $saldosMercos[$id];
                if (!empty($itemMercos['excluido'])) {
                    continue; // Excluído na API
                }
                $saldo = (float)($itemMercos['saldo_atual'] ?? 0.0);
            } else {
                $saldo = (float)($saldosFallback[$id] ?? 0.0);
            }

            $nomeExibicao = trim((string)($r['username'] ?: ($r['fantasia'] ?: $r['razao'])));
            $cdGestor = (int)$r['cdgestor'];
            $nomeGestorRep = trim((string)$r['gestor']);

            $lista[] = [
                'id' => $id,
                'nome' => $nomeExibicao,
                'razao' => trim((string)$r['razao']),
                'fantasia' => trim((string)$r['fantasia']),
                'filial' => (int)$r['filial'],
                'cdgestor' => $cdGestor,
                'gestor' => $nomeGestorRep,
                'saldo' => $saldo,
            ];

            if ($cdGestor > 0 && $nomeGestorRep !== '') {
                if (!isset($gestoresEquipeMap[$cdGestor])) {
                    $gestoresEquipeMap[$cdGestor] = [
                        'cdgestor' => $cdGestor,
                        'nome' => $nomeGestorRep,
                        'qtd_representantes' => 0,
                        'saldo_equipe' => 0.0,
                    ];
                }
                $gestoresEquipeMap[$cdGestor]['qtd_representantes']++;
                $gestoresEquipeMap[$cdGestor]['saldo_equipe'] = round($gestoresEquipeMap[$cdGestor]['saldo_equipe'] + $saldo, 2);
            }
        }

        usort($lista, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        $gestoresEquipe = array_values($gestoresEquipeMap);
        usort($gestoresEquipe, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        // Dados do gestor de cobertura da filial (extraído do mesmo mapa Mercos sem segunda chamada HTTP)
        $gestorConfig = self::GESTORES_FILIAL[$filial] ?? null;
        $saldoGestor = 0.0;
        if ($gestorConfig) {
            $gId = (int)$gestorConfig['id'];
            if (!empty($saldosMercos) && isset($saldosMercos[$gId])) {
                $saldoGestor = (float)($saldosMercos[$gId]['saldo_atual'] ?? 0.0);
            } else {
                $saldoGestor = (float)($saldosFallback[$gId] ?? 0.0);
            }
        }

        return $this->json($response, [
            'success' => true,
            'filial' => $filial,
            'representantes' => $lista,
            'gestores_equipe' => $gestoresEquipe,
            'meta_cache' => $api->ultimoStatusCache(),
            'gestor_filial' => $gestorConfig ? [
                'id' => $gestorConfig['id'],
                'nome' => $gestorConfig['nome'],
                'cargo' => $gestorConfig['cargo'],
                'saldo' => $saldoGestor
            ] : null
        ]);
    }

    /**
     * Consulta saldo atual em tempo real de um representante específico e do gestor da filial
     */
    public function saldo(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filial = (int)($params['filial'] ?? 1);
        $colabId = (int)($params['colaborador_id'] ?? 0);

        if ($colabId <= 0 || !in_array($filial, self::FILIAIS_ADMIN, true)) {
            return $this->erro($response, 'Parâmetros inválidos');
        }

        try {
            $api = $this->flex($filial);
            $saldoRep = $api->saldoDe($colabId, true);
            if ($saldoRep === null) {
                return $this->erro($response, 'Representante não encontrado na API Mercos.');
            }

            $gConfig = self::GESTORES_FILIAL[$filial] ?? null;
            $saldoGestor = null;
            if ($gConfig) {
                $saldoGestor = $api->saldoDe($gConfig['id'], true);
            }

            return $this->json($response, [
                'success' => true,
                'colaborador_id' => $colabId,
                'filial' => $filial,
                'saldo_representante' => (float)$saldoRep,
                'gestor' => $gConfig ? [
                    'id' => $gConfig['id'],
                    'nome' => $gConfig['nome'],
                    'saldo' => (float)($saldoGestor ?? 0.0)
                ] : null
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao consultar saldo no Mercos: ' . $e->getMessage(), 502);
        }
    }

    /**
     * Retorna o histórico auditado unificado (Manuais, Transferências, Bonificações NF e Flex Mínimo)
     * com filtro por Gestor, Representante, Natureza, Rotina de Origem, Período e cruzamento com Saldo Mercos Live.
     */
    public function historico(Request $request, Response $response): Response
    {
        try {
            $u = $this->usuario($request);
            $params = $request->getQueryParams();

            $filial = isset($params['filial']) && $params['filial'] !== ''
                ? (int)$params['filial']
                : (!empty($u['filiais']) ? (int)$u['filiais'][0] : 1);
            if (!in_array($filial, self::FILIAIS_ADMIN, true)) {
                $filial = 1;
            }
            if (!$u['is_admin'] && !empty($u['filiais']) && !in_array($filial, $u['filiais'], true)) {
                return $this->erro($response, 'Filial não liberada para o seu usuário.', 403);
            }

            $cdGestorFiltro = (int)($params['cdgestor'] ?? 0);
            $colabIdFiltro = (int)($params['colaborador_id'] ?? 0);
            $tipoFiltro = strtoupper(trim((string)($params['tipo'] ?? 'TODOS')));
            $origemFiltro = strtoupper(trim((string)($params['origem'] ?? 'TODAS')));
            $apenasGestor = !empty($params['apenas_gestor']) && $params['apenas_gestor'] !== '0';
            $busca = trim((string)($params['busca'] ?? ''));
            $limite = min(500, max(10, (int)($params['limite'] ?? 150)));
            $forcarSync = !empty($params['forcar']);

            $parseData = static fn ($val, string $padrao): string =>
                preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$val) ? (string)$val : $padrao;
            $dataInicio = $parseData($params['data_inicio'] ?? '', date('Y-m-d', strtotime('-60 days')));
            $dataFim = $parseData($params['data_fim'] ?? '', date('Y-m-d'));
            if ($dataFim < $dataInicio) {
                [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
            }

            $inicioTs = $dataInicio . ' 00:00:00';
            $fimTs = $dataFim . ' 23:59:59';

            // Restrição de acesso por carteiras de gestores quando não for admin
            $filtroColabsRestritos = [];
            if (!$u['is_admin'] && !empty($u['gestores'])) {
                $gestoresCsv = implode(',', array_map('intval', $u['gestores']));
                $stRest = $this->pdo->prepare("
                    SELECT DISTINCT idcolaborador
                    FROM usuarios_mercos
                    WHERE filial = :filial
                      AND (cdgestor IN ({$gestoresCsv}) OR idcolaborador IN ({$gestoresCsv}))
                ");
                $stRest->execute(['filial' => $filial]);
                $filtroColabsRestritos = array_map('intval', $stRest->fetchAll(PDO::FETCH_COLUMN));
                foreach ($u['gestores'] as $gid) {
                    $filtroColabsRestritos[] = (int)$gid;
                }
                $filtroColabsRestritos = array_values(array_unique(array_filter($filtroColabsRestritos)));
                if ($colabIdFiltro > 0 && !in_array($colabIdFiltro, $filtroColabsRestritos, true)) {
                    return $this->erro($response, 'Sem permissão para visualizar o histórico deste colaborador.', 403);
                }
            }

            $cteSql = "
            WITH carteira_gestor AS (
                SELECT DISTINCT ON (idcolaborador)
                    idcolaborador,
                    cdgestor,
                    UPPER(TRIM(gestor)) AS gestor_nome
                FROM usuarios_mercos
                WHERE filial = :f_cg
                ORDER BY idcolaborador, cdgestor DESC NULLS LAST
            ),
            extrato_bruto AS (
                -- 1. Movimentações Manuais do Portal
                SELECT
                    m.id::bigint AS id,
                    m.data_movimentacao AS datahora,
                    'MANUAL_PORTAL'::text AS origem,
                    'Manual (Portal)'::text AS origem_rotulo,
                    COALESCE(NULLIF(TRIM(m.usuario), ''), 'PORTAL')::text AS usuario,
                    m.filial::integer AS filial,
                    m.idcolaborador::integer AS idcolaborador,
                    COALESCE(NULLIF(TRIM(m.nome_colaborador), ''), 'Colab ' || m.idcolaborador)::text AS nome_colaborador,
                    UPPER(COALESCE(m.tipo_movimentacao, 'DEBITO'))::text AS tipo_movimentacao,
                    ABS(COALESCE(m.valor_solicitado, 0))::numeric(14,2) AS valor_solicitado,
                    COALESCE(m.observacao, '')::text AS observacao,
                    m.saldo_anterior_rep::numeric(14,2) AS saldo_anterior_rep,
                    m.saldo_novo_rep::numeric(14,2) AS saldo_novo_rep,
                    COALESCE(m.usou_gestor, false)::boolean AS usou_gestor,
                    m.idgestor::integer AS idgestor,
                    m.nome_gestor::text AS nome_gestor,
                    COALESCE(m.valor_gestor, 0)::numeric(14,2) AS valor_gestor,
                    m.saldo_anterior_gestor::numeric(14,2) AS saldo_anterior_gestor,
                    m.saldo_novo_gestor::numeric(14,2) AS saldo_novo_gestor,
                    COALESCE(m.sucesso, true)::boolean AS sucesso,
                    COALESCE(m.mensagem, '')::text AS mensagem,
                    NULL::text AS documento,
                    m.detalhes_json::text AS detalhes_json
                FROM movimentacoes_flex_mercos_log m
                WHERE m.filial = :f_m
                  AND m.data_movimentacao >= :i_ts_m
                  AND m.data_movimentacao <= :f_ts_m

                UNION ALL

                -- 2A. Transferências - Saída (Débito em quem enviou)
                SELECT
                    t.id::bigint AS id,
                    t.data_transferencia AS datahora,
                    'TRANSFERENCIA'::text AS origem,
                    'Transferência Enviada'::text AS origem_rotulo,
                    COALESCE(NULLIF(TRIM(t.usuario), ''), 'PORTAL')::text AS usuario,
                    t.filial::integer AS filial,
                    t.colaborador_de::integer AS idcolaborador,
                    COALESCE(NULLIF(TRIM(t.nome_de), ''), 'Colab ' || t.colaborador_de)::text AS nome_colaborador,
                    'DEBITO'::text AS tipo_movimentacao,
                    ABS(COALESCE(t.valor, 0))::numeric(14,2) AS valor_solicitado,
                    ('Enviado para ' || COALESCE(t.nome_para, 'Colab ' || t.colaborador_para) || ' — ' || COALESCE(t.motivo, 'Sem motivo'))::text AS observacao,
                    t.valor_saldo_anterior_de::numeric(14,2) AS saldo_anterior_rep,
                    t.valor_novo_saldo_de::numeric(14,2) AS saldo_novo_rep,
                    false AS usou_gestor,
                    t.colaborador_para::integer AS idgestor,
                    t.nome_para::text AS nome_gestor,
                    0::numeric(14,2) AS valor_gestor,
                    NULL::numeric(14,2) AS saldo_anterior_gestor,
                    NULL::numeric(14,2) AS saldo_novo_gestor,
                    true AS sucesso,
                    COALESCE(t.mensagem, 'Transferência realizada com sucesso')::text AS mensagem,
                    NULL::text AS documento,
                    json_build_object(
                        'rotina', 'TRANSFERENCIA_SAIDA',
                        'contraparte_id', t.colaborador_para,
                        'contraparte_nome', t.nome_para,
                        'saldo_anterior', t.valor_saldo_anterior_de,
                        'saldo_novo', t.valor_novo_saldo_de
                    )::text AS detalhes_json
                FROM transferencias_flex_mercos_log t
                WHERE t.filial = :f_ts
                  AND COALESCE(t.sucesso, true) = true
                  AND t.data_transferencia >= :i_ts_ts
                  AND t.data_transferencia <= :f_ts_ts

                UNION ALL

                -- 2B. Transferências - Entrada (Crédito em quem recebeu)
                SELECT
                    t.id::bigint AS id,
                    t.data_transferencia AS datahora,
                    'TRANSFERENCIA'::text AS origem,
                    'Transferência Recebida'::text AS origem_rotulo,
                    COALESCE(NULLIF(TRIM(t.usuario), ''), 'PORTAL')::text AS usuario,
                    t.filial::integer AS filial,
                    t.colaborador_para::integer AS idcolaborador,
                    COALESCE(NULLIF(TRIM(t.nome_para), ''), 'Colab ' || t.colaborador_para)::text AS nome_colaborador,
                    'CREDITO'::text AS tipo_movimentacao,
                    ABS(COALESCE(t.valor, 0))::numeric(14,2) AS valor_solicitado,
                    ('Recebido de ' || COALESCE(t.nome_de, 'Colab ' || t.colaborador_de) || ' — ' || COALESCE(t.motivo, 'Sem motivo'))::text AS observacao,
                    t.valor_saldo_anterior_para::numeric(14,2) AS saldo_anterior_rep,
                    t.valor_novo_saldo_para::numeric(14,2) AS saldo_novo_rep,
                    false AS usou_gestor,
                    t.colaborador_de::integer AS idgestor,
                    t.nome_de::text AS nome_gestor,
                    0::numeric(14,2) AS valor_gestor,
                    NULL::numeric(14,2) AS saldo_anterior_gestor,
                    NULL::numeric(14,2) AS saldo_novo_gestor,
                    true AS sucesso,
                    COALESCE(t.mensagem, 'Transferência recebida com sucesso')::text AS mensagem,
                    NULL::text AS documento,
                    json_build_object(
                        'rotina', 'TRANSFERENCIA_ENTRADA',
                        'contraparte_id', t.colaborador_de,
                        'contraparte_nome', t.nome_de,
                        'saldo_anterior', t.valor_saldo_anterior_para,
                        'saldo_novo', t.valor_novo_saldo_para
                    )::text AS detalhes_json
                FROM transferencias_flex_mercos_log t
                WHERE t.filial = :f_te
                  AND COALESCE(t.sucesso, true) = true
                  AND t.data_transferencia >= :i_ts_te
                  AND t.data_transferencia <= :f_ts_te

                UNION ALL

                -- 3A. Bonificações NF - Débito no Representante
                SELECT
                    b.id::bigint AS id,
                    b.data_criacao AS datahora,
                    'BONIFICACAO_CRON'::text AS origem,
                    'Bonificação (NF)'::text AS origem_rotulo,
                    'CRON SISTEMA'::text AS usuario,
                    b.idfilial::integer AS filial,
                    b.idvendrepre::integer AS idcolaborador,
                    COALESCE(NULLIF(TRIM(v.fantasia), ''), NULLIF(TRIM(v.razao), ''), 'Rep ' || b.idvendrepre)::text AS nome_colaborador,
                    'DEBITO'::text AS tipo_movimentacao,
                    ABS(COALESCE(b.valor, 0))::numeric(14,2) AS valor_solicitado,
                    COALESCE(b.observacao, 'Bonificação Nota ' || COALESCE(b.numero_nota, ''))::text AS observacao,
                    NULL::numeric(14,2) AS saldo_anterior_rep,
                    NULL::numeric(14,2) AS saldo_novo_rep,
                    (b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0)::boolean AS usou_gestor,
                    CASE WHEN b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0 THEN b.codgestor ELSE NULL END::integer AS idgestor,
                    CASE WHEN b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0 THEN COALESCE(NULLIF(TRIM(g.fantasia), ''), NULLIF(TRIM(g.razao), ''), 'Gestor ' || b.codgestor) ELSE NULL END::text AS nome_gestor,
                    ABS(COALESCE(b.valor_tirado, 0))::numeric(14,2) AS valor_gestor,
                    NULL::numeric(14,2) AS saldo_anterior_gestor,
                    NULL::numeric(14,2) AS saldo_novo_gestor,
                    true AS sucesso,
                    'Débito automático de bonificação faturada'::text AS mensagem,
                    b.numero_nota::text AS documento,
                    json_build_object(
                        'rotina', 'BONIFICACAO_CRON',
                        'numero_nota', b.numero_nota,
                        'codgestor', b.codgestor,
                        'valor_tirado_gestor', b.valor_tirado
                    )::text AS detalhes_json
                FROM log_flex_bonificacao b
                LEFT JOIN cliforemp v ON v.idcliforemp = b.idvendrepre
                LEFT JOIN cliforemp g ON g.idcliforemp = b.codgestor
                WHERE b.idfilial = :f_b1
                  AND b.data_criacao >= :i_ts_b1
                  AND b.data_criacao <= :f_ts_b1

                UNION ALL

                -- 3B. Bonificações NF - Cobertura do Gestor
                SELECT
                    b.id::bigint AS id,
                    b.data_criacao AS datahora,
                    'BONIFICACAO_GESTOR'::text AS origem,
                    'Cobertura Bonificação'::text AS origem_rotulo,
                    'CRON SISTEMA'::text AS usuario,
                    b.idfilial::integer AS filial,
                    b.codgestor::integer AS idcolaborador,
                    COALESCE(NULLIF(TRIM(g.fantasia), ''), NULLIF(TRIM(g.razao), ''), 'Gestor ' || b.codgestor)::text AS nome_colaborador,
                    'DEBITO'::text AS tipo_movimentacao,
                    ABS(COALESCE(b.valor_tirado, 0))::numeric(14,2) AS valor_solicitado,
                    ('Cobertura rep ' || COALESCE(NULLIF(TRIM(v.fantasia), ''), NULLIF(TRIM(v.razao), ''), '' || b.idvendrepre) || ' — NF ' || COALESCE(b.numero_nota, '') || ' | ' || COALESCE(b.observacao, ''))::text AS observacao,
                    NULL::numeric(14,2) AS saldo_anterior_rep,
                    NULL::numeric(14,2) AS saldo_novo_rep,
                    true AS usou_gestor,
                    b.codgestor::integer AS idgestor,
                    COALESCE(NULLIF(TRIM(g.fantasia), ''), NULLIF(TRIM(g.razao), ''), 'Gestor ' || b.codgestor)::text AS nome_gestor,
                    ABS(COALESCE(b.valor_tirado, 0))::numeric(14,2) AS valor_gestor,
                    NULL::numeric(14,2) AS saldo_anterior_gestor,
                    NULL::numeric(14,2) AS saldo_novo_gestor,
                    true AS sucesso,
                    'Cobertura automática de bonificação pelo Gestor'::text AS mensagem,
                    b.numero_nota::text AS documento,
                    json_build_object(
                        'rotina', 'BONIFICACAO_GESTOR',
                        'numero_nota', b.numero_nota,
                        'idvendrepre', b.idvendrepre,
                        'representante', COALESCE(v.fantasia, v.razao)
                    )::text AS detalhes_json
                FROM log_flex_bonificacao b
                LEFT JOIN cliforemp v ON v.idcliforemp = b.idvendrepre
                LEFT JOIN cliforemp g ON g.idcliforemp = b.codgestor
                WHERE b.idfilial = :f_b2
                  AND b.codgestor IS NOT NULL AND b.codgestor > 0
                  AND b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0
                  AND b.data_criacao >= :i_ts_b2
                  AND b.data_criacao <= :f_ts_b2

                UNION ALL

                -- 4. Aportes Flex Mínimo ao Gestor
                SELECT
                    a.id::bigint AS id,
                    (a.data::text || ' 00:00:00')::timestamp AS datahora,
                    'FLEX_MINIMO'::text AS origem,
                    'Aporte / Flex Mínimo'::text AS origem_rotulo,
                    'MOTOR FLEX MÍNIMO'::text AS usuario,
                    a.idfilial::integer AS filial,
                    a.cdgestor::integer AS idcolaborador,
                    COALESCE(NULLIF(TRIM(g.fantasia), ''), NULLIF(TRIM(g.razao), ''), 'Gestor ' || a.cdgestor)::text AS nome_colaborador,
                    'CREDITO'::text AS tipo_movimentacao,
                    ABS(COALESCE(a.valor_emprestimo, 0))::numeric(14,2) AS valor_solicitado,
                    COALESCE(a.motivo, 'Aporte Flex Mínimo')::text AS observacao,
                    NULL::numeric(14,2) AS saldo_anterior_rep,
                    NULL::numeric(14,2) AS saldo_novo_rep,
                    false AS usou_gestor,
                    a.cdgestor::integer AS idgestor,
                    COALESCE(NULLIF(TRIM(g.fantasia), ''), NULLIF(TRIM(g.razao), ''), 'Gestor ' || a.cdgestor)::text AS nome_gestor,
                    0::numeric(14,2) AS valor_gestor,
                    NULL::numeric(14,2) AS saldo_anterior_gestor,
                    NULL::numeric(14,2) AS saldo_novo_gestor,
                    true AS sucesso,
                    'Aporte automático Flex Mínimo ao Gestor'::text AS mensagem,
                    NULL::text AS documento,
                    json_build_object(
                        'rotina', 'FLEX_MINIMO',
                        'cdgestor', a.cdgestor,
                        'filial', a.idfilial,
                        'data', a.data
                    )::text AS detalhes_json
                FROM log_debito_flex_gestor a
                LEFT JOIN cliforemp g ON g.idcliforemp = a.cdgestor
                WHERE a.idfilial = :f_fm
                  AND a.data >= :i_d_fm::date
                  AND a.data <= :f_d_fm::date
            ),
            extrato_auditado AS (
                SELECT
                    e.*,
                    to_char(e.datahora, 'DD/MM/YYYY HH24:MI:SS') AS data_formatada,
                    cg.cdgestor AS cdgestor_carteira,
                    COALESCE(cg.gestor_nome, e.nome_gestor) AS nome_gestor_carteira
                FROM extrato_bruto e
                LEFT JOIN carteira_gestor cg ON cg.idcolaborador = e.idcolaborador
            )";

            $binds = [
                'f_cg' => $filial,
                'f_m' => $filial, 'i_ts_m' => $inicioTs, 'f_ts_m' => $fimTs,
                'f_ts' => $filial, 'i_ts_ts' => $inicioTs, 'f_ts_ts' => $fimTs,
                'f_te' => $filial, 'i_ts_te' => $inicioTs, 'f_ts_te' => $fimTs,
                'f_b1' => $filial, 'i_ts_b1' => $inicioTs, 'f_ts_b1' => $fimTs,
                'f_b2' => $filial, 'i_ts_b2' => $inicioTs, 'f_ts_b2' => $fimTs,
                'f_fm' => $filial, 'i_d_fm' => $dataInicio, 'f_d_fm' => $dataFim,
            ];

            $whereClauses = ['1=1'];
            if (!empty($filtroColabsRestritos)) {
                $whereClauses[] = 'idcolaborador IN (' . implode(',', $filtroColabsRestritos) . ')';
            }
            if ($colabIdFiltro > 0) {
                $whereClauses[] = '(idcolaborador = :colab_f1 OR idgestor = :colab_f2)';
                $binds['colab_f1'] = $colabIdFiltro;
                $binds['colab_f2'] = $colabIdFiltro;
            }
            if ($cdGestorFiltro > 0) {
                $whereClauses[] = '(cdgestor_carteira = :gestor_f1 OR idgestor = :gestor_f2 OR idcolaborador = :gestor_f3)';
                $binds['gestor_f1'] = $cdGestorFiltro;
                $binds['gestor_f2'] = $cdGestorFiltro;
                $binds['gestor_f3'] = $cdGestorFiltro;
            }
            if (in_array($tipoFiltro, ['CREDITO', 'DEBITO'], true)) {
                $whereClauses[] = 'tipo_movimentacao = :tipo_f';
                $binds['tipo_f'] = $tipoFiltro;
            }
            if ($origemFiltro === 'MANUAL_PORTAL' || $origemFiltro === 'MANUAL') {
                $whereClauses[] = "origem = 'MANUAL_PORTAL'";
            } elseif ($origemFiltro === 'TRANSFERENCIA') {
                $whereClauses[] = "origem = 'TRANSFERENCIA'";
            } elseif ($origemFiltro === 'BONIFICACAO') {
                $whereClauses[] = "origem IN ('BONIFICACAO_CRON', 'BONIFICACAO_GESTOR')";
            } elseif ($origemFiltro === 'FLEX_MINIMO') {
                $whereClauses[] = "origem = 'FLEX_MINIMO'";
            }
            if ($apenasGestor) {
                $whereClauses[] = 'usou_gestor = true';
            }
            if ($busca !== '') {
                $whereClauses[] = '(nome_colaborador ILIKE :q1 OR usuario ILIKE :q2 OR observacao ILIKE :q3 OR COALESCE(documento, \'\') ILIKE :q4 OR COALESCE(nome_gestor_carteira, \'\') ILIKE :q5 OR COALESCE(nome_gestor, \'\') ILIKE :q6)';
                $like = '%' . $busca . '%';
                for ($qi = 1; $qi <= 6; $qi++) {
                    $binds['q' . $qi] = $like;
                }
            }

            $whereSql = implode(' AND ', $whereClauses);

            // 1. Totais auditados do período/filtros
            $sqlResumo = "$cteSql
                SELECT
                    COALESCE(SUM(CASE WHEN tipo_movimentacao = 'CREDITO' THEN valor_solicitado ELSE 0 END), 0) AS total_creditos,
                    COUNT(CASE WHEN tipo_movimentacao = 'CREDITO' THEN 1 END) AS qtd_creditos,
                    COALESCE(SUM(CASE WHEN tipo_movimentacao = 'DEBITO' THEN valor_solicitado ELSE 0 END), 0) AS total_debitos,
                    COUNT(CASE WHEN tipo_movimentacao = 'DEBITO' THEN 1 END) AS qtd_debitos,
                    COALESCE(SUM(CASE WHEN usou_gestor THEN valor_gestor ELSE 0 END), 0) AS total_coberto_gestor,
                    COUNT(CASE WHEN usou_gestor THEN 1 END) AS qtd_cobertura_gestor,
                    COUNT(*) AS total_registros
                FROM extrato_auditado
                WHERE {$whereSql}";
            $stRes = $this->pdo->prepare($sqlResumo);
            $stRes->execute($binds);
            $resumoRaw = $stRes->fetch(PDO::FETCH_ASSOC) ?: [];

            $totCred = (float)($resumoRaw['total_creditos'] ?? 0);
            $totDeb = (float)($resumoRaw['total_debitos'] ?? 0);
            $resumoAuditoria = [
                'total_creditos' => round($totCred, 2),
                'qtd_creditos' => (int)($resumoRaw['qtd_creditos'] ?? 0),
                'total_debitos' => round($totDeb, 2),
                'qtd_debitos' => (int)($resumoRaw['qtd_debitos'] ?? 0),
                'saldo_liquido' => round($totCred - $totDeb, 2),
                'total_coberto_gestor' => round((float)($resumoRaw['total_coberto_gestor'] ?? 0), 2),
                'qtd_cobertura_gestor' => (int)($resumoRaw['qtd_cobertura_gestor'] ?? 0),
                'total_registros' => (int)($resumoRaw['total_registros'] ?? 0),
            ];

            // 2. Registros detalhados
            $sqlLista = "$cteSql
                SELECT *
                FROM extrato_auditado
                WHERE {$whereSql}
                ORDER BY datahora DESC, id DESC
                LIMIT :limite";
            $stmt = $this->pdo->prepare($sqlLista);
            foreach ($binds as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Cruza com Saldo Mercos Live (via cache/API ou fallback local)
            $saldosLive = $this->obterMapaSaldosLive($filial, $forcarSync);
            $gConfig = self::GESTORES_FILIAL[$filial] ?? null;
            $saldoGestorLive = $gConfig ? ($saldosLive[(int)$gConfig['id']] ?? null) : null;

            foreach ($rows as &$r) {
                $idCol = (int)$r['idcolaborador'];
                $r['id'] = (int)$r['id'];
                $r['filial'] = (int)$r['filial'];
                $r['idcolaborador'] = $idCol;
                $r['valor_solicitado'] = (float)$r['valor_solicitado'];
                $r['saldo_anterior_rep'] = $r['saldo_anterior_rep'] !== null ? (float)$r['saldo_anterior_rep'] : null;
                $r['saldo_novo_rep'] = $r['saldo_novo_rep'] !== null ? (float)$r['saldo_novo_rep'] : null;
                $r['usou_gestor'] = (bool)$r['usou_gestor'];
                $r['idgestor'] = $r['idgestor'] !== null ? (int)$r['idgestor'] : null;
                $r['cdgestor_carteira'] = $r['cdgestor_carteira'] !== null ? (int)$r['cdgestor_carteira'] : null;
                $r['valor_gestor'] = (float)$r['valor_gestor'];
                $r['saldo_anterior_gestor'] = $r['saldo_anterior_gestor'] !== null ? (float)$r['saldo_anterior_gestor'] : null;
                $r['saldo_novo_gestor'] = $r['saldo_novo_gestor'] !== null ? (float)$r['saldo_novo_gestor'] : null;
                $r['sucesso'] = (bool)$r['sucesso'];
                $r['saldo_mercos_atual'] = isset($saldosLive[$idCol]) ? round((float)$saldosLive[$idCol], 2) : null;
            }
            unset($r);

            return $this->json($response, [
                'success' => true,
                'filial' => $filial,
                'filtros' => [
                    'cdgestor' => $cdGestorFiltro,
                    'colaborador_id' => $colabIdFiltro,
                    'tipo' => $tipoFiltro,
                    'origem' => $origemFiltro,
                    'apenas_gestor' => $apenasGestor,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'busca' => $busca,
                    'limite' => $limite,
                ],
                'resumo_auditoria' => $resumoAuditoria,
                'gestores_disponiveis' => $this->gestoresFiltroDaFilial($filial, $u),
                'saldo_gestor_live' => $saldoGestorLive,
                'meta_cache' => $this->flex($filial)->ultimoStatusCache(),
                'historico' => $rows,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar histórico auditado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Executa a movimentação manual no Mercos com suporte à cobertura de Gestor
     */
    public function movimentar(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        if (!$u['is_admin'] && !in_array('movimentacao-flex', $u['permissoes'], true)) {
            return $this->erro($response, 'Seu perfil de usuário não possui permissão para executar movimentações manuais de verba flex.', 403);
        }
        $body = json_decode($request->getBody()->getContents(), true) ?? [];

        $filial = (int)($body['filial'] ?? 0);
        $colabId = (int)($body['colaborador_id'] ?? 0);
        $tipo = strtoupper(trim((string)($body['tipo'] ?? '')));
        $valor = round((float)($body['valor'] ?? 0), 2);
        $observacao = trim((string)($body['observacao'] ?? ''));
        $tirarSaldoGestor = !empty($body['tirar_saldo_gestor']);

        // Validações básicas
        if (!in_array($filial, self::FILIAIS_ADMIN, true)) {
            return $this->erro($response, 'Filial inválida. Escolha Filial 1 (SC) ou Filial 6 (PR).');
        }
        if ($colabId <= 0) {
            return $this->erro($response, 'Representante não informado.');
        }
        if (!in_array($tipo, ['CREDITO', 'DEBITO'], true)) {
            return $this->erro($response, 'Tipo de movimentação inválido. Escolha Adicionar ou Remover.');
        }
        if ($valor <= 0.009) {
            return $this->erro($response, 'Informe um valor válido maior que zero.');
        }
        if ($observacao === '' || mb_strlen($observacao) < 3) {
            return $this->erro($response, 'O campo Observação é OBRIGATÓRIO (mínimo de 3 caracteres).');
        }

        // Buscar dados do representante e gestor da carteira
        $stmtRep = $this->pdo->prepare('
            SELECT um.idcolaborador, um.username, um.cdgestor, um.gestor, c.razao, c.fantasia 
            FROM usuarios_mercos um 
            LEFT JOIN cliforemp c ON c.idcliforemp = um.idcolaborador 
            WHERE um.idcolaborador = :id AND um.filial = :filial LIMIT 1
        ');
        $stmtRep->execute(['id' => $colabId, 'filial' => $filial]);
        $repInfo = $stmtRep->fetch(PDO::FETCH_ASSOC);
        $nomeRep = $repInfo ? trim((string)($repInfo['username'] ?: ($repInfo['fantasia'] ?: $repInfo['razao']))) : "Colaborador {$colabId}";
        $gestorCarteira = [
            'cdgestor' => isset($repInfo['cdgestor']) ? (int)$repInfo['cdgestor'] : null,
            'nome' => trim((string)($repInfo['gestor'] ?? '')),
        ];

        $api = $this->flex($filial);

        // 1. Ler saldo atual do representante
        try {
            $saldoAntRep = $api->saldoDe($colabId, true);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Não foi possível ler o saldo do representante na API Mercos: ' . $e->getMessage(), 502);
        }
        if ($saldoAntRep === null) {
            return $this->erro($response, 'Representante sem cadastro de saldo Flex na API Mercos.');
        }

        $etapasExecutadas = [];
        $usouGestor = false;
        $idGestor = null;
        $nomeGestor = null;
        $valorGestor = 0.00;
        $saldoAntGestor = null;
        $saldoNovoGestor = null;

        // =====================================================================
        // CENÁRIO A: ADICIONAR SALDO (CRÉDITO DIRETO)
        // =====================================================================
        if ($tipo === 'CREDITO') {
            try {
                $api->movimentar($colabId, $valor, $observacao);
                $saldoNovoRep = $api->saldoDe($colabId, true) ?? ($saldoAntRep + $valor);

                $etapasExecutadas[] = [
                    'acao' => 'CREDITO_DIRETO',
                    'destino' => $nomeRep,
                    'valor' => $valor,
                    'observacao' => $observacao,
                    'saldo_anterior' => $saldoAntRep,
                    'saldo_novo' => $saldoNovoRep
                ];

                $this->gravarLog([
                    'usuario' => $u['username'],
                    'filial' => $filial,
                    'idcolaborador' => $colabId,
                    'nome_colaborador' => $nomeRep,
                    'tipo_movimentacao' => 'CREDITO',
                    'valor_solicitado' => $valor,
                    'observacao' => $observacao,
                    'saldo_anterior_rep' => $saldoAntRep,
                    'saldo_novo_rep' => $saldoNovoRep,
                    'usou_gestor' => false,
                    'sucesso' => true,
                    'mensagem' => 'Crédito aplicado com sucesso no Mercos.',
                    'detalhes_json' => json_encode(['gestor_carteira' => $gestorCarteira, 'etapas' => $etapasExecutadas])
                ]);

                return $this->json($response, [
                    'success' => true,
                    'message' => 'Saldo adicionado com sucesso!',
                    'saldo_anterior' => $saldoAntRep,
                    'saldo_novo' => $saldoNovoRep,
                    'etapas' => $etapasExecutadas
                ]);
            } catch (\Throwable $e) {
                return $this->erro($response, 'Falha ao creditar saldo na API Mercos: ' . $e->getMessage(), 500);
            }
        }

        // =====================================================================
        // CENÁRIO B: REMOVER SALDO (DÉBITO)
        // =====================================================================
        // Verifica se o representante tem saldo suficiente
        $temSaldoSuficiente = ($saldoAntRep >= $valor);

        if (!$temSaldoSuficiente && !$tirarSaldoGestor) {
            $faltante = round($valor - max(0, $saldoAntRep), 2);
            $msg = sprintf(
                'Saldo insuficiente no representante (Saldo atual: R$ %s). O valor solicitado para remoção é R$ %s. Para retirar a diferença de R$ %s do Gestor da filial, marque a opção "Tirar saldo do gestor".',
                number_format($saldoAntRep, 2, ',', '.'),
                number_format($valor, 2, ',', '.'),
                number_format($faltante, 2, ',', '.')
            );
            return $this->erro($response, $msg, 400);
        }

        // Subcenário B1: Representante tem saldo suficiente (Sem necessidade de Gestor)
        if ($temSaldoSuficiente) {
            try {
                $api->movimentar($colabId, -$valor, $observacao);
                $saldoNovoRep = $api->saldoDe($colabId, true) ?? ($saldoAntRep - $valor);

                $etapasExecutadas[] = [
                    'acao' => 'DEBITO_DIRETO',
                    'destino' => $nomeRep,
                    'valor' => -$valor,
                    'observacao' => $observacao,
                    'saldo_anterior' => $saldoAntRep,
                    'saldo_novo' => $saldoNovoRep
                ];

                $this->gravarLog([
                    'usuario' => $u['username'],
                    'filial' => $filial,
                    'idcolaborador' => $colabId,
                    'nome_colaborador' => $nomeRep,
                    'tipo_movimentacao' => 'DEBITO',
                    'valor_solicitado' => $valor,
                    'observacao' => $observacao,
                    'saldo_anterior_rep' => $saldoAntRep,
                    'saldo_novo_rep' => $saldoNovoRep,
                    'usou_gestor' => false,
                    'sucesso' => true,
                    'mensagem' => 'Remoção de saldo concluída com sucesso no Mercos.',
                    'detalhes_json' => json_encode(['gestor_carteira' => $gestorCarteira, 'etapas' => $etapasExecutadas])
                ]);

                return $this->json($response, [
                    'success' => true,
                    'message' => 'Saldo removido com sucesso!',
                    'saldo_anterior' => $saldoAntRep,
                    'saldo_novo' => $saldoNovoRep,
                    'etapas' => $etapasExecutadas
                ]);
            } catch (\Throwable $e) {
                return $this->erro($response, 'Falha ao remover saldo na API Mercos: ' . $e->getMessage(), 500);
            }
        }

        // Subcenário B2: Saldo insuficiente + Checkbox Marcado -> Cobertura via Gestor
        $gConfig = self::GESTORES_FILIAL[$filial] ?? null;
        if (!$gConfig) {
            return $this->erro($response, "Nenhum gestor padrão configurado para a filial {$filial}.");
        }
        $idGestor = $gConfig['id'];
        $nomeGestor = $gConfig['nome'];
        $usouGestor = true;

        // Calcula a diferença que virá do gestor
        // Se representante tem saldo positivo (ex: 100 e quer tirar 120): diferença = 20
        // Se representante está 0 ou negativo: o valor solicitado total vem do gestor
        $diferenca = ($saldoAntRep > 0)
            ? round($valor - $saldoAntRep, 2)
            : round($valor, 2);

        $valorGestor = $diferenca;

        // Ler saldo atual do gestor
        try {
            $saldoAntGestor = $api->saldoDe($idGestor, true);
        } catch (\Throwable $e) {
            return $this->erro($response, "Não foi possível verificar o saldo do Gestor {$nomeGestor}: " . $e->getMessage(), 502);
        }

        if ($saldoAntGestor === null) {
            return $this->erro($response, "Gestor {$nomeGestor} não encontrado na API Mercos da filial {$filial}.");
        }

        if ($saldoAntGestor < $diferenca) {
            $msg = sprintf(
                'Saldo insuficiente no Gestor %s (Saldo atual: R$ %s). O valor necessário para cobrir o empréstimo da movimentação é R$ %s.',
                $nomeGestor,
                number_format($saldoAntGestor, 2, ',', '.'),
                number_format($diferenca, 2, ',', '.')
            );
            return $this->erro($response, $msg, 400);
        }

        // Executar os 3 passos rigorosamente na API Mercos
        try {
            // PASSO 1: Debita do Gestor a diferença
            // Observação exata solicitada: "Emprestimo para o representante {nome} para movimentação : {obs}"
            $obsGestor = "Emprestimo para o representante {$nomeRep} para movimentação : {$observacao}";
            $api->movimentar($idGestor, -$diferenca, $obsGestor);

            $saldoAposGestor = $api->saldoDe($idGestor, true) ?? ($saldoAntGestor - $diferenca);
            $saldoNovoGestor = $saldoAposGestor;

            $etapasExecutadas[] = [
                'etapa' => 1,
                'acao' => 'DEBITO_GESTOR',
                'origem' => $nomeGestor,
                'valor' => -$diferenca,
                'observacao' => $obsGestor,
                'saldo_anterior' => $saldoAntGestor,
                'saldo_novo' => $saldoAposGestor
            ];

            // PASSO 2: Credita no Representante a diferença
            // Observação exata solicitada: "Emprestimo para movimentação : {obs}"
            $obsRepEmprestimo = "Emprestimo para movimentação : {$observacao}";
            $api->movimentar($colabId, +$diferenca, $obsRepEmprestimo);

            $saldoIntermediarioRep = $api->saldoDe($colabId, true) ?? ($saldoAntRep + $diferenca);

            $etapasExecutadas[] = [
                'etapa' => 2,
                'acao' => 'CREDITO_EMPRESTIMO_REP',
                'destino' => $nomeRep,
                'valor' => +$diferenca,
                'observacao' => $obsRepEmprestimo,
                'saldo_anterior' => $saldoAntRep,
                'saldo_novo' => $saldoIntermediarioRep
            ];

            // PASSO 3: Debita do Representante o valor solicitado total
            // Observação exata solicitada: "{obs}" (Remoção para ajuste de saldo)
            $api->movimentar($colabId, -$valor, $observacao);

            $saldoNovoRep = $api->saldoDe($colabId, true) ?? ($saldoIntermediarioRep - $valor);

            $etapasExecutadas[] = [
                'etapa' => 3,
                'acao' => 'DEBITO_FINAL_REP',
                'destino' => $nomeRep,
                'valor' => -$valor,
                'observacao' => $observacao,
                'saldo_anterior' => $saldoIntermediarioRep,
                'saldo_novo' => $saldoNovoRep
            ];

            // Grava Log Completo no PostgreSQL
            $this->gravarLog([
                'usuario' => $u['username'],
                'filial' => $filial,
                'idcolaborador' => $colabId,
                'nome_colaborador' => $nomeRep,
                'tipo_movimentacao' => 'DEBITO',
                'valor_solicitado' => $valor,
                'observacao' => $observacao,
                'saldo_anterior_rep' => $saldoAntRep,
                'saldo_novo_rep' => $saldoNovoRep,
                'usou_gestor' => true,
                'idgestor' => $idGestor,
                'nome_gestor' => $nomeGestor,
                'valor_gestor' => $valorGestor,
                'saldo_anterior_gestor' => $saldoAntGestor,
                'saldo_novo_gestor' => $saldoNovoGestor,
                'sucesso' => true,
                'mensagem' => 'Remoção com cobertura de gestor realizada em 3 etapas com sucesso.',
                'detalhes_json' => json_encode(['gestor_carteira' => $gestorCarteira, 'etapas' => $etapasExecutadas])
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Movimentação com cobertura do Gestor concluída com sucesso!',
                'usou_gestor' => true,
                'gestor' => [
                    'id' => $idGestor,
                    'nome' => $nomeGestor,
                    'valor_retirado' => $valorGestor,
                    'saldo_anterior' => $saldoAntGestor,
                    'saldo_novo' => $saldoNovoGestor
                ],
                'representante' => [
                    'id' => $colabId,
                    'nome' => $nomeRep,
                    'valor_solicitado' => $valor,
                    'saldo_anterior' => $saldoAntRep,
                    'saldo_novo' => $saldoNovoRep
                ],
                'etapas' => $etapasExecutadas
            ]);

        } catch (\Throwable $e) {
            error_log('[flex-movimentacao] Erro durante etapas de movimentação com gestor: ' . $e->getMessage());

            $this->gravarLog([
                'usuario' => $u['username'],
                'filial' => $filial,
                'idcolaborador' => $colabId,
                'nome_colaborador' => $nomeRep,
                'tipo_movimentacao' => 'DEBITO',
                'valor_solicitado' => $valor,
                'observacao' => $observacao,
                'saldo_anterior_rep' => $saldoAntRep,
                'saldo_novo_rep' => $saldoAntRep,
                'usou_gestor' => true,
                'idgestor' => $idGestor,
                'nome_gestor' => $nomeGestor,
                'valor_gestor' => $valorGestor,
                'saldo_anterior_gestor' => $saldoAntGestor,
                'saldo_novo_gestor' => $saldoAntGestor,
                'sucesso' => false,
                'mensagem' => 'Falha durante execução: ' . $e->getMessage(),
                'detalhes_json' => json_encode(['etapas_executadas' => $etapasExecutadas])
            ]);

            return $this->erro($response, 'Erro durante o processamento da movimentação no Mercos: ' . $e->getMessage(), 500);
        }
    }

    private function gravarLog(array $dados): void
    {
        try {
            $sql = 'INSERT INTO movimentacoes_flex_mercos_log (
                        usuario, filial, idcolaborador, nome_colaborador, tipo_movimentacao,
                        valor_solicitado, observacao, saldo_anterior_rep, saldo_novo_rep,
                        usou_gestor, idgestor, nome_gestor, valor_gestor,
                        saldo_anterior_gestor, saldo_novo_gestor, sucesso, mensagem, detalhes_json
                    ) VALUES (
                        :usuario, :filial, :idcolaborador, :nome_colaborador, :tipo_movimentacao,
                        :valor_solicitado, :observacao, :saldo_anterior_rep, :saldo_novo_rep,
                        :usou_gestor, :idgestor, :nome_gestor, :valor_gestor,
                        :saldo_anterior_gestor, :saldo_novo_gestor, :sucesso, :mensagem, :detalhes_json
                    )';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'usuario' => $dados['usuario'] ?? 'SISTEMA',
                'filial' => $dados['filial'] ?? 1,
                'idcolaborador' => $dados['idcolaborador'] ?? 0,
                'nome_colaborador' => $dados['nome_colaborador'] ?? '',
                'tipo_movimentacao' => $dados['tipo_movimentacao'] ?? 'DEBITO',
                'valor_solicitado' => $dados['valor_solicitado'] ?? 0.0,
                'observacao' => $dados['observacao'] ?? '',
                'saldo_anterior_rep' => $dados['saldo_anterior_rep'] ?? 0.0,
                'saldo_novo_rep' => $dados['saldo_novo_rep'] ?? 0.0,
                'usou_gestor' => !empty($dados['usou_gestor']) ? 'true' : 'false',
                'idgestor' => $dados['idgestor'] ?? null,
                'nome_gestor' => $dados['nome_gestor'] ?? null,
                'valor_gestor' => $dados['valor_gestor'] ?? 0.0,
                'saldo_anterior_gestor' => $dados['saldo_anterior_gestor'] ?? null,
                'saldo_novo_gestor' => $dados['saldo_novo_gestor'] ?? null,
                'sucesso' => !empty($dados['sucesso']) ? 'true' : 'false',
                'mensagem' => $dados['mensagem'] ?? '',
                'detalhes_json' => $dados['detalhes_json'] ?? null
            ]);
        } catch (\Throwable $e) {
            error_log('[flex-movimentacao] Falha ao gravar log no banco: ' . $e->getMessage());
        }
    }
}

