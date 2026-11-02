<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Resuelve el idioma de la petición: preferencia en sesión (es|en), si no el predeterminado. */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (is_cli() && ENVIRONMENT !== 'testing') {
            return null;
        }
        $locale = session('locale');
        if (! in_array($locale, config('App')->supportedLocales, true)) {
            $locale = config('App')->defaultLocale;
        }
        $request->setLocale($locale);
        service('language')->setLocale($locale);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
