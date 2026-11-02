<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\AuditQuery;

class Audit extends BaseController
{
    public function index()
    {
        $b = AuditQuery::builder($this->request->getGet());

        return view('shared/audit', $this->paginate($b) + ['filters' => AuditQuery::filters($this->request->getGet()), 'base' => 'plataforma/auditoria']);
    }
}
