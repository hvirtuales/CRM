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
        return redirect()->to(self::safeInternalUrl((string) $this->request->getPost('back')));
    }

    /**
     * Solo URLs del mismo origen que baseURL (esquema + host + puerto exactos); si no, inicio.
     * Evita open redirect con hosts como «crm.ejemplo.com.evil.com» o «crm.ejemplo.com@evil.com».
     */
    public static function safeInternalUrl(string $url): string
    {
        $base = parse_url(base_url());
        $u    = parse_url($url);
        if ($url === '' || $u === false || isset($u['user']) || isset($u['pass'])
            || ($u['scheme'] ?? null) !== ($base['scheme'] ?? null)
            || strtolower($u['host'] ?? '') !== strtolower($base['host'] ?? '')
            || ($u['port'] ?? null) !== ($base['port'] ?? null)) {
            return site_url('/');
        }

        return $url;
    }

    /** Si solo hay un ámbito disponible se entra directo; si no, selector. */
    private function afterLogin(int $userId)
    {
        $pending = session('after_login');
        if (is_string($pending) && preg_match('#^invitacion/[0-9a-f]{48}$#', $pending)) {
            session()->remove('after_login');

            return redirect()->to(site_url($pending)); // aceptar la invitación con la cuenta recién autenticada
        }

        return redirect()->to(site_url(Services::contextSwitcher()->autoEnter($userId) ? '/' : 'contexto'));
    }
}
