<?php

namespace Components\Crm\Controllers;

use App\Controllers\BaseController;
use Components\Crm\Libraries\Result;

class Setup extends BaseController
{
    public function index()
    {
        $cid = (int) session('active_company_id');

        return view('Components\Crm\Views\setup\index', [
            'projects' => service('crmSetup')->projects($cid),
            'stages'   => service('crmSetup')->stages($cid),
            'editProject' => ($id = (int) $this->request->getGet('project')) ? service('crmSetup')->projectOf($cid, $id) : null,
            'editStage'   => ($id = (int) $this->request->getGet('stage')) ? service('crmSetup')->stageOf($cid, $id) : null,
        ]);
    }

    public function storeProject()
    {
        return $this->done(service('crmSetup')->saveProject((int) session('active_company_id'), $this->request->getPost()));
    }

    public function updateProject(int $id)
    {
        return $this->done(service('crmSetup')->saveProject((int) session('active_company_id'), $this->request->getPost(), $id));
    }

    public function storeStage()
    {
        return $this->done(service('crmSetup')->saveStage((int) session('active_company_id'), $this->request->getPost()));
    }

    public function updateStage(int $id)
    {
        return $this->done(service('crmSetup')->saveStage((int) session('active_company_id'), $this->request->getPost(), $id));
    }

    public function deleteStage(int $id)
    {
        return $this->done(service('crmSetup')->deleteStage((int) session('active_company_id'), $id));
    }

    private function done(Result $r)
    {
        return $r->ok
            ? redirect()->to(site_url('crm/configuracion'))->with('message', $r->message)
            : redirect()->back()->withInput()->with('error', $r->message)->with('errors', $r->errors);
    }
}
