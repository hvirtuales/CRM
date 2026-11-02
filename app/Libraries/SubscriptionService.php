<?php

namespace App\Libraries;

use Config\Services;

/**
 * Ciclo de vida de suscripciones: trial → active → past_due → suspended → cancelled → archived.
 * Única vía de escritura de `companies.plan_id`.
 */
class SubscriptionService
{
    /** Transiciones permitidas. */
    public const TRANSITIONS = [
        'trial'     => ['active', 'past_due', 'cancelled'],
        'active'    => ['past_due', 'suspended', 'cancelled'],
        'past_due'  => ['active', 'suspended', 'cancelled'],
        'suspended' => ['active', 'cancelled'],
        'cancelled' => ['archived', 'active'],
        'archived'  => [],
    ];

    public const OPEN_STATUSES = ['trial', 'active', 'past_due', 'suspended'];

    public function currentForCompany(int $companyId): ?array
    {
        return db_connect()->table('subscriptions')
            ->where('company_id', $companyId)
            ->where('subscriber_type', 'company')
            ->whereIn('status', array_merge(self::OPEN_STATUSES, ['cancelled']))
            ->orderBy('id', 'DESC')
            ->get(1)->getRowArray() ?: null;
    }

    /**
     * Asigna (o cambia) el plan de un negocio. Cierra la suscripción abierta anterior.
     *
     * @param int|null $soldByTenantId distribuidor que comercializa (para comisiones)
     */
    public function assignPlan(int $companyId, int $planId, ?int $soldByTenantId, ?int $actorId): Result
    {
        $db   = db_connect();
        $plan = $db->table('plans')->where('id', $planId)->where('is_active', 1)->where('target', 'company')->get()->getRowArray();
        if (! $plan) {
            return Result::fail(lang('Core.planInvalid'));
        }

        $now   = time();
        $trial = (int) $plan['trial_days'] > 0;
        $db->transStart();

        $current = $this->currentForCompany($companyId);
        if ($current && in_array($current['status'], self::OPEN_STATUSES, true)) {
            $db->table('subscriptions')->where('id', $current['id'])->update([
                'status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s', $now), 'updated_at' => date('Y-m-d H:i:s', $now),
            ]);
        }

        $periodEnd = $trial
            ? strtotime('+' . (int) $plan['trial_days'] . ' days', $now)
            : strtotime($plan['billing_period'] === 'yearly' ? '+1 year' : '+1 month', $now);

        $db->table('subscriptions')->insert([
            'subscriber_type'    => 'company',
            'company_id'         => $companyId,
            'sold_by_tenant_id'  => $soldByTenantId,
            'plan_id'            => $planId,
            'status'             => $trial ? 'trial' : 'active',
            'price'              => $plan['price'],
            'currency'           => $plan['currency'],
            'billing_period'     => $plan['billing_period'],
            'started_at'         => date('Y-m-d H:i:s', $now),
            'trial_ends_at'      => $trial ? date('Y-m-d H:i:s', $periodEnd) : null,
            'current_period_end' => date('Y-m-d H:i:s', $periodEnd),
            'created_by'         => $actorId,
            'created_at'         => date('Y-m-d H:i:s', $now),
            'updated_at'         => date('Y-m-d H:i:s', $now),
        ]);
        $subId = (int) $db->insertID();
        $db->table('companies')->where('id', $companyId)->update(['plan_id' => $planId, 'updated_at' => date('Y-m-d H:i:s', $now)]);
        $db->transComplete();

        Services::plans()->flush();
        Services::audit()->log('subscription.assigned', 'subscription', $subId, ['company_id' => $companyId, 'plan' => $plan['code']], $companyId);

        return Result::success(['id' => $subId]);
    }

    public function transition(int $subscriptionId, string $to, ?int $actorId): Result
    {
        $db  = db_connect();
        $sub = $db->table('subscriptions')->where('id', $subscriptionId)->get()->getRowArray();
        if (! $sub) {
            return Result::notFound();
        }
        if (! in_array($to, self::TRANSITIONS[$sub['status']] ?? [], true)) {
            return Result::fail(lang('Core.transitionInvalid', [lang('Core.status.' . $sub['status']), lang('Core.status.' . $to)]), [], 409);
        }

        $data = ['status' => $to, 'updated_at' => date('Y-m-d H:i:s')];
        if ($to === 'cancelled') {
            $data['cancelled_at'] = date('Y-m-d H:i:s');
        }
        $db->table('subscriptions')->where('id', $subscriptionId)->update($data);
        Services::audit()->log('subscription.' . $to, 'subscription', $subscriptionId, ['from' => $sub['status'], 'by' => $actorId], $sub['company_id'] ? (int) $sub['company_id'] : null);

        return Result::success();
    }

    /**
     * Registra un pago aprobado. Idempotente por (method, reference): un reintento no duplica
     * pago ni comisión. Extiende el periodo y reactiva la suscripción si estaba vencida.
     */
    public function registerPayment(int $subscriptionId, string $amount, string $method, string $reference, ?int $actorId): Result
    {
        $db  = db_connect();
        $sub = $db->table('subscriptions')->where('id', $subscriptionId)->get()->getRowArray();
        if (! $sub || ! in_array($sub['status'], self::OPEN_STATUSES, true)) {
            return Result::fail(lang('Core.paymentNotAllowed'));
        }
        if (! in_array($method, ['manual', 'transfer', 'wompi', 'paypal'], true)
            || ! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $amount) || (float) $amount <= 0
            || ! preg_match('/^[A-Za-z0-9._\-]{3,100}$/', $reference)) {
            return Result::fail(lang('Core.paymentInvalid'));
        }

        $dup = $db->table('payments')->where('method', $method)->where('reference', $reference)->get()->getRowArray();
        if ($dup) {
            return Result::success(['id' => (int) $dup['id'], 'duplicate' => true], lang('Core.paymentDuplicate'));
        }

        $start = max(time(), strtotime((string) $sub['current_period_end']) ?: time());
        $end   = strtotime($sub['billing_period'] === 'yearly' ? '+1 year' : '+1 month', $start);
        $now   = date('Y-m-d H:i:s');

        $db->transStart();
        $db->table('payments')->insert([
            'subscription_id' => $subscriptionId, 'amount' => $amount, 'currency' => $sub['currency'],
            'method' => $method, 'reference' => $reference, 'status' => 'approved',
            'period_start' => date('Y-m-d H:i:s', $start), 'period_end' => date('Y-m-d H:i:s', $end),
            'paid_at' => $now, 'recorded_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $paymentId = (int) $db->insertID();
        $db->table('subscriptions')->where('id', $subscriptionId)->update([
            'status' => 'active', 'current_period_end' => date('Y-m-d H:i:s', $end), 'updated_at' => $now,
        ]);

        // Comisión del distribuidor que vendió (si existe): base = monto pagado, tasa vigente del tenant.
        if ($sub['sold_by_tenant_id']) {
            $tenant = $db->table('tenants')->where('id', $sub['sold_by_tenant_id'])->get()->getRowArray();
            $rate   = $tenant ? (float) $tenant['commission_rate'] : 0.0;
            if ($rate > 0) {
                $db->table('commissions')->insert([
                    'tenant_id' => $sub['sold_by_tenant_id'], 'company_id' => $sub['company_id'], 'payment_id' => $paymentId,
                    'base_amount' => $amount, 'rate' => number_format($rate, 4, '.', ''),
                    'amount' => number_format(round((float) $amount * $rate, 2), 2, '.', ''),
                    'currency' => $sub['currency'], 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return Result::fail(lang('Core.unexpected'), [], 500);
        }

        Services::audit()->log('payment.recorded', 'payment', $paymentId, ['subscription_id' => $subscriptionId, 'amount' => $amount, 'method' => $method], $sub['company_id'] ? (int) $sub['company_id'] : null);

        return Result::success(['id' => $paymentId]);
    }

    /**
     * Proceso diario (cron): vence pruebas y periodos impagos, aplica periodo de gracia y suspensión.
     *
     * @return array<string, int>
     */
    public function runLifecycle(?int $now = null): array
    {
        $now   = $now ?? time();
        $db    = db_connect();
        $stats = ['past_due' => 0, 'suspended' => 0];

        $due = $db->table('subscriptions')->whereIn('status', ['trial', 'active'])
            ->where('current_period_end <', date('Y-m-d H:i:s', $now))->get()->getResultArray();
        foreach ($due as $s) {
            $this->transition((int) $s['id'], 'past_due', null)->ok && $stats['past_due']++;
        }

        $late = $db->table('subscriptions')->where('status', 'past_due')->get()->getResultArray();
        foreach ($late as $s) {
            $limit = strtotime('+' . (int) $s['grace_days'] . ' days', strtotime((string) $s['current_period_end']));
            if ($limit < $now) {
                $this->transition((int) $s['id'], 'suspended', null)->ok && $stats['suspended']++;
            }
        }

        return $stats;
    }
}
