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
            'invitations' => Services::invitations()->pending('tenant', (int) Services::context()->tenantId(), null),
            'canEdit' => auth_can('tenant.users.edit'),
        ]);
    }

    public function store()
    {
        $r = Services::users()->addMember('tenant', (int) Services::context()->tenantId(), null, $this->request->getPost(), $this->actorId());
        \App\Libraries\InvitationNotice::flash();

        return $this->afterResult($r, 'distribuidor/equipo', lang('Core.memberAdded'));
    }

    public function update(int $id)
    {
        return $this->afterResult(Services::users()->updateMember($id, 'tenant', (int) Services::context()->tenantId(), null, $this->request->getPost(), $this->actorId()), 'distribuidor/equipo');
    }

    public function revokeInvitation(int $id)
    {
        return $this->afterResult(Services::invitations()->revoke($id, 'tenant', (int) Services::context()->tenantId(), null), 'distribuidor/equipo');
    }
}
