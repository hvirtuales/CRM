<?php

namespace App\Libraries;

use Config\Rbac;
use Config\Services;

/**
 * Autenticación: contraseña (Argon2id), bloqueo por intentos, MFA TOTP y política de contraseñas.
 */
class AuthService
{
    public const MAX_FAILS_PER_ACCOUNT = 5;
    public const MAX_FAILS_PER_IP      = 30;
    public const WINDOW_MINUTES        = 15;
    public const MIN_PASSWORD_LENGTH   = 12;

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    /**
     * Hash ficticio PRECALCULADO (mismos parámetros Argon2id por defecto) para que un correo
     * inexistente cueste exactamente un password_verify, igual que uno existente.
     */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$blY2eVRhTkxEakxaZ3czZg$4APdfSes4jZDP+N/PuGMDoJrkrRNBPPwTLunHQh0jd4';

    private static function dummyHash(): string
    {
        return defined('PASSWORD_ARGON2ID') ? self::DUMMY_HASH : '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    }

    /**
     * Valida credenciales. No revela si el correo existe.
     *
     * @return Result data = fila de usuario
     */
    public function attempt(string $email, string $password, string $ip): Result
    {
        $email     = strtolower(trim($email));
        $emailHash = hash('sha256', $email);

        if ($this->isLocked($emailHash, $ip)) {
            Services::audit()->log('auth.locked', 'user', null, ['email_hash' => substr($emailHash, 0, 12)]);

            return Result::fail(lang('Auth.locked', [self::WINDOW_MINUTES]), [], 429);
        }

        $user = db_connect()->table('users')->where('email', $email)->where('deleted_at', null)->get()->getRowArray();
        $ok   = password_verify($password, $user['password_hash'] ?? self::dummyHash());

        $this->recordAttempt($emailHash, $ip, $ok && $user && $user['status'] === 'active');

        if (! $ok || ! $user) {
            return Result::fail(lang('Auth.invalidCredentials'), [], 401);
        }
        if ($user['status'] !== 'active') {
            return Result::fail(lang('Auth.accountSuspended'), [], 403);
        }

        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            db_connect()->table('users')->where('id', $user['id'])->update(['password_hash' => self::hashPassword($password)]);
        }

        return Result::success($user);
    }

    public function isLocked(string $emailHash, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $db    = db_connect();

        $byAccount = $db->table('login_attempts')->where('email_hash', $emailHash)->where('success', 0)->where('created_at >=', $since)->countAllResults();
        if ($byAccount >= self::MAX_FAILS_PER_ACCOUNT) {
            return true;
        }

        return $db->table('login_attempts')->where('ip', $ip)->where('success', 0)->where('created_at >=', $since)->countAllResults() >= self::MAX_FAILS_PER_IP;
    }

    private function recordAttempt(string $emailHash, string $ip, bool $success): void
    {
        $db = db_connect();
        $db->table('login_attempts')->insert([
            'email_hash' => $emailHash, 'ip' => $ip, 'success' => $success ? 1 : 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($success) {
            // Un acceso correcto limpia los fallos de la cuenta.
            $db->table('login_attempts')->where('email_hash', $emailHash)->where('success', 0)->delete();
        }
    }

    /** Inicia sesión tras credenciales (y MFA si aplica). Regenera el id para evitar fijación. */
    public function startSession(array $user, bool $mfaPassed): void
    {
        $session = session();
        $session->regenerate(true);
        $session->set([
            'user_id'         => (int) $user['id'],
            'session_version' => (int) $user['session_version'],
            'mfa_passed'      => $mfaPassed,
            'locale'          => $user['locale'],
        ]);
        db_connect()->table('users')->where('id', $user['id'])->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /** ¿El usuario debe usar MFA? (inscrito, o con membresía de plataforma y política activa) */
    public function mfaRequired(array $user): bool
    {
        if ((int) $user['mfa_enabled'] === 1) {
            return true;
        }

        return config(Rbac::class)->requireMfaForPlatform && $this->hasPlatformMembership((int) $user['id']);
    }

    public function hasPlatformMembership(int $userId): bool
    {
        return db_connect()->table('memberships')->where('user_id', $userId)->where('scope', 'platform')->where('status', 'active')->countAllResults() > 0;
    }

    public const MAX_MFA_FAILURES = 10;

    /**
     * Verifica el segundo factor: rechaza códigos ya usados (paso ≤ último aceptado) y cuenta fallos
     * consecutivos. Al llegar a MAX_MFA_FAILURES se invalidan todas las sesiones del usuario
     * (debe volver a ingresar con contraseña) y se audita.
     */
    public function verifyMfa(array $user, string $code): bool
    {
        $db     = db_connect();
        $secret = $this->decryptSecret($user['mfa_secret'] ?? null);
        $step   = $secret !== null ? Totp::matchStep($secret, $code) : null;
        $last   = $db->table('users')->select('mfa_last_step, mfa_failures, session_version')->where('id', $user['id'])->get()->getRowArray();

        if ($step !== null && ($last['mfa_last_step'] === null || $step > (int) $last['mfa_last_step'])) {
            $db->table('users')->where('id', $user['id'])->update(['mfa_last_step' => $step, 'mfa_failures' => 0]);

            return true;
        }

        $failures = (int) $last['mfa_failures'] + 1;
        $update   = ['mfa_failures' => $failures];
        if ($failures >= self::MAX_MFA_FAILURES) {
            $update = ['mfa_failures' => 0, 'session_version' => (int) $last['session_version'] + 1];
            Services::audit()->log('auth.mfa_lockout', 'user', (int) $user['id']);
        }
        $db->table('users')->where('id', $user['id'])->update($update);

        return false;
    }

    public function encryptSecret(string $secret): string
    {
        return base64_encode(Services::encrypter()->encrypt($secret));
    }

    public function decryptSecret(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return Services::encrypter()->decrypt(base64_decode($stored, true) ?: '');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Política de contraseñas (NIST SP 800-63B): longitud mínima, sin composición forzada,
     * rechaza contraseñas que contienen el correo o comunes.
     *
     * @return string|null mensaje de error o null si es válida
     */
    public function passwordProblem(string $password, string $email = ''): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return lang('Auth.passwordTooShort', [self::MIN_PASSWORD_LENGTH]);
        }
        if (mb_strlen($password) > 128) {
            return lang('Auth.passwordTooLong');
        }
        $local = strtolower((string) strstr($email, '@', true));
        if ($local !== '' && strlen($local) >= 4 && str_contains(strtolower($password), $local)) {
            return lang('Auth.passwordContainsEmail');
        }
        $common = ['123456789012', 'contraseña123', 'password1234', 'qwertyuiop12', 'herramientas', 'adminadmin12'];
        if (in_array(strtolower($password), $common, true) || count(array_unique(str_split($password))) < 5) {
            return lang('Auth.passwordWeak');
        }

        return null;
    }

    /** Cambia la contraseña e invalida todas las demás sesiones del usuario. */
    public function changePassword(int $userId, string $newPassword): void
    {
        $db   = db_connect();
        $user = $db->table('users')->where('id', $userId)->get()->getRowArray();
        $version = (int) $user['session_version'] + 1;
        $db->table('users')->where('id', $userId)->update([
            'password_hash' => self::hashPassword($newPassword), 'must_change_password' => 0,
            'password_changed_at' => date('Y-m-d H:i:s'), 'session_version' => $version, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ((int) session('user_id') === $userId) {
            session()->set('session_version', $version);
        }
        Services::audit()->log('auth.password_changed', 'user', $userId);
    }
}
