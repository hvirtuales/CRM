<?php

namespace Tests\Security;

use Tests\Support\HvTestCase;

/**
 * OWASP API Top 10: autenticación por token, BOLA (objetos de otro negocio), habilidades
 * mínimas, revocación y ausencia de asignación masiva de company_id.
 */
final class ApiTest extends HvTestCase
{
    private int $hv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hv = $this->companyId('herramientas-virtuales');
    }

    private function token(array $abilities, string $email = 'gerente@hv.test', ?int $company = null): string
    {
        $company ??= $this->hv;

        return $this->asContext($email, 'company', $company, fn ($ctx) => service('apiTokens')->create($ctx, 'prueba', $abilities, 30)->data['token']);
    }

    private function api(string $token)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
    }

    public function testRequiresValidBearerToken(): void
    {
        $this->get('api/v1/crm/contacts')->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer hvk_' . str_repeat('0', 48)])->get('api/v1/crm/contacts')->assertStatus(401);
    }

    public function testListsOnlyOwnBusinessAndHidesForeignObjects(): void
    {
        $a2 = $this->companyId('inmobiliaria-andes');
        $foreign = $this->asContext('andes@hv.test', 'company', $a2, fn () => service('crmContacts')->create($a2, ['first_name' => 'ApiAjeno', 'email' => uniqid() . '@a2.test'], null)->data['id']);

        $tok = $this->token(['crm.view']);
        $r   = $this->api($tok)->get('api/v1/crm/contacts?per_page=100');
        $r->assertStatus(200);
        $r->assertDontSee('ApiAjeno');
        $this->api($tok)->get('api/v1/crm/contacts/' . $foreign)->assertStatus(404); // 404, no 403
    }

    public function testAbilitiesAreEnforcedAndCannotExceedCreator(): void
    {
        $readOnly = $this->token(['crm.view']);
        $this->api($readOnly)->withBodyFormat('json')->post('api/v1/crm/contacts', ['first_name' => 'X', 'email' => 'apix@x.test'])->assertStatus(403);

        // El asesor no tiene crm.export ni company.*: esas habilidades se descartan
        $r = $this->asContext('asesor@hv.test', 'company', $this->hv, fn ($ctx) => service('apiTokens')->create($ctx, 'x', ['company.users.edit', 'crm.export'], 30));
        $this->assertFalse($r->ok);
    }

    public function testCreateIgnoresInjectedCompanyId(): void
    {
        $a2  = $this->companyId('inmobiliaria-andes');
        $tok = $this->token(['crm.view', 'crm.create']);
        $email = 'api' . uniqid() . '@x.test';
        $this->api($tok)->withBodyFormat('json')->post('api/v1/crm/contacts', ['first_name' => 'Api', 'email' => $email, 'company_id' => $a2])->assertStatus(201);
        $this->assertSame($this->hv, (int) $this->db()->table('cmp_crm_contacts')->where('email', $email)->get()->getRow()->company_id);
    }

    public function testRevokedAndExpiredTokensStopWorking(): void
    {
        $tok = $this->token(['crm.view']);
        $id  = (int) $this->db()->table('api_tokens')->where('token_hash', hash('sha256', $tok))->get()->getRow()->id;
        $this->api($tok)->get('api/v1/crm/contacts')->assertStatus(200);
        $this->db()->table('api_tokens')->where('id', $id)->update(['expires_at' => date('Y-m-d H:i:s', time() - 1)]);
        $this->api($tok)->get('api/v1/crm/contacts')->assertStatus(401);
        $this->db()->table('api_tokens')->where('id', $id)->update(['expires_at' => date('Y-m-d H:i:s', time() + 3600), 'revoked_at' => date('Y-m-d H:i:s')]);
        $this->api($tok)->get('api/v1/crm/contacts')->assertStatus(401);
        // Solo se guarda el hash
        $this->assertSame(0, $this->rows('api_tokens', ['token_hash' => $tok]));
    }

    public function testSuspendedMembershipInvalidatesItsTokens(): void
    {
        $tok = $this->token(['crm.view'], 'asesor@hv.test');
        $m   = $this->membershipId('asesor@hv.test', 'company', $this->hv);
        $this->db()->table('memberships')->where('id', $m)->update(['status' => 'suspended']);
        try {
            $this->api($tok)->get('api/v1/crm/contacts')->assertStatus(401);
        } finally {
            $this->db()->table('memberships')->where('id', $m)->update(['status' => 'active']);
        }
    }
}
