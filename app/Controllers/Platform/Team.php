<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use Config\Services;

class Team extends BaseController
{
    public function index()
    {
        return view('shared/team', [
            'title'   => lang('Nav.team'),
            'members' => Services::users()->members('platform', null, null),
            'roles'   => Services::rbac()->assignableRoles('platform'),
            'base'    => 'plataforma/equipo',
            'invitations' => Services::invitations()->pending('platform', null, null),
            'canEdit' => auth_can('platform.users.edit'),
        ]);
    }

    public function store()
    {
        $r = Services::users()->addMember('platform', null, null, $this->request->getPost(), $this->actorId());
        \App\Libraries\InvitationNotice::flash();

        return $this->afterResult($r, 'plataforma/equipo', lang('Core.memberAdded'));
    }

    public function update(int $id)
    {
        return $this->afterResult(Services::users()->updateMember($id, 'platform', null, null, $this->request->getPost(), $this->actorId()), 'plataforma/equipo');
    }

    public function revokeInvitation(int $id)
    {
        return $this->afterResult(Services::invitations()->revoke($id, 'platform', null, null), 'plataforma/equipo');
    }
}
