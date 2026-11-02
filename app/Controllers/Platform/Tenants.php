<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\Result;
use Config\Services;

class Tenants extends BaseController
{
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        $b = db_connect()->table('tenants t')
            ->select('t.*, (SELECT COUNT(*) FROM companies c WHERE c.tenant_id = t.id AND c.deleted_at IS NULL) AS companies_count', false)
            ->where('t.deleted_at', null);
        if ($q !== '') {
            $b->groupStart()->like('t.name', $q)->orLike('t.slug', $q)->orLike('t.tax_id', $q)->groupEnd();
        }
        $status = (string) $this->request->getGet('status');
        if (in_array($status, ['active', 'suspended', 'archived'], true)) {
            $b->where('t.status', $status);
        }

        $editId = (int) $this->request->getGet('edit');

        return view('platform/tenants', $this->paginate($b->orderBy('t.name')) + [
            'q' => $q, 'status' => $status,
            'edit' => $editId ? db_connect()->table('tenants')->where('id', $editId)->get()->getRowArray() : null,
        ]);
    }

    public function store()
    {
        $result = Services::provisioning()->createTenant($this->request->getPost(), $this->actorId());
        \App\Libraries\InvitationNotice::flash();

        return $this->afterResult($result, 'plataforma/distribuidores', lang('Core.tenantCreated'));
    }

    public function update(int $id)
    {
        $db = db_connect();
        if (! $db->table('tenants')->where('id', $id)->countAllResults()) {
            return $this->afterResult(Result::notFound(), 'plataforma/distribuidores');
        }
        $name = trim((string) $this->request->getPost('name'));
        $rate = (string) $this->request->getPost('commission_rate');
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = lang('Core.validation.name');
        }
        if (! preg_match('/^(0(\.\d{1,4})?|1(\.0{1,4})?)$/', $rate)) {
            $errors['commission_rate'] = lang('Core.validation.rate');
        }
        $email = strtolower(trim((string) $this->request->getPost('contact_email')));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = lang('Core.validation.email');
        }
        if ($errors) {
            return $this->afterResult(Result::fail(lang('Core.validation.fix'), $errors), '', null, 'plataforma/distribuidores?edit=' . $id);
        }
        $db->table('tenants')->where('id', $id)->update([
            'name' => $name, 'commission_rate' => $rate, 'contact_email' => $email ?: null,
            'tax_id' => trim((string) $this->request->getPost('tax_id')) ?: null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Services::audit()->log('tenant.updated', 'tenant', $id, ['commission_rate' => $rate], null, $id);

        return redirect()->to(site_url('plataforma/distribuidores'))->with('message', lang('Core.saved'));
    }

    public function status(int $id)
    {
        return $this->afterResult(
            Services::provisioning()->setStatus('tenants', $id, (string) $this->request->getPost('status')),
            'plataforma/distribuidores'
        );
    }
}
