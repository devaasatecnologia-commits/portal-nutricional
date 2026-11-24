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

    public static function catalogoFaixasDetalhado(): array
    {
        return [
            [
                'codigo' => 'BRANCA', 'nome' => 'Faixa Branca', 'japones' => '白帯',
                'subtitulo' => 'Fundamentos e sobrevivência', 'xp_range' => '0 a 287 XP',
                'cor' => '#F8FAFC', 'borda' => '#CBD5E1', 'texto' => '#1E293B',
                'significado' => 'Aprendizado das bases, cadência inicial e construção de rotina sem se afobar.',
                'graus' => ['1º Grau: 0 a 71 XP', '2º Grau: 72 a 143 XP', '3º Grau: 144 a 215 XP', '4º Grau: 216 a 287 XP'],
            ],
            [
                'codigo' => 'AZUL', 'nome' => 'Faixa Azul', 'japones' => '青帯',
                'subtitulo' => 'Consistência e defesa/ataque', 'xp_range' => '288 a 575 XP',
                'cor' => '#2563EB', 'borda' => '#1D4ED8', 'texto' => '#FFFFFF',
                'significado' => 'Consolidação da frequência, maior leitura de carteira e respostas rápidas aos indicadores.',
                'graus' => ['1º Grau: 288 a 359 XP', '2º Grau: 360 a 431 XP', '3º Grau: 432 a 503 XP', '4º Grau: 504 a 575 XP'],
            ],
            [
                'codigo' => 'ROXA', 'nome' => 'Faixa Roxa', 'japones' => '紫帯',
                'subtitulo' => 'Refinamento técnico', 'xp_range' => '576 a 863 XP',
                'cor' => '#7C3AED', 'borda' => '#6D28D9', 'texto' => '#FFFFFF',
                'significado' => 'Domínio estratégico do mix, volume e positivação.',
                'graus' => ['1º Grau: 576 a 647 XP', '2º Grau: 648 a 719 XP', '3º Grau: 720 a 791 XP', '4º Grau: 792 a 863 XP'],
            ],
            [
                'codigo' => 'MARROM', 'nome' => 'Faixa Marrom', 'japones' => '茶帯',
                'subtitulo' => 'Ajuste fino e alta pressão', 'xp_range' => '864 a 1.151 XP',
                'cor' => '#78350F', 'borda' => '#451A03', 'texto' => '#FFFFFF',
                'significado' => 'Refinamento de execução, baixa perda e alta eficiência competitiva.',
                'graus' => ['1º Grau: 864 a 935 XP', '2º Grau: 936 a 1.007 XP', '3º Grau: 1.008 a 1.079 XP', '4º Grau: 1.080 a 1.151 XP'],
            ],
            [
                'codigo' => 'PRETA', 'nome' => 'Faixa Preta', 'japones' => '黒帯',
                'subtitulo' => 'Maestria e excelência', 'xp_range' => '1.152 a 1.440 XP',
                'cor' => '#0F172A', 'borda' => '#E8C87E', 'texto' => '#E8C87E',
                'significado' => 'Nível de excelência máxima: consistência integral e precisão técnica no ranking.',
                'graus' => ['1º Grau: 1.152 a 1.223 XP', '2º Grau: 1.224 a 1.295 XP', '3º Grau: 1.296 a 1.367 XP', '4º Grau: 1.368 a 1.439 XP', '5º Grau (Consagração): 1.440 XP'],
            ],
        ];
    }

    public static function catalogoPins(): array
    {
        return [
            // 1. PINs de Graduacao (Faixas)
            ['codigo' => 'PIN-FX-BR-0',    'nome' => 'Início no Tatame (Faixa Branca)',   'categoria' => '1. Graduação (Faixas)', 'icone' => 'fa-ribbon',        'regra' => 'Iniciar a Faixa Branca na temporada.'],
            ['codigo' => 'PIN-FX-AZ-0',    'nome' => 'Promoção Faixa Azul',               'categoria' => '1. Graduação (Faixas)', 'icone' => 'fa-ribbon',        'regra' => 'Atingir 288 XP e iniciar a Faixa Azul.'],
            ['codigo' => 'PIN-FX-RX-0',    'nome' => 'Promoção Faixa Roxa',               'categoria' => '1. Graduação (Faixas)', 'icone' => 'fa-ribbon',        'regra' => 'Atingir 576 XP e iniciar a Faixa Roxa.'],
            ['codigo' => 'PIN-FX-MR-0',    'nome' => 'Promoção Faixa Marrom',             'categoria' => '1. Graduação (Faixas)', 'icone' => 'fa-ribbon',        'regra' => 'Atingir 864 XP e iniciar a Faixa Marrom.'],
            ['codigo' => 'PIN-FX-PR-0',    'nome' => 'Promoção Faixa Preta',              'categoria' => '1. Graduação (Faixas)', 'icone' => 'fa-medal',         'regra' => 'Atingir 1.152 XP e entrar na Faixa Preta.'],
            ['codigo' => 'PIN-FX-PR-CONS', 'nome' => 'Consagração Faixa Preta (1.440 XP)','categoria' => '1. Graduação (Faixas)', 'icone' => 'fa-crown',         'regra' => 'Atingir a pontuação máxima de 1.440 pontos.'],

            // 2. PINs de Volume de Vendas & Acumulo XP
            ['codigo' => 'PIN-VND-0025',   'nome' => 'Aquecimento no Tatame',             'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-cart-shopping', 'regra' => 'Realizar 25 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0050',   'nome' => 'Pegada Firme',                      'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-hand-fist',     'regra' => 'Realizar 50 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0100',   'nome' => 'Centurião das Vendas',              'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-shield',        'regra' => 'Realizar 100 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0200',   'nome' => 'Raspagem Contínua',                 'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-bolt',          'regra' => 'Realizar 200 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0350',   'nome' => 'Passagem Constante',                'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-angles-up',     'regra' => 'Realizar 350 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0500',   'nome' => 'Meio Milhar no Tatame',             'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-fire-flame-curved','regra' => 'Realizar 500 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0600',   'nome' => 'Controle Lateral Sólido',           'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-lock',          'regra' => 'Realizar 600 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0750',   'nome' => 'Finalizador do Tatame',             'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-trophy',        'regra' => 'Realizar 750 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-0850',   'nome' => 'Pressão de Faixa Preta',            'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-dumbbell',      'regra' => 'Realizar 850 vendas válidas no ano.'],
            ['codigo' => 'PIN-VND-1000',   'nome' => 'Milhar da Arte Suave',              'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-gem',           'regra' => 'Realizar 1.000 vendas válidas no ano.'],
            ['codigo' => 'PIN-XP-050',     'nome' => 'Espírito de Guerreiro',             'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-star-half-stroke','regra' => 'Acumular 50 pontos de XP.'],
            ['codigo' => 'PIN-XP-100',     'nome' => 'Centurião do Tatame',               'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-star',          'regra' => 'Acumular 100 pontos de XP.'],
            ['codigo' => 'PIN-XP-250',     'nome' => 'Um Quarto do Caminho',              'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-compass',       'regra' => 'Acumular 250 pontos de XP.'],
            ['codigo' => 'PIN-XP-500',     'nome' => 'Meio Milhar de Elite',              'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-rocket',        'regra' => 'Acumular 500 pontos de XP.'],
            ['codigo' => 'PIN-XP-720',     'nome' => 'Metade da Jornada (720 XP)',        'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-mountain-sun',  'regra' => 'Atingir 50% da escala de 1.440 pontos (720 XP).'],
            ['codigo' => 'PIN-XP-1200',    'nome' => 'Lenda do Tatame',                   'categoria' => '2. Volume & Acúmulo XP','icone' => 'fa-dragon',        'regra' => 'Acumular 1.200 pontos de XP.'],

            // 3. PINs de Precisao Mensal & Ritmo (Series Anuais e Gatilhos D10/D20/D30)
            ['codigo' => 'PIN-D10-25',     'nome' => 'Raspagem Relâmpago (Dia 10)',       'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-stopwatch',     'regra' => 'Atingir 25%+ do faturamento alvo até o Dia 10 (+20 pts).'],
            ['codigo' => 'PIN-D20-60',     'nome' => 'Passagem de Guarda (Dia 20)',       'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-shield-halved', 'regra' => 'Atingir 60%+ do faturamento alvo até o Dia 20 (+20 pts).'],
            ['codigo' => 'PIN-D01-03X',    'nome' => 'Kimura em Série (3x no Ano)',       'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-award',         'regra' => 'Cumprir o Desafio 01 (>= 15% da base) em 3 meses no ano.'],
            ['codigo' => 'PIN-D01-06X',    'nome' => 'Domínio da Kimura (6x no Ano)',     'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-award',         'regra' => 'Cumprir o Desafio 01 (>= 15% da base) em 6 meses no ano.'],
            ['codigo' => 'PIN-D01-09X',    'nome' => 'Mestre da Kimura (9x no Ano)',      'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-award',         'regra' => 'Cumprir o Desafio 01 (>= 15% da base) em 9 meses no ano.'],
            ['codigo' => 'PIN-D02-03X',    'nome' => 'Armlock em Série (3x no Ano)',      'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-crosshairs',    'regra' => 'Cumprir o Desafio 02 (>= 20% da base) em 3 meses no ano.'],
            ['codigo' => 'PIN-D02-06X',    'nome' => 'Domínio do Armlock (6x no Ano)',    'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-crosshairs',    'regra' => 'Cumprir o Desafio 02 (>= 20% da base) em 6 meses no ano.'],
            ['codigo' => 'PIN-D02-09X',    'nome' => 'Mestre do Armlock (9x no Ano)',     'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-crosshairs',    'regra' => 'Cumprir o Desafio 02 (>= 20% da base) em 9 meses no ano.'],
            ['codigo' => 'PIN-D03-03X',    'nome' => 'RNC em Série (3x no Ano)',          'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-fire',          'regra' => 'Cumprir o Desafio 03 (>= 25% da base) em 3 meses no ano.'],
            ['codigo' => 'PIN-D03-06X',    'nome' => 'Domínio do RNC (6x no Ano)',        'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-fire',          'regra' => 'Cumprir o Desafio 03 (>= 25% da base) em 6 meses no ano.'],
            ['codigo' => 'PIN-D03-09X',    'nome' => 'Mestre do RNC (9x no Ano)',         'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-fire',          'regra' => 'Cumprir o Desafio 03 (>= 25% da base) em 9 meses no ano.'],
            ['codigo' => 'PIN-META-03X',   'nome' => 'Meta no Tatame (3x no Ano)',        'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-bullseye',      'regra' => 'Atingir a meta mensal (>= 100%) em 3 meses no ano.'],
            ['codigo' => 'PIN-META-06X',   'nome' => 'Semestre de Meta (6x no Ano)',      'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-bullseye',      'regra' => 'Atingir a meta mensal (>= 100%) em 6 meses no ano.'],
            ['codigo' => 'PIN-META-09X',   'nome' => 'Ritmo de Faixa Preta (9x no Ano)',  'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-bullseye',      'regra' => 'Atingir a meta mensal (>= 100%) em 9 meses no ano.'],
            ['codigo' => 'PIN-D30-110',    'nome' => 'Domínio Total (110% Meta)',         'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-crown',         'regra' => 'Superar uma meta mensal atingindo 110% no ano (+30 pts).'],
            ['codigo' => 'PIN-FULL-120',   'nome' => 'Lutador Completo (Teto Mensal)',    'categoria' => '3. Precisão & Ritmo',   'icone' => 'fa-gem',           'regra' => 'Atingir a pontuação máxima possível em um mês (120 pts).'],

            // 4. PINs de Carteira, Positivacao e Desafios Tecnicos
            ['codigo' => 'PIN-CART-LAT',   'nome' => 'Controle Lateral Ativo',            'categoria' => '4. Carteira & Desafios','icone' => 'fa-users',         'regra' => 'Positivar ao menos 50% ou 60% da carteira ativa no mês.'],
            ['codigo' => 'PIN-CART-70',    'nome' => 'Cobertura de Elite (70%)',          'categoria' => '4. Carteira & Desafios','icone' => 'fa-users-gear',    'regra' => 'Positivar 70% ou mais da carteira ativa no mês (+20 pts).'],
            ['codigo' => 'PIN-CART-85',    'nome' => 'Guarda Intransponível (85%)',       'categoria' => '4. Carteira & Desafios','icone' => 'fa-chess-rook',    'regra' => 'Positivação igual ou superior a 85% da carteira ativa.'],
            ['codigo' => 'PIN-DES-KIM',    'nome' => 'Chave de Ombro / Kimura',           'categoria' => '4. Carteira & Desafios','icone' => 'fa-hand-back-fist','regra' => 'Cumprir o Desafio 01 (>= 15% da base com produtos foco).'],
            ['codigo' => 'PIN-DES-ARM',    'nome' => 'Armlock de Mix',                    'categoria' => '4. Carteira & Desafios','icone' => 'fa-circle-check',  'regra' => 'Cumprir o Desafio 02 (>= 20% da base com produtos foco).'],
            ['codigo' => 'PIN-DES-RNC',    'nome' => 'Mata-Leão / RNC de Expansão',       'categoria' => '4. Carteira & Desafios','icone' => 'fa-bolt-lightning','regra' => 'Cumprir o Desafio 03 (reativar/abrir >= 25% da base).'],
            ['codigo' => 'PIN-DES-TRIPLE', 'nome' => 'Tríplice Finalização do Mês',       'categoria' => '4. Carteira & Desafios','icone' => 'fa-cubes',         'regra' => 'Concluir os 3 desafios (Kimura, Armlock e RNC) no mesmo mês.'],

            // 5. PINs de Consistencia, Disciplina e Acoes Taticas
            ['codigo' => 'PIN-CONS-3M',    'nome' => 'Hat-Trick no Tatame',               'categoria' => '5. Consistência & Ética','icone' => 'fa-calendar-check','regra' => 'Atingir meta mensal (>= 100%) por 3 meses consecutivos.'],
            ['codigo' => 'PIN-CONS-6M',    'nome' => 'Invicto no Semestre',               'categoria' => '5. Consistência & Ética','icone' => 'fa-medal',         'regra' => 'Manter 6 meses seguidos acima de 80 pontos.'],
            ['codigo' => 'PIN-CONS-12M',   'nome' => 'Temporada Completa (12/12)',        'categoria' => '5. Consistência & Ética','icone' => 'fa-earth-americas','regra' => 'Participar e pontuar em todos os 12 meses da temporada.'],
            ['codigo' => 'PIN-AUD-ZERO',   'nome' => 'Zero Punish (Trimestre)',           'categoria' => '5. Consistência & Ética','icone' => 'fa-scale-balanced','regra' => 'Concluir um trimestre sem devoluções que impactem indicadores.'],
            ['codigo' => 'PIN-AUD-ANO',    'nome' => 'Defesa Limpa Anual',                'categoria' => '5. Consistência & Ética','icone' => 'fa-shield-heart',  'regra' => 'Encerrar o ano sem devoluções que impactem indicadores.'],
            ['codigo' => 'PIN-EXTRA-1X',   'nome' => 'Ponto Extra (+5) em Ação',          'categoria' => '5. Consistência & Ética','icone' => 'fa-plus',          'regra' => 'Pontuar 1x em ações de pontos extras no ano.'],
            ['codigo' => 'PIN-EXTRA-3X',   'nome' => 'Ponto Extra (Em 3 vezes)',          'categoria' => '5. Consistência & Ética','icone' => 'fa-wand-magic-sparkles','regra' => 'Pontuar 3x em ações de pontos extras no ano.'],
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
            $dfJanela = date('Y-m-d', strtotime($df . ' +45 days'));
            $vendIn = implode(',', $abertosVendIds);

            // Faturamento bruto alinhado a Mercos: usa DATA DE IMPORTACAO/CHEGADA DO PEDIDO (palmtop_pedido.data / pedido.data)
            // para notas ja faturadas (status = 2, idtransacao = 1), mantendo fallback para n.dataemissao se avulsa.
            $cteNfsPed = "
                WITH n_base AS (
                    SELECT n.idnfs, n.idfilial, n.idvendrepre, n.idcliforemp, n.valortotalnota, n.dataemissao,
                           NULLIF(regexp_replace(COALESCE(n.pedidospda, ''), '[^0-9]', '', 'g'), '')::bigint AS idpda,
                           NULLIF(regexp_replace(split_part(COALESCE(n.pedidos, ''), ',', 1), '[^0-9]', '', 'g'), '')::bigint AS idped
                    FROM nfs n
                    WHERE n.status = 2
                      AND n.idtransacao = 1
                      AND n.dataemissao BETWEEN :di AND :df_janela
                      AND n.idvendrepre IN ($vendIn)
                ),
                n_ref AS (
                    SELECT nb.idnfs, nb.idvendrepre, nb.idcliforemp, nb.valortotalnota,
                           COALESCE(pp.data, p.data, nb.dataemissao) AS data_ref
                    FROM n_base nb
                    LEFT JOIN palmtop_pedido pp ON pp.idpedidopda = nb.idpda AND pp.idfilial = nb.idfilial
                    LEFT JOIN pedido p ON p.idpedido = nb.idped
                )
            ";

            $sqlNfs = $cteNfsPed . "
                SELECT idvendrepre AS idvendedor,
                       COALESCE(SUM(CASE WHEN data_ref <= :d10 THEN valortotalnota ELSE 0 END), 0)::float AS fat_d10,
                       COALESCE(SUM(CASE WHEN data_ref <= :d20 THEN valortotalnota ELSE 0 END), 0)::float AS fat_d20,
                       COALESCE(SUM(valortotalnota), 0)::float AS fat_d30,
                       COUNT(DISTINCT idcliforemp)::int AS clientes_pos,
                       COUNT(DISTINCT idnfs)::int AS qtd_vendas
                FROM n_ref
                WHERE data_ref BETWEEN :di AND :df
                GROUP BY idvendrepre
            ";
            $stNfs = $this->pdo->prepare($sqlNfs);
            $stNfs->execute(['di' => $di, 'df_janela' => $dfJanela, 'd10' => $d10, 'd20' => $d20, 'df' => $df]);
            foreach ($stNfs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $erpVendas[(int)$r['idvendedor']] = $r;
            }

            // Devolucoes no mes do fato de retorno (Regra 8: nfe status=2, status_devolucao=1)
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

            // Desafios 1, 2 e 3 (tambem pela data de chegada/importacao do pedido)
            foreach ([1, 2, 3] as $numD) {
                $dCfg = $desafios[$numD] ?? null;
                if (!$dCfg) continue;
                if (!empty($dCfg['itens'])) {
                    $itensIn = implode(',', array_map('intval', $dCfg['itens']));
                    $sqlD = $cteNfsPed . "
                        SELECT nr.idvendrepre AS idvendedor,
                               COUNT(DISTINCT nr.idcliforemp)::int AS clientes_item
                        FROM n_ref nr
                        JOIN nfs_item ni ON ni.idnfs = nr.idnfs
                        WHERE nr.data_ref BETWEEN :di AND :df
                          AND ni.iditem IN ($itensIn)
                        GROUP BY nr.idvendrepre
                    ";
                    $stDi = $this->pdo->prepare($sqlD);
                    $stDi->execute(['di' => $di, 'df_janela' => $dfJanela, 'df' => $df]);
                    foreach ($stDi->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $erpDesafios[$numD][(int)$r['idvendedor']] = (int)$r['clientes_item'];
                    }
                } elseif (($dCfg['regra_tipo'] ?? '') === 'REATIVACAO_ABERTURA') {
                    $di90 = date('Y-m-d', strtotime($di . ' -90 days'));
                    $diPrev = date('Y-m-d', strtotime($di . ' -1 day'));
                    $sqlRnc = $cteNfsPed . "
                        SELECT nr.idvendrepre AS idvendedor,
                               COUNT(DISTINCT nr.idcliforemp)::int AS clientes_rnc
                        FROM n_ref nr
                        WHERE nr.data_ref BETWEEN :di AND :df
                          AND NOT EXISTS (
                              SELECT 1 FROM nfs n2
                              WHERE n2.status = 2 AND n2.idtransacao = 1
                                AND n2.idcliforemp = nr.idcliforemp
                                AND n2.dataemissao BETWEEN :di90 AND :diprev
                          )
                        GROUP BY nr.idvendrepre
                    ";
                    $stRnc = $this->pdo->prepare($sqlRnc);
                    $stRnc->execute(['di' => $di, 'df_janela' => $dfJanela, 'df' => $df, 'di90' => $di90, 'diprev' => $diPrev]);
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
                    'idgestor' => $p['equipe_idgestor'] ? (int)$p['equipe_idgestor'] : null,
                    'filiais' => $p['filiais'] ?: '1,6',
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
                'idgestor' => $p['equipe_idgestor'] ? (int)$p['equipe_idgestor'] : null,
                'filiais' => $p['filiais'] ?: '1,6',
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
    public function calcularRankingAnual(int $idcampanha, int $ano, int $mesReferencia, ?array $filtroParticipanteIds = null): array
    {
        $sqlP = "
            SELECT p.id, p.idvendedor, p.nome_exibicao, p.apelido_tatame, p.categoria, p.filiais, e.idgestor,
                   COALESCE(e.nome, 'Sem Equipe') AS equipe, COALESCE(e.cor, '#2F5D50') AS equipe_cor
            FROM p365_participantes p
            LEFT JOIN p365_equipes e ON e.id = p.idequipe
            WHERE p.idcampanha = ? AND p.ativo = TRUE
        ";
        if ($filtroParticipanteIds !== null) {
            if (empty($filtroParticipanteIds)) return [];
            $sqlP .= " AND p.id IN (" . implode(',', array_map('intval', $filtroParticipanteIds)) . ")";
        }
        $stP = $this->pdo->prepare($sqlP);
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
                'filiais' => $p['filiais'] ?: '1,6',
                'idgestor' => $p['idgestor'] ? (int)$p['idgestor'] : null,
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

                // Avaliar PINs do Round (codigos oficiais T27)
                $pins = ['PIN-FX-BR-0'];
                if ($r['pts_dia10'] >= 20) $pins[] = 'PIN-D10-25';
                if ($r['pts_dia20'] >= 20) $pins[] = 'PIN-D20-60';
                if ($r['pts_dia30'] >= 30) $pins[] = 'PIN-D30-110';
                if ($r['pts_total_mes'] >= 120) $pins[] = 'PIN-FULL-120';
                if ($r['pct_positivacao'] >= 50) $pins[] = 'PIN-CART-LAT';
                if ($r['pct_positivacao'] >= 70) $pins[] = 'PIN-CART-70';
                if ($r['pct_positivacao'] >= 85) $pins[] = 'PIN-CART-85';
                if ($r['pts_desafio1'] > 0) $pins[] = 'PIN-DES-KIM';
                if ($r['pts_desafio2'] > 0) $pins[] = 'PIN-DES-ARM';
                if ($r['pts_desafio3'] > 0) $pins[] = 'PIN-DES-RNC';
                if ($r['pts_desafio1'] > 0 && $r['pts_desafio2'] > 0 && $r['pts_desafio3'] > 0) $pins[] = 'PIN-DES-TRIPLE';

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

    /**
     * Avalia dinamicamente todos os PINs conquistados por um participante (ou lista de participantes)
     * somando snapshots fechados + desempenho acumulado de XP, volume de vendas e series do ano.
     */
    public function avaliarPinsParticipante(int $idcampanha, int $ano, int $mesReferencia, array $partIds): array
    {
        $partIds = array_values(array_unique(array_filter(array_map('intval', $partIds))));
        if (empty($partIds)) return [];

        $inP = implode(',', $partIds);
        $desbloqueadosMap = [];

        // 1. PINs ja gravados em p365_pins_desbloqueados
        $stSaved = $this->pdo->prepare("SELECT pin_codigo, MIN(ano) AS ano, MIN(mes) AS mes FROM p365_pins_desbloqueados WHERE idcampanha = ? AND idparticipante IN ($inP) GROUP BY pin_codigo");
        $stSaved->execute([$idcampanha]);
        foreach ($stSaved->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $desbloqueadosMap[$s['pin_codigo']] = ['pin_codigo' => $s['pin_codigo'], 'ano' => (int)$s['ano'], 'mes' => (int)$s['mes']];
        }

        // 2. Avaliacao dinamica sobre XP anual, rounds fechados + round atual e volume de vendas ERP
        $ranking = $this->calcularRankingAnual($idcampanha, $ano, $mesReferencia, $partIds);
        $maxXp = 0;
        foreach ($ranking as $rk) {
            $maxXp = max($maxXp, (int)$rk['xp_total']);
        }
        $addPin = function (string $code) use (&$desbloqueadosMap, $ano, $mesReferencia) {
            if (!isset($desbloqueadosMap[$code])) {
                $desbloqueadosMap[$code] = ['pin_codigo' => $code, 'ano' => $ano, 'mes' => $mesReferencia];
            }
        };

        $addPin('PIN-FX-BR-0');
        if ($maxXp >= 50)   $addPin('PIN-XP-050');
        if ($maxXp >= 100)  $addPin('PIN-XP-100');
        if ($maxXp >= 250)  $addPin('PIN-XP-250');
        if ($maxXp >= 288)  $addPin('PIN-FX-AZ-0');
        if ($maxXp >= 500)  $addPin('PIN-XP-500');
        if ($maxXp >= 576)  $addPin('PIN-FX-RX-0');
        if ($maxXp >= 720)  $addPin('PIN-XP-720');
        if ($maxXp >= 864)  $addPin('PIN-FX-MR-0');
        if ($maxXp >= 1152) $addPin('PIN-FX-PR-0');
        if ($maxXp >= 1200) $addPin('PIN-XP-1200');
        if ($maxXp >= 1440) $addPin('PIN-FX-PR-CONS');

        // Volume de vendas validas no ano (nfs status=2, idtransacao=1)
        $stVends = $this->pdo->prepare("SELECT DISTINCT idvendedor FROM p365_participantes WHERE id IN ($inP)");
        $stVends->execute();
        $vends = array_map('intval', $stVends->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($vends)) {
            $inV = implode(',', $vends);
            $stQtd = $this->pdo->prepare("
                SELECT MAX(qtd) FROM (
                    SELECT idvendrepre, COUNT(*)::int AS qtd
                    FROM nfs
                    WHERE status = 2 AND idtransacao = 1
                      AND dataemissao BETWEEN :di AND :df
                      AND idvendrepre IN ($inV)
                    GROUP BY idvendrepre
                ) sub
            ");
            $stQtd->execute(['di' => "$ano-01-01", 'df' => "$ano-12-31"]);
            $maxVendas = (int)($stQtd->fetchColumn() ?: 0);
            foreach ([25 => 'PIN-VND-0025', 50 => 'PIN-VND-0050', 100 => 'PIN-VND-0100', 200 => 'PIN-VND-0200', 350 => 'PIN-VND-0350', 500 => 'PIN-VND-0500', 600 => 'PIN-VND-0600', 750 => 'PIN-VND-0750', 850 => 'PIN-VND-0850', 1000 => 'PIN-VND-1000'] as $lim => $code) {
                if ($maxVendas >= $lim) $addPin($code);
            }
        }

        // Round atual ao vivo tambem habilita visualização imediata dos PINs mensais
        $roundAtual = $this->calcularRound($idcampanha, $ano, $mesReferencia, $partIds);
        foreach ($roundAtual as $r) {
            if ($r['pts_dia10'] >= 20) $addPin('PIN-D10-25');
            if ($r['pts_dia20'] >= 20) $addPin('PIN-D20-60');
            if ($r['pts_dia30'] >= 30) $addPin('PIN-D30-110');
            if ($r['pts_total_mes'] >= 120) $addPin('PIN-FULL-120');
            if ($r['pct_positivacao'] >= 50) $addPin('PIN-CART-LAT');
            if ($r['pct_positivacao'] >= 70) $addPin('PIN-CART-70');
            if ($r['pct_positivacao'] >= 85) $addPin('PIN-CART-85');
            if ($r['pts_desafio1'] > 0) $addPin('PIN-DES-KIM');
            if ($r['pts_desafio2'] > 0) $addPin('PIN-DES-ARM');
            if ($r['pts_desafio3'] > 0) $addPin('PIN-DES-RNC');
            if ($r['pts_desafio1'] > 0 && $r['pts_desafio2'] > 0 && $r['pts_desafio3'] > 0) $addPin('PIN-DES-TRIPLE');
        }

        // Pontos extras no ano
        $stEx = $this->pdo->prepare("SELECT MAX(qtd) FROM (SELECT idparticipante, COUNT(*)::int AS qtd FROM p365_lancamentos_extras WHERE idcampanha = ? AND ano = ? AND tipo = 'EXTRA_5' AND pontos > 0 AND idparticipante IN ($inP) GROUP BY idparticipante) s");
        $stEx->execute([$idcampanha, $ano]);
        $maxEx = (int)($stEx->fetchColumn() ?: 0);
        if ($maxEx >= 1) $addPin('PIN-EXTRA-1X');
        if ($maxEx >= 3) $addPin('PIN-EXTRA-3X');

        return array_values($desbloqueadosMap);
    }

    /**
     * Importa/atualiza automaticamente Equipes (Gestores) e Representantes ativos do ERP (vw_gestor_repre),
     * calculando a carteira base real de clientes ativos de cada representante.
     */
    public function sincronizarComErp(int $idcampanha): array
    {
        $rows = $this->pdo->query("
            SELECT DISTINCT idgestor, gestor, idrepresentante, representante, idfilial
            FROM vw_gestor_repre
            WHERE COALESCE(inativo, 'N') = 'N'
              AND idrepresentante > 0
            ORDER BY gestor, representante
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return ['equipes' => 0, 'participantes' => 0];
        }

        // Mapa de carteira ativa real no ERP por vendedor
        $carteiraMap = [];
        foreach ($this->pdo->query("
            SELECT cl.idvendedor, COUNT(DISTINCT cl.idcliforemp)::int AS qtd
            FROM cliente cl
            JOIN cliforemp cf ON cf.idcliforemp = cl.idcliforemp
            WHERE COALESCE(cf.inativo, 'N') = 'N' AND cl.idvendedor > 0
            GROUP BY cl.idvendedor
        ") as $c) {
            $carteiraMap[(int)$c['idvendedor']] = (int)$c['qtd'];
        }

        $this->pdo->beginTransaction();
        try {
            $equipeCache = [];
            foreach ($this->pdo->query("SELECT id, idgestor FROM p365_equipes WHERE idcampanha = " . (int)$idcampanha) as $eq) {
                if (!empty($eq['idgestor'])) {
                    $equipeCache[(int)$eq['idgestor']] = (int)$eq['id'];
                }
            }

            $stInsEq = $this->pdo->prepare("INSERT INTO p365_equipes (idcampanha, nome, idgestor, cor) VALUES (?, ?, ?, '#2F5D50') RETURNING id");
            $stUpPart = $this->pdo->prepare("
                INSERT INTO p365_participantes (idcampanha, idequipe, idvendedor, nome_exibicao, apelido_tatame, categoria, filiais, carteira_base, ativo)
                VALUES (?, ?, ?, ?, ?, 'REPRESENTANTE', ?, ?, TRUE)
                ON CONFLICT (idcampanha, idvendedor)
                DO UPDATE SET
                    idequipe = COALESCE(EXCLUDED.idequipe, p365_participantes.idequipe),
                    nome_exibicao = EXCLUDED.nome_exibicao,
                    filiais = EXCLUDED.filiais,
                    carteira_base = GREATEST(1, EXCLUDED.carteira_base),
                    ativo = TRUE
            ");

            $equipesCriadas = 0;
            $partCount = 0;
            foreach ($rows as $r) {
                $idGestor = (int)($r['idgestor'] ?? 0);
                $nomeGestor = trim((string)($r['gestor'] ?? 'Equipe Geral'));
                $idRep = (int)$r['idrepresentante'];
                $nomeRep = trim(ltrim((string)$r['representante'], '* '));
                $filial = (string)((int)($r['idfilial'] ?? 1));

                $idEquipe = null;
                if ($idGestor > 0) {
                    if (!isset($equipeCache[$idGestor])) {
                        $stInsEq->execute([$idcampanha, 'Equipe ' . $nomeGestor, $idGestor]);
                        $equipeCache[$idGestor] = (int)$stInsEq->fetchColumn();
                        $equipesCriadas++;
                    }
                    $idEquipe = $equipeCache[$idGestor];
                }

                $carteira = max(1, $carteiraMap[$idRep] ?? 60);
                $stUpPart->execute([$idcampanha, $idEquipe, $idRep, $nomeRep, $nomeRep, $filial, $carteira]);
                $partCount++;
            }

            $this->pdo->commit();
            return ['equipes_criadas' => $equipesCriadas, 'participantes_sincronizados' => $partCount];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
