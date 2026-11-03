<?php

namespace App\Libraries;

use Config\Commercial;

/**
 * Cobranza escalonada (tarea diaria, después del ciclo de suscripciones). Etapas:
 *  - renewal:  suscripción activa a ≤ 7 días del corte (recordatorio de renovación).
 *  - past_due: el periodo venció sin pago (inicia el periodo de gracia).
 *  - final:    queda ≤ 1 día de gracia antes de la suspensión.
 *  - suspended: servicio suspendido (solo lectura) hasta regularizar.
 * Destinatarios: administradores del negocio y, si lo vendió un distribuidor, también el distribuidor.
 * Cada etapa se envía una sola vez por periodo y destinatario (dedupe_key). No incluye datos de pago
 * sensibles: remite al detalle del plan dentro de la plataforma.
 */
class DunningService
{
    public const RENEWAL_DAYS = 7;

    /** @return array{renewal:int, past_due:int, final:int, suspended:int} */
    public function run(?int $now = null): array
    {
        $now   = $now ?? time();
        $stats = ['renewal' => 0, 'past_due' => 0, 'final' => 0, 'suspended' => 0];
        $db    = db_connect();
        $house = config(Commercial::class)->houseCompanySlug;

        $subs = $db->table('subscriptions s')
            ->select('s.*, c.name AS company_name, c.locale, c.tenant_id AS company_tenant_id, c.slug, p.name AS plan_name')
            ->join('companies c', 'c.id = s.company_id')->join('plans p', 'p.id = s.plan_id')
            ->where('s.subscriber_type', 'company')->whereIn('s.status', ['active', 'past_due', 'suspended'])
            ->where('c.status !=', 'archived')->where('c.slug !=', $house)
            ->get()->getResultArray();

        foreach ($subs as $s) {
            $end   = strtotime((string) $s['current_period_end']);
            $grace = $end + (int) $s['grace_days'] * 86400;
            $stage = match (true) {
                $s['status'] === 'active' && $end > $now && $end <= $now + self::RENEWAL_DAYS * 86400 => 'renewal',
                $s['status'] === 'past_due' && $grace - $now <= 86400                                  => 'final',
                $s['status'] === 'past_due'                                                              => 'past_due',
                $s['status'] === 'suspended'                                                             => 'suspended',
                default                                                                                  => null,
            };
            if ($stage === null) {
                continue;
            }
            if ($this->notify($s, $stage, $end, $grace) > 0) {
                $stats[$stage]++;
            }
        }

        return $stats;
    }

    /** Envía la etapa al negocio y a su distribuidor. Devuelve cuántos correos se encolaron. */
    private function notify(array $s, string $stage, int $end, int $grace): int
    {
        $n     = new NotificationService();
        $sent  = 0;
        $args  = [$s['company_name'], $s['plan_name'], money($s['price'], $s['currency']), date('Y-m-d', $end), date('Y-m-d', $grace)];
        $base  = 'dunning:' . $stage . ':' . $s['id'] . ':' . date('Y-m-d', $end) . ':';

        foreach ($n->companyRecipients((int) $s['company_id']) as $email) {
            $sent += $n->queue($base . 'c:' . sha1($email), 'dunning_' . $stage, $email, (string) $s['locale'],
                'Notify.dunning.' . $stage . '.subject', [$s['company_name']], 'emails/message',
                static fn () => [
                    'greeting'  => lang('Notify.dunning.greeting', [$s['company_name']]),
                    'lines'     => [lang('Notify.dunning.' . $stage . '.body', $args), lang('Notify.dunning.howToPay')],
                    'cta_label' => lang('Notify.dunning.ctaCompany'),
                    'cta_url'   => site_url('negocio/plan'),
                    'note'      => lang('Notify.dunning.note'),
                ], (int) $s['company_id']) !== null ? 1 : 0;
        }

        $tenantId = $s['sold_by_tenant_id'] ?: $s['company_tenant_id'];
        if ($tenantId) {
            foreach ($n->tenantRecipients((int) $tenantId) as $email) {
                $sent += $n->queue($base . 't:' . sha1($email), 'dunning_' . $stage, $email, 'es',
                    'Notify.dunning.tenant.subject', [$s['company_name'], lang('Notify.dunning.stage.' . $stage)], 'emails/message',
                    static fn () => [
                        'greeting'  => lang('Notify.dunning.tenant.greeting'),
                        'lines'     => [lang('Notify.dunning.' . $stage . '.body', $args), lang('Notify.dunning.tenant.action')],
                        'cta_label' => lang('Notify.dunning.ctaTenant'),
                        'cta_url'   => site_url('distribuidor/suscripciones'),
                    ], null, (int) $tenantId) !== null ? 1 : 0;
            }
        }

        return $sent;
    }
}
