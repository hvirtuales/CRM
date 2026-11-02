<?php

namespace Components\Crm\Libraries;

/**
 * Interfaz PÚBLICA del CRM para otros componentes (p. ej. Formularios): captura un prospecto
 * dentro de UN negocio. Deduplica el contacto en ese negocio, registra consentimiento y crea
 * la oportunidad en la primera etapa abierta. Los demás componentes no tocan tablas cmp_crm_*.
 */
class LeadCapture
{
    /**
     * @param array{first_name?:string,last_name?:string,organization?:string,email?:string,phone?:string,city?:string,notes?:string} $contact
     * @param array{title?:string, amount?:string, project_id?:int|null, consent?:bool, consent_source?:string, create_opportunity?:bool} $options
     *
     * @return Result data: contact_id, opportunity_id|null, deduplicated
     */
    public function capture(int $companyId, array $contact, array $options, string $source = 'form'): Result
    {
        $contacts = new ContactService();
        $email    = strtolower(trim((string) ($contact['email'] ?? '')));
        $phone    = ContactService::normalizePhone($contact['phone'] ?? null);
        $existing = $contacts->findDuplicate($companyId, $email ?: null, $phone);
        $consent  = ! empty($options['consent']);

        if ($existing) {
            $contactId = (int) $existing['id'];
            if ($consent && (int) $existing['consent_marketing'] !== 1) {
                db_connect()->table('cmp_crm_contacts')->where('id', $contactId)->where('company_id', $companyId)->update([
                    'consent_marketing' => 1, 'consent_at' => date('Y-m-d H:i:s'),
                    'consent_source' => mb_substr((string) ($options['consent_source'] ?? $source), 0, 120), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $dedup = true;
        } else {
            $r = $contacts->create($companyId, $contact + ['consent_marketing' => $consent ? 1 : 0, 'consent_source' => $options['consent_source'] ?? $source], null, $source);
            if (! $r->ok) {
                return $r;
            }
            $contactId = (int) $r->data['id'];
            $dedup     = false;
        }

        $oppId = null;
        if (($options['create_opportunity'] ?? true) === true) {
            $owner = $this->defaultOwner($companyId);
            if ($owner !== null) {
                $title = trim((string) ($options['title'] ?? '')) ?: lang('Crm.leadTitle', [$contacts->displayName($contacts->find($companyId, $contactId))]);
                $r     = (new OpportunityService())->create($companyId, [
                    'title' => mb_substr($title, 0, 150), 'contact_id' => $contactId, 'amount' => $options['amount'] ?? '0',
                    'project_id' => isset($options['project_id']) ? (string) $options['project_id'] : '',
                    'owner_user_id' => (string) $owner,
                ], $owner, $source);
                if ($r->ok) {
                    $oppId = (int) $r->data['id'];
                }
            }
        }

        return Result::success(['contact_id' => $contactId, 'opportunity_id' => $oppId, 'deduplicated' => $dedup]);
    }

    /** Responsable por defecto: el propietario activo más antiguo del negocio. */
    private function defaultOwner(int $companyId): ?int
    {
        $row = db_connect()->table('memberships m')->select('m.user_id')->join('roles r', 'r.id = m.role_id')
            ->where('m.company_id', $companyId)->where('m.scope', 'company')->where('m.status', 'active')
            ->orderBy("CASE WHEN r.code = 'business_owner' THEN 0 WHEN r.code = 'sales_manager' THEN 1 ELSE 2 END", '', false)
            ->orderBy('m.id', 'ASC')->get(1)->getRowArray();

        return $row ? (int) $row['user_id'] : null;
    }
}
