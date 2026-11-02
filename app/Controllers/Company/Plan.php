<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use Config\Services;

class Plan extends BaseController
{
    public function index()
    {
        $cid = (int) Services::context()->companyId();
        $sub = Services::subscriptions()->currentForCompany($cid);

        return view('company/plan', [
            'plan'       => Services::plans()->planForCompany($cid),
            'sub'        => $sub,
            'planStatus' => Services::components()->planStatus($cid),
            'users'      => Services::plans()->usersRemaining($cid),
            'payments'   => $sub ? db_connect()->table('payments p')->select('p.*')->join('subscriptions s', 's.id = p.subscription_id')
                ->where('s.company_id', $cid)->orderBy('p.id', 'DESC')->get(12)->getResultArray() : [],
        ]);
    }
}
