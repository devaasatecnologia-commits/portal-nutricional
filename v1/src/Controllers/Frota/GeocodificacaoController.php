<?php

namespace Nutricional\Controllers\Frota;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GeocodificacaoController
{
    private $pdo;
    private $geolocalizacaoService;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->geolocalizacaoService = new \Nutricional\Services\Frota\GeolocalizacaoService($this->pdo);
    }
    
    /**
     * POST /v1/frota/geocodificar
     * Geocodificar um endereço manualmente
     */
    public function geocodificar(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        
        $endereco = $input['endereco'] ?? '';
        $clienteErpId = (int)($input['cliente_erp_id'] ?? 0);
        
        if (empty($endereco)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Endereço é obrigatório'
            ], 400);
        }
        
        try {
            // 🔥 CORREÇÃO: Usar o método correto que existe na classe
            $resultado = $this->geolocalizacaoService->buscarNoGoogleMaps($endereco);
            
            // Se a geocodificação foi bem-sucedida, adicionar metadados
            if ($resultado['success']) {
                // O método já retorna 'origem', 'confiavel' e 'mensagem'
                // Mas podemos complementar ou padronizar
                $resultado['origem'] = $resultado['origem'] ?? 'google_maps';
                $resultado['confiavel'] = $resultado['confiavel'] ?? true;
                $resultado['mensagem'] = $resultado['mensagem'] ?? 'Coordenadas obtidas via Google Maps';
            } else {
                // Se falhou, podemos tentar um fallback (ex: por CEP ou simplificado)
                // O próprio service já faz tentativas com endereço simplificado
                // Se ainda assim falhar, retornamos o erro
                $resultado['mensagem'] = $resultado['mensagem'] ?? 'Falha ao geocodificar o endereço';
            }
            
            return $this->json($response, $resultado);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resposta JSON padronizada
     */
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}