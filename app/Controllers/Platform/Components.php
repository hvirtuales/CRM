<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use Config\Services;

/**
 * Componentes: la instalación de código la hace el despliegue (`php spark hv:components sync`);
 * aquí solo se habilitan/deshabilitan globalmente y se verifica integridad.
 */
class Components extends BaseController
{
    public function index()
    {
        $manager = Services::components();
        $rows    = array_column(db_connect()->table('components')->get()->getResultArray(), null, 'code');
        $list    = [];
        foreach ($manager->manifests() as $code => $m) {
            $installed = $rows[$code] ?? null;
            $list[] = [
                'code' => $code, 'name' => $m['name'], 'version' => $m['version'], 'description' => $m['description'] ?? '',
                'depends' => $m['depends'] ?? [], 'scope' => $m['scope'] ?? 'company',
                'permissions' => count($m['permissions'] ?? []),
                'installed' => $installed, 'enabled' => $manager->isEnabled($code),
                'pendingSync' => ! $installed || $installed['version'] !== $m['version'],
                'tampered' => $installed && $installed['checksum'] && ! hash_equals((string) $installed['checksum'], $manager->checksum($m['path'])),
            ];
        }

        return view('platform/components', ['components' => $list]);
    }

    public function status(string $code)
    {
        return $this->afterResult(Services::components()->setStatus($code, $this->request->getPost('enabled') === '1'), 'plataforma/componentes');
    }
}
