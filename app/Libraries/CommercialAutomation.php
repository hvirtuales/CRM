<?php

namespace App\Libraries;

use Config\Commercial;
use Config\Services;

/**
 * Señales comerciales de la plataforma (tarea diaria).
 *
 * Para negocios DIRECTOS crea oportunidades en el CRM de Herramientas Virtuales:
 *  - renewal: suscripción activa a ≤ N días del corte.
 *  - trial:   prueba que termina en ≤ N días.
 *  - upsell:  algún cupo del plan ≥ umbral de uso (una vez por mes).
 * Para negocios de distribuidor devuelve las mismas señales para su panel (sin tocar el CRM de HV).
 * Idempotente: commercial_signals.signal_key es único.
 */
class CommercialAutomation
{
    /**
     * Señales vigentes de un conjunto de negocios.
     *
     * @param list<array<string,mixed>> $companies filas de companies
     *
     * @return list<array{kind:string, key:string, company:array, sub:?array, detail:string, amount:string, currency:string}>
     */
    public function signalsFor(array $companies, ?int $now = null): array
    {
        $now  = $now ?? time();
        $cfg  = config(Commercial::class);
        $subs = Services::subscriptions();
        $out  = [];

        foreach ($companies as $c) {
            if ($c['status'] !== 'active') {
                continue;
            }
            $sub  = $subs->currentForCompany((int) $c['id']);
            $plan = $sub ? db_connect()->table('plans')->select('name')->where('id', $sub['plan_id'])->get()->getRow() : null;

            if ($sub && $sub['status'] === 'active' && strtotime((string) $sub['current_period_end']) <= $now + $cfg->renewalDays * 86400) {
                $out[] = ['kind' => 'renewal', 'key' => 'renewal:' . $sub['id'] . ':' . substr((string) $sub['current_period_end'], 0, 10),
                    'company' => $c, 'sub' => $sub, 'detail' => (string) ($plan->name ?? ''), 'amount' => (string) $sub['price'], 'currency' => (string) $sub['currency']];
            }
            if ($sub && $sub['status'] === 'trial' && $sub['trial_ends_at'] && strtotime((string) $sub['trial_ends_at']) <= $now + $cfg->trialDays * 86400) {
                $out[] = ['kind' => 'trial', 'key' => 'trial:' . $sub['id'],
                    'company' => $c, 'sub' => $sub, 'detail' => (string) ($plan->name ?? ''), 'amount' => (string) $sub['price'], 'currency' => (string) $sub['currency']];
            }
            if ($sub && in_array($sub['status'], ['trial', 'active'], true)) {
                $hot = [];
                foreach (Services::components()->planStatus((int) $c['id']) as $ps) {
                    foreach ($ps['limits'] as $l) {
                        if ($l['max'] > 0 && $l['used'] / $l['max'] >= $cfg->usageThreshold) {
                            $hot[] = ($l['label_key'] ? lang($l['label_key']) : $l['label']) . ' ' . $l['used'] . '/' . $l['max'];
                        }
                    }
                }
                if ($hot) {
                    $out[] = ['kind' => 'upsell', 'key' => 'upsell:' . $c['id'] . ':' . date('Y-m', $now),
                        'company' => $c, 'sub' => $sub, 'detail' => implode(', ', $hot), 'amount' => '0', 'currency' => (string) $sub['currency']];
                }
            }
        }

        return $out;
    }

    /**
     * Crea en el CRM de HV las oportunidades de los negocios directos que aún no la tienen.
     *
     * @return array{renewal:int, trial:int, upsell:int, skipped:string|null}
     */
    public function run(?int $now = null): array
    {
        $now   = $now ?? time();
        $stats = ['renewal' => 0, 'trial' => 0, 'upsell' => 0, 'skipped' => null];
        $cfg   = config(Commercial::class);
        $db    = db_connect();

        $house = $db->table('companies')->where('slug', $cfg->houseCompanySlug)->where('status', 'active')->get()->getRowArray();
        if (! $house) {
            $stats['skipped'] = 'house_company_missing';

            return $stats;
        }
        if (! Services::components()->isEnabled('crm')) {
            $stats['skipped'] = 'crm_disabled';

            return $stats;
        }
        $houseId   = (int) $house['id'];
        $projectId = Rls::withCompany($houseId, fn () => $this->houseProject($houseId));

        $direct = $db->table('companies')->where('tenant_id', null)->where('id !=', $houseId)->where('deleted_at', null)->get()->getResultArray();
        foreach ($this->signalsFor($direct, $now) as $s) {
            if ($db->table('commercial_signals')->where('signal_key', $s['key'])->countAllResults()) {
                continue;
            }
            $c       = $s['company'];
            $ownerU  = $db->table('memberships m')->select('u.name, u.email')->join('users u', 'u.id = m.user_id')->join('roles r', 'r.id = m.role_id')
                ->where('m.company_id', $c['id'])->where('m.scope', 'company')->where('m.status', 'active')->where('r.code', 'business_owner')
                ->orderBy('m.id')->get(1)->getRowArray();
            $email   = $c['email'] ?: ($ownerU['email'] ?? null);
            if (! $email) {
                continue; // sin forma de contacto no se crea (el CRM exige correo o teléfono)
            }
            $lead = Rls::withCompany($houseId, static fn () => service('crmLeads')->capture($houseId, [
                'first_name' => $ownerU['name'] ?? $c['name'], 'organization' => $c['name'], 'email' => $email, 'phone' => $c['phone'],
            ], [
                'title'      => lang('Commercial.title.' . $s['kind'], [$c['name'], $s['detail']]),
                'amount'     => $s['amount'],
                'project_id' => $projectId,
            ], 'automation'));
            if (! $lead->ok) {
                log_message('warning', 'Señal comercial {key} no creada: {msg}', ['key' => $s['key'], 'msg' => $lead->message]);

                continue;
            }
            $db->table('commercial_signals')->insert([
                'signal_key' => $s['key'], 'kind' => $s['kind'], 'company_id' => $c['id'],
                'opportunity_id' => $lead->data['opportunity_id'], 'created_at' => date('Y-m-d H:i:s', $now),
            ]);
            Services::audit()->log('commercial.signal', 'company', (int) $c['id'], ['kind' => $s['kind'], 'opportunity_id' => $lead->data['opportunity_id']], $houseId);
            $stats[$s['kind']]++;
        }

        return $stats;
    }

    /**
     * Registra un prospecto en el CRM de Herramientas Virtuales (p. ej. autorregistro de prueba).
     */
    public function captureHouseLead(string $companyName, string $personName, string $email, ?string $phone, string $title): ?int
    {
        $db    = db_connect();
        $house = $db->table('companies')->where('slug', config(Commercial::class)->houseCompanySlug)->where('status', 'active')->get()->getRowArray();
        if (! $house || ! Services::components()->isEnabled('crm')) {
            return null;
        }
        $houseId = (int) $house['id'];
        // Todo lo que se escribe en el CRM de HV ocurre dentro del contexto de HV (RLS).
        $r = Rls::withCompany($houseId, fn () => service('crmLeads')->capture($houseId, [
            'first_name' => $personName, 'organization' => $companyName, 'email' => $email, 'phone' => $phone,
        ], ['title' => $title, 'project_id' => $this->houseProject($houseId)], 'automation'));

        return $r->ok ? $r->data['opportunity_id'] : null;
    }

    /** Proyecto del CRM de HV para estas oportunidades (lo crea la primera vez). */
    public function houseProject(int $houseId): ?int
    {
        $cfg = config(Commercial::class);
        $p   = db_connect()->table('cmp_crm_projects')->where('company_id', $houseId)->where('code', $cfg->houseProjectCode)->get()->getRowArray();
        if ($p) {
            return (int) $p['id'];
        }
        $r = service('crmSetup')->saveProject($houseId, ['name' => $cfg->houseProjectName, 'code' => $cfg->houseProjectCode]);

        return $r->ok ? (int) $r->data['id'] : null;
    }
}
