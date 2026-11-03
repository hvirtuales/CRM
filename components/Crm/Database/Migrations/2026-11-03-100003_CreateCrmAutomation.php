<?php

namespace Components\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Automatización comercial del CRM:
 * - cmp_crm_project_members: equipo comercial por proyecto (reparto rotativo de leads).
 * - cmp_crm_settings: umbrales de seguimiento por negocio.
 * - cmp_crm_opportunities.last_activity_at: base de las alertas de oportunidades sin actividad.
 */
class CreateCrmAutomation extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache(); // la caché de tablas/columnas del driver puede estar obsoleta
        if (! $this->db->tableExists('cmp_crm_project_members', false)) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'       => ['type' => 'INT', 'unsigned' => true],
                'project_id'       => ['type' => 'INT', 'unsigned' => true],
                'user_id'          => ['type' => 'INT', 'unsigned' => true],
                'last_assigned_at' => ['type' => 'DATETIME', 'null' => true],
                'last_turn'        => ['type' => 'BIGINT', 'null' => true], // turno monotónico: desempate exacto
                'assigned_count'   => ['type' => 'INT', 'default' => 0],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['project_id', 'user_id']);
            $this->forge->addKey(['company_id', 'project_id']);
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('project_id', 'cmp_crm_projects', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_crm_project_members', true);
        }

        if (! $this->db->tableExists('cmp_crm_settings', false)) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'company_id'    => ['type' => 'INT', 'unsigned' => true],
                'stale_days'    => ['type' => 'INT', 'default' => 7],   // sin actividad ⇒ alerta al responsable
                'escalate_days' => ['type' => 'INT', 'default' => 14],  // sin actividad ⇒ escala al gerente
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('company_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
            $this->forge->createTable('cmp_crm_settings', true);
        }

        if (! $this->db->fieldExists('last_activity_at', 'cmp_crm_opportunities')) {
            $this->forge->addColumn('cmp_crm_opportunities', ['last_activity_at' => ['type' => 'DATETIME', 'null' => true]]);
            // Respaldo: última actividad registrada o, si no hay, la creación.
            $rows = $this->db->table('cmp_crm_opportunities')->select('id, created_at')->get()->getResultArray();
            foreach ($rows as $r) {
                $last = $this->db->table('cmp_crm_activities')->selectMax('created_at', 'm')->where('opportunity_id', $r['id'])->get()->getRow()->m ?? null;
                $this->db->table('cmp_crm_opportunities')->where('id', $r['id'])->update(['last_activity_at' => $last ?? $r['created_at']]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('cmp_crm_project_members', true);
        $this->forge->dropTable('cmp_crm_settings', true);
        if ($this->db->fieldExists('last_activity_at', 'cmp_crm_opportunities')) {
            $this->forge->dropColumn('cmp_crm_opportunities', 'last_activity_at');
        }
    }
}
