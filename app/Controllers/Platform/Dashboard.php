<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $mrr = $db->table('subscriptions')->select('currency, billing_period, SUM(price) AS total')
            ->whereIn('status', ['active', 'past_due'])->groupBy(['currency', 'billing_period'])->get()->getResultArray();
        $mrrByCurrency = [];
        foreach ($mrr as $r) {
            $monthly = $r['billing_period'] === 'yearly' ? (float) $r['total'] / 12 : (float) $r['total'];
            $mrrByCurrency[$r['currency']] = ($mrrByCurrency[$r['currency']] ?? 0) + $monthly;
        }

        return view('platform/dashboard', [
            'emailConfigured' => \App\Libraries\NotificationService::emailConfigured(),
            'queuedMails'     => $db->table('outbox')->where('status', 'queued')->countAllResults(),
            'failedMails'     => $db->table('outbox')->where('status', 'failed')->countAllResults(),
            'tenants'        => $db->table('tenants')->where('status', 'active')->where('deleted_at', null)->countAllResults(),
            'companies'      => $db->table('companies')->where('status', 'active')->where('deleted_at', null)->countAllResults(),
            'direct'         => $db->table('companies')->where('tenant_id', null)->where('status', 'active')->countAllResults(),
            'mrr'            => $mrrByCurrency,
            'statusCounts'   => array_column($db->table('subscriptions')->select('status, COUNT(*) AS n')->groupBy('status')->get()->getResultArray(), 'n', 'status'),
            'pendingComm'    => $db->table('commissions')->select('currency, SUM(amount) AS total')->where('status', 'pending')->groupBy('currency')->get()->getResultArray(),
            'renewals'       => $db->table('subscriptions s')->select('s.id, s.current_period_end, s.status, c.name AS company_name, p.name AS plan_name')
                ->join('companies c', 'c.id = s.company_id')->join('plans p', 'p.id = s.plan_id')
                ->whereIn('s.status', ['trial', 'active', 'past_due'])
                ->where('s.current_period_end <=', date('Y-m-d H:i:s', strtotime('+15 days')))
                ->orderBy('s.current_period_end', 'ASC')->get(8)->getResultArray(),
            'recentTenants'  => $db->table('tenants')->where('deleted_at', null)->orderBy('id', 'DESC')->get(5)->getResultArray(),
        ]);
    }
}
