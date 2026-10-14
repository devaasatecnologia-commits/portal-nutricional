<?php
namespace Nutricional\Services;

/**
 * Registra e resume a disponibilidade da API Mercos (tabela flex_api_status_log).
 * Falhas ao gravar nunca derrubam quem chama (o monitoramento e acessorio).
 */
class FlexApiMonitor
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \getPDO();
    }

    /** Testa a filial e registra o resultado. @return array resultado de MercosFlexService::sonda() */
    public function testar(int $filial, string $origem = 'cron'): array
    {
        $r = (new MercosFlexService($filial))->sonda();
        $this->registrar($filial, $r, $origem);
        return $r;
    }

    public function registrar(int $filial, array $r, string $origem): void
    {
        try {
            $this->pdo->prepare('INSERT INTO flex_api_status_log (filial, ok, http, ms, erro, origem) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$filial, $r['ok'] ? 'true' : 'false', $r['http'] ?: null, $r['ms'], $r['erro'] ? mb_substr($r['erro'], 0, 300) : null, $origem]);
        } catch (\Throwable $e) {
            error_log('[flex-monitor] nao gravou status: ' . $e->getMessage());
        }
    }

    public function limpar(int $dias = 30): void
    {
        try {
            $this->pdo->prepare("DELETE FROM flex_api_status_log WHERE em < NOW() - (? || ' days')::interval")->execute([$dias]);
        } catch (\Throwable $e) {
            error_log('[flex-monitor] nao limpou: ' . $e->getMessage());
        }
    }

    /**
     * Resumo do periodo: disponibilidade, tempo medio, quedas e barras para grafico.
     *
     * @return array<string,mixed>
     */
    public function historico(int $filial, int $horas): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT em, ok, http, ms, erro FROM flex_api_status_log
             WHERE filial = ? AND em >= NOW() - (? || ' hours')::interval
             ORDER BY em ASC LIMIT 20000"
        );
        $stmt->execute([$filial, $horas]);
        $linhas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = count($linhas);
        $oks = 0;
        $somaMs = 0;
        $quedas = [];
        $atual = null;
        foreach ($linhas as $l) {
            $ts = strtotime($l['em']);
            if ($l['ok']) {
                $oks++;
                $somaMs += (int)$l['ms'];
                if ($atual) {
                    $atual['fim'] = date('c', $ts);
                    $atual['duracao_seg'] = $ts - strtotime($atual['inicio']);
                    $quedas[] = $atual;
                    $atual = null;
                }
            } elseif ($atual === null) {
                $atual = ['inicio' => date('c', $ts), 'fim' => null, 'duracao_seg' => null, 'falhas' => 1, 'erro' => $l['erro']];
            } else {
                $atual['falhas']++;
            }
        }
        if ($atual) {
            $atual['duracao_seg'] = time() - strtotime($atual['inicio']);
            $quedas[] = $atual;
        }

        // Barras: ate 48 baldes de tempo; um balde com qualquer falha fica vermelho.
        $baldes = [];
        if ($total > 0) {
            $n = 48;
            $ini = time() - $horas * 3600;
            $tam = ($horas * 3600) / $n;
            $tmp = array_fill(0, $n, ['ok' => 0, 'falha' => 0]);
            foreach ($linhas as $l) {
                $i = min($n - 1, max(0, (int)floor((strtotime($l['em']) - $ini) / $tam)));
                $tmp[$i][$l['ok'] ? 'ok' : 'falha']++;
            }
            foreach ($tmp as $i => $b) {
                $baldes[] = [
                    'de' => date('c', (int)($ini + $i * $tam)),
                    'estado' => $b['falha'] > 0 ? 'falha' : ($b['ok'] > 0 ? 'ok' : 'sem_dados'),
                ];
            }
        }

        $ultima = $linhas ? $linhas[$total - 1] : null;
        return [
            'horas' => $horas,
            'checagens' => $total,
            'falhas' => $total - $oks,
            'disponibilidade' => $total ? round($oks / $total * 100, 2) : null,
            'tempo_medio_ms' => $oks ? (int)round($somaMs / $oks) : null,
            'ultima' => $ultima ? ['em' => date('c', strtotime($ultima['em'])), 'ok' => (bool)$ultima['ok'], 'ms' => (int)$ultima['ms'], 'erro' => $ultima['erro']] : null,
            'quedas' => array_slice(array_reverse($quedas), 0, 30),
            'baldes' => $baldes,
        ];
    }
}
