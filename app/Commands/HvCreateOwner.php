<?php

namespace App\Commands;

use App\Libraries\AuthService;
use App\Libraries\Ids;
use App\Libraries\Rls;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Crea el propietario inicial de la plataforma (sin datos ficticios). La contraseña se pide
 * de forma interactiva o por la variable HV_OWNER_PASSWORD; nunca por argumento (queda en el historial).
 */
class HvCreateOwner extends BaseCommand
{
    protected $group       = 'HV CRM';
    protected $name        = 'hv:create-owner';
    protected $description = 'Crea el usuario propietario de la plataforma.';
    protected $usage       = 'hv:create-owner <email> <nombre>';

    public function run(array $params)
    {
        Rls::setBypass(true);
        $email = strtolower(trim((string) ($params[0] ?? CLI::prompt('Correo'))));
        $name  = trim((string) ($params[1] ?? CLI::prompt('Nombre')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            CLI::error('Correo o nombre inválido.');

            return EXIT_ERROR;
        }
        $password = (string) (getenv('HV_OWNER_PASSWORD') ?: CLI::prompt('Contraseña (mín. 12)'));
        if ($problem = Services::auth()->passwordProblem($password, $email)) {
            CLI::error($problem);

            return EXIT_ERROR;
        }

        $db = db_connect();
        if ($db->table('users')->where('email', $email)->countAllResults()) {
            CLI::error('Ya existe un usuario con ese correo.');

            return EXIT_ERROR;
        }
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('users')->insert([
            'public_id' => Ids::publicId(), 'name' => $name, 'email' => $email, 'password_hash' => AuthService::hashPassword($password),
            'status' => 'active', 'locale' => 'es', 'session_version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $uid = (int) $db->insertID();
        $db->table('memberships')->insert([
            'user_id' => $uid, 'scope' => 'platform', 'role_id' => Services::rbac()->systemRoleId('platform_owner'),
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->transComplete();
        Services::audit()->log('platform.owner_created', 'user', $uid);
        CLI::write('Propietario creado. Deberá configurar MFA en el primer ingreso.', 'green');

        return EXIT_SUCCESS;
    }
}
