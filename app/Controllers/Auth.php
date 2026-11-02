<?php

namespace App\Controllers;

use Config\Services;

class Auth extends BaseController
{
    public function login()
    {
        if (session('user_id') && Services::context()->loadFromSession()) {
            return redirect()->to(site_url('/'));
        }

        return view('auth/login');
    }

    public function attempt()
    {
        $ip = $this->request->getIPAddress();
        if (! Services::throttler()->check('login-' . md5($ip), 10, MINUTE)) {
            return redirect()->back()->with('error', lang('Core.tooManyRequests'));
        }

        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');
        if ($email === '' || $password === '' || strlen($password) > 256) {
            return redirect()->back()->withInput()->with('error', lang('Auth.invalidCredentials'));
        }

        $result = Services::auth()->attempt($email, $password, $ip);
        if (! $result->ok) {
            return redirect()->back()->withInput()->with('error', $result->message);
        }

        $user = $result->data;
        $mfa  = Services::auth()->mfaRequired($user);
        Services::auth()->startSession($user, ! $mfa);
        Services::resetContext()->loadFromSession();
        Services::audit()->log('auth.login', 'user', (int) $user['id'], ['mfa_pending' => $mfa]);

        if ($mfa) {
            return redirect()->to(site_url((int) $user['mfa_enabled'] === 1 ? 'mfa' : 'perfil/mfa'));
        }

        return $this->afterLogin((int) $user['id']);
    }

    public function mfa()
    {
        if (session('mfa_passed')) {
            return redirect()->to(site_url('/'));
        }

        return view('auth/mfa');
    }

    public function verifyMfa()
    {
        $ctx  = Services::context();
        $user = $ctx->user();
        if (! Services::throttler()->check('mfa-' . $user['id'], 5, MINUTE)) {
            return redirect()->back()->with('error', lang('Core.tooManyRequests'));
        }
        if (! Services::auth()->verifyMfa($user, (string) $this->request->getPost('code'))) {
            Services::audit()->log('auth.mfa_failed', 'user', (int) $user['id']);

            return redirect()->back()->with('error', lang('Auth.mfaInvalid'));
        }

        session()->regenerate(true);
        session()->set('mfa_passed', true);
        Services::audit()->log('auth.mfa_passed', 'user', (int) $user['id']);

        return $this->afterLogin((int) $user['id']);
    }

    public function logout()
    {
        if (session('user_id')) {
            Services::resetContext()->loadFromSession();
            Services::audit()->log('auth.logout', 'user', (int) session('user_id'));
        }
        session()->destroy();

        return redirect()->to(site_url('ingresar'))->with('message', lang('Auth.loggedOut'));
    }

    public function locale(string $locale)
    {
        if (in_array($locale, config('App')->supportedLocales, true)) {
            session()->set('locale', $locale);
            if ($uid = session('user_id')) {
                db_connect()->table('users')->where('id', (int) $uid)->update(['locale' => $locale]);
            }
        }
        // Solo redirecciones internas (evita open redirect).
        $back = previous_url();
        $base = rtrim(base_url(), '/');

        return redirect()->to(str_starts_with($back, $base) ? $back : site_url('/'));
    }

    /** Si solo hay un ámbito disponible se entra directo; si no, selector. */
    private function afterLogin(int $userId)
    {
        return redirect()->to(site_url(Services::contextSwitcher()->autoEnter($userId) ? '/' : 'contexto'));
    }
}
