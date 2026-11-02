<?php

namespace App\Libraries;

/**
 * Marca dinámica (design system): un solo acento por cliente, texto sobre acento por contraste WCAG
 * y variante para modo oscuro con contraste ≥ 4,5:1 contra el fondo oscuro.
 */
final class BrandService
{
    public const DEFAULT = '#D80023';

    public static function valid(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * Variables CSS listas para emitir crudas (ya validadas).
     *
     * @return array{brand:string, brandInk:string, brandDark:string, brandInkDark:string}
     */
    public static function variables(?string $hex): array
    {
        $hex  = self::valid($hex) ? strtoupper($hex) : self::DEFAULT;
        $dark = $hex;
        for ($i = 0; $i < 20 && self::contrast($dark, '#0F0F11') < 4.5; $i++) {
            $dark = self::mix($hex, '#FFFFFF', 0.05 * ($i + 1));
        }

        return [
            'brand'        => $hex,
            'brandInk'     => self::bestInk($hex),
            'brandDark'    => $dark,
            'brandInkDark' => self::bestInk($dark),
        ];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function bestInk(string $hex): string
    {
        return self::contrast($hex, '#FFFFFF') >= self::contrast($hex, '#18181B') ? '#FFFFFF' : '#18181B';
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $f = static function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function mix(string $a, string $b, float $t): string
    {
        $ca = self::rgb($a);
        $cb = self::rgb($b);
        $t  = min(1, $t);

        return sprintf('#%02X%02X%02X', ...array_map(static fn ($x, $y) => (int) round($x + ($y - $x) * $t), $ca, $cb));
    }
}
