<?php

namespace App\Libraries;

use Config\Services;

/**
 * Liquidación mensual de comisiones de distribuidores.
 * - Las comisiones nacen «aprobadas» cuando el pago queda registrado (pago confirmado).
 * - El primer día de cada mes (o cuando corra la tarea diaria) se agrupan las aprobadas de meses
 *   cerrados en un extracto por distribuidor y moneda.
 * - La plataforma marca el extracto como pagado con su referencia; sus comisiones pasan a pagadas.
 */
class CommissionService
{
    /** @return array{settlements:int} */
    public function settleClosedMonths(?int $now = null): array
    {
        $now        = $now ?? time();
        $monthStart = date('Y-m-01 00:00:00', $now);
        $db         = db_connect();
        $created    = 0;

        $rows = $db->table('commissions')->where('status', 'approved')->where('settlement_id', null)
            ->where('created_at <', $monthStart)->orderBy('id')->get()->getResultArray();
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['tenant_id'] . '|' . substr((string) $r['created_at'], 0, 7) . '|' . $r['currency']][] = $r;
        }
        foreach ($groups as $key => $items) {
            [$tenantId, $period, $currency] = explode('|', $key);
            $cents = array_sum(array_map(static fn ($i) => (int) round((float) $i['amount'] * 100), $items));
            $db->transStart();
            $existing = $db->table('commission_settlements')->where('tenant_id', $tenantId)->where('period', $period)->where('currency', $currency)->get()->getRowArray();
            if ($existing && $existing['status'] === 'paid') {
                $db->transRollback();

                continue; // un extracto pagado no se modifica; se liquidarán en el siguiente
            }
            if ($existing) {
                $settlementId = (int) $existing['id'];
                $db->table('commission_settlements')->where('id', $settlementId)->update([
                    'total' => number_format(((int) round((float) $existing['total'] * 100) + $cents) / 100, 2, '.', ''),
                    'commissions_count' => (int) $existing['commissions_count'] + count($items),
                ]);
            } else {
                $db->table('commission_settlements')->insert([
                    'tenant_id' => $tenantId, 'period' => $period, 'currency' => $currency,
                    'total' => number_format($cents / 100, 2, '.', ''), 'commissions_count' => count($items),
                    'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', $now),
                ]);
                $settlementId = (int) $db->insertID();
                $created++;
            }
            $db->table('commissions')->whereIn('id', array_column($items, 'id'))->update(['settlement_id' => $settlementId]);
            $db->transComplete();
        }

        return ['settlements' => $created];
    }

    public function markSettlementPaid(int $id, string $reference, int $actorId): Result
    {
        $reference = trim($reference);
        if (! preg_match('/^[A-Za-z0-9._\-]{3,100}$/', $reference)) {
            return Result::fail(lang('Core.paymentInvalid'), ['reference' => lang('Core.paymentInvalid')]);
        }
        $db = db_connect();
        $s  = $db->table('commission_settlements')->where('id', $id)->get()->getRowArray();
        if (! $s) {
            return Result::notFound();
        }
        if ($s['status'] !== 'pending') {
            return Result::fail(lang('Core.transitionInvalid', [lang('Core.status.' . $s['status']), lang('Core.status.paid')]), [], 409);
        }
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('commission_settlements')->where('id', $id)->update(['status' => 'paid', 'reference' => $reference, 'paid_at' => $now, 'paid_by' => $actorId]);
        $db->table('commissions')->where('settlement_id', $id)->where('status', 'approved')->update(['status' => 'paid', 'paid_at' => $now, 'updated_at' => $now]);
        $db->transComplete();
        Services::audit()->log('commission.settlement_paid', 'commission_settlement', $id, ['reference' => $reference], null, (int) $s['tenant_id']);

        return Result::success(null, lang('Core.settlementPaid'));
    }

    /** @return list<array<string,mixed>> */
    public function settlements(?int $tenantId = null, int $limit = 100): array
    {
        $b = db_connect()->table('commission_settlements s')->select('s.*, t.name AS tenant_name')->join('tenants t', 't.id = s.tenant_id');
        if ($tenantId !== null) {
            $b->where('s.tenant_id', $tenantId);
        }

        return $b->orderBy('s.period', 'DESC')->orderBy('s.id', 'DESC')->get($limit)->getResultArray();
    }

    /** @return list<array<string,mixed>> comisiones de un extracto (acotado al distribuidor si se indica) */
    public function settlementLines(int $settlementId, ?int $tenantId = null): array
    {
        $b = db_connect()->table('commissions m')->select('m.*, c.name AS company_name, p.reference AS payment_reference, p.paid_at AS payment_date')
            ->join('companies c', 'c.id = m.company_id', 'left')->join('payments p', 'p.id = m.payment_id')
            ->where('m.settlement_id', $settlementId);
        if ($tenantId !== null) {
            $b->where('m.tenant_id', $tenantId);
        }

        return $b->orderBy('m.id')->get()->getResultArray();
    }
}
