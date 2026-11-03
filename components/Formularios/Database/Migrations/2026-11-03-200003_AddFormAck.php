<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Acuse de recibo automático al lead (correo transaccional), activable por formulario. */
class AddFormAck extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache();
        if (! $this->db->fieldExists('send_ack', 'cmp_form_forms')) {
            $this->forge->addColumn('cmp_form_forms', ['send_ack' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('send_ack', 'cmp_form_forms')) {
            $this->forge->dropColumn('cmp_form_forms', 'send_ack');
        }
    }
}
