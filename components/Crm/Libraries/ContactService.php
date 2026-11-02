<?php

namespace Components\Crm\Libraries;

use Components\Crm\Models\ActivityModel;
use Components\Crm\Models\ContactModel;
use Config\Services;

/**
 * Contactos del negocio. Deduplicación SOLO dentro del negocio (correo o teléfono normalizado):
 * la coincidencia entre negocios distintos nunca autoriza compartir perfiles.
 */
class ContactService
{
    public function __construct(private readonly PlanGuard $guard = new PlanGuard())
    {
    }

    /**
     * @param array<string, mixed> $filters q, status, owner
     */
    public function search(int $companyId, array $filters = []): ContactModel
    {
        $m = (new ContactModel())->forCompany($companyId);
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $m->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('organization', $q)
                ->orLike('email', $q)->orLike('phone', $q)->groupEnd();
        }
        $status = (string) ($filters['status'] ?? 'active');
        if (in_array($status, ['active', 'archived'], true)) {
            $m->where('status', $status);
        }

        return $m->orderBy('first_name', 'ASC');
    }

    public function find(int $companyId, int $id): ?array
    {
        return (new ContactModel())->forCompany($companyId)->where('id', $id)->first();
    }

    public function create(int $companyId, array $data, ?int $actorId, string $source = 'manual'): Result
    {
        if (! $this->guard->serviceEnabled($companyId)) {
            return Result::fail(lang('Crm.serviceDisabled'), [], 403);
        }
        $left = $this->guard->contactsLeft($companyId);
        if ($left !== null && $left <= 0) {
            return Result::fail(lang('Crm.contactsQuotaExhausted'), [], 409);
        }

        $clean = $this->validate($companyId, $data);
        if ($clean instanceof Result) {
            return $clean;
        }
        if ($dup = $this->findDuplicate($companyId, $clean['email'], $clean['phone_normalized'])) {
            return Result::fail(lang('Crm.duplicateContact', [$this->displayName($dup)]), ['email' => lang('Crm.duplicateContact', [$this->displayName($dup)])], 409);
        }

        $clean += ['company_id' => $companyId, 'source' => $source, 'status' => 'active'];
        $clean['owner_user_id'] ??= $actorId;
        if (! empty($clean['consent_marketing'])) {
            $clean['consent_at']     = date('Y-m-d H:i:s');
            $clean['consent_source'] = $clean['consent_source'] ?? $source;
        }

        $model = new ContactModel();
        $model->insert($clean);
        $id = (int) $model->getInsertID();
        Services::audit()->log('crm.contact_created', 'crm_contact', $id, ['source' => $source], $companyId);

        return Result::success(['id' => $id], lang('Crm.contactSaved'));
    }

    public function update(int $companyId, int $id, array $data): Result
    {
        $contact = $this->find($companyId, $id);
        if (! $contact) {
            return Result::notFound();
        }
        $clean = $this->validate($companyId, $data);
        if ($clean instanceof Result) {
            return $clean;
        }
        $dup = $this->findDuplicate($companyId, $clean['email'], $clean['phone_normalized']);
        if ($dup && (int) $dup['id'] !== $id) {
            return Result::fail(lang('Crm.duplicateContact', [$this->displayName($dup)]), ['email' => lang('Crm.duplicateContact', [$this->displayName($dup)])], 409);
        }
        // Registrar consentimiento solo cuando cambia (trazabilidad).
        if (! empty($clean['consent_marketing']) && (int) $contact['consent_marketing'] !== 1) {
            $clean['consent_at']     = date('Y-m-d H:i:s');
            $clean['consent_source'] = 'manual';
        } elseif (empty($clean['consent_marketing']) && (int) $contact['consent_marketing'] === 1) {
            $clean['consent_at']     = date('Y-m-d H:i:s');
            $clean['consent_source'] = 'revoked';
        }
        (new ContactModel())->update($id, $clean);
        Services::audit()->log('crm.contact_updated', 'crm_contact', $id, [], $companyId);

        return Result::success(['id' => $id], lang('Crm.contactSaved'));
    }

    /** Archivar (no borrar): conserva el historial. */
    public function archive(int $companyId, int $id, bool $archived = true): Result
    {
        if (! $this->find($companyId, $id)) {
            return Result::notFound();
        }
        (new ContactModel())->update($id, ['status' => $archived ? 'archived' : 'active']);
        Services::audit()->log($archived ? 'crm.contact_archived' : 'crm.contact_restored', 'crm_contact', $id, [], $companyId);

        return Result::success(null, $archived ? lang('Crm.contactArchived') : lang('Crm.contactRestored'));
    }

    public function findDuplicate(int $companyId, ?string $email, ?string $phoneNorm): ?array
    {
        if (! $email && ! $phoneNorm) {
            return null;
        }
        $m = (new ContactModel())->forCompany($companyId)->groupStart();
        if ($email) {
            $m->where('email', $email);
        }
        if ($phoneNorm) {
            $email ? $m->orWhere('phone_normalized', $phoneNorm) : $m->where('phone_normalized', $phoneNorm);
        }

        return $m->groupEnd()->first();
    }

    /** @return list<array<string,mixed>> */
    public function activities(int $companyId, int $contactId): array
    {
        return (new ActivityModel())->forCompany($companyId)->where('contact_id', $contactId)->orderBy('id', 'DESC')->findAll(50);
    }

    /**
     * Interfaz pública para otros componentes: nombres y correo de varios contactos del negocio.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{name:string, organization:?string, email:?string}>
     */
    public function summaries(int $companyId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ((new ContactModel())->forCompany($companyId)->withDeleted()->whereIn('id', $ids)->findAll() as $c) {
            $out[(int) $c['id']] = ['name' => $this->displayName($c), 'organization' => $c['organization'], 'email' => $c['email']];
        }

        return $out;
    }

    public function displayName(array $c): string
    {
        return trim($c['first_name'] . ' ' . ($c['last_name'] ?? '')) ?: (string) $c['organization'];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '57')) {
            $digits = substr($digits, 2); // Colombia: +57 3001234567 ≡ 3001234567
        }

        return $digits === '' ? null : substr($digits, -15);
    }

    /**
     * @return array<string, mixed>|Result
     */
    private function validate(int $companyId, array $d): array|Result
    {
        $errors = [];
        $kind   = in_array($d['kind'] ?? 'person', ['person', 'organization'], true) ? ($d['kind'] ?? 'person') : 'person';
        $first  = trim((string) ($d['first_name'] ?? ''));
        $org    = trim((string) ($d['organization'] ?? ''));
        if ($first === '' && $org === '') {
            $errors['first_name'] = lang('Crm.validation.nameOrOrg');
        }
        if (mb_strlen($first) > 80 || mb_strlen((string) ($d['last_name'] ?? '')) > 80 || mb_strlen($org) > 150) {
            $errors['first_name'] = lang('Crm.validation.tooLong');
        }
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
            $errors['email'] = lang('Crm.validation.email');
        }
        $phone = trim((string) ($d['phone'] ?? ''));
        if ($phone !== '' && ! preg_match('/^[0-9+()\s\-]{7,40}$/', $phone)) {
            $errors['phone'] = lang('Crm.validation.phone');
        }
        if ($email === '' && $phone === '') {
            $errors['email'] = lang('Crm.validation.emailOrPhone');
        }
        $owner = isset($d['owner_user_id']) && ctype_digit((string) $d['owner_user_id']) && (int) $d['owner_user_id'] > 0 ? (int) $d['owner_user_id'] : null;
        if ($owner !== null && ! Members::isActiveMember($companyId, $owner)) {
            $errors['owner_user_id'] = lang('Crm.validation.owner');
        }
        if ($errors) {
            return Result::fail(lang('Crm.validation.fix'), $errors);
        }

        $out = [
            'kind' => $kind, 'first_name' => $first !== '' ? $first : $org, 'last_name' => trim((string) ($d['last_name'] ?? '')) ?: null,
            'organization' => $org ?: null, 'email' => $email ?: null, 'phone' => $phone ?: null,
            'phone_normalized' => self::normalizePhone($phone), 'city' => mb_substr(trim((string) ($d['city'] ?? '')), 0, 80) ?: null,
            'notes' => mb_substr(trim((string) ($d['notes'] ?? '')), 0, 5000) ?: null,
            'consent_marketing' => ! empty($d['consent_marketing']) ? 1 : 0,
        ];
        if ($owner !== null) {
            $out['owner_user_id'] = $owner;
        }

        return $out;
    }
}
