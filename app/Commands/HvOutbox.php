<?php

namespace App\Commands;

use App\Libraries\NotificationService;
use App\Libraries\Rls;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Envía la bandeja de salida (cron cada 5 minutos):
 *   *\/5 * * * * cd /var/www/hv-crm && php spark hv:outbox >> writable/logs/outbox.log 2>&1
 */
class HvOutbox extends BaseCommand
{
    protected $group       = 'HV CRM';
    protected $name        = 'hv:outbox';
    protected $description = 'Envía los correos transaccionales pendientes con reintentos.';

    public function run(array $params)
    {
        Rls::setBypass(true);
        $fh = fopen(WRITEPATH . 'hv-outbox.lock', 'c');
        if (! $fh || ! flock($fh, LOCK_EX | LOCK_NB)) {
            CLI::error('Otra ejecución está en curso.');

            return EXIT_ERROR;
        }
        if (! NotificationService::emailConfigured()) {
            CLI::write('Correo saliente sin configurar (email.*): los mensajes permanecen en cola.', 'yellow');
        }
        CLI::write(json_encode(['at' => gmdate('c')] + (new NotificationService())->deliverDue()));
        flock($fh, LOCK_UN);

        return EXIT_SUCCESS;
    }
}
