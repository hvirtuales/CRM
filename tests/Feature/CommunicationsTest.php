<?php

namespace Tests\Feature;

use App\Libraries\DunningService;
use App\Libraries\NotificationService;
use App\Libraries\SignupService;
use Config\Services;
use Tests\Support\HvTestCase;

/**
 * Bloque 2: bandeja de salida, acuse inmediato al lead, cotizaciones por correo, cobranza escalonada
 * y autorregistro con verificación de correo.
 */
final class CommunicationsTest extends HvTestCase
{
    /** Cuerpo del último correo entregado (capturado del evento «email» de MockEmail). */
    private string $lastMailBody = '';

    protected function setUp(): void
    {
        parent::setUp();
        \CodeIgniter\Events\Events::on('email', function (array $archive): void {
            $this->lastMailBody = (string) ($archive['body'] ?? '');
        });
    }

    private function outbox(array $where): array
    {
        $b = $this->db()->table('outbox');
        foreach ($where as $k => $v) {
            $b->where($k, $v);
        }

        return $b->orderBy('id')->get()->getResultArray();
    }

    // ------------------------------------------------------------ bandeja de salida

    public function testOutboxIsIdempotentAndWaitsWhenEmailIsNotConfigured(): void
    {
        $n   = new NotificationService();
        $key = 'test:' . uniqid();
        $id  = $n->queue($key, 'test', 'cliente@x.test', 'es', 'Signup.mail.subject', [], 'emails/message', ['greeting' => '<b>Hola</b>', 'lines' => []]);
        $this->assertNotNull($id);
        $this->assertNull($n->queue($key, 'test', 'cliente@x.test', 'es', 'Signup.mail.subject', [], 'emails/message', ['greeting' => 'x']), 'duplicado');
        $row = $this->db()->table('outbox')->where('id', $id)->get()->getRow();
        $this->assertSame('sent', $row->status, 'con correo configurado sale de inmediato');
        $this->assertStringContainsString('&lt;b&gt;Hola&lt;/b&gt;', $row->body_html, 'datos escapados en el correo');

        $cfg = config('Email');
        $prev = $cfg->fromEmail;
        $cfg->fromEmail = '';
        try {
            $id2 = $n->queue('test:' . uniqid(), 'test', 'otro@x.test', 'en', 'Signup.mail.subject', [], 'emails/message', ['greeting' => 'Hi']);
            $this->assertSame('queued', $this->db()->table('outbox')->where('id', $id2)->get()->getRow()->status);
            $this->assertSame('Verify your email to activate HV CRM', $this->db()->table('outbox')->where('id', $id2)->get()->getRow()->subject, 'idioma del destinatario');
        } finally {
            $cfg->fromEmail = $prev;
        }
        $this->assertGreaterThanOrEqual(1, $n->deliverDue()['sent']);
        $this->assertNull($n->queue('x:' . uniqid(), 'test', 'no-es-correo', 'es', 'Signup.mail.subject', [], 'emails/message', []));
    }

    public function testOnlyPlatformCanSeeTheOutbox(): void
    {
        $this->actingAs('superadmin@hv.test', 'platform')->get('plataforma/correos')->assertStatus(200);
        $this->actingAs('distribuidor@hv.test', 'tenant')->get('plataforma/correos')->assertStatus(403);
    }

    // ------------------------------------------------------------ acuse al lead

    public function testLeadReceivesOneAcknowledgementPerDay(): void
    {
        $form  = $this->db()->table('cmp_form_forms')->where('status', 'published')->orderBy('id')->get()->getRowArray();
        $email = 'lead' . uniqid() . '@cliente.test';
        $post  = fn () => $this->post('form-publico/' . $form['public_id'], [
            '_hv_token' => service('formSubmissions')->issueToken($form['public_id'], time() - 10),
            'f' => ['nombre' => '<script>x</script>Lucía', 'negocio' => 'Tienda', 'correo' => $email, 'celular' => '300' . random_int(1000000, 9999999)],
            'consent' => '1',
        ]);
        $post()->assertStatus(200);
        $post()->assertStatus(200);
        $acks = $this->outbox(['kind' => 'lead_ack', 'to_email' => $email]);
        $this->assertCount(1, $acks, 'anti-abuso: un acuse por día y destinatario');
        $this->assertStringContainsString('Herramientas Virtuales CO', $acks[0]['subject']);
        $this->assertStringNotContainsString('<script>', $acks[0]['body_html']);

        $this->db()->table('cmp_form_forms')->where('id', $form['id'])->update(['send_ack' => 0]);
        try {
            $other = 'noack' . uniqid() . '@cliente.test';
            $this->post('form-publico/' . $form['public_id'], ['_hv_token' => service('formSubmissions')->issueToken($form['public_id'], time() - 10),
                'f' => ['nombre' => 'B', 'negocio' => 'T', 'correo' => $other], 'consent' => '1']);
            $this->assertCount(0, $this->outbox(['to_email' => $other]));
        } finally {
            $this->db()->table('cmp_form_forms')->where('id', $form['id'])->update(['send_ack' => 1]);
        }
    }

    // ------------------------------------------------------------ cotizaciones

    public function testSentQuoteEmailsClientWithPublicLinkAndReminds(): void
    {
        $hv  = $this->companyId('herramientas-virtuales');
        $opp = $this->db()->table('cmp_crm_opportunities')->where('company_id', $hv)->where('owner_user_id', $this->userId('gerente@hv.test'))->where('deleted_at', null)->get()->getRow();
        $email = $this->db()->table('cmp_crm_contacts')->where('id', $opp->contact_id)->get()->getRow()->email;
        $q = $this->asContext('gerente@hv.test', 'company', $hv, function () use ($hv, $opp) {
            $r = service('quoteQuotes')->save($hv, ['opportunity_id' => (string) $opp->id, 'valid_until' => date('Y-m-d', time() + 2 * 86400),
                'lines' => [['description' => 'Plan', 'quantity' => '1', 'unit_price' => '100000', 'tax_percent' => '19']]], $this->userId('gerente@hv.test'));
            $t = service('quoteQuotes')->transition($hv, (int) $r->data['id'], 'sent', $this->userId('gerente@hv.test'));
            $this->assertTrue($t->data['emailed']);

            return $this->db()->table('cmp_quote_quotes')->where('id', $r->data['id'])->get()->getRowArray();
        });
        $sent = $this->outbox(['kind' => 'quote_sent', 'to_email' => strtolower($email)]);
        $this->assertNotEmpty($sent);
        $this->assertStringContainsString('quote-publico/' . $q['public_id'], end($sent)['body_html']);

        service('quoteQuotes')->runDaily(time());
        $this->assertNotEmpty($this->outbox(['kind' => 'quote_reminder', 'to_email' => strtolower($email)]));
    }

    // ------------------------------------------------------------ cobranza

    public function testDunningEscalatesToBusinessAndDistributorOncePerStage(): void
    {
        $a1  = $this->companyId('comercializadora-san-victorino');
        $sub = Services::subscriptions()->currentForCompany($a1);
        $this->db()->table('subscriptions')->where('id', $sub['id'])->update([
            'status' => 'past_due', 'current_period_end' => date('Y-m-d H:i:s', time() - 86400), 'grace_days' => 5,
        ]);
        try {
            $stats = (new DunningService())->run();
            $this->assertGreaterThanOrEqual(1, $stats['past_due']);
            $mails = $this->outbox(['kind' => 'dunning_past_due']);
            $to    = array_column($mails, 'to_email');
            $this->assertContains('negocio@hv.test', $to, 'propietario del negocio');
            $this->assertContains('distribuidor@hv.test', $to, 'distribuidor que lo vendió');

            $before = count($this->outbox(['kind' => 'dunning_past_due']));
            (new DunningService())->run();
            $this->assertSame($before, count($this->outbox(['kind' => 'dunning_past_due'])), 'una vez por etapa');

            // A un día de agotar la gracia: último aviso
            (new DunningService())->run(time() + 4 * 86400 + 3600);
            $this->assertNotEmpty($this->outbox(['kind' => 'dunning_final']));
            // La empresa «casa» no se cobra a sí misma
            $this->assertCount(0, array_filter($this->outbox([]), fn ($m) => (int) $m['company_id'] === $this->companyId('herramientas-virtuales') && str_starts_with($m['kind'], 'dunning')));
        } finally {
            $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => $sub['status'], 'current_period_end' => $sub['current_period_end']]);
        }
    }

    // ------------------------------------------------------------ autorregistro

    public function testSelfSignupRequiresEmailVerificationAndGrantsOneTrial(): void
    {
        $this->get('registro')->assertStatus(200);
        $email = 'nuevo' . uniqid() . '@negocio.test';
        $plan  = (int) $this->db()->table('plans')->where('code', 'profesional')->get()->getRow()->id;
        $data  = ['company_name' => 'Panadería ' . uniqid(), 'owner_name' => 'Rosa', 'email' => $email, 'plan_id' => (string) $plan, 'accept_terms' => '1', 'csrf_hv' => self::CSRF];

        $this->post('registro', $data)->assertStatus(200);
        $this->assertSame(1, $this->rows('signups', ['email' => $email, 'status' => 'pending']));
        $this->assertSame(0, $this->rows('companies', ['email' => $email]), 'no se crea nada antes de verificar');
        $mail = $this->outbox(['kind' => 'signup_verify', 'to_email' => $email]);
        $this->assertCount(1, $mail);
        $this->assertSame('[redactado tras el envío]', $mail[0]['body_html'], 'el enlace no queda guardado tras enviarse');
        preg_match('#registro/verificar/([0-9a-f]{48})#', $this->lastMailBody, $m);

        $r = $this->get('registro/verificar/' . $m[1]);
        $r->assertRedirect();
        $this->assertStringContainsString('invitacion/', $r->response()->getHeaderLine('Location'));
        $company = $this->db()->table('companies')->where('email', $email)->get()->getRow();
        $this->assertNotNull($company);
        $this->assertNull($company->tenant_id, 'negocio directo');
        $this->assertSame('trial', Services::subscriptions()->currentForCompany((int) $company->id)['status']);
        $this->get('registro/verificar/' . $m[1])->assertStatus(404); // un solo uso

        // El prospecto llega al CRM de Herramientas Virtuales
        $hv = $this->companyId('herramientas-virtuales');
        $this->assertSame(1, $this->rows('cmp_crm_contacts', ['company_id' => $hv, 'email' => $email]));

        // Una prueba por correo
        $this->post('registro', $data);
        preg_match('#registro/verificar/([0-9a-f]{48})#', $this->lastMailBody, $m2);
        $this->get('registro/verificar/' . $m2[1])->assertStatus(409);
        $this->assertSame(1, $this->rows('companies', ['email' => $email]));
    }

    public function testSignupVerificationMailsAreCappedPerRecipient(): void
    {
        $email = 'victima' . uniqid() . '@x.test';
        $plan  = (int) $this->db()->table('plans')->where('code', 'basico')->get()->getRow()->id;
        for ($i = 0; $i < 5; $i++) {
            (new SignupService())->start(['company_name' => 'Spam ' . $i, 'owner_name' => 'S', 'email' => $email, 'plan_id' => $plan, 'accept_terms' => 1], '10.0.0.' . $i);
        }
        $this->assertCount(3, $this->outbox(['kind' => 'signup_verify', 'to_email' => $email]));
    }

    public function testSignupRejectsAbuseAndNonTrialPlans(): void
    {
        $email = 'bot' . uniqid() . '@x.test';
        $this->post('registro', ['company_name' => 'Bot', 'owner_name' => 'Bot', 'email' => $email, 'plan_id' => '1', 'accept_terms' => '1', 'website_url' => 'spam', 'csrf_hv' => self::CSRF])->assertStatus(200);
        $this->assertSame(0, $this->rows('signups', ['email' => $email]), 'honeypot');

        $paid = (int) $this->db()->table('plans')->where('code', 'empresarial')->get()->getRow()->id; // sin días de prueba
        $r = (new SignupService())->start(['company_name' => 'X', 'owner_name' => 'X', 'email' => 'x' . uniqid() . '@x.test', 'plan_id' => $paid, 'accept_terms' => 1], '1.1.1.1');
        $this->assertFalse($r->ok);
        $this->assertArrayHasKey('plan_id', $r->errors);
        $r = (new SignupService())->start(['company_name' => 'X', 'owner_name' => 'X', 'email' => 'x' . uniqid() . '@x.test', 'plan_id' => (int) $this->db()->table('plans')->where('code', 'basico')->get()->getRow()->id], '1.1.1.1');
        $this->assertArrayHasKey('accept_terms', $r->errors);

        config('Commercial')->selfSignup = false;
        try {
            $this->get('registro')->assertStatus(404);
        } finally {
            config('Commercial')->selfSignup = true;
        }
    }
}
