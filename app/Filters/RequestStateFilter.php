<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Primer filtro global: cada petición arranca con contexto vacío y cachés limpias, también en
 * rutas públicas. Ningún dato de autorización sobrevive entre peticiones (modo worker incluido).
 */
class RequestStateFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        Services::resetRequestState();
        \App\Libraries\Rls::setCompany(null);
        \App\Libraries\Rls::setBypass(false);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
