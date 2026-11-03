<?php

namespace Tests\Feature;

use App\Libraries\CommercialAutomation;
use App\Libraries\CommissionService;
use Config\Services;
use Tests\Support\HvTestCase;

/**
 * Bloque 1 de automatización comercial: reparto de leads por proyecto, seguimiento con escalamiento,
 * cierre/vencimiento de cotizaciones, oportunidades de renovación/ampliación y liquidación de comisiones.
 */
final class CommercialAutomationTest extends HvTestCase
{
    use \CodeIgniter\Test\StreamFilterTrait;

    private int $hv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hv = $this->companyId('herramientas-virtuales');
    }

    private function project(string $name): int
    {
        return $this->asContext('gerente@hv.test', 'company', $this->hv, fn () => (int) service('crmSetup')->saveProject($this->hv, ['name' => $name . ' ' . uniqid()])->data['id']);
    }

    private function lead(int $projectId): array
    {
        $r = service('crmLeads')->capture($this->hv, ['first_name' => 'Lead', 'email' => uniqid('l') . '@x.test'], ['project_id' => $projectId], 'form');
        $this->assertTrue($r->ok);

        return $this->db()->table('cmp_crm_opportunities')->where('id', $r->data['opportunity_id'])->get()->getRowArray();
    }

    // ------------------------------------------------------------ 1. reparto por proyecto

    public function testLeadsRotateAmongTheProjectTeam(): void
    {
        $p      = $this->project('Carnetización');
        $asesor = $this->userId('asesor@hv.test');
        $gerente = $this->userId('gerente@hv.test');
        $this->assertTrue(service('crmAssignment')->setTeam($this->hv, $p, [$asesor, $gerente])->ok);

        $owners = array_map(fn () => (int) $this->lead($p)['owner_user_id'], range(1, 4));
        $this->assertSame([2, 2], array_values(array_count_values($owners)), 'reparto parejo entre el equipo');
        $this->assertNotSame($owners[0], $owners[1], 'turnos alternados');

        $opp = $this->lead($p);
        $this->assertSame(1, $this->rows('cmp_crm_activities', ['opportunity_id' => $opp['id'], 'type' => 'system', 'body' => lang('Crm.autoAssigned', [$this->db()->table('users')->where('id', $opp['owner_user_id'])->get()->getRow()->name])]));
        $contact = $this->db()->table('cmp_crm_contacts')->where('id', $opp['contact_id'])->get()->getRow();
        $this->assertSame((int) $opp['owner_user_id'], (int) $contact->owner_user_id, 'el contacto queda con el mismo responsable');
    }

    public function testSuspendedTeamMembersAreSkippedAndForeignUsersRejected(): void
    {
        $p      = $this->project('Salud');
        $asesor = $this->userId('asesor@hv.test');
        $this->assertFalse(service('crmAssignment')->setTeam($this->hv, $p, [$asesor, $this->userId('andes@hv.test')])->ok, 'usuario de otro negocio');
        service('crmAssignment')->setTeam($this->hv, $p, [$asesor, $this->userId('gerente@hv.test')]);
        $m = $this->membershipId('asesor@hv.test', 'company', $this->hv);
        $this->db()->table('memberships')->where('id', $m)->update(['status' => 'suspended']);
        try {
            foreach (range(1, 3) as $_) {
                $this->assertSame($this->userId('gerente@hv.test'), (int) $this->lead($p)['owner_user_id']);
            }
        } finally {
            $this->db()->table('memberships')->where('id', $m)->update(['status' => 'active']);
        }
    }

    public function testProjectWithoutTeamFallsBackToSalesReps(): void
    {
        $p = $this->project('Sin equipo');
        $this->assertSame($this->userId('asesor@hv.test'), (int) $this->lead($p)['owner_user_id'], 'único asesor comercial del negocio');
    }

    public function testTeamIsConfiguredFromTheSetupScreenOnlyWithPermission(): void
    {
        $p = $this->project('UI');
        $this->actingAs('asesor@hv.test', 'company', $this->hv)->postForm('crm/proyectos/' . $p . '/equipo', ['team' => [(string) $this->userId('asesor@hv.test')]])->assertStatus(403);
        $this->actingAs('gerente@hv.test', 'company', $this->hv)->postForm('crm/proyectos/' . $p . '/equipo', ['team' => [(string) $this->userId('asesor@hv.test')]]);
        $this->assertSame([$this->userId('asesor@hv.test')], service('crmAssignment')->team($this->hv, $p));
    }

    // ------------------------------------------------------------ 2. seguimiento

    public function testIdleOpportunitiesAlertOwnerAndEscalateToManager(): void
    {
        $opp = $this->lead($this->project('Seguimiento'));
        $this->db()->table('cmp_crm_opportunities')->where('id', $opp['id'])->update([
            'owner_user_id' => $this->userId('asesor@hv.test'), 'last_activity_at' => date('Y-m-d H:i:s', time() - 16 * 86400),
            'next_action_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);
        $agent = service('crmFollowUp')->alerts($this->hv, $this->userId('asesor@hv.test'), false);
        $this->assertContains((int) $opp['id'], array_map('intval', array_column($agent['stale'], 'id')));
        $this->assertContains((int) $opp['id'], array_map('intval', array_column($agent['overdue'], 'id')));
        $this->assertSame([], $agent['escalated'], 'el asesor no ve escalamientos');
        $manager = service('crmFollowUp')->alerts($this->hv, $this->userId('gerente@hv.test'), true);
        $this->assertContains((int) $opp['id'], array_map('intval', array_column($manager['escalated'], 'id')));

        // Una gestión humana reinicia el reloj; un aviso del sistema no.
        service('crmOpportunities')->log($this->hv, (int) $opp['id'], null, null, 'system', 'aviso');
        $this->assertContains((int) $opp['id'], array_map('intval', array_column(service('crmFollowUp')->alerts($this->hv, $this->userId('asesor@hv.test'), false)['stale'], 'id')));
        service('crmOpportunities')->log($this->hv, (int) $opp['id'], null, $this->userId('asesor@hv.test'), 'call', 'Llamada');
        $this->assertNotContains((int) $opp['id'], array_map('intval', array_column(service('crmFollowUp')->alerts($this->hv, $this->userId('asesor@hv.test'), false)['stale'], 'id')));

        $this->actingAs('asesor@hv.test', 'company', $this->hv)->get('crm/seguimiento')->assertStatus(200);
    }

    public function testFollowUpSettingsAreValidated(): void
    {
        $this->assertFalse(service('crmFollowUp')->saveSettings($this->hv, ['stale_days' => '10', 'escalate_days' => '5'])->ok);
        $this->assertTrue(service('crmFollowUp')->saveSettings($this->hv, ['stale_days' => '5', 'escalate_days' => '10'])->ok);
        $this->assertSame(['stale_days' => 5, 'escalate_days' => 10], service('crmFollowUp')->settings($this->hv));
        service('crmFollowUp')->saveSettings($this->hv, ['stale_days' => '7', 'escalate_days' => '14']);
    }

    // ------------------------------------------------------------ 3. cotizaciones

    private function sentQuote(string $validUntil): array
    {
        $opp = $this->db()->table('cmp_crm_opportunities')->where('company_id', $this->hv)->where('owner_user_id', $this->userId('gerente@hv.test'))->where('deleted_at', null)->get()->getRow();
        $id  = $this->asContext('gerente@hv.test', 'company', $this->hv, function () use ($opp, $validUntil) {
            $r = service('quoteQuotes')->save($this->hv, ['opportunity_id' => (string) $opp->id, 'valid_until' => $validUntil,
                'lines' => [['description' => 'Servicio', 'quantity' => '2', 'unit_price' => '500000', 'tax_percent' => '19']]], $this->userId('gerente@hv.test'));
            service('quoteQuotes')->transition($this->hv, (int) $r->data['id'], 'sent', $this->userId('gerente@hv.test'));

            return (int) $r->data['id'];
        });

        return $this->db()->table('cmp_quote_quotes')->where('id', $id)->get()->getRowArray();
    }

    public function testAcceptedQuoteClosesOpportunityAsWonWithQuotedAmount(): void
    {
        $q = $this->sentQuote(date('Y-m-d', time() + 10 * 86400));
        $this->asContext('gerente@hv.test', 'company', $this->hv, fn () => service('quoteQuotes')->transition($this->hv, (int) $q['id'], 'accepted', $this->userId('gerente@hv.test')));
        $opp = $this->db()->table('cmp_crm_opportunities o')->select('o.amount, s.kind')->join('cmp_crm_stages s', 's.id = o.stage_id')->where('o.id', $q['opportunity_id'])->get()->getRow();
        $this->assertSame('won', $opp->kind);
        $this->assertSame('1190000.00', number_format((float) $opp->amount, 2, '.', ''));
    }

    public function testDailyJobExpiresQuotesAndRemindsOnce(): void
    {
        $expiring = $this->sentQuote(date('Y-m-d', time() + 2 * 86400));
        $expired  = $this->sentQuote(date('Y-m-d', time() + 1 * 86400));
        $later    = time() + 2 * 86400; // dentro de 2 días: la segunda ya venció

        $stats = service('quoteQuotes')->runDaily(time());
        $this->assertGreaterThanOrEqual(2, $stats['reminded']);
        $this->assertSame(0, service('quoteQuotes')->runDaily(time())['reminded'], 'recordatorio una sola vez');
        $opp = $this->db()->table('cmp_crm_opportunities')->where('id', $expiring['opportunity_id'])->get()->getRow();
        $this->assertNotNull($opp->next_action_at);

        service('quoteQuotes')->runDaily($later);
        $this->assertSame('expired', $this->db()->table('cmp_quote_quotes')->where('id', $expired['id'])->get()->getRow()->status);
        $this->assertSame('sent', $this->db()->table('cmp_quote_quotes')->where('id', $expiring['id'])->get()->getRow()->status);
    }

    // ------------------------------------------------------------ 4. renovación / prueba / ampliación

    public function testDirectBusinessSignalsCreateOpportunitiesInHouseCrmOnce(): void
    {
        $r = $this->asContext('superadmin@hv.test', 'platform', null, fn () => Services::provisioning()->createCompany([
            'name' => 'Cliente Directo ' . uniqid(), 'email' => uniqid('cd') . '@cliente.test', 'owner_name' => 'Dueño', 'owner_email' => uniqid() . '@o.test',
            'plan_id' => (int) $this->db()->table('plans')->where('code', 'profesional')->get()->getRow()->id,
        ], null, $this->userId('superadmin@hv.test')));
        $cid = (int) $r->data['id'];
        $this->db()->table('subscriptions')->where('company_id', $cid)->update(['trial_ends_at' => date('Y-m-d H:i:s', time() + 2 * 86400)]);

        $auto = new CommercialAutomation();
        $stats = $auto->run();
        $this->assertGreaterThanOrEqual(1, $stats['trial']);
        $signal = $this->db()->table('commercial_signals')->where('company_id', $cid)->where('kind', 'trial')->get()->getRow();
        $this->assertNotNull($signal);
        $opp = $this->db()->table('cmp_crm_opportunities')->where('id', $signal->opportunity_id)->get()->getRow();
        $this->assertSame($this->hv, (int) $opp->company_id, 'la oportunidad vive en el CRM de Herramientas Virtuales');
        $this->assertSame('automation', $opp->source);
        $project = $this->db()->table('cmp_crm_projects')->where('id', $opp->project_id)->get()->getRow();
        $this->assertSame(config('Commercial')->houseProjectCode, $project->code);

        $auto->run();
        $this->assertSame(1, $this->rows('commercial_signals', ['company_id' => $cid, 'kind' => 'trial']), 'idempotente');
    }

    public function testDistributorBusinessesSignalOnlyInTheirPortfolio(): void
    {
        $a1  = $this->companyId('comercializadora-san-victorino');
        $sub = Services::subscriptions()->currentForCompany($a1);
        $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => 'active', 'current_period_end' => date('Y-m-d H:i:s', time() + 5 * 86400)]);
        $plan = (int) $this->db()->table('companies')->where('id', $a1)->get()->getRow()->plan_id;
        try {
            (new CommercialAutomation())->run();
            $this->assertSame(0, $this->rows('commercial_signals', ['company_id' => $a1]), 'no entra al CRM de HV');
            $kinds = array_column((new CommercialAutomation())->signalsFor([$this->db()->table('companies')->where('id', $a1)->get()->getRowArray()]), 'kind');
            $this->assertContains('renewal', $kinds);
            $page = $this->actingAs('distribuidor@hv.test', 'tenant')->get('distribuidor');
            $page->assertSee(lang('Commercial.kind.renewal'));
        } finally {
            $this->db()->table('subscriptions')->where('id', $sub['id'])->update(['status' => $sub['status'], 'current_period_end' => $sub['current_period_end']]);
        }
        $this->assertGreaterThan(0, $plan);
    }

    public function testUsageNearPlanLimitRaisesUpsellSignal(): void
    {
        $a2   = $this->companyId('inmobiliaria-andes');
        $plan = (int) $this->db()->table('companies')->where('id', $a2)->get()->getRow()->plan_id;
        $used = $this->rows('cmp_crm_contacts', ['company_id' => $a2, 'deleted_at' => null]);
        $this->asContext('andes@hv.test', 'company', $a2, fn () => service('crmContacts')->create($a2, ['first_name' => 'U', 'email' => uniqid() . '@u.test'], null));
        $this->db()->table('plans')->where('id', $plan)->update(['max_contacts' => $used + 1]);
        Services::plans()->flush();
        try {
            $kinds = array_column((new CommercialAutomation())->signalsFor([$this->db()->table('companies')->where('id', $a2)->get()->getRowArray()]), 'kind');
            $this->assertContains('upsell', $kinds);
        } finally {
            $this->db()->table('plans')->where('id', $plan)->update(['max_contacts' => 500]);
        }
    }

    // ------------------------------------------------------------ 5. comisiones

    public function testCommissionsAreApprovedOnPaymentAndSettledMonthly(): void
    {
        $a1  = $this->companyId('comercializadora-san-victorino');
        $sub = Services::subscriptions()->currentForCompany($a1);
        $r   = Services::subscriptions()->registerPayment((int) $sub['id'], '100000.00', 'transfer', 'SET-' . uniqid(), null);
        $c   = $this->db()->table('commissions')->where('payment_id', $r->data['id'])->get()->getRow();
        $this->assertSame('approved', $c->status, 'aprobación automática');

        $svc = new CommissionService();
        $svc->settleClosedMonths(time()); // mes en curso: no se liquida todavía
        $this->assertNull($this->db()->table('commissions')->where('id', $c->id)->get()->getRow()->settlement_id);

        $svc->settleClosedMonths(strtotime('first day of next month 08:00'));
        $settlementId = (int) $this->db()->table('commissions')->where('id', $c->id)->get()->getRow()->settlement_id;
        $this->assertGreaterThan(0, $settlementId);
        $s = $this->db()->table('commission_settlements')->where('id', $settlementId)->get()->getRow();
        $this->assertSame('pending', $s->status);
        $this->assertGreaterThanOrEqual(20000.0, (float) $s->total);

        // El distribuidor descarga su extracto; otro distribuidor no puede.
        $csv = (string) $this->actingAs('distribuidor@hv.test', 'tenant')->get('distribuidor/comisiones/extractos/' . $settlementId)->response()->getBody();
        $this->assertStringContainsString('Comercializadora San Victorino', $csv);

        $this->assertFalse($svc->markSettlementPaid($settlementId, 'x', 1)->ok, 'referencia obligatoria');
        $this->actingAs('superadmin@hv.test', 'platform')->postForm('plataforma/comisiones/extractos/' . $settlementId . '/pagar', ['reference' => 'LIQ-' . uniqid()]);
        $this->assertSame('paid', $this->db()->table('commission_settlements')->where('id', $settlementId)->get()->getRow()->status);
        $this->assertSame('paid', $this->db()->table('commissions')->where('id', $c->id)->get()->getRow()->status);
    }

    public function testOtherDistributorCannotReadStatement(): void
    {
        $id = (int) ($this->db()->table('commission_settlements')->get()->getRow()->id ?? 0);
        if ($id === 0) {
            $this->markTestSkipped('sin extractos');
        }
        $other = $this->asContext('superadmin@hv.test', 'platform', null, fn () => Services::provisioning()->createTenant([
            'name' => 'Otro Distribuidor ' . uniqid(), 'commission_rate' => '0.1', 'owner_name' => 'X', 'owner_email' => uniqid() . '@t.test',
        ], $this->userId('superadmin@hv.test')));
        $tid = (int) $other->data['id'];
        $uid = $this->userId('soporte@hv.test');
        $this->db()->table('memberships')->insert(['user_id' => $uid, 'scope' => 'tenant', 'tenant_id' => $tid, 'role_id' => Services::rbac()->systemRoleId('tenant_owner'), 'status' => 'active']);
        $this->actingAs('soporte@hv.test', 'tenant', $tid)->get('distribuidor/comisiones/extractos/' . $id)->assertRedirectTo(site_url('distribuidor/comisiones'));
    }

    public function testDailyCommandRunsAllAutomations(): void
    {
        command('hv:lifecycle');
        $out = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('"commercial"', $out);
        $this->assertStringContainsString('"quote"', $out, 'los componentes participan vía hv.daily');
    }
}
