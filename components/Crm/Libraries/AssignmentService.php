<?php

namespace Components\Crm\Libraries;

use Config\Services;

/**
 * Reparto automático de leads POR PROYECTO.
 *
 * Regla: entre los integrantes activos del equipo del proyecto, recibe el lead quien lleve más
 * tiempo sin recibir uno (rotativo justo, resistente a altas/bajas del equipo). Si el proyecto no
 * tiene equipo (o no hay proyecto), se reparte entre los asesores y gerentes comerciales activos
 * del negocio; si no hay ninguno, al propietario.
 */
class AssignmentService
{
    /** Roles que reciben leads cuando el proyecto no tiene equipo. */
    private const FALLBACK_ROLES = ['sales_agent', 'sales_manager'];

    /** Elige el responsable del siguiente lead y registra la asignación. */
    public function nextOwner(int $companyId, ?int $projectId): ?int
    {
        $db = db_connect();

        if ($projectId !== null) {
            $candidate = $db->table('cmp_crm_project_members pm')
                ->select('pm.id, pm.user_id')
                ->join('memberships m', "m.user_id = pm.user_id AND m.company_id = pm.company_id AND m.scope = 'company' AND m.status = 'active'", 'inner', false)
                ->join('users u', "u.id = pm.user_id AND u.status = 'active'", 'inner', false)
                ->where('pm.company_id', $companyId)->where('pm.project_id', $projectId)
                ->orderBy('CASE WHEN pm.last_turn IS NULL THEN 0 ELSE 1 END', '', false)
                ->orderBy('pm.last_turn', 'ASC')->orderBy('pm.id', 'ASC')
                ->get(1)->getRowArray();
            if ($candidate) {
                $turn = (int) ($db->table('cmp_crm_project_members')->selectMax('last_turn', 't')->where('project_id', $projectId)->get()->getRow()->t ?? 0) + 1;
                $db->table('cmp_crm_project_members')->where('id', $candidate['id'])
                    ->set('last_assigned_at', date('Y-m-d H:i:s'))->set('last_turn', $turn)->set('assigned_count', 'assigned_count + 1', false)->update();

                return (int) $candidate['user_id'];
            }
        }

        // Sin equipo de proyecto: rotativo entre el equipo comercial del negocio según su último lead recibido.
        $pool = $db->table('memberships m')->select('m.user_id')->join('roles r', 'r.id = m.role_id')
            ->join('users u', "u.id = m.user_id AND u.status = 'active'", 'inner', false)
            ->where('m.company_id', $companyId)->where('m.scope', 'company')->where('m.status', 'active')
            ->whereIn('r.code', self::FALLBACK_ROLES)->get()->getResultArray();
        if ($pool !== []) {
            $ranked = [];
            foreach (array_map('intval', array_column($pool, 'user_id')) as $uid) {
                $last = $db->table('cmp_crm_opportunities')->selectMax('created_at', 'm')->where('company_id', $companyId)
                    ->where('owner_user_id', $uid)->whereIn('source', ['form', 'automation'])->get()->getRow()->m ?? null;
                $ranked[] = [$last === null ? 0 : 1, (string) $last, $uid]; // nunca asignado primero; luego el más antiguo
            }
            sort($ranked);

            return $ranked[0][2];
        }

        $owner = $db->table('memberships m')->select('m.user_id')->join('roles r', 'r.id = m.role_id')
            ->where('m.company_id', $companyId)->where('m.scope', 'company')->where('m.status', 'active')
            ->where('r.code', 'business_owner')->orderBy('m.id', 'ASC')->get(1)->getRowArray();

        return $owner ? (int) $owner['user_id'] : null;
    }

    /** @return list<int> ids de usuario del equipo del proyecto */
    public function team(int $companyId, int $projectId): array
    {
        return array_map('intval', array_column(db_connect()->table('cmp_crm_project_members')->select('user_id')
            ->where('company_id', $companyId)->where('project_id', $projectId)->get()->getResultArray(), 'user_id'));
    }

    /**
     * Reemplaza el equipo del proyecto. Solo admite miembros activos del MISMO negocio.
     *
     * @param list<int|string> $userIds
     */
    public function setTeam(int $companyId, int $projectId, array $userIds): Result
    {
        if (! (new SetupService())->projectOf($companyId, $projectId)) {
            return Result::notFound();
        }
        $ids = array_values(array_unique(array_map('intval', array_filter($userIds, static fn ($v) => ctype_digit((string) $v)))));
        foreach ($ids as $uid) {
            if (! Members::isActiveMember($companyId, $uid)) {
                return Result::fail(lang('Crm.validation.owner'), ['team' => lang('Crm.validation.owner')]);
            }
        }
        $db      = db_connect();
        $current = $this->team($companyId, $projectId);
        $db->transStart();
        foreach (array_diff($current, $ids) as $uid) {
            $db->table('cmp_crm_project_members')->where('company_id', $companyId)->where('project_id', $projectId)->where('user_id', $uid)->delete();
        }
        foreach (array_diff($ids, $current) as $uid) {
            $db->table('cmp_crm_project_members')->insert(['company_id' => $companyId, 'project_id' => $projectId, 'user_id' => $uid, 'created_at' => date('Y-m-d H:i:s')]);
        }
        $db->transComplete();
        Services::audit()->log('crm.project_team_updated', 'crm_project', $projectId, ['users' => $ids], $companyId);

        return Result::success(null, lang('Crm.teamSaved'));
    }

    /** @return array<int, list<string>> project_id => nombres del equipo */
    public function teamsByProject(int $companyId): array
    {
        $rows = db_connect()->table('cmp_crm_project_members pm')->select('pm.project_id, u.name')
            ->join('users u', 'u.id = pm.user_id')->where('pm.company_id', $companyId)->orderBy('u.name')->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['project_id']][] = $r['name'];
        }

        return $out;
    }
}
