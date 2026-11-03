<?php

namespace Components\Crm\Libraries;

use Config\Services;

/**
 * Seguimiento que no se olvida. Alertas calculadas (siempre actuales, sin procesos en segundo plano):
 * - Acción vencida: próxima acción con fecha pasada.
 * - Sin actividad: oportunidad abierta sin gestión humana en N días (stale_days).
 * - Escalada: sin actividad en M días (escalate_days); la ven quienes gestionan el equipo (crm.manage).
 * Solo oportunidades en etapas abiertas.
 */
class FollowUpService
{
    public const DEFAULTS = ['stale_days' => 7, 'escalate_days' => 14];

    /** @return array{stale_days:int, escalate_days:int} */
    public function settings(int $companyId): array
    {
        $row = db_connect()->table('cmp_crm_settings')->where('company_id', $companyId)->get()->getRowArray();

        return $row ? ['stale_days' => (int) $row['stale_days'], 'escalate_days' => (int) $row['escalate_days']] : self::DEFAULTS;
    }

    public function saveSettings(int $companyId, array $d): Result
    {
        $stale = (string) ($d['stale_days'] ?? '');
        $esc   = (string) ($d['escalate_days'] ?? '');
        if (! ctype_digit($stale) || ! ctype_digit($esc) || (int) $stale < 1 || (int) $stale > 90 || (int) $esc <= (int) $stale || (int) $esc > 180) {
            return Result::fail(lang('Crm.validation.followUpDays'), ['stale_days' => lang('Crm.validation.followUpDays')]);
        }
        $db  = db_connect();
        $row = ['stale_days' => (int) $stale, 'escalate_days' => (int) $esc, 'updated_at' => date('Y-m-d H:i:s')];
        if ($db->table('cmp_crm_settings')->where('company_id', $companyId)->countAllResults()) {
            $db->table('cmp_crm_settings')->where('company_id', $companyId)->update($row);
        } else {
            $db->table('cmp_crm_settings')->insert($row + ['company_id' => $companyId]);
        }
        Services::audit()->log('crm.followup_settings', 'crm_settings', $companyId, $row, $companyId);

        return Result::success(null, lang('Crm.followUpSaved'));
    }

    /**
     * @return array{overdue: list<array>, stale: list<array>, escalated: list<array>, settings: array}
     */
    public function alerts(int $companyId, int $userId, bool $canManage, ?int $now = null): array
    {
        $now      = $now ?? time();
        $settings = $this->settings($companyId);
        $staleAt  = date('Y-m-d H:i:s', $now - $settings['stale_days'] * 86400);
        $escAt    = date('Y-m-d H:i:s', $now - $settings['escalate_days'] * 86400);
        $nowSql   = date('Y-m-d H:i:s', $now);

        $base = static function () use ($companyId) {
            return db_connect()->table('cmp_crm_opportunities o')
                ->select('o.id, o.title, o.amount, o.currency, o.next_action, o.next_action_at, o.last_activity_at, o.owner_user_id, u.name AS owner_name, c.first_name, c.last_name, c.organization')
                ->join('cmp_crm_stages s', 's.id = o.stage_id')
                ->join('cmp_crm_contacts c', 'c.id = o.contact_id')
                ->join('users u', 'u.id = o.owner_user_id', 'left')
                ->where('o.company_id', $companyId)->where('o.deleted_at', null)->where('s.kind', 'open');
        };

        $overdue = $base()->where('o.owner_user_id', $userId)->where('o.next_action_at <', $nowSql)
            ->orderBy('o.next_action_at', 'ASC')->get(100)->getResultArray();
        $stale = $base()->where('o.owner_user_id', $userId)->where('o.last_activity_at <', $staleAt)
            ->orderBy('o.last_activity_at', 'ASC')->get(100)->getResultArray();
        $escalated = $canManage
            ? $base()->where('o.last_activity_at <', $escAt)->orderBy('o.last_activity_at', 'ASC')->get(100)->getResultArray()
            : [];

        return compact('overdue', 'stale', 'escalated') + ['settings' => $settings];
    }

    public function pendingCount(int $companyId, int $userId, bool $canManage): int
    {
        $a = $this->alerts($companyId, $userId, $canManage);

        return count(array_unique(array_merge(array_column($a['overdue'], 'id'), array_column($a['stale'], 'id'), array_column($a['escalated'], 'id'))));
    }
}
