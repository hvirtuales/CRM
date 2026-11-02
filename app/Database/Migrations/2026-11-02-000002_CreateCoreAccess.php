<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Núcleo: RBAC contextual (roles por ámbito, permisos, membresías), accesos de soporte y tokens API.
 */
class CreateCoreAccess extends Migration
{
    public function up(): void
    {
        $ts = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if (! $this->db->tableExists('roles')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code'        => ['type' => 'VARCHAR', 'constraint' => 60],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
                'scope'       => ['type' => 'VARCHAR', 'constraint' => 10], // platform|tenant|company
                'company_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true], // rol personalizado de un negocio
                'is_system'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['scope', 'code']);
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('roles', true);
        }

        if (! $this->db->tableExists('permissions')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'description' => ['type' => 'VARCHAR', 'constraint' => 190],
                'scope'       => ['type' => 'VARCHAR', 'constraint' => 10],
                'component'   => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true], // NULL = núcleo
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('permissions', true);
        }

        if (! $this->db->tableExists('role_permissions')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'role_id'       => ['type' => 'INT', 'unsigned' => true],
                'permission_id' => ['type' => 'INT', 'unsigned' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['role_id', 'permission_id']);
            $this->forge->addForeignKey('role_id', 'roles', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('permission_id', 'permissions', 'id', '', 'CASCADE');
            $this->forge->createTable('role_permissions', true);
        }

        if (! $this->db->tableExists('memberships')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'    => ['type' => 'INT', 'unsigned' => true],
                'scope'      => ['type' => 'VARCHAR', 'constraint' => 10], // platform|tenant|company
                'tenant_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'role_id'    => ['type' => 'INT', 'unsigned' => true],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active|suspended
                'invited_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey('user_id');
            $this->forge->addKey(['scope', 'tenant_id']);
            $this->forge->addKey(['scope', 'company_id']);
            $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('role_id', 'roles', 'id', '', 'RESTRICT');
            $this->forge->createTable('memberships', true);
        }

        if (! $this->db->tableExists('support_grants')) {
            // Acceso temporal de soporte de plataforma a un negocio: justificado, aprobado por el negocio, con vencimiento.
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'      => ['type' => 'INT', 'unsigned' => true],
                'company_id'   => ['type' => 'INT', 'unsigned' => true],
                'reason'       => ['type' => 'TEXT'],
                'ticket_ref'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'], // pending|approved|rejected|revoked
                'hours'        => ['type' => 'INT', 'default' => 2],
                'decided_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'decided_at'   => ['type' => 'DATETIME', 'null' => true],
                'expires_at'   => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('support_grants', true);
        }

        if (! $this->db->tableExists('api_tokens')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'       => ['type' => 'INT', 'unsigned' => true],
                'membership_id' => ['type' => 'INT', 'unsigned' => true],
                'company_id'    => ['type' => 'INT', 'unsigned' => true],
                'name'          => ['type' => 'VARCHAR', 'constraint' => 100],
                'token_hash'    => ['type' => 'VARCHAR', 'constraint' => 64], // SHA-256; el token solo se muestra una vez
                'abilities'     => ['type' => 'TEXT'], // JSON: subconjunto de permisos de la membresía
                'last_used_at'  => ['type' => 'DATETIME', 'null' => true],
                'expires_at'    => ['type' => 'DATETIME', 'null' => true],
                'revoked_at'    => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('token_hash');
            $this->forge->addKey('company_id');
            $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('membership_id', 'memberships', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('api_tokens', true);
        }
    }

    public function down(): void
    {
        foreach (['api_tokens', 'support_grants', 'memberships', 'role_permissions', 'permissions', 'roles'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
