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
            'is_admin' => $isAdmin,
            'idusuario' => (int)($user['idusuario'] ?? 0),
            'filiais' => $filiais,
            'gestores' => $isAdmin ? [] : $ints((string)($row['dash_gestores'] ?? '')),
        ];
    }

    /** Valida filial e periodo; padrao = mes atual. */
    private function filtros(Request $request, array $usuario): array
    {
        $q = $request->getQueryParams();
        $filial = (int)($q['filial'] ?? 0);
        if ($filial !== 0 && !in_array($filial, $usuario['filiais'], true)) {
            throw new \DomainException('Filial nao liberada para o seu usuario.');
        }
        if ($filial === 0 && empty($usuario['filiais'])) {
            throw new \DomainException('Nenhuma filial liberada para o seu usuario.');
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
        $filiaisLiberadas = $usuario['filiais'] ?? [];
        $filialFiltro = (int)($f['filial'] ?? 0);

        $sql = " FROM log_flex_bonificacao lfb
                 JOIN nfs ON lfb.numero_nota ~ '^[0-9]+\$' AND CAST(lfb.numero_nota AS INT) = nfs.idnfs
                 JOIN cliforemp cli ON cli.idcliforemp = nfs.idcliforemp
                 JOIN cliforemp vend ON vend.idcliforemp = lfb.idvendrepre
                 LEFT JOIN cliforemp gestor ON gestor.idcliforemp = nfs.idsupervisor
                 WHERE lfb.valor <> 0
                   AND nfs.datahora >= :inicio::date AND nfs.datahora < (:fim::date + 1)";
        $p = ['inicio' => $f['inicio'], 'fim' => $f['fim']];

        if ($filialFiltro > 0) {
            $sql .= ' AND lfb.idfilial = :filial';
            $p['filial'] = $filialFiltro;
        } elseif (!empty($filiaisLiberadas)) {
            $sql .= ' AND lfb.idfilial IN (' . implode(',', array_map('intval', $filiaisLiberadas)) . ')';
        }

        if ($usuario['gestores'] !== []) {
            $sql .= ' AND nfs.idsupervisor IN (' . implode(',', $usuario['gestores']) . ')';
        }
        if ($comRepresentante && $f['representante'] > 0) {
            $sql .= ' AND lfb.idvendrepre = :rep';
            $p['rep'] = $f['representante'];
        }
        return [$sql, $p];
    }

    private function baseCreditos(array $f, array $usuario = []): array
    {
        $filiaisLiberadas = $usuario['filiais'] ?? [];
        $filialFiltro = (int)($f['filial'] ?? 0);

        $sql = ' FROM log_debito_flex_gestor ldfg WHERE ldfg.data >= :inicio::date AND ldfg.data <= :fim::date';
        $p = ['inicio' => $f['inicio'], 'fim' => $f['fim']];

        if ($filialFiltro > 0) {
            $sql .= ' AND ldfg.idfilial = :filial';
            $p['filial'] = $filialFiltro;
        } elseif (!empty($filiaisLiberadas)) {
            $sql .= ' AND ldfg.idfilial IN (' . implode(',', array_map('intval', $filiaisLiberadas)) . ')';
        }

        return [$sql, $p];
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
                if (count($filiais) > 1) {
                    array_unshift($filiais, ['idfilial' => 0, 'razao' => 'Todas as Filiais Liberadas']);
                }
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
                        lfb.valor_tirado AS valor_coberto_gestor, ABS(lfb.valor) AS valor_tirado, lfb.valor $from
                 ORDER BY nfs.datahora DESC, lfb.id DESC LIMIT " . $f['limite'],
                $p
            );
            $tot = $this->consultar('SELECT COALESCE(SUM(ABS(lfb.valor)),0) AS total, COUNT(*) AS qtd' . $from, $p)[0];

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
                    'debitos' => (float)$this->consultar('SELECT COALESCE(SUM(ABS(lfb.valor)),0) v' . $d, $pd)[0]['v'],
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
                "SELECT lfb.idvendrepre AS id, vend.fantasia AS nome,
                        ABS(SUM(lfb.valor)) AS total,
                        COUNT(*) AS qtd,
                        ABS(MIN(lfb.valor)) AS maior_retirada
                 $d
                 GROUP BY 1, 2
                 ORDER BY total DESC",
                $pd
            );

            $totalDeb = abs((float)($atual['debitos'] ?? 0));
            foreach ($ranking as &$rk) {
                $rk['total'] = (float)$rk['total'];
                $rk['qtd'] = (int)$rk['qtd'];
                $rk['maior_retirada'] = (float)$rk['maior_retirada'];
                $rk['percentual'] = $totalDeb > 0 ? round(($rk['total'] / $totalDeb) * 100, 1) : 0.0;
            }

            // Estatísticas detalhadas
            $statsDebitos = $this->consultar(
                "SELECT COUNT(*) AS qtd,
                        ABS(COALESCE(MIN(lfb.valor), 0)) AS maior_debito,
                        ABS(COALESCE(AVG(lfb.valor), 0)) AS ticket_medio
                 $d",
                $pd
            )[0] ?? ['qtd' => 0, 'maior_debito' => 0, 'ticket_medio' => 0];

            [$c, $pc] = $this->baseCreditos($f);
            $statsCreditos = $this->consultar(
                "SELECT COUNT(*) AS qtd,
                        COALESCE(MAX(ldfg.valor_emprestimo), 0) AS maior_credito,
                        COALESCE(AVG(ldfg.valor_emprestimo), 0) AS media_credito
                 $c AND ldfg.valor_emprestimo > 0",
                $pc
            )[0] ?? ['qtd' => 0, 'maior_credito' => 0, 'media_credito' => 0];

            // Detalhe da maior retirada única
            $detalheMaiorDebito = $this->consultar(
                "SELECT ABS(lfb.valor) AS valor,
                        lfb.numero_nota AS nota,
                        nfs.datahora AS data,
                        cli.fantasia AS cliente,
                        vend.idcliforemp AS idrepresentante,
                        vend.fantasia AS representante
                 $d
                 ORDER BY ABS(lfb.valor) DESC, nfs.datahora DESC
                 LIMIT 1",
                $pd
            )[0] ?? null;
            if ($detalheMaiorDebito) {
                $detalheMaiorDebito['valor'] = (float)$detalheMaiorDebito['valor'];
            }

            // Detalhe do maior crédito/empréstimo
            $detalheMaiorCredito = $this->consultar(
                "SELECT ldfg.valor_emprestimo AS valor,
                        ldfg.motivo,
                        ldfg.data
                 $c AND ldfg.valor_emprestimo > 0
                 ORDER BY ldfg.valor_emprestimo DESC
                 LIMIT 1",
                $pc
            )[0] ?? null;
            if ($detalheMaiorCredito) {
                $detalheMaiorCredito['valor'] = (float)$detalheMaiorCredito['valor'];
            }

            // Rankings de Transferências de Verba (Mercos Log)
            $rankingTransferenciasEnviadas = [];
            $rankingTransferenciasRecebidas = [];
            $balancoEquipe = [];
            try {
                $filialFiltro = (int)($f['filial'] ?? 0);
                $filiaisLiberadas = $usuario['filiais'] ?? [];
                $whereFilialTransf = $filialFiltro > 0 ? "filial = :filial" : ("filial IN (" . implode(',', array_map('intval', $filiaisLiberadas)) . ")");

                $pTransf = [
                    'inicio' => $f['inicio'],
                    'fim' => $f['fim'],
                ];
                if ($filialFiltro > 0) {
                    $pTransf['filial'] = $filialFiltro;
                }

                $rankingTransferenciasEnviadas = $this->consultar(
                    "SELECT colaborador_de AS id,
                            COALESCE(NULLIF(TRIM(nome_de), ''), 'ID ' || colaborador_de) AS nome,
                            COUNT(*) AS qtd,
                            COALESCE(SUM(valor), 0) AS total,
                            COALESCE(MAX(valor), 0) AS maior_operacao
                     FROM transferencias_flex_mercos_log
                     WHERE $whereFilialTransf AND sucesso = true
                       AND data_transferencia >= :inicio::date AND data_transferencia < (:fim::date + 1)
                     GROUP BY colaborador_de, nome_de
                     ORDER BY total DESC
                     LIMIT 50",
                    $pTransf
                );
                foreach ($rankingTransferenciasEnviadas as &$rtc) {
                    $rtc['qtd'] = (int)$rtc['qtd'];
                    $rtc['total'] = (float)$rtc['total'];
                    $rtc['maior_operacao'] = (float)$rtc['maior_operacao'];
                }
                unset($rtc);

                $rankingTransferenciasRecebidas = $this->consultar(
                    "SELECT colaborador_para AS id,
                            COALESCE(NULLIF(TRIM(nome_para), ''), 'ID ' || colaborador_para) AS nome,
                            COUNT(*) AS qtd,
                            COALESCE(SUM(valor), 0) AS total,
                            COALESCE(MAX(valor), 0) AS maior_operacao
                     FROM transferencias_flex_mercos_log
                     WHERE $whereFilialTransf AND sucesso = true
                       AND data_transferencia >= :inicio::date AND data_transferencia < (:fim::date + 1)
                     GROUP BY colaborador_para, nome_para
                     ORDER BY total DESC
                     LIMIT 50",
                    $pTransf
                );
                foreach ($rankingTransferenciasRecebidas as &$rtr) {
                    $rtr['qtd'] = (int)$rtr['qtd'];
                    $rtr['total'] = (float)$rtr['total'];
                    $rtr['maior_operacao'] = (float)$rtr['maior_operacao'];
                }
                unset($rtr);

                // Balanço Consolidado da Equipe
                $mapEquipe = [];
                foreach ($ranking as $rk) {
                    $k = 'k_' . $rk['id'];
                    $mapEquipe[$k] = [
                        'id' => $rk['id'],
                        'nome' => $rk['nome'],
                        'bonificacoes' => (float)$rk['total'],
                        'qtd_bonificacoes' => (int)$rk['qtd'],
                        'transferido_enviado' => 0.0,
                        'qtd_enviado' => 0,
                        'transferido_cedido' => 0.0,
                        'qtd_cedido' => 0,
                        'transferido_recebido' => 0.0,
                        'qtd_recebido' => 0,
                    ];
                }
                foreach ($rankingTransferenciasEnviadas as $tc) {
                    $k = 'k_' . $tc['id'];
                    if (!isset($mapEquipe[$k])) {
                        $mapEquipe[$k] = [
                            'id' => $tc['id'],
                            'nome' => $tc['nome'],
                            'bonificacoes' => 0.0,
                            'qtd_bonificacoes' => 0,
                            'transferido_enviado' => 0.0,
                            'qtd_enviado' => 0,
                            'transferido_cedido' => 0.0,
                            'qtd_cedido' => 0,
                            'transferido_recebido' => 0.0,
                            'qtd_recebido' => 0,
                        ];
                    }
                    $mapEquipe[$k]['transferido_enviado'] = (float)$tc['total'];
                    $mapEquipe[$k]['qtd_enviado'] = (int)$tc['qtd'];
                    $mapEquipe[$k]['transferido_cedido'] = (float)$tc['total'];
                    $mapEquipe[$k]['qtd_cedido'] = (int)$tc['qtd'];
                }
                foreach ($rankingTransferenciasRecebidas as $tr) {
                    $k = 'k_' . $tr['id'];
                    if (!isset($mapEquipe[$k])) {
                        $mapEquipe[$k] = [
                            'id' => $tr['id'],
                            'nome' => $tr['nome'],
                            'bonificacoes' => 0.0,
                            'qtd_bonificacoes' => 0,
                            'transferido_enviado' => 0.0,
                            'qtd_enviado' => 0,
                            'transferido_cedido' => 0.0,
                            'qtd_cedido' => 0,
                            'transferido_recebido' => 0.0,
                            'qtd_recebido' => 0,
                        ];
                    }
                    $mapEquipe[$k]['transferido_recebido'] = (float)$tr['total'];
                    $mapEquipe[$k]['qtd_recebido'] = (int)$tr['qtd'];
                }

                foreach ($mapEquipe as $item) {
                    $saidas = $item['bonificacoes'] + $item['transferido_enviado'];
                    $entradas = $item['transferido_recebido'];
                    $item['total_saidas'] = $saidas;
                    $item['total_entradas'] = $entradas;
                    $item['saldo_liquido'] = $entradas - $saidas;
                    $balancoEquipe[] = $item;
                }
                usort($balancoEquipe, fn($a, $b) => ($b['total_saidas'] <=> $a['total_saidas']));
            } catch (\Throwable $e) {
                error_log('[verbas-flex] rankings de transferencias: ' . $e->getMessage());
            }

            // Série diária no período para gráficos de linha/área
            $serieDiaria = $this->consultar(
                "SELECT date_trunc('day', nfs.datahora)::date AS dia,
                        ABS(SUM(lfb.valor)) AS debito,
                        COUNT(*) AS qtd
                 $d
                 GROUP BY 1
                 ORDER BY 1",
                $pd
            );

            return $this->json($response, [
                'success' => true,
                'periodo' => ['inicio' => $f['inicio'], 'fim' => $f['fim']],
                'atual' => $atual,
                'anterior' => $anterior,
                'serie' => $serie,
                'serie_diaria' => $serieDiaria,
                'ranking' => $ranking,
                'ranking_transferencias_enviadas' => $rankingTransferenciasEnviadas,
                'ranking_transferencias_cedidas' => $rankingTransferenciasEnviadas,
                'ranking_transferencias_recebidas' => $rankingTransferenciasRecebidas,
                'balanco_equipe' => $balancoEquipe,
                'detalhe_maior_debito' => $detalheMaiorDebito,
                'detalhe_maior_credito' => $detalheMaiorCredito,
                'estatisticas' => [
                    'qtd_debitos' => (int)$statsDebitos['qtd'],
                    'ticket_medio' => (float)$statsDebitos['ticket_medio'],
                    'maior_debito' => (float)$statsDebitos['maior_debito'],
                    'qtd_creditos' => (int)$statsCreditos['qtd'],
                    'maior_credito' => (float)$statsCreditos['maior_credito'],
                    'media_credito' => (float)$statsCreditos['media_credito'],
                    'total_representantes' => count($ranking),
                ],
            ]);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao montar resumo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retorna a lista de colaboradores (representantes e gestores) disponíveis para auditoria,
     * respeitando rigorosamente a hierarquia de permissões do usuário logado.
     * GET /v1/verbas-flex/colaboradores-auditoria
     */
    public function colaboradoresAuditoria(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $params = $request->getQueryParams();
            $filial = (int)($params['filial'] ?? 0);

            if ($filial === 0 && !empty($usuario['filiais'])) {
                $filial = $usuario['filiais'][0];
            }
            if ($filial !== 0 && !in_array($filial, $usuario['filiais'], true)) {
                return $this->erro($response, 'Filial não liberada para o seu usuário.', 403);
            }

            // 1. Busca saldos ao vivo na API da Mercos para a filial
            $saldosMercos = [];
            try {
                $mercosApi = new \Nutricional\Services\MercosFlexService($filial);
                $saldosMercos = $mercosApi->saldos(false);
            } catch (\Throwable $e) {
                error_log(sprintf('[verbas-flex] falha ao buscar saldos mercos f%d: %s', $filial, $e->getMessage()));
            }

            // 2. Busca representantes ativos no ERP vinculados à filial
            $sql = "
                SELECT DISTINCT ON (um.idcolaborador)
                    um.idcolaborador AS id,
                    COALESCE(NULLIF(TRIM(c.fantasia), ''), NULLIF(TRIM(c.razao), ''), um.nome, 'ID ' || um.idcolaborador) AS nome,
                    um.username,
                    um.cdgestor,
                    um.gestor,
                    um.filial
                FROM usuarios_mercos um
                INNER JOIN cliforemp c ON c.idcliforemp = um.idcolaborador
                WHERE um.filial = :filial
                  AND um.idcolaborador IS NOT NULL
                  AND COALESCE(c.inativo, 'N') <> 'S'
            ";

            $p = ['filial' => $filial];

            if (!$usuario['is_admin'] && !empty($usuario['gestores'])) {
                $gestoresCsv = implode(',', array_map('intval', $usuario['gestores']));
                $sql .= " AND (um.cdgestor IN ($gestoresCsv) OR um.idcolaborador IN ($gestoresCsv))";
            }

            $sql .= " ORDER BY um.idcolaborador, nome";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($p);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Filtra apenas os que possuem conta flex ATIVA na Mercos (não excluídos)
            $colaboradoresAtivos = [];
            $saldoTotalEquipe = 0.0;

            foreach ($rows as $r) {
                $id = (int)$r['id'];
                if (!empty($saldosMercos)) {
                    if (!isset($saldosMercos[$id])) {
                        continue; // Sem conta flex na API Mercos
                    }
                    if (!empty($saldosMercos[$id]['excluido'])) {
                        continue; // Excluído na API Mercos
                    }
                    $saldo = (float)($saldosMercos[$id]['saldo_atual'] ?? 0.0);
                } else {
                    $saldo = 0.0;
                }

                $r['id'] = $id;
                $r['saldo_mercos'] = $saldo;
                $colaboradoresAtivos[] = $r;
                $saldoTotalEquipe += $saldo;
            }

            // 4. Adiciona também o gestor da filial se não estiver na lista
            $gestoresConfig = [
                1 => ['id' => 12335, 'nome' => 'TIAGO (MOV) - Gestor Filial 1'],
                6 => ['id' => 15816, 'nome' => 'TALES BINDE - Gestor Filial 6']
            ];
            if (isset($gestoresConfig[$filial])) {
                $gId = $gestoresConfig[$filial]['id'];
                $jaExiste = false;
                foreach ($colaboradoresAtivos as $ca) {
                    if ($ca['id'] === $gId) {
                        $jaExiste = true;
                        break;
                    }
                }
                if (!$jaExiste && isset($saldosMercos[$gId])) {
                    $saldoG = (float)($saldosMercos[$gId]['saldo_atual'] ?? 0.0);
                    $colaboradoresAtivos[] = [
                        'id' => $gId,
                        'nome' => $gestoresConfig[$filial]['nome'],
                        'username' => 'GESTOR',
                        'cdgestor' => $gId,
                        'gestor' => 'Gestor Geral',
                        'filial' => $filial,
                        'saldo_mercos' => $saldoG,
                        'is_gestor' => true
                    ];
                }
            }

            usort($colaboradoresAtivos, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));

            return $this->json($response, [
                'success' => true,
                'filial' => $filial,
                'total_ativos_mercos' => count($colaboradoresAtivos),
                'saldo_total_equipe' => round($saldoTotalEquipe, 2),
                'colaboradores' => $colaboradoresAtivos
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar colaboradores: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Auditoria & Extrato Espelho Mercos:
     * Unifica em ordem cronológica reversa:
     * 1. Movimentações Manuais do Portal (com operador)
     * 2. Transferências entre colaboradores (quem enviou, quem recebeu, operador)
     * 3. Bonificações por Nota Fiscal faturada (débito rep + débito gestor quando cobriu)
     * 4. Aportes de Flex Mínimo / Empréstimos concedidos ao caixa do gestor
     *
     * GET /v1/verbas-flex/auditoria-espelho
     */
    public function auditoriaEspelho(Request $request, Response $response): Response
    {
        try {
            $usuario = $this->usuario($request);
            $f = $this->filtros($request, $usuario);
            $params = $request->getQueryParams();

            $filial = $f['filial'];
            if ($filial === 0 && !empty($usuario['filiais'])) {
                $filial = $usuario['filiais'][0];
            }

            $colaboradorId = (int)($params['colaborador'] ?? 0);
            $origemFiltro = trim((string)($params['origem'] ?? 'todas'));
            $naturezaFiltro = trim((string)($params['natureza'] ?? 'todos'));
            $busca = trim((string)($params['q'] ?? ''));
            $limite = max(10, min(1000, (int)($params['limite'] ?? 300)));

            $filtroColabsGestor = [];
            if (!$usuario['is_admin'] && !empty($usuario['gestores'])) {
                $gestoresCsv = implode(',', array_map('intval', $usuario['gestores']));
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT um.idcolaborador 
                    FROM usuarios_mercos um 
                    WHERE um.filial = :filial 
                      AND (um.cdgestor IN ($gestoresCsv) OR um.idcolaborador IN ($gestoresCsv))
                ");
                $stmt->execute(['filial' => $filial]);
                $filtroColabsGestor = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                foreach ($usuario['gestores'] as $g) {
                    $filtroColabsGestor[] = (int)$g;
                }
                $filtroColabsGestor = array_values(array_unique(array_filter($filtroColabsGestor)));

                if ($colaboradorId > 0 && !in_array($colaboradorId, $filtroColabsGestor, true)) {
                    return $this->erro($response, 'Você não tem permissão para visualizar o histórico deste colaborador.', 403);
                }
            }

            $inicioTs = $f['inicio'] . ' 00:00:00';
            $fimTs = $f['fim'] . ' 23:59:59';
            $inicioDate = $f['inicio'];
            $fimDate = $f['fim'];

            $sql = "
            WITH extrato AS (
                -- 1. Manuais Portal
                SELECT 
                    m.id,
                    m.data_movimentacao AS datahora,
                    'MANUAL_PORTAL' AS origem,
                    'Manual (Portal)' AS origem_rotulo,
                    COALESCE(m.usuario, 'PORTAL') AS operador,
                    m.idcolaborador,
                    COALESCE(m.nome_colaborador, 'Colab ' || m.idcolaborador) AS colaborador,
                    m.tipo_movimentacao AS tipo,
                    m.valor_solicitado AS valor,
                    m.saldo_anterior_rep AS saldo_anterior,
                    m.saldo_novo_rep AS saldo_novo,
                    m.usou_gestor,
                    m.idgestor,
                    m.nome_gestor,
                    m.valor_gestor,
                    m.observacao,
                    NULL::text AS documento,
                    m.detalhes_json::text AS detalhes
                FROM movimentacoes_flex_mercos_log m
                WHERE m.filial = :filial AND m.data_movimentacao >= :inicio_ts AND m.data_movimentacao <= :fim_ts

                UNION ALL

                -- 2A. Transferências - Saída (Quem enviou)
                SELECT 
                    t.id,
                    t.data_transferencia AS datahora,
                    'TRANSFERENCIA' AS origem,
                    'Transferência Enviada' AS origem_rotulo,
                    COALESCE(t.usuario, 'PORTAL') AS operador,
                    t.colaborador_de AS idcolaborador,
                    COALESCE(t.nome_de, 'Colab ' || t.colaborador_de) AS colaborador,
                    'DEBITO' AS tipo,
                    t.valor,
                    t.valor_saldo_anterior_de AS saldo_anterior,
                    t.valor_novo_saldo_de AS saldo_novo,
                    false AS usou_gestor,
                    t.colaborador_para AS idgestor,
                    t.nome_para AS nome_gestor,
                    0 AS valor_gestor,
                    'Enviado para ' || COALESCE(t.nome_para, 'colab ' || t.colaborador_para) || ' — Motivo: ' || COALESCE(t.motivo, 'Sem motivo') AS observacao,
                    NULL::text AS documento,
                    json_build_object('colaborador_para', t.colaborador_para, 'nome_para', t.nome_para, 'saldo_anterior', t.valor_saldo_anterior_de, 'saldo_novo', t.valor_novo_saldo_de)::text AS detalhes
                FROM transferencias_flex_mercos_log t
                WHERE t.filial = :filial AND t.sucesso = true AND t.data_transferencia >= :inicio_ts AND t.data_transferencia <= :fim_ts

                UNION ALL

                -- 2B. Transferências - Entrada (Quem recebeu)
                SELECT 
                    t.id,
                    t.data_transferencia AS datahora,
                    'TRANSFERENCIA' AS origem,
                    'Transferência Recebida' AS origem_rotulo,
                    COALESCE(t.usuario, 'PORTAL') AS operador,
                    t.colaborador_para AS idcolaborador,
                    COALESCE(t.nome_para, 'Colab ' || t.colaborador_para) AS colaborador,
                    'CREDITO' AS tipo,
                    t.valor,
                    t.valor_saldo_anterior_para AS saldo_anterior,
                    t.valor_novo_saldo_para AS saldo_novo,
                    false AS usou_gestor,
                    t.colaborador_de AS idgestor,
                    t.nome_de AS nome_gestor,
                    0 AS valor_gestor,
                    'Recebido de ' || COALESCE(t.nome_de, 'colab ' || t.colaborador_de) || ' — Motivo: ' || COALESCE(t.motivo, 'Sem motivo') AS observacao,
                    NULL::text AS documento,
                    json_build_object('colaborador_de', t.colaborador_de, 'nome_de', t.nome_de, 'saldo_anterior', t.valor_saldo_anterior_para, 'saldo_novo', t.valor_novo_saldo_para)::text AS detalhes
                FROM transferencias_flex_mercos_log t
                WHERE t.filial = :filial AND t.sucesso = true AND t.data_transferencia >= :inicio_ts AND t.data_transferencia <= :fim_ts

                UNION ALL

                -- 3A. Bonificações - Representante
                SELECT 
                    b.id,
                    b.data_criacao AS datahora,
                    'BONIFICACAO_CRON' AS origem,
                    'Bonificação (Nota Fiscal)' AS origem_rotulo,
                    'CRON SISTEMA' AS operador,
                    b.idvendrepre AS idcolaborador,
                    COALESCE(v.fantasia, v.razao, 'Rep ' || b.idvendrepre) AS colaborador,
                    'DEBITO' AS tipo,
                    ABS(b.valor) AS valor,
                    NULL::numeric AS saldo_anterior,
                    NULL::numeric AS saldo_novo,
                    (b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0) AS usou_gestor,
                    CASE WHEN b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0 THEN b.codgestor ELSE NULL END AS idgestor,
                    CASE WHEN b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0 THEN COALESCE(g.fantasia, g.razao, 'Gestor ' || b.codgestor) ELSE NULL END AS nome_gestor,
                    ABS(COALESCE(b.valor_tirado, 0)) AS valor_gestor,
                    b.observacao,
                    b.numero_nota AS documento,
                    json_build_object('numero_nota', b.numero_nota, 'valor_nota', b.valor, 'codgestor', b.codgestor, 'gestor', g.fantasia, 'valor_tirado_gestor', b.valor_tirado)::text AS detalhes
                FROM log_flex_bonificacao b
                LEFT JOIN cliforemp v ON v.idcliforemp = b.idvendrepre
                LEFT JOIN cliforemp g ON g.idcliforemp = b.codgestor
                WHERE b.idfilial = :filial AND b.data_criacao >= :inicio_ts AND b.data_criacao <= :fim_ts

                UNION ALL

                -- 3B. Bonificações - Cobertura do Gestor (apenas quando o gestor efetivamente cobriu valor_tirado > 0)
                SELECT 
                    b.id,
                    b.data_criacao AS datahora,
                    'BONIFICACAO_GESTOR' AS origem,
                    'Cobertura de Bonificação' AS origem_rotulo,
                    'CRON SISTEMA' AS operador,
                    b.codgestor AS idcolaborador,
                    COALESCE(g.fantasia, g.razao, 'Gestor ' || b.codgestor) AS colaborador,
                    'DEBITO' AS tipo,
                    ABS(b.valor_tirado) AS valor,
                    NULL::numeric AS saldo_anterior,
                    NULL::numeric AS saldo_novo,
                    true AS usou_gestor,
                    b.codgestor AS idgestor,
                    COALESCE(g.fantasia, g.razao, 'Gestor ' || b.codgestor) AS nome_gestor,
                    ABS(b.valor_tirado) AS valor_gestor,
                    'Cobertura de bonificação do rep ' || COALESCE(v.fantasia, v.razao, '' || b.idvendrepre) || ' — Nota: ' || b.numero_nota || ' | ' || b.observacao AS observacao,
                    b.numero_nota AS documento,
                    json_build_object('numero_nota', b.numero_nota, 'valor_nota', b.valor, 'idvendrepre', b.idvendrepre, 'rep', v.fantasia)::text AS detalhes
                FROM log_flex_bonificacao b
                LEFT JOIN cliforemp v ON v.idcliforemp = b.idvendrepre
                LEFT JOIN cliforemp g ON g.idcliforemp = b.codgestor
                WHERE b.idfilial = :filial AND b.codgestor IS NOT NULL AND b.codgestor > 0
                  AND b.valor_tirado IS NOT NULL AND ABS(b.valor_tirado) > 0
                  AND b.data_criacao >= :inicio_ts AND b.data_criacao <= :fim_ts

                UNION ALL

                -- 4. Aportes Flex Mínimo ao Gestor
                SELECT 
                    a.id,
                    (a.data::text || ' 00:00:00')::timestamp AS datahora,
                    'FLEX_MINIMO' AS origem,
                    'Aporte / Flex Mínimo' AS origem_rotulo,
                    'MOTOR FLEX MÍNIMO' AS operador,
                    a.cdgestor AS idcolaborador,
                    COALESCE(g.fantasia, g.razao, 'Gestor ' || a.cdgestor) AS colaborador,
                    'CREDITO' AS tipo,
                    a.valor_emprestimo AS valor,
                    NULL::numeric AS saldo_anterior,
                    NULL::numeric AS saldo_novo,
                    false AS usou_gestor,
                    NULL::integer AS idgestor,
                    NULL::text AS nome_gestor,
                    0 AS valor_gestor,
                    a.motivo AS observacao,
                    NULL::text AS documento,
                    json_build_object('cdgestor', a.cdgestor, 'filial', a.idfilial, 'data', a.data)::text AS detalhes
                FROM log_debito_flex_gestor a
                LEFT JOIN cliforemp g ON g.idcliforemp = a.cdgestor
                WHERE a.idfilial = :filial AND a.data >= :inicio_d AND a.data <= :fim_d
            )
            ";

            $whereClauses = ["1=1"];
            $binds = [
                'filial' => $filial,
                'inicio_ts' => $inicioTs,
                'fim_ts' => $fimTs,
                'inicio_d' => $inicioDate,
                'fim_d' => $fimDate,
            ];

            if ($colaboradorId > 0) {
                $whereClauses[] = "idcolaborador = :colaborador_id";
                $binds['colaborador_id'] = $colaboradorId;
            } elseif (!empty($filtroColabsGestor)) {
                $whereClauses[] = "idcolaborador IN (" . implode(',', $filtroColabsGestor) . ")";
            }

            if ($origemFiltro === 'bonificacao') {
                $whereClauses[] = "origem IN ('BONIFICACAO_CRON', 'BONIFICACAO_GESTOR')";
            } elseif ($origemFiltro === 'manual') {
                $whereClauses[] = "origem = 'MANUAL_PORTAL'";
            } elseif ($origemFiltro === 'transferencia') {
                $whereClauses[] = "origem = 'TRANSFERENCIA'";
            } elseif ($origemFiltro === 'flex_minimo') {
                $whereClauses[] = "origem = 'FLEX_MINIMO'";
            }

            if ($naturezaFiltro === 'credito') {
                $whereClauses[] = "tipo = 'CREDITO'";
            } elseif ($naturezaFiltro === 'debito') {
                $whereClauses[] = "tipo = 'DEBITO'";
            }

            if ($busca !== '') {
                $whereClauses[] = "(colaborador ILIKE :busca OR operador ILIKE :busca OR observacao ILIKE :busca OR documento ILIKE :busca OR nome_gestor ILIKE :busca)";
                $binds['busca'] = '%' . $busca . '%';
            }

            $whereSql = implode(' AND ', $whereClauses);

            $sqlTotais = "$sql SELECT 
                COALESCE(SUM(CASE WHEN tipo = 'CREDITO' THEN valor ELSE 0 END), 0) AS total_creditos,
                COALESCE(SUM(CASE WHEN tipo = 'DEBITO' THEN valor ELSE 0 END), 0) AS total_debitos,
                COUNT(CASE WHEN tipo = 'CREDITO' THEN 1 END) AS qtd_creditos,
                COUNT(CASE WHEN tipo = 'DEBITO' THEN 1 END) AS qtd_debitos,
                COUNT(*) AS total_registros
            FROM extrato WHERE $whereSql";

            $totaisStmt = $this->pdo->prepare($sqlTotais);
            $totaisStmt->execute($binds);
            $totais = $totaisStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_creditos' => 0,
                'total_debitos' => 0,
                'qtd_creditos' => 0,
                'qtd_debitos' => 0,
                'total_registros' => 0,
            ];

            $sqlRegistros = "$sql SELECT * FROM extrato WHERE $whereSql ORDER BY datahora DESC, id DESC LIMIT :limite";
            $regStmt = $this->pdo->prepare($sqlRegistros);
            foreach ($binds as $k => $v) {
                $regStmt->bindValue($k, $v);
            }
            $regStmt->bindValue('limite', $limite, PDO::PARAM_INT);
            $regStmt->execute();
            $registros = $regStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($registros as &$r) {
                $r['id'] = (int)$r['id'];
                $r['idcolaborador'] = (int)$r['idcolaborador'];
                $r['valor'] = (float)$r['valor'];
                $r['saldo_anterior'] = $r['saldo_anterior'] !== null ? (float)$r['saldo_anterior'] : null;
                $r['saldo_novo'] = $r['saldo_novo'] !== null ? (float)$r['saldo_novo'] : null;
                $r['usou_gestor'] = (bool)$r['usou_gestor'];
                $r['idgestor'] = $r['idgestor'] !== null ? (int)$r['idgestor'] : null;
                $r['valor_gestor'] = (float)$r['valor_gestor'];
                if ($r['detalhes'] && is_string($r['detalhes'])) {
                    $r['detalhes'] = json_decode($r['detalhes'], true);
                }
            }
            unset($r);

            $totalCreditos = (float)$totais['total_creditos'];
            $totalDebitos = (float)$totais['total_debitos'];

            return $this->json($response, [
                'success' => true,
                'filtros' => [
                    'filial' => $filial,
                    'inicio' => $f['inicio'],
                    'fim' => $f['fim'],
                    'colaborador' => $colaboradorId,
                    'origem' => $origemFiltro,
                    'natureza' => $naturezaFiltro,
                    'busca' => $busca,
                    'limite' => $limite,
                ],
                'totais' => [
                    'total_creditos' => $totalCreditos,
                    'total_debitos' => $totalDebitos,
                    'saldo_liquido' => $totalCreditos - $totalDebitos,
                    'qtd_creditos' => (int)$totais['qtd_creditos'],
                    'qtd_debitos' => (int)$totais['qtd_debitos'],
                    'total_registros' => (int)$totais['total_registros'],
                ],
                'registros' => $registros,
            ]);
        } catch (\DomainException $e) {
            return $this->erro($response, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro na auditoria espelho: ' . $e->getMessage(), 500);
        }
    }
}
