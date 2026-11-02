<?php

namespace Components\Crm\Libraries;

use Components\Crm\Models\ActivityModel;
use Components\Crm\Models\OpportunityModel;
use Config\Services;

/**
 * Oportunidades y embudo. Regla ABAC: con `crm.edit` solo se modifican las oportunidades propias;
 * las de otros asesores requieren además `crm.manage`. La comprobación vive aquí (web y API).
 */
class OpportunityService
{
    public const ACTIVITY_TYPES = ['note', 'call', 'email', 'meeting', 'whatsapp'];

    public function __construct(
        private readonly PlanGuard $guard = new PlanGuard(),
        private readonly SetupService $setup = new SetupService(),
    ) {
    }

    public function find(int $companyId, int $id): ?array
    {
        return (new OpportunityModel())->forCompany($companyId)->where('id', $id)->first();
    }

    /**
     * Oportunidades con datos de contacto, etapa y responsable para tablero o listado.
     *
     * @param array<string, mixed> $filters project, owner, q, mine
     *
     * @return list<array<string, mixed>>
     */
    public function board(int $companyId, array $filters = [], ?int $actorId = null): array
    {
        $b = db_connect()->table('cmp_crm_opportunities o')
            ->select('o.*, c.first_name, c.last_name, c.organization, c.email AS contact_email, c.phone AS contact_phone, u.name AS owner_name, p.name AS project_name')
            ->join('cmp_crm_contacts c', 'c.id = o.contact_id AND c.company_id = o.company_id')
            ->join('users u', 'u.id = o.owner_user_id', 'left')
            ->join('cmp_crm_projects p', 'p.id = o.project_id AND p.company_id = o.company_id', 'left')
            ->where('o.company_id', $companyId)
            ->where('o.deleted_at', null);

        if (! empty($filters['project']) && ctype_digit((string) $filters['project'])) {
            $b->where('o.project_id', (int) $filters['project']);
        }
        if (! empty($filters['mine']) && $actorId) {
            $b->where('o.owner_user_id', $actorId);
        } elseif (! empty($filters['owner']) && ctype_digit((string) $filters['owner'])) {
            $b->where('o.owner_user_id', (int) $filters['owner']);
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $b->groupStart()->like('o.title', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.organization', $q)->groupEnd();
        }

        return $b->orderBy('o.next_action_at', 'ASC')->orderBy('o.id', 'DESC')->get()->getResultArray();
    }

    /**
     * Interfaz pública: títulos de varias oportunidades del negocio.
     *
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    public function titles(int $companyId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $rows = (new OpportunityModel())->forCompany($companyId)->withDeleted()->select('id, title')->whereIn('id', $ids)->findAll();

        return array_column($rows, 'title', 'id');
    }

    /**
     * Interfaz pública: oportunidades abiertas del negocio para selectores de otros componentes.
     *
     * @return array<int, string>
     */
    public function openOptions(int $companyId): array
    {
        $rows = db_connect()->table('cmp_crm_opportunities o')->select('o.id, o.title')
            ->join('cmp_crm_stages s', 's.id = o.stage_id')
            ->where('o.company_id', $companyId)->where('o.deleted_at', null)->where('s.kind !=', 'lost')
            ->orderBy('o.id', 'DESC')->get(500)->getResultArray();

        return array_column($rows, 'title', 'id');
    }

    public function create(int $companyId, array $data, int $actorId, string $source = 'manual'): Result
    {
        if (! $this->guard->serviceEnabled($companyId)) {
            return Result::fail(lang('Crm.serviceDisabled'), [], 403);
        }
        $left = $this->guard->opportunitiesLeft($companyId);
        if ($left !== null && $left <= 0) {
            return Result::fail(lang('Crm.opportunitiesQuotaExhausted'), [], 409);
        }

        $clean = $this->validate($companyId, $data, true);
        if ($clean instanceof Result) {
            return $clean;
        }
        $stage = isset($clean['stage_id']) ? $this->setup->stageOf($companyId, $clean['stage_id']) : $this->setup->firstOpenStage($companyId);
        $clean['stage_id']    = (int) $stage['id'];
        $clean['probability'] = (int) $stage['probability'];
        // Asignar a otro asesor al crear también es gestionar registros ajenos.
        if (! isset($clean['owner_user_id']) || ((int) $clean['owner_user_id'] !== $actorId && ! auth_can('crm.manage') && $source !== 'form')) {
            $clean['owner_user_id'] = $actorId;
        }
        $clean += ['company_id' => $companyId, 'source' => $source];

        $model = new OpportunityModel();
        $model->insert($clean);
        $id = (int) $model->getInsertID();
        $this->log($companyId, $id, (int) $clean['contact_id'], $actorId, 'system', lang('Crm.activityCreated', [$stage['name']]));
        Services::audit()->log('crm.opportunity_created', 'crm_opportunity', $id, ['source' => $source], $companyId);

        return Result::success(['id' => $id], lang('Crm.opportunitySaved'));
    }

    public function update(int $companyId, int $id, array $data, int $actorId): Result
    {
        $opp = $this->find($companyId, $id);
        if (! $opp) {
            return Result::notFound();
        }
        if (! $this->canTouch($opp, $actorId)) {
            return Result::forbidden();
        }
        $clean = $this->validate($companyId, $data, false);
        if ($clean instanceof Result) {
            return $clean;
        }
        // Reasignar responsable es gestionar el registro de otro: requiere crm.manage.
        if (isset($clean['owner_user_id']) && (int) $clean['owner_user_id'] !== (int) $opp['owner_user_id'] && ! auth_can('crm.manage')) {
            return Result::forbidden();
        }
        unset($clean['stage_id']); // el cambio de etapa va por move() (trazabilidad)
        (new OpportunityModel())->update($id, $clean);
        Services::audit()->log('crm.opportunity_updated', 'crm_opportunity', $id, [], $companyId);

        return Result::success(['id' => $id], lang('Crm.opportunitySaved'));
    }

    /** Cambio de etapa (tablero kanban, ganar/perder) con bitácora. */
    public function move(int $companyId, int $id, int $stageId, int $actorId, ?string $lostReason = null): Result
    {
        $opp = $this->find($companyId, $id);
        if (! $opp) {
            return Result::notFound();
        }
        if (! $this->canTouch($opp, $actorId)) {
            return Result::forbidden();
        }
        $stage = $this->setup->stageOf($companyId, $stageId); // etapa de OTRO negocio ⇒ null
        if (! $stage) {
            return Result::fail(lang('Crm.validation.stage'));
        }
        if ((int) $opp['stage_id'] === $stageId) {
            return Result::success(['id' => $id]);
        }
        $lostReason = $lostReason !== null ? mb_substr(trim($lostReason), 0, 190) : null;
        if ($stage['kind'] === 'lost' && ($lostReason === null || $lostReason === '')) {
            return Result::fail(lang('Crm.validation.lostReason'), ['lost_reason' => lang('Crm.validation.lostReason')]);
        }

        $from = $this->setup->stageOf($companyId, (int) $opp['stage_id']);
        (new OpportunityModel())->update($id, [
            'stage_id'    => $stageId,
            'probability' => (int) $stage['probability'],
            'closed_at'   => $stage['kind'] === 'open' ? null : date('Y-m-d H:i:s'),
            'lost_reason' => $stage['kind'] === 'lost' ? $lostReason : null,
        ]);
        $text = lang('Crm.activityMoved', [$from['name'] ?? '—', $stage['name']]) . ($lostReason ? ' · ' . $lostReason : '');
        $this->log($companyId, $id, (int) $opp['contact_id'], $actorId, 'stage', $text);
        Services::audit()->log('crm.opportunity_moved', 'crm_opportunity', $id, ['from' => $opp['stage_id'], 'to' => $stageId], $companyId);

        return Result::success(['id' => $id, 'stage' => $stage], lang('Crm.opportunityMoved', [$stage['name']]));
    }

    public function archive(int $companyId, int $id): Result
    {
        if (! $this->find($companyId, $id)) {
            return Result::notFound();
        }
        (new OpportunityModel())->delete($id); // borrado lógico (deleted_at)
        Services::audit()->log('crm.opportunity_archived', 'crm_opportunity', $id, [], $companyId);

        return Result::success(null, lang('Crm.opportunityArchived'));
    }

    public function addActivity(int $companyId, int $id, string $type, string $body, int $actorId): Result
    {
        $opp = $this->find($companyId, $id);
        if (! $opp) {
            return Result::notFound();
        }
        if (! $this->canTouch($opp, $actorId)) {
            return Result::forbidden();
        }
        $body = trim($body);
        if (! in_array($type, self::ACTIVITY_TYPES, true) || $body === '' || mb_strlen($body) > 5000) {
            return Result::fail(lang('Crm.validation.activity'), ['body' => lang('Crm.validation.activity')]);
        }
        $this->log($companyId, $id, (int) $opp['contact_id'], $actorId, $type, $body);

        return Result::success(null, lang('Crm.activitySaved'));
    }

    /** @return list<array<string, mixed>> */
    public function activities(int $companyId, int $id): array
    {
        return db_connect()->table('cmp_crm_activities a')->select('a.*, u.name AS user_name')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.company_id', $companyId)->where('a.opportunity_id', $id)
            ->orderBy('a.id', 'DESC')->get(100)->getResultArray();
    }

    /** ¿Puede el actor modificar esta oportunidad? (propia, o crm.manage) */
    public function canTouch(array $opp, int $actorId): bool
    {
        return (int) $opp['owner_user_id'] === $actorId || auth_can('crm.manage');
    }

    public function log(int $companyId, ?int $oppId, ?int $contactId, ?int $userId, string $type, string $body): void
    {
        (new ActivityModel())->insert([
            'company_id' => $companyId, 'opportunity_id' => $oppId, 'contact_id' => $contactId,
            'user_id' => $userId, 'type' => $type, 'body' => $body,
        ]);
    }

    /**
     * @return array<string, mixed>|Result
     */
    private function validate(int $companyId, array $d, bool $creating): array|Result
    {
        $errors = [];
        $out    = [];

        $title = trim((string) ($d['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 150) {
            $errors['title'] = lang('Crm.validation.title');
        }
        $out['title'] = $title;

        if ($creating || isset($d['contact_id'])) {
            $cid = (int) ($d['contact_id'] ?? 0);
            if ($cid <= 0 || ! (new ContactService())->find($companyId, $cid)) {
                $errors['contact_id'] = lang('Crm.validation.contact'); // contacto de otro negocio ⇒ inválido
            }
            $out['contact_id'] = $cid;
        }

        $amount = str_replace(',', '.', trim((string) ($d['amount'] ?? '0')));
        if ($amount === '') {
            $amount = '0';
        }
        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $amount)) {
            $errors['amount'] = lang('Crm.validation.amount');
        }
        $out['amount'] = $amount;

        $currency = strtoupper(trim((string) ($d['currency'] ?? '')));
        if ($currency === '') {
            $currency = (string) (db_connect()->table('companies')->select('currency')->where('id', $companyId)->get()->getRow()->currency ?? 'COP');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = lang('Crm.validation.currency');
        }
        $out['currency'] = $currency;

        $project = (string) ($d['project_id'] ?? '');
        if ($project !== '' && $project !== '0') {
            if (! ctype_digit($project) || ! $this->setup->projectOf($companyId, (int) $project)) {
                $errors['project_id'] = lang('Crm.validation.project');
            }
            $out['project_id'] = (int) $project;
        } else {
            $out['project_id'] = null;
        }

        if (isset($d['stage_id']) && $d['stage_id'] !== '') {
            if (! ctype_digit((string) $d['stage_id']) || ! $this->setup->stageOf($companyId, (int) $d['stage_id'])) {
                $errors['stage_id'] = lang('Crm.validation.stage');
            }
            $out['stage_id'] = (int) $d['stage_id'];
        }

        if (isset($d['owner_user_id']) && $d['owner_user_id'] !== '') {
            if (! ctype_digit((string) $d['owner_user_id']) || ! Members::isActiveMember($companyId, (int) $d['owner_user_id'])) {
                $errors['owner_user_id'] = lang('Crm.validation.owner');
            }
            $out['owner_user_id'] = (int) $d['owner_user_id'];
        }

        $date = trim((string) ($d['expected_close_date'] ?? ''));
        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['expected_close_date'] = lang('Crm.validation.date');
        }
        $out['expected_close_date'] = $date ?: null;

        $out['next_action'] = mb_substr(trim((string) ($d['next_action'] ?? '')), 0, 190) ?: null;
        $nextAt = trim((string) ($d['next_action_at'] ?? ''));
        if ($nextAt !== '') {
            $ts = strtotime(str_replace('T', ' ', $nextAt));
            if ($ts === false) {
                $errors['next_action_at'] = lang('Crm.validation.date');
            } else {
                // Entrada en la zona del negocio → se almacena en UTC.
                $tz = (string) (db_connect()->table('companies')->select('timezone')->where('id', $companyId)->get()->getRow()->timezone ?? 'UTC');
                $out['next_action_at'] = (new \DateTimeImmutable(str_replace('T', ' ', $nextAt), new \DateTimeZone($tz)))
                    ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        } else {
            $out['next_action_at'] = null;
        }

        if ($errors) {
            return Result::fail(lang('Crm.validation.fix'), $errors);
        }

        return $out;
    }
}
