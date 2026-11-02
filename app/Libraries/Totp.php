<?php

namespace App\Libraries;

/**
 * TOTP (RFC 6238) — SHA-1, 6 dígitos, paso de 30 s. Compatible con Google Authenticator,
 * Microsoft Authenticator, 1Password, etc. Sin dependencias externas.
 */
final class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $counter = intdiv($time ?? time(), 30);
        $binary  = pack('N*', 0) . pack('N*', $counter);
        $hash    = hash_hmac('sha1', $binary, self::base32Decode($secret), true);
        $offset  = ord($hash[19]) & 0x0F;
        $value   = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
                 | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** Verifica con ventana de ±1 paso para tolerar desfase de reloj. Comparación en tiempo constante. */
    public static function verify(string $secret, string $code, ?int $time = null): bool
    {
        return self::matchStep($secret, $code, $time) !== null;
    }

    /**
     * Paso de tiempo (time/30) que coincide con el código, o null. Permite rechazar la reutilización
     * de un código ya aceptado (paso ≤ último aceptado).
     */
    public static function matchStep(string $secret, string $code, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $time ??= time();
        foreach ([-30, 0, 30] as $drift) {
            if (hash_equals(self::code($secret, $time + $drift), $code)) {
                return intdiv($time + $drift, 30);
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    private static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $data): string
    {
        $data = strtoupper(rtrim($data, '='));
        $bits = '';
        foreach (str_split($data) as $c) {
            $pos = strpos(self::B32, $c);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
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
