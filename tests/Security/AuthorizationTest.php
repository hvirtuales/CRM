<?php

namespace Tests\Security;

use Config\Services;
use Tests\Support\HvTestCase;

/**
 * RBAC + ABAC: permisos verticales, propiedad de registros, componentes y planes, soporte
 * temporal, modo solo lectura y última línea de defensa en servicios.
 */
final class AuthorizationTest extends HvTestCase
{
    private int $hv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hv = $this->companyId('herramientas-virtuales');
    }

    private function oppOwnedBy(string $email): int
    {
        return $this->asContext($email, 'company', $this->hv, function () use ($email) {
            $c = service('crmContacts')->create($this->hv, ['first_name' => 'Cliente', 'email' => uniqid('c') . '@x.test'], null);

            return (int) service('crmOpportunities')->create($this->hv, ['title' => 'Opp de ' . $email, 'contact_id' => $c->data['id']], $this->userId($email))->data['id'];
        });
    }

    public function testSalesAgentCannotManageUsersOrSettings(): void
    {
        $as = fn () => $this->actingAs('asesor@hv.test', 'company', $this->hv);
        $as()->get('negocio/usuarios')->assertStatus(403);
        $as()->get('negocio/configuracion')->assertStatus(403);
        $as()->get('negocio/tokens')->assertStatus(403);
        $as()->get('crm/configuracion')->assertStatus(403);
        $as()->get('crm/contactos/exportar')->assertStatus(403);
        $as()->postForm('negocio/usuarios', ['name' => 'Intruso', 'email' => 'intruso@x.test', 'role_id' => Services::rbac()->systemRoleId('business_owner')])->assertStatus(403);
        $this->assertSame(0, $this->rows('users', ['email' => 'intruso@x.test']));
    }

    public function testAgentCannotEditOpportunityOfAnotherAgent(): void
    {
        $opp = $this->oppOwnedBy('gerente@hv.test');
        $won = (int) $this->db()->table('cmp_crm_stages')->where('company_id', $this->hv)->where('kind', 'won')->get()->getRow()->id;

        $as = fn () => $this->actingAs('asesor@hv.test', 'company', $this->hv);
        $as()->postForm('crm/oportunidades/' . $opp, ['title' => 'Cambiado por asesor']);
        $as()->postForm('crm/oportunidades/' . $opp . '/etapa', ['stage_id' => $won]);
        $this->assertSame(1, $this->rows('cmp_crm_opportunities', ['id' => $opp, 'title' => 'Opp de gerente@hv.test']));
        $this->assertNotSame($won, (int) $this->db()->table('cmp_crm_opportunities')->where('id', $opp)->get()->getRow()->stage_id);

        // JSON (kanban) responde 403 explícito
        $r = $as()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'X-CSRF-TOKEN' => self::CSRF])
            ->withBodyFormat('json')->post('crm/oportunidades/' . $opp . '/etapa', ['stage_id' => $won]);
        $r->assertStatus(403);
    }

    public function testAgentCannotReassignOwnOpportunityToSomeoneElse(): void
    {
        $opp = $this->oppOwnedBy('asesor@hv.test');
        $this->actingAs('asesor@hv.test', 'company', $this->hv)->postForm('crm/oportunidades/' . $opp, [
            'title' => 'Opp de asesor@hv.test', 'owner_user_id' => $this->userId('gerente@hv.test'),
        ]);
        $this->assertSame($this->userId('asesor@hv.test'), (int) $this->db()->table('cmp_crm_opportunities')->where('id', $opp)->get()->getRow()->owner_user_id);
    }

    public function testManagerCanEditAnyOpportunity(): void
    {
        $opp = $this->oppOwnedBy('asesor@hv.test');
        $this->actingAs('gerente@hv.test', 'company', $this->hv)->postForm('crm/oportunidades/' . $opp, ['title' => 'Editada por gerente']);
        $this->assertSame(1, $this->rows('cmp_crm_opportunities', ['id' => $opp, 'title' => 'Editada por gerente']));
    }

    public function testCannotGrantRoleOfAnotherScopeOrEscalate(): void
    {
        $platformRole = Services::rbac()->systemRoleId('platform_owner');
        $this->actingAs('gerente@hv.test', 'company', $this->hv)->postForm('negocio/usuarios', [
            'name' => 'Escalada', 'email' => 'escalada@x.test', 'role_id' => $platformRole,
        ]);
        $this->assertSame(0, $this->rows('users', ['email' => 'escalada@x.test']));
    }

    public function testCannotModifyOwnMembership(): void
    {
        $mine = $this->membershipId('gerente@hv.test', 'company', $this->hv);
        $this->actingAs('gerente@hv.test', 'company', $this->hv)->postForm('negocio/usuarios/' . $mine, ['status' => 'suspended']);
        $this->assertSame(1, $this->rows('memberships', ['id' => $mine, 'status' => 'active']));
    }

    public function testDisabledComponentClosesRoutesAndMenus(): void
    {
        $this->assertTrue(Services::components()->setStatus('quote', false)->ok);
        try {
            $this->actingAs('gerente@hv.test', 'company', $this->hv)->get('cotizaciones')->assertStatus(403);
            $this->actingAs('gerente@hv.test', 'company', $this->hv)->get('negocio')->assertDontSee('cotizaciones/catalogo');
            // crm no se puede deshabilitar mientras form/quote dependan de él
            Services::components()->setStatus('form', true);
            $this->assertFalse(Services::components()->setStatus('crm', false)->ok);
        } finally {
            Services::components()->setStatus('quote', true);
        }
    }

    public function testPlanWithoutServiceBlocksRouteAndService(): void
    {
        $a2   = $this->companyId('inmobiliaria-andes'); // plan Básico: cotizaciones no incluidas
        $this->actingAs('andes@hv.test', 'company', $a2)->get('cotizaciones')->assertStatus(403);
        $r = $this->asContext('andes@hv.test', 'company', $a2, fn () => service('quoteCatalog')->list($a2));
        $this->assertIsArray($r);
        $q = $this->asContext('andes@hv.test', 'company', $a2, fn () => service('quoteQuotes')->save($a2, ['opportunity_id' => '1', 'lines' => []], $this->userId('andes@hv.test')));
        $this->assertFalse($q->ok, 'el servicio también lo bloquea (no solo la ruta)');
    }

    public function testQuotaIsEnforcedInService(): void
    {
        $a2   = $this->companyId('inmobiliaria-andes');
        $plan = (int) $this->db()->table('companies')->where('id', $a2)->get()->getRow()->plan_id;
        $this->db()->table('plans')->where('id', $plan)->update(['max_contacts' => 1]);
        try {
            $this->asContext('andes@hv.test', 'company', $a2, fn () => service('crmContacts')->create($a2, ['first_name' => 'Uno', 'email' => uniqid() . '@q.test'], null));
            Services::plans()->flush();
            $r = $this->asContext('andes@hv.test', 'company', $a2, fn () => service('crmContacts')->create($a2, ['first_name' => 'Dos', 'email' => uniqid() . '@q.test'], null));
            $this->assertFalse($r->ok);
            $this->assertSame(409, $r->status);
        } finally {
            $this->db()->table('plans')->where('id', $plan)->update(['max_contacts' => 500]);
        }
    }

    public function testSuspendedSubscriptionIsReadOnly(): void
    {
        $a1  = $this->companyId('comercializadora-san-victorino');
        $sub = Services::subscriptions()->currentForCompany($a1);
        $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => 'suspended']);
        try {
            $as = fn () => $this->actingAs('negocio@hv.test', 'company', $a1);
            $as()->get('crm/contactos')->assertStatus(200);
            $as()->get('crm/contactos/exportar')->assertStatus(200); // exportar sus datos sigue permitido
            $as()->postForm('crm/contactos', ['first_name' => 'Nuevo', 'email' => 'nuevo-susp@x.test'])->assertStatus(403);
            $this->assertSame(0, $this->rows('cmp_crm_contacts', ['email' => 'nuevo-susp@x.test']));
        } finally {
            $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => $sub['status']]);
        }
    }

    public function testSupportAccessRequiresBusinessApprovalAndIsReadOnly(): void
    {
        $a1 = $this->companyId('comercializadora-san-victorino');
        $this->actingAs('soporte@hv.test', 'platform')->postForm('plataforma/soporte', [
            'company_id' => $a1, 'reason' => 'Caso SOP-1: el formulario no crea oportunidades.', 'hours' => 2,
        ]);
        $grant = $this->db()->table('support_grants')->where('company_id', $a1)->orderBy('id', 'DESC')->get()->getRow();
        $this->assertSame('pending', $grant->status);

        // Sin aprobar: no puede entrar
        $this->actingAs('soporte@hv.test', 'platform')->postForm('contexto/soporte', ['grant_id' => $grant->id]);
        $this->assertEmpty(session('support_grant_id'));

        // Otro negocio NO puede aprobar una solicitud que no es suya
        $this->actingAs('gerente@hv.test', 'company', $this->hv)->postForm('negocio/soporte/' . $grant->id, ['decision' => 'approve']);
        $this->assertSame(1, $this->rows('support_grants', ['id' => $grant->id, 'status' => 'pending']));

        // El negocio dueño aprueba
        $this->actingAs('negocio@hv.test', 'company', $a1)->postForm('negocio/soporte/' . $grant->id, ['decision' => 'approve']);
        $this->assertSame(1, $this->rows('support_grants', ['id' => $grant->id, 'status' => 'approved']));

        $s = $this->sessionFor('soporte@hv.test', 'platform');
        unset($s['membership_id']);
        $s['support_grant_id'] = (int) $grant->id;
        $this->withSession($s)->get('crm/contactos')->assertStatus(200);
        $this->withSession($s)->post('crm/contactos', ['first_name' => 'Soporte', 'email' => 'soporte-escribe@x.test', 'csrf_hv' => self::CSRF])->assertStatus(403);
        $this->withSession($s)->get('negocio/usuarios')->assertStatus(200);

        // Revocado: deja de funcionar al instante
        $this->db()->table('support_grants')->where('id', $grant->id)->update(['status' => 'revoked']);
        $this->withSession($s)->get('crm/contactos')->assertRedirectTo(site_url('contexto'));
    }
}
