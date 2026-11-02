<?php

namespace App\Libraries;

/**
 * Resultado de una operación de dominio. Los controladores traducen esto a HTTP;
 * los mensajes ya vienen traducidos con lang().
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

    /**
     * @param array<string, string> $errors
     */
    public static function fail(string $message, array $errors = [], int $status = 422): self
    {
        return new self(false, $message, null, $errors, $status);
    }

    public static function forbidden(?string $message = null): self
    {
        return new self(false, $message ?? lang('Core.forbidden'), null, [], 403);
    }

    public static function notFound(?string $message = null): self
    {
        return new self(false, $message ?? lang('Core.notFound'), null, [], 404);
    }
}
