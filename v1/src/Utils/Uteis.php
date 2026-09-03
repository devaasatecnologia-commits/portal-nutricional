<?php
/**
 * Classe Utilitária para API v1
 * Local: /v1/src/Utils/Uteis.php
 */

namespace Nutricional\Utils;

class Uteis
{
    /**
     * Criptografa uma string usando AES-128-CBC
     */
    public static function encrypt($data, $key) 
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-128-CBC'));
        $encryptedData = openssl_encrypt($data, 'AES-128-CBC', $key, 0, $iv);
        return base64_encode($encryptedData . '::' . base64_encode($iv));
    }

    /**
     * Descriptografa uma string usando AES-128-CBC
     */
    public static function decrypt($data, $key) 
    {
        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) {
            throw new \Exception("Dados criptografados inválidos");
        }
        list($encryptedData, $iv) = $parts;
        $iv = base64_decode($iv);
        if ($iv === false) {
            throw new \Exception("IV inválido");
        }
        return openssl_decrypt($encryptedData, 'AES-128-CBC', $key, 0, $iv);
    }

    /**
     * Gera um token único para execução de crons
     */
    public static function gerarTokenUnico($tokenBase, $chave, $tipo) 
    {
        $tokenComTimestamp = $tokenBase . '_' . time() . '_' . uniqid();
        return self::encrypt($tokenComTimestamp, $chave);
    }

    /**
     * Valida token (versão simplificada)
     */
    public static function validarToken($tokenCriptografado, $tokenEsperado, $tipo = 'default') 
    {
        $chave = $_ENV['CHAVE_SECRETA'] ?? 'alansabe123456';
        
        try {
            $decrypted = self::decrypt($tokenCriptografado, $chave);
            
            // Verifica se contém o token base
            if (strpos($decrypted, $tokenEsperado) === 0) {
                return true;
            }
            
            // Compatibilidade com tokens antigos
            if ($decrypted === $tokenEsperado) {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log("Erro ao validar token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se é último dia útil do mês
     */
    public static function isUltimoDiaUtil() 
    {
        $hoje = new \DateTime();
        $dia = (int)$hoje->format('d');
        $diaSemana = (int)$hoje->format('N');
        $ultimoDiaMes = (int)$hoje->format('t');
        
        if ($diaSemana >= 6) {
            return false;
        }

        if ($dia === $ultimoDiaMes) {
            return true;
        }

        $dataUltimo = new \DateTime($hoje->format('Y-m-t'));
        $uDiaSemana = (int)$dataUltimo->format('N');

        if ($uDiaSemana === 6 && $dia === ($ultimoDiaMes - 1)) {
            return true;
        }

        if ($uDiaSemana === 7 && $dia === ($ultimoDiaMes - 2)) {
            return true;
        }

        return false;
    }

    /**
     * Constrói corpo do email para representantes
     */
    public static function construirCorpoEmail($pedidos, $repre) 
    {
        if (empty($pedidos) || !is_array($pedidos)) {
            return '<p>Nenhum pedido encontrado para gerar relatório.</p>';
        }

        $html = '<h1>Relatório de Alterações de Pedidos</h1>';
        $html .= '<h2>Representante: ' . htmlspecialchars($repre) . '</h2>';
        
        foreach ($pedidos as $pedido) {
            $html .= '<p>Pedido: ' . ($pedido['numeroPedidoMercos'] ?? 'N/A') . 
                     ' - Cliente: ' . ($pedido['cliente'] ?? 'N/A') . 
                     ' - Produto: ' . ($pedido['produto'] ?? 'N/A') . '</p>';
        }
        
        return $html;
    }

    /**
     * Constrói email para gestores
     */
    public static function construirEmailGestor($gestor) 
    {
        $html = '<h1>Relatório de Inadimplência</h1>';
        $html .= '<p>Gestor: ' . htmlspecialchars($gestor['nomegestor']) . '</p>';
        $html .= '<p>Data: ' . date('d/m/Y') . '</p>';
        return $html;
    }

    /**
     * Gera Excel com dados dos representantes
     */
    public static function gerarExcelRepresentantes($idGestor, $dados) 
    {
        $arquivo = sys_get_temp_dir() . "/resumo_rep_{$idGestor}_" . date('YmdHis') . ".csv";
        
        $csv = fopen($arquivo, 'w');
        fputcsv($csv, ['Representante', 'Valor Carteira', 'Vencidos', 'Percentual']);
        
        foreach ($dados as $row) {
            fputcsv($csv, [
                $row['Nome do Representante'] ?? '',
                $row['Valor Total'] ?? 0,
                $row['Vencidos'] ?? 0,
                $row['Percentual'] ?? 0
            ]);
        }
        
        fclose($csv);
        return $arquivo;
    }
}