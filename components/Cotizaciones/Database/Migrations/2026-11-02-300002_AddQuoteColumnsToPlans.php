<?php

namespace Components\Cotizaciones\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQuoteColumnsToPlans extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }
        $fields = [];
        if (! $this->db->fieldExists('quote_enabled', 'plans')) {
            $fields['quote_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (! $this->db->fieldExists('max_quotes', 'plans')) {
            $fields['max_quotes'] = ['type' => 'INT', 'default' => 0];
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
        foreach (['quote_enabled', 'max_quotes'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
