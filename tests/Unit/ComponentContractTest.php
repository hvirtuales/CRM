<?php

namespace Tests\Unit;

use Tests\Support\HvTestCase;
use Config\Services;

/**
 * Contrato de componentes: manifiestos válidos, permisos dentro del prefijo, idiomas completos,
 * sin clases en App\, sin SQL crudo ni CDN.
 */
final class ComponentContractTest extends HvTestCase
{
    public function testManifestValidationRejectsDangerousDeclarations(): void
    {
        $m = Services::components();
        $base = ['code' => 'evil', 'name' => 'X', 'version' => '1.0.0', 'table_prefix' => 'cmp_evil_'];

        $this->assertNotEmpty($m->validate($base + ['permissions' => ['platform.tenants.create' => 'escalar']], 'Evil'), 'permiso global silencioso');
        $this->assertNotEmpty($m->validate(['table_prefix' => 'users_'] + $base, 'Evil'), 'prefijo de tablas ajeno');
        $this->assertNotEmpty($m->validate(['code' => 'EVIL!'] + $base, 'Evil'));
        $this->assertNotEmpty($m->validate($base + ['permissions' => ['evil.view' => 'v'], 'menus' => [['label' => 'x', 'route' => 'x', 'permission' => 'evil.view', 'icon' => 'icon-no-existe']]], 'Evil'));
        $this->assertSame([], $m->validate($base + ['permissions' => ['evil.view' => 'v']], 'Evil'));
    }

    public function testShippedComponentsHonorTheContract(): void
    {
        $manager = Services::components();
        $this->assertSame(['crm', 'form', 'quote'], array_values(array_intersect(['crm', 'form', 'quote'], array_keys($manager->manifests()))));

        foreach ($manager->manifests() as $code => $m) {
            $this->assertSame([], $manager->validate($m, $m['folder']), $code);
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($m['path'], \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }
                $src = (string) file_get_contents($f->getPathname());
                $this->assertDoesNotMatchRegularExpression('/^namespace App\\\\/m', $src, $f->getPathname());
                $this->assertDoesNotMatchRegularExpression('/->query\(\s*["\'](SELECT|INSERT|UPDATE|DELETE)/i', $src, $f->getPathname());
                $this->assertDoesNotMatchRegularExpression('#https?://(cdn|unpkg|cdnjs)#', $src, $f->getPathname());
                $this->assertDoesNotMatchRegularExpression("/'type'\\s*=>\\s*'(ENUM|JSON)'/i", $src, $f->getPathname());
            }
        }
    }

    public function testLanguageFilesHaveTheSameKeysInEsAndEn(): void
    {
        $flat = static function (array $a, string $p = '') use (&$flat): array {
            $o = [];
            foreach ($a as $k => $v) {
                $o = is_array($v) ? array_merge($o, $flat($v, $p . $k . '.')) : array_merge($o, [$p . $k]);
            }

            return $o;
        };
        $files = array_merge(glob(APPPATH . 'Language/es/*.php'), glob(ROOTPATH . 'components/*/Language/es/*.php'));
        $this->assertNotEmpty($files);
        foreach ($files as $es) {
            $en = str_replace('/es/', '/en/', $es);
            $this->assertFileExists($en);
            $a = $flat(require $es);
            $b = $flat(require $en);
            $this->assertSame([], array_values(array_diff($a, $b)), 'faltan en en: ' . $es);
            $this->assertSame([], array_values(array_diff($b, $a)), 'faltan en es: ' . $es);
        }
    }

    public function testEveryUsedLangKeyIsDefined(): void
    {
        $missing = [];
        $roots   = [APPPATH, ROOTPATH . 'components/'];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all("/lang\\('([A-Z][A-Za-z]+)\\.([A-Za-z0-9_.]+)'(?!\\s*\\.)/", (string) file_get_contents($f->getPathname()), $m, PREG_SET_ORDER);
                foreach ($m as [, $file, $key]) {
                    if (str_ends_with($key, '.')) {
                        continue; // clave dinámica (prefijo . variable)
                    }
                    foreach (['es', 'en'] as $loc) {
                        service('language')->setLocale($loc);
                        if (lang($file . '.' . $key) === $file . '.' . $key) {
                            $missing[] = "{$loc}:{$file}.{$key}";
                        }
                    }
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));
    }
}
