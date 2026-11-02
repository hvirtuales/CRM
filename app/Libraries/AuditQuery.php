<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;

/** Consulta de auditoría con filtros seguros; el ámbito lo fija el llamador, nunca el GET. */
final class AuditQuery
{
    /**
     * @param array<string, mixed> $get
     */
    public static function builder(array $get, ?int $tenantId = null, ?int $companyId = null): BaseBuilder
    {
        $f = self::filters($get);
        $b = db_connect()->table('audit_events a')
            ->select('a.*, u.name AS actor_name, u.email AS actor_email')
            ->join('users u', 'u.id = a.actor_user_id', 'left');
        if ($tenantId !== null) {
            $b->where('a.tenant_id', $tenantId);
        }
        if ($companyId !== null) {
            $b->where('a.company_id', $companyId);
        }
        if ($f['action'] !== '') {
            $b->like('a.action', $f['action'], 'after');
        }
        if ($f['from'] !== '') {
            $b->where('a.occurred_at >=', $f['from'] . ' 00:00:00');
        }
        if ($f['to'] !== '') {
            $b->where('a.occurred_at <=', $f['to'] . ' 23:59:59');
        }

        return $b->orderBy('a.id', 'DESC');
    }

    /**
     * @param array<string, mixed> $get
     *
     * @return array{action:string, from:string, to:string}
     */
    public static function filters(array $get): array
    {
        $date = static fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        $action = is_string($get['action'] ?? null) && preg_match('/^[a-z_.]{1,60}$/', $get['action']) ? $get['action'] : '';

        return ['action' => $action, 'from' => $date($get['from'] ?? null), 'to' => $date($get['to'] ?? null)];
    }
}
