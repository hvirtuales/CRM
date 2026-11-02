<?php

namespace Tests\Security;

use App\Libraries\Totp;
use Config\Services;
use Tests\Support\HvTestCase;

/**
 * OWASP ASVS V2/V3: autenticación, bloqueo por intentos, MFA, sesiones y CSRF.
 */
final class AuthenticationTest extends HvTestCase
{
    private const PWD = 'Demo-HV-CRM-2026!';

    public function testProtectedRoutesRequireLogin(): void
    {
        foreach (['negocio', 'crm/contactos', 'plataforma', 'distribuidor', 'formularios', 'cotizaciones'] as $path) {
            $r = $this->get($path);
            $r->assertRedirectTo(site_url('ingresar'));
        }
    }

    public function testInvalidCredentialsDoNotRevealWhetherEmailExists(): void
    {
        $a = $this->post('ingresar', ['email' => 'gerente@hv.test', 'password' => 'incorrecta-123456', 'csrf_hv' => self::CSRF]);
        $b = $this->post('ingresar', ['email' => 'noexiste@hv.test', 'password' => 'incorrecta-123456', 'csrf_hv' => self::CSRF]);
        $this->assertSame(session('error'), lang('Auth.invalidCredentials'));
        $a->assertRedirect();
        $b->assertRedirect();
    }

    public function testAccountLocksAfterFiveFailures(): void
    {
        $email = 'andes@hv.test';
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['csrf_hv' => self::CSRF])->post('ingresar', ['email' => $email, 'password' => 'mala-clave-' . $i . 'xxxx', 'csrf_hv' => self::CSRF]);
        }
        $result = Services::auth()->attempt($email, self::PWD, '10.0.0.9');
        $this->assertFalse($result->ok, 'incluso con la clave correcta queda bloqueada');
        $this->assertSame(429, $result->status);
        $this->db()->table('login_attempts')->where('email_hash', hash('sha256', $email))->delete();
    }

    public function testSuccessfulLoginEntersSingleContextDirectly(): void
    {
        $r = $this->withSession(['csrf_hv' => self::CSRF])->post('ingresar', ['email' => 'andes@hv.test', 'password' => self::PWD, 'csrf_hv' => self::CSRF]);
        $r->assertRedirectTo(site_url('/'));
        $this->assertSame($this->membershipId('andes@hv.test', 'company'), (int) session('membership_id'));
        $this->assertTrue((bool) session('mfa_passed'));
    }

    public function testPlatformUsersMustEnrollMfa(): void
    {
        $uid = $this->userId('soporte@hv.test');
        $this->db()->table('users')->where('id', $uid)->update(['mfa_enabled' => 0, 'mfa_secret' => null]);
        $session = $this->sessionFor('soporte@hv.test', 'platform');
        $session['mfa_passed'] = false;
        $this->withSession($session)->get('plataforma')->assertRedirectTo(site_url('perfil/mfa'));
    }

    public function testMfaCodeIsRequiredWhenEnrolled(): void
    {
        $uid    = $this->userId('soporte@hv.test');
        $secret = Totp::generateSecret();
        $this->db()->table('users')->where('id', $uid)->update(['mfa_enabled' => 1, 'mfa_secret' => Services::auth()->encryptSecret($secret)]);
        $session = $this->sessionFor('soporte@hv.test', 'platform');
        $session['mfa_passed'] = false;

        $this->withSession($session)->get('plataforma')->assertRedirectTo(site_url('mfa'));
        $this->withSession($session)->post('mfa', ['code' => '000000', 'csrf_hv' => self::CSRF]);
        $this->assertFalse((bool) session('mfa_passed'));
        $this->withSession($session)->post('mfa', ['code' => Totp::code($secret), 'csrf_hv' => self::CSRF]);
        $this->assertTrue((bool) session('mfa_passed'));
        // El secreto se guarda cifrado, nunca en claro
        $stored = $this->db()->table('users')->where('id', $uid)->get()->getRow()->mfa_secret;
        $this->assertStringNotContainsString($secret, (string) $stored);
    }

    public function testPasswordChangeInvalidatesOtherSessions(): void
    {
        $session = $this->sessionFor('negocio@hv.test', 'company', $this->companyId('comercializadora-san-victorino'));
        Services::auth()->changePassword($this->userId('negocio@hv.test'), 'Nueva frase muy segura 2026');
        // La sesión anterior (session_version vieja) ya no sirve
        $this->withSession($session)->get('negocio')->assertRedirectTo(site_url('ingresar'));
    }

    public function testSuspendedUserLosesAccessImmediately(): void
    {
        $uid     = $this->userId('asesor@hv.test');
        $session = $this->sessionFor('asesor@hv.test', 'company', $this->companyId('herramientas-virtuales'));
        $this->db()->table('users')->where('id', $uid)->update(['status' => 'suspended']);
        $this->withSession($session)->get('crm/oportunidades')->assertRedirectTo(site_url('ingresar'));
        $this->db()->table('users')->where('id', $uid)->update(['status' => 'active']);
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->actingAs('gerente@hv.test', 'company', $this->companyId('herramientas-virtuales'))
            ->post('crm/contactos', ['first_name' => 'Sin token', 'email' => 'sintoken@x.test']);
    }

    public function testLanguageSwitchDoesNotOpenRedirect(): void
    {
        $r = $this->withHeaders(['Referer' => 'https://evil.example/phish'])->get('idioma/en');
        $location = $r->response()->getHeaderLine('Location');
        $this->assertStringStartsWith(rtrim(base_url(), '/'), $location);
    }

    public function testSecurityHeadersArePresent(): void
    {
        $r = $this->get('ingresar');
        $h = $r->response();
        $this->assertStringContainsString("script-src 'self'", $h->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $h->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('DENY', $h->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $h->getHeaderLine('X-Content-Type-Options'));
    }
}
