<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use App\Libraries\Result;
use Config\Services;

/**
 * Negocios de la cartera. El tenant_id SIEMPRE sale del contexto; nunca del formulario (US-002).
 */
class Companies extends BaseController
{
    public function index()
    {
        $tid = (int) Services::context()->tenantId();
        $db  = db_connect();
        $q   = trim((string) $this->request->getGet('q'));
        $b   = $db->table('companies c')
            ->select('c.id, c.name, c.status, c.tax_id, c.email, c.created_at, p.name AS plan_name, s.status AS sub_status, s.current_period_end')
            ->join('plans p', 'p.id = c.plan_id', 'left')
            ->join('subscriptions s', "s.company_id = c.id AND s.status IN ('trial','active','past_due','suspended')", 'left', false)
            ->where('c.tenant_id', $tid)->where('c.deleted_at', null);
        if ($q !== '') {
            $b->groupStart()->like('c.name', $q)->orLike('c.tax_id', $q)->groupEnd();
        }
        $editId = (int) $this->request->getGet('edit');

        return view('tenant/companies', $this->paginate($b->orderBy('c.name')) + [
            'q'     => $q,
            'plans' => $db->table('plans')->where('target', 'company')->where('is_active', 1)->where('is_public', 1)->orderBy('price')->get()->getResultArray(),
            'edit'  => $editId ? $db->table('companies')->where('id', $editId)->where('tenant_id', $tid)->get()->getRowArray() : null,
        ]);
    }

    public function store()
    {
        $data = $this->request->getPost();
        unset($data['tenant_id']); // defensa explícita: el ámbito lo decide el servidor
        $r = Services::provisioning()->createCompany($data, (int) Services::context()->tenantId(), $this->actorId());
        \App\Libraries\InvitationNotice::flash();

        return $this->afterResult($r, 'distribuidor/negocios', lang('Core.companyCreated'));
    }

    public function plan(int $id)
    {
        $tid     = (int) Services::context()->tenantId();
        $company = db_connect()->table('companies')->where('id', $id)->where('tenant_id', $tid)->get()->getRowArray();
        if (! $company) {
            // 404 y no 403: no confirmar la existencia de negocios de otros distribuidores.
            return $this->afterResult(Result::notFound(), 'distribuidor/negocios');
        }
        $planId = (int) $this->request->getPost('plan_id');
        if (! Services::provisioning()->planSellable($planId, true)) {
            return $this->afterResult(Result::fail(lang('Core.planInvalid')), 'distribuidor/negocios');
        }

        return $this->afterResult(Services::subscriptions()->assignPlan($id, $planId, $tid, $this->actorId(), true), 'distribuidor/negocios', lang('Core.planAssigned'));
    }
}
