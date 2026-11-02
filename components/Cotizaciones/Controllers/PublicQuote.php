<?php

namespace Components\Cotizaciones\Controllers;

use App\Controllers\BaseController;

/** Vista pública de una cotización enviada (solo lectura, enlace opaco no indexable). */
class PublicQuote extends BaseController
{
    /**
     * Con RLS activo la búsqueda por identificador opaco necesita omitir la política un instante;
     * después se fija el negocio dueño y el resto de lecturas quedan acotadas a él.
     */
    private function lookup(string $publicId): ?array
    {
        $rls = class_exists(\App\Libraries\Rls::class);
        $rls && \App\Libraries\Rls::setBypass(true);

        try {
            $quote = service('quoteQuotes')->findPublic($publicId);
        } finally {
            $rls && \App\Libraries\Rls::setBypass(false);
        }
        if ($quote && $rls) {
            \App\Libraries\Rls::setCompany((int) $quote['company_id']);
        }

        return $quote;
    }

    public function show(string $publicId)
    {
        $quote = $this->lookup($publicId);
        $this->response->setHeader('X-Robots-Tag', 'noindex, nofollow')->setHeader('Referrer-Policy', 'no-referrer');
        if (! $quote) {
            return $this->response->setStatusCode(404)->setBody(view('Components\Cotizaciones\Views\public\quote', ['quote' => null, 'lines' => [], 'brand' => null]));
        }
        $brand = $quote['tenant_id'] ? (db_connect()->table('tenants')->select('brand_color')->where('id', $quote['tenant_id'])->get()->getRow()->brand_color ?? null) : null;

        return view('Components\Cotizaciones\Views\public\quote', [
            'quote' => $quote,
            'lines' => service('quoteQuotes')->lines((int) $quote['company_id'], (int) $quote['id']),
            'brand' => $brand,
        ]);
    }
}
