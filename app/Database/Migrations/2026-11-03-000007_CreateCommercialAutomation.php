<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * - commercial_signals: idempotencia de las oportunidades automáticas (una por señal).
 * - commission_settlements: extractos mensuales de comisiones por distribuidor y moneda.
 */
class CreateCommercialAutomation extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache(); // la caché de tablas/columnas del driver puede estar obsoleta
        if (! $this->db->tableExists('commercial_signals', false)) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'signal_key'     => ['type' => 'VARCHAR', 'constraint' => 120],
                'kind'           => ['type' => 'VARCHAR', 'constraint' => 20], // renewal|trial|upsell
                'company_id'     => ['type' => 'INT', 'unsigned' => true],  // negocio al que se refiere la señal
                'opportunity_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('signal_key');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('commercial_signals', true);
        }

        if (! $this->db->tableExists('commission_settlements', false)) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'         => ['type' => 'INT', 'unsigned' => true],
                'period'            => ['type' => 'VARCHAR', 'constraint' => 7], // AAAA-MM
                'currency'          => ['type' => 'VARCHAR', 'constraint' => 3],
                'total'             => ['type' => 'DECIMAL', 'constraint' => '15,2'],
                'commissions_count' => ['type' => 'INT', 'default' => 0],
                'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|paid
                'reference'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'paid_at'           => ['type' => 'DATETIME', 'null' => true],
                'paid_by'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['tenant_id', 'period', 'currency']);
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', '', 'RESTRICT');
            $this->forge->createTable('commission_settlements', true);
        }

        if (! $this->db->fieldExists('settlement_id', 'commissions')) {
            $this->forge->addColumn('commissions', ['settlement_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true]]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('settlement_id', 'commissions')) {
            $this->forge->dropColumn('commissions', 'settlement_id');
        }
        $this->forge->dropTable('commission_settlements', true);
        $this->forge->dropTable('commercial_signals', true);
    }
}
