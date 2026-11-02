<?php

namespace App\Libraries;

use Config\Services;

/**
 * Bitácora de auditoría (solo inserción). Redacta claves sensibles antes de persistir.
 */
class AuditService
{
    private const REDACT = ['password', 'password_hash', 'token', 'secret', 'mfa_secret', 'code', 'otp', 'authorization'];

    /**
     * @param array<string, mixed> $data
     */
    public function log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $data = [], ?int $companyId = null, ?int $tenantId = null): void
    {
        $ctx     = Services::context();
        $request = Services::request();
        $isHttp  = ! is_cli() || ENVIRONMENT === 'testing';

        try {
            db_connect()->table('audit_events')->insert([
                'occurred_at'   => date('Y-m-d H:i:s'),
                'actor_user_id' => $ctx->userId(),
                'scope'         => $ctx->scope(),
                'tenant_id'     => $tenantId ?? $ctx->tenantId() ?? ($ctx->company()['tenant_id'] ?? null),
                'company_id'    => $companyId ?? $ctx->companyId(),
                'action'        => substr($action, 0, 100),
                'entity_type'   => $entityType,
                'entity_id'     => $entityId === null ? null : (string) $entityId,
                'ip'            => $isHttp && method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
                'user_agent'    => $isHttp && method_exists($request, 'getUserAgent') ? substr((string) $request->getUserAgent(), 0, 255) : null,
                'request_id'    => $ctx->requestId(),
                'data'          => $data === [] ? null : json_encode($this->redact($data + ($ctx->supportGrant() ? ['support_grant_id' => $ctx->supportGrant()['id']] : [])), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            // La auditoría no debe tumbar la operación, pero el fallo queda en el log técnico.
            log_message('critical', 'Fallo de auditoría {action}: {msg}', ['action' => $action, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), self::REDACT, true)) {
                $data[$k] = '[redactado]';
            } elseif (is_array($v)) {
                $data[$k] = $this->redact($v);
            }
        }

        return $data;
    }
}
