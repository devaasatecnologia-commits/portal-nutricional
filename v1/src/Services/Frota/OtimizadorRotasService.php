<?php
// src/Services/Frota/OtimizadorRotasService.php

namespace Nutricional\Services\Frota;

class OtimizadorRotasService
{
    private $pdo;
    private $geolocalizacaoService;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->geolocalizacaoService = new GeolocalizacaoService($pdo);
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
                    c.latitude,
                    c.longitude,
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
                if (empty($entrega['latitude']) || empty($entrega['longitude'])) {
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
            
            // 🔥 PASSO 2: Algoritmo do vizinho mais próximo
            $rotaOtimizada = $this->vizinhoMaisProximo($entregas);
            
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
            
            // Calcular métricas
            $metricas = $this->calcularMetricas($rotaOtimizada);
            
            return [
                'success' => true,
                'data' => [
                    'embarque_id' => $embarqueId,
                    'total_entregas' => count($rotaOtimizada),
                    'ordem_anterior' => array_column($entregas, 'id'),
                    'nova_ordem' => array_column($rotaOtimizada, 'id')
                ],
                'rota' => $rotaOtimizada,
                'metricas' => $metricas
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Algoritmo do vizinho mais próximo
     */
    private function vizinhoMaisProximo(array $entregas): array
    {
        if (empty($entregas)) return [];
        
        $naoVisitados = $entregas;
        $rota = [];
        
        // Começar com a primeira entrega
        $primeiro = array_shift($naoVisitados);
        $rota[] = $primeiro;
        $atual = $primeiro;
        
        while (!empty($naoVisitados)) {
            $maisProximo = null;
            $menorDistancia = PHP_FLOAT_MAX;
            $indiceMaisProximo = 0;
            
            foreach ($naoVisitados as $index => $entrega) {
                $distancia = $this->calcularDistancia(
                    $atual['latitude'],
                    $atual['longitude'],
                    $entrega['latitude'],
                    $entrega['longitude']
                );
                
                if ($distancia < $menorDistancia) {
                    $menorDistancia = $distancia;
                    $maisProximo = $entrega;
                    $indiceMaisProximo = $index;
                }
            }
            
            if ($maisProximo) {
                $rota[] = $maisProximo;
                $atual = $maisProximo;
                unset($naoVisitados[$indiceMaisProximo]);
                $naoVisitados = array_values($naoVisitados);
            }
        }
        
        return $rota;
    }
    
    /**
     * Calcula métricas da rota
     */
    private function calcularMetricas(array $rota): array
    {
        $distanciaTotal = 0;
        $tempoTotal = 0;
        
        for ($i = 0; $i < count($rota) - 1; $i++) {
            $distancia = $this->calcularDistancia(
                $rota[$i]['latitude'],
                $rota[$i]['longitude'],
                $rota[$i + 1]['latitude'],
                $rota[$i + 1]['longitude']
            );
            $distanciaTotal += $distancia;
            $tempoTotal += ($distancia / 40) * 60; // 40km/h em minutos
        }
        
        return [
            'distancia_total_km' => round($distanciaTotal, 2),
            'tempo_estimado_min' => round($tempoTotal, 1),
            'tempo_estimado_horas' => round($tempoTotal / 60, 1),
            'media_km_entrega' => round($distanciaTotal / max(1, count($rota)), 2)
        ];
    }
    
    /**
     * Calcula distância entre dois pontos (Haversine)
     */
    private function calcularDistancia($lat1, $lng1, $lat2, $lng2): float
    {
        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) {
            return 0;
        }
        
        $R = 6371; // Raio da Terra em km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}