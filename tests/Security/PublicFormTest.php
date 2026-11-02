<?php

namespace Tests\Security;

use Tests\Support\HvTestCase;

/**
 * Superficie pública sin sesión: formularios de captación y enlace de cotización.
 */
final class PublicFormTest extends HvTestCase
{
    private array $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->db()->table('cmp_form_forms')->where('status', 'published')->orderBy('id')->get()->getRowArray();
    }

    private function token(int $age = 10): string
    {
        return service('formSubmissions')->issueToken($this->form['public_id'], time() - $age);
    }

    private function payload(string $email, array $extra = []): array
    {
        return $extra + [
            '_hv_token' => $this->token(),
            'f' => ['nombre' => 'Lucía', 'negocio' => 'Tienda Lucía', 'correo' => $email, 'celular' => '300' . random_int(1000000, 9999999), 'tipo' => 'Local'],
            'consent' => '1',
        ];
    }

    public function testValidSubmissionCreatesContactAndOpportunityInOwnerBusinessOnly(): void
    {
        $email = 'lead' . uniqid() . '@cliente.test';
        $this->post('form-publico/' . $this->form['public_id'], $this->payload($email))->assertStatus(200);

        $contact = $this->db()->table('cmp_crm_contacts')->where('email', $email)->get()->getRowArray();
        $this->assertNotNull($contact);
        $this->assertSame((int) $this->form['company_id'], (int) $contact['company_id']);
        $this->assertSame(1, (int) $contact['consent_marketing']);
        $this->assertStringStartsWith('form:' . $this->form['public_id'], (string) $contact['consent_source']);
        $this->assertSame(1, $this->rows('cmp_crm_opportunities', ['contact_id' => $contact['id'], 'company_id' => $this->form['company_id']]));
        $this->assertSame(1, $this->rows('cmp_form_submissions', ['contact_id' => $contact['id']]));
        // la IP no se guarda en claro
        $sub = $this->db()->table('cmp_form_submissions')->where('contact_id', $contact['id'])->get()->getRow();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $sub->ip_hash);
    }

    public function testReplayedTokenDoesNotDuplicate(): void
    {
        $email   = 'replay' . uniqid() . '@cliente.test';
        $payload = $this->payload($email);
        $first = $this->post('form-publico/' . $this->form['public_id'], $payload);
        $first->assertStatus(200);
        $this->post('form-publico/' . $this->form['public_id'], $payload);
        $cid = (int) $this->db()->table('cmp_crm_contacts')->where('email', $email)->get()->getRow()->id;
        $this->assertSame(1, $this->rows('cmp_form_submissions', ['contact_id' => $cid]));
        $this->assertSame(1, $this->rows('cmp_crm_opportunities', ['contact_id' => $cid]));
    }

    public function testHoneypotForgedTokenTooFastAndMissingConsentAreRejected(): void
    {
        $url = 'form-publico/' . $this->form['public_id'];
        $e1 = 'bot' . uniqid() . '@x.test';
        $this->post($url, $this->payload($e1, ['website_url' => 'http://spam.example']))->assertStatus(200); // éxito simulado
        $e2 = 'forged' . uniqid() . '@x.test';
        $this->post($url, $this->payload($e2, ['_hv_token' => time() . '.abcdef.' . str_repeat('0', 64)]))->assertStatus(400);
        $e3 = 'fast' . uniqid() . '@x.test';
        $this->post($url, $this->payload($e3, ['_hv_token' => $this->token(0)]))->assertStatus(400);
        $e4 = 'noconsent' . uniqid() . '@x.test';
        $p  = $this->payload($e4);
        unset($p['consent']);
        $this->post($url, $p)->assertStatus(422);
        // token de OTRO formulario no sirve aquí
        $e5 = 'other' . uniqid() . '@x.test';
        $this->post($url, $this->payload($e5, ['_hv_token' => service('formSubmissions')->issueToken('zzzzzzzzzzzzzzzzzzzzzzzzzz', time() - 10)]))->assertStatus(400);

        foreach ([$e1, $e2, $e3, $e4, $e5] as $e) {
            $this->assertSame(0, $this->rows('cmp_crm_contacts', ['email' => $e]), $e);
        }
    }

    public function testFieldValidationUsesPublishedVersionAndEscapesOutput(): void
    {
        $url = 'form-publico/' . $this->form['public_id'];
        $p   = $this->payload('no-es-correo');
        $p['f']['tipo'] = 'Opción inventada';
        $p['f']['nombre'] = '<script>alert(1)</script>';
        $r = $this->post($url, $p);
        $r->assertStatus(422);
        $r->assertDontSee('<script>alert(1)</script>', null);
        $r->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', null);
    }

    public function testUnpublishedOrUnknownFormsReturn404(): void
    {
        $this->get('form-publico/noexiste000000000000000000')->assertStatus(404);
        $this->get('form-publico/zzzzzzzzzzzzzzzzzzzzzzzzzz')->assertStatus(404);
        $this->get('form-publico/1')->assertStatus(404); // ids internos secuenciales no sirven
    }

    public function testEmbeddingOnlyOnDeclaredDomains(): void
    {
        $r = $this->get('form-publico/' . $this->form['public_id']);
        $this->assertStringContainsString("frame-ancestors 'none'", $r->response()->getHeaderLine('Content-Security-Policy'));
        $this->db()->table('cmp_form_forms')->where('id', $this->form['id'])->update(['embed_domains' => json_encode(['https://www.herramientasvirtuales.co'])]);
        $r = $this->get('form-publico/' . $this->form['public_id']);
        $this->assertStringContainsString('frame-ancestors \'self\' https://www.herramientasvirtuales.co', $r->response()->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('', $r->response()->getHeaderLine('X-Frame-Options'));
        $this->db()->table('cmp_form_forms')->where('id', $this->form['id'])->update(['embed_domains' => null]);
    }

    public function testDraftQuotesAreNotPublic(): void
    {
        $q = $this->db()->table('cmp_quote_quotes')->where('status', 'draft')->get()->getRow();
        if ($q) {
            $this->get('quote-publico/' . $q->public_id)->assertStatus(404);
        }
        $this->get('quote-publico/aaaaaaaaaaaaaaaaaaaaaaaaaa')->assertStatus(404);
    }
}
