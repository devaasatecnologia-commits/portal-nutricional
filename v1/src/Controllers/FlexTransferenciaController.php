<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Transferencia de saldo Flex entre representantes (equivalente ao processo do ERP).
 *
 * Regras:
 *  - o usuario so enxerga representantes (usuarios_mercos) das filiais de usuario.dash_filiais
 *    e, se tiver dash_gestores, apenas dos gestores liberados (usuarios_mercos.cdgestor);
 *  - saldo e movimentacao ficam na API Mercos de cada filial (/saldo_flex);
 *  - cada transferencia gera registro em transferencias_flex_mercos_log.
 */
class FlexTransferenciaController
{
    private const FILIAIS_ADMIN = [1, 6];
    private const FLEX_URL_PADRAO = [
        1 => 'http://mercosf1.nutricionalbr.com:8081/saldo_flex',
        6 => 'http://mercosf1.nutricionalbr.com:8086/saldo_flex',
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
            'username' => strtoupper((string)($row['username'] ?? ($user['username'] ?? ''))),
            'filiais' => $filiais,
            'gestores' => $isAdmin ? [] : $ints((string)($row['dash_gestores'] ?? '')),
        ];
    }

    /** Representantes liberados para o usuario (opcionalmente de uma filial). */
    private function representantes(array $usuario, ?int $filial = null): array
    {
        $filiais = $filial !== null ? array_values(array_intersect($usuario['filiais'], [$filial])) : $usuario['filiais'];
        if ($filiais === []) {
            return [];
        }

        $sql = 'SELECT DISTINCT ON (filial, idcolaborador) idcolaborador, username, nome, filial, cdgestor, gestor
                FROM usuarios_mercos
                WHERE idcolaborador IS NOT NULL AND filial IN (' . implode(',', array_map('intval', $filiais)) . ')';
        if ($usuario['gestores'] !== []) {
            $sql .= ' AND cdgestor IN (' . implode(',', $usuario['gestores']) . ')';
        }
        $sql .= ' ORDER BY filial, idcolaborador';

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $out = array_map(static fn (array $r): array => [
            'id' => (int)$r['idcolaborador'],
            'nome' => trim((string)($r['username'] ?: $r['nome'])),
            'filial' => (int)$r['filial'],
            'gestor' => (string)$r['gestor'],
        ], $rows);
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        return $out;
    }

    private function flexUrl(int $filial): string
    {
        $env = getenv('FLEX_API_URL_F' . $filial) ?: ($_ENV['FLEX_API_URL_F' . $filial] ?? '');
        if ($env !== '') {
            return $env;
        }
        return self::FLEX_URL_PADRAO[$filial] ?? self::FLEX_URL_PADRAO[6];
    }

    /** @return array{0:int,1:string} status HTTP e corpo */
    private function http(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new \RuntimeException('API de saldo Flex indisponivel: ' . $err);
        }
        return [$status, (string)$resp];
    }

    /** @return array<int,array> saldos indexados por cliforemp_id */
    private function saldos(int $filial): array
    {
        [$status, $body] = $this->http('GET', $this->flexUrl($filial));
        $data = json_decode($body, true);
        if ($status >= 400 || !is_array($data)) {
            throw new \RuntimeException('Resposta invalida da API de saldo Flex (HTTP ' . $status . ')');
        }
        $map = [];
        foreach ($data as $item) {
            if (isset($item['cliforemp_id'])) {
                $map[(int)$item['cliforemp_id']] = $item;
            }
        }
        return $map;
    }

    private function movimentar(int $filial, int $colaborador, float $valor, string $obs): void
    {
        [$status, $body] = $this->http('POST', $this->flexUrl($filial), [
            'colaborador_id' => $colaborador,
            'valor_movimentado' => $valor,
            'observacao' => $obs,
        ]);
        if ($status >= 400) {
            throw new \RuntimeException('API de saldo Flex recusou a movimentacao (HTTP ' . $status . '): ' . mb_substr($body, 0, 200));
        }
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

    /** GET /v1/flex/contexto */
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
            return $this->json($response, [
                'success' => true,
                'usuario' => $usuario['username'],
                'filiais' => $filiais,
                'representantes' => $this->representantes($usuario),
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar contexto: ' . $e->getMessage(), 500);
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
                return $this->erro($response, 'Representante nao liberado para o seu usuario.', 403);
            }
            $item = $this->saldos($filial)[$id] ?? null;
            if ($item === null) {
                return $this->erro($response, 'Representante sem saldo Flex na API.', 404);
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
            return $this->erro($response, 'Escolha dois representantes diferentes (DE e PARA).');
        }
        if ($valor <= 0) {
            return $this->erro($response, 'Informe um valor maior que zero.');
        }
        if (mb_strlen($motivo) < 4) {
            return $this->erro($response, 'Informe um motivo com pelo menos 4 caracteres.');
        }
        $motivo = mb_substr($motivo, 0, 255);

        $usuario = $this->usuario($request);
        $repDe = $this->permitido($usuario, $filial, $de);
        $repPara = $this->permitido($usuario, $filial, $para);
        if ($repDe === null || $repPara === null) {
            return $this->erro($response, 'Representante nao liberado para o seu usuario nesta filial.', 403);
        }

        // evita duas transferencias simultaneas do mesmo representante
        $lock = $this->pdo->prepare('SELECT pg_try_advisory_lock(:k)');
        $lock->execute(['k' => crc32('flex:' . $filial . ':' . $de)]);
        if (!$lock->fetchColumn()) {
            return $this->erro($response, 'Ja existe uma transferencia em andamento para este representante.', 409);
        }

        $log = [
            'de' => $de, 'para' => $para, 'valor' => $valor, 'motivo' => $motivo, 'ok' => false, 'msg' => '',
            'nome_de' => $repDe['nome'], 'nome_para' => $repPara['nome'], 'usuario' => $usuario['username'],
            'saldo_ant_de' => null, 'saldo_novo_de' => null, 'saldo_ant_para' => null, 'saldo_novo_para' => null,
            'filial' => $filial,
        ];
        $debitado = false;

        try {
            $saldos = $this->saldos($filial);
            $sDe = $saldos[$de] ?? null;
            $sPara = $saldos[$para] ?? null;
            if ($sDe === null || $sPara === null) {
                throw new \DomainException('Representante sem saldo Flex cadastrado na API.');
            }
            if (!empty($sDe['excluido']) || !empty($sPara['excluido'])) {
                throw new \DomainException('Representante inativo/excluido nao pode transferir ou receber.');
            }
            $log['saldo_ant_de'] = (float)$sDe['saldo_atual'];
            $log['saldo_ant_para'] = (float)$sPara['saldo_atual'];
            if ($log['saldo_ant_de'] < $valor) {
                throw new \DomainException('O representante ' . $repDe['nome'] . ' nao possui saldo suficiente para transferir.');
            }

            $this->movimentar($filial, $de, -$valor, $motivo);
            $debitado = true;
            $this->movimentar($filial, $para, $valor, $motivo);

            $novos = $this->saldos($filial);
            $log['saldo_novo_de'] = isset($novos[$de]) ? (float)$novos[$de]['saldo_atual'] : round($log['saldo_ant_de'] - $valor, 2);
            $log['saldo_novo_para'] = isset($novos[$para]) ? (float)$novos[$para]['saldo_atual'] : round($log['saldo_ant_para'] + $valor, 2);
            $log['ok'] = true;
            $log['msg'] = 'Transferência realizada com sucesso';

            $aviso = null;
            try {
                $this->registrarLog($log);
            } catch (\Throwable $e) {
                $aviso = 'Transferencia feita, mas o registro no log falhou: ' . $e->getMessage();
            }

            return $this->json($response, [
                'success' => true,
                'message' => "Transferência de R$ " . number_format($valor, 2, ',', '.') . " de {$repDe['nome']} para {$repPara['nome']} realizada.",
                'saldo_de' => $log['saldo_novo_de'],
                'saldo_para' => $log['saldo_novo_para'],
                'aviso' => $aviso,
            ]);
        } catch (\DomainException $e) {
            $log['msg'] = $e->getMessage();
            $status = 422;
        } catch (\Throwable $e) {
            $log['msg'] = 'Erro ao enviar saldo flex: ' . $e->getMessage();
            $status = 502;
            if ($debitado) {
                // o debito passou e o credito falhou: devolve para quem enviou
                try {
                    $this->movimentar($filial, $de, $valor, 'ESTORNO: ' . $motivo);
                    $log['msg'] .= ' (débito estornado automaticamente)';
                } catch (\Throwable $e2) {
                    $log['msg'] .= ' (ATENCAO: debito de ' . $repDe['nome'] . ' NAO foi estornado - conferir manualmente)';
                }
            }
        } finally {
            $unlock = $this->pdo->prepare('SELECT pg_advisory_unlock(:k)');
            $unlock->execute(['k' => crc32('flex:' . $filial . ':' . $de)]);
        }

        try {
            $this->registrarLog($log);
        } catch (\Throwable $e) {
            // falha de log nao esconde o erro principal
        }
        return $this->erro($response, $log['msg'], $status);
    }

    /** GET /v1/flex/movimentacoes?filial=1&inicio=YYYY-MM-DD&fim=YYYY-MM-DD */
    public function movimentacoes(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $usuario = $this->usuario($request);
            $filial = (int)($q['filial'] ?? 0);
            if (!in_array($filial, $usuario['filiais'], true)) {
                return $this->erro($response, 'Filial nao liberada para o seu usuario.', 403);
            }

            $ok = static fn (string $d): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
            $inicio = $ok((string)($q['inicio'] ?? '')) ? $q['inicio'] : date('Y-m-d', strtotime('-30 days'));
            $fim = $ok((string)($q['fim'] ?? '')) ? $q['fim'] : date('Y-m-d');

            $sql = 'SELECT data_transferencia, nome_de, nome_para, valor, valor_saldo_anterior_de, valor_novo_saldo_de,
                           valor_saldo_anterior_para, valor_novo_saldo_para, motivo, usuario, sucesso, mensagem
                    FROM transferencias_flex_mercos_log
                    WHERE filial = :filial
                      AND data_transferencia >= :inicio::date AND data_transferencia < (:fim::date + 1)';
            $params = ['filial' => $filial, 'inicio' => $inicio, 'fim' => $fim];

            if ($usuario['gestores'] !== []) {
                $ids = array_map(static fn (array $r): int => $r['id'], $this->representantes($usuario, $filial));
                $lista = $ids === [] ? '0' : implode(',', $ids);
                $sql .= " AND (usuario = :usuario OR colaborador_de IN ($lista) OR colaborador_para IN ($lista))";
                $params['usuario'] = $usuario['username'];
            }
            $sql .= ' ORDER BY data_transferencia DESC LIMIT 500';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, ['success' => true, 'inicio' => $inicio, 'fim' => $fim, 'movimentacoes' => $rows]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar movimentacoes: ' . $e->getMessage(), 500);
        }
    }
}
