<?php

namespace App\Commands;

use App\Libraries\Rls;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class HvComponents extends BaseCommand
{
    protected $group       = 'HV CRM';
    protected $name        = 'hv:components';
    protected $description = 'Lista, sincroniza, habilita o deshabilita componentes.';
    protected $usage       = 'hv:components [list|sync|enable <code>|disable <code>]';

    public function run(array $params)
    {
        Rls::setBypass(true);
        $manager = Services::components();
        $action  = $params[0] ?? 'list';

        switch ($action) {
            case 'sync':
                foreach ($manager->sync() as $line) {
                    CLI::write('  ✓ ' . $line, 'green');
                }
                break;

            case 'enable':
            case 'disable':
                $r = $manager->setStatus((string) ($params[1] ?? ''), $action === 'enable');
                CLI::write($r->ok ? 'OK' : (string) $r->message, $r->ok ? 'green' : 'red');
                break;

            default:
                $rows = [];
                foreach ($manager->manifests() as $code => $m) {
                    $rows[] = [$code, $m['name'], $m['version'], $m['scope'] ?? 'company', $manager->isEnabled($code) ? 'enabled' : 'disabled', implode(',', $m['depends'] ?? [])];
                }
                CLI::table($rows, ['code', 'nombre', 'versión', 'ámbito', 'estado', 'depende de']);
        }
    }
}
