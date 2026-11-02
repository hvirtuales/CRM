<?php

namespace App\Controllers;

use Config\Services;

class ContextController extends BaseController
{
    public function index()
    {
        $uid = $this->actorId();

        return view('context/index', [
            'available' => Services::contextSwitcher()->available($uid),
            'support'   => Services::contextSwitcher()->supportAccesses($uid),
        ]);
    }

    public function switch()
    {
        $result = Services::contextSwitcher()->switchTo($this->actorId(), (int) $this->request->getPost('membership_id'));

        return $result->ok ? redirect()->to(site_url('/')) : redirect()->to(site_url('contexto'))->with('error', $result->message);
    }

    public function support()
    {
        $result = Services::contextSwitcher()->enterSupport($this->actorId(), (int) $this->request->getPost('grant_id'));

        return $result->ok ? redirect()->to(site_url('negocio')) : redirect()->to(site_url('contexto'))->with('error', $result->message);
    }
}
