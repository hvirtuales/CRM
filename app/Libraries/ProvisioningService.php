<?php

namespace App\Libraries;

use Config\Services;

/**
 * Alta de distribuidores y negocios con su propietario inicial y plan (US-001, US-002).
 * El tenant de un negocio lo decide el ámbito del actor, nunca el formulario.
 */
class ProvisioningService
{
    /**
     * @param array<string, mixed> $data name, slug?, tax_id?, contact_email?, commission_rate?, owner_name, owner_email
     */
    public function createTenant(array $data, int $actorId): Result
    {
        $errors = $this->validateOrg($data, 'tenants');
        $rate   = (string) ($data['commission_rate'] ?? '0');
        if (! preg_match('/^(0(\.\d{1,4})?|1(\.0{1,4})?)$/', $rate)) {
            $errors['commission_rate'] = lang('Core.validation.rate');
        }
        $errors += $this->validateOwner($data);
        if ($errors !== []) {
            return Result::fail(lang('Core.validation.fix'), $errors);
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('tenants')->insert([
            'public_id' => Ids::publicId(), 'name' => trim($data['name']), 'slug' => $this->uniqueSlug('tenants', $data['slug'] ?? $data['name']),
            'tax_id' => $this->nullable($data['tax_id'] ?? null), 'contact_email' => $this->nullable(strtolower((string) ($data['contact_email'] ?? ''))),
            'status' => 'active', 'commission_rate' => $rate, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $tenantId = (int) $db->insertID();

        $owner = Services::users()->addMember('tenant', $tenantId, null, [
            'name' => $data['owner_name'], 'email' => $data['owner_email'],
            'role_id' => Services::rbac()->systemRoleId('tenant_owner'),
        ], $actorId);
        if (! $owner->ok) {
            $db->transRollback();

            return $owner;
        }
        $db->transComplete();

        Services::audit()->log('tenant.created', 'tenant', $tenantId, ['name' => $data['name']], null, $tenantId);

        return Result::success(['id' => $tenantId, 'temp_password' => $owner->data['temp_password']]);
    }

    /**
     * @param int|null $tenantId tenant del actor (NULL = negocio directo de plataforma)
     */
    public function createCompany(array $data, ?int $tenantId, int $actorId): Result
    {
        $errors = $this->validateOrg($data, 'companies');
        $errors += $this->validateOwner($data);
        $planId = (int) ($data['plan_id'] ?? 0);
        if ($planId > 0 && ! $this->planSellable($planId, $tenantId !== null)) {
            $errors['plan_id'] = lang('Core.planInvalid');
        }
        $tz = (string) ($data['timezone'] ?? 'America/Bogota');
        if (! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = lang('Core.validation.timezone');
        }
        $currency = strtoupper((string) ($data['currency'] ?? 'COP'));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = lang('Core.validation.currency');
        }
        if ($errors !== []) {
            return Result::fail(lang('Core.validation.fix'), $errors);
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('companies')->insert([
            'public_id' => Ids::publicId(), 'tenant_id' => $tenantId, 'name' => trim($data['name']),
            'slug' => $this->uniqueSlug('companies', $data['slug'] ?? $data['name']),
            'tax_id' => $this->nullable($data['tax_id'] ?? null), 'email' => $this->nullable(strtolower((string) ($data['email'] ?? ''))),
            'phone' => $this->nullable($data['phone'] ?? null), 'status' => 'active', 'timezone' => $tz,
            'currency' => $currency, 'locale' => in_array($data['locale'] ?? 'es', ['es', 'en'], true) ? $data['locale'] ?? 'es' : 'es',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $companyId = (int) $db->insertID();

        $owner = Services::users()->addMember('company', null, $companyId, [
            'name' => $data['owner_name'], 'email' => $data['owner_email'],
            'role_id' => Services::rbac()->systemRoleId('business_owner'),
        ], $actorId);
        if (! $owner->ok) {
            $db->transRollback();

            return $owner;
        }
        if ($planId > 0) {
            $sub = Services::subscriptions()->assignPlan($companyId, $planId, $tenantId, $actorId);
            if (! $sub->ok) {
                $db->transRollback();

                return $sub;
            }
        }
        $db->transComplete();

        Services::audit()->log('company.created', 'company', $companyId, ['name' => $data['name'], 'tenant_id' => $tenantId], $companyId, $tenantId);

        return Result::success(['id' => $companyId, 'temp_password' => $owner->data['temp_password']]);
    }

    /** Traslado entre distribuidores: privilegiado, justificado y con historial. */
    public function transferCompany(int $companyId, ?int $toTenantId, string $reason, int $actorId): Result
    {
        $db      = db_connect();
        $company = $db->table('companies')->where('id', $companyId)->get()->getRowArray();
        if (! $company) {
            return Result::notFound();
        }
        if (mb_strlen(trim($reason)) < 10) {
            return Result::fail(lang('Core.validation.reason'), ['reason' => lang('Core.validation.reason')]);
        }
        if ($toTenantId !== null && ! $db->table('tenants')->where('id', $toTenantId)->where('status', 'active')->countAllResults()) {
            return Result::fail(lang('Core.validation.tenant'));
        }
        if ((int) $company['tenant_id'] === (int) $toTenantId) {
            return Result::fail(lang('Core.transferSame'));
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('company_transfers')->insert([
            'company_id' => $companyId, 'from_tenant_id' => $company['tenant_id'], 'to_tenant_id' => $toTenantId,
            'reason' => trim($reason), 'actor_user_id' => $actorId, 'created_at' => $now,
        ]);
        $db->table('companies')->where('id', $companyId)->update(['tenant_id' => $toTenantId, 'updated_at' => $now]);
        // Las comisiones futuras pasan al nuevo distribuidor.
        $db->table('subscriptions')->where('company_id', $companyId)->whereIn('status', SubscriptionService::OPEN_STATUSES)
            ->update(['sold_by_tenant_id' => $toTenantId, 'updated_at' => $now]);
        $db->transComplete();

        Services::audit()->log('company.transferred', 'company', $companyId, ['from' => $company['tenant_id'], 'to' => $toTenantId, 'reason' => $reason], $companyId);

        return Result::success();
    }

    public function setStatus(string $table, int $id, string $status): Result
    {
        if (! in_array($table, ['tenants', 'companies'], true) || ! in_array($status, ['active', 'suspended', 'archived'], true)) {
            return Result::fail(lang('Core.validation.fix'));
        }
        $db = db_connect();
        if (! $db->table($table)->where('id', $id)->countAllResults()) {
            return Result::notFound();
        }
        $db->table($table)->where('id', $id)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log(rtrim($table, 's') . '.status_' . $status, rtrim($table, 's'), $id, [], $table === 'companies' ? $id : null, $table === 'tenants' ? $id : null);

        return Result::success();
    }

    public function planSellable(int $planId, bool $byTenant): bool
    {
        $b = db_connect()->table('plans')->where('id', $planId)->where('is_active', 1)->where('target', 'company');
        if ($byTenant) {
            $b->where('is_public', 1);
        }

        return $b->countAllResults() > 0;
    }

    /** @return array<string, string> */
    private function validateOrg(array $data, string $table): array
    {
        $errors = [];
        $name   = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = lang('Core.validation.name');
        }
        foreach (['contact_email', 'email'] as $f) {
            if (! empty($data[$f]) && ! filter_var($data[$f], FILTER_VALIDATE_EMAIL)) {
                $errors[$f] = lang('Core.validation.email');
            }
        }
        if (! empty($data['tax_id']) && ! preg_match('/^[0-9A-Za-z.\-]{3,40}$/', (string) $data['tax_id'])) {
            $errors['tax_id'] = lang('Core.validation.taxId');
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function validateOwner(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['owner_name'] ?? '')) === '') {
            $errors['owner_name'] = lang('Core.validation.name');
        }
        if (! filter_var($data['owner_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['owner_email'] = lang('Core.validation.email');
        }

        return $errors;
    }

    private function uniqueSlug(string $table, string $text): string
    {
        $base = Ids::slug($text, 60);
        $slug = $base;
        $i    = 2;
        while (db_connect()->table($table)->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function nullable(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
