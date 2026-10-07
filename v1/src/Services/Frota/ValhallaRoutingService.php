<?php

namespace Nutricional\Services\Frota;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ValhallaRoutingService
{
    private Client $client;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim($_ENV['VALHALLA_URL'] ?? 'http://localhost:8002', '/');
        $this->timeout = (int)($_ENV['VALHALLA_TIMEOUT'] ?? 12);
        $this->client = new Client(['timeout' => $this->timeout]);
    }

    public function route(array $points, array $vehicle = []): array
    {
        if (count($points) < 2) {
            return ['success' => false, 'error' => 'A rota precisa de pelo menos dois pontos'];
        }

        $locations = array_map(static function (array $point): array {
            return [
                'lat' => (float)$point['latitude'],
                'lon' => (float)$point['longitude'],
                'type' => 'break'
            ];
        }, $points);

        $payload = [
            'locations' => $locations,
            'costing' => 'truck',
            'units' => 'kilometers',
            'directions_options' => ['units' => 'kilometers'],
            'costing_options' => ['truck' => $this->costingOptions($vehicle)]
        ];

        try {
            $response = $this->client->post($this->baseUrl . '/route', [
                'headers' => ['Accept' => 'application/json'],
                'json' => $payload
            ]);
            $data = json_decode((string)$response->getBody(), true);
            if (!is_array($data) || empty($data['trip'])) {
                return ['success' => false, 'error' => 'Valhalla retornou uma rota inválida'];
            }

            $trip = $data['trip'];
            return [
                'success' => true,
                'provider' => 'valhalla',
                'profile' => 'truck',
                'distance_km' => (float)($trip['summary']['length'] ?? 0),
                'duration_seconds' => (int)($trip['summary']['time'] ?? 0),
                'shape' => $this->decodeShape($trip['legs'] ?? []),
                'legs' => $trip['legs'] ?? [],
                'raw' => $data
            ];
        } catch (GuzzleException $exception) {
            error_log('Valhalla indisponível: ' . $exception->getMessage());
            return ['success' => false, 'error' => 'Serviço de roteamento Valhalla indisponível'];
        }
    }

    public function timeDistanceMatrix(array $points, array $vehicle = []): array
    {
        if (count($points) < 2) {
            return ['success' => false, 'error' => 'A matriz precisa de pelo menos dois pontos'];
        }

        $locations = [];
        foreach ($points as $point) {
            $latitude = $point['latitude'] ?? null;
            $longitude = $point['longitude'] ?? null;
            if (
                !is_numeric($latitude)
                || !is_numeric($longitude)
                || (float)$latitude < -90
                || (float)$latitude > 90
                || (float)$longitude < -180
                || (float)$longitude > 180
            ) {
                return ['success' => false, 'error' => 'A rota contém coordenadas inválidas'];
            }

            $locations[] = ['lat' => (float)$latitude, 'lon' => (float)$longitude];
        }

        $payload = [
            'sources' => $locations,
            'targets' => $locations,
            'costing' => 'truck',
            'units' => 'kilometers',
            'verbose' => true,
            'costing_options' => ['truck' => $this->costingOptions($vehicle)]
        ];

        try {
            $response = $this->client->post($this->baseUrl . '/sources_to_targets', [
                'headers' => ['Accept' => 'application/json'],
                'json' => $payload
            ]);
            $data = json_decode((string)$response->getBody(), true);
            $cells = $data['sources_to_targets'] ?? null;
            $pointCount = count($locations);

            if (!is_array($cells) || count($cells) !== $pointCount * $pointCount) {
                return ['success' => false, 'error' => 'Valhalla retornou uma matriz de rotas inválida'];
            }

            $matrix = array_fill(0, $pointCount, []);
            foreach ($cells as $index => $cell) {
                if (!is_array($cell)) {
                    return ['success' => false, 'error' => 'Valhalla retornou uma matriz de rotas inválida'];
                }

                $from = intdiv($index, $pointCount);
                $to = $index % $pointCount;
                $time = $cell['time'] ?? null;
                $distance = $cell['distance'] ?? null;
                if (
                    ($time !== null && (!is_numeric($time) || (float)$time < 0))
                    || ($distance !== null && (!is_numeric($distance) || (float)$distance < 0))
                ) {
                    return ['success' => false, 'error' => 'Valhalla retornou métricas de rota inválidas'];
                }

                $matrix[$from][$to] = [
                    'time_seconds' => is_numeric($time) ? (float)$time : null,
                    'distance_km' => is_numeric($distance) ? (float)$distance : null
                ];
            }

            return [
                'success' => true,
                'provider' => 'valhalla',
                'profile' => 'truck',
                'matrix' => $matrix,
                'warnings' => is_array($data['warnings'] ?? null) ? $data['warnings'] : []
            ];
        } catch (GuzzleException $exception) {
            error_log('Matriz Valhalla indisponível: ' . $exception->getMessage());
            return [
                'success' => false,
                'code' => 'service_unavailable',
                'error' => 'Serviço de roteamento Valhalla indisponível'
            ];
        }
    }

    private function costingOptions(array $vehicle): array
    {
        return array_filter([
            'height' => $this->number($vehicle['altura'] ?? $_ENV['TRUCK_HEIGHT'] ?? null),
            'width' => $this->number($vehicle['largura'] ?? $_ENV['TRUCK_WIDTH'] ?? null),
            'length' => $this->number($vehicle['comprimento'] ?? $_ENV['TRUCK_LENGTH'] ?? null),
            'weight' => $this->number($vehicle['peso'] ?? $vehicle['capacidade_peso'] ?? $_ENV['TRUCK_WEIGHT'] ?? null),
            'fixed_speed' => $this->number($_ENV['TRUCK_FIXED_SPEED'] ?? null)
        ], static fn ($value) => $value !== null);
    }

    private function number($value): ?float
    {
        return $value === null || $value === '' ? null : (float)$value;
    }

    private function decodeShape(array $legs): array
    {
        $shape = [];
        foreach ($legs as $leg) {
            foreach ($this->decodePolyline((string)($leg['shape'] ?? '')) as $point) {
                $shape[] = $point;
            }
        }
        return $shape;
    }

    private function decodePolyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $latitude = 0;
        $longitude = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            $latitude += $this->decodeValue($encoded, $index);
            $longitude += $this->decodeValue($encoded, $index);
            $points[] = ['latitude' => $latitude / 1e6, 'longitude' => $longitude / 1e6];
        }

        return $points;
    }

    private function decodeValue(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($encoded);
        do {
            if ($index >= $length) return 0;
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1f) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? -($result >> 1) : ($result >> 1);
    }
}
