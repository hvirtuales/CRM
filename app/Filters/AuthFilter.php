<?php

namespace App\Filters;

use App\Libraries\Rls;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * `auth`        → sesión válida + MFA superado + contraseña vigente + ámbito activo.
 * `auth:noscope` → igual pero sin exigir ámbito (selector de contexto, perfil).
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $ctx = Services::resetRequestState();
        if (! $ctx->loadFromSession()) {
            if (session('user_id')) {
                session()->destroy();
            }

            return $this->deny($request, 'ingresar', lang('Auth.loginRequired'));
        }

        $path = trim($request->getUri()->getPath(), '/');

        if (! session('mfa_passed') && Services::auth()->mfaRequired($ctx->user())) {
            $target = (int) $ctx->user()['mfa_enabled'] === 1 ? 'mfa' : 'perfil/mfa';
            if (! str_starts_with($path, $target) && ! str_starts_with($path, 'salir')) {
                return $this->deny($request, $target, lang('Auth.mfaRequired'));
            }

            return null; // permite completar el MFA
        }

        if ((int) $ctx->user()['must_change_password'] === 1 && ! str_starts_with($path, 'perfil/clave') && ! str_starts_with($path, 'salir')) {
            return $this->deny($request, 'perfil/clave', lang('Auth.mustChangePassword'));
        }

        $requireScope = ! in_array('noscope', (array) $arguments, true);
        if ($requireScope && $ctx->scope() === null) {
            return $this->deny($request, 'contexto', lang('Core.chooseContext'));
        }

        Rls::setCompany($ctx->companyId());

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function deny(RequestInterface $request, string $to, string $message)
    {
        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return Services::response()->setStatusCode(401)->setJSON(['messages' => ['error' => $message]]);
        }

        return redirect()->to(site_url($to))->with('error', $message);
    }
}
