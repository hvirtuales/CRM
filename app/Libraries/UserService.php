<?php

namespace App\Libraries;

use Config\Services;

/**
 * Alta de usuarios y membresías dentro de un ámbito. Toda membresía nueva queda acotada
 * al ámbito del actor (nunca se acepta tenant_id/company_id del formulario).
 */
class UserService
{
    /**
     * Crea (o reutiliza por correo) un usuario y le da una membresía en el ámbito indicado.
     *
     * @param array{name?:string, email?:string, password?:string, role_id?:int|string} $data
     */
    public function addMember(string $scope, ?int $tenantId, ?int $companyId, array $data, ?int $actorId): Result
    {
        $name  = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $roleId = (int) ($data['role_id'] ?? 0);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = lang('Core.validation.name');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = lang('Core.validation.email');
        }
        $role = $this->assignableRole($scope, $companyId, $roleId);
        if (! $role) {
            $errors['role_id'] = lang('Core.validation.role');
        }
        if ($errors !== []) {
            return Result::fail(lang('Core.validation.fix'), $errors);
        }

        if ($scope === 'company' && $companyId !== null) {
            $left = Services::plans()->usersRemaining($companyId);
            if ($left !== null && $left <= 0) {
                return Result::fail(lang('Core.usersQuotaExhausted'));
            }
        }

        $db   = db_connect();
        $user = $db->table('users')->where('email', $email)->where('deleted_at', null)->get()->getRowArray();
        $tempPassword = null;
        $now  = date('Y-m-d H:i:s');

        $db->transStart();
        if (! $user) {
            $password = (string) ($data['password'] ?? '');
            if ($password === '') {
                $tempPassword = $this->temporaryPassword();
                $password     = $tempPassword;
            } elseif ($problem = Services::auth()->passwordProblem($password, $email)) {
                $db->transRollback();

                return Result::fail($problem, ['password' => $problem]);
            }
            $db->table('users')->insert([
                'public_id' => Ids::publicId(), 'name' => $name, 'email' => $email,
                'password_hash' => AuthService::hashPassword($password), 'status' => 'active',
                'locale' => service('request')->getLocale() ?: 'es', 'must_change_password' => 1,
                'session_version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $userId = (int) $db->insertID();
        } else {
            $userId = (int) $user['id'];
        }

        $exists = $db->table('memberships')->where('user_id', $userId)->where('scope', $scope)
            ->where('tenant_id', $tenantId)->where('company_id', $companyId)->get()->getRowArray();
        if ($exists) {
            $db->transRollback();

            return Result::fail(lang('Core.memberExists'), ['email' => lang('Core.memberExists')], 409);
        }

        $db->table('memberships')->insert([
            'user_id' => $userId, 'scope' => $scope, 'tenant_id' => $tenantId, 'company_id' => $companyId,
            'role_id' => $roleId, 'status' => 'active', 'invited_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $membershipId = (int) $db->insertID();
        $db->transComplete();

        Services::audit()->log('membership.created', 'membership', $membershipId, ['user_id' => $userId, 'role' => $role['code'], 'scope' => $scope], $companyId, $tenantId);

        return Result::success(['user_id' => $userId, 'membership_id' => $membershipId, 'temp_password' => $tempPassword]);
    }

    /** Cambia rol o estado de una membresía comprobando que pertenece al ámbito del actor. */
    public function updateMember(int $membershipId, string $scope, ?int $tenantId, ?int $companyId, array $data, int $actorId): Result
    {
        $db = db_connect();
        $m  = $db->table('memberships')->where('id', $membershipId)->where('scope', $scope)
            ->where('tenant_id', $tenantId)->where('company_id', $companyId)->get()->getRowArray();
        if (! $m) {
            return Result::notFound();
        }
        if ((int) $m['user_id'] === $actorId) {
            return Result::fail(lang('Core.cannotEditSelf'), [], 409);
        }

        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($data['role_id'])) {
            $role = $this->assignableRole($scope, $companyId, (int) $data['role_id']);
            if (! $role) {
                return Result::fail(lang('Core.validation.role'));
            }
            $update['role_id'] = (int) $data['role_id'];
        }
        if (isset($data['status'])) {
            if (! in_array($data['status'], ['active', 'suspended'], true)) {
                return Result::fail(lang('Core.validation.fix'));
            }
            $update['status'] = $data['status'];
        }

        // No dejar el ámbito sin propietario activo.
        if ($this->isLastOwner($m) && (($update['status'] ?? 'active') !== 'active' || (isset($update['role_id']) && $update['role_id'] !== (int) $m['role_id']))) {
            return Result::fail(lang('Core.lastOwner'), [], 409);
        }

        $db->table('memberships')->where('id', $membershipId)->update($update);
        Services::audit()->log('membership.updated', 'membership', $membershipId, array_diff_key($update, ['updated_at' => 1]), $companyId, $tenantId);

        return Result::success();
    }

    /** @return list<array<string, mixed>> */
    public function members(string $scope, ?int $tenantId, ?int $companyId): array
    {
        return db_connect()->table('memberships m')
            ->select('m.id, m.user_id, m.status, m.role_id, m.created_at, u.name, u.email, u.last_login_at, u.mfa_enabled, r.name AS role_name, r.code AS role_code')
            ->join('users u', 'u.id = m.user_id')
            ->join('roles r', 'r.id = m.role_id')
            ->where('m.scope', $scope)->where('m.tenant_id', $tenantId)->where('m.company_id', $companyId)
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();
    }

    private function assignableRole(string $scope, ?int $companyId, int $roleId): ?array
    {
        $b = db_connect()->table('roles')->where('id', $roleId)->where('scope', $scope)->groupStart()->where('is_system', 1);
        if ($companyId !== null) {
            $b->orWhere('company_id', $companyId);
        }

        return $b->groupEnd()->get()->getRowArray() ?: null;
    }

    private function isLastOwner(array $m): bool
    {
        $ownerCode = ['platform' => 'platform_owner', 'tenant' => 'tenant_owner', 'company' => 'business_owner'][$m['scope']];
        $ownerRole = Services::rbac()->systemRoleId($ownerCode);
        if ((int) $m['role_id'] !== $ownerRole) {
            return false;
        }

        return db_connect()->table('memberships')->where('scope', $m['scope'])->where('tenant_id', $m['tenant_id'])
            ->where('company_id', $m['company_id'])->where('role_id', $ownerRole)->where('status', 'active')->countAllResults() <= 1;
    }

    /** Contraseña temporal legible (se muestra una sola vez; el usuario debe cambiarla al entrar). */
    public function temporaryPassword(): string
    {
        $words = ['Nube', 'Faro', 'Ruta', 'Puente', 'Mapa', 'Brisa', 'Cauce', 'Norte', 'Prisma', 'Vela'];

        return $words[random_int(0, 9)] . '-' . bin2hex(random_bytes(4)) . '-' . $words[random_int(0, 9)] . random_int(10, 99);
    }
}
