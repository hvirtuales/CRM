<?php

namespace App\Libraries;

use Config\Services;

/**
 * Entitlements comerciales del negocio. El plan define capacidades, nunca sustituye el RBAC.
 * Regla de compatibilidad del contrato: sin plan asignado ⇒ permitido e ilimitado.
 */
class PlanService
{
    /** @var array<int, array<string, mixed>|null> */
    private array $cache = [];

    public function planForCompany(int $companyId): ?array
    {
        if (! array_key_exists($companyId, $this->cache)) {
            $row = db_connect()->table('companies c')
                ->select('p.*')
                ->join('plans p', 'p.id = c.plan_id')
                ->where('c.id', $companyId)
                ->get()->getRowArray();
            $this->cache[$companyId] = $row ?: null;
        }

        return $this->cache[$companyId];
    }

    /** ¿El plan del negocio incluye el componente? (según `plan_flag` del Manifest) */
    public function componentAllowed(int $companyId, string $code): bool
    {
        $manifest = Services::components()->manifest($code);
        $flag     = $manifest['plan_flag'] ?? null;
        if ($flag === null) {
            return true;
        }
        $plan = $this->planForCompany($companyId);
        if ($plan === null) {
            return true;
        }

        return (int) ($plan[$flag] ?? 1) === 1;
    }

    /** Cupo restante de una columna `max_*`; null = ilimitado. */
    public function remaining(int $companyId, string $column, string $table, string $companyColumn = 'company_id'): ?int
    {
        $plan = $this->planForCompany($companyId);
        $max  = $plan === null ? 0 : (int) ($plan[$column] ?? 0);
        if ($max <= 0) {
            return null;
        }

        return max(0, $max - $this->countUsage($table, $companyColumn, $companyId));
    }

    /** Usuarios activos del negocio frente a `plans.max_users`. */
    public function usersRemaining(int $companyId): ?int
    {
        $plan = $this->planForCompany($companyId);
        $max  = $plan === null ? 0 : (int) $plan['max_users'];
        if ($max <= 0) {
            return null;
        }
        $used = db_connect()->table('memberships')->where('company_id', $companyId)->where('scope', 'company')->where('status', 'active')->countAllResults();

        return max(0, $max - $used);
    }

    public function countUsage(string $table, string $companyColumn, int $companyId): int
    {
        $db = db_connect();
        if (! preg_match('/^cmp_[a-z]{3,8}_[a-z_]+$/', $table) || ! $db->tableExists($table)) {
            return 0;
        }

        return Rls::withCompany($companyId, static function () use ($db, $table, $companyColumn, $companyId) {
            $b = $db->table($table)->where($companyColumn, $companyId);
            if ($db->fieldExists('deleted_at', $table)) {
                $b->where('deleted_at', null);
            }

            return $b->countAllResults();
        });
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
