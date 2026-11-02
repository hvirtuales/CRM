<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Cabeceras de seguridad (OWASP ASVS V14): CSP sin scripts en línea, anti-clickjacking,
 * no-sniff, política de referer y permisos. Los formularios públicos pueden declarar
 * dominios de inserción (frame-ancestors) en la respuesta antes de este filtro.
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $ancestors = $response->getHeaderLine('X-HV-Frame-Ancestors');
        $response->removeHeader('X-HV-Frame-Ancestors');
        $frame = $ancestors !== '' ? $ancestors : "'none'";

        $response->setHeader('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'", // variables de marca y vistas públicas autosuficientes
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'frame-ancestors ' . $frame,
        ]));
        if ($ancestors === '') {
            $response->setHeader('X-Frame-Options', 'DENY');
        } else {
            $response->removeHeader('X-Frame-Options'); // frame-ancestors manda; XFO no admite listas
        }
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->setHeader('X-Request-Id', Services::context()->requestId());
        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (Services::context()->isAuthenticated()) {
            $response->setHeader('Cache-Control', 'no-store, private'); // datos de negocio: nunca en cachés compartidas
        }

        return $response;
    }
}
