<?php

namespace Components\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tablas del CRM. Portables (Forge), idempotentes, con company_id en todas.
 */
class CreateCrmTables extends Migration
{
    public function up(): void
    {
        $ts = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if (! $this->db->tableExists('cmp_crm_projects')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'  => ['type' => 'INT', 'unsigned' => true],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
                'code'        => ['type' => 'VARCHAR', 'constraint' => 60],
                'description' => ['type' => 'TEXT', 'null' => true],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active|archived
                'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['company_id', 'code']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_crm_projects', true);
        }

        if (! $this->db->tableExists('cmp_crm_stages')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'  => ['type' => 'INT', 'unsigned' => true],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 80],
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'open'], // open|won|lost
                'probability' => ['type' => 'INT', 'default' => 0],
                'position'    => ['type' => 'INT', 'default' => 0],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'position']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_crm_stages', true);
        }

        if (! $this->db->tableExists('cmp_crm_contacts')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'       => ['type' => 'INT', 'unsigned' => true],
                'kind'             => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'person'], // person|organization
                'first_name'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'last_name'        => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'organization'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'email'            => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'phone'            => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'phone_normalized' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // deduplicación
                'city'             => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'source'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual'], // manual|form|api|import
                'owner_user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'consent_marketing'=> ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'consent_at'       => ['type' => 'DATETIME', 'null' => true],
                'consent_source'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'notes'            => ['type' => 'TEXT', 'null' => true],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'], // active|archived
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'email']);
            $this->forge->addKey(['company_id', 'phone_normalized']);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('owner_user_id', 'users', 'id', '', 'SET NULL');
            $this->forge->createTable('cmp_crm_contacts', true);
        }

        if (! $this->db->tableExists('cmp_crm_opportunities')) {
            $this->forge->addField([
                'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'          => ['type' => 'INT', 'unsigned' => true],
                'project_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'contact_id'          => ['type' => 'INT', 'unsigned' => true],
                'stage_id'            => ['type' => 'INT', 'unsigned' => true],
                'title'               => ['type' => 'VARCHAR', 'constraint' => 150],
                'amount'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency'            => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
                'probability'         => ['type' => 'INT', 'default' => 0],
                'owner_user_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'source'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual'],
                'expected_close_date' => ['type' => 'DATE', 'null' => true],
                'next_action'         => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'next_action_at'      => ['type' => 'DATETIME', 'null' => true],
                'lost_reason'         => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'closed_at'           => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'stage_id']);
            $this->forge->addKey(['company_id', 'owner_user_id']);
            $this->forge->addKey(['company_id', 'contact_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('contact_id', 'cmp_crm_contacts', 'id', '', 'RESTRICT');
            $this->forge->addForeignKey('stage_id', 'cmp_crm_stages', 'id', '', 'RESTRICT');
            $this->forge->addForeignKey('project_id', 'cmp_crm_projects', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('owner_user_id', 'users', 'id', '', 'SET NULL');
            $this->forge->createTable('cmp_crm_opportunities', true);
        }

        if (! $this->db->tableExists('cmp_crm_activities')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'     => ['type' => 'INT', 'unsigned' => true],
                'opportunity_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'contact_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'type'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'note'], // note|call|email|meeting|whatsapp|stage|system
                'body'           => ['type' => 'TEXT'],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'opportunity_id']);
            $this->forge->addKey(['company_id', 'contact_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('opportunity_id', 'cmp_crm_opportunities', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('contact_id', 'cmp_crm_contacts', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');
            $this->forge->createTable('cmp_crm_activities', true);
        }
    }

    public function down(): void
    {
        foreach (['cmp_crm_activities', 'cmp_crm_opportunities', 'cmp_crm_contacts', 'cmp_crm_stages', 'cmp_crm_projects'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
