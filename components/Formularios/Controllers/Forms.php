<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\PlanGuard;
use Components\Formularios\Libraries\Result;

class Forms extends BaseController
{
    public function index()
    {
        $cid    = (int) session('active_company_id');
        $editId = (int) $this->request->getGet('edit');

        return view('Components\Formularios\Views\admin\index', [
            'forms'    => service('formForms')->list($cid),
            'edit'     => $editId ? service('formForms')->find($cid, $editId) : null,
            'isNew'    => $this->request->getGet('new') === '1',
            'projects' => service('crmSetup')->projects($cid, true),
            'quota'    => (new PlanGuard())->formsLeft($cid),
            'types'    => FormService::TYPES,
            'mappings' => FormService::MAPPINGS,
        ]);
    }

    public function submissions(int $id)
    {
        $cid  = (int) session('active_company_id');
        $form = service('formForms')->find($cid, $id);
        if (! $form) {
            return redirect()->to(site_url('formularios'))->with('error', lang('Form.notFound'));
        }

        return view('Components\Formularios\Views\admin\submissions', [
            'form' => $form, 'rows' => service('formForms')->submissions($cid, $id),
            'fields' => json_decode((string) ($form['published_fields'] ?? $form['fields']), true) ?: [],
        ]);
    }

    public function store()
    {
        $r = service('formForms')->save((int) session('active_company_id'), $this->request->getPost());

        return $this->done($r, $r->ok ? 'formularios?edit=' . $r->data['id'] : 'formularios?new=1');
    }

    public function update(int $id)
    {
        return $this->done(service('formForms')->save((int) session('active_company_id'), $this->request->getPost(), $id), 'formularios?edit=' . $id);
    }

    public function publish(int $id)
    {
        return $this->done(service('formForms')->publish((int) session('active_company_id'), $id), 'formularios');
    }

    public function close(int $id)
    {
        return $this->done(service('formForms')->close((int) session('active_company_id'), $id), 'formularios');
    }

    public function archive(int $id)
    {
        return $this->done(service('formForms')->archive((int) session('active_company_id'), $id), 'formularios');
    }

    private function done(Result $r, string $to)
    {
        if ($r->ok) {
            return redirect()->to(site_url($to))->with('message', $r->message);
        }
        if ($r->status === 404) {
            return redirect()->to(site_url('formularios'))->with('error', $r->message);
        }

        return redirect()->to(site_url($to))->withInput()->with('error', $r->message)->with('errors', $r->errors);
    }
}
