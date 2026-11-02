<?php

namespace Components\Crm\Libraries;

use Components\Crm\Models\ProjectModel;
use Components\Crm\Models\StageModel;

/**
 * Proyectos (líneas de servicio / marcas) y etapas del embudo de un negocio.
 */
class SetupService
{
    public const KINDS = ['open', 'won', 'lost'];

    /** Crea el embudo por defecto la primera vez que el negocio usa el CRM. Idempotente. */
    public function ensureDefaults(int $companyId): void
    {
        $stages = new StageModel();
        if ($stages->forCompany($companyId)->countAllResults() > 0) {
            return;
        }
        $defaults = [
            ['stageNew', 'open', 10], ['stageContacted', 'open', 25], ['stageProposal', 'open', 50],
            ['stageNegotiation', 'open', 75], ['stageWon', 'won', 100], ['stageLost', 'lost', 0],
        ];
        foreach ($defaults as $i => [$key, $kind, $prob]) {
            $stages->insert([
                'company_id' => $companyId, 'name' => lang('Crm.' . $key), 'kind' => $kind,
                'probability' => $prob, 'position' => ($i + 1) * 10,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function stages(int $companyId): array
    {
        $this->ensureDefaults($companyId);

        return (new StageModel())->forCompany($companyId)->orderBy('position', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /** @return list<array<string, mixed>> */
    public function projects(int $companyId, bool $onlyActive = false): array
    {
        $m = (new ProjectModel())->forCompany($companyId);
        if ($onlyActive) {
            $m->where('status', 'active');
        }

        return $m->orderBy('name', 'ASC')->findAll();
    }

    public function stageOf(int $companyId, int $stageId): ?array
    {
        return (new StageModel())->forCompany($companyId)->where('id', $stageId)->first();
    }

    public function firstOpenStage(int $companyId): array
    {
        $this->ensureDefaults($companyId);

        return (new StageModel())->forCompany($companyId)->where('kind', 'open')->orderBy('position', 'ASC')->first();
    }

    public function stageOfKind(int $companyId, string $kind): ?array
    {
        return (new StageModel())->forCompany($companyId)->where('kind', $kind)->orderBy('position', 'ASC')->first();
    }

    public function projectOf(int $companyId, int $projectId): ?array
    {
        return (new ProjectModel())->forCompany($companyId)->where('id', $projectId)->first();
    }

    public function saveProject(int $companyId, array $data, ?int $id = null): Result
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = lang('Crm.validation.name');
        }
        if ($code === '') {
            $code = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\transliterator_transliterate('Any-Latin; Latin-ASCII', $name) ?: $name)), '-');
        }
        if (! preg_match('/^[a-z0-9\-]{2,60}$/', $code)) {
            $errors['code'] = lang('Crm.validation.code');
        }
        $status = in_array($data['status'] ?? 'active', ['active', 'archived'], true) ? ($data['status'] ?? 'active') : 'active';

        $model = new ProjectModel();
        $dup   = $model->forCompany($companyId)->where('code', $code)->withDeleted()->first();
        if ($dup && (int) $dup['id'] !== (int) $id) {
            $errors['code'] = lang('Crm.validation.codeTaken');
        }
        if ($errors) {
            return Result::fail(lang('Crm.validation.fix'), $errors);
        }

        $row = ['name' => $name, 'code' => $code, 'description' => trim((string) ($data['description'] ?? '')) ?: null, 'status' => $status];
        if ($id === null) {
            $model->insert($row + ['company_id' => $companyId]);

            return Result::success(['id' => (int) $model->getInsertID()], lang('Crm.projectSaved'));
        }
        if (! $this->projectOf($companyId, $id)) {
            return Result::notFound();
        }
        $model->update($id, $row);

        return Result::success(['id' => $id], lang('Crm.projectSaved'));
    }

    public function saveStage(int $companyId, array $data, ?int $id = null): Result
    {
        $name = trim((string) ($data['name'] ?? ''));
        $kind = (string) ($data['kind'] ?? 'open');
        $prob = (string) ($data['probability'] ?? '0');
        $pos  = (string) ($data['position'] ?? '0');
        $errors = [];
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'] = lang('Crm.validation.name');
        }
        if (! in_array($kind, self::KINDS, true)) {
            $errors['kind'] = lang('Crm.validation.fix');
        }
        if (! ctype_digit($prob) || (int) $prob > 100) {
            $errors['probability'] = lang('Crm.validation.probability');
        }
        if (! ctype_digit($pos) || (int) $pos > 10000) {
            $errors['position'] = lang('Crm.validation.integer');
        }
        if ($errors) {
            return Result::fail(lang('Crm.validation.fix'), $errors);
        }
        $row   = ['name' => $name, 'kind' => $kind, 'probability' => (int) $prob, 'position' => (int) $pos];
        $model = new StageModel();
        if ($id === null) {
            $model->insert($row + ['company_id' => $companyId]);

            return Result::success(null, lang('Crm.stageSaved'));
        }
        $stage = $this->stageOf($companyId, $id);
        if (! $stage) {
            return Result::notFound();
        }
        // No dejar el embudo sin una etapa abierta (las nuevas oportunidades entran ahí).
        if ($stage['kind'] === 'open' && $kind !== 'open'
            && $model->forCompany($companyId)->where('kind', 'open')->countAllResults() <= 1) {
            return Result::fail(lang('Crm.lastOpenStage'));
        }
        $model->update($id, $row);

        return Result::success(null, lang('Crm.stageSaved'));
    }

    public function deleteStage(int $companyId, int $id): Result
    {
        $stage = $this->stageOf($companyId, $id);
        if (! $stage) {
            return Result::notFound();
        }
        $inUse = db_connect()->table('cmp_crm_opportunities')->where('company_id', $companyId)->where('stage_id', $id)->countAllResults();
        if ($inUse > 0) {
            return Result::fail(lang('Crm.stageInUse', [$inUse]), [], 409);
        }
        if ($stage['kind'] === 'open' && (new StageModel())->forCompany($companyId)->where('kind', 'open')->countAllResults() <= 1) {
            return Result::fail(lang('Crm.lastOpenStage'), [], 409);
        }
        (new StageModel())->delete($id);

        return Result::success(null, lang('Crm.stageDeleted'));
    }
}
