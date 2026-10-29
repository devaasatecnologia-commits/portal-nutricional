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
        ];
    }

    private function flex(int $filial): MercosFlexService
    {
        return new MercosFlexService($filial);
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
     * Lista representantes da filial com saldos em tempo real
     */
    public function listarRepresentantes(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        $params = $request->getQueryParams();
        $filial = (int)($params['filial'] ?? 1);

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
        $sql .= ' ORDER BY um.idcolaborador';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['filial' => $filial]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Consulta API Mercos para saldos atualizados
        $saldosMercos = [];
        try {
            $saldosMercos = $this->flex($filial)->saldos(false);
        } catch (\Throwable $e) {
            error_log(sprintf('[flex-movimentacao] Falha ao consultar saldos mercos f%d: %s', $filial, $e->getMessage()));
        }

        // 3. Monta lista filtrada
        $lista = [];
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
                $saldo = 0.0;
            }

            $nomeExibicao = trim((string)($r['username'] ?: ($r['fantasia'] ?: $r['razao'])));

            $lista[] = [
                'id' => $id,
                'nome' => $nomeExibicao,
                'razao' => trim((string)$r['razao']),
                'fantasia' => trim((string)$r['fantasia']),
                'filial' => (int)$r['filial'],
                'cdgestor' => (int)$r['cdgestor'],
                'gestor' => (string)$r['gestor'],
                'saldo' => $saldo,
            ];
        }

        usort($lista, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        // Dados do gestor da filial
        $gestorConfig = self::GESTORES_FILIAL[$filial] ?? null;
        $saldoGestor = 0.0;
        if ($gestorConfig) {
            try {
                $saldoGestor = (float)($this->flex($filial)->saldoDe($gestorConfig['id'], true) ?? 0.0);
            } catch (\Throwable $e) {}
        }

        return $this->json($response, [
            'success' => true,
            'filial' => $filial,
            'representantes' => $lista,
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
     * Retorna o histórico de movimentações manuais salvas no banco
     */
    public function historico(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filial = isset($params['filial']) && $params['filial'] !== '' ? (int)$params['filial'] : null;
        $limite = min(100, max(10, (int)($params['limite'] ?? 50)));

        $sql = 'SELECT 
                    id, 
                    to_char(data_movimentacao, \'DD/MM/YYYY HH24:MI:SS\') AS data_formatada,
                    usuario, 
                    filial, 
                    idcolaborador, 
                    nome_colaborador, 
                    tipo_movimentacao, 
                    valor_solicitado, 
                    observacao, 
                    saldo_anterior_rep, 
                    saldo_novo_rep, 
                    usou_gestor, 
                    idgestor, 
                    nome_gestor, 
                    valor_gestor, 
                    saldo_anterior_gestor, 
                    saldo_novo_gestor, 
                    sucesso, 
                    mensagem,
                    detalhes_json
                FROM movimentacoes_flex_mercos_log ';
        $conds = [];
        $binds = [];

        if ($filial !== null && in_array($filial, self::FILIAIS_ADMIN, true)) {
            $conds[] = 'filial = :filial';
            $binds['filial'] = $filial;
        }

        if (!empty($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY id DESC LIMIT :limite';

        $stmt = $this->pdo->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->json($response, [
            'success' => true,
            'historico' => $rows
        ]);
    }

    /**
     * Executa a movimentação manual no Mercos com suporte à cobertura de Gestor
     */
    public function movimentar(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
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

        // Buscar dados do representante
        $stmtRep = $this->pdo->prepare('
            SELECT um.idcolaborador, um.username, c.razao, c.fantasia 
            FROM usuarios_mercos um 
            LEFT JOIN cliforemp c ON c.idcliforemp = um.idcolaborador 
            WHERE um.idcolaborador = :id AND um.filial = :filial LIMIT 1
        ');
        $stmtRep->execute(['id' => $colabId, 'filial' => $filial]);
        $repInfo = $stmtRep->fetch(PDO::FETCH_ASSOC);
        $nomeRep = $repInfo ? trim((string)($repInfo['username'] ?: ($repInfo['fantasia'] ?: $repInfo['razao']))) : "Colaborador {$colabId}";

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
                    'detalhes_json' => json_encode(['etapas' => $etapasExecutadas])
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
                    'detalhes_json' => json_encode(['etapas' => $etapasExecutadas])
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
                'detalhes_json' => json_encode(['etapas' => $etapasExecutadas])
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

