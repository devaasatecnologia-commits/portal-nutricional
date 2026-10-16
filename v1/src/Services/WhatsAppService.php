<?php
namespace Nutricional\Services;

/**
 * Envio de mensagens por WhatsApp. Provedor escolhido pelas credenciais salvas no cofre:
 *  - Z-API (ZAPI_INSTANCE_ID + ZAPI_TOKEN [+ ZAPI_CLIENT_TOKEN])
 *  - Meta WhatsApp Cloud API (WHATSAPP_META_PHONE_ID + WHATSAPP_META_TOKEN + WHATSAPP_META_TEMPLATE)
 */
class WhatsAppService
{
    public static function provedor(): ?string
    {
        if (SecretVault::get('ZAPI_INSTANCE_ID') !== '' && SecretVault::get('ZAPI_TOKEN') !== '') {
            return 'zapi';
        }
        if (SecretVault::get('WHATSAPP_META_PHONE_ID') !== '' && SecretVault::get('WHATSAPP_META_TOKEN') !== ''
            && SecretVault::get('WHATSAPP_META_TEMPLATE') !== '') {
            return 'meta';
        }
        return null;
    }

    /** Normaliza para E.164 sem '+': aceita 10-11 digitos (BR) ou 12-15 com DDI. Retorna null se invalido. */
    public static function normalizarNumero(string $numero): ?string
    {
        $n = preg_replace('/\D+/', '', $numero);
        if (strlen($n) === 10 || strlen($n) === 11) {
            $n = '55' . $n;
        }
        return preg_match('/^\d{12,15}$/', $n) ? $n : null;
    }

    public static function mascarar(string $numero): string
    {
        return strlen($numero) > 6 ? substr($numero, 0, 4) . str_repeat('*', strlen($numero) - 6) . substr($numero, -2) : '****';
    }

    /** Envia o codigo de verificacao. Retorna true em caso de sucesso. */
    public static function enviarCodigo(string $numero, string $codigo): bool
    {
        switch (self::provedor()) {
            case 'zapi':
                $url = 'https://api.z-api.io/instances/' . rawurlencode(SecretVault::get('ZAPI_INSTANCE_ID'))
                    . '/token/' . rawurlencode(SecretVault::get('ZAPI_TOKEN')) . '/send-text';
                $headers = ['Content-Type: application/json'];
                if (SecretVault::get('ZAPI_CLIENT_TOKEN') !== '') {
                    $headers[] = 'Client-Token: ' . SecretVault::get('ZAPI_CLIENT_TOKEN');
                }
                $body = ['phone' => $numero, 'message' => "Portal Nutricional: seu codigo de verificacao e *{$codigo}*. Vale por 5 minutos. Nao compartilhe."];
                return self::post($url, $headers, $body);
            case 'meta':
                $url = 'https://graph.facebook.com/v20.0/' . rawurlencode(SecretVault::get('WHATSAPP_META_PHONE_ID')) . '/messages';
                $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . SecretVault::get('WHATSAPP_META_TOKEN')];
                $body = [
                    'messaging_product' => 'whatsapp',
                    'to' => $numero,
                    'type' => 'template',
                    'template' => [
                        'name' => SecretVault::get('WHATSAPP_META_TEMPLATE'),
                        'language' => ['code' => 'pt_BR'],
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $codigo]]],
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $codigo]]],
                        ],
                    ],
                ];
                return self::post($url, $headers, $body);
        }
        return false;
    }

    private static function post(string $url, array $headers, array $body): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($http < 200 || $http >= 300) {
            error_log('[WhatsApp] envio falhou HTTP ' . $http . ' ' . $err . ' ' . substr((string)$resp, 0, 200));
            return false;
        }
        return true;
    }
}
