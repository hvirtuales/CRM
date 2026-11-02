<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use App\Libraries\DashboardWidgets;
use CodeIgniter\Events\Events;
use Config\Services;

class Dashboard extends BaseController
{
    public function index()
    {
        $cid     = (int) Services::context()->companyId();
        $widgets = new DashboardWidgets($cid);
        // Los componentes aportan KPIs y tarjetas escuchando 'dashboard.render'.
        Events::trigger('dashboard.render', $widgets);

        return view('company/dashboard', [
            'kpis'       => $widgets->kpis(),
            'cards'      => $widgets->cards(),
            'planStatus' => Services::components()->planStatus($cid),
            'plan'       => Services::plans()->planForCompany($cid),
            'sub'        => Services::subscriptions()->currentForCompany($cid),
        ]);
    }
}
