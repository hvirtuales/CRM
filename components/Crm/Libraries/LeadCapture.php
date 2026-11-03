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
        $project  = isset($options['project_id']) && (int) $options['project_id'] > 0
            && (new SetupService())->projectOf($companyId, (int) $options['project_id']) ? (int) $options['project_id'] : null;
        $wantsOpp = ($options['create_opportunity'] ?? true) === true;
        // Reparto automático por proyecto (AssignmentService): un solo responsable para contacto y oportunidad.
        $owner    = (new AssignmentService())->nextOwner($companyId, $project);

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
            $r = $contacts->create($companyId, $contact + ['consent_marketing' => $consent ? 1 : 0, 'consent_source' => $options['consent_source'] ?? $source]
                + ($owner !== null ? ['owner_user_id' => (string) $owner] : []), null, $source);
            if (! $r->ok) {
                return $r;
            }
            $contactId = (int) $r->data['id'];
            $dedup     = false;
        }

        $oppId = null;
        if ($wantsOpp) {
            if ($owner !== null) {
                $title = trim((string) ($options['title'] ?? '')) ?: lang('Crm.leadTitle', [$contacts->displayName($contacts->find($companyId, $contactId))]);
                $r     = (new OpportunityService())->create($companyId, [
                    'title' => mb_substr($title, 0, 150), 'contact_id' => $contactId, 'amount' => $options['amount'] ?? '0',
                    'project_id' => $project !== null ? (string) $project : '',
                    'owner_user_id' => (string) $owner,
                ], $owner, $source);
                if ($r->ok) {
                    $oppId = (int) $r->data['id'];
                    $name  = (string) (db_connect()->table('users')->select('name')->where('id', $owner)->get()->getRow()->name ?? '');
                    (new OpportunityService())->log($companyId, $oppId, $contactId, null, 'system', lang('Crm.autoAssigned', [$name]));
                }
            }
        }

        return Result::success(['contact_id' => $contactId, 'opportunity_id' => $oppId, 'deduplicated' => $dedup]);
    }

}
