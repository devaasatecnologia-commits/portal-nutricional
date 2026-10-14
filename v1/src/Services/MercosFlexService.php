<?php
namespace Nutricional\Services;

/**
 * Cliente da API Mercos de saldo Flex (/saldo_flex), uma instancia por filial.
 *
 * - GET: ate 3 tentativas com espera crescente (falha de rede ou HTTP 5xx).
 * - POST: nunca repete sozinho, pois repetir poderia debitar duas vezes.
 *   Quando o resultado do POST e incerto (timeout), conferimos o saldo para saber se foi aplicado.
 * - Token opcional por .env (FLEX_API_TOKEN) e URL por filial (FLEX_API_URL_F{filial}).
 * - Cada chamada vai para o error_log com tempo e status, sem dados sensiveis.
 */
class MercosFlexService
{
    private const URL_PADRAO = [
        1 => 'http://mercosf1.nutricionalbr.com:8081/saldo_flex',
        6 => 'http://mercosf1.nutricionalbr.com:8086/saldo_flex',
    ];
    private const TENTATIVAS_GET = 3;

    private int $filial;
    private string $url;
    /** @var array<int,array>|null */
    private ?array $cache = null;

    public function __construct(int $filial)
    {
        $this->filial = $filial;
        $env = getenv('FLEX_API_URL_F' . $filial) ?: ($_ENV['FLEX_API_URL_F' . $filial] ?? '');
        $this->url = $env !== '' ? $env : (self::URL_PADRAO[$filial] ?? self::URL_PADRAO[6]);
    }

    /** @return array{0:int,1:string} */
    private function chamar(string $metodo, ?array $corpo = null): array
    {
        $headers = ['Accept: application/json'];
        $token = getenv('FLEX_API_TOKEN') ?: ($_ENV['FLEX_API_TOKEN'] ?? '');
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($corpo !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $metodo === 'GET' ? 12 : 25,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
        }

        $ini = microtime(true);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);
        error_log(sprintf('[flex] f%d %s %d %.0fms%s', $this->filial, $metodo, $status, (microtime(true) - $ini) * 1000, $erro ? " erro=$erro" : ''));

        if ($resp === false) {
            throw new MercosIndisponivelException('API Mercos indisponivel: ' . $erro);
        }
        return [$status, (string)$resp];
    }

    /** @return array<int,array> saldos indexados por cliforemp_id */
    public function saldos(bool $forcar = false): array
    {
        if ($this->cache !== null && !$forcar) {
            return $this->cache;
        }

        $ultimo = null;
        for ($i = 1; $i <= self::TENTATIVAS_GET; $i++) {
            try {
                [$status, $corpo] = $this->chamar('GET');
                $dados = json_decode($corpo, true);
                if ($status < 400 && is_array($dados)) {
                    $mapa = [];
                    foreach ($dados as $item) {
                        if (isset($item['cliforemp_id'], $item['saldo_atual'])) {
                            $mapa[(int)$item['cliforemp_id']] = $item;
                        }
                    }
                    return $this->cache = $mapa;
                }
                $ultimo = new MercosIndisponivelException('Resposta invalida da API Mercos (HTTP ' . $status . ')');
                if ($status >= 400 && $status < 500) {
                    break; // erro do cliente nao melhora tentando de novo
                }
            } catch (MercosIndisponivelException $e) {
                $ultimo = $e;
            }
            if ($i < self::TENTATIVAS_GET) {
                usleep($i * 400000);
            }
        }
        throw $ultimo;
    }

    public function saldoDe(int $id, bool $forcar = false): ?float
    {
        $item = $this->saldos($forcar)[$id] ?? null;
        return $item === null ? null : (float)$item['saldo_atual'];
    }

    /**
     * Aplica uma movimentacao (negativa = debito). Se a resposta for incerta, confere o saldo:
     * se mudou como esperado, considera aplicada.
     */
    public function movimentar(int $colaborador, float $valor, string $observacao): void
    {
        $antes = $this->saldoDe($colaborador, true);
        if ($antes === null) {
            throw new MercosIndisponivelException('Representante sem saldo Flex na API Mercos.');
        }

        try {
            [$status, $corpo] = $this->chamar('POST', [
                'colaborador_id' => $colaborador,
                'valor_movimentado' => $valor,
                'observacao' => $observacao,
            ]);
            if ($status < 400) {
                return;
            }
            if ($status < 500) {
                throw new MercosRecusouException('API Mercos recusou a movimentacao (HTTP ' . $status . '): ' . mb_substr($corpo, 0, 200));
            }
        } catch (MercosRecusouException $e) {
            throw $e;
        } catch (MercosIndisponivelException $e) {
            // segue para a conferencia abaixo
        }

        try {
            $depois = $this->saldoDe($colaborador, true);
        } catch (MercosIndisponivelException $e) {
            throw new MercosIndisponivelException('Resultado da movimentacao incerto e saldo nao pode ser conferido; verifique manualmente.');
        }
        if ($depois !== null && abs(($antes + $valor) - $depois) < 0.005) {
            return;
        }
        throw new MercosIndisponivelException('A API Mercos nao confirmou a movimentacao.');
    }
}
