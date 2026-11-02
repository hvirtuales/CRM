<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use Config\Services;

class Companies extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $q  = trim((string) $this->request->getGet('q'));
        $b  = $db->table('companies c')
            ->select('c.id, c.name, c.slug, c.status, c.tax_id, c.created_at, c.tenant_id, t.name AS tenant_name, p.name AS plan_name')
            ->join('tenants t', 't.id = c.tenant_id', 'left')
            ->join('plans p', 'p.id = c.plan_id', 'left')
            ->where('c.deleted_at', null);
        if ($q !== '') {
            $b->groupStart()->like('c.name', $q)->orLike('c.tax_id', $q)->groupEnd();
        }
        $tenant = (string) $this->request->getGet('tenant');
        if ($tenant === 'direct') {
            $b->where('c.tenant_id', null);
        } elseif (ctype_digit($tenant)) {
            $b->where('c.tenant_id', (int) $tenant);
        }

        $editId = (int) $this->request->getGet('edit');

        return view('platform/companies', $this->paginate($b->orderBy('c.name')) + [
            'q' => $q, 'tenant' => $tenant,
            'tenants' => $db->table('tenants')->select('id, name')->where('status', 'active')->orderBy('name')->get()->getResultArray(),
            'plans'   => $db->table('plans')->where('target', 'company')->where('is_active', 1)->orderBy('price')->get()->getResultArray(),
            'edit'    => $editId ? $db->table('companies')->where('id', $editId)->get()->getRowArray() : null,
        ]);
    }

    /** Negocio directo (sin distribuidor). */
    public function store()
    {
        $result = Services::provisioning()->createCompany($this->request->getPost(), null, $this->actorId());
        if ($result->ok && $result->data['temp_password']) {
            session()->setFlashdata('temp_password', $result->data['temp_password']);
        }

        return $this->afterResult($result, 'plataforma/negocios', lang('Core.companyCreated'));
    }

    public function status(int $id)
    {
        return $this->afterResult(Services::provisioning()->setStatus('companies', $id, (string) $this->request->getPost('status')), 'plataforma/negocios');
    }

    public function transfer(int $id)
    {
        $to = (string) $this->request->getPost('tenant_id');

        return $this->afterResult(
            Services::provisioning()->transferCompany($id, ctype_digit($to) && (int) $to > 0 ? (int) $to : null, (string) $this->request->getPost('reason'), $this->actorId()),
            'plataforma/negocios',
            lang('Core.companyTransferred'),
            'plataforma/negocios?edit=' . $id
        );
    }

    public function plan(int $id)
    {
        $company = db_connect()->table('companies')->where('id', $id)->get()->getRowArray();
        if (! $company) {
            return redirect()->to(site_url('plataforma/negocios'))->with('error', lang('Core.notFound'));
        }

        return $this->afterResult(
            Services::subscriptions()->assignPlan($id, (int) $this->request->getPost('plan_id'), $company['tenant_id'] ? (int) $company['tenant_id'] : null, $this->actorId()),
            'plataforma/negocios',
            lang('Core.planAssigned')
        );
    }
}
