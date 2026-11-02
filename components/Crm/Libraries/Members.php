<?php

namespace Components\Crm\Libraries;

/**
 * Lectura mínima de miembros del negocio (superficie del núcleo: users + memberships).
 */
final class Members
{
    public static function isActiveMember(int $companyId, int $userId): bool
    {
        return db_connect()->table('memberships')->where('company_id', $companyId)->where('scope', 'company')
            ->where('user_id', $userId)->where('status', 'active')->countAllResults() > 0;
    }

    /** @return array<int, string> id => nombre */
    public static function options(int $companyId): array
    {
        $rows = db_connect()->table('memberships m')->select('u.id, u.name')->join('users u', 'u.id = m.user_id')
            ->where('m.company_id', $companyId)->where('m.scope', 'company')->where('m.status', 'active')
            ->orderBy('u.name')->get()->getResultArray();

        return array_column($rows, 'name', 'id');
    }
}
