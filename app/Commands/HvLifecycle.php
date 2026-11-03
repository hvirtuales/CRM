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
    protected $description = 'Tareas comerciales diarias: ciclo de suscripciones, oportunidades de renovación/ampliación, extractos de comisiones y tareas de componentes.';

    public function run(array $params)
    {
        Rls::setBypass(true);
        $lock = WRITEPATH . 'hv-lifecycle.lock';
        $fh   = fopen($lock, 'c');
        if (! $fh || ! flock($fh, LOCK_EX | LOCK_NB)) {
            CLI::error('Otra ejecución está en curso.');

            return EXIT_ERROR;
        }
        $now    = time();
        $report = (object) ['now' => $now, 'results' => []];
        $report->results['subscriptions'] = Services::subscriptions()->runLifecycle($now);
        $report->results['dunning']       = (new \App\Libraries\DunningService())->run($now);
        $report->results['commercial']    = (new \App\Libraries\CommercialAutomation())->run($now);
        $report->results['commissions']   = (new \App\Libraries\CommissionService())->settleClosedMonths($now);
        // Los componentes agregan sus tareas diarias (p. ej. vencimiento de cotizaciones).
        \CodeIgniter\Events\Events::trigger('hv.daily', $report);
        $report->results['outbox'] = (new \App\Libraries\NotificationService())->deliverDue();
        CLI::write(json_encode(['at' => gmdate('c', $now)] + $report->results, JSON_UNESCAPED_UNICODE));
        flock($fh, LOCK_UN);

        return EXIT_SUCCESS;
    }
}
