<?php

namespace Components\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Columnas propias del CRM en `plans` (única tabla base que el contrato permite ampliar).
 * crm_enabled (1 = incluido), max_contacts y max_opportunities (0 = ilimitado).
 */
class AddCrmColumnsToPlans extends Migration
{
    private const COLUMNS = ['crm_enabled', 'max_contacts', 'max_opportunities'];

    public function up(): void
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }
        $fields = [];
        if (! $this->db->fieldExists('crm_enabled', 'plans')) {
            $fields['crm_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (! $this->db->fieldExists('max_contacts', 'plans')) {
            $fields['max_contacts'] = ['type' => 'INT', 'default' => 0];
        }
        if (! $this->db->fieldExists('max_opportunities', 'plans')) {
            $fields['max_opportunities'] = ['type' => 'INT', 'default' => 0];
        }
        if ($fields) {
            $this->forge->addColumn('plans', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }
        foreach (self::COLUMNS as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
