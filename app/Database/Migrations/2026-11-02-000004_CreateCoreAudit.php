<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Núcleo: auditoría, intentos de acceso y registro de componentes instalados.
 */
class CreateCoreAudit extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('audit_events', false)) {
            $this->forge->addField([
                'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'occurred_at'   => ['type' => 'DATETIME'],
                'actor_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'scope'         => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'tenant_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'company_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'action'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'entity_type'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'entity_id'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'ip'            => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'user_agent'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'request_id'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'data'          => ['type' => 'TEXT', 'null' => true], // JSON redactado
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'occurred_at']);
            $this->forge->addKey(['tenant_id', 'occurred_at']);
            $this->forge->addKey('action');
            $this->forge->createTable('audit_events', true);
        }

        if (! $this->db->tableExists('login_attempts', false)) {
            $this->forge->addField([
                'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'email_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
                'ip'         => ['type' => 'VARCHAR', 'constraint' => 45],
                'success'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['email_hash', 'created_at']);
            $this->forge->addKey(['ip', 'created_at']);
            $this->forge->createTable('login_attempts', true);
        }

        if (! $this->db->tableExists('components', false)) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code'         => ['type' => 'VARCHAR', 'constraint' => 8],
                'name'         => ['type' => 'VARCHAR', 'constraint' => 120],
                'folder'       => ['type' => 'VARCHAR', 'constraint' => 60],
                'version'      => ['type' => 'VARCHAR', 'constraint' => 20],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'enabled'], // enabled|disabled
                'checksum'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'installed_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('components', true);
        }
    }

    public function down(): void
    {
        foreach (['components', 'login_attempts', 'audit_events'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
