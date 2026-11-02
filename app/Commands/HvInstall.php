<?php

namespace App\Commands;

use App\Libraries\Rls;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Instala o actualiza la plataforma: migraciones del núcleo, componentes, permisos/roles y RLS.
 * Idempotente: puede ejecutarse en cada despliegue.
 */
class HvInstall extends BaseCommand
{
    protected $group       = 'HV CRM';
    protected $name        = 'hv:install';
    protected $description = 'Migra el núcleo, sincroniza componentes, permisos, roles y políticas RLS.';

    public function run(array $params)
    {
        Rls::setBypass(true);
        foreach (Services::components()->sync() as $line) {
            CLI::write('  ✓ ' . $line, 'green');
        }
        CLI::write('Instalación completada.', 'green');
    }
}
