<?php
// src/Services/Frota/OtimizadorRotasService.php

namespace Nutricional\Services\Frota;

class OtimizadorRotasService
{
    private const ORIGEM_LATITUDE = -28.979438954992666;
    private const ORIGEM_LONGITUDE = -49.53561648427039;

    private $pdo;
    private $geolocalizacaoService;
    private $routingService;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->geolocalizacaoService = new GeolocalizacaoService($pdo);
        $this->routingService = new ValhallaRoutingService();
    }
    
    /**
     * Otimiza a rota do embarque, tentando geolocalizar entregas sem coordenadas
     */
    public function otimizar(int $embarqueId): array
    {
        try {
            // Buscar todas as entregas do embarque
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id,
                    e.cliente_id,
                    e.endereco,
                    e.numero,
                    e.bairro,
                    e.cidade,
                    e.uf,
                    e.cep,
                    e.ordem_entrega,
                    COALESCE(e.latitude, c.latitude) AS latitude,
                    COALESCE(e.longitude, c.longitude) AS longitude,
                    c.nome as cliente_nome,
                    c.erp_id
                FROM frota_entrega e
                LEFT JOIN frota_cliente c ON c.id = e.cliente_id
                WHERE e.embarque_id = :embarque_id
                ORDER BY e.ordem_entrega ASC
            ");
            $stmt->execute(['embarque_id' => $embarqueId]);
            $entregas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($entregas)) {
                return ['success' => false, 'error' => 'Nenhuma entrega encontrada para este embarque'];
            }
            
            // 🔥 PASSO 1: Geolocalizar entregas sem coordenadas
            $semCoordenadas = [];
            foreach ($entregas as &$entrega) {
                if (!is_numeric($entrega['latitude']) || !is_numeric($entrega['longitude'])) {
                    // NÍVEL 1: Buscar na frota_cliente
                    $resultado = $this->geolocalizacaoService->buscarNaFrotaCliente($entrega['erp_id'] ?? null);
                    
                    if ($resultado['success']) {
                        // Atualiza a entrega com as coordenadas encontradas
                        $this->pdo->prepare("
                            UPDATE frota_entrega 
                            SET latitude = :lat, longitude = :lng,
                                origem_geolocalizacao = 'frota_cliente',
                                status_geolocalizacao = 'valido',
                                mensagem_geolocalizacao = :mensagem,
                                updated_at = NOW()
                            WHERE id = :id
                        ")->execute([
                            'lat' => $resultado['latitude'],
                            'lng' => $resultado['longitude'],
                            'mensagem' => $resultado['mensagem'] ?? 'Coordenadas obtidas da frota_cliente',
                            'id' => $entrega['id']
                        ]);
                        $entrega['latitude'] = $resultado['latitude'];
                        $entrega['longitude'] = $resultado['longitude'];
                    } else {
                        // NÍVEL 2: Google Maps com endereço completo
                        $enderecoCompleto = trim(
                            ($entrega['endereco'] ?? '') .
                            (!empty($entrega['numero']) ? ', ' . $entrega['numero'] : '') .
                            (!empty($entrega['bairro']) ? ', ' . $entrega['bairro'] : '') .
                            ', ' . ($entrega['cidade'] ?? '') .
                            ', ' . ($entrega['uf'] ?? '') .
                            (!empty($entrega['cep']) ? ', CEP: ' . $entrega['cep'] : '')
                        );
                        
                        $resultado = $this->geolocalizacaoService->buscarNoGoogleMaps($enderecoCompleto);
                        if ($resultado['success']) {
                            $this->pdo->prepare("
                                UPDATE frota_entrega 
                                SET latitude = :lat, longitude = :lng,
                                    origem_geolocalizacao = 'google_maps',
                                    status_geolocalizacao = 'valido',
                                    mensagem_geolocalizacao = :mensagem,
                                    updated_at = NOW()
                                WHERE id = :id
                            ")->execute([
                                'lat' => $resultado['latitude'],
                                'lng' => $resultado['longitude'],
                                'mensagem' => $resultado['mensagem'] ?? 'Coordenadas obtidas via Google Maps',
                                'id' => $entrega['id']
                            ]);
                            $entrega['latitude'] = $resultado['latitude'];
                            $entrega['longitude'] = $resultado['longitude'];
                        } else {
                            // NÍVEL 3: Busca simplificada (sem número)
                            $resultado = $this->geolocalizacaoService->buscarNoGoogleMapsSimplificado($enderecoCompleto);
                            if ($resultado['success']) {
                                $this->pdo->prepare("
                                    UPDATE frota_entrega 
                                    SET latitude = :lat, longitude = :lng,
                                        origem_geolocalizacao = 'google_maps_simplificado',
                                        status_geolocalizacao = 'pendente_confirmacao',
                                        mensagem_geolocalizacao = :mensagem,
                                        updated_at = NOW()
                                    WHERE id = :id
                                ")->execute([
                                    'lat' => $resultado['latitude'],
                                    'lng' => $resultado['longitude'],
                                    'mensagem' => $resultado['mensagem'] ?? 'Coordenadas aproximadas',
                                    'id' => $entrega['id']
                                ]);
                                $entrega['latitude'] = $resultado['latitude'];
                                $entrega['longitude'] = $resultado['longitude'];
                            } else {
                                $semCoordenadas[] = $entrega['id'];
                            }
                        }
                    }
                }
            }
            
            // Se ainda houver entregas sem coordenadas, retorna erro
            if (!empty($semCoordenadas)) {
                return [
                    'success' => false,
                    'error' => 'Não foi possível geolocalizar as seguintes entregas: ' . implode(', ', $semCoordenadas),
                    'entregas_sem_coordenadas' => $semCoordenadas
                ];
            }
            
            $pontos = [[
                'latitude' => self::ORIGEM_LATITUDE,
                'longitude' => self::ORIGEM_LONGITUDE
            ]];
            foreach ($entregas as $entrega) {
                $pontos[] = [
                    'latitude' => $entrega['latitude'],
                    'longitude' => $entrega['longitude']
                ];
            }

            $resultadoMatriz = $this->routingService->timeDistanceMatrix($pontos);
            if (!$resultadoMatriz['success']) {
                return $resultadoMatriz;
            }

            $ordemIndices = $this->encontrarOrdemInicial($resultadoMatriz['matrix'], count($entregas));
            if ($ordemIndices === null) {
                return [
                    'success' => false,
                    'error' => 'Não foi possível encontrar um trajeto rodoviário entre o CD e todas as entregas.'
                ];
            }

            $ordemIndices = $this->melhorarOrdemPorTempo($ordemIndices, $resultadoMatriz['matrix']);
            $metricas = $this->calcularMetricasRodoviarias($ordemIndices, $resultadoMatriz['matrix']);
            if ($metricas === null) {
                return [
                    'success' => false,
                    'error' => 'Não foi possível calcular a rota completa de ida e volta ao CD.'
                ];
            }

            $rotaOtimizada = array_map(
                static fn (int $indice): array => $entregas[$indice - 1],
                $ordemIndices
            );
            $pontosGeometria = array_merge(
                [[
                    'latitude' => self::ORIGEM_LATITUDE,
                    'longitude' => self::ORIGEM_LONGITUDE
                ]],
                array_map(static fn (array $entrega): array => [
                    'latitude' => $entrega['latitude'],
                    'longitude' => $entrega['longitude']
                ], $rotaOtimizada),
                [[
                    'latitude' => self::ORIGEM_LATITUDE,
                    'longitude' => self::ORIGEM_LONGITUDE
                ]]
            );
            $resultadoGeometria = $this->routingService->route($pontosGeometria);
            $avisos = $resultadoMatriz['warnings'] ?? [];
            if (!$resultadoGeometria['success'] || empty($resultadoGeometria['shape'])) {
                $avisos[] = $resultadoGeometria['error'] ?? 'A geometria rodoviária não foi retornada.';
            }
            
            // Atualizar a ordem das entregas no banco
            $this->pdo->beginTransaction();
            try {
                foreach ($rotaOtimizada as $ordem => $entrega) {
                    $this->pdo->prepare("
                        UPDATE frota_entrega 
                        SET ordem_entrega = :ordem,
                            updated_at = NOW()
                        WHERE id = :id
                    ")->execute([
                        'ordem' => $ordem + 1,
                        'id' => $entrega['id']
                    ]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            
            return [
                'success' => true,
                'data' => [
                    'embarque_id' => $embarqueId,
                    'total_entregas' => count($rotaOtimizada),
                    'ordem_anterior' => array_column($entregas, 'id'),
                    'nova_ordem' => array_column($rotaOtimizada, 'id'),
                    'provedor' => $resultadoMatriz['provider'],
                    'perfil' => $resultadoMatriz['profile'],
                    'criterio' => 'menor_tempo_rodoviario',
                    'inclui_retorno_ao_cd' => true,
                    'avisos' => $avisos
                ],
                'rota' => $rotaOtimizada,
                'geometria' => $resultadoGeometria['shape'] ?? [],
                'metricas' => $metricas
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function encontrarOrdemInicial(array $matriz, int $totalEntregas): ?array
    {
        $naoVisitados = range(1, $totalEntregas);
        $ordem = [];
        $atual = 0;

        while ($naoVisitados !== []) {
            $melhorIndice = null;
            $menorTempo = PHP_FLOAT_MAX;

            foreach ($naoVisitados as $indice) {
                $tempo = $matriz[$atual][$indice]['time_seconds'] ?? null;
                if (is_numeric($tempo) && $tempo < $menorTempo) {
                    $menorTempo = $tempo;
                    $melhorIndice = $indice;
                }
            }

            if ($melhorIndice === null) {
                return null;
            }

            $ordem[] = $melhorIndice;
            $atual = $melhorIndice;
            $naoVisitados = array_values(array_diff($naoVisitados, [$melhorIndice]));
        }

        return $ordem;
    }

    private function melhorarOrdemPorTempo(array $ordem, array $matriz): array
    {
        $melhorou = true;
        while ($melhorou) {
            $melhorou = false;
            $metricasAtuais = $this->calcularMetricasRodoviarias($ordem, $matriz);
            if ($metricasAtuais === null) {
                return $ordem;
            }

            for ($inicio = 0; $inicio < count($ordem) - 1; $inicio++) {
                for ($fim = $inicio + 1; $fim < count($ordem); $fim++) {
                    $candidato = $ordem;
                    array_splice(
                        $candidato,
                        $inicio,
                        $fim - $inicio + 1,
                        array_reverse(array_slice($ordem, $inicio, $fim - $inicio + 1))
                    );
                    $metricasCandidato = $this->calcularMetricasRodoviarias($candidato, $matriz);

                    if (
                        $metricasCandidato !== null
                        && $metricasCandidato['tempo_total_segundos'] < $metricasAtuais['tempo_total_segundos'] - 0.01
                    ) {
                        $ordem = $candidato;
                        $melhorou = true;
                        break 2;
                    }
                }
            }
        }

        return $ordem;
    }

    private function calcularMetricasRodoviarias(array $ordem, array $matriz): ?array
    {
        $pontos = array_merge([0], $ordem, [0]);
        $distanciaTotal = 0.0;
        $tempoTotal = 0.0;

        for ($i = 0; $i < count($pontos) - 1; $i++) {
            $trecho = $matriz[$pontos[$i]][$pontos[$i + 1]] ?? null;
            if (
                !is_array($trecho)
                || !is_numeric($trecho['time_seconds'] ?? null)
                || !is_numeric($trecho['distance_km'] ?? null)
            ) {
                return null;
            }

            $tempoTotal += (float)$trecho['time_seconds'];
            $distanciaTotal += (float)$trecho['distance_km'];
        }

        return [
            'distancia_total_km' => round($distanciaTotal, 2),
            'tempo_estimado_min' => round($tempoTotal / 60, 1),
            'tempo_estimado_horas' => round($tempoTotal / 3600, 1),
            'media_km_entrega' => round($distanciaTotal / max(1, count($ordem)), 2),
            'tempo_total_segundos' => $tempoTotal,
            'provedor' => 'valhalla',
            'perfil' => 'truck'
        ];
    }
}