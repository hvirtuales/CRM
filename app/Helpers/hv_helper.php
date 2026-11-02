<?php

/**
 * Helpers del núcleo HV CRM (autocargados). Disponibles en vistas de componentes.
 */

use App\Libraries\Context;
use Config\Services;

if (! function_exists('ctx')) {
    function ctx(): Context
    {
        return Services::context();
    }
}

if (! function_exists('auth_can')) {
    /** Comprobación de permiso en el contexto activo (también para reglas ABAC en servicios). */
    function auth_can(string $permission): bool
    {
        return Services::context()->can($permission);
    }
}

if (! function_exists('icon')) {
    /** Ícono SVG del sprite propio (sin CDN). Decorativo: acompáñelo siempre de texto o aria-label. */
    function icon(string $name, int $size = 16, string $class = ''): string
    {
        $name = preg_replace('/^icon-/', '', $name) ?? '';
        if (! preg_match('/^[a-z0-9\-]+$/', $name)) {
            return '';
        }

        return '<svg class="shrink-0 ' . esc($class, 'attr') . '" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">'
            . '<use href="' . base_url('assets/img/icons.svg') . '#' . $name . '"></use></svg>';
    }
}

if (! function_exists('money')) {
    /** Formato monetario según el locale activo; moneda ISO 4217. */
    function money(string|float|int|null $amount, string $currency = 'COP'): string
    {
        $locale = service('request')->getLocale() === 'en' ? 'en_US' : 'es_CO';
        $fmt    = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        if (in_array($currency, ['COP', 'CLP', 'PYG'], true)) {
            $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
        }

        return (string) $fmt->formatCurrency((float) $amount, $currency);
    }
}

if (! function_exists('num')) {
    function num(int|float|string|null $value, int $decimals = 0): string
    {
        $locale = service('request')->getLocale() === 'en' ? 'en_US' : 'es_CO';
        $fmt    = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);

        return (string) $fmt->format((float) $value);
    }
}

if (! function_exists('dt')) {
    /** Fecha almacenada en UTC mostrada en la zona del negocio activo (o Bogotá). */
    function dt(?string $utc, bool $withTime = true): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        $tz = Services::context()->company()['timezone'] ?? 'America/Bogota';

        try {
            $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
            $d = $d->setTimezone(new DateTimeZone($tz));
        } catch (Throwable) {
            return '—';
        }
        $locale = service('request')->getLocale() === 'en' ? 'en_US' : 'es_CO';
        $fmt    = new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE, $tz);

        return (string) $fmt->format($d);
    }
}

if (! function_exists('status_badge')) {
    /** Badge de estado: fondo neutro, color en el texto (design system). */
    function status_badge(string $status, ?string $label = null): string
    {
        $tone = match ($status) {
            'active', 'approved', 'paid', 'won', 'accepted', 'published', 'enabled' => 'text-ok',
            'trial', 'pending', 'past_due', 'draft', 'sent', 'open' => 'text-warn',
            'suspended', 'cancelled', 'rejected', 'revoked', 'lost', 'void', 'disabled', 'expired' => 'text-danger',
            default => 'text-ink-soft',
        };
        $label ??= lang('Core.status.' . $status);

        return '<span class="inline-flex items-center rounded-sm bg-muted px-2 py-0.5 text-xs font-medium ' . $tone . '">' . esc($label) . '</span>';
    }
}

if (! function_exists('field_error')) {
    /** Error de validación del servidor para un campo (flashdata 'errors'). */
    function field_error(string $field): string
    {
        $errors = session('errors') ?? [];
        if (! isset($errors[$field])) {
            return '';
        }

        return '<p class="mt-1 text-xs text-danger" id="err-' . esc($field, 'attr') . '">' . esc($errors[$field]) . '</p>';
    }
}

if (! function_exists('active_company_id')) {
    function active_company_id(): ?int
    {
        return Services::context()->companyId();
    }
}
