<?php

namespace App\Libraries;

/**
 * Identificadores públicos opacos (no secuenciales) para exponer en URLs.
 */
final class Ids
{
    private const ALPHABET = '0123456789abcdefghjkmnpqrstvwxyz'; // Crockford base32, minúsculas

    /** 26 caracteres ≈ 130 bits de entropía. */
    public static function publicId(int $length = 26): string
    {
        $bytes = random_bytes($length);
        $out   = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[ord($bytes[$i]) & 31];
        }

        return $out;
    }

    public static function isPublicId(?string $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-z]{20,32}$/', $value) === 1;
    }

    /** Slug URL seguro a partir de un texto. */
    public static function slug(string $text, int $max = 60): string
    {
        $text = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: strtolower($text);
        $text = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');

        return substr($text !== '' ? $text : 'item', 0, $max);
    }
}
