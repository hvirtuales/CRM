<?php

namespace Components\Cotizaciones\Controllers;

use App\Controllers\BaseController;
use Components\Cotizaciones\Libraries\PlanGuard;
use Components\Cotizaciones\Libraries\QuoteService;
use Components\Cotizaciones\Libraries\Result;

class Quotes extends BaseController
{
    public function index()
    {
        $cid     = (int) session('active_company_id');
        $filters = ['status' => (string) $this->request->getGet('status'), 'q' => (string) $this->request->getGet('q')];
        $openId  = (int) $this->request->getGet('open');
        $open    = $openId ? service('quoteQuotes')->find($cid, $openId) : null;
        $newFor  = (int) $this->request->getGet('opportunity');

        return view('Components\Cotizaciones\Views\admin\index', [
            'quotes'        => service('quoteQuotes')->list($cid, $filters),
            'filters'       => $filters,
            'open'          => $open,
            'lines'         => $open ? service('quoteQuotes')->lines($cid, $openId) : [],
            'isNew'         => $this->request->getGet('new') === '1' || $newFor > 0,
            'newFor'        => $newFor,
            'opportunities' => service('crmOpportunities')->openOptions($cid),
            'catalog'       => service('quoteCatalog')->list($cid, true),
            'quota'         => (new PlanGuard())->quotesLeft($cid),
            'transitions'   => QuoteService::TRANSITIONS,
            'currency'      => (string) (ctx()->company()['currency'] ?? 'COP'),
        ]);
    }

    public function store()
    {
        $r = service('quoteQuotes')->save((int) session('active_company_id'), $this->request->getPost(), $this->actorId());

        return $this->done($r, $r->ok ? 'cotizaciones?open=' . $r->data['id'] : 'cotizaciones?new=1');
    }

    public function update(int $id)
    {
        return $this->done(service('quoteQuotes')->save((int) session('active_company_id'), $this->request->getPost(), $this->actorId(), $id), 'cotizaciones?open=' . $id);
    }

    public function transition(int $id)
    {
        $to = (string) $this->request->getPost('status');
        if ($to === 'void') {
            return $this->done(Result::fail(lang('Quote.transitionInvalid'), [], 403), 'cotizaciones?open=' . $id); // anular exige quote.delete
        }

        return $this->done(service('quoteQuotes')->transition((int) session('active_company_id'), $id, $to, $this->actorId()), 'cotizaciones?open=' . $id);
    }

    public function void(int $id)
    {
        return $this->done(service('quoteQuotes')->transition((int) session('active_company_id'), $id, 'void', $this->actorId()), 'cotizaciones');
    }

    private function done(Result $r, string $to)
    {
        if ($r->ok) {
            return redirect()->to(site_url($to))->with('message', $r->message);
        }
        if ($r->status === 404) {
            return redirect()->to(site_url('cotizaciones'))->with('error', $r->message);
        }

        return redirect()->to(site_url($to))->withInput()->with('error', $r->message)->with('errors', $r->errors);
    }
}
