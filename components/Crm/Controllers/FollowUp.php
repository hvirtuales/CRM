<?php

namespace Components\Crm\Controllers;

use App\Controllers\BaseController;

/** Bandeja de seguimiento: acciones vencidas, oportunidades sin actividad y escaladas al gerente. */
class FollowUp extends BaseController
{
    public function index()
    {
        $cid = (int) session('active_company_id');

        return view('Components\Crm\Views\followup\index', service('crmFollowUp')->alerts($cid, $this->actorId(), auth_can('crm.manage')));
    }
}
