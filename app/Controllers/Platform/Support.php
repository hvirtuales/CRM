<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use Config\Services;

class Support extends BaseController
{
    public function index()
    {
        return view('platform/support', [
            'grants'    => Services::supportGrants()->forUser($this->actorId()),
            'companies' => db_connect()->table('companies')->select('id, name')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
        ]);
    }

    public function store()
    {
        $p = $this->request->getPost();

        return $this->afterResult(
            Services::supportGrants()->request($this->actorId(), (int) ($p['company_id'] ?? 0), (string) ($p['reason'] ?? ''), trim((string) ($p['ticket_ref'] ?? '')) ?: null, (int) ($p['hours'] ?? 2)),
            'plataforma/soporte',
            lang('Core.supportRequested')
        );
    }
}
