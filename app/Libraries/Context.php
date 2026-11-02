<?php

namespace App\Libraries;

use Config\Rbac;
use Config\Services;

/**
 * Contexto efectivo de la petición: actor, ámbito activo, organización y permisos.
 *
 * Nunca se confía en identificadores enviados por el navegador: el ámbito sale de una membresía
 * activa (o de un acceso de soporte aprobado y vigente) que se revalida en cada petición.
 */
class Context
{
    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_TENANT   = 'tenant';
    public const SCOPE_COMPANY  = 'company';

    private ?array $user        = null;
    private ?array $membership  = null;
    private ?array $tenant      = null;
    private ?array $company     = null;
    private ?array $supportGrant = null;
    private ?string $scope      = null;
    private bool $readOnly      = false;
    private ?string $readOnlyReason = null;
    private bool $viaApi        = false;

    /** @var array<string, true> */
    private array $permissions = [];

    private string $requestId;

    public function __construct()
    {
        $this->requestId = bin2hex(random_bytes(8));
    }

    // ------------------------------------------------------------------ carga

    /**
     * Carga el contexto desde la sesión. Devuelve false si la sesión ya no es válida
     * (usuario suspendido, versión de sesión distinta, membresía revocada…).
     */
    public function loadFromSession(): bool
    {
        $session = session();
        $userId  = (int) ($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $user = db_connect()->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();
        if (! $user || $user['status'] !== 'active'
            || (int) $user['session_version'] !== (int) $session->get('session_version')) {
            return false;
        }
        $this->user = $user;

        if ($grantId = (int) ($session->get('support_grant_id') ?? 0)) {
            return $this->loadSupportGrant($grantId);
        }

        $membershipId = (int) ($session->get('membership_id') ?? 0);
        if ($membershipId > 0) {
            if ($this->loadMembership($membershipId)) {
                $this->persist(); // la sesión refleja siempre el ámbito recalculado en servidor
            } else {
                // La membresía dejó de ser válida: se conserva la sesión, pero sin ámbito.
                $this->clearScopeInSession();
            }
        }

        return true;
    }

    /** Carga contexto desde una membresía ya autorizada (API tokens, pruebas, CLI). */
    public function loadForMembership(array $user, int $membershipId, bool $viaApi = false): bool
    {
        $this->user   = $user;
        $this->viaApi = $viaApi;

        return $this->loadMembership($membershipId);
    }

    private function loadMembership(int $membershipId): bool
    {
        $db = db_connect();
        $m  = $db->table('memberships')
            ->where('id', $membershipId)
            ->where('user_id', $this->user['id'])
            ->where('status', 'active')
            ->get()->getRowArray();

        if (! $m) {
            return false;
        }

        $scope = $m['scope'];
        if ($scope === self::SCOPE_TENANT) {
            $tenant = $db->table('tenants')->where('id', $m['tenant_id'])->where('deleted_at', null)->get()->getRowArray();
            if (! $tenant || $tenant['status'] !== 'active') {
                return false;
            }
            $this->tenant = $tenant;
        } elseif ($scope === self::SCOPE_COMPANY) {
            $company = $db->table('companies')->where('id', $m['company_id'])->where('deleted_at', null)->get()->getRowArray();
            if (! $company || $company['status'] === 'archived') {
                return false;
            }
            $this->company = $company;
            if ($company['tenant_id']) {
                $this->tenant = $db->table('tenants')->where('id', $company['tenant_id'])->get()->getRowArray();
            }
            $this->evaluateCompanyAccessMode();
        } elseif ($scope !== self::SCOPE_PLATFORM) {
            return false;
        }

        $this->membership  = $m;
        $this->scope       = $scope;
        $this->permissions = array_fill_keys(Services::rbac()->permissionsForRole((int) $m['role_id']), true);

        return true;
    }

    private function loadSupportGrant(int $grantId): bool
    {
        $db    = db_connect();
        $grant = $db->table('support_grants')
            ->where('id', $grantId)
            ->where('user_id', $this->user['id'])
            ->where('status', 'approved')
            ->get()->getRowArray();

        if (! $grant || strtotime((string) $grant['expires_at']) <= time()) {
            $this->clearScopeInSession();

            return true; // la sesión sigue siendo válida; el acceso de soporte venció
        }

        // El actor debe seguir teniendo una membresía de plataforma con permiso de soporte.
        if (! $this->hasPlatformPermission('platform.support.request')) {
            $this->clearScopeInSession();

            return true;
        }

        $company = $db->table('companies')->where('id', $grant['company_id'])->where('deleted_at', null)->get()->getRowArray();
        if (! $company) {
            $this->clearScopeInSession();

            return true;
        }

        $this->company      = $company;
        $this->supportGrant = $grant;
        $this->scope        = self::SCOPE_COMPANY;
        $this->readOnly     = true;
        $this->readOnlyReason = lang('Core.readOnlySupport');
        $this->permissions  = array_fill_keys(Services::rbac()->expandGrants(config(Rbac::class)->supportGrants, self::SCOPE_COMPANY), true);
        $this->persist();

        return true;
    }

    private function hasPlatformPermission(string $permission): bool
    {
        $rows = db_connect()->table('memberships')
            ->select('role_id')
            ->where('user_id', $this->user['id'])
            ->where('scope', self::SCOPE_PLATFORM)
            ->where('status', 'active')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            if (in_array($permission, Services::rbac()->permissionsForRole((int) $row['role_id']), true)) {
                return true;
            }
        }

        return false;
    }

    /** Suscripción suspendida/cancelada o negocio suspendido ⇒ solo lectura y exportación. */
    private function evaluateCompanyAccessMode(): void
    {
        if ($this->company['status'] === 'suspended') {
            $this->readOnly       = true;
            $this->readOnlyReason = lang('Core.readOnlyCompanySuspended');

            return;
        }

        $sub = Services::subscriptions()->currentForCompany((int) $this->company['id']);
        if ($sub && in_array($sub['status'], ['suspended', 'cancelled', 'archived'], true)) {
            $this->readOnly       = true;
            $this->readOnlyReason = lang('Core.readOnlySubscription');
        }
    }

    private function clearScopeInSession(): void
    {
        session()->remove(['membership_id', 'support_grant_id', 'active_scope', 'active_tenant_id', 'active_company_id']);
        $this->membership = $this->tenant = $this->company = $this->supportGrant = null;
        $this->scope       = null;
        $this->permissions = [];
    }

    // ------------------------------------------------------------- consultas

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user ? (int) $this->user['id'] : null;
    }

    public function scope(): ?string
    {
        return $this->scope;
    }

    public function membership(): ?array
    {
        return $this->membership;
    }

    public function tenant(): ?array
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        if ($this->scope === self::SCOPE_TENANT) {
            return (int) $this->tenant['id'];
        }

        return null; // en ámbito negocio el tenant solo se usa para marca, nunca para autorizar
    }

    public function company(): ?array
    {
        return $this->company;
    }

    public function companyId(): ?int
    {
        return $this->scope === self::SCOPE_COMPANY ? (int) $this->company['id'] : null;
    }

    public function supportGrant(): ?array
    {
        return $this->supportGrant;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function readOnlyReason(): ?string
    {
        return $this->readOnlyReason;
    }

    public function isApi(): bool
    {
        return $this->viaApi;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_keys($this->permissions);
    }

    /** Restringe los permisos a un subconjunto (habilidades de un token API). */
    public function restrictTo(array $abilities): void
    {
        $this->permissions = array_intersect_key($this->permissions, array_fill_keys($abilities, true));
    }

    /**
     * ¿Puede el actor ejecutar `$permission` en el contexto activo?
     * Comprueba: permiso explícito + componente habilitado + plan del negocio. Denegar por defecto.
     */
    public function can(string $permission): bool
    {
        if (! isset($this->permissions[$permission])) {
            return false;
        }

        $component = Services::rbac()->componentOf($permission);
        if ($component === null) {
            return true;
        }

        $manager = Services::components();
        if (! $manager->isEnabled($component)) {
            return false;
        }

        return $this->scope !== self::SCOPE_COMPANY
            || Services::plans()->componentAllowed((int) $this->company['id'], $component);
    }

    /** Nombre visible de la organización activa. */
    public function organizationName(): ?string
    {
        return match ($this->scope) {
            self::SCOPE_COMPANY  => $this->company['name'],
            self::SCOPE_TENANT   => $this->tenant['name'],
            self::SCOPE_PLATFORM => lang('Core.platform'),
            default              => null,
        };
    }

    /** Fija el ámbito en la sesión (llamado por ContextSwitcher tras autorizar). */
    public function persist(): void
    {
        session()->set([
            'membership_id'     => $this->membership['id'] ?? null,
            'support_grant_id'  => $this->supportGrant['id'] ?? null,
            'active_scope'      => $this->scope,
            'active_tenant_id'  => $this->tenantId(),
            'active_company_id' => $this->companyId(),
        ]);
    }
}
