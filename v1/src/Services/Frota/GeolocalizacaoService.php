<?php

namespace Nutricional\Services\Frota;

class GeolocalizacaoService
{
    private $pdo;
    private $googleApiKey;
    
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->googleApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
    }
    
    /**
     * Buscar coordenadas em 2 níveis
     * NÍVEL 1: frota_cliente (mais confiável - GPS do motorista)
     * NÍVEL 2: Google Maps (com endereço completo)
     */
    public function buscarCoordenadas($clienteId, $enderecoCompleto)
    {
        // NÍVEL 1: Buscar na tabela frota_cliente
        $resultado = $this->buscarNaFrotaCliente($clienteId);
        if ($resultado['success']) {
            return $resultado;
        }
        
        // NÍVEL 2: Buscar no Google Maps (com endereço completo)
        if (!empty($enderecoCompleto)) {
            $resultado = $this->buscarNoGoogleMaps($enderecoCompleto);
            if ($resultado['success']) {
                return $resultado;
            }
        }
        
        // Se nada funcionar, tentar com endereço simplificado
        return $this->buscarNoGoogleMapsSimplificado($enderecoCompleto);
    }
    
    /**
     * NÍVEL 1: Buscar na tabela frota_cliente
     * Retorna coordenadas se existirem, com indicador se veio do GPS do motorista
     */
    public function buscarNaFrotaCliente($clienteId)
    {
        $stmt = $this->pdo->prepare("
            SELECT latitude, longitude, origem_coordenada, data_ultima_atualizacao
            FROM frota_cliente 
            WHERE erp_id = :cliente_id 
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute(['cliente_id' => $clienteId]);
        $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($cliente && !empty($cliente['latitude']) && !empty($cliente['longitude'])) {
            // Verifica se veio do motorista (GPS)
            $veioDoMotorista = in_array($cliente['origem_coordenada'], [
                'checkin_motorista', 
                'checkout_motorista'
            ]);
            
            // Verifica se a coordenada é recente (últimos 7 dias)
            $dataAtualizacao = $cliente['data_ultima_atualizacao'] ?? null;
            $recente = false;
            if ($dataAtualizacao) {
                $diferenca = time() - strtotime($dataAtualizacao);
                $recente = $diferenca < (7 * 24 * 60 * 60); // 7 dias
            }
            
            // Confiável se veio do motorista OU é recente
            $confiavel = $veioDoMotorista || $recente;
            
            $mensagem = '';
            if ($veioDoMotorista) {
                $mensagem = 'Coordenadas obtidas do GPS do motorista';
            } elseif ($recente) {
                $mensagem = 'Coordenadas atualizadas recentemente na frota_cliente';
            } else {
                $mensagem = 'Coordenadas obtidas da frota_cliente (pode estar desatualizada)';
            }
            
            return [
                'success' => true,
                'latitude' => (float)$cliente['latitude'],
                'longitude' => (float)$cliente['longitude'],
                'origem' => $cliente['origem_coordenada'] ?? 'frota_cliente',
                'confiavel' => $confiavel,
                'mensagem' => $mensagem,
                'data_atualizacao' => $dataAtualizacao
            ];
        }
        
        return ['success' => false];
    }
    
    /**
     * NÍVEL 2: Buscar no Google Maps (com endereço completo)
     */
    public function buscarNoGoogleMaps($enderecoCompleto)
    {
        if (empty($this->googleApiKey) || empty($enderecoCompleto)) {
            return ['success' => false, 'mensagem' => 'API Key ou endereço vazio'];
        }
        
        // Limpar e formatar endereço
        $enderecoFormatado = $this->formatarEndereco($enderecoCompleto);
        
        error_log("[Geolocalizacao] Buscando no Google Maps: {$enderecoFormatado}");
        
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . 
               urlencode($enderecoFormatado) . 
               "&key={$this->googleApiKey}&region=br&language=pt-BR";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("[Geolocalizacao] Erro HTTP: {$httpCode} - {$curlError}");
            return ['success' => false, 'mensagem' => "HTTP Error: {$httpCode}"];
        }
        
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $result = $data['results'][0];
            $lat = $result['geometry']['location']['lat'];
            $lng = $result['geometry']['location']['lng'];
            
            // Verificar precisão (tipo de resultado)
            $tipos = $result['types'] ?? [];
            $preciso = in_array('street_address', $tipos) || in_array('premise', $tipos);
            
            // Verificar se o endereço é completo (tem número)
            $addressComponents = $result['address_components'] ?? [];
            $temNumero = false;
            foreach ($addressComponents as $component) {
                if (in_array('street_number', $component['types'] ?? [])) {
                    $temNumero = true;
                    break;
                }
            }
            
            // Se não tem número, a precisão é menor
            if (!$temNumero) {
                $preciso = false;
            }
            
            // Verificar se o endereço é exato (tem nome da rua + número)
            $enderecoCompletoEncontrado = $result['formatted_address'] ?? '';
            
            return [
                'success' => true,
                'latitude' => $lat,
                'longitude' => $lng,
                'origem' => 'google_maps',
                'confiavel' => $preciso,
                'mensagem' => $preciso ? 
                    'Coordenadas precisas obtidas via Google Maps' : 
                    'Coordenadas aproximadas obtidas via Google Maps (endereço sem número ou genérico)',
                'endereco_encontrado' => $enderecoCompletoEncontrado
            ];
        }
        
        return [
            'success' => false, 
            'mensagem' => "Google Maps: {$data['status']} - " . ($data['error_message'] ?? 'Sem resultados')
        ];
    }
    
    /**
     * Tentativa com endereço simplificado (sem número)
     */
    private function buscarNoGoogleMapsSimplificado($enderecoCompleto)
    {
        if (empty($enderecoCompleto)) {
            return ['success' => false, 'mensagem' => 'Endereço vazio'];
        }
        
        // Remove número e complemento para tentar achar a rua/avenida
        $enderecoLimpo = preg_replace('/\s*,\s*\d+\s*.*$/', '', $enderecoCompleto);
        $enderecoLimpo = preg_replace('/\s*nº\s*\d+.*$/i', '', $enderecoLimpo);
        $enderecoLimpo = preg_replace('/\s*n[°º]\s*\d+.*$/i', '', $enderecoLimpo);
        $enderecoLimpo = trim($enderecoLimpo);
        
        if (empty($enderecoLimpo) || $enderecoLimpo === $enderecoCompleto) {
            return ['success' => false, 'mensagem' => 'Não foi possível simplificar o endereço'];
        }
        
        error_log("[Geolocalizacao] Tentando endereço simplificado: {$enderecoLimpo}");
        return $this->buscarNoGoogleMaps($enderecoLimpo);
    }
    
    /**
     * Formatar endereço para melhorar a geolocalização
     */
    private function formatarEndereco($endereco)
    {
        // Remove espaços extras
        $endereco = trim(preg_replace('/\s+/', ' ', $endereco));
        
        // Remove caracteres especiais
        $endereco = preg_replace('/[^\w\s\.,\-\(\)\/]/u', ' ', $endereco);
        
        // Remove múltiplas vírgulas
        $endereco = preg_replace('/,\s*,/', ',', $endereco);
        $endereco = trim($endereco, ', ');
        
        // Se não tiver "Brasil" no final, adiciona
        if (!preg_match('/brasil|brazil/i', $endereco)) {
            $endereco .= ', Brasil';
        }
        
        return $endereco;
    }
    
    /**
     * Geocodificar em lote (para importações em massa)
     */
    public function geocodificarLote($enderecos)
    {
        $resultados = [];
        
        foreach ($enderecos as $key => $endereco) {
            $resultado = $this->buscarNoGoogleMaps($endereco);
            $resultados[$key] = $resultado;
            
            // Delay para não exceder limite da API (50 requisições/segundo)
            usleep(200000); // 200ms
        }
        
        return $resultados;
    }
    
    /**
     * Buscar coordenadas por CEP (alternativa)
     */
    public function buscarPorCEP($cep)
    {
        if (empty($cep) || empty($this->googleApiKey)) {
            return ['success' => false];
        }
        
        $cepLimpo = preg_replace('/[^0-9]/', '', $cep);
        if (strlen($cepLimpo) !== 8) {
            return ['success' => false];
        }
        
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . 
               urlencode($cepLimpo) . 
               "&key={$this->googleApiKey}&region=br&language=pt-BR";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false];
        }
        
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $result = $data['results'][0];
            return [
                'success' => true,
                'latitude' => $result['geometry']['location']['lat'],
                'longitude' => $result['geometry']['location']['lng'],
                'origem' => 'google_maps_cep',
                'confiavel' => false,
                'mensagem' => 'Coordenadas obtidas via Google Maps pelo CEP (aproximado)'
            ];
        }
        
        return ['success' => false];
    }
    
    /**
     * Verificar se as coordenadas são válidas
     */
    public function validarCoordenadas($lat, $lng)
    {
        if ($lat === null || $lng === null) {
            return false;
        }
        
        $lat = (float)$lat;
        $lng = (float)$lng;
        
        // Latitude: -90 a 90
        // Longitude: -180 a 180
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }
        
        // Verificar se não é zero (0,0) que geralmente indica erro
        if ($lat == 0 && $lng == 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Calcular distância entre duas coordenadas (em km)
     */
    public function calcularDistancia($lat1, $lng1, $lat2, $lng2)
    {
        if (!$this->validarCoordenadas($lat1, $lng1) || !$this->validarCoordenadas($lat2, $lng2)) {
            return null;
        }
        
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distancia = $earthRadius * $c;
        
        return round($distancia, 2);
    }
}