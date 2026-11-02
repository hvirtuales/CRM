<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use App\Libraries\AuditQuery;
use Config\Services;

class Audit extends BaseController
{
    public function index()
    {
        $b = AuditQuery::builder($this->request->getGet(), null, (int) Services::context()->companyId());

        return view('shared/audit', $this->paginate($b) + ['filters' => AuditQuery::filters($this->request->getGet()), 'base' => 'negocio/auditoria']);
    }
}
