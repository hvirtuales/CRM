<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use Config\Services;

class Subscriptions extends BaseController
{
    public function index()
    {
        $tid = (int) Services::context()->tenantId();
        $b   = db_connect()->table('subscriptions s')
            ->select('s.*, c.name AS company_name, p.name AS plan_name')
            ->join('companies c', 'c.id = s.company_id')->join('plans p', 'p.id = s.plan_id')
            ->where('c.tenant_id', $tid);
        $status = (string) $this->request->getGet('status');
        if (in_array($status, ['trial', 'active', 'past_due', 'suspended', 'cancelled'], true)) {
            $b->where('s.status', $status);
        }

        return view('tenant/subscriptions', $this->paginate($b->orderBy('s.current_period_end', 'ASC')) + ['status' => $status]);
    }
}
