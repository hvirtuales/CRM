<?php

namespace App\Controllers;

use App\Libraries\Totp;
use Config\Services;

class Profile extends BaseController
{
    public function index()
    {
        return view('profile/index', ['user' => Services::context()->user()]);
    }

    public function update()
    {
        $name   = trim((string) $this->request->getPost('name'));
        $locale = (string) $this->request->getPost('locale');
        if ($name === '' || mb_strlen($name) > 120 || ! in_array($locale, ['es', 'en'], true)) {
            return redirect()->back()->withInput()->with('error', lang('Core.validation.fix'))->with('errors', ['name' => lang('Core.validation.name')]);
        }
        db_connect()->table('users')->where('id', $this->actorId())->update(['name' => $name, 'locale' => $locale, 'updated_at' => date('Y-m-d H:i:s')]);
        session()->set('locale', $locale);
        Services::audit()->log('user.profile_updated', 'user', $this->actorId());

        return redirect()->to(site_url('perfil'))->with('message', lang('Core.saved'));
    }

    public function password()
    {
        return view('profile/password', ['forced' => (int) Services::context()->user()['must_change_password'] === 1]);
    }

    public function changePassword()
    {
        $user    = Services::context()->user();
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('new_password_confirm');

        if (! password_verify($current, $user['password_hash'])) {
            return redirect()->back()->with('error', lang('Auth.currentPasswordWrong'))->with('errors', ['current_password' => lang('Auth.currentPasswordWrong')]);
        }
        if ($new !== $confirm) {
            return redirect()->back()->with('error', lang('Auth.passwordMismatch'))->with('errors', ['new_password_confirm' => lang('Auth.passwordMismatch')]);
        }
        if ($problem = Services::auth()->passwordProblem($new, $user['email'])) {
            return redirect()->back()->with('error', $problem)->with('errors', ['new_password' => $problem]);
        }
        if (password_verify($new, $user['password_hash'])) {
            return redirect()->back()->with('error', lang('Auth.passwordReused'))->with('errors', ['new_password' => lang('Auth.passwordReused')]);
        }

        Services::auth()->changePassword((int) $user['id'], $new);

        return redirect()->to(site_url('/'))->with('message', lang('Auth.passwordChanged'));
    }

    public function mfa()
    {
        $user = Services::context()->user();
        if ((int) $user['mfa_enabled'] === 1) {
            return view('profile/mfa', ['enabled' => true]);
        }

        // Secreto provisional en sesión hasta confirmar con un código válido.
        $secret = session('mfa_pending_secret');
        if (! is_string($secret) || strlen($secret) < 16) {
            $secret = Totp::generateSecret();
            session()->set('mfa_pending_secret', $secret);
        }

        return view('profile/mfa', [
            'enabled' => false,
            'secret'  => $secret,
            'uri'     => Totp::uri($secret, $user['email'], 'HV CRM'),
        ]);
    }

    public function enableMfa()
    {
        $user   = Services::context()->user();
        $secret = session('mfa_pending_secret');
        if (! is_string($secret) || ! Totp::verify($secret, (string) $this->request->getPost('code'))) {
            return redirect()->back()->with('error', lang('Auth.mfaInvalid'));
        }

        db_connect()->table('users')->where('id', $user['id'])->update([
            'mfa_secret' => Services::auth()->encryptSecret($secret), 'mfa_enabled' => 1, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        session()->remove('mfa_pending_secret');
        session()->regenerate(true);
        session()->set('mfa_passed', true);
        Services::audit()->log('auth.mfa_enabled', 'user', (int) $user['id']);
        if (! session('membership_id') && ! session('support_grant_id')) {
            Services::contextSwitcher()->autoEnter((int) $user['id']);
        }

        return redirect()->to(site_url('/'))->with('message', lang('Auth.mfaEnabled'));
    }

    public function disableMfa()
    {
        $user = Services::context()->user();
        if (Services::auth()->hasPlatformMembership((int) $user['id']) && config('Rbac')->requireMfaForPlatform) {
            return redirect()->back()->with('error', lang('Auth.mfaMandatory'));
        }
        if (! Services::auth()->verifyMfa($user, (string) $this->request->getPost('code'))) {
            return redirect()->back()->with('error', lang('Auth.mfaInvalid'));
        }
        db_connect()->table('users')->where('id', $user['id'])->update(['mfa_secret' => null, 'mfa_enabled' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log('auth.mfa_disabled', 'user', (int) $user['id']);

        return redirect()->to(site_url('perfil'))->with('message', lang('Auth.mfaDisabled'));
    }
}
