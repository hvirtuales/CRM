<?php

namespace App\Libraries;

use Config\Services;

/**
 * Invitaciones por correo (corrección del hallazgo «vinculación de cuentas preexistentes»).
 *
 * - El token (192 bits) se envía SOLO al correo invitado; se guarda su SHA-256; un solo uso; vence en 72 h.
 * - Cuenta nueva: el invitado define su propia contraseña al aceptar.
 * - Cuenta existente: debe haber iniciado sesión con ESA cuenta para aceptar.
 * - En desarrollo/pruebas, si no hay correo configurado, el enlace se escribe en el log técnico.
 */
class InvitationService
{
    public const TTL_HOURS = 72;

    /** Último enlace generado (solo fuera de producción, para pruebas y demostración local). */
    public ?string $lastLinkForDevelopment = null;

    /**
     * @param bool $handOver true SOLO cuando el titular ya demostró controlar el correo (autorregistro
     *                       verificado): no se envía correo y el token se devuelve para continuar el flujo.
     */
    public function invite(string $scope, ?int $tenantId, ?int $companyId, string $name, string $email, int $roleId, ?int $actorId, bool $handOver = false): Result
    {
        $db = db_connect();

        // ¿Ya es miembro? Mismo mensaje genérico que un alta (no revela si la cuenta existe).
        $user = $db->table('users')->select('id')->where('email', $email)->where('deleted_at', null)->get()->getRowArray();
        if ($user && $db->table('memberships')->where('user_id', $user['id'])->where('scope', $scope)
            ->where('tenant_id', $tenantId)->where('company_id', $companyId)->countAllResults() > 0) {
            return Result::fail(lang('Core.memberExists'), ['email' => lang('Core.memberExists')], 409);
        }

        // Reemplaza invitaciones pendientes al mismo correo en este ámbito.
        $now = date('Y-m-d H:i:s');
        $db->table('invitations')->where('email', $email)->where('scope', $scope)->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)->where('accepted_at', null)->where('revoked_at', null)->update(['revoked_at' => $now]);

        $token = bin2hex(random_bytes(24));
        $db->table('invitations')->insert([
            'token_hash' => hash('sha256', $token), 'email' => $email, 'name' => $name, 'scope' => $scope,
            'tenant_id' => $tenantId, 'company_id' => $companyId, 'role_id' => $roleId, 'invited_by' => $actorId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600), 'created_at' => $now,
        ]);
        $id = (int) $db->insertID();
        Services::audit()->log('invitation.created', 'invitation', $id, ['scope' => $scope, 'role_id' => $roleId], $companyId, $tenantId);

        if ($handOver) {
            return Result::success(['invitation_id' => $id, 'sent' => false, 'token' => $token]);
        }
        $sent = $this->deliver($email, $name, $token, $this->organizationName($scope, $tenantId, $companyId));

        return Result::success(['invitation_id' => $id, 'sent' => $sent], $sent ? lang('Core.invitationSent', [$email]) : lang('Core.invitationNotSent'));
    }

    /** Invitación vigente por token, o null. */
    public function findValid(string $token): ?array
    {
        if (! preg_match('/^[0-9a-f]{48}$/', $token)) {
            return null;
        }
        $inv = db_connect()->table('invitations')->where('token_hash', hash('sha256', $token))
            ->where('accepted_at', null)->where('revoked_at', null)->get()->getRowArray();
        if (! $inv || strtotime((string) $inv['expires_at']) <= time()) {
            return null;
        }
        $inv['organization'] = $this->organizationName($inv['scope'], $inv['tenant_id'] ? (int) $inv['tenant_id'] : null, $inv['company_id'] ? (int) $inv['company_id'] : null);
        $inv['has_account']  = db_connect()->table('users')->where('email', $inv['email'])->where('deleted_at', null)->countAllResults() > 0;

        return $inv;
    }

    /**
     * Acepta la invitación.
     *
     * @param int|null    $sessionUserId usuario con sesión (requerido si la cuenta ya existe)
     * @param string|null $password      contraseña nueva (requerida si la cuenta NO existe)
     */
    public function accept(string $token, ?int $sessionUserId, ?string $password, ?string $confirm): Result
    {
        $inv = $this->findValid($token);
        if (! $inv) {
            return Result::fail(lang('Core.invitationInvalid'), [], 404);
        }
        $db   = db_connect();
        $user = $db->table('users')->where('email', $inv['email'])->where('deleted_at', null)->get()->getRowArray();
        $now  = date('Y-m-d H:i:s');

        if ($user) {
            if ($sessionUserId !== (int) $user['id']) {
                return Result::fail(lang('Core.invitationLoginAs', [$inv['email']]), [], 403);
            }
            $userId = (int) $user['id'];
        } else {
            if ($password === null || $password !== $confirm) {
                return Result::fail(lang('Auth.passwordMismatch'), ['password_confirm' => lang('Auth.passwordMismatch')]);
            }
            if ($problem = Services::auth()->passwordProblem($password, $inv['email'])) {
                return Result::fail($problem, ['password' => $problem]);
            }
        }
        if ($inv['scope'] === 'company' && ! Services::users()->roleIsAssignable('company', (int) $inv['company_id'], (int) $inv['role_id'])) {
            return Result::fail(lang('Core.invitationInvalid'), [], 409);
        }

        $db->transStart();
        // Consumir el token primero (un solo uso, también ante peticiones concurrentes).
        $db->table('invitations')->where('id', $inv['id'])->where('accepted_at', null)->update(['accepted_at' => $now]);
        if ($db->affectedRows() !== 1) {
            $db->transRollback();

            return Result::fail(lang('Core.invitationInvalid'), [], 409);
        }
        if (! $user) {
            $db->table('users')->insert([
                'public_id' => Ids::publicId(), 'name' => $inv['name'], 'email' => $inv['email'],
                'password_hash' => AuthService::hashPassword((string) $password), 'status' => 'active', 'locale' => 'es',
                'session_version' => 1, 'password_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $userId = (int) $db->insertID();
        }
        $exists = $db->table('memberships')->where('user_id', $userId)->where('scope', $inv['scope'])
            ->where('tenant_id', $inv['tenant_id'])->where('company_id', $inv['company_id'])->countAllResults();
        if (! $exists) {
            $db->table('memberships')->insert([
                'user_id' => $userId, 'scope' => $inv['scope'], 'tenant_id' => $inv['tenant_id'], 'company_id' => $inv['company_id'],
                'role_id' => $inv['role_id'], 'status' => 'active', 'invited_by' => $inv['invited_by'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $db->transComplete();
        $db->table('outbox')->where('dedupe_key', 'invitation:' . hash('sha256', $token))->where('status', 'queued')->delete();
        Services::audit()->log('invitation.accepted', 'invitation', (int) $inv['id'], ['user_id' => $userId], $inv['company_id'] ? (int) $inv['company_id'] : null, $inv['tenant_id'] ? (int) $inv['tenant_id'] : null);

        return Result::success(['user_id' => $userId, 'new_account' => ! $user]);
    }

    public function revoke(int $id, string $scope, ?int $tenantId, ?int $companyId): Result
    {
        $db = db_connect();
        $db->table('invitations')->where('id', $id)->where('scope', $scope)->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)->where('accepted_at', null)->where('revoked_at', null)->update(['revoked_at' => date('Y-m-d H:i:s')]);
        if ($db->affectedRows() !== 1) {
            return Result::notFound();
        }
        Services::audit()->log('invitation.revoked', 'invitation', $id, [], $companyId, $tenantId);

        return Result::success(null, lang('Core.invitationRevoked'));
    }

    /** @return list<array<string, mixed>> */
    public function pending(string $scope, ?int $tenantId, ?int $companyId): array
    {
        return db_connect()->table('invitations i')->select('i.id, i.email, i.name, i.expires_at, i.created_at, r.code AS role_code, r.name AS role_name')
            ->join('roles r', 'r.id = i.role_id')
            ->where('i.scope', $scope)->where('i.tenant_id', $tenantId)->where('i.company_id', $companyId)
            ->where('i.accepted_at', null)->where('i.revoked_at', null)->where('i.expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('i.id', 'DESC')->get()->getResultArray();
    }

    public function pendingCount(string $scope, ?int $tenantId, ?int $companyId): int
    {
        return count($this->pending($scope, $tenantId, $companyId));
    }

    private function organizationName(string $scope, ?int $tenantId, ?int $companyId): string
    {
        $db = db_connect();

        return match ($scope) {
            'tenant'  => (string) ($db->table('tenants')->select('name')->where('id', $tenantId)->get()->getRow()->name ?? ''),
            'company' => (string) ($db->table('companies')->select('name')->where('id', $companyId)->get()->getRow()->name ?? ''),
            default   => lang('Core.platform'),
        };
    }

    /**
     * Envía el enlace al correo invitado por la bandeja de salida. Nunca lo devuelve al administrador.
     * Devuelve true si salió de inmediato (si no, queda en cola hasta configurar el correo).
     */
    private function deliver(string $email, string $name, string $token, string $organization): bool
    {
        $link = site_url('invitacion/' . $token);
        if (ENVIRONMENT !== 'production') {
            $this->lastLinkForDevelopment = $link;
            if (! NotificationService::emailConfigured()) {
                log_message('info', 'Invitación (sin correo configurado) para {email}: {link}', ['email' => $email, 'link' => $link]);
            }
        }
        $id = (new NotificationService())->queue('invitation:' . hash('sha256', $token), 'invitation', $email, (string) (service('request')->getLocale() ?: 'es'),
            'Core.invitationSubject', [$organization], 'emails/message', [
                'greeting'  => lang('Core.invitationHello', [$name]),
                'lines'     => [lang('Core.invitationBody', [$organization])],
                'cta_label' => lang('Core.invitationAccept'),
                'cta_url'   => $link,
                'note'      => lang('Core.invitationExpires', [self::TTL_HOURS]),
            ]);

        return $id !== null && db_connect()->table('outbox')->where('id', $id)->where('status', 'sent')->countAllResults() > 0;
    }
}
