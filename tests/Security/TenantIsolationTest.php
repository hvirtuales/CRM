<?php

namespace Tests\Security;

use Tests\Support\HvTestCase;

/**
 * Criterio de salida Fase 0: cero accesos cruzados entre negocios (mismo distribuidor y
 * distintos) y entre distribuidores. Incluye el caso «cambio el id en el POST» (IDOR).
 */
final class TenantIsolationTest extends HvTestCase
{
    private int $hv;
    private int $a1;
    private int $a2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hv = $this->companyId('herramientas-virtuales');
        $this->a1 = $this->companyId('comercializadora-san-victorino');
        $this->a2 = $this->companyId('inmobiliaria-andes');
    }

    /** Crea un contacto + oportunidad en A2 como su propietaria. */
    private function seedA2(): array
    {
        return $this->asContext('andes@hv.test', 'company', $this->a2, function () {
            $c = service('crmContacts')->create($this->a2, ['first_name' => 'Secreto', 'last_name' => 'DeAndes', 'email' => 'secreto' . uniqid() . '@andes.test'], $this->userId('andes@hv.test'));
            $o = service('crmOpportunities')->create($this->a2, ['title' => 'Negocio confidencial A2', 'contact_id' => $c->data['id'], 'amount' => '777'], $this->userId('andes@hv.test'));

            return [$c->data['id'], $o->data['id']];
        });
    }

    public function testBusinessCannotReadAnotherBusinessContactsSameDistributor(): void
    {
        [$contactId, $oppId] = $this->seedA2();
        $as = fn () => $this->actingAs('negocio@hv.test', 'company', $this->a1);

        $as()->get('crm/contactos')->assertDontSee('DeAndes');
        $as()->get('crm/contactos?edit=' . $contactId)->assertDontSee('DeAndes');
        $as()->get('crm/oportunidades?open=' . $oppId)->assertDontSee('Negocio confidencial A2');
        $as()->get('crm/oportunidades?view=list')->assertDontSee('Negocio confidencial A2');
        $as()->get('crm/contactos/exportar')->assertDontSee('DeAndes');
    }

    public function testIdorOnUpdateMoveArchiveIsBlocked(): void
    {
        [$contactId, $oppId] = $this->seedA2();
        $as = fn () => $this->actingAs('negocio@hv.test', 'company', $this->a1);

        $as()->postForm('crm/contactos/' . $contactId, ['first_name' => 'Hackeado', 'email' => 'x@x.test']);
        $as()->postForm('crm/contactos/' . $contactId . '/archivar');
        $as()->postForm('crm/oportunidades/' . $oppId, ['title' => 'Hackeado']);
        $as()->postForm('crm/oportunidades/' . $oppId . '/archivar');
        $as()->postForm('crm/oportunidades/' . $oppId . '/actividad', ['type' => 'note', 'body' => 'intrusión']);

        $this->assertSame(1, $this->rows('cmp_crm_contacts', ['id' => $contactId, 'first_name' => 'Secreto', 'status' => 'active']));
        $this->assertSame(1, $this->rows('cmp_crm_opportunities', ['id' => $oppId, 'title' => 'Negocio confidencial A2', 'deleted_at' => null]));
        $this->assertSame(0, $this->rows('cmp_crm_activities', ['opportunity_id' => $oppId, 'body' => 'intrusión']));
    }

    public function testCannotMoveOpportunityToStageOfAnotherBusiness(): void
    {
        [, $oppA2] = $this->seedA2();
        $foreignStage = (int) $this->db()->table('cmp_crm_stages')->where('company_id', $this->hv)->where('kind', 'won')->get()->getRow()->id;
        $before = (int) $this->db()->table('cmp_crm_opportunities')->where('id', $oppA2)->get()->getRow()->stage_id;

        $this->actingAs('andes@hv.test', 'company', $this->a2)->postForm('crm/oportunidades/' . $oppA2 . '/etapa', ['stage_id' => $foreignStage]);
        $this->assertSame($before, (int) $this->db()->table('cmp_crm_opportunities')->where('id', $oppA2)->get()->getRow()->stage_id);
    }

    public function testCannotLinkOpportunityToContactOfAnotherBusiness(): void
    {
        [$contactA2] = $this->seedA2();
        $this->actingAs('negocio@hv.test', 'company', $this->a1)->postForm('crm/oportunidades', ['title' => 'Robo de contacto', 'contact_id' => $contactA2]);
        $this->assertSame(0, $this->rows('cmp_crm_opportunities', ['title' => 'Robo de contacto']));
    }

    public function testDeduplicationNeverCrossesBusinesses(): void
    {
        $email = 'compartido' . uniqid() . '@cliente.test';
        $this->asContext('andes@hv.test', 'company', $this->a2, fn () => service('crmContacts')->create($this->a2, ['first_name' => 'Ana', 'email' => $email], null));
        $r = $this->asContext('negocio@hv.test', 'company', $this->a1, fn () => service('crmContacts')->create($this->a1, ['first_name' => 'Ana', 'email' => $email], null));
        $this->assertTrue($r->ok, 'el mismo correo en otro negocio es un contacto distinto');
        $this->assertSame(2, $this->rows('cmp_crm_contacts', ['email' => $email]));
    }

    public function testUserWithTwoScopesDoesNotMixData(): void
    {
        // distribuidor@hv.test es dueño del tenant y «viewer» en A1: en A1 solo ve A1
        $this->actingAs('distribuidor@hv.test', 'company', $this->a1)->get('crm/contactos')->assertStatus(200);
        $this->actingAs('distribuidor@hv.test', 'company', $this->a1)->postForm('crm/contactos', ['first_name' => 'X', 'email' => 'viewer@x.test'])->assertStatus(403);
        // y en ámbito distribuidor no tiene acceso al CRM de ningún negocio
        $this->actingAs('distribuidor@hv.test', 'tenant')->get('crm/contactos')->assertStatus(403);
    }

    public function testCannotSwitchIntoForeignMembership(): void
    {
        $foreign = $this->membershipId('andes@hv.test', 'company', $this->a2);
        $this->actingAs('negocio@hv.test', 'company', $this->a1)->postForm('contexto', ['membership_id' => $foreign]);
        $this->assertNotSame($foreign, (int) session('membership_id'));
    }

    public function testForgedSessionCompanyIdIsIgnored(): void
    {
        // Aunque el navegador/sesión diga otra empresa, el ámbito se recalcula desde la membresía.
        [, $oppA2] = $this->seedA2();
        $s = $this->sessionFor('negocio@hv.test', 'company', $this->a1);
        $s['active_company_id'] = $this->a2;
        $this->withSession($s)->get('crm/oportunidades?open=' . $oppA2)->assertDontSee('Negocio confidencial A2');
        $this->assertSame($this->a1, (int) session('active_company_id'));
    }

    public function testDistributorOnlyManagesItsPortfolio(): void
    {
        $as = fn () => $this->actingAs('distribuidor@hv.test', 'tenant');
        $list = $as()->get('distribuidor/negocios');
        $list->assertSee('Comercializadora San Victorino');
        $list->assertDontSee('Herramientas Virtuales CO');

        // Asignar plan a un negocio que NO es de su cartera (directo de plataforma)
        $planBefore = $this->db()->table('companies')->where('id', $this->hv)->get()->getRow()->plan_id;
        $plan = (int) $this->db()->table('plans')->where('code', 'basico')->get()->getRow()->id;
        $as()->postForm('distribuidor/negocios/' . $this->hv . '/plan', ['plan_id' => $plan]);
        $this->assertSame($planBefore, $this->db()->table('companies')->where('id', $this->hv)->get()->getRow()->plan_id);

        // tenant_id inyectado en el alta: se ignora, el negocio queda en SU cartera
        $as()->postForm('distribuidor/negocios', [
            'name' => 'Negocio Inyectado', 'tenant_id' => 999999, 'timezone' => 'America/Bogota', 'currency' => 'COP', 'locale' => 'es',
            'owner_name' => 'Dueño', 'owner_email' => 'inyectado' . uniqid() . '@x.test',
        ]);
        $row = $this->db()->table('companies')->where('name', 'Negocio Inyectado')->get()->getRow();
        $this->assertNotNull($row);
        $this->assertSame($this->tenantId(), (int) $row->tenant_id);
    }

    public function testDistributorCannotAccessPlatformOrOtherDistributors(): void
    {
        $this->actingAs('distribuidor@hv.test', 'tenant')->get('plataforma/distribuidores')->assertStatus(403);
        $this->actingAs('distribuidor@hv.test', 'tenant')->get('plataforma/negocios')->assertStatus(403);
    }

    public function testPlatformCannotReadBusinessDataWithoutSupportGrant(): void
    {
        $this->seedA2();
        $this->actingAs('superadmin@hv.test', 'platform')->get('crm/contactos')->assertStatus(403);
        $this->actingAs('superadmin@hv.test', 'platform')->get('negocio')->assertStatus(403);
    }
}
