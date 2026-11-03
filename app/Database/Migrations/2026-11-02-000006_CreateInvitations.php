<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Invitaciones: una membresía solo se crea cuando el TITULAR del correo acepta un enlace de un solo
 * uso. Ningún administrador ve ni fija contraseñas de terceros.
 */
class CreateInvitations extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('invitations', false)) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'token_hash'  => ['type' => 'VARCHAR', 'constraint' => 64],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 190],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'scope'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'role_id'     => ['type' => 'INT', 'unsigned' => true],
            'invited_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'expires_at'  => ['type' => 'DATETIME'],
            'accepted_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['scope', 'company_id']);
        $this->forge->addKey(['scope', 'tenant_id']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', '', 'CASCADE');
        $this->forge->createTable('invitations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('invitations', true);
    }
}
