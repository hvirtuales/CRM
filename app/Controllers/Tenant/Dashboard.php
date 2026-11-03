<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use Config\Services;

/**
 * Panel de cartera: solo metadatos comerciales de SUS negocios (sin datos de clientes finales).
 */
class Dashboard extends BaseController
{
    public function index()
    {
        $tid = (int) Services::context()->tenantId();
        $db  = db_connect();

        $mrr = [];
        foreach ($db->table('subscriptions')->select('currency, billing_period, SUM(price) AS total')
            ->where('sold_by_tenant_id', $tid)->whereIn('status', ['active', 'past_due'])
            ->groupBy(['currency', 'billing_period'])->get()->getResultArray() as $r) {
            $mrr[$r['currency']] = ($mrr[$r['currency']] ?? 0) + ($r['billing_period'] === 'yearly' ? (float) $r['total'] / 12 : (float) $r['total']);
        }

        $portfolio = $db->table('companies')->where('tenant_id', $tid)->where('deleted_at', null)->get(300)->getResultArray();
        $signals   = (new \App\Libraries\CommercialAutomation())->signalsFor($portfolio);

        return view('tenant/dashboard', [
            'signals'     => $signals,
            'companies'   => $db->table('companies')->where('tenant_id', $tid)->where('status', 'active')->where('deleted_at', null)->countAllResults(),
            'trials'      => $db->table('subscriptions')->where('sold_by_tenant_id', $tid)->where('status', 'trial')->countAllResults(),
            'pastDue'     => $db->table('subscriptions')->where('sold_by_tenant_id', $tid)->where('status', 'past_due')->countAllResults(),
            'mrr'         => $mrr,
            'commissions' => $db->table('commissions')->select('status, currency, SUM(amount) AS total')->where('tenant_id', $tid)
                ->whereIn('status', ['pending', 'approved'])->groupBy(['status', 'currency'])->get()->getResultArray(),
            'renewals'    => $db->table('subscriptions s')->select('s.id, s.status, s.current_period_end, c.name AS company_name, p.name AS plan_name')
                ->join('companies c', 'c.id = s.company_id')->join('plans p', 'p.id = s.plan_id')
                ->where('s.sold_by_tenant_id', $tid)->whereIn('s.status', ['trial', 'active', 'past_due'])
                ->where('s.current_period_end <=', date('Y-m-d H:i:s', strtotime('+15 days')))
                ->orderBy('s.current_period_end')->get(8)->getResultArray(),
        ]);
    }
}
