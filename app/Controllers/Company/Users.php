<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use Config\Services;

class Users extends BaseController
{
    public function index()
    {
        $cid = (int) Services::context()->companyId();

        return view('shared/team', [
            'title'   => lang('Nav.users'),
            'members' => Services::users()->members('company', null, $cid),
            'roles'   => Services::rbac()->assignableRoles('company', $cid),
            'base'    => 'negocio/usuarios',
            'invitations' => Services::invitations()->pending('company', null, (int) Services::context()->companyId()),
            'canEdit' => auth_can('company.users.edit'),
            'quota'   => Services::plans()->usersRemaining($cid),
        ]);
    }

    public function store()
    {
        $r = Services::users()->addMember('company', null, (int) Services::context()->companyId(), $this->request->getPost(), $this->actorId());
        \App\Libraries\InvitationNotice::flash();

        return $this->afterResult($r, 'negocio/usuarios', lang('Core.memberAdded'));
    }

    public function update(int $id)
    {
        return $this->afterResult(
            Services::users()->updateMember($id, 'company', null, (int) Services::context()->companyId(), $this->request->getPost(), $this->actorId()),
            'negocio/usuarios'
        );
    }

    public function revokeInvitation(int $id)
    {
        return $this->afterResult(Services::invitations()->revoke($id, 'company', null, (int) Services::context()->companyId()), 'negocio/usuarios');
    }
}
