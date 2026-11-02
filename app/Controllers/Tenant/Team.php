<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use Config\Services;

class Team extends BaseController
{
    public function index()
    {
        return view('shared/team', [
            'title'   => lang('Nav.team'),
            'members' => Services::users()->members('tenant', (int) Services::context()->tenantId(), null),
            'roles'   => Services::rbac()->assignableRoles('tenant'),
            'base'    => 'distribuidor/equipo',
            'canEdit' => auth_can('tenant.users.edit'),
        ]);
    }

    public function store()
    {
        $r = Services::users()->addMember('tenant', (int) Services::context()->tenantId(), null, $this->request->getPost(), $this->actorId());
        if ($r->ok && $r->data['temp_password']) {
            session()->setFlashdata('temp_password', $r->data['temp_password']);
        }

        return $this->afterResult($r, 'distribuidor/equipo', lang('Core.memberAdded'));
    }

    public function update(int $id)
    {
        return $this->afterResult(Services::users()->updateMember($id, 'tenant', (int) Services::context()->tenantId(), null, $this->request->getPost(), $this->actorId()), 'distribuidor/equipo');
    }
}
