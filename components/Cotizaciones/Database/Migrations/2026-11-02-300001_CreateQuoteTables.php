<?php

namespace Components\Cotizaciones\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuoteTables extends Migration
{
    public function up(): void
    {
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        if (! $this->db->tableExists('cmp_quote_services')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'  => ['type' => 'INT', 'unsigned' => true],
                'sku'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
                'description' => ['type' => 'TEXT', 'null' => true],
                'unit_price'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'currency'    => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
                'tax_rate'    => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0], // 0.1900 = IVA 19 %
                'billing'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'one_time'], // one_time|monthly|yearly
                'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'is_active']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_quote_services', true);
        }

        if (! $this->db->tableExists('cmp_quote_quotes')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'     => ['type' => 'INT', 'unsigned' => true],
                'public_id'      => ['type' => 'VARCHAR', 'constraint' => 32],
                'number'         => ['type' => 'VARCHAR', 'constraint' => 30],
                'opportunity_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true], // CRM (validado por servicio)
                'contact_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'], // draft|sent|accepted|rejected|void
                'currency'       => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'COP'],
                'subtotal'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'tax_total'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'total'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'valid_until'    => ['type' => 'DATE', 'null' => true],
                'notes'          => ['type' => 'TEXT', 'null' => true],
                'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'sent_at'        => ['type' => 'DATETIME', 'null' => true],
                'decided_at'     => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            ] + $ts);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('public_id');
            $this->forge->addUniqueKey(['company_id', 'number']);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
            $this->forge->createTable('cmp_quote_quotes', true);
        }

        if (! $this->db->tableExists('cmp_quote_lines')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'  => ['type' => 'INT', 'unsigned' => true],
                'quote_id'    => ['type' => 'INT', 'unsigned' => true],
                'service_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255],
                'quantity'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 1],
                'unit_price'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'tax_rate'    => ['type' => 'DECIMAL', 'constraint' => '7,4', 'default' => 0],
                'line_total'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
                'position'    => ['type' => 'INT', 'default' => 0],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'quote_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('quote_id', 'cmp_quote_quotes', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('service_id', 'cmp_quote_services', 'id', '', 'SET NULL');
            $this->forge->createTable('cmp_quote_lines', true);
        }
    }

    public function down(): void
    {
        foreach (['cmp_quote_lines', 'cmp_quote_quotes', 'cmp_quote_services'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
