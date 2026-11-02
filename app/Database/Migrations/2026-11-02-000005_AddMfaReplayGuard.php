<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * MFA: último paso TOTP aceptado (anti-reutilización del mismo código) y fallos consecutivos.
 */
class AddMfaReplayGuard extends Migration
{
    public function up(): void
    {
        $fields = [];
        if (! $this->db->fieldExists('mfa_last_step', 'users')) {
            $fields['mfa_last_step'] = ['type' => 'BIGINT', 'null' => true];
        }
        if (! $this->db->fieldExists('mfa_failures', 'users')) {
            $fields['mfa_failures'] = ['type' => 'INT', 'default' => 0];
        }
        if ($fields) {
            $this->forge->addColumn('users', $fields);
        }
    }

    public function down(): void
    {
        foreach (['mfa_last_step', 'mfa_failures'] as $c) {
            if ($this->db->fieldExists($c, 'users')) {
                $this->forge->dropColumn('users', $c);
            }
        }
    }
}
