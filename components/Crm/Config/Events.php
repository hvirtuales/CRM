<?php

use CodeIgniter\Events\Events;

/**
 * Aporta KPIs y la tarjeta de próximas acciones al panel del negocio.
 */
Events::on('dashboard.render', static function ($widgets): void {
    if (! function_exists('auth_can') || ! auth_can('crm.view')) {
        return;
    }
    $cid = (int) $widgets->companyId;
    $db  = db_connect();

    $open = $db->table('cmp_crm_opportunities o')->select('COUNT(*) AS n, SUM(o.amount * o.probability / 100) AS weighted, SUM(o.amount) AS total')
        ->join('cmp_crm_stages s', 's.id = o.stage_id')->where('o.company_id', $cid)->where('o.deleted_at', null)->where('s.kind', 'open')
        ->get()->getRowArray();
    $wonMonth = $db->table('cmp_crm_opportunities o')->select('COUNT(*) AS n, SUM(o.amount) AS total')
        ->join('cmp_crm_stages s', 's.id = o.stage_id')->where('o.company_id', $cid)->where('o.deleted_at', null)->where('s.kind', 'won')
        ->where('o.closed_at >=', gmdate('Y-m-01 00:00:00'))->get()->getRowArray();
    $currency = (string) ($db->table('companies')->select('currency')->where('id', $cid)->get()->getRow()->currency ?? 'COP');
    $contacts = $db->table('cmp_crm_contacts')->where('company_id', $cid)->where('deleted_at', null)->where('status', 'active')->countAllResults();

    $widgets->addKpi(lang('Crm.kpiOpen'), num($open['n'] ?? 0), lang('Crm.kpiOpenHint', [money($open['total'] ?? 0, $currency)]), null, 10);
    $widgets->addKpi(lang('Crm.kpiWeighted'), money($open['weighted'] ?? 0, $currency), lang('Crm.kpiWeightedHint'), null, 20);
    $widgets->addKpi(lang('Crm.kpiWonMonth'), money($wonMonth['total'] ?? 0, $currency), lang('Crm.kpiWonMonthHint', [num($wonMonth['n'] ?? 0)]), 'ok', 30);
    $widgets->addKpi(lang('Crm.kpiContacts'), num($contacts), null, null, 40);

    $next = $db->table('cmp_crm_opportunities o')->select('o.id, o.title, o.next_action, o.next_action_at, c.first_name, c.last_name')
        ->join('cmp_crm_contacts c', 'c.id = o.contact_id')->join('cmp_crm_stages s', 's.id = o.stage_id')
        ->where('o.company_id', $cid)->where('o.deleted_at', null)->where('s.kind', 'open')->where('o.next_action_at IS NOT NULL', null, false)
        ->where('o.owner_user_id', ctx()->userId())
        ->orderBy('o.next_action_at', 'ASC')->get(6)->getResultArray();
    $widgets->addCard(view('Components\Crm\Views\widgets\next_actions', ['rows' => $next]), 10, true);
});
