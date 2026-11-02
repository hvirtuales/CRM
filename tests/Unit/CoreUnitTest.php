<?php

namespace Tests\Unit;

use App\Libraries\AuthService;
use App\Libraries\BrandService;
use App\Libraries\Ids;
use App\Libraries\Totp;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pruebas unitarias de primitivas de seguridad (sin base de datos).
 */
final class CoreUnitTest extends CIUnitTestCase
{
    public function testTotpMatchesRfc6238Vector(): void
    {
        // RFC 6238, apéndice B: secreto ASCII "12345678901234567890", T=59 ⇒ 94287082 (6 dígitos: 287082)
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertTrue(Totp::verify($secret, '287082', 59));
        $this->assertTrue(Totp::verify($secret, '287 082', 59 + 30), 'tolera ±1 paso y espacios');
        $this->assertFalse(Totp::verify($secret, '287082', 59 + 120), 'código vencido');
        $this->assertFalse(Totp::verify($secret, 'abcdef', 59));
    }

    public function testTotpSecretIsRandomBase32(): void
    {
        $a = Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $a);
        $this->assertNotSame($a, Totp::generateSecret());
    }

    public function testPasswordPolicy(): void
    {
        $auth = new AuthService();
        $this->assertNotNull($auth->passwordProblem('corta1!', 'ana@x.co'), 'longitud mínima');
        $this->assertNotNull($auth->passwordProblem('aaaaaaaaaaaaaaaa', 'ana@x.co'), 'repetitiva');
        $this->assertNotNull($auth->passwordProblem('mariagomez-2026-segura', 'mariagomez@x.co'), 'contiene el correo');
        $this->assertNotNull($auth->passwordProblem(str_repeat('ab1', 50), 'ana@x.co'), 'demasiado larga');
        $this->assertNull($auth->passwordProblem('Faro azul sobre el puente 7', 'ana@x.co'));
    }

    public function testPasswordHashIsArgon2idWhenAvailable(): void
    {
        $hash = AuthService::hashPassword('Faro azul sobre el puente 7');
        if (defined('PASSWORD_ARGON2ID')) {
            $this->assertStringStartsWith('$argon2id$', $hash);
        }
        $this->assertTrue(password_verify('Faro azul sobre el puente 7', $hash));
    }

    public function testBrandRejectsInvalidColorsAndKeepsContrast(): void
    {
        $this->assertSame(BrandService::DEFAULT, BrandService::variables('red;}body{display:none')['brand'], 'inyección CSS descartada');
        $this->assertSame(BrandService::DEFAULT, BrandService::variables('#12')['brand']);
        foreach (['#D80023', '#0E7490', '#FFEE00', '#111111'] as $hex) {
            $v = BrandService::variables($hex);
            $this->assertGreaterThanOrEqual(4.5, BrandService::contrast($v['brand'], $v['brandInk']), "texto sobre {$hex}");
            $this->assertGreaterThanOrEqual(4.5, BrandService::contrast($v['brandDark'], '#0F0F11'), "acento oscuro de {$hex}");
        }
    }

    public function testPublicIdsAreOpaque(): void
    {
        $ids = array_map(static fn () => Ids::publicId(), range(1, 200));
        $this->assertCount(200, array_unique($ids));
        foreach ($ids as $id) {
            $this->assertTrue(Ids::isPublicId($id));
        }
        $this->assertFalse(Ids::isPublicId('../../etc/passwd'));
    }
}
