<?php

namespace Tests\Security;

use App\Libraries\Rls;
use Config\Services;
use Tests\Support\HvTestCase;

/**
 * Integridad comercial (pagos idempotentes, comisiones, totales en servidor) e inyección
 * (SQLi, XSS almacenado, CSV injection) y la segunda barrera RLS de PostgreSQL.
 */
final class BillingAndInjectionTest extends HvTestCase
{
    public function testPaymentIsIdempotentAndCommissionIsSingle(): void
    {
        $a1  = $this->companyId('comercializadora-san-victorino');
        $sub = Services::subscriptions()->currentForCompany($a1);
        $ref = 'TRX-' . uniqid();
        $r1  = Services::subscriptions()->registerPayment((int) $sub['id'], '189000.00', 'transfer', $ref, null);
        $r2  = Services::subscriptions()->registerPayment((int) $sub['id'], '189000.00', 'transfer', $ref, null);
        $this->assertTrue($r1->ok);
        $this->assertTrue($r2->data['duplicate'] ?? false);
        $pid = (int) $r1->data['id'];
        $this->assertSame(1, $this->rows('payments', ['reference' => $ref]));
        $this->assertSame(1, $this->rows('commissions', ['payment_id' => $pid]));
        $c = $this->db()->table('commissions')->where('payment_id', $pid)->get()->getRow();
        $this->assertSame('37800.00', number_format((float) $c->amount, 2, '.', ''), '20 % de 189.000');
    }

    public function testPaymentRejectsMalformedInput(): void
    {
        $sub = Services::subscriptions()->currentForCompany($this->companyId('comercializadora-san-victorino'));
        foreach ([['-5', 'transfer', 'ok-ref-1'], ['1e9', 'transfer', 'ok-ref-2'], ['100', 'bitcoin', 'ok-ref-3'], ['100', 'transfer', "x'; DROP TABLE payments;--"]] as [$amt, $m, $ref]) {
            $this->assertFalse(Services::subscriptions()->registerPayment((int) $sub['id'], $amt, $m, $ref, null)->ok, "{$amt}/{$m}/{$ref}");
        }
        $this->assertTrue(db_connect()->tableExists('payments'));
    }

    public function testDistributorCannotSellPrivatePlans(): void
    {
        $private = (int) $this->db()->table('plans')->where('code', 'empresarial')->get()->getRow()->id;
        $this->db()->table('plans')->where('id', $private)->update(['is_public' => 0]);
        try {
            $a2 = $this->companyId('inmobiliaria-andes');
            $before = $this->db()->table('companies')->where('id', $a2)->get()->getRow()->plan_id;
            $this->actingAs('distribuidor@hv.test', 'tenant')->postForm('distribuidor/negocios/' . $a2 . '/plan', ['plan_id' => $private]);
            $this->assertSame($before, $this->db()->table('companies')->where('id', $a2)->get()->getRow()->plan_id);
        } finally {
            $this->db()->table('plans')->where('id', $private)->update(['is_public' => 1]);
        }
    }

    public function testSubscriptionTransitionsFollowTheLifecycle(): void
    {
        $sub = Services::subscriptions()->currentForCompany($this->companyId('inmobiliaria-andes'));
        $this->assertFalse(Services::subscriptions()->transition((int) $sub['id'], 'archived', null)->ok, 'no se archiva sin cancelar');
    }

    public function testQuoteTotalsAreComputedServerSideAndCatalogPriceCannotBeTampered(): void
    {
        $hv = $this->companyId('herramientas-virtuales');
        $svc = $this->db()->table('cmp_quote_services')->where('company_id', $hv)->where('unit_price', '149000.00')->get()->getRow();
        $opp = $this->db()->table('cmp_crm_opportunities')->where('company_id', $hv)->get()->getRow();
        $r = $this->asContext('gerente@hv.test', 'company', $hv, fn () => service('quoteQuotes')->save($hv, [
            'opportunity_id' => (string) $opp->id, 'currency' => 'COP',
            'lines' => [
                ['service_id' => (string) $svc->id, 'description' => '', 'quantity' => '2', 'unit_price' => '1', 'tax_percent' => '0'], // precio manipulado
                ['service_id' => '', 'description' => 'Instalación', 'quantity' => '1.5', 'unit_price' => '100000.33', 'tax_percent' => '19'],
            ],
            'subtotal' => '1', 'total' => '1', // ignorados
        ], $this->userId('gerente@hv.test')));
        $this->assertTrue($r->ok);
        $q = $this->db()->table('cmp_quote_quotes')->where('id', $r->data['id'])->get()->getRow();
        // 2 × 149.000 = 298.000 (+19 % = 56.620) ; 1,5 × 100.000,33 = 150.000,50 (+19 % = 28.500,10)
        $this->assertSame('448000.50', number_format((float) $q->subtotal, 2, '.', ''));
        $this->assertSame('85120.10', number_format((float) $q->tax_total, 2, '.', ''));
        $this->assertSame('533120.60', number_format((float) $q->total, 2, '.', ''));
        $this->assertMatchesRegularExpression('/^COT-\d{4}-\d{5}$/', (string) $q->number);
    }

    public function testQuoteCannotReferenceForeignOpportunityOrService(): void
    {
        $hv = $this->companyId('herramientas-virtuales');
        $a2 = $this->companyId('inmobiliaria-andes');
        $foreignOpp = $this->asContext('andes@hv.test', 'company', $a2, function () use ($a2) {
            $c = service('crmContacts')->create($a2, ['first_name' => 'Z', 'email' => uniqid() . '@z.test'], null);

            return service('crmOpportunities')->create($a2, ['title' => 'Z', 'contact_id' => $c->data['id']], $this->userId('andes@hv.test'))->data['id'];
        });
        $r = $this->asContext('gerente@hv.test', 'company', $hv, fn () => service('quoteQuotes')->save($hv, [
            'opportunity_id' => (string) $foreignOpp, 'lines' => [['description' => 'x', 'quantity' => '1', 'unit_price' => '1', 'tax_percent' => '0']],
        ], $this->userId('gerente@hv.test')));
        $this->assertFalse($r->ok);
        $this->assertArrayHasKey('opportunity_id', $r->errors);
    }

    public function testStoredXssIsEscapedEverywhere(): void
    {
        $hv = $this->companyId('herramientas-virtuales');
        $payload = '<img src=x onerror=alert(1)>';
        $this->actingAs('gerente@hv.test', 'company', $hv)->postForm('crm/contactos', [
            'first_name' => $payload, 'organization' => '"><script>alert(2)</script>', 'email' => 'xss' . uniqid() . '@x.test',
        ]);
        $r = $this->actingAs('gerente@hv.test', 'company', $hv)->get('crm/contactos?q=' . urlencode('onerror'));
        $r->assertDontSee('<img src=x onerror=alert(1)>', null);
        $r->assertSee('&lt;img src=x onerror=alert(1)&gt;', null);
        $this->actingAs('gerente@hv.test', 'company', $hv)->get('crm/contactos?q=' . urlencode('"><script>'))->assertDontSee('"><script>alert(2)</script>', null);
    }

    public function testSqlInjectionInFiltersIsInert(): void
    {
        $hv = $this->companyId('herramientas-virtuales');
        foreach (["' OR '1'='1", "1; DROP TABLE users;--", "%' UNION SELECT password_hash FROM users--"] as $q) {
            $r = $this->actingAs('gerente@hv.test', 'company', $hv)->get('crm/contactos?q=' . urlencode($q));
            $r->assertStatus(200);
            $r->assertDontSee('$argon2id$', null);
            $this->actingAs('gerente@hv.test', 'company', $hv)->get('crm/oportunidades?view=list&q=' . urlencode($q))->assertStatus(200);
        }
        $this->assertTrue(db_connect()->tableExists('users'));
    }

    public function testCsvExportNeutralizesFormulaInjection(): void
    {
        $hv = $this->companyId('herramientas-virtuales');
        $this->asContext('gerente@hv.test', 'company', $hv, fn () => service('crmContacts')->create($hv, [
            'first_name' => '=HYPERLINK("http://evil.test","clic")', 'organization' => '+SUM(A1)', 'email' => 'csv' . uniqid() . '@x.test',
        ], null));
        $csv = (string) $this->actingAs('gerente@hv.test', 'company', $hv)->get('crm/contactos/exportar')->response()->getBody();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+SUM(A1)", $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|;)"?=HYPERLINK/m', $csv);
    }

    public function testRowLevelSecurityIsASecondBarrier(): void
    {
        if (! Rls::supported()) {
            $this->markTestSkipped('RLS solo aplica a PostgreSQL con HV_RLS=true.');
        }
        $hv = $this->companyId('herramientas-virtuales');
        $a2 = $this->companyId('inmobiliaria-andes');
        $db = db_connect();

        Rls::setBypass(false);
        Rls::setCompany(null);
        $this->assertSame(0, $db->table('cmp_crm_contacts')->countAllResults(), 'sin negocio fijado no se ve nada');

        Rls::setCompany($a2);
        // Una consulta que «olvida» el filtro company_id igualmente solo ve A2
        $companies = array_unique(array_column($db->table('cmp_crm_contacts')->select('company_id')->get()->getResultArray(), 'company_id'));
        $this->assertSame([], array_values(array_diff(array_map('intval', $companies), [$a2])));

        // Y no puede escribir filas de otro negocio
        $this->expectException(\Throwable::class);
        try {
            $db->table('cmp_crm_contacts')->insert(['company_id' => $hv, 'first_name' => 'Cruzado', 'kind' => 'person', 'source' => 'manual', 'status' => 'active']);
        } finally {
            Rls::setBypass(true);
        }
    }
}
