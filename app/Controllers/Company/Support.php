<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use Config\Services;

class Support extends BaseController
{
    public function index()
    {
        return view('company/support', ['grants' => Services::supportGrants()->forCompany((int) Services::context()->companyId())]);
    }

    public function decide(int $id)
    {
        return $this->afterResult(
            Services::supportGrants()->decide($id, (int) Services::context()->companyId(), (string) $this->request->getPost('decision'), $this->actorId()),
            'negocio/soporte'
        );
    }
}
