<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\SubscriptionService;
use Config\Services;

class Subscriptions extends BaseController
{
    public function index()
    {
        $b = db_connect()->table('subscriptions s')
            ->select('s.*, c.name AS company_name, p.name AS plan_name, t.name AS tenant_name')
            ->join('companies c', 'c.id = s.company_id', 'left')
            ->join('plans p', 'p.id = s.plan_id')
            ->join('tenants t', 't.id = s.sold_by_tenant_id', 'left');
        $status = (string) $this->request->getGet('status');
        if (array_key_exists($status, SubscriptionService::TRANSITIONS)) {
            $b->where('s.status', $status);
        } else {
            $b->whereIn('s.status', SubscriptionService::OPEN_STATUSES);
        }
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $b->like('c.name', $q);
        }
        $editId = (int) $this->request->getGet('edit');

        return view('platform/subscriptions', $this->paginate($b->orderBy('s.current_period_end', 'ASC')) + [
            'status' => $status, 'q' => $q,
            'edit' => $editId ? db_connect()->table('subscriptions s')->select('s.*, c.name AS company_name, p.name AS plan_name')
                ->join('companies c', 'c.id = s.company_id', 'left')->join('plans p', 'p.id = s.plan_id')->where('s.id', $editId)->get()->getRowArray() : null,
            'payments' => $editId ? db_connect()->table('payments')->where('subscription_id', $editId)->orderBy('id', 'DESC')->get(20)->getResultArray() : [],
        ]);
    }

    public function transition(int $id)
    {
        return $this->afterResult(
            Services::subscriptions()->transition($id, (string) $this->request->getPost('status'), $this->actorId()),
            'plataforma/suscripciones?edit=' . $id
        );
    }

    public function payment(int $id)
    {
        $p = $this->request->getPost();

        return $this->afterResult(
            Services::subscriptions()->registerPayment($id, (string) ($p['amount'] ?? ''), (string) ($p['method'] ?? 'manual'), trim((string) ($p['reference'] ?? '')), $this->actorId()),
            'plataforma/suscripciones?edit=' . $id,
            lang('Core.paymentRecorded')
        );
    }
}
