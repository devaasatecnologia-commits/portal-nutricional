<?php

namespace Nutricional\Services;

use PDO;

/**
 * Motor de regras, captura automatica ERP (nfs / nfe) e gamificacao do
 * NUTRICIONAL - PROGRAMA 365 COMERCIAL (Temporada 2027 - Jiu-Jitsu).
 */
class Programa365Service
{
    private PDO $pdo;

    /** Transacoes de devolucao de venda alinhadas ao Cubo de Vendas / VendasDashboardController */
    private const TRANS_DEVOLUCAO = '8,9,15,1000,1001,1004,1005,1007,1011,1014,1015,1020,1024,1025,1031,1032,1033';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function calcularPontosDia10(float $pct): int
    {
        if ($pct >= 25.0) return 20;
        if ($pct >= 20.0) return 15;
        if ($pct >= 15.0) return 10;
        return 0;
    }

    public static function calcularPontosDia20(float $pct): int
    {
        if ($pct >= 60.0) return 20;
        if ($pct >= 55.0) return 15;
        if ($pct >= 50.0) return 10;
        return 0;
    }

    public static function calcularPontosDia30(float $pct): int
    {
        if ($pct >= 110.0) return 30;
        if ($pct >= 100.0) return 20;
        if ($pct >= 90.0) return 15;
        if ($pct >= 80.0) return 10;
        return 0;
    }

    public static function calcularPontosPositivacao(float $pct): int
    {
        if ($pct >= 70.0) return 20;
        if ($pct >= 60.0) return 15;
        if ($pct >= 50.0) return 10;
        return 0;
    }

    public static function calcularBonusMes(int $pontos): float
    {
        if ($pontos >= 110) return 1000.00;
        if ($pontos >= 100) return 750.00;
        if ($pontos >= 90)  return 500.00;
        if ($pontos >= 80)  return 250.00;
        if ($pontos >= 70)  return 150.00;
        return 0.00;
    }

    public static function calcularFaixa(int $xp): array
    {
        $faixas = [
            ['codigo' => 'BRANCA', 'nome' => 'Faixa Branca', 'min' => 0,    'max' => 287,  'cor' => '#F8FAFC', 'borda' => '#CBD5E1', 'texto' => '#1E293B'],
            ['codigo' => 'AZUL',   'nome' => 'Faixa Azul',   'min' => 288,  'max' => 575,  'cor' => '#2563EB', 'borda' => '#1D4ED8', 'texto' => '#FFFFFF'],
            ['codigo' => 'ROXA',   'nome' => 'Faixa Roxa',   'min' => 576,  'max' => 863,  'cor' => '#7C3AED', 'borda' => '#6D28D9', 'texto' => '#FFFFFF'],
            ['codigo' => 'MARROM', 'nome' => 'Faixa Marrom', 'min' => 864,  'max' => 1151, 'cor' => '#78350F', 'borda' => '#451A03', 'texto' => '#FFFFFF'],
            ['codigo' => 'PRETA',  'nome' => 'Faixa Preta',  'min' => 1152, 'max' => 1440, 'cor' => '#0F172A', 'borda' => '#E8C87E', 'texto' => '#E8C87E'],
        ];

        $atual = $faixas[0];
        $proxima = $faixas[1];
        foreach ($faixas as $idx => $f) {
            if ($xp >= $f['min']) {
                $atual = $f;
                $proxima = $faixas[$idx + 1] ?? null;
            }
        }

        if ($xp >= 1440) {
            $grau = 5; // 5o Grau - Consagracao
        } else {
            $dentro = max(0, $xp - $atual['min']);
            $grau = min(4, (int)floor($dentro / 72) + 1);
        }

        $xpRestanteProx = $proxima ? max(0, $proxima['min'] - $xp) : 0;
        return [
            'codigo' => $atual['codigo'],
            'nome' => $atual['nome'],
            'grau' => $grau,
            'cor' => $atual['cor'],
            'borda' => $atual['borda'],
            'texto' => $atual['texto'],
            'xp' => $xp,
            'xp_max' => 1440,
            'pct_jornada' => min(100, round(($xp / 1440) * 100, 1)),
            'proxima_faixa' => $proxima ? $proxima['nome'] : 'Faixa Preta 5o Grau (Consagracao)',
            'xp_falta_proxima' => $xpRestanteProx,
        ];
    }

    public static function catalogoPins(): array
    {
        return [
            ['codigo' => 'RASPAGEM_OURO',    'nome' => 'Raspagem Relampago',    'categoria' => 'Cadencia',   'icone' => 'fa-bolt',          'regra' => 'Bater 25%+ da meta ate o Dia 10 (+20 pts)'],
            ['codigo' => 'GUARDA_PASSADA',   'nome' => 'Passagem de Guarda',    'categoria' => 'Cadencia',   'icone' => 'fa-shield-halved', 'regra' => 'Bater 60%+ da meta ate o Dia 20 (+20 pts)'],
            ['codigo' => 'FINALIZADOR_100',  'nome' => 'Finalizador 100%',      'categoria' => 'Cadencia',   'icone' => 'fa-trophy',        'regra' => 'Fechar o mes com 100%+ da meta liquida'],
            ['codigo' => 'FINALIZADOR_110',  'nome' => 'Ippon 110%+',           'categoria' => 'Cadencia',   'icone' => 'fa-crown',         'regra' => 'Superar 110%+ da meta no Round (+30 pts)'],
            ['codigo' => 'CONTROLE_70',      'nome' => 'Controle Lateral 70%',  'categoria' => 'Carteira',   'icone' => 'fa-users',         'regra' => 'Positivar 70%+ da carteira efetiva (piso 60)'],
            ['codigo' => 'KIMONO_MASTER',    'nome' => 'Mestre do Kimono',      'categoria' => 'Desafios',   'icone' => 'fa-award',         'regra' => 'Concluir Desafio 01 (Kimono) no Round'],
            ['codigo' => 'ARMLOCK_PRO',      'nome' => 'Armlock Cirurgico',     'categoria' => 'Desafios',   'icone' => 'fa-crosshairs',    'regra' => 'Concluir Desafio 02 (Armlock) no Round'],
            ['codigo' => 'RNC_REATIVADOR',   'nome' => 'Mata-Leao RNC',         'categoria' => 'Desafios',   'icone' => 'fa-fire',          'regra' => 'Concluir Desafio 03 (Reativacao/Abertura) no Round'],
            ['codigo' => 'ROUND_PERFEITO',   'nome' => 'Round Perfeito (120)',  'categoria' => 'Elite',      'icone' => 'fa-gem',           'regra' => 'Atingir os 120 pontos maximos em um Round'],
            ['codigo' => 'FAIXA_AZUL',       'nome' => 'Graduacao Azul',        'categoria' => 'Graduacao',  'icone' => 'fa-ribbon',        'regra' => 'Alcancar 288 XP acumulados na temporada'],
            ['codigo' => 'FAIXA_ROXA',       'nome' => 'Graduacao Roxa',        'categoria' => 'Graduacao',  'icone' => 'fa-ribbon',        'regra' => 'Alcancar 576 XP acumulados na temporada'],
            ['codigo' => 'FAIXA_MARROM',     'nome' => 'Graduacao Marrom',      'categoria' => 'Graduacao',  'icone' => 'fa-ribbon',        'regra' => 'Alcancar 864 XP acumulados na temporada'],
            ['codigo' => 'FAIXA_PRETA',      'nome' => 'Faixa Preta T27',       'categoria' => 'Graduacao',  'icone' => 'fa-medal',         'regra' => 'Alcancar 1.152 XP acumulados na temporada'],
            ['codigo' => 'CONSAGRACAO_1440', 'nome' => 'Consagracao 1.440 XP',  'categoria' => 'Elite',      'icone' => 'fa-star',          'regra' => 'Atingir 1.440 XP na Temporada 2027'],
        ];
    }

    public static function catalogoPremiacaoAnual(): array
    {
        return [
            1 => 10000.00,
            2 => 7000.00,
            3 => 5000.00,
            4 => 3000.00,
            5 => 2000.00,
            6 => 1000.00,
        ];
    }

    public function getCampanhaAtiva(?int $campanhaId = null): ?array
    {
        if ($campanhaId) {
            $st = $this->pdo->prepare("SELECT * FROM p365_campanhas WHERE id = ?");
            $st->execute([$campanhaId]);
        } else {
            $st = $this->pdo->query("SELECT * FROM p365_campanhas WHERE ativo = TRUE ORDER BY temporada_ano DESC, id DESC LIMIT 1");
        }
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Calcula ou recupera o desempenho de todos os participantes em um Round (ano/mes).
     * Se o round estiver fechado para o participante, usa o snapshot p365_round_fechamento.
     * Caso contrario, captura ao vivo do ERP (nfs + nfe) com filtros indexados.
     */
    public function calcularRound(int $idcampanha, int $ano, int $mes, ?array $filtroParticipanteIds = null): array
    {
        $campanha = $this->getCampanhaAtiva($idcampanha);
        if (!$campanha) {
            return [];
        }
        $pisoCarteira = max(1, (int)($campanha['piso_carteira_positivacao'] ?? 60));

        // 1. Carregar participantes ativos
        $sqlP = "
            SELECT p.*, e.nome AS equipe_nome, e.cor AS equipe_cor, e.idgestor AS equipe_idgestor
            FROM p365_participantes p
            LEFT JOIN p365_equipes e ON e.id = p.idequipe
            WHERE p.idcampanha = :c AND p.ativo = TRUE
        ";
        $paramsP = ['c' => $idcampanha];
        if ($filtroParticipanteIds !== null) {
            if (empty($filtroParticipanteIds)) return [];
            $in = implode(',', array_map('intval', $filtroParticipanteIds));
            $sqlP .= " AND p.id IN ($in)";
        }
        $sqlP .= " ORDER BY p.nome_exibicao";
        $stP = $this->pdo->prepare($sqlP);
        $stP->execute($paramsP);
        $participantes = $stP->fetchAll(PDO::FETCH_ASSOC);
        if (!$participantes) return [];

        $partIds = array_map(fn($p) => (int)$p['id'], $participantes);
        $vendIds = array_values(array_unique(array_map(fn($p) => (int)$p['idvendedor'], $participantes)));

        // 2. Snapshots ja fechados
        $stF = $this->pdo->prepare("SELECT * FROM p365_round_fechamento WHERE idcampanha = ? AND ano = ? AND mes = ? AND fechado = TRUE");
        $stF->execute([$idcampanha, $ano, $mes]);
        $fechados = [];
        foreach ($stF->fetchAll(PDO::FETCH_ASSOC) as $rf) {
            $fechados[(int)$rf['idparticipante']] = $rf;
        }

        // 3. Metas do Round
        $stM = $this->pdo->prepare("SELECT * FROM p365_metas_round WHERE idcampanha = ? AND ano = ? AND mes = ?");
        $stM->execute([$idcampanha, $ano, $mes]);
        $metas = [];
        foreach ($stM->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $metas[(int)$m['idparticipante']] = $m;
        }

        // 4. Desafios do Mes (1, 2, 3)
        $stD = $this->pdo->prepare("SELECT * FROM p365_desafios_mes WHERE idcampanha = ? AND ano = ? AND mes = ? AND ativo = TRUE ORDER BY numero_desafio");
        $stD->execute([$idcampanha, $ano, $mes]);
        $desafios = [];
        foreach ($stD->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $d['itens'] = json_decode($d['itens_json'] ?? '[]', true) ?: [];
            $desafios[(int)$d['numero_desafio']] = $d;
        }

        // 5. Lancamentos Extras / Ajustes do Round
        $stE = $this->pdo->prepare("
            SELECT idparticipante,
                   COALESCE(SUM(pontos), 0) AS pts_extras,
                   COALESCE(SUM(valor_ajuste), 0) AS valor_ajuste
            FROM p365_lancamentos_extras
            WHERE idcampanha = ? AND ano = ? AND mes = ?
            GROUP BY idparticipante
        ");
        $stE->execute([$idcampanha, $ano, $mes]);
        $extras = [];
        foreach ($stE->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            $extras[(int)$ex['idparticipante']] = [
                'pts' => (int)$ex['pts_extras'],
                'valor' => (float)$ex['valor_ajuste'],
            ];
        }

        // 6. Captura ERP ao vivo apenas para participantes cujo round NAO esta fechado
        $abertosVendIds = [];
        foreach ($participantes as $p) {
            if (!isset($fechados[(int)$p['id']])) {
                $abertosVendIds[] = (int)$p['idvendedor'];
            }
        }
        $abertosVendIds = array_values(array_unique($abertosVendIds));

        $erpVendas = [];
        $erpDevolucoes = [];
        $erpDesafios = [];
        if (!empty($abertosVendIds)) {
            $di = sprintf('%04d-%02d-01', $ano, $mes);
            $d10 = sprintf('%04d-%02d-10', $ano, $mes);
            $d20 = sprintf('%04d-%02d-20', $ano, $mes);
            $df = date('Y-m-t', strtotime($di));
            $vendIn = implode(',', $abertosVendIds);

            // Faturamento bruto por marco (1..10, 1..20, 1..fim) e clientes positivados
            $sqlNfs = "
                SELECT n.idvendrepre AS idvendedor,
                       COALESCE(SUM(CASE WHEN n.dataemissao <= :d10 THEN n.valortotalnota ELSE 0 END), 0)::float AS fat_d10,
                       COALESCE(SUM(CASE WHEN n.dataemissao <= :d20 THEN n.valortotalnota ELSE 0 END), 0)::float AS fat_d20,
                       COALESCE(SUM(n.valortotalnota), 0)::float AS fat_d30,
                       COUNT(DISTINCT n.idcliforemp)::int AS clientes_pos
                FROM nfs n
                WHERE n.status = 2
                  AND n.idtransacao = 1
                  AND n.dataemissao BETWEEN :di AND :df
                  AND n.idvendrepre IN ($vendIn)
                GROUP BY n.idvendrepre
            ";
            $stNfs = $this->pdo->prepare($sqlNfs);
            $stNfs->execute(['di' => $di, 'd10' => $d10, 'd20' => $d20, 'df' => $df]);
            foreach ($stNfs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $erpVendas[(int)$r['idvendedor']] = $r;
            }

            // Devolucoes no mes (nfe status=2, status_devolucao=1, idtransacao IN TRANS_DEVOLUCAO)
            $sqlDev = "
                SELECT n.idvendrepre AS idvendedor,
                       COALESCE(SUM(n.valortotalnota), 0)::float AS dev_mes
                FROM nfe n
                WHERE n.status = 2
                  AND COALESCE(n.status_devolucao, 0) = 1
                  AND n.idtransacao IN (" . self::TRANS_DEVOLUCAO . ")
                  AND n.dataemissao BETWEEN :di AND :df
                  AND n.idvendrepre IN ($vendIn)
                GROUP BY n.idvendrepre
            ";
            $stDev = $this->pdo->prepare($sqlDev);
            $stDev->execute(['di' => $di, 'df' => $df]);
            foreach ($stDev->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $erpDevolucoes[(int)$r['idvendedor']] = (float)$r['dev_mes'];
            }

            // Desafios 1, 2 e 3
            foreach ([1, 2, 3] as $numD) {
                $dCfg = $desafios[$numD] ?? null;
                if (!$dCfg) continue;
                if (!empty($dCfg['itens'])) {
                    $itensIn = implode(',', array_map('intval', $dCfg['itens']));
                    $sqlD = "
                        SELECT n.idvendrepre AS idvendedor,
                               COUNT(DISTINCT n.idcliforemp)::int AS clientes_item
                        FROM nfs n
                        JOIN nfs_item ni ON ni.idnfs = n.idnfs
                        WHERE n.status = 2
                          AND n.idtransacao = 1
                          AND n.dataemissao BETWEEN :di AND :df
                          AND n.idvendrepre IN ($vendIn)
                          AND ni.iditem IN ($itensIn)
                        GROUP BY n.idvendrepre
                    ";
                    $stDi = $this->pdo->prepare($sqlD);
                    $stDi->execute(['di' => $di, 'df' => $df]);
                    foreach ($stDi->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $erpDesafios[$numD][(int)$r['idvendedor']] = (int)$r['clientes_item'];
                    }
                } elseif (($dCfg['regra_tipo'] ?? '') === 'REATIVACAO_ABERTURA') {
                    // Clientes faturados no mes sem compra nos 90 dias anteriores
                    $di90 = date('Y-m-d', strtotime($di . ' -90 days'));
                    $diPrev = date('Y-m-d', strtotime($di . ' -1 day'));
                    $sqlRnc = "
                        SELECT n.idvendrepre AS idvendedor,
                               COUNT(DISTINCT n.idcliforemp)::int AS clientes_rnc
                        FROM nfs n
                        WHERE n.status = 2
                          AND n.idtransacao = 1
                          AND n.dataemissao BETWEEN :di AND :df
                          AND n.idvendrepre IN ($vendIn)
                          AND NOT EXISTS (
                              SELECT 1 FROM nfs n2
                              WHERE n2.status = 2 AND n2.idtransacao = 1
                                AND n2.idcliforemp = n.idcliforemp
                                AND n2.dataemissao BETWEEN :di90 AND :diprev
                          )
                        GROUP BY n.idvendrepre
                    ";
                    $stRnc = $this->pdo->prepare($sqlRnc);
                    $stRnc->execute(['di' => $di, 'df' => $df, 'di90' => $di90, 'diprev' => $diPrev]);
                    foreach ($stRnc->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $erpDesafios[$numD][(int)$r['idvendedor']] = (int)$r['clientes_rnc'];
                    }
                }
            }
        }

        // 7. Consolidar resultado por participante
        $resultado = [];
        foreach ($participantes as $p) {
            $pid = (int)$p['id'];
            $vid = (int)$p['idvendedor'];

            if (isset($fechados[$pid])) {
                $rf = $fechados[$pid];
                $resultado[] = [
                    'idparticipante' => $pid,
                    'idvendedor' => $vid,
                    'nome' => $p['nome_exibicao'],
                    'apelido' => $p['apelido_tatame'] ?: $p['nome_exibicao'],
                    'equipe' => $p['equipe_nome'] ?: 'Sem Equipe',
                    'equipe_cor' => $p['equipe_cor'] ?: '#2F5D50',
                    'categoria' => $p['categoria'],
                    'ano' => $ano,
                    'mes' => $mes,
                    'fechado' => true,
                    'meta_faturamento' => (float)$rf['meta_faturamento'],
                    'fat_dia10' => (float)$rf['fat_dia10'],
                    'fat_dia20' => (float)$rf['fat_dia20'],
                    'fat_dia30' => (float)$rf['fat_dia30'],
                    'devolucoes_mes' => (float)$rf['devolucoes_mes'],
                    'fat_liquido' => (float)$rf['fat_liquido'],
                    'pct_dia10' => (float)$rf['pct_dia10'],
                    'pct_dia20' => (float)$rf['pct_dia20'],
                    'pct_dia30' => (float)$rf['pct_dia30'],
                    'pts_dia10' => (int)$rf['pts_dia10'],
                    'pts_dia20' => (int)$rf['pts_dia20'],
                    'pts_dia30' => (int)$rf['pts_dia30'],
                    'carteira_ativa' => (int)$rf['carteira_ativa'],
                    'carteira_efetiva' => (int)$rf['carteira_efetiva'],
                    'clientes_positivados' => (int)$rf['clientes_positivados'],
                    'pct_positivacao' => (float)$rf['pct_positivacao'],
                    'pts_positivacao' => (int)$rf['pts_positivacao'],
                    'pct_desafio1' => (float)$rf['pct_desafio1'],
                    'pts_desafio1' => (int)$rf['pts_desafio1'],
                    'pct_desafio2' => (float)$rf['pct_desafio2'],
                    'pts_desafio2' => (int)$rf['pts_desafio2'],
                    'pct_desafio3' => (float)$rf['pct_desafio3'],
                    'pts_desafio3' => (int)$rf['pts_desafio3'],
                    'pts_extras' => (int)$rf['pts_extras'],
                    'pts_total_mes' => (int)$rf['pts_total_mes'],
                    'bonus_mes_rs' => (float)$rf['bonus_mes_rs'],
                ];
                continue;
            }

            $metaRow = $metas[$pid] ?? [];
            $metaFat = (float)($metaRow['meta_faturamento'] ?? 0);
            $carteiraAtiva = (int)($metaRow['carteira_ativa'] ?? $p['carteira_base'] ?? $pisoCarteira);
            $carteiraEfetiva = max($pisoCarteira, $carteiraAtiva);

            $v = $erpVendas[$vid] ?? ['fat_d10' => 0, 'fat_d20' => 0, 'fat_d30' => 0, 'clientes_pos' => 0];
            $dev = (float)($erpDevolucoes[$vid] ?? 0);
            $ajusteVal = (float)($extras[$pid]['valor'] ?? 0);
            $ptsExtras = (int)($extras[$pid]['pts'] ?? 0);

            $fatD10 = round((float)$v['fat_d10'], 2);
            $fatD20 = round((float)$v['fat_d20'], 2);
            $fatD30 = round((float)$v['fat_d30'], 2);
            $fatLiq = round(max(0, $fatD30 - $dev + $ajusteVal), 2);

            $pctD10 = $metaFat > 0 ? round(($fatD10 / $metaFat) * 100, 2) : 0.0;
            $pctD20 = $metaFat > 0 ? round(($fatD20 / $metaFat) * 100, 2) : 0.0;
            $pctD30 = $metaFat > 0 ? round(($fatLiq / $metaFat) * 100, 2) : 0.0;

            $ptsD10 = self::calcularPontosDia10($pctD10);
            $ptsD20 = self::calcularPontosDia20($pctD20);
            $ptsD30 = self::calcularPontosDia30($pctD30);

            $cliPos = (int)$v['clientes_pos'];
            $pctPos = $carteiraEfetiva > 0 ? round(($cliPos / $carteiraEfetiva) * 100, 2) : 0.0;
            $ptsPos = self::calcularPontosPositivacao($pctPos);

            $pctDes = [1 => 0.0, 2 => 0.0, 3 => 0.0];
            $ptsDes = [1 => 0, 2 => 0, 3 => 0];
            foreach ([1, 2, 3] as $numD) {
                $dCfg = $desafios[$numD] ?? null;
                if (!$dCfg) continue;
                $cliD = (int)($erpDesafios[$numD][$vid] ?? 0);
                $pct = $carteiraEfetiva > 0 ? round(($cliD / $carteiraEfetiva) * 100, 2) : 0.0;
                $pctDes[$numD] = $pct;
                if ($pct >= (float)$dCfg['meta_percentual']) {
                    $ptsDes[$numD] = (int)$dCfg['pontos'];
                }
            }

            $ptsTotal = $ptsD10 + $ptsD20 + $ptsD30 + $ptsPos + $ptsDes[1] + $ptsDes[2] + $ptsDes[3] + $ptsExtras;
            $bonus = self::calcularBonusMes($ptsTotal);

            $resultado[] = [
                'idparticipante' => $pid,
                'idvendedor' => $vid,
                'nome' => $p['nome_exibicao'],
                'apelido' => $p['apelido_tatame'] ?: $p['nome_exibicao'],
                'equipe' => $p['equipe_nome'] ?: 'Sem Equipe',
                'equipe_cor' => $p['equipe_cor'] ?: '#2F5D50',
                'categoria' => $p['categoria'],
                'ano' => $ano,
                'mes' => $mes,
                'fechado' => false,
                'meta_faturamento' => $metaFat,
                'fat_dia10' => $fatD10,
                'fat_dia20' => $fatD20,
                'fat_dia30' => $fatD30,
                'devolucoes_mes' => round($dev, 2),
                'fat_liquido' => $fatLiq,
                'pct_dia10' => $pctD10,
                'pct_dia20' => $pctD20,
                'pct_dia30' => $pctD30,
                'pts_dia10' => $ptsD10,
                'pts_dia20' => $ptsD20,
                'pts_dia30' => $ptsD30,
                'carteira_ativa' => $carteiraAtiva,
                'carteira_efetiva' => $carteiraEfetiva,
                'clientes_positivados' => $cliPos,
                'pct_positivacao' => $pctPos,
                'pts_positivacao' => $ptsPos,
                'pct_desafio1' => $pctDes[1],
                'pts_desafio1' => $ptsDes[1],
                'pct_desafio2' => $pctDes[2],
                'pts_desafio2' => $ptsDes[2],
                'pct_desafio3' => $pctDes[3],
                'pts_desafio3' => $ptsDes[3],
                'pts_extras' => $ptsExtras,
                'pts_total_mes' => $ptsTotal,
                'bonus_mes_rs' => $bonus,
            ];
        }

        usort($resultado, function ($a, $b) {
            if ($b['pts_total_mes'] !== $a['pts_total_mes']) {
                return $b['pts_total_mes'] <=> $a['pts_total_mes'];
            }
            return $b['fat_liquido'] <=> $a['fat_liquido'];
        });

        return $resultado;
    }

    /**
     * Ranking anual acumulado da campanha (somando rounds fechados + round aberto atual).
     * Desempate oficial: MAIOR FATURAMENTO LIQUIDO ACUMULADO.
     */
    public function calcularRankingAnual(int $idcampanha, int $ano, int $mesReferencia): array
    {
        $stP = $this->pdo->prepare("
            SELECT p.id, p.idvendedor, p.nome_exibicao, p.apelido_tatame, p.categoria,
                   COALESCE(e.nome, 'Sem Equipe') AS equipe, COALESCE(e.cor, '#2F5D50') AS equipe_cor
            FROM p365_participantes p
            LEFT JOIN p365_equipes e ON e.id = p.idequipe
            WHERE p.idcampanha = ? AND p.ativo = TRUE
        ");
        $stP->execute([$idcampanha]);
        $acumulado = [];
        foreach ($stP->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $acumulado[(int)$p['id']] = [
                'idparticipante' => (int)$p['id'],
                'idvendedor' => (int)$p['idvendedor'],
                'nome' => $p['nome_exibicao'],
                'apelido' => $p['apelido_tatame'] ?: $p['nome_exibicao'],
                'equipe' => $p['equipe'],
                'equipe_cor' => $p['equipe_cor'],
                'xp_total' => 0,
                'fat_liquido_anual' => 0.0,
                'bonus_acumulado_rs' => 0.0,
                'rounds_contabilizados' => 0,
            ];
        }
        if (!$acumulado) return [];

        // Rounds fechados do ano
        $stF = $this->pdo->prepare("
            SELECT idparticipante, mes, pts_total_mes, fat_liquido, bonus_mes_rs
            FROM p365_round_fechamento
            WHERE idcampanha = ? AND ano = ? AND fechado = TRUE
        ");
        $stF->execute([$idcampanha, $ano]);
        $mesesFechados = [];
        foreach ($stF->fetchAll(PDO::FETCH_ASSOC) as $rf) {
            $pid = (int)$rf['idparticipante'];
            $m = (int)$rf['mes'];
            $mesesFechados[$pid][$m] = true;
            if (isset($acumulado[$pid])) {
                $acumulado[$pid]['xp_total'] += (int)$rf['pts_total_mes'];
                $acumulado[$pid]['fat_liquido_anual'] += (float)$rf['fat_liquido'];
                $acumulado[$pid]['bonus_acumulado_rs'] += (float)$rf['bonus_mes_rs'];
                $acumulado[$pid]['rounds_contabilizados']++;
            }
        }

        // Soma round em andamento (mesReferencia) para quem ainda nao fechou esse mes
        $roundAtual = $this->calcularRound($idcampanha, $ano, $mesReferencia);
        foreach ($roundAtual as $r) {
            $pid = (int)$r['idparticipante'];
            if (!empty($mesesFechados[$pid][$mesReferencia]) || !isset($acumulado[$pid])) continue;
            $acumulado[$pid]['xp_total'] += (int)$r['pts_total_mes'];
            $acumulado[$pid]['fat_liquido_anual'] += (float)$r['fat_liquido'];
            $acumulado[$pid]['bonus_acumulado_rs'] += (float)$r['bonus_mes_rs'];
            $acumulado[$pid]['rounds_contabilizados']++;
        }

        $lista = array_values($acumulado);
        usort($lista, function ($a, $b) {
            if ($b['xp_total'] !== $a['xp_total']) {
                return $b['xp_total'] <=> $a['xp_total'];
            }
            return $b['fat_liquido_anual'] <=> $a['fat_liquido_anual'];
        });

        $premios = self::catalogoPremiacaoAnual();
        foreach ($lista as $idx => &$item) {
            $pos = $idx + 1;
            $item['posicao'] = $pos;
            $item['faixa'] = self::calcularFaixa($item['xp_total']);
            $item['premio_anual_rs'] = $premios[$pos] ?? 0.0;
            $item['fat_liquido_anual'] = round($item['fat_liquido_anual'], 2);
            $item['bonus_acumulado_rs'] = round($item['bonus_acumulado_rs'], 2);
        }

        return $lista;
    }

    /**
     * Fecha (congela) o snapshot de um round e desbloqueia automaticamente os PINs conquistados.
     */
    public function fecharRound(int $idcampanha, int $ano, int $mes, string $usuario): array
    {
        $rows = $this->calcularRound($idcampanha, $ano, $mes);
        if (!$rows) {
            return ['fechados' => 0];
        }

        $this->pdo->beginTransaction();
        try {
            $stmtUp = $this->pdo->prepare("
                INSERT INTO p365_round_fechamento (
                    idcampanha, idparticipante, ano, mes,
                    fat_dia10, fat_dia20, fat_dia30, devolucoes_mes, fat_liquido, meta_faturamento,
                    pct_dia10, pct_dia20, pct_dia30, pts_dia10, pts_dia20, pts_dia30,
                    carteira_ativa, carteira_efetiva, clientes_positivados, pct_positivacao, pts_positivacao,
                    pct_desafio1, pts_desafio1, pct_desafio2, pts_desafio2, pct_desafio3, pts_desafio3,
                    pts_extras, pts_total_mes, bonus_mes_rs, fechado, fechado_por, fechado_em
                ) VALUES (
                    :idcampanha, :idparticipante, :ano, :mes,
                    :fat_dia10, :fat_dia20, :fat_dia30, :devolucoes_mes, :fat_liquido, :meta_faturamento,
                    :pct_dia10, :pct_dia20, :pct_dia30, :pts_dia10, :pts_dia20, :pts_dia30,
                    :carteira_ativa, :carteira_efetiva, :clientes_positivados, :pct_positivacao, :pts_positivacao,
                    :pct_desafio1, :pts_desafio1, :pct_desafio2, :pts_desafio2, :pct_desafio3, :pts_desafio3,
                    :pts_extras, :pts_total_mes, :bonus_mes_rs, TRUE, :fechado_por, NOW()
                )
                ON CONFLICT (idcampanha, idparticipante, ano, mes)
                DO UPDATE SET
                    fat_dia10 = EXCLUDED.fat_dia10, fat_dia20 = EXCLUDED.fat_dia20, fat_dia30 = EXCLUDED.fat_dia30,
                    devolucoes_mes = EXCLUDED.devolucoes_mes, fat_liquido = EXCLUDED.fat_liquido,
                    meta_faturamento = EXCLUDED.meta_faturamento,
                    pct_dia10 = EXCLUDED.pct_dia10, pct_dia20 = EXCLUDED.pct_dia20, pct_dia30 = EXCLUDED.pct_dia30,
                    pts_dia10 = EXCLUDED.pts_dia10, pts_dia20 = EXCLUDED.pts_dia20, pts_dia30 = EXCLUDED.pts_dia30,
                    carteira_ativa = EXCLUDED.carteira_ativa, carteira_efetiva = EXCLUDED.carteira_efetiva,
                    clientes_positivados = EXCLUDED.clientes_positivados, pct_positivacao = EXCLUDED.pct_positivacao,
                    pts_positivacao = EXCLUDED.pts_positivacao,
                    pct_desafio1 = EXCLUDED.pct_desafio1, pts_desafio1 = EXCLUDED.pts_desafio1,
                    pct_desafio2 = EXCLUDED.pct_desafio2, pts_desafio2 = EXCLUDED.pts_desafio2,
                    pct_desafio3 = EXCLUDED.pct_desafio3, pts_desafio3 = EXCLUDED.pts_desafio3,
                    pts_extras = EXCLUDED.pts_extras, pts_total_mes = EXCLUDED.pts_total_mes,
                    bonus_mes_rs = EXCLUDED.bonus_mes_rs,
                    fechado = TRUE, fechado_por = EXCLUDED.fechado_por, fechado_em = NOW()
            ");

            $stmtPin = $this->pdo->prepare("
                INSERT INTO p365_pins_desbloqueados (idcampanha, idparticipante, pin_codigo, ano, mes)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (idcampanha, idparticipante, pin_codigo, ano, mes) DO NOTHING
            ");

            foreach ($rows as $r) {
                $stmtUp->execute([
                    'idcampanha' => $idcampanha,
                    'idparticipante' => $r['idparticipante'],
                    'ano' => $ano,
                    'mes' => $mes,
                    'fat_dia10' => $r['fat_dia10'],
                    'fat_dia20' => $r['fat_dia20'],
                    'fat_dia30' => $r['fat_dia30'],
                    'devolucoes_mes' => $r['devolucoes_mes'],
                    'fat_liquido' => $r['fat_liquido'],
                    'meta_faturamento' => $r['meta_faturamento'],
                    'pct_dia10' => $r['pct_dia10'],
                    'pct_dia20' => $r['pct_dia20'],
                    'pct_dia30' => $r['pct_dia30'],
                    'pts_dia10' => $r['pts_dia10'],
                    'pts_dia20' => $r['pts_dia20'],
                    'pts_dia30' => $r['pts_dia30'],
                    'carteira_ativa' => $r['carteira_ativa'],
                    'carteira_efetiva' => $r['carteira_efetiva'],
                    'clientes_positivados' => $r['clientes_positivados'],
                    'pct_positivacao' => $r['pct_positivacao'],
                    'pts_positivacao' => $r['pts_positivacao'],
                    'pct_desafio1' => $r['pct_desafio1'],
                    'pts_desafio1' => $r['pts_desafio1'],
                    'pct_desafio2' => $r['pct_desafio2'],
                    'pts_desafio2' => $r['pts_desafio2'],
                    'pct_desafio3' => $r['pct_desafio3'],
                    'pts_desafio3' => $r['pts_desafio3'],
                    'pts_extras' => $r['pts_extras'],
                    'pts_total_mes' => $r['pts_total_mes'],
                    'bonus_mes_rs' => $r['bonus_mes_rs'],
                    'fechado_por' => $usuario,
                ]);

                // Avaliar PINs do Round
                $pins = [];
                if ($r['pts_dia10'] >= 20) $pins[] = 'RASPAGEM_OURO';
                if ($r['pts_dia20'] >= 20) $pins[] = 'GUARDA_PASSADA';
                if ($r['pct_dia30'] >= 100) $pins[] = 'FINALIZADOR_100';
                if ($r['pts_dia30'] >= 30) $pins[] = 'FINALIZADOR_110';
                if ($r['pts_positivacao'] >= 20) $pins[] = 'CONTROLE_70';
                if ($r['pts_desafio1'] > 0) $pins[] = 'KIMONO_MASTER';
                if ($r['pts_desafio2'] > 0) $pins[] = 'ARMLOCK_PRO';
                if ($r['pts_desafio3'] > 0) $pins[] = 'RNC_REATIVADOR';
                if ($r['pts_total_mes'] >= 120) $pins[] = 'ROUND_PERFEITO';

                foreach ($pins as $pinCode) {
                    $stmtPin->execute([$idcampanha, $r['idparticipante'], $pinCode, $ano, $mes]);
                }
            }

            $this->pdo->commit();
            return ['fechados' => count($rows)];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
