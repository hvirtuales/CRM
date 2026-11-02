<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\Result;
use Config\Services;

class Commissions extends BaseController
{
    public function index()
    {
        $b = db_connect()->table('commissions m')
            ->select('m.*, t.name AS tenant_name, c.name AS company_name, p.reference, p.paid_at AS payment_date')
            ->join('tenants t', 't.id = m.tenant_id')
            ->join('companies c', 'c.id = m.company_id', 'left')
            ->join('payments p', 'p.id = m.payment_id');
        $status = (string) $this->request->getGet('status');
        if (in_array($status, ['pending', 'approved', 'paid', 'void'], true)) {
            $b->where('m.status', $status);
        }
        $tenant = (int) $this->request->getGet('tenant');
        if ($tenant > 0) {
            $b->where('m.tenant_id', $tenant);
        }

        return view('platform/commissions', $this->paginate($b->orderBy('m.id', 'DESC')) + [
            'status' => $status, 'tenant' => $tenant,
            'tenants' => db_connect()->table('tenants')->select('id, name')->orderBy('name')->get()->getResultArray(),
        ]);
    }

    public function status(int $id)
    {
        $to  = (string) $this->request->getPost('status');
        $db  = db_connect();
        $row = $db->table('commissions')->where('id', $id)->get()->getRowArray();
        $allowed = ['pending' => ['approved', 'void'], 'approved' => ['paid', 'void'], 'paid' => [], 'void' => []];
        if (! $row) {
            return $this->afterResult(Result::notFound(), 'plataforma/comisiones');
        }
        if (! in_array($to, $allowed[$row['status']] ?? [], true)) {
            return $this->afterResult(Result::fail(lang('Core.transitionInvalid', [lang('Core.status.' . $row['status']), lang('Core.status.' . $to)])), 'plataforma/comisiones');
        }
        $db->table('commissions')->where('id', $id)->update(['status' => $to, 'paid_at' => $to === 'paid' ? date('Y-m-d H:i:s') : null, 'updated_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log('commission.' . $to, 'commission', $id, [], null, (int) $row['tenant_id']);

        return redirect()->to(site_url('plataforma/comisiones'))->with('message', lang('Core.saved'));
    }
}
