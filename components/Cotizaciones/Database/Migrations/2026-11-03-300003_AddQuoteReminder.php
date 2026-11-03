<?php

namespace Components\Cotizaciones\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Marca de recordatorio previo al vencimiento (idempotencia de la tarea diaria). */
class AddQuoteReminder extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache(); // la caché de tablas/columnas del driver puede estar obsoleta
        if (! $this->db->fieldExists('reminded_at', 'cmp_quote_quotes')) {
            $this->forge->addColumn('cmp_quote_quotes', ['reminded_at' => ['type' => 'DATETIME', 'null' => true]]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('reminded_at', 'cmp_quote_quotes')) {
            $this->forge->dropColumn('cmp_quote_quotes', 'reminded_at');
        }
    }
}
