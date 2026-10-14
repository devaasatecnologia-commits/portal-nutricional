<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Verbas Flex: monitor de debitos (bonificacao retirada nas notas) e creditos (emprestimos ao gestor).
 *
 * Acesso sempre pelo painel admin (tabela usuario):
 *  - dash_filiais: filiais que o usuario enxerga (admin sem valor: 1 e 6);
 *  - dash_gestores: supervisores liberados; filtram os debitos por nfs.idsupervisor
 *    (vazio = todos). Os creditos (log_debito_flex_gestor) nao tem supervisor, entao so respeitam a filial.
 */
class VerbasFlexController
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
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);

        $stmt = $this->pdo->prepare('SELECT dash_filiais, dash_gestores FROM usuario WHERE idusuario = :id');
        $stmt->execute(['id' => (int)($user['idusuario'] ?? 0)]);
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
            'filiais' => $filiais,
            'gestores' => $isAdmin ? [] : $ints((string)($row['dash_gestores'] ?? '')),
        ];
    }

    /** Valida filial e periodo; padrao = mes atual. */
    private function filtros(Request $request, array $usuario): array
    {
        $q = $request->getQueryParams();
        $filial = (int)($q['filial'] ?? 0);
        if (!in_array($filial, $usuario['filiais'], true)) {
            throw new \DomainException('Filial nao liberada para o seu usuario.');
        }
        $data = static fn ($d, string $padrao): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? (string)$d : $padrao;
        $inicio = $data($q['inicio'] ?? '', date('Y-m-01'));
        $fim = $data($q['fim'] ?? '', date('Y-m-d'));
        if ($fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        return [
            'filial' => $filial,
            'inicio' => $inicio,
            'fim' => $fim,
            'representante' => (int)($q['representante'] ?? 0),
            'busca' => trim((string)($q['q'] ?? '')),
            'limite' => max(1, min(2000, (int)($q['limite'] ?? 500))),
        ];
    }

    /** FROM/WHERE comum dos debitos. @return array{0:string,1:array} */
    private function baseDebitos(array $f, array $usuario, bool $comRepresentante = true): array
    {
        $sql = " FROM log_flex_bonificacao lfb
                 JOIN nfs ON lfb.numero_nota ~ '^[0-9]+\$' AND CAST(lfb.numero_nota AS INT) = nfs.idnfs
                 JOIN cliforemp cli ON cli.idcliforemp = nfs.idcliforemp
                 JOIN cliforemp vend ON vend.idcliforemp = lfb.idvendrepre
                 LEFT JOIN cliforemp gestor ON gestor.idcliforemp = nfs.idsupervisor
                 WHERE lfb.valor_tirado <> 0
                   AND lfb.idfilial = :filial
                   AND nfs.datahora >= :inicio::date AND nfs.datahora < (:fim::date + 1)";
        $p = ['filial' => $f['filial'], 'inicio' => $f['inicio'], 'fim' => $f['fim']];

        if ($usuario['gestores'] !== []) {
            $sql .= ' AND nfs.idsupervisor IN (' . implode(',', $usuario['gestores']) . ')';
        }
        if ($comRepresentante && $f['representante'] > 0) {
            $sql .= ' AND lfb.idvendrepre = :rep';
            $p['rep'] = $f['representante'];
        }
        return [$sql, $p];
    }

    private function baseCreditos(array $f): array
    {
        return [
            ' FROM log_debito_flex_gestor ldfg WHERE ldfg.idfilial = :filial AND ldfg.data >= :inicio::date AND ldfg.data <= :fim::date',
            ['filial' => $f['filial'], 'inicio' => $f['inicio'], 'fim' => $f['fim']],
        ];
    }

    private function consultar(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** GET /v1/verbas-flex/contexto */
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
            return $this->json($response, ['success' => true, 'filiais' => $filiais]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar contexto: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/verbas-flex/debitos */
    public function debitos(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $f = $this->filtros($request, $usuario);
            [$from, $p] = $this->baseDebitos($f, $usuario);

            if ($f['busca'] !== '') {
                $from .= ' AND (lfb.observacao ILIKE :b OR cli.fantasia ILIKE :b OR vend.fantasia ILIKE :b OR lfb.numero_nota = :bx)';
                $p['b'] = '%' . $f['busca'] . '%';
                $p['bx'] = $f['busca'];
            }

            $rows = $this->consultar(
                "SELECT lfb.id, lfb.observacao AS motivo, nfs.datahora AS data, nfs.idnfs AS nota,
                        cli.idcliforemp AS cdcliente, cli.fantasia AS cliente,
                        lfb.idvendrepre AS cdrepre, vend.fantasia AS representante,
                        gestor.fantasia AS gestor, nfs.valortotalitens AS valor_nota,
                        lfb.valor_tirado, lfb.valor $from
                 ORDER BY nfs.datahora DESC, lfb.id DESC LIMIT " . $f['limite'],
                $p
            );
            $tot = $this->consultar('SELECT COALESCE(SUM(lfb.valor_tirado),0) AS total, COUNT(*) AS qtd' . $from, $p)[0];

            // representantes do periodo (sem o filtro de representante) para o seletor
            [$fromRep, $pRep] = $this->baseDebitos($f, $usuario, false);
            $reps = $this->consultar(
                "SELECT DISTINCT lfb.idvendrepre AS id, vend.fantasia AS nome $fromRep ORDER BY 2",
                $pRep
            );

            return $this->json($response, [
                'success' => true,
                'registros' => $rows,
                'total' => (float)$tot['total'],
                'quantidade' => (int)$tot['qtd'],
                'representantes' => $reps,
            ]);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar debitos: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/verbas-flex/creditos */
    public function creditos(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $f = $this->filtros($request, $usuario);
            [$from, $p] = $this->baseCreditos($f);

            if ($f['busca'] !== '') {
                $from .= ' AND ldfg.motivo ILIKE :b';
                $p['b'] = '%' . $f['busca'] . '%';
            }
            if (empty($request->getQueryParams()['zerados'])) {
                $from .= ' AND ldfg.valor_emprestimo <> 0';
            }

            $rows = $this->consultar(
                "SELECT ldfg.id, ldfg.data, ldfg.motivo, ldfg.valor_emprestimo $from
                 ORDER BY ldfg.data DESC, ldfg.id DESC LIMIT " . $f['limite'],
                $p
            );
            $tot = $this->consultar('SELECT COALESCE(SUM(ldfg.valor_emprestimo),0) AS total, COUNT(*) AS qtd' . $from, $p)[0];

            return $this->json($response, [
                'success' => true,
                'registros' => $rows,
                'total' => (float)$tot['total'],
                'quantidade' => (int)$tot['qtd'],
            ]);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao buscar creditos: ' . $e->getMessage(), 500);
        }
    }

    private function filialLiberada(Request $request): int
    {
        $usuario = $this->usuario($request);
        $filial = (int)($request->getQueryParams()['filial'] ?? 0);
        if (!in_array($filial, $usuario['filiais'], true)) {
            throw new \DomainException('Filial nao liberada para o seu usuario.');
        }
        return $filial;
    }

    /** GET /v1/verbas-flex/api-status?filial= : testa a API Mercos agora, registra no historico e traz os saldos dos gestores */
    public function apiStatus(Request $request, Response $response): Response
    {
        try {
            $filial = $this->filialLiberada($request);
            $r = (new \Nutricional\Services\FlexApiMonitor($this->pdo))->testar($filial, 'manual');

            $gestores = [];
            try {
                $stmt = $this->pdo->prepare('SELECT gestor_id, nome, valor_minimo, ativo, ultima_verificacao, falhas_seguidas, ultimo_erro FROM flex_minimo_config WHERE filial = ? ORDER BY nome');
                $stmt->execute([$filial]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                    $saldo = $r['saldos'][(int)$g['gestor_id']]['saldo_atual'] ?? null;
                    $g['saldo'] = $saldo === null ? null : (float)$saldo;
                    $g['abaixo_minimo'] = $g['saldo'] !== null && $g['saldo'] < (float)$g['valor_minimo'];
                    $gestores[] = $g;
                }
            } catch (\Throwable $e) {
                error_log('[verbas-flex] gestores do monitor: ' . $e->getMessage());
            }

            return $this->json($response, [
                'success' => true,
                'filial' => $filial,
                'ok' => $r['ok'],
                'http' => $r['http'],
                'ms' => $r['ms'],
                'erro' => $r['erro'],
                'testado_em' => date('c'),
                'gestores' => $gestores,
            ]);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao testar a API: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/verbas-flex/api-historico?filial=&horas=24 */
    public function apiHistorico(Request $request, Response $response): Response
    {
        try {
            $filial = $this->filialLiberada($request);
            $horas = max(1, min(168, (int)($request->getQueryParams()['horas'] ?? 24)));
            $h = (new \Nutricional\Services\FlexApiMonitor($this->pdo))->historico($filial, $horas);
            return $this->json($response, ['success' => true, 'filial' => $filial] + $h);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar historico: ' . $e->getMessage(), 500);
        }
    }

    /** GET /v1/verbas-flex/resumo : cards, comparativo com periodo anterior, serie mensal e ranking */
    public function resumo(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $f = $this->filtros($request, $usuario);
            $f['representante'] = 0;

            $soma = function (array $ff) use ($usuario): array {
                [$d, $pd] = $this->baseDebitos($ff, $usuario);
                [$c, $pc] = $this->baseCreditos($ff);
                return [
                    'debitos' => (float)$this->consultar('SELECT COALESCE(SUM(lfb.valor_tirado),0) v' . $d, $pd)[0]['v'],
                    'creditos' => (float)$this->consultar('SELECT COALESCE(SUM(ldfg.valor_emprestimo),0) v' . $c, $pc)[0]['v'],
                ];
            };

            $atual = $soma($f);

            // periodo anterior com a mesma duracao, imediatamente antes do inicio
            $dias = (int)((strtotime($f['fim']) - strtotime($f['inicio'])) / 86400) + 1;
            $ant = $f;
            $ant['fim'] = date('Y-m-d', strtotime($f['inicio'] . ' -1 day'));
            $ant['inicio'] = date('Y-m-d', strtotime($ant['fim'] . ' -' . ($dias - 1) . ' days'));
            $anterior = $soma($ant);

            // serie dos ultimos 6 meses
            $serie = [];
            for ($i = 5; $i >= 0; $i--) {
                $ini = date('Y-m-01', strtotime("first day of -$i month"));
                $fimMes = date('Y-m-t', strtotime($ini));
                $s = $soma(['filial' => $f['filial'], 'inicio' => $ini, 'fim' => $fimMes, 'representante' => 0]);
                $serie[] = ['mes' => date('m/Y', strtotime($ini))] + $s;
            }

            [$d, $pd] = $this->baseDebitos($f, $usuario);
            $ranking = $this->consultar(
                "SELECT lfb.idvendrepre AS id, vend.fantasia AS nome, SUM(lfb.valor_tirado) AS total, COUNT(*) AS qtd $d
                 GROUP BY 1, 2 ORDER BY 3 DESC LIMIT 5",
                $pd
            );

            return $this->json($response, [
                'success' => true,
                'periodo' => ['inicio' => $f['inicio'], 'fim' => $f['fim']],
                'atual' => $atual,
                'anterior' => $anterior,
                'serie' => $serie,
                'ranking' => $ranking,
            ]);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao montar resumo: ' . $e->getMessage(), 500);
        }
    }
}
