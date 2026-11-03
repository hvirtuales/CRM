<?php

namespace App\Libraries;

use CodeIgniter\Database\MigrationRunner;
use Config\Autoload;
use Config\Icons;
use Config\Services;

/**
 * Gestor de componentes drop-in (`components/<Carpeta>/Manifest.php`).
 *
 * - Instalación de código: solo equipo de plataforma (despliegue + `php spark hv:components sync`).
 * - Habilitación global: tabla `components` (enabled/disabled), desde el panel de plataforma.
 * - Habilitación por negocio: bandera `plan_flag` del plan vigente (PlanService).
 * Deshabilitar no borra datos: cierra menús, rutas (filtro permission) y API.
 */
class ComponentManager
{
    private const REQUIRED = ['code', 'name', 'version', 'table_prefix'];

    /** @var array<string, array<string, mixed>>|null code => manifest */
    private ?array $manifests = null;

    /** @var array<string, string>|null code => status */
    private ?array $status = null;

    /** @var list<string>|null columnas de `plans` (memo por petición) */
    private ?array $planFields = null;

    /**
     * Manifests válidos indexados por code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function manifests(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $this->manifests = [];
        foreach (Autoload::componentDirectories() as $folder => $path) {
            $manifest = require $path . 'Manifest.php';
            if (! is_array($manifest)) {
                log_message('error', 'Manifest inválido en components/{folder}', ['folder' => $folder]);

                continue;
            }
            $errors = $this->validate($manifest, $folder);
            if ($errors !== []) {
                log_message('error', 'Manifest de {folder} rechazado: {errors}', ['folder' => $folder, 'errors' => implode('; ', $errors)]);

                continue;
            }
            $manifest['folder']                  = $folder;
            $manifest['path']                    = $path;
            $this->manifests[$manifest['code']] = $manifest;
        }

        return $this->manifests;
    }

    public function manifest(string $code): ?array
    {
        return $this->manifests()[$code] ?? null;
    }

    /**
     * Valida el contrato del Manifest. Un componente no puede introducir permisos fuera de su prefijo
     * ni de ámbito plataforma (regla de seguridad de módulos).
     *
     * @return list<string>
     */
    public function validate(array $m, string $folder): array
    {
        $errors = [];
        foreach (self::REQUIRED as $key) {
            if (empty($m[$key]) || ! is_string($m[$key])) {
                $errors[] = "falta '{$key}'";
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $code = $m['code'];
        if (! preg_match('/^[a-z]{3,8}$/', $code)) {
            $errors[] = 'code debe tener 3–8 letras minúsculas';
        }
        if ($m['table_prefix'] !== 'cmp_' . $code . '_') {
            $errors[] = "table_prefix debe ser cmp_{$code}_";
        }
        if (! preg_match('/^\d+\.\d+\.\d+$/', $m['version'])) {
            $errors[] = 'version debe ser SemVer';
        }
        if (! in_array($m['scope'] ?? 'company', ['company', 'tenant'], true)) {
            $errors[] = 'scope inválido';
        }
        foreach (array_keys($m['permissions'] ?? []) as $perm) {
            if (! preg_match('/^' . $code . '\.[a-z_]+$/', (string) $perm)) {
                $errors[] = "permiso '{$perm}' fuera del prefijo {$code}.";
            }
        }
        foreach ($m['role_grants'] ?? [] as $role => $perms) {
            foreach ((array) $perms as $perm) {
                if (! isset(($m['permissions'] ?? [])[$perm])) {
                    $errors[] = "role_grants de '{$role}' concede '{$perm}', que no es un permiso propio del componente";
                }
            }
        }
        $icons = config(Icons::class)->available;
        foreach ($m['menus'] ?? [] as $menu) {
            if (! isset($menu['permission'], $menu['route'], $menu['label'])) {
                $errors[] = 'menú incompleto';
            } elseif (! isset(($m['permissions'] ?? [])[$menu['permission']])) {
                $errors[] = "menú con permiso no declarado '{$menu['permission']}'";
            }
            $icon = preg_replace('/^icon-/', '', (string) ($menu['icon'] ?? ''));
            if ($icon !== '' && ! in_array($icon, $icons, true)) {
                $errors[] = "icono inexistente '{$menu['icon']}'";
            }
        }

        return $errors;
    }

    public function isEnabled(string $code): bool
    {
        if (! isset($this->manifests()[$code])) {
            return false;
        }
        if ($this->status === null) {
            $rows         = db_connect()->table('components')->select('code, status')->get()->getResultArray();
            $this->status = array_column($rows, 'status', 'code');
        }

        return ($this->status[$code] ?? 'disabled') === 'enabled';
    }

    /**
     * Instala o actualiza todos los componentes presentes: migraciones del núcleo primero
     * (crean `plans`), luego cada componente en orden de dependencias, permisos y publicación de assets.
     *
     * @return list<string> mensajes de progreso
     */
    public function sync(bool $migrate = true): array
    {
        $log = [];
        $db  = db_connect();

        if ($migrate) {
            /** @var MigrationRunner $runner */
            $runner = Services::migrations(null, null, false);
            $runner->setNamespace('App')->latest();
            $log[] = 'núcleo: migraciones al día';
        }

        foreach ($this->dependencyOrder() as $code) {
            $m = $this->manifests()[$code];
            if ($migrate) {
                $runner = Services::migrations(null, null, false);
                $runner->setNamespace('Components\\' . $m['folder'])->latest();
            }
            $this->publishAssets($m);

            $now = date('Y-m-d H:i:s');
            $row = $db->table('components')->where('code', $code)->get()->getRowArray();
            $data = [
                'name' => $m['name'], 'folder' => $m['folder'], 'version' => $m['version'],
                'checksum' => $this->checksum($m['path']), 'updated_at' => $now,
            ];
            if ($row) {
                $db->table('components')->where('id', $row['id'])->update($data);
            } else {
                $db->table('components')->insert($data + ['code' => $code, 'status' => 'enabled', 'installed_at' => $now]);
            }
            $log[] = "{$code}: {$m['version']} sincronizado";
        }

        // Verificación: toda columna de plan declarada en un Manifest debe existir tras migrar.
        $this->planFields = null;
        $db->resetDataCache();
        $fields  = $db->getFieldNames('plans');
        $missing = [];
        foreach ($this->manifests() as $m) {
            foreach (array_merge(isset($m['plan_flag']) ? [$m['plan_flag']] : [], array_column($m['plan_limits'] ?? [], 'column')) as $col) {
                if (! in_array($col, $fields, true)) {
                    $missing[] = $m['code'] . ':' . $col;
                }
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException('Columnas de plan faltantes tras migrar: ' . implode(', ', $missing));
        }

        Services::rbac()->sync();
        $log[] = 'permisos y roles sincronizados';

        if (Rls::supported()) {
            $log = array_merge($log, Rls::apply($this));
        }

        $this->status = null;

        return $log;
    }

    public function setStatus(string $code, bool $enabled): Result
    {
        $m = $this->manifest($code);
        if (! $m) {
            return Result::notFound();
        }
        if (! $enabled) {
            // No se puede deshabilitar un componente del que dependen otros habilitados.
            foreach ($this->manifests() as $other) {
                if (in_array($code, $other['depends'] ?? [], true) && $this->isEnabled($other['code'])) {
                    return Result::fail(lang('Core.componentRequiredBy', [$other['name']]));
                }
            }
        } else {
            foreach ($m['depends'] ?? [] as $dep) {
                if (! $this->isEnabled($dep)) {
                    return Result::fail(lang('Core.componentNeeds', [$dep]));
                }
            }
        }

        db_connect()->table('components')->where('code', $code)->update([
            'status' => $enabled ? 'enabled' : 'disabled', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->status = null;
        Services::audit()->log($enabled ? 'component.enabled' : 'component.disabled', 'component', $code, ['version' => $m['version']]);

        return Result::success();
    }

    /** @return list<string> */
    public function dependencyOrder(): array
    {
        $manifests = $this->manifests();
        $ordered   = [];
        $visiting  = [];

        $visit = function (string $code) use (&$visit, &$ordered, &$visiting, $manifests): void {
            if (in_array($code, $ordered, true)) {
                return;
            }
            if (isset($visiting[$code])) {
                throw new \RuntimeException('Dependencia circular entre componentes: ' . $code);
            }
            $visiting[$code] = true;
            foreach ($manifests[$code]['depends'] ?? [] as $dep) {
                if (! isset($manifests[$dep])) {
                    throw new \RuntimeException("El componente {$code} requiere {$dep}, que no está presente");
                }
                $visit($dep);
            }
            $ordered[] = $code;
        };

        foreach (array_keys($manifests) as $code) {
            $visit($code);
        }

        return $ordered;
    }

    /**
     * Menús de componentes visibles para el contexto (habilitado + permiso + plan).
     *
     * @return list<array<string, mixed>>
     */
    public function menusFor(Context $ctx): array
    {
        $groups = [];
        foreach ($this->manifests() as $code => $m) {
            $scope = ($m['scope'] ?? 'company');
            if ($scope !== $ctx->scope() || ! $this->isEnabled($code)) {
                continue;
            }
            $items = [];
            foreach ($m['menus'] ?? [] as $menu) {
                if ($ctx->can($menu['permission'])) {
                    $items[] = $menu + ['order' => 50];
                }
            }
            if ($items !== []) {
                usort($items, static fn ($a, $b) => $a['order'] <=> $b['order']);
                $groups[] = ['code' => $code, 'name' => $m['name'], 'name_key' => $m['name_key'] ?? null, 'items' => $items, 'order' => $items[0]['order']];
            }
        }
        usort($groups, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $groups;
    }

    /**
     * Recuadros de servicios para el formulario de planes, generados desde los Manifest.
     * Solo ofrece columnas que existen realmente en `plans`.
     *
     * @return list<array{code:string, name:string, flag:?string, limits:list<array<string,string>>}>
     */
    public function planFormGroups(): array
    {
        // Sin caché de tablas del driver (puede quedar obsoleta tras migrar en el mismo proceso);
        // memoizado por petición en $planFields.
        $db     = db_connect();
        if ($this->planFields === null) {
            $db->resetDataCache();
            $this->planFields = $db->tableExists('plans', false) ? $db->getFieldNames('plans') : [];
        }
        $fields = $this->planFields;
        $groups = [];

        foreach ($this->manifests() as $code => $m) {
            $flag   = isset($m['plan_flag']) && in_array($m['plan_flag'], $fields, true) ? $m['plan_flag'] : null;
            $limits = array_values(array_filter($m['plan_limits'] ?? [], static fn ($l) => in_array($l['column'], $fields, true)));
            if ($flag || $limits) {
                $groups[] = ['code' => $code, 'name' => $m['name'], 'flag' => $flag, 'limits' => $limits];
            }
        }

        return $groups;
    }

    /** @return list<string> columnas de `plans` pertenecientes a componentes */
    public function planColumns(): array
    {
        $cols = [];
        foreach ($this->planFormGroups() as $g) {
            if ($g['flag']) {
                $cols[] = $g['flag'];
            }
            foreach ($g['limits'] as $l) {
                $cols[] = $l['column'];
            }
        }

        return $cols;
    }

    /**
     * Estado del plan del negocio por componente: activo/no incluido y consumo de cupos.
     *
     * @return list<array<string, mixed>>
     */
    public function planStatus(int $companyId): array
    {
        $plans = Services::plans();
        $plan  = $plans->planForCompany($companyId);
        $out   = [];

        foreach ($this->planFormGroups() as $g) {
            if (! $this->isEnabled($g['code'])) {
                continue;
            }
            $limits = [];
            foreach ($g['limits'] as $l) {
                $max  = $plan === null ? 0 : (int) ($plan[$l['column']] ?? 0);
                $used = $plans->countUsage($l['table'], $l['company_column'] ?? 'company_id', $companyId);
                $limits[] = ['label' => $l['label'], 'label_key' => $l['label_key'] ?? null, 'max' => $max, 'used' => $used];
            }
            $out[] = [
                'code'    => $g['code'],
                'name'    => $g['name'],
                'enabled' => $plans->componentAllowed($companyId, $g['code']),
                'limits'  => $limits,
            ];
        }

        return $out;
    }

    /** Copia `assets/` del componente a `public/components/<code>/` (sin CDN). */
    private function publishAssets(array $m): void
    {
        $src = $m['path'] . 'assets';
        if (! is_dir($src)) {
            return;
        }
        $dst = FCPATH . 'components' . DIRECTORY_SEPARATOR . $m['code'];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $target = $dst . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) {
                is_dir($target) || mkdir($target, 0755, true);
            } elseif (preg_match('/\.(css|js|svg|png|jpg|webp|woff2)$/i', $item->getFilename())) {
                is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    /** Huella SHA-256 del código del componente (detecta cambios no desplegados por el proceso). */
    public function checksum(string $path): string
    {
        $files = [];
        $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $files[substr($f->getPathname(), strlen($path))] = hash_file('sha256', $f->getPathname());
            }
        }
        ksort($files);

        return hash('sha256', json_encode($files));
    }

    public function flush(): void
    {
        $this->planFields = null;
        $this->status    = null;
        $this->manifests = null;
    }
}
