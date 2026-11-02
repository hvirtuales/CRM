<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Imprime la superficie del núcleo que usa un componente (clases App\, vistas, helpers, filtros,
 * tablas base, sesión). Esa lista es lo que la instalación destino debe ofrecer.
 * También detecta incumplimientos evidentes del contrato (clases en App\, SQL crudo, CDN, texto sin lang()).
 */
class HvContract extends BaseCommand
{
    protected $group       = 'HV CRM';
    protected $name        = 'hv:contract';
    protected $description = 'Superficie del núcleo y chequeo de contrato de un componente.';
    protected $usage       = 'hv:contract <code>';

    public function run(array $params)
    {
        $m = Services::components()->manifest((string) ($params[0] ?? ''));
        if (! $m) {
            CLI::error('Componente no encontrado.');

            return EXIT_ERROR;
        }

        $surface = ['clases' => [], 'vistas' => [], 'filtros' => [], 'tablas' => [], 'sesion' => [], 'helpers' => []];
        $issues  = [];
        $it      = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($m['path'], \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            $rel = substr($f->getPathname(), strlen($m['path']));

            preg_match_all('/\b(App\\\\[A-Za-z\\\\]+|Config\\\\[A-Za-z]+)/', $src, $x);
            foreach ($x[1] as $c) {
                $surface['clases'][$c] = true;
            }
            preg_match_all("/view\\('((?!Components)[a-z_\\/]+)'/", $src, $x);
            foreach ($x[1] as $v) {
                $surface['vistas'][$v] = true;
            }
            preg_match_all("/'filter'\\s*=>\\s*'([a-z]+)/", $src, $x);
            foreach ($x[1] as $v) {
                $surface['filtros'][$v] = true;
            }
            preg_match_all("/(?:table\\(|join\\()'(users|companies|roles|plans|tenants)\\b/", $src, $x);
            foreach ($x[1] as $v) {
                $surface['tablas'][$v] = true;
            }
            preg_match_all("/session\\('([a-z_]+)'\\)/", $src, $x);
            foreach ($x[1] as $v) {
                $surface['sesion'][$v] = true;
            }
            preg_match_all('/\b(icon|money|num|dt|status_badge|field_error|auth_can|ctx)\(/', $src, $x);
            foreach ($x[1] as $v) {
                $surface['helpers'][$v] = true;
            }

            if (preg_match('/^namespace App\\\\/m', $src)) {
                $issues[] = "{$rel}: clase en namespace App\\";
            }
            if (preg_match('/->query\(\s*["\']\s*(SELECT|INSERT|UPDATE|DELETE)/i', $src)) {
                $issues[] = "{$rel}: SQL crudo";
            }
            if (preg_match('#https?://(cdn|unpkg|cdnjs)#', $src)) {
                $issues[] = "{$rel}: recurso CDN";
            }
            if (preg_match("/'type'\\s*=>\\s*'(ENUM|JSON)'/i", $src)) {
                $issues[] = "{$rel}: tipo no portable";
            }
        }

        CLI::write("Superficie del núcleo usada por {$m['code']} {$m['version']}:", 'yellow');
        foreach ($surface as $k => $items) {
            CLI::write(str_pad($k, 9) . ': ' . (implode(', ', array_keys($items)) ?: '—'));
        }
        if ($issues) {
            CLI::write('Incumplimientos:', 'red');
            foreach ($issues as $i) {
                CLI::write('  - ' . $i, 'red');
            }

            return EXIT_ERROR;
        }
        CLI::write('Sin incumplimientos detectados.', 'green');

        return EXIT_SUCCESS;
    }
}
