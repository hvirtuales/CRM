<?php

namespace Components\Cotizaciones\Controllers;

use App\Controllers\BaseController;
use Components\Cotizaciones\Libraries\Result;

class Catalog extends BaseController
{
    public function index()
    {
        $cid    = (int) session('active_company_id');
        $editId = (int) $this->request->getGet('edit');

        return view('Components\Cotizaciones\Views\admin\catalog', [
            'services' => service('quoteCatalog')->list($cid),
            'edit'     => $editId ? service('quoteCatalog')->find($cid, $editId) : null,
            'isNew'    => $this->request->getGet('new') === '1',
            'currency' => (string) (ctx()->company()['currency'] ?? 'COP'),
        ]);
    }

    public function store()
    {
        return $this->done(service('quoteCatalog')->save((int) session('active_company_id'), $this->request->getPost()), 'cotizaciones/catalogo?new=1');
    }

    public function update(int $id)
    {
        return $this->done(service('quoteCatalog')->save((int) session('active_company_id'), $this->request->getPost(), $id), 'cotizaciones/catalogo?edit=' . $id);
    }

    private function done(Result $r, string $failTo)
    {
        if ($r->ok) {
            return redirect()->to(site_url('cotizaciones/catalogo'))->with('message', $r->message);
        }

        return redirect()->to(site_url($r->status === 404 ? 'cotizaciones/catalogo' : $failTo))->withInput()->with('error', $r->message)->with('errors', $r->errors);
    }
}
