<?php

namespace App\Libraries;

use Config\Services;

/**
 * Lista y cambia el ámbito activo del usuario. Cada cambio se autoriza en servidor,
 * regenera la sesión y queda auditado (US-013).
 */
class ContextSwitcher
{
    /**
     * Ámbitos disponibles para el usuario.
     *
     * @return list<array{membership_id:int, scope:string, name:string, role:string, status:string}>
     */
    public function available(int $userId): array
    {
        $db   = db_connect();
        $rows = $db->table('memberships m')
            ->select('m.id, m.scope, m.tenant_id, m.company_id, r.name AS role_name, r.code AS role_code, t.name AS tenant_name, t.status AS tenant_status, c.name AS company_name, c.status AS company_status')
            ->join('roles r', 'r.id = m.role_id')
            ->join('tenants t', 't.id = m.tenant_id', 'left')
            ->join('companies c', 'c.id = m.company_id', 'left')
            ->where('m.user_id', $userId)
            ->where('m.status', 'active')
            ->orderBy('m.scope', 'ASC')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $status = match ($r['scope']) {
                'tenant'  => (string) $r['tenant_status'],
                'company' => (string) $r['company_status'],
                default   => 'active',
            };
            if ($status === 'archived' || ($r['scope'] === 'tenant' && $status !== 'active')) {
                continue;
            }
            $out[] = [
                'membership_id' => (int) $r['id'],
                'scope'         => $r['scope'],
                'name'          => match ($r['scope']) {
                    'platform' => lang('Core.platform'),
                    'tenant'   => (string) $r['tenant_name'],
                    default    => (string) $r['company_name'],
                },
                'role'          => $r['role_name'],
                'status'        => $status,
            ];
        }

        // Orden: plataforma, distribuidor, negocio
        $rank = ['platform' => 0, 'tenant' => 1, 'company' => 2];
        usort($out, static fn ($a, $b) => [$rank[$a['scope']], $a['name']] <=> [$rank[$b['scope']], $b['name']]);

        return $out;
    }

    /** @return list<array<string, mixed>> accesos de soporte aprobados y vigentes */
    public function supportAccesses(int $userId): array
    {
        return db_connect()->table('support_grants g')
            ->select('g.id, g.expires_at, c.name AS company_name')
            ->join('companies c', 'c.id = g.company_id')
            ->where('g.user_id', $userId)
            ->where('g.status', 'approved')
            ->where('g.expires_at >', date('Y-m-d H:i:s'))
            ->get()->getResultArray();
    }

    /** Tras autenticarse: si hay un único espacio disponible se entra directo. */
    public function autoEnter(int $userId): bool
    {
        $available = $this->available($userId);

        return count($available) === 1 && $this->switchTo($userId, $available[0]['membership_id'])->ok;
    }

    public function switchTo(int $userId, int $membershipId): Result
    {
        $allowed = array_column($this->available($userId), 'membership_id');
        if (! in_array($membershipId, $allowed, true)) {
            Services::audit()->log('context.switch_denied', 'membership', $membershipId);

            return Result::forbidden(lang('Core.contextDenied'));
        }

        $this->resetScope();
        session()->set('membership_id', $membershipId);

        $ctx = Services::resetContext();
        if (! $ctx->loadFromSession() || $ctx->scope() === null) {
            return Result::forbidden(lang('Core.contextDenied'));
        }
        Rls::setCompany($ctx->companyId());
        Services::audit()->log('context.switched', 'membership', $membershipId, ['scope' => $ctx->scope()]);

        return Result::success(['scope' => $ctx->scope()]);
    }

    public function enterSupport(int $userId, int $grantId): Result
    {
        $grant = db_connect()->table('support_grants')->where('id', $grantId)->where('user_id', $userId)
            ->where('status', 'approved')->where('expires_at >', date('Y-m-d H:i:s'))->get()->getRowArray();
        if (! $grant) {
            return Result::forbidden(lang('Core.contextDenied'));
        }

        $this->resetScope();
        session()->set('support_grant_id', $grantId);
        $ctx = Services::resetContext();
        if (! $ctx->loadFromSession() || $ctx->scope() === null) {
            return Result::forbidden(lang('Core.contextDenied'));
        }
        Rls::setCompany($ctx->companyId());
        Services::audit()->log('support.session_started', 'support_grant', $grantId, [], (int) $grant['company_id']);

        return Result::success();
    }

    /** Limpia el ámbito y regenera el id de sesión: nada del contexto anterior se hereda. */
    private function resetScope(): void
    {
        $session = session();
        $session->remove(['membership_id', 'support_grant_id', 'active_scope', 'active_tenant_id', 'active_company_id']);
        if (! is_cli()) {
            $session->regenerate(true);
        }
    }
}
