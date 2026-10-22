<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Transferência de saldo Flex entre representantes (equivalente ao processo do ERP).
 *
 * Regras:
 *  - O usuário só enxerga representantes das filiais liberadas (usuario.dash_filiais)
 *    e, se tiver dash_gestores, apenas dos gestores liberados (usuarios_mercos.cdgestor);
 *  - Apenas representantes ATIVOS no ERP (cliforemp.inativo <> 'S') e com CONTA/VÍNCULO ATIVO
 *    na API Mercos (/saldo_flex, excluido = false) aparecem nas seleções DE e PARA;
 *  - Observação no Mercos registra explicitamente de quem saiu, para quem foi e o usuário operador;
 *  - Saldo e movimentação comunicam diretamente com a API Mercos de cada filial;
 *  - Cada transferência gera registro detalhado em transferencias_flex_mercos_log.
 */
class FlexTransferenciaController
{
    private const FILIAIS_ADMIN = [1, 6];

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
            'username' => strtoupper((string)($row['username'] ?? ($user['username'] ?? ''))),
            'filiais' => $filiais,
            'gestores' => $isAdmin ? [] : $ints((string)($row['dash_gestores'] ?? '')),
            'is_admin' => $isAdmin,
        ];
    }

    private function flex(int $filial): \Nutricional\Services\MercosFlexService
    {
        return new \Nutricional\Services\MercosFlexService($filial);
    }

    /**
     * Representantes liberados para o usuário, ATIVOS no ERP e com VÍNCULO/CONTA ATIVA no Mercos.
     * Retorna os saldos atualizados diretamente da API Mercos anexados a cada representante.
     */
    private function representantes(array $usuario, int $filial): array
    {
        if (!in_array($filial, $usuario['filiais'], true)) {
            return [];
        }

        // 1. Busca representantes ATIVOS no ERP vinculados à filial
        $sql = 'SELECT DISTINCT ON (um.idcolaborador) 
                    um.idcolaborador, um.username, um.nome, um.filial, um.cdgestor, um.gestor,
                    c.razao, c.fantasia
                FROM usuarios_mercos um
                INNER JOIN cliforemp c ON c.idcliforemp = um.idcolaborador
                WHERE um.idcolaborador IS NOT NULL 
                  AND um.filial = :filial
                  AND COALESCE(c.inativo, \'N\') <> \'S\'';
        if ($usuario['gestores'] !== []) {
            $sql .= ' AND um.cdgestor IN (' . implode(',', array_map('intval', $usuario['gestores'])) . ')';
        }
        $sql .= ' ORDER BY um.idcolaborador';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['filial' => $filial]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        // 2. Consulta a API Mercos para checar vínculo e saldo
        $saldosMercos = [];
        try {
            $saldosMercos = $this->flex($filial)->saldos(false);
        } catch (\Throwable $e) {
            error_log(sprintf('[flex] Falha ao consultar saldos mercos filial %d: %s', $filial, $e->getMessage()));
        }

        // 3. Filtra apenas os que possuem vínculo real e conta ativa na API Mercos
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['idcolaborador'];

            // Se a API Mercos respondeu com sucesso, exige presença no mapa de saldos e não excluído
            if (!empty($saldosMercos)) {
                if (!isset($saldosMercos[$id])) {
                    continue; // Sem conta flex na API Mercos
                }
                $itemMercos = $saldosMercos[$id];
                if (!empty($itemMercos['excluido'])) {
                    continue; // Excluído / inativo na API Mercos
                }
                $saldo = (float)($itemMercos['saldo_atual'] ?? 0.0);
            } else {
                // Se a API Mercos estiver temporariamente fora, mantém os representantes ativos com saldo 0
                $saldo = 0.0;
            }

            $nomeExibicao = trim((string)($r['username'] ?: ($r['fantasia'] ?: $r['razao'])));

            $out[] = [
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

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        return $out;
    }

    private function permitido(array $usuario, int $filial, int $id): ?array
    {
        foreach ($this->representantes($usuario, $filial) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    /** Calcula KPIs e métricas do mês para a visão transparente do Gestor */
    private function calcularResumo(array $usuario, int $filial, array $reps = []): array
    {
        if ($reps === []) {
            $reps = $this->representantes($usuario, $filial);
        }

        // Estatísticas das transferências do mês corrente
        $sql = 'SELECT 
                    COUNT(*) as qtd_mes,
                    COALESCE(SUM(valor), 0) as total_mes,
                    COUNT(CASE WHEN sucesso THEN 1 END) as qtd_sucesso,
                    COUNT(CASE WHEN NOT sucesso THEN 1 END) as qtd_falhas
                FROM transferencias_flex_mercos_log
                WHERE filial = :filial
                  AND sucesso = true
                  AND data_transferencia >= DATE_TRUNC(\'month\', CURRENT_DATE)';
        $params = ['filial' => $filial];

        if ($usuario['gestores'] !== []) {
            $idsReps = array_map(static fn (array $r): int => $r['id'], $reps);
            $listaIds = $idsReps === [] ? '0' : implode(',', $idsReps);
            $sql .= ' AND (usuario = :user OR colaborador_de IN (' . $listaIds . ') OR colaborador_para IN (' . $listaIds . '))';
            $params['user'] = $usuario['username'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Última transferência com sucesso realizada
        $sqlUltima = 'SELECT data_transferencia, nome_de, nome_para, valor, usuario, motivo
                      FROM transferencias_flex_mercos_log
                      WHERE filial = :filial AND sucesso = true';
        if ($usuario['gestores'] !== []) {
            $sqlUltima .= ' AND (usuario = :user OR colaborador_de IN (' . $listaIds . ') OR colaborador_para IN (' . $listaIds . '))';
        }
        $sqlUltima .= ' ORDER BY data_transferencia DESC LIMIT 1';

        $stmtUltima = $this->pdo->prepare($sqlUltima);
        $stmtUltima->execute($params);
        $ultima = $stmtUltima->fetch(PDO::FETCH_ASSOC) ?: null;

        // Saldo total da equipe e saldo do gestor logado
        $saldoEquipe = 0.0;
        $saldoGestor = null;
        $nomeGestor = null;
        $usernameUpper = $usuario['username'];

        foreach ($reps as $r) {
            $saldoEquipe += $r['saldo'];
            // Identifica se este registro é a conta própria do gestor/usuário
            if ($saldoGestor === null) {
                $nomeU = strtoupper($r['nome']);
                if (str_contains($nomeU, $usernameUpper) || str_contains($nomeU, '(GESTOR)')) {
                    if (str_contains($nomeU, $usernameUpper)) {
                        $saldoGestor = $r['saldo'];
                        $nomeGestor = $r['nome'];
                    }
                }
            }
        }

        return [
            'total_mes' => (float)($stats['total_mes'] ?? 0.0),
            'qtd_mes' => (int)($stats['qtd_mes'] ?? 0),
            'qtd_sucesso' => (int)($stats['qtd_sucesso'] ?? 0),
            'saldo_equipe' => round($saldoEquipe, 2),
            'total_representantes' => count($reps),
            'saldo_gestor' => $saldoGestor,
            'nome_gestor' => $nomeGestor,
            'ultima_transferencia' => $ultima,
        ];
    }

    /** GET /v1/flex/contexto?filial=X */
    public function contexto(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $filiais = [];
            if ($usuario['filiais'] !== []) {
                $in = implode(',', $usuario['filiais']);
                $filiais = $this->pdo->query("SELECT idfilial, razao FROM filial WHERE idfilial IN ($in) ORDER BY idfilial")
                    ->fetchAll(PDO::FETCH_ASSOC);
            }

            $q = $request->getQueryParams();
            $filialPadrao = !empty($filiais) ? (int)$filiais[0]['idfilial'] : 1;
            $filial = (int)($q['filial'] ?? $filialPadrao);
            if (!in_array($filial, $usuario['filiais'], true) && !empty($usuario['filiais'])) {
                $filial = (int)$usuario['filiais'][0];
            }

            $reps = $this->representantes($usuario, $filial);
            $resumo = $this->calcularResumo($usuario, $filial, $reps);

            return $this->json($response, [
                'success' => true,
                'usuario' => $usuario['username'],
                'is_admin' => $usuario['is_admin'],
                'filiais' => $filiais,
                'filial_atual' => $filial,
                'representantes' => $reps,
                'resumo' => $resumo,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar contexto: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/flex/representantes?filial=X */
    public function listarRepresentantes(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $filial = (int)($q['filial'] ?? 0);
            $usuario = $this->usuario($request);

            if (!in_array($filial, $usuario['filiais'], true)) {
                return $this->erro($response, 'Filial não liberada para o seu usuário.', 403);
            }

            $reps = $this->representantes($usuario, $filial);
            $resumo = $this->calcularResumo($usuario, $filial, $reps);

            return $this->json($response, [
                'success' => true,
                'filial' => $filial,
                'representantes' => $reps,
                'resumo' => $resumo,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao listar representantes: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/flex/resumo?filial=X */
    public function resumo(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $filial = (int)($q['filial'] ?? 0);
            $usuario = $this->usuario($request);

            if (!in_array($filial, $usuario['filiais'], true)) {
                return $this->erro($response, 'Filial não liberada para o seu usuário.', 403);
            }

            $resumo = $this->calcularResumo($usuario, $filial);
            return $this->json($response, ['success' => true, 'resumo' => $resumo]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar resumo: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/flex/saldo?filial=1&id=123 */
    public function saldo(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $filial = (int)($q['filial'] ?? 0);
            $id = (int)($q['id'] ?? 0);
            $rep = $this->permitido($this->usuario($request), $filial, $id);
            if ($rep === null) {
                return $this->erro($response, 'Representante não liberado, inativo no ERP ou sem vínculo no Mercos.', 403);
            }
            $item = $this->flex($filial)->saldos(true)[$id] ?? null;
            if ($item === null) {
                return $this->erro($response, 'Representante sem saldo Flex na API Mercos.', 404);
            }
            return $this->json($response, [
                'success' => true,
                'id' => $id,
                'nome' => $rep['nome'],
                'saldo' => (float)$item['saldo_atual'],
                'ativo' => empty($item['excluido']),
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, $e->getMessage(), 502);
        }
    }

    private function registrarLog(array $d): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO transferencias_flex_mercos_log
             (colaborador_de, colaborador_para, valor, motivo, data_transferencia, sucesso, mensagem, nome_de, nome_para,
              valor_saldo_anterior_de, valor_novo_saldo_de, usuario, valor_saldo_anterior_para, valor_novo_saldo_para, filial)
             VALUES (:de, :para, :valor, :motivo, CURRENT_TIMESTAMP, :ok, :msg, :nde, :npara,
                     :sade, :snde, :usuario, :sapara, :snpara, :filial)'
        );
        $stmt->execute([
            'de' => $d['de'], 'para' => $d['para'], 'valor' => $d['valor'], 'motivo' => $d['motivo'],
            'ok' => $d['ok'] ? 'true' : 'false', 'msg' => $d['msg'], 'nde' => $d['nome_de'], 'npara' => $d['nome_para'],
            'sade' => $d['saldo_ant_de'], 'snde' => $d['saldo_novo_de'], 'usuario' => $d['usuario'],
            'sapara' => $d['saldo_ant_para'], 'snpara' => $d['saldo_novo_para'], 'filial' => $d['filial'],
        ]);
    }

    /** POST /v1/flex/transferir {filial, de, para, valor, motivo} */
    public function transferir(Request $request, Response $response): Response
    {
        $b = (array)($request->getParsedBody() ?? []);
        $filial = (int)($b['filial'] ?? 0);
        $de = (int)($b['de'] ?? 0);
        $para = (int)($b['para'] ?? 0);
        $valor = round((float)($b['valor'] ?? 0), 2);
        $motivo = trim((string)($b['motivo'] ?? ''));

        if ($de <= 0 || $para <= 0 || $de === $para) {
            return $this->erro($response, 'Escolha dois representantes diferentes (origem DE e destino PARA).');
        }
        if ($valor <= 0) {
            return $this->erro($response, 'Informe um valor maior que zero.');
        }
        if (mb_strlen($motivo) < 4) {
            return $this->erro($response, 'Informe um motivo claro com pelo menos 4 caracteres.');
        }
        $motivo = mb_substr($motivo, 0, 255);

        $usuario = $this->usuario($request);
        $repDe = $this->permitido($usuario, $filial, $de);
        $repPara = $this->permitido($usuario, $filial, $para);
        if ($repDe === null || $repPara === null) {
            return $this->erro($response, 'Um dos representantes selecionados não está liberado, está inativo no ERP ou sem vínculo no Mercos.', 403);
        }

        // Evita transferências simultâneas concorrentes do mesmo representante emissor
        $lock = $this->pdo->prepare('SELECT pg_try_advisory_lock(:k)');
        $lock->execute(['k' => crc32('flex:' . $filial . ':' . $de)]);
        if (!$lock->fetchColumn()) {
            return $this->erro($response, 'Já existe uma transferência em andamento para este representante. Aguarde alguns instantes.', 409);
        }

        $log = [
            'de' => $de, 'para' => $para, 'valor' => $valor, 'motivo' => $motivo, 'ok' => false, 'msg' => '',
            'nome_de' => $repDe['nome'], 'nome_para' => $repPara['nome'], 'usuario' => $usuario['username'],
            'saldo_ant_de' => null, 'saldo_novo_de' => null, 'saldo_ant_para' => null, 'saldo_novo_para' => null,
            'filial' => $filial,
        ];
        $debitado = false;

        try {
            $flex = $this->flex($filial);
            $saldos = $flex->saldos(true);
            $sDe = $saldos[$de] ?? null;
            $sPara = $saldos[$para] ?? null;
            if ($sDe === null || $sPara === null) {
                throw new \DomainException('Representante sem saldo Flex cadastrado na API Mercos.');
            }
            if (!empty($sDe['excluido']) || !empty($sPara['excluido'])) {
                throw new \DomainException('Representante inativo ou excluído no Mercos não pode transferir ou receber.');
            }
            $log['saldo_ant_de'] = (float)$sDe['saldo_atual'];
            $log['saldo_ant_para'] = (float)$sPara['saldo_atual'];
            if ($log['saldo_ant_de'] < $valor) {
                throw new \DomainException('O representante ' . $repDe['nome'] . ' não possui saldo suficiente para transferir (Saldo: R$ ' . number_format($log['saldo_ant_de'], 2, ',', '.') . ').');
            }

            // Observação transparente enviada para a API Mercos com DE, PARA e Usuário Solicitante
            $prefixoMercos = sprintf('Transf: %s (#%d) -> %s (#%d) por %s | ', $repDe['nome'], $de, $repPara['nome'], $para, $usuario['username']);
            $maxDisponivel = 250 - mb_strlen($prefixoMercos);
            $motivoTruncado = mb_strlen($motivo) > $maxDisponivel ? (mb_substr($motivo, 0, max(1, $maxDisponivel - 3)) . '...') : $motivo;
            $obsMercos = $prefixoMercos . $motivoTruncado;

            // 1. Débito no emissor
            $flex->movimentar($de, -$valor, $obsMercos);
            $debitado = true;

            // 2. Crédito no destinatário
            $flex->movimentar($para, $valor, $obsMercos);

            $novos = [];
            try {
                $novos = $flex->saldos(true);
            } catch (\Throwable $e) {
                // Transferência já aplicada no Mercos: fallback para saldos calculados
            }
            $log['saldo_novo_de'] = isset($novos[$de]) ? (float)$novos[$de]['saldo_atual'] : round($log['saldo_ant_de'] - $valor, 2);
            $log['saldo_novo_para'] = isset($novos[$para]) ? (float)$novos[$para]['saldo_atual'] : round($log['saldo_ant_para'] + $valor, 2);
            $log['ok'] = true;
            $log['msg'] = 'Transferência realizada com sucesso';

            $aviso = null;
            try {
                $this->registrarLog($log);
            } catch (\Throwable $e) {
                $aviso = 'Transferência aplicada no Mercos, mas o registro no log interno falhou: ' . $e->getMessage();
            }

            return $this->json($response, [
                'success' => true,
                'message' => "Transferência de R$ " . number_format($valor, 2, ',', '.') . " de {$repDe['nome']} para {$repPara['nome']} concluída com sucesso.",
                'obs_mercos' => $obsMercos,
                'saldo_de' => $log['saldo_novo_de'],
                'saldo_para' => $log['saldo_novo_para'],
                'aviso' => $aviso,
            ]);
        } catch (\DomainException $e) {
            $log['msg'] = $e->getMessage();
            $status = 422;
        } catch (\Throwable $e) {
            $log['msg'] = 'Erro ao processar transferência no Mercos: ' . $e->getMessage();
            $status = 502;
            if ($debitado) {
                // Se o débito passou mas o crédito falhou: estorno automático com observação explicativa
                try {
                    $obsEstorno = mb_substr(sprintf('ESTORNO Transf. p/ %s (#%d) por %s falhou: %s', $repPara['nome'], $para, $usuario['username'], $motivo), 0, 250);
                    $flex->movimentar($de, $valor, $obsEstorno);
                    $log['msg'] .= ' (débito de origem foi estornado automaticamente no Mercos)';
                } catch (\Throwable $e2) {
                    $log['msg'] .= ' (ATENÇÃO CRÍTICA: débito de ' . $repDe['nome'] . ' NÃO pôde ser estornado automaticamente - conferir no Mercos)';
                }
            }
        } finally {
            $unlock = $this->pdo->prepare('SELECT pg_advisory_unlock(:k)');
            $unlock->execute(['k' => crc32('flex:' . $filial . ':' . $de)]);
        }

        try {
            $this->registrarLog($log);
        } catch (\Throwable $e) {
            // Falha ao registrar log de erro não mascara a mensagem original
        }
        return $this->erro($response, $log['msg'], $status);
    }

    /**
     * GET /v1/flex/movimentacoes?filial=1[&de=ID][&para=ID][&inicio=YYYY-MM-DD][&fim=YYYY-MM-DD][&status=ok|falha][&q=termo][&limite=10]
     */
    public function movimentacoes(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $usuario = $this->usuario($request);
            $filial = (int)($q['filial'] ?? 0);
            if (!in_array($filial, $usuario['filiais'], true)) {
                return $this->erro($response, 'Filial não liberada para o seu usuário.', 403);
            }

            $ok = static fn (string $d): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
            $inicio = $ok((string)($q['inicio'] ?? '')) ? $q['inicio'] : null;
            $fim = $ok((string)($q['fim'] ?? '')) ? $q['fim'] : null;
            $de = (int)($q['de'] ?? 0);
            $para = (int)($q['para'] ?? 0);
            $statusFiltro = trim((string)($q['status'] ?? ''));
            $buscaTexto = trim((string)($q['q'] ?? ''));
            $limite = max(1, min(2000, (int)($q['limite'] ?? 10)));

            $sql = 'SELECT id, data_transferencia, colaborador_de, colaborador_para, nome_de, nome_para, valor,
                           valor_saldo_anterior_de, valor_novo_saldo_de,
                           valor_saldo_anterior_para, valor_novo_saldo_para,
                           motivo, usuario, sucesso, mensagem, filial
                    FROM transferencias_flex_mercos_log
                    WHERE filial = :filial';
            $params = ['filial' => $filial];

            if ($inicio !== null) {
                $sql .= ' AND data_transferencia >= :inicio::date';
                $params['inicio'] = $inicio;
            }
            if ($fim !== null) {
                $sql .= ' AND data_transferencia < (:fim::date + 1)';
                $params['fim'] = $fim;
            }
            if ($de > 0) {
                $sql .= ' AND colaborador_de = :de';
                $params['de'] = $de;
            }
            if ($para > 0) {
                $sql .= ' AND colaborador_para = :para';
                $params['para'] = $para;
            }
            if ($statusFiltro === 'ok') {
                $sql .= ' AND sucesso = true';
            } elseif ($statusFiltro === 'falha') {
                $sql .= ' AND sucesso = false';
            }
            if ($buscaTexto !== '') {
                $sql .= ' AND (nome_de ILIKE :q OR nome_para ILIKE :q OR motivo ILIKE :q OR usuario ILIKE :q)';
                $params['q'] = '%' . $buscaTexto . '%';
            }

            if ($usuario['gestores'] !== []) {
                $reps = $this->representantes($usuario, $filial);
                $ids = array_map(static fn (array $r): int => $r['id'], $reps);
                $lista = $ids === [] ? '0' : implode(',', $ids);
                $sql .= " AND (usuario = :usuario OR colaborador_de IN ($lista) OR colaborador_para IN ($lista))";
                $params['usuario'] = $usuario['username'];
            }
            $sql .= ' ORDER BY data_transferencia DESC LIMIT ' . $limite;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'success' => true,
                'inicio' => $inicio,
                'fim' => $fim,
                'total' => count($rows),
                'movimentacoes' => $rows,
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar movimentações: ' . $e->getMessage(), 500);
        }
    }
}
