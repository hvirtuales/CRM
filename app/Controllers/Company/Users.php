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
            'canEdit' => auth_can('company.users.edit'),
            'quota'   => Services::plans()->usersRemaining($cid),
        ]);
    }

    public function store()
    {
        $r = Services::users()->addMember('company', null, (int) Services::context()->companyId(), $this->request->getPost(), $this->actorId());
        if ($r->ok && $r->data['temp_password']) {
            session()->setFlashdata('temp_password', $r->data['temp_password']);
        }

        return $this->afterResult($r, 'negocio/usuarios', lang('Core.memberAdded'));
    }

    public function update(int $id)
    {
        return $this->afterResult(
            Services::users()->updateMember($id, 'company', null, (int) Services::context()->companyId(), $this->request->getPost(), $this->actorId()),
            'negocio/usuarios'
        );
    }
}
