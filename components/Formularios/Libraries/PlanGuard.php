<?php

namespace Components\Formularios\Libraries;

/** Plan aplicado en el servicio. Sin plan ⇒ permitido e ilimitado. */
class PlanGuard
{
    public function serviceEnabled(int $companyId): bool
    {
        $plan = $this->planFor($companyId);

        return $plan === null || (int) ($plan['form_enabled'] ?? 1) === 1;
    }

    public function formsLeft(int $companyId): ?int
    {
        $plan = $this->planFor($companyId);
        $max  = $plan === null ? 0 : (int) ($plan['max_forms'] ?? 0);
        if ($max <= 0) {
            return null;
        }
        $used = db_connect()->table('cmp_form_forms')->where('company_id', $companyId)->where('deleted_at', null)->countAllResults();

        return max(0, $max - $used);
    }

    private function planFor(int $companyId): ?array
    {
        return db_connect()->table('companies c')->select('p.*')->join('plans p', 'p.id = c.plan_id')
            ->where('c.id', $companyId)->get()->getRowArray() ?: null;
    }
}
