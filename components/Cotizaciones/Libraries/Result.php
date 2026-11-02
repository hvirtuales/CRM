<?php

namespace Components\Cotizaciones\Libraries;

/**
 * Resultado de operación del componente (copia propia: el componente no depende de utilidades
 * del núcleo que podrían no existir en otra instalación).
 */
final class Result
{
    /**
     * @param array<string, string> $errors
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $message = null,
        public readonly mixed $data = null,
        public readonly array $errors = [],
        public readonly int $status = 200,
    ) {
    }

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(true, $message, $data);
    }

    /** @param array<string, string> $errors */
    public static function fail(string $message, array $errors = [], int $status = 422): self
    {
        return new self(false, $message, null, $errors, $status);
    }

    /** 404 también para registros de otro negocio: no se confirma su existencia. */
    public static function notFound(): self
    {
        return new self(false, lang('Quote.notFound'), null, [], 404);
    }

    public static function forbidden(): self
    {
        return new self(false, lang('Quote.notFound'), null, [], 403);
    }
}
