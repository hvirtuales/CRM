<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bandeja de salida de correos transaccionales: cola idempotente (dedupe_key) con reintentos.
 * También autorregistro de negocios (signups) con verificación de correo.
 */
class CreateOutbox extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache();
        if (! $this->db->tableExists('outbox', false)) {
            $this->forge->addField([
                'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'dedupe_key'  => ['type' => 'VARCHAR', 'constraint' => 150],
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 40],  // lead_ack|quote_sent|dunning_*|invitation|signup_verify…
                'company_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'tenant_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'to_email'    => ['type' => 'VARCHAR', 'constraint' => 190],
                'subject'     => ['type' => 'VARCHAR', 'constraint' => 255],
                'body_html'   => ['type' => 'TEXT'],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'queued'], // queued|sent|failed
                'attempts'    => ['type' => 'INT', 'default' => 0],
                'last_error'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'send_after'  => ['type' => 'DATETIME'],
                'sent_at'     => ['type' => 'DATETIME', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('dedupe_key');
            $this->forge->addKey(['status', 'send_after']);
            $this->forge->addKey(['company_id', 'created_at']);
            $this->forge->createTable('outbox', true);
        }

        if (! $this->db->tableExists('signups', false)) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'token_hash'   => ['type' => 'VARCHAR', 'constraint' => 64],
                'email'        => ['type' => 'VARCHAR', 'constraint' => 190],
                'owner_name'   => ['type' => 'VARCHAR', 'constraint' => 120],
                'company_name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'phone'        => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'plan_id'      => ['type' => 'INT', 'unsigned' => true],
                'ip_hash'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|verified|cancelled
                'company_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'expires_at'   => ['type' => 'DATETIME'],
                'verified_at'  => ['type' => 'DATETIME', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('token_hash');
            $this->forge->addKey(['email', 'status']);
            $this->forge->createTable('signups', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('signups', true);
        $this->forge->dropTable('outbox', true);
    }
}
