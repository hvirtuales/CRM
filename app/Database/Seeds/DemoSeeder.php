<?php

namespace App\Database\Seeds;

use App\Libraries\AuthService;
use App\Libraries\Ids;
use App\Libraries\Rls;
use CodeIgniter\Database\Seeder;
use Config\Services;

/**
 * Datos de DEMOSTRACIÓN para desarrollo y pruebas de aceptación. Se niega a correr en producción
 * (regla 12 de la especificación: no generar datos ficticios en producción).
 *
 *   php spark db:seed DemoSeeder
 *
 * Contraseña: variable HV_DEMO_PASSWORD o, si no existe, «Demo-HV-CRM-2026!».
 * Los precios de los planes son ILUSTRATIVOS: deben validarse comercialmente.
 */
class DemoSeeder extends Seeder
{
    public function run()
    {
        if (ENVIRONMENT === 'production') {
            throw new \RuntimeException('DemoSeeder no se ejecuta en producción.');
        }
        Rls::setBypass(true);
        Services::rbac()->sync();
        $db  = $this->db;
        $now = date('Y-m-d H:i:s');
        $pwd = getenv('HV_DEMO_PASSWORD') ?: 'Demo-HV-CRM-2026!';

        if ($db->table('users')->where('email', 'superadmin@hv.test')->countAllResults()) {
            echo "Demo ya sembrada.\n";

            return;
        }

        // ---------------------------------------------------------- Planes (ilustrativos)
        $plans = [
            ['basico', 'Básico', 'company', '89000', 14, 3, ['crm_enabled' => 1, 'max_contacts' => 500, 'max_opportunities' => 200, 'form_enabled' => 1, 'max_forms' => 2, 'quote_enabled' => 0, 'max_quotes' => 0]],
            ['profesional', 'Profesional', 'company', '189000', 14, 10, ['crm_enabled' => 1, 'max_contacts' => 5000, 'max_opportunities' => 2000, 'form_enabled' => 1, 'max_forms' => 10, 'quote_enabled' => 1, 'max_quotes' => 500]],
            ['empresarial', 'Empresarial', 'company', '459000', 0, 0, ['crm_enabled' => 1, 'max_contacts' => 0, 'max_opportunities' => 0, 'form_enabled' => 1, 'max_forms' => 0, 'quote_enabled' => 1, 'max_quotes' => 0]],
            ['distribuidor', 'Distribuidor', 'tenant', '0', 0, 0, []],
        ];
        $planIds = [];
        foreach ($plans as [$code, $name, $target, $price, $trial, $users, $extra]) {
            $db->table('plans')->insert([
                'code' => $code, 'name' => $name, 'target' => $target, 'price' => $price, 'currency' => 'COP',
                'billing_period' => 'monthly', 'trial_days' => $trial, 'max_users' => $users, 'is_active' => 1, 'is_public' => 1,
                'description' => 'Plan de demostración: precio ilustrativo pendiente de validación comercial.',
                'created_at' => $now, 'updated_at' => $now,
            ] + $extra);
            $planIds[$code] = (int) $db->insertID();
        }

        // ---------------------------------------------------------- Usuarios
        $mk = function (string $email, string $name) use ($db, $pwd, $now): int {
            $db->table('users')->insert([
                'public_id' => Ids::publicId(), 'name' => $name, 'email' => $email, 'password_hash' => AuthService::hashPassword($pwd),
                'status' => 'active', 'locale' => 'es', 'session_version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);

            return (int) $db->insertID();
        };
        $member = function (int $uid, string $scope, ?int $tid, ?int $cid, string $role) use ($db, $now): void {
            $db->table('memberships')->insert([
                'user_id' => $uid, 'scope' => $scope, 'tenant_id' => $tid, 'company_id' => $cid,
                'role_id' => Services::rbac()->systemRoleId($role), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        $super = $mk('superadmin@hv.test', 'Superadministrador HV');
        $member($super, 'platform', null, null, 'platform_owner');
        $support = $mk('soporte@hv.test', 'Soporte HV');
        $member($support, 'platform', null, null, 'platform_support');

        // ---------------------------------------------------------- Distribuidor
        $db->table('tenants')->insert([
            'public_id' => Ids::publicId(), 'name' => 'Distribuidor Demo Caribe', 'slug' => 'distribuidor-demo', 'status' => 'active',
            'brand_name' => 'Caribe CRM', 'brand_color' => '#0E7490', 'commission_rate' => '0.2000', 'contact_email' => 'distribuidor@hv.test',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $tenantId = (int) $db->insertID();
        $dist = $mk('distribuidor@hv.test', 'Laura Distribuidora');
        $member($dist, 'tenant', $tenantId, null, 'tenant_owner');

        // ---------------------------------------------------------- Negocios
        $company = function (string $name, string $slug, ?int $tid) use ($db, $now): int {
            $db->table('companies')->insert([
                'public_id' => Ids::publicId(), 'tenant_id' => $tid, 'name' => $name, 'slug' => $slug, 'status' => 'active',
                'timezone' => 'America/Bogota', 'currency' => 'COP', 'locale' => 'es', 'created_at' => $now, 'updated_at' => $now,
            ]);

            return (int) $db->insertID();
        };
        $hv = $company('Herramientas Virtuales CO', 'herramientas-virtuales', null);
        $a1 = $company('Comercializadora San Victorino', 'comercializadora-san-victorino', $tenantId);
        $a2 = $company('Inmobiliaria Andes', 'inmobiliaria-andes', $tenantId);

        $owner = $mk('gerente@hv.test', 'Camila Gerente HV');
        $member($owner, 'company', null, $hv, 'business_owner');
        $agent = $mk('asesor@hv.test', 'Andrés Asesor HV');
        $member($agent, 'company', null, $hv, 'sales_agent');
        $member($dist, 'company', null, $a1, 'viewer'); // misma persona, otro ámbito: datos separados
        $ownerA1 = $mk('negocio@hv.test', 'Pedro San Victorino');
        $member($ownerA1, 'company', null, $a1, 'business_owner');
        $ownerA2 = $mk('andes@hv.test', 'Sofía Andes');
        $member($ownerA2, 'company', null, $a2, 'business_owner');

        $subs = Services::subscriptions();
        $r = $subs->assignPlan($hv, $planIds['empresarial'], null, $super); // sin prueba: nace pendiente de pago
        $subs->registerPayment((int) $r->data['id'], '459000.00', 'transfer', 'DEMO-TRX-HV-0001', $super);
        $r = $subs->assignPlan($a1, $planIds['profesional'], $tenantId, $super);
        $subs->transition((int) $r->data['id'], 'active', $super);
        $subs->registerPayment((int) $r->data['id'], '189000.00', 'transfer', 'DEMO-TRX-0001', $super);
        $subs->assignPlan($a2, $planIds['basico'], $tenantId, $super);

        // ---------------------------------------------------------- CRM de Herramientas Virtuales
        Rls::setCompany($hv);
        $setup = service('crmSetup');
        $setup->ensureDefaults($hv);
        $projects = [];
        foreach (['Vitrina Digital San Victorino', 'Carnetización Digital', 'Salud.red', 'Vitrina Inmobiliaria', 'Leads Lawyers', 'Desarrollo y consultoría TI'] as $p) {
            $projects[] = (int) $setup->saveProject($hv, ['name' => $p])->data['id'];
        }
        $catalog = service('quoteCatalog');
        $svc = [];
        foreach ([['Vitrina Digital Profesional', '149000', 'monthly'], ['Carnet digital por persona', '12000', 'one_time'], ['Consultoría TI (hora)', '180000', 'one_time']] as [$n, $p, $b]) {
            $svc[] = (int) $catalog->save($hv, ['name' => $n, 'unit_price' => $p, 'tax_percent' => '19', 'currency' => 'COP', 'billing' => $b, 'is_active' => 1])->data['id'];
        }

        $contacts = service('crmContacts');
        $opps     = service('crmOpportunities');
        $people = [
            ['María', 'Gómez', 'Almacén La 10', 'maria@almacenla10.test', '3001234567', 0, '1500000'],
            ['Jorge', 'Ruiz', 'Colegio Nuevo Horizonte', 'jorge@nuevohorizonte.test', '3109876543', 1, '4800000'],
            ['Paula', 'Mendoza', 'IPS Vida Sana', 'paula@vidasana.test', '3205550000', 2, '9200000'],
            ['Ricardo', 'Lozano', 'Lozano Abogados', 'ricardo@lozanoabogados.test', '3157771111', 4, '2400000'],
        ];
        $stages = array_values(array_filter($setup->stages($hv), static fn ($s) => $s['kind'] === 'open'));
        foreach ($people as $i => [$fn, $ln, $org, $email, $phone, $proj, $amount]) {
            $c = $contacts->create($hv, ['first_name' => $fn, 'last_name' => $ln, 'organization' => $org, 'email' => $email, 'phone' => $phone, 'city' => 'Bogotá', 'consent_marketing' => 1], $owner);
            $o = $opps->create($hv, [
                'title' => $org . ' — ' . ['Vitrina digital', 'Carnetización', 'Salud.red', 'Leads'][$i],
                'contact_id' => $c->data['id'], 'amount' => $amount, 'project_id' => (string) $projects[$proj],
                'next_action' => 'Llamar para seguimiento', 'next_action_at' => date('Y-m-d\TH:i', strtotime('+' . ($i + 1) . ' days 10:00')),
            ], $i % 2 ? $agent : $owner);
            $opps->move($hv, (int) $o->data['id'], (int) $stages[min($i, count($stages) - 1)]['id'], $i % 2 ? $agent : $owner);
        }

        // Formulario publicado de captación
        $form = service('formForms')->save($hv, [
            'name' => 'Registro de comerciantes — Vitrina Digital', 'project_id' => (string) $projects[0],
            'description' => 'Déjenos sus datos y un asesor le contactará hoy.',
            'fields_json' => json_encode([
                ['key' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true, 'maps_to' => 'first_name'],
                ['key' => 'negocio', 'label' => 'Nombre del negocio', 'type' => 'text', 'required' => true, 'maps_to' => 'organization'],
                ['key' => 'correo', 'label' => 'Correo', 'type' => 'email', 'required' => true, 'maps_to' => 'email'],
                ['key' => 'celular', 'label' => 'Celular', 'type' => 'phone', 'required' => false, 'maps_to' => 'phone'],
                ['key' => 'tipo', 'label' => 'Tipo de local', 'type' => 'select', 'required' => false, 'maps_to' => 'none', 'options' => ['Local', 'Bodega', 'Oficina']],
            ]),
            'consent_text' => 'Autorizo el tratamiento de mis datos personales conforme a la política de privacidad de Herramientas Virtuales (Ley 1581 de 2012).',
            'create_opportunity' => 1,
            'send_ack'           => 1,
        ]);
        service('formForms')->publish($hv, (int) $form->data['id']);
        Rls::setBypass(true);

        echo "Demo sembrada. Contraseña de todos los usuarios: {$pwd}\n";
        echo "  superadmin@hv.test (MFA obligatorio al primer ingreso) · soporte@hv.test\n";
        echo "  distribuidor@hv.test (distribuidor + consulta en San Victorino)\n";
        echo "  gerente@hv.test · asesor@hv.test (Herramientas Virtuales) · negocio@hv.test · andes@hv.test\n";
    }
}
