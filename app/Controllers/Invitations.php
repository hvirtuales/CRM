<?php

namespace App\Controllers;

use Config\Services;

/**
 * Aceptación de invitaciones. El token llega solo al correo invitado.
 * - Cuenta nueva: el titular define su contraseña aquí.
 * - Cuenta existente: debe iniciar sesión con ESA cuenta (y su MFA) antes de aceptar.
 */
class Invitations extends BaseController
{
    public function show(string $token)
    {
        $inv = Services::invitations()->findValid($token);
        if (! $inv) {
            return $this->response->setStatusCode(404)->setBody(view('auth/invitation', ['inv' => null, 'token' => '', 'sessionUser' => null]));
        }
        $sessionUser = $this->sessionUser();
        if ($inv['has_account'] && ! $sessionUser) {
            session()->set('after_login', 'invitacion/' . $token);
        }

        return view('auth/invitation', ['inv' => $inv, 'token' => $token, 'sessionUser' => $sessionUser]);
    }

    public function accept(string $token)
    {
        if (! Services::throttler()->check('invite-' . md5($this->request->getIPAddress()), 10, MINUTE)) {
            return redirect()->back()->with('error', lang('Core.tooManyRequests'));
        }
        $user = $this->sessionUser();
        $r    = Services::invitations()->accept(
            $token,
            $user ? (int) $user['id'] : null,
            $this->request->getPost('password') !== null ? (string) $this->request->getPost('password') : null,
            $this->request->getPost('password_confirm') !== null ? (string) $this->request->getPost('password_confirm') : null,
        );
        if (! $r->ok) {
            return redirect()->to(site_url('invitacion/' . $token))->with('error', $r->message)->with('errors', $r->errors);
        }
        if ($r->data['new_account']) {
            return redirect()->to(site_url('ingresar'))->with('message', lang('Core.invitationAcceptedNew'));
        }

        return redirect()->to(site_url('contexto'))->with('message', lang('Core.invitationAccepted'));
    }

    /** Usuario autenticado y con MFA superado (si lo requiere), o null. */
    private function sessionUser(): ?array
    {
        $ctx = Services::resetRequestState();
        if (! $ctx->loadFromSession()) {
            return null;
        }
        if (! session('mfa_passed') && Services::auth()->mfaRequired($ctx->user())) {
            return null;
        }

        return $ctx->user();
    }
}
