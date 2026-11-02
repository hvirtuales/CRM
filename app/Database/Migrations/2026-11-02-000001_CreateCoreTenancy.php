<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Núcleo: identidades, distribuidores (tenants), negocios (companies) y planes.
 *
 * Portabilidad (MySQL/PostgreSQL/SQL Server): Forge, sin ENUM ni JSON nativo.
 * Negocio directo = companies.tenant_id NULL (decisión única, ver docs/adr/0002).
 */
class CreateCoreTenancy extends Migration
{
    public function up(): void
    {
        $ts = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if (! $this->db->tableExists('plans')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code'           => ['type' => 'VARCHAR', 'constraint' => 40],
                'name'           => ['type' => 'VARCHAR', 'constraint' => 120],
                'description'    => ['type' => 'TEXT', 'null' => true],
                'target'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'company'], // company|tenant
                'price'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency'       => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
                'billing_period' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'monthly'], // monthly|yearly
                'trial_days'     => ['type' => 'INT', 'default' => 0],
                'max_users'      => ['type' => 'INT', 'default' => 0], // 0 = sin límite
                'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'is_public'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], // vendible por distribuidores
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('plans', true);
        }

        if (! $this->db->tableExists('users')) {
            $this->forge->addField([
                'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'public_id'            => ['type' => 'VARCHAR', 'constraint' => 32],
                'name'                 => ['type' => 'VARCHAR', 'constraint' => 120],
                'email'                => ['type' => 'VARCHAR', 'constraint' => 190],
                'password_hash'        => ['type' => 'VARCHAR', 'constraint' => 255],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active|suspended
                'locale'               => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'es'],
                'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'mfa_secret'           => ['type' => 'TEXT', 'null' => true], // cifrado con Encryption
                'mfa_enabled'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'session_version'      => ['type' => 'INT', 'default' => 1], // invalida sesiones al cambiar clave/suspender
                'last_login_at'        => ['type' => 'DATETIME', 'null' => true],
                'password_changed_at'  => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('email');
            $this->forge->addUniqueKey('public_id');
            $this->forge->createTable('users', true);
        }

        if (! $this->db->tableExists('tenants')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'public_id'       => ['type' => 'VARCHAR', 'constraint' => 32],
                'name'            => ['type' => 'VARCHAR', 'constraint' => 150],
                'slug'            => ['type' => 'VARCHAR', 'constraint' => 80],
                'tax_id'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'contact_email'   => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active|suspended|archived
                'brand_name'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'brand_color'     => ['type' => 'VARCHAR', 'constraint' => 7, 'null' => true],
                'commission_rate' => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0], // 0.2000 = 20 %
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('slug');
            $this->forge->addUniqueKey('public_id');
            $this->forge->createTable('tenants', true);
        }

        if (! $this->db->tableExists('companies')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'public_id'  => ['type' => 'VARCHAR', 'constraint' => 32],
                'tenant_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true], // NULL = negocio directo
                'plan_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true], // plan vigente (lo escribe SubscriptionService)
                'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
                'slug'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'tax_id'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'phone'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active|suspended|archived
                'timezone'   => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'America/Bogota'],
                'currency'   => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
                'locale'     => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'es'],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('slug');
            $this->forge->addUniqueKey('public_id');
            $this->forge->addKey('tenant_id');
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', '', 'RESTRICT');
            $this->forge->addForeignKey('plan_id', 'plans', 'id', '', 'SET NULL');
            $this->forge->createTable('companies', true);
        }

        if (! $this->db->tableExists('company_transfers')) {
            // Historial de traslados de negocio entre distribuidores (operación privilegiada).
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'     => ['type' => 'INT', 'unsigned' => true],
                'from_tenant_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'to_tenant_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'reason'         => ['type' => 'TEXT'],
                'actor_user_id'  => ['type' => 'INT', 'unsigned' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('company_transfers', true);
        }
    }

    public function down(): void
    {
        foreach (['company_transfers', 'companies', 'tenants', 'users', 'plans'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
