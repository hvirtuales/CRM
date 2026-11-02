<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFormColumnsToPlans extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }
        $fields = [];
        if (! $this->db->fieldExists('form_enabled', 'plans')) {
            $fields['form_enabled'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (! $this->db->fieldExists('max_forms', 'plans')) {
            $fields['max_forms'] = ['type' => 'INT', 'default' => 0];
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
        foreach (['form_enabled', 'max_forms'] as $col) {
            if ($this->db->fieldExists($col, 'plans')) {
                $this->forge->dropColumn('plans', $col);
            }
        }
    }
}
