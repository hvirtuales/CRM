<?php

namespace Tests\Security;

use App\Libraries\Totp;
use Config\Services;
use Tests\Support\HvTestCase;

/**
 * Regresiones de los hallazgos de la revisión de seguridad independiente (docs/security).
 */
final class ReviewRegressionTest extends HvTestCase
{
    private function company(string $slug): int
    {
        return $this->companyId($slug);
    }

    // ---------------------------------------------------------------- H1 invitaciones

    public function testInvitingNeverCreatesAccountsOrRevealsPasswords(): void
    {
        $hv    = $this->company('herramientas-virtuales');
        $email = 'nuevo' . uniqid() . '@partner.test';
        $r = $this->actingAs('gerente@hv.test', 'company', $hv)->postForm('negocio/usuarios', [
            'name' => 'Nuevo', 'email' => $email, 'role_id' => Services::rbac()->systemRoleId('sales_agent'), 'password' => 'Atacante elige clave 2026',
        ]);
        $r->assertRedirect();
        $this->assertSame(0, $this->rows('users', ['email' => $email]), 'no se crea la cuenta al invitar');
        $this->assertSame(1, $this->rows('invitations', ['email' => $email, 'accepted_at' => null]));
        $this->assertNull(session('temp_password'));
    }

    public function testSameResponseForExistingAndNewEmails(): void
    {
        $hv   = $this->company('herramientas-virtuales');
        $role = Services::rbac()->systemRoleId('viewer');
        $a = $this->asContext('gerente@hv.test', 'company', $hv, fn () => Services::users()->addMember('company', null, $hv, ['name' => 'X', 'email' => 'andes@hv.test', 'role_id' => $role], 1));
        $b = $this->asContext('gerente@hv.test', 'company', $hv, fn () => Services::users()->addMember('company', null, $hv, ['name' => 'Y', 'email' => 'nadie' . uniqid() . '@x.test', 'role_id' => $role], 1));
        $this->assertTrue($a->ok && $b->ok);
        $this->assertSame(array_keys($a->data), array_keys($b->data));
        $this->assertSame(str_replace('andes@hv.test', 'E', (string) $a->message), preg_replace('/nadie\w+@x\.test/', 'E', (string) $b->message));
    }

    public function testInvitationAcceptanceRequiresOwnershipAndIsSingleUse(): void
    {
        $hv = $this->company('herramientas-virtuales');
        $inv = Services::invitations();

        // Cuenta nueva: el invitado define su contraseña
        $email = 'invitada' . uniqid() . '@x.test';
        $this->asContext('gerente@hv.test', 'company', $hv, fn () => $inv->invite('company', null, $hv, 'Invitada', $email, Services::rbac()->systemRoleId('viewer'), 1));
        $token = basename((string) $inv->lastLinkForDevelopment);
        $this->assertFalse($inv->accept($token, null, 'corta', 'corta')->ok, 'política de contraseñas');
        $this->assertTrue($inv->accept($token, null, 'Frase propia muy segura 1', 'Frase propia muy segura 1')->ok);
        $this->assertFalse($inv->accept($token, null, 'Frase propia muy segura 1', 'Frase propia muy segura 1')->ok, 'un solo uso');
        $this->assertTrue(password_verify('Frase propia muy segura 1', (string) $this->db()->table('users')->where('email', $email)->get()->getRow()->password_hash));

        // Cuenta existente: solo su titular autenticado puede aceptar
        $this->asContext('gerente@hv.test', 'company', $hv, fn () => $inv->invite('company', null, $hv, 'Andes', 'andes@hv.test', Services::rbac()->systemRoleId('viewer'), 1));
        $token2 = basename((string) $inv->lastLinkForDevelopment);
        $this->assertSame(403, $inv->accept($token2, null, 'X', 'X')->status, 'sin sesión no se vincula');
        $this->assertSame(403, $inv->accept($token2, $this->userId('negocio@hv.test'), null, null)->status, 'otra cuenta no puede aceptarla');
        $this->assertTrue($inv->accept($token2, $this->userId('andes@hv.test'), null, null)->ok);

        // Vencida
        $this->asContext('gerente@hv.test', 'company', $hv, fn () => $inv->invite('company', null, $hv, 'V', 'vence' . uniqid() . '@x.test', Services::rbac()->systemRoleId('viewer'), 1));
        $token3 = basename((string) $inv->lastLinkForDevelopment);
        $this->db()->table('invitations')->where('token_hash', hash('sha256', $token3))->update(['expires_at' => date('Y-m-d H:i:s', time() - 1)]);
        $this->assertNull($inv->findValid($token3));
        $this->get('invitacion/' . $token3)->assertStatus(404);
    }

    // ---------------------------------------------------------------- M2 servicio gratuito

    public function testDistributorMustSellAPlanAndCannotResetTrialsOrLiftSuspension(): void
    {
        $as = fn () => $this->actingAs('distribuidor@hv.test', 'tenant');
        $name = 'Sin plan ' . uniqid();
        $as()->postForm('distribuidor/negocios', ['name' => $name, 'timezone' => 'America/Bogota', 'currency' => 'COP', 'locale' => 'es', 'owner_name' => 'D', 'owner_email' => uniqid() . '@d.test']);
        $this->assertSame(0, $this->rows('companies', ['name' => $name]));

        $a2   = $this->company('inmobiliaria-andes'); // en prueba
        $subs = Services::subscriptions();
        $before = $subs->currentForCompany($a2);
        $prof = (int) $this->db()->table('plans')->where('code', 'profesional')->get()->getRow()->id;
        $as()->postForm('distribuidor/negocios/' . $a2 . '/plan', ['plan_id' => $prof]);
        $after = $subs->currentForCompany($a2);
        $this->assertSame($before['current_period_end'], $after['current_period_end'], 'cambiar de plan no reinicia la prueba');
        $this->assertSame('trial', $after['status']);

        $this->db()->table('subscriptions')->where('id', $after['id'])->update(['status' => 'suspended']);
        $emp = (int) $this->db()->table('plans')->where('code', 'empresarial')->get()->getRow()->id;
        $as()->postForm('distribuidor/negocios/' . $a2 . '/plan', ['plan_id' => $emp]);
        $this->assertSame('suspended', $subs->currentForCompany($a2)['status'], 'no levanta la suspensión');
        $this->db()->table('subscriptions')->where('id', $after['id'])->update(['status' => 'trial']);
    }

    public function testFirstPaidSubscriptionStartsPendingPayment(): void
    {
        $r = $this->asContext('superadmin@hv.test', 'platform', null, fn () => Services::provisioning()->createCompany([
            'name' => 'Directo ' . uniqid(), 'owner_name' => 'O', 'owner_email' => uniqid() . '@o.test',
            'plan_id' => (int) $this->db()->table('plans')->where('code', 'empresarial')->get()->getRow()->id,
        ], null, $this->userId('superadmin@hv.test')));
        $this->assertTrue($r->ok);
        $this->assertSame('past_due', Services::subscriptions()->currentForCompany((int) $r->data['id'])['status']);
    }

    // ---------------------------------------------------------------- M4 archivada

    public function testArchivedSubscriptionIsReadOnlyAndLosesPlan(): void
    {
        $a1  = $this->company('comercializadora-san-victorino');
        $sub = Services::subscriptions()->currentForCompany($a1);
        $planId = $this->db()->table('companies')->where('id', $a1)->get()->getRow()->plan_id;
        $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => 'cancelled']);
        try {
            $this->assertTrue(Services::subscriptions()->transition((int) $sub['id'], 'archived', null)->ok);
            $this->assertNull($this->db()->table('companies')->where('id', $a1)->get()->getRow()->plan_id);
            $this->actingAs('negocio@hv.test', 'company', $a1)->postForm('crm/contactos', ['first_name' => 'A', 'email' => 'arch@x.test'])->assertStatus(403);
        } finally {
            $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => $sub['status']]);
            $this->db()->table('companies')->where('id', $a1)->update(['plan_id' => $planId]);
        }
    }

    // ---------------------------------------------------------------- L6 superficie pública

    public function testPublicFormHonorsKillSwitchAndBillingState(): void
    {
        $form = $this->db()->table('cmp_form_forms')->where('status', 'published')->orderBy('id')->get()->getRowArray();
        $this->get('form-publico/' . $form['public_id'])->assertStatus(200);
        Services::components()->setStatus('form', false);
        try {
            $this->get('form-publico/' . $form['public_id'])->assertStatus(404);
        } finally {
            Services::components()->setStatus('form', true);
        }
        $sub = Services::subscriptions()->currentForCompany((int) $form['company_id']);
        $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => 'suspended']);
        try {
            $this->get('form-publico/' . $form['public_id'])->assertStatus(404);
        } finally {
            $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => $sub['status']]);
        }
    }

    // ---------------------------------------------------------------- L7 cupo al reactivar

    public function testReactivatingMembersRespectsUserQuota(): void
    {
        $hv   = $this->company('herramientas-virtuales');
        $plan = (int) $this->db()->table('companies')->where('id', $hv)->get()->getRow()->plan_id;
        $agent = $this->membershipId('asesor@hv.test', 'company', $hv);
        $this->db()->table('memberships')->where('id', $agent)->update(['status' => 'suspended']);
        $this->db()->table('plans')->where('id', $plan)->update(['max_users' => 1]); // solo cabe la gerente
        try {
            $r = $this->asContext('gerente@hv.test', 'company', $hv, fn () => Services::users()->updateMember($agent, 'company', null, $hv, ['status' => 'active'], $this->userId('gerente@hv.test')));
            $this->assertFalse($r->ok);
        } finally {
            $this->db()->table('plans')->where('id', $plan)->update(['max_users' => 0]);
            $this->db()->table('memberships')->where('id', $agent)->update(['status' => 'active']);
        }
    }

    // ---------------------------------------------------------------- L8 MFA

    public function testMfaCodesCannotBeReplayedAndFailuresLockTheSession(): void
    {
        $uid    = $this->userId('soporte@hv.test');
        $secret = Totp::generateSecret();
        $this->db()->table('users')->where('id', $uid)->update(['mfa_enabled' => 1, 'mfa_secret' => Services::auth()->encryptSecret($secret), 'mfa_last_step' => null, 'mfa_failures' => 0]);
        $user = $this->db()->table('users')->where('id', $uid)->get()->getRowArray();
        $code = Totp::code($secret);
        $this->assertTrue(Services::auth()->verifyMfa($user, $code));
        $this->assertFalse(Services::auth()->verifyMfa($user, $code), 'el mismo código no se reutiliza');

        $version = (int) $this->db()->table('users')->where('id', $uid)->get()->getRow()->session_version;
        for ($i = 0; $i < 10; $i++) {
            Services::auth()->verifyMfa($user, '000000');
        }
        $this->assertSame($version + 1, (int) $this->db()->table('users')->where('id', $uid)->get()->getRow()->session_version, 'cierra las sesiones');
    }

    // ---------------------------------------------------------------- Info

    public function testApiTokensOnlyCarryApiAbilitiesAndDieWithPasswordChange(): void
    {
        $hv = $this->company('herramientas-virtuales');
        $r  = $this->asContext('gerente@hv.test', 'company', $hv, fn ($ctx) => Services::apiTokens()->create($ctx, 't', ['company.users.edit', 'crm.manage', 'crm.view'], 30));
        $this->assertSame(['crm.view'], json_decode((string) $this->db()->table('api_tokens')->where('id', $r->data['id'])->get()->getRow()->abilities, true));

        $this->db()->table('api_tokens')->where('id', $r->data['id'])->update(['created_at' => date('Y-m-d H:i:s', time() - 60)]);
        $this->withHeaders(['Authorization' => 'Bearer ' . $r->data['token']])->get('api/v1/crm/contacts')->assertStatus(200);
        $this->db()->table('users')->where('id', $this->userId('gerente@hv.test'))->update(['password_changed_at' => date('Y-m-d H:i:s')]);
        $this->withHeaders(['Authorization' => 'Bearer ' . $r->data['token']])->get('api/v1/crm/contacts')->assertStatus(401);
    }

    public function testManifestRoleGrantsCannotGrantCorePermissions(): void
    {
        $errors = Services::components()->validate([
            'code' => 'evil', 'name' => 'E', 'version' => '1.0.0', 'table_prefix' => 'cmp_evil_',
            'permissions' => ['evil.view' => 'v'], 'role_grants' => ['sales_agent' => ['company.users.edit']],
        ], 'Evil');
        $this->assertNotEmpty($errors);
    }

    public function testQuoteRespectsOpportunityOwnership(): void
    {
        $hv  = $this->company('herramientas-virtuales');
        $opp = $this->db()->table('cmp_crm_opportunities')->where('company_id', $hv)->where('owner_user_id', $this->userId('gerente@hv.test'))->get()->getRow();
        $r = $this->asContext('asesor@hv.test', 'company', $hv, fn () => service('quoteQuotes')->save($hv, [
            'opportunity_id' => (string) $opp->id, 'lines' => [['description' => 'x', 'quantity' => '1', 'unit_price' => '1', 'tax_percent' => '0']],
        ], $this->userId('asesor@hv.test')));
        $this->assertSame(403, $r->status);
    }

    public function testPaymentReferenceCannotBeReusedAcrossSubscriptions(): void
    {
        $s1 = Services::subscriptions()->currentForCompany($this->company('comercializadora-san-victorino'));
        $s2 = Services::subscriptions()->currentForCompany($this->company('herramientas-virtuales'));
        $ref = 'REF-' . uniqid();
        $this->assertTrue(Services::subscriptions()->registerPayment((int) $s1['id'], '1000', 'transfer', $ref, null)->ok);
        $r = Services::subscriptions()->registerPayment((int) $s2['id'], '1000', 'transfer', $ref, null);
        $this->assertFalse($r->ok);
        $this->assertSame(409, $r->status);
    }

    public function testLoginTimingDoesNotDependOnAccountExistence(): void
    {
        $ref = new \ReflectionClass(\App\Libraries\AuthService::class);
        $this->assertTrue($ref->hasConstant('DUMMY_HASH'), 'hash ficticio precalculado (sin generar Argon2 por petición)');
        $t = static function (string $email): float {
            $s = microtime(true);
            Services::auth()->attempt($email, 'clave-equivocada-xyz', '10.9.9.' . random_int(1, 200));

            return microtime(true) - $s;
        };
        $known = $unknown = 0.0;
        for ($i = 0; $i < 3; $i++) {
            $known   += $t('gerente@hv.test');
            $unknown += $t('nadie' . $i . '@x.test');
        }
        $this->assertLessThan(1.6, max($known, $unknown) / max(min($known, $unknown), 0.001), 'tiempos comparables');
        $this->db()->table('login_attempts')->where('success', 0)->delete();
    }
}
