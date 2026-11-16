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
    private const CACHE_TTL_SEGUNDOS = 20;
    private const RATE_LIMIT_BURST_SEGUNDOS = 3;

    private int $filial;
    private string $url;
    /** @var array<int,array>|null */
    private ?array $cache = null;
    /** @var array{cache_hit:bool,atualizado_em:?string,idade_segundos:?int,stale:bool} */
    private array $statusCache = [
        'cache_hit' => false,
        'atualizado_em' => null,
        'idade_segundos' => null,
        'stale' => false,
    ];

    public function __construct(int $filial)
    {
        $this->filial = $filial;
        $env = getenv('FLEX_API_URL_F' . $filial) ?: ($_ENV['FLEX_API_URL_F' . $filial] ?? '');
        $this->url = $env !== '' ? $env : (self::URL_PADRAO[$filial] ?? self::URL_PADRAO[6]);
    }

    private function arquivoCache(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nutricional_mercos_flex_f' . $this->filial . '.json';
    }

    /** @return array{ts:int,saldos:array<int,array>}|null */
    private function lerCacheDisco(): ?array
    {
        $arq = $this->arquivoCache();
        if (!is_file($arq)) {
            return null;
        }
        $raw = @file_get_contents($arq);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['ts'], $decoded['saldos']) || !is_array($decoded['saldos'])) {
            return null;
        }
        $mapa = [];
        foreach ($decoded['saldos'] as $k => $v) {
            $mapa[(int)$k] = $v;
        }
        return ['ts' => (int)$decoded['ts'], 'saldos' => $mapa];
    }

    /** @param array<int,array> $mapa */
    private function gravarCacheDisco(array $mapa): void
    {
        $arq = $this->arquivoCache();
        $payload = json_encode(['ts' => time(), 'saldos' => $mapa], JSON_UNESCAPED_UNICODE);
        if ($payload !== false) {
            @file_put_contents($arq, $payload, LOCK_EX);
        }
    }

    public function invalidarCacheDisco(): void
    {
        $this->cache = null;
        $arq = $this->arquivoCache();
        if (is_file($arq)) {
            @unlink($arq);
        }
    }

    /** @return array{cache_hit:bool,atualizado_em:?string,idade_segundos:?int,stale:bool} */
    public function ultimoStatusCache(): array
    {
        return $this->statusCache;
    }

    /** @return array{0:int,1:string} */
    private function chamar(string $metodo, ?array $corpo = null): array
    {
        $headers = ['Accept: application/json'];
        $token = \Nutricional\Services\SecretVault::get('FLEX_API_TOKEN');
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
    public function saldos(bool $forcar = false, bool $permitirStaleFallback = false): array
    {
        if ($this->cache !== null && !$forcar) {
            return $this->cache;
        }

        $disco = $this->lerCacheDisco();
        if ($disco !== null) {
            $idade = max(0, time() - $disco['ts']);
            $ttlEfetivo = $forcar ? self::RATE_LIMIT_BURST_SEGUNDOS : self::CACHE_TTL_SEGUNDOS;
            if ($idade <= $ttlEfetivo) {
                $this->statusCache = [
                    'cache_hit' => true,
                    'atualizado_em' => date('d/m/Y H:i:s', $disco['ts']),
                    'idade_segundos' => $idade,
                    'stale' => false,
                ];
                return $this->cache = $disco['saldos'];
            }
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
                    $this->gravarCacheDisco($mapa);
                    $this->statusCache = [
                        'cache_hit' => false,
                        'atualizado_em' => date('d/m/Y H:i:s'),
                        'idade_segundos' => 0,
                        'stale' => false,
                    ];
                    return $this->cache = $mapa;
                }
                $ultimo = new MercosIndisponivelException('Resposta invalida da API Mercos (HTTP ' . $status . ')');
                if ($status === 429) {
                    usleep($i * 600000);
                    continue;
                }
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

        if ($permitirStaleFallback && $disco !== null) {
            $idade = max(0, time() - $disco['ts']);
            $this->statusCache = [
                'cache_hit' => true,
                'atualizado_em' => date('d/m/Y H:i:s', $disco['ts']),
                'idade_segundos' => $idade,
                'stale' => true,
            ];
            return $this->cache = $disco['saldos'];
        }

        throw $ultimo;
    }

    /**
     * Teste de disponibilidade: um unico GET, sem retry, para medir a API como ela esta.
     * Nunca lanca excecao.
     *
     * @return array{ok:bool,http:int,ms:int,erro:?string,saldos:array<int,array>}
     */
    public function sonda(): array
    {
        $ini = microtime(true);
        $http = 0;
        $erro = null;
        $saldos = [];
        try {
            [$http, $corpo] = $this->chamar('GET');
            $dados = json_decode($corpo, true);
            if ($http >= 400) {
                $erro = 'HTTP ' . $http;
            } elseif (!is_array($dados)) {
                $erro = 'Resposta invalida (nao e JSON de lista)';
            } else {
                foreach ($dados as $item) {
                    if (isset($item['cliforemp_id'], $item['saldo_atual'])) {
                        $saldos[(int)$item['cliforemp_id']] = $item;
                    }
                }
                $this->cache = $saldos;
            }
        } catch (MercosIndisponivelException $e) {
            $erro = $e->getMessage();
        }

        return [
            'ok' => $erro === null,
            'http' => $http,
            'ms' => (int)round((microtime(true) - $ini) * 1000),
            'erro' => $erro,
            'saldos' => $saldos,
        ];
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
        $this->invalidarCacheDisco();
        $antes = $this->saldoDe($colaborador, true);
        if ($antes === null) {
            throw new MercosIndisponivelException('Representante sem saldo Flex na API Mercos.');
        }

        // Proteção preventiva contra rate-limit em operações sequenciais (ex: 3 etapas com gestor)
        usleep(180000);

        try {
            [$status, $corpo] = $this->chamar('POST', [
                'colaborador_id' => $colaborador,
                'valor_movimentado' => $valor,
                'observacao' => $observacao,
            ]);
            if ($status < 400) {
                $this->invalidarCacheDisco();
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
            $this->invalidarCacheDisco();
            $depois = $this->saldoDe($colaborador, true);
        } catch (MercosIndisponivelException $e) {
            throw new MercosIndisponivelException('Resultado da movimentacao incerto e saldo nao pode ser conferido; verifique manualmente.');
        }
        if ($depois !== null && abs(($antes + $valor) - $depois) < 0.005) {
            return;
        }
        throw new MercosIndisponivelException('A API Mercos nao confirmou a movimentacao.');
    }

    /**
     * Preparado para expansão futura: consulta extrato detalhado de movimentações se implementado no bridge.
     * Retorna array vazio defensivamente se o endpoint ainda não existir (HTTP 404/500).
     *
     * @param int $colaborador ID cliforemp do representante (ou 0 para todos)
     * @return array
     */
    public function buscarExtratoMercos(int $colaborador = 0): array
    {
        try {
            $pathExtrato = str_replace('/saldo_flex', '/extrato_flex', $this->url);
            $urlReq = $pathExtrato . ($colaborador > 0 ? '?colaborador_id=' . $colaborador : '');
            
            $ch = curl_init($urlReq);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $resp = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 && is_string($resp)) {
                $dados = json_decode($resp, true);
                if (is_array($dados)) {
                    return $dados;
                }
            }
        } catch (\Throwable $e) {
            error_log('[flex] falha sondagem extrato futuro: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Preparado para expansão futura: consulta flex gerado/consumido em pedidos se implementado no bridge.
     *
     * @param array $filtros Filtros adicionais (periodo, colaborador, pedido)
     * @return array
     */
    public function buscarPedidosFlexMercos(array $filtros = []): array
    {
        try {
            $pathPedidos = str_replace('/saldo_flex', '/pedidos_flex', $this->url);
            $query = !empty($filtros) ? '?' . http_build_query($filtros) : '';
            
            $ch = curl_init($pathPedidos . $query);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $resp = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 && is_string($resp)) {
                $dados = json_decode($resp, true);
                if (is_array($dados)) {
                    return $dados;
                }
            }
        } catch (\Throwable $e) {
            error_log('[flex] falha sondagem pedidos flex futuro: ' . $e->getMessage());
        }
        return [];
    }
}
