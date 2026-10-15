<?php
namespace Nutricional\Services;

/** TOTP (RFC 6238, SHA-1, 6 digitos, 30s) compativel com Google/Microsoft Authenticator. */
class Totp
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function gerarSegredo(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function uri(string $segredo, string $conta, string $emissor = 'Portal Nutricional'): string
    {
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
            . '?secret=' . $segredo . '&issuer=' . rawurlencode($emissor) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function codigo(string $segredo, int $contador): string
    {
        $chave = self::base32Decode($segredo);
        $hmac = hash_hmac('sha1', pack('J', $contador), $chave, true);
        $o = ord($hmac[19]) & 0x0F;
        $n = ((ord($hmac[$o]) & 0x7F) << 24) | (ord($hmac[$o + 1]) << 16) | (ord($hmac[$o + 2]) << 8) | ord($hmac[$o + 3]);
        return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Valida o codigo aceitando +-1 janela de 30s.
     * Retorna o contador usado (para impedir reuso) ou null se invalido.
     */
    public static function verificar(string $segredo, string $codigo, int $ultimoContador = 0): ?int
    {
        $codigo = preg_replace('/\D/', '', $codigo);
        if (strlen($codigo) !== 6) {
            return null;
        }
        $atual = intdiv(time(), 30);
        for ($i = -1; $i <= 1; $i++) {
            $c = $atual + $i;
            if ($c > $ultimoContador && hash_equals(self::codigo($segredo, $c), $codigo)) {
                return $c;
            }
        }
        return null;
    }

    private static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $grupo) {
            $out .= self::ALFABETO[bindec(str_pad($grupo, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    private static function base32Decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($b32, '='))) as $ch) {
            $p = strpos(self::ALFABETO, $ch);
            if ($p !== false) {
                $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
