<?php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Health check do módulo Frota.
 *
 * 🔥 NOVO 2026-09-30 (Fase 1.5):
 *   - Endpoint público (`/v1/frota/health`) para monitoramento externo.
 *   - Verifica: DB (latency), Cobli (configurado + conexão, com cache 60s),
 *     versão do SW do motorista, fila offline agregada.
 *   - Responde sempre 200, mesmo se algum check falhar. O status geral
 *     fica em `data.status`: "ok" | "degraded" | "down".
 *     Motivo: o UptimeRobot (ou similar) só entende 200 = "no ar".
 *     Se a gente devolvesse 500 quando a Cobli cai, o monitor dispararia
 *     alerta falso-positivo — a API está no ar, só a Cobli caiu.
 */
class HealthController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    /**
     * GET /v1/frota/health
     */
    public function check(Request $request, Response $response): Response
    {
        $inicio = microtime(true);

        $checks = [
            'db'                => $this->checkDb(),
            'cobli'             => $this->checkCobli(),
            'sw_version'        => $this->getSwVersion(),
            'fila_offline_count'=> $this->getFilaOfflineCount(),
        ];

        // ── Status geral
        $status = 'ok';
        if (!$checks['db']['ok']) {
            $status = 'down';
        } elseif (!$checks['cobli']['ok']) {
            $status = 'degraded';
        }

        // ── Latência total do endpoint
        $latencyTotal = (int)round((microtime(true) - $inicio) * 1000);

        $payload = [
            'success'   => true,
            'status'    => $status,
            'timestamp' => date('c'),                    // ISO 8601
            'versao'    => 'v1',
            'latency_ms'=> $latencyTotal,
            'checks'    => $checks,
        ];

        // Sempre 200 — o `status` interno é quem diz o estado.
        // (Assim o UptimeRobot não dispara alerta falso quando só a Cobli cai.)
        return $this->json($response, $payload, 200);
    }

    // =================================================================
    // CHECKS INDIVIDUAIS
    // =================================================================

    /**
     * DB: executa um SELECT 1 e mede a latência.
     */
    private function checkDb(): array
    {
        $inicio = microtime(true);
        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
            $latency = (int)round((microtime(true) - $inicio) * 1000);
            return [
                'ok'         => true,
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'         => false,
                'latency_ms' => (int)round((microtime(true) - $inicio) * 1000),
                'error'      => substr($e->getMessage(), 0, 200),
            ];
        }
    }

    /**
     * Cobli: verifica se está configurada e se o último status conhecido
     * está OK. Usa cache em arquivo por 60s para não bater na API
     * externa a cada health check (evita latência de 1-2s por request).
     */
    private function checkCobli(): array
    {
        $cacheFile = sys_get_temp_dir() . '/frota_health_cobli.json';
        $cacheTtl  = 60; // segundos

        // ── Cache hit
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['ok'])) {
                $cached['fonte'] = 'cache';
                return $cached;
            }
        }

        // ── Cache miss: verifica de verdade
        $resultado = [
            'configurado' => false,
            'ok'          => false,
            'latency_ms'  => 0,
            'fonte'       => 'live',
        ];

        try {
            $configurado = $this->getConfig('cobli_api_key');
            $resultado['configurado'] = !empty($configurado);

            if (!$resultado['configurado']) {
                $resultado['error'] = 'Chave de API não configurada';
                return $resultado;
            }

            $inicio = microtime(true);

            // Reusa o CobliService (testarConexao já faz um GET leve)
            $cobli = new \Nutricional\Services\Frota\CobliService($this->pdo);
            $teste = $cobli->testarConexao();

            $resultado['latency_ms'] = (int)round((microtime(true) - $inicio) * 1000);
            $resultado['ok'] = (bool)($teste['success'] ?? false);

            if (!$resultado['ok']) {
                $resultado['error'] = substr((string)($teste['error'] ?? 'Falha desconhecida'), 0, 200);
            }
        } catch (\Throwable $e) {
            $resultado['ok'] = false;
            $resultado['error'] = substr($e->getMessage(), 0, 200);
        }

        // Salva no cache só se a resposta é confiável (evita cachear falha
        // de rede por 60s e ficar mostrando down desnecessariamente)
        if ($resultado['ok'] || $resultado['configurado'] === false) {
            @file_put_contents($cacheFile, json_encode($resultado));
        }

        return $resultado;
    }

    /**
     * Versão atual do Service Worker do motorista.
     * Lê do arquivo físico em `portal/modules/frota/service-worker.js`.
     */
    private function getSwVersion(): ?string
    {
        try {
            $swPath = __DIR__ . '/../../../../portal/modules/frota/service-worker.js';
            if (!file_exists($swPath)) return null;

            $conteudo = file_get_contents($swPath, false, null, 0, 1000);
            if (preg_match("/CACHE_NAME\s*=\s*'([^']+)'/", $conteudo, $m)) {
                return $m[1];
            }
        } catch (\Throwable $e) {
            // silencioso
        }
        return null;
    }

    /**
     * Quantas operações estão pendentes na fila offline (agregado global).
     * Lê de `frota_operacao_offline` (tabela de idempotência).
     * Se a tabela não existir, retorna null.
     */
    private function getFilaOfflineCount(): ?int
    {
        try {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM frota_operacao_offline
                WHERE created_at >= NOW() - INTERVAL '24 hours'
            ");
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =================================================================
    // HELPERS
    // =================================================================

    private function getConfig(string $chave): ?string
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT valor FROM frota_configuracao WHERE chave = :chave
            ");
            $stmt->execute(['chave' => $chave]);
            $v = $stmt->fetchColumn();
            return $v === false ? null : (string)$v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}