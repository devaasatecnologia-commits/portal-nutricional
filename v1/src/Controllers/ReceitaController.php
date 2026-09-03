<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;

class ReceitaController
{
    /**
     * POST /v1/receita/consultar
     * Busca dados de CNPJ na Receita Federal
     */
    public function consultar(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $cnpj = trim($input['cnpj'] ?? '');
        
        if (empty($cnpj)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'CNPJ é obrigatório'
            ], 400);
        }
        
        // Limpar CNPJ
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) !== 14) {
            return $this->json($response, [
                'success' => false,
                'error' => 'CNPJ inválido (deve ter 14 dígitos)'
            ], 400);
        }
        
        // Validar CNPJ (módulo 11)
        if (!$this->validarCNPJ($cnpj)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'CNPJ inválido (dígitos verificadores não conferem)'
            ], 400);
        }
        
        try {
            // 1º TENTATIVA: Buscar localmente no ERP
            $dadosLocal = $this->buscarLocalmente($cnpj);
            if ($dadosLocal) {
                return $this->json($response, [
                    'success' => true,
                    'dados' => $dadosLocal,
                    'fonte' => 'ERP'
                ]);
            }
            
            // 2º TENTATIVA: Buscar via BrasilAPI
            $dadosAPI = $this->buscarViaBrasilAPI($cnpj);
            if ($dadosAPI) {
                return $this->json($response, [
                    'success' => true,
                    'dados' => $dadosAPI,
                    'fonte' => 'BrasilAPI'
                ]);
            }
            
            // 3º TENTATIVA: Buscar via ReceitaWS (API alternativa)
            $dadosWS = $this->buscarViaReceitaWS($cnpj);
            if ($dadosWS) {
                return $this->json($response, [
                    'success' => true,
                    'dados' => $dadosWS,
                    'fonte' => 'ReceitaWS'
                ]);
            }
            
            // 4º TENTATIVA: Buscar via CNPJWS (outra API alternativa)
            $dadosCNPJWS = $this->buscarViaCNPJWS($cnpj);
            if ($dadosCNPJWS) {
                return $this->json($response, [
                    'success' => true,
                    'dados' => $dadosCNPJWS,
                    'fonte' => 'CNPJWS'
                ]);
            }
            
            // Se nenhuma fonte encontrou, retorna erro com flag para cadastro manual
            return $this->json($response, [
                'success' => false,
                'error' => 'CNPJ não encontrado na Receita Federal',
                'manual' => true,
                'cnpj' => $cnpj,
                'message' => 'Preencha os dados manualmente'
            ], 404);
            
        } catch (Exception $e) {
            error_log('Erro ao consultar CNPJ: ' . $e->getMessage());
            return $this->json($response, [
                'success' => false,
                'error' => 'Erro ao consultar CNPJ: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Valida CNPJ (módulo 11)
     */
    private function validarCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }
        
        // Validação do primeiro dígito verificador
        $soma = 0;
        $multiplicador = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $multiplicador;
            $multiplicador--;
            if ($multiplicador < 2) {
                $multiplicador = 9;
            }
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;
        
        if ($cnpj[12] != $digito1) {
            return false;
        }
        
        // Validação do segundo dígito verificador
        $soma = 0;
        $multiplicador = 6;
        for ($i = 0; $i < 13; $i++) {
            $soma += $cnpj[$i] * $multiplicador;
            $multiplicador--;
            if ($multiplicador < 2) {
                $multiplicador = 9;
            }
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;
        
        return $cnpj[13] == $digito2;
    }
    
    /**
     * Busca dados via BrasilAPI (gratuita)
     */
    private function buscarViaBrasilAPI(string $cnpj): ?array
    {
        $url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("BrasilAPI erro: HTTP {$httpCode} - " . substr($response, 0, 200));
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || isset($data['errors'])) {
            return null;
        }
        
        // Mapear dados para o formato esperado
        return [
            'razao_social' => $data['razao_social'] ?? '',
            'nome_fantasia' => $data['nome_fantasia'] ?? '',
            'cnpj' => $data['cnpj'] ?? $cnpj,
            'cep' => $data['cep'] ?? '',
            'logradouro' => $data['logradouro'] ?? '',
            'numero' => $data['numero'] ?? '',
            'complemento' => $data['complemento'] ?? '',
            'bairro' => $data['bairro'] ?? '',
            'municipio' => $data['municipio'] ?? '',
            'uf' => $data['uf'] ?? '',
            'telefone' => $data['telefone'] ?? '',
            'email' => $data['email'] ?? '',
            'situacao_cadastral' => $data['situacao_cadastral'] ?? '',
            'data_situacao_cadastral' => $data['data_situacao_cadastral'] ?? '',
            'atividade_principal' => $data['atividade_principal'] ?? [],
            'atividades_secundarias' => $data['atividades_secundarias'] ?? [],
            'natureza_juridica' => $data['natureza_juridica'] ?? '',
            'capital_social' => (float)($data['capital_social'] ?? 0),
            'porte' => $data['porte'] ?? '',
            'data_abertura' => $data['data_abertura'] ?? '',
            'qsa' => $data['qsa'] ?? [],
            'fonte' => 'BrasilAPI'
        ];
    }
    
    /**
     * Busca via ReceitaWS (API alternativa gratuita)
     */
    private function buscarViaReceitaWS(string $cnpj): ?array
    {
        $url = "https://www.receitaws.com.br/v1/cnpj/{$cnpj}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || (isset($data['status']) && $data['status'] === 'ERROR')) {
            return null;
        }
        
        // Mapear dados
        return [
            'razao_social' => $data['nome'] ?? '',
            'nome_fantasia' => $data['fantasia'] ?? '',
            'cnpj' => $data['cnpj'] ?? $cnpj,
            'cep' => $data['cep'] ?? '',
            'logradouro' => $data['logradouro'] ?? '',
            'numero' => $data['numero'] ?? '',
            'complemento' => $data['complemento'] ?? '',
            'bairro' => $data['bairro'] ?? '',
            'municipio' => $data['municipio'] ?? '',
            'uf' => $data['uf'] ?? '',
            'telefone' => $data['telefone'] ?? '',
            'email' => $data['email'] ?? '',
            'situacao_cadastral' => $data['situacao'] ?? '',
            'data_situacao_cadastral' => $data['data_situacao'] ?? '',
            'atividade_principal' => $data['atividade_principal'] ?? [],
            'natureza_juridica' => $data['natureza_juridica'] ?? '',
            'capital_social' => (float)($data['capital_social'] ?? 0),
            'porte' => $data['porte'] ?? '',
            'data_abertura' => $data['abertura'] ?? '',
            'fonte' => 'ReceitaWS'
        ];
    }
    
    /**
     * Busca via CNPJWS (API alternativa)
     */
    private function buscarViaCNPJWS(string $cnpj): ?array
    {
        $url = "https://api.cnpjws.com/api/v1/cnpj/{$cnpj}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || isset($data['error'])) {
            return null;
        }
        
        return [
            'razao_social' => $data['razao_social'] ?? '',
            'nome_fantasia' => $data['nome_fantasia'] ?? '',
            'cnpj' => $data['cnpj'] ?? $cnpj,
            'cep' => $data['cep'] ?? '',
            'logradouro' => $data['logradouro'] ?? '',
            'numero' => $data['numero'] ?? '',
            'complemento' => $data['complemento'] ?? '',
            'bairro' => $data['bairro'] ?? '',
            'municipio' => $data['municipio'] ?? '',
            'uf' => $data['uf'] ?? '',
            'telefone' => $data['telefone'] ?? '',
            'email' => $data['email'] ?? '',
            'situacao_cadastral' => $data['situacao_cadastral'] ?? '',
            'data_situacao_cadastral' => $data['data_situacao_cadastral'] ?? '',
            'capital_social' => (float)($data['capital_social'] ?? 0),
            'porte' => $data['porte'] ?? '',
            'data_abertura' => $data['data_abertura'] ?? '',
            'fonte' => 'CNPJWS'
        ];
    }
    
    /**
     * Busca localmente no ERP
     */
    private function buscarLocalmente(string $cnpj): ?array
    {
        $pdo = \getPDO();
        
        $stmt = $pdo->prepare("
            SELECT 
                idcliforemp,
                razao,
                fantasia,
                cnpj,
                fone,
                email,
                endereco,
                numero,
                bairro,
                cep,
                complemento,
                uf,
                (SELECT descricao FROM cidade WHERE idcidade = cliforemp.idcidade) as cidade
            FROM cliforemp 
            WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj
            LIMIT 1
        ");
        $stmt->execute(['cnpj' => $cnpj]);
        $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            return null;
        }
        
        return [
            'razao_social' => $cliente['razao'] ?? '',
            'nome_fantasia' => $cliente['fantasia'] ?? '',
            'cnpj' => $cliente['cnpj'] ?? $cnpj,
            'cep' => $cliente['cep'] ?? '',
            'logradouro' => $cliente['endereco'] ?? '',
            'numero' => $cliente['numero'] ?? '',
            'complemento' => $cliente['complemento'] ?? '',
            'bairro' => $cliente['bairro'] ?? '',
            'municipio' => $cliente['cidade'] ?? '',
            'uf' => $cliente['uf'] ?? '',
            'telefone' => $cliente['fone'] ?? '',
            'email' => $cliente['email'] ?? '',
            'fonte' => 'ERP'
        ];
    }
    
    /**
     * Formata CNPJ para exibição
     */
    private function formatarCNPJ(string $cnpj): string
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj);
        }
        return $cnpj;
    }
    
    /**
     * Response JSON helper
     */
    private function json($response, $data, $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}