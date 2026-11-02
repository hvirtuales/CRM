<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Núcleo: suscripciones, pagos y comisiones de distribuidores.
 * No se almacenan datos de tarjetas ni bancarios: solo referencias del proveedor.
 */
class CreateCoreBilling extends Migration
{
    public function up(): void
    {
        $ts = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if (! $this->db->tableExists('subscriptions')) {
            $this->forge->addField([
                'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'subscriber_type'    => ['type' => 'VARCHAR', 'constraint' => 10], // company|tenant
                'company_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'tenant_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true], // suscriptor tenant
                'sold_by_tenant_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true], // distribuidor que vendió
                'plan_id'            => ['type' => 'INT', 'unsigned' => true],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'trial'], // trial|active|past_due|suspended|cancelled|archived
                'price'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency'           => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
                'billing_period'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'monthly'],
                'started_at'         => ['type' => 'DATETIME', 'null' => true],
                'trial_ends_at'      => ['type' => 'DATETIME', 'null' => true],
                'current_period_end' => ['type' => 'DATETIME', 'null' => true],
                'grace_days'         => ['type' => 'INT', 'default' => 5],
                'cancelled_at'       => ['type' => 'DATETIME', 'null' => true],
                'created_by'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey(['sold_by_tenant_id', 'status']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('plan_id', 'plans', 'id', '', 'RESTRICT');
            $this->forge->createTable('subscriptions', true);
        }

        if (! $this->db->tableExists('payments')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'subscription_id' => ['type' => 'INT', 'unsigned' => true],
                'amount'          => ['type' => 'DECIMAL', 'constraint' => '15,2'],
                'currency'        => ['type' => 'VARCHAR', 'constraint' => 3],
                'method'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual'], // manual|transfer|wompi|paypal
                'reference'       => ['type' => 'VARCHAR', 'constraint' => 100], // idempotencia por (method, reference)
                'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'approved'], // approved|void
                'period_start'    => ['type' => 'DATETIME', 'null' => true],
                'period_end'      => ['type' => 'DATETIME', 'null' => true],
                'paid_at'         => ['type' => 'DATETIME', 'null' => true],
                'recorded_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['method', 'reference']);
            $this->forge->addKey('subscription_id');
            $this->forge->addForeignKey('subscription_id', 'subscriptions', 'id', '', 'RESTRICT');
            $this->forge->createTable('payments', true);
        }

        if (! $this->db->tableExists('commissions')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
                'company_id'  => ['type' => 'INT', 'unsigned' => true],
                'payment_id'  => ['type' => 'INT', 'unsigned' => true],
                'base_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
                'rate'        => ['type' => 'DECIMAL', 'constraint' => '7,4'],
                'amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2'],
                'currency'    => ['type' => 'VARCHAR', 'constraint' => 3],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|approved|paid|void
                'paid_at'     => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('payment_id'); // una comisión por pago: reintentos no duplican
            $this->forge->addKey(['tenant_id', 'status']);
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', '', 'RESTRICT');
            $this->forge->addForeignKey('payment_id', 'payments', 'id', '', 'RESTRICT');
            $this->forge->createTable('commissions', true);
        }
    }

    public function down(): void
    {
        foreach (['commissions', 'payments', 'subscriptions'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
