<?php

namespace Components\Crm\Controllers;

use App\Controllers\BaseController;
use Components\Crm\Libraries\Members;
use Components\Crm\Libraries\Result;

class Contacts extends BaseController
{
    public function index()
    {
        $cid     = (int) session('active_company_id');
        $filters = ['q' => (string) $this->request->getGet('q'), 'status' => (string) ($this->request->getGet('status') ?: 'active')];
        $model   = service('crmContacts')->search($cid, $filters);
        $rows    = $model->paginate(50);
        $editId  = (int) $this->request->getGet('edit');
        $edit    = $editId ? service('crmContacts')->find($cid, $editId) : null;

        return view('Components\Crm\Views\contacts\index', [
            'rows'       => $rows,
            'pager'      => $model->pager,
            'filters'    => $filters,
            'edit'       => $edit,
            'activities' => $edit ? service('crmContacts')->activities($cid, $editId) : [],
            'owners'     => Members::options($cid),
            'quota'      => service('crmPlanGuard')->contactsLeft($cid),
        ]);
    }

    public function store()
    {
        $r = service('crmContacts')->create((int) session('active_company_id'), $this->request->getPost(), $this->actorId());

        return $this->done($r, 'crm/contactos');
    }

    public function update(int $id)
    {
        $r = service('crmContacts')->update((int) session('active_company_id'), $id, $this->request->getPost());

        return $this->done($r, 'crm/contactos?edit=' . $id);
    }

    public function archive(int $id)
    {
        $r = service('crmContacts')->archive((int) session('active_company_id'), $id, $this->request->getPost('restore') !== '1');

        return $this->done($r, 'crm/contactos');
    }

    /**
     * Exportación CSV (separador ; y BOM para Excel en español). Neutraliza inyección de fórmulas
     * (CSV injection, OWASP): celdas que empiezan con = + - @ tab o CR se prefijan con apóstrofo.
     */
    public function export()
    {
        $cid  = (int) session('active_company_id');
        $rows = service('crmContacts')->search($cid, ['status' => (string) ($this->request->getGet('status') ?: 'active'), 'q' => (string) $this->request->getGet('q')])->findAll(20000);
        $safe = static function ($v): string {
            $v = (string) $v;

            return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        };
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [lang('Crm.firstName'), lang('Crm.lastName'), lang('Crm.organization'), lang('Crm.email'), lang('Crm.phone'), lang('Crm.city'), lang('Crm.source'), lang('Crm.consent'), lang('Crm.createdAt')], ';');
        foreach ($rows as $c) {
            fputcsv($fh, array_map($safe, [$c['first_name'], $c['last_name'], $c['organization'], $c['email'], $c['phone'], $c['city'], $c['source'], $c['consent_marketing'] ? 'SI' : 'NO', $c['created_at']]), ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        service('audit')->log('crm.contacts_exported', 'crm_contact', null, ['count' => count($rows)], $cid);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="contactos-' . date('Ymd') . '.csv"')
            ->setBody($csv);
    }

    private function done(Result $r, string $to)
    {
        if ($r->ok) {
            return redirect()->to(site_url($to))->with('message', $r->message ?? lang('Crm.saved'));
        }
        if ($r->status === 404) {
            return redirect()->to(site_url('crm/contactos'))->with('error', $r->message);
        }

        return redirect()->back()->withInput()->with('error', $r->message)->with('errors', $r->errors);
    }
}
