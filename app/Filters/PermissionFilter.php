<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * `permission:a.b.c` (o varios separados por coma: basta uno). Denegar por defecto.
 * Además aplica modo solo lectura (suscripción suspendida, acceso de soporte) a métodos que escriben.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $ctx  = Services::context();
        $args = (array) $arguments;

        if ($args === [] || ! $ctx->isAuthenticated()) {
            return $this->forbidden($request, lang('Core.forbidden'));
        }

        $allowed = false;
        foreach ($args as $perm) {
            if ($ctx->can(trim((string) $perm))) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            Services::audit()->log('authz.denied', 'route', null, ['path' => $request->getUri()->getPath(), 'needs' => $args]);

            return $this->forbidden($request, lang('Core.forbidden'));
        }

        $method = strtoupper($request->getMethod());
        if ($ctx->isReadOnly() && ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $this->forbidden($request, $ctx->readOnlyReason() ?? lang('Core.readOnly'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function forbidden(RequestInterface $request, string $message)
    {
        $response = Services::response()->setStatusCode(403);
        if ($request->isAJAX() || str_starts_with(trim($request->getUri()->getPath(), '/'), 'api/')
            || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $response->setJSON(['messages' => ['error' => $message]]);
        }

        return $response->setBody(view('errors_hv/forbidden', ['message' => $message]));
    }
}
