<?php

namespace Components\Formularios\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFormTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('cmp_form_forms', false)) {
            $this->forge->addField([
                'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'         => ['type' => 'INT', 'unsigned' => true],
                'public_id'          => ['type' => 'VARCHAR', 'constraint' => 32], // opaco, no secuencial
                'project_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true], // proyecto del CRM (validado por servicio)
                'name'               => ['type' => 'VARCHAR', 'constraint' => 120],
                'description'        => ['type' => 'TEXT', 'null' => true],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'], // draft|published|closed
                'fields'             => ['type' => 'TEXT'], // JSON borrador
                'published_fields'   => ['type' => 'TEXT', 'null' => true], // JSON versión publicada (inmutable)
                'version'            => ['type' => 'INT', 'default' => 0],
                'consent_text'       => ['type' => 'TEXT', 'null' => true],
                'success_message'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'embed_domains'      => ['type' => 'TEXT', 'null' => true], // JSON lista de hosts
                'create_opportunity' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'submissions_count'  => ['type' => 'INT', 'default' => 0],
                'published_at'       => ['type' => 'DATETIME', 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('public_id');
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_form_forms', true);
        }

        if (! $this->db->tableExists('cmp_form_submissions', false)) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'      => ['type' => 'INT', 'unsigned' => true],
                'form_id'         => ['type' => 'INT', 'unsigned' => true],
                'form_version'    => ['type' => 'INT'],
                'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 64],
                'data'            => ['type' => 'TEXT'], // JSON respuestas validadas
                'contact_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'opportunity_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'consent'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'utm'             => ['type' => 'TEXT', 'null' => true],
                'ip_hash'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true], // HMAC de la IP (minimización)
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['form_id', 'idempotency_key']); // reenvíos no duplican
            $this->forge->addKey(['company_id', 'form_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('form_id', 'cmp_form_forms', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_form_submissions', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('cmp_form_submissions', true);
        $this->forge->dropTable('cmp_form_forms', true);
    }
}
