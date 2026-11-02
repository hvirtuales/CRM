<?php

namespace App\Commands;

use App\Libraries\Rls;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Proceso diario (cron/systemd timer): vence pruebas y periodos, aplica gracia y suspensión.
 *   15 2 * * * cd /var/www/hv-crm && php spark hv:lifecycle >> writable/logs/lifecycle.log 2>&1
 */
class HvLifecycle extends BaseCommand
{
    protected $group       = 'HV CRM';
    protected $name        = 'hv:lifecycle';
    protected $description = 'Aplica el ciclo de vida de suscripciones (past_due, suspensión).';

    public function run(array $params)
    {
        Rls::setBypass(true);
        $lock = WRITEPATH . 'hv-lifecycle.lock';
        $fh   = fopen($lock, 'c');
        if (! $fh || ! flock($fh, LOCK_EX | LOCK_NB)) {
            CLI::error('Otra ejecución está en curso.');

            return EXIT_ERROR;
        }
        $stats = Services::subscriptions()->runLifecycle();
        CLI::write(json_encode(['at' => gmdate('c')] + $stats));
        flock($fh, LOCK_UN);

        return EXIT_SUCCESS;
    }
}
