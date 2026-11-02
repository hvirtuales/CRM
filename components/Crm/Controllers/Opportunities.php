<?php

namespace Components\Crm\Controllers;

use App\Controllers\BaseController;
use Components\Crm\Libraries\Members;
use Components\Crm\Libraries\Result;

/**
 * Embudo comercial: tablero kanban y listado. Controlador delgado; reglas en OpportunityService.
 */
class Opportunities extends BaseController
{
    public function index()
    {
        $cid     = (int) session('active_company_id');
        $filters = [
            'project' => $this->request->getGet('project'), 'owner' => $this->request->getGet('owner'),
            'q' => $this->request->getGet('q'), 'mine' => $this->request->getGet('mine') === '1',
        ];
        $opps   = service('crmOpportunities')->board($cid, $filters, $this->actorId());
        $view   = $this->request->getGet('view') === 'list' ? 'list' : 'board';
        $openId = (int) $this->request->getGet('open');
        $open   = $openId ? service('crmOpportunities')->find($cid, $openId) : null;

        return view('Components\Crm\Views\opportunities\index', [
            'opps'       => $opps,
            'stages'     => service('crmSetup')->stages($cid),
            'projects'   => service('crmSetup')->projects($cid, true),
            'owners'     => Members::options($cid),
            'filters'    => $filters,
            'view'       => $view,
            'open'       => $open,
            'openContact'=> $open ? service('crmContacts')->find($cid, (int) $open['contact_id']) : null,
            'activities' => $open ? service('crmOpportunities')->activities($cid, $openId) : [],
            'canTouch'   => $open ? service('crmOpportunities')->canTouch($open, $this->actorId()) : false,
            'contacts'   => service('crmContacts')->search($cid, ['status' => 'active'])->findAll(500),
            'quota'      => service('crmPlanGuard')->opportunitiesLeft($cid),
            'currency'   => (string) (ctx()->company()['currency'] ?? 'COP'),
        ]);
    }

    public function store()
    {
        $r = service('crmOpportunities')->create((int) session('active_company_id'), $this->request->getPost(), $this->actorId());

        return $this->done($r, 'crm/oportunidades' . ($r->ok ? '?open=' . $r->data['id'] : ''));
    }

    public function update(int $id)
    {
        $r = service('crmOpportunities')->update((int) session('active_company_id'), $id, $this->request->getPost(), $this->actorId());

        return $this->done($r, 'crm/oportunidades?open=' . $id);
    }

    /** Admite formulario o JSON (tablero kanban con fetch + CSRF). */
    public function move(int $id)
    {
        $data = $this->request->is('json') ? (array) $this->request->getJSON(true) : $this->request->getPost();
        $r    = service('crmOpportunities')->move((int) session('active_company_id'), $id, (int) ($data['stage_id'] ?? 0), $this->actorId(), isset($data['lost_reason']) ? (string) $data['lost_reason'] : null);

        if ($this->request->is('json') || $this->request->isAJAX()) {
            return $this->response->setStatusCode($r->ok ? 200 : $r->status)->setJSON($r->ok
                ? ['data' => ['id' => $id], 'message' => $r->message]
                : ['messages' => ['error' => $r->message], 'errors' => $r->errors]);
        }

        return $this->done($r, 'crm/oportunidades?open=' . $id);
    }

    public function activity(int $id)
    {
        $r = service('crmOpportunities')->addActivity((int) session('active_company_id'), $id, (string) $this->request->getPost('type'), (string) $this->request->getPost('body'), $this->actorId());

        return $this->done($r, 'crm/oportunidades?open=' . $id);
    }

    public function archive(int $id)
    {
        return $this->done(service('crmOpportunities')->archive((int) session('active_company_id'), $id), 'crm/oportunidades');
    }

    private function done(Result $r, string $to)
    {
        if ($r->ok) {
            return redirect()->to(site_url($to))->with('message', $r->message ?? lang('Crm.saved'));
        }
        if ($r->status === 404) {
            return redirect()->to(site_url('crm/oportunidades'))->with('error', $r->message);
        }

        return redirect()->back()->withInput()->with('error', $r->message)->with('errors', $r->errors);
    }
}
