<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use Config\Services;

class Commissions extends BaseController
{
    public function index()
    {
        $tid = (int) Services::context()->tenantId();
        $b   = db_connect()->table('commissions m')
            ->select('m.*, c.name AS company_name, p.paid_at AS payment_date')
            ->join('companies c', 'c.id = m.company_id', 'left')->join('payments p', 'p.id = m.payment_id')
            ->where('m.tenant_id', $tid);
        $status = (string) $this->request->getGet('status');
        if (in_array($status, ['pending', 'approved', 'paid', 'void'], true)) {
            $b->where('m.status', $status);
        }

        return view('tenant/commissions', $this->paginate($b->orderBy('m.id', 'DESC')) + ['status' => $status]);
    }
}
