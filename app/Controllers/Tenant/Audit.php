<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use App\Libraries\AuditQuery;
use Config\Services;

class Audit extends BaseController
{
    public function index()
    {
        // Solo acciones ejecutadas en el ámbito del distribuidor (no la actividad interna de sus negocios).
        $b = AuditQuery::builder($this->request->getGet(), (int) Services::context()->tenantId())->where('a.scope', 'tenant');

        return view('shared/audit', $this->paginate($b) + ['filters' => AuditQuery::filters($this->request->getGet()), 'base' => 'distribuidor/auditoria']);
    }
}
