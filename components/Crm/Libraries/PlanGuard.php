<?php

namespace Components\Crm\Libraries;

/**
 * Hace valer el plan en el SERVICIO (no en la vista). Sin plan asignado ⇒ permitido e ilimitado.
 */
class PlanGuard
{
    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->planFor($companyId);

        return $plan === null || (int) ($plan['crm_enabled'] ?? 1) === 1;
    }

    /** null = ilimitado */
    public function contactsLeft(int $companyId): ?int
    {
        return $this->left($companyId, 'max_contacts', 'cmp_crm_contacts');
    }

    public function opportunitiesLeft(int $companyId): ?int
    {
        return $this->left($companyId, 'max_opportunities', 'cmp_crm_opportunities');
    }

    private function left(int $companyId, string $column, string $table): ?int
    {
        $plan = $this->planFor($companyId);
        $max  = $plan === null ? 0 : (int) ($plan[$column] ?? 0);
        if ($max <= 0) {
            return null;
        }
        $used = db_connect()->table($table)->where('company_id', $companyId)->where('deleted_at', null)->countAllResults();

        return max(0, $max - $used);
    }

    private function planFor(int $companyId): ?array
    {
        $row = db_connect()->table('companies c')->select('p.*')->join('plans p', 'p.id = c.plan_id')
            ->where('c.id', $companyId)->get()->getRowArray();

        return $row ?: null;
    }
}
