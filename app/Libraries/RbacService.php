<?php

namespace App\Libraries;

use Config\Rbac;
use Config\Services;

/**
 * Catálogo de permisos y resolución rol → permisos.
 *
 * Fuente de verdad: Config\Rbac (núcleo) + `permissions`/`role_grants` de cada Manifest.
 * `sync()` es idempotente: siembra lo que falta y retira permisos de componentes que ya no existen.
 */
class RbacService
{
    /** @var array<int, list<string>> */
    private array $roleCache = [];

    /** @var array<string, string|null>|null código => componente */
    private ?array $componentIndex = null;

    /** @return list<string> */
    public function permissionsForRole(int $roleId): array
    {
        if (! isset($this->roleCache[$roleId])) {
            $rows = db_connect()->table('role_permissions rp')
                ->select('p.code')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->where('rp.role_id', $roleId)
                ->get()->getResultArray();
            $this->roleCache[$roleId] = array_column($rows, 'code');
        }

        return $this->roleCache[$roleId];
    }

    public function componentOf(string $permission): ?string
    {
        if ($this->componentIndex === null) {
            $rows                 = db_connect()->table('permissions')->select('code, component')->get()->getResultArray();
            $this->componentIndex = array_column($rows, 'component', 'code');
        }

        return $this->componentIndex[$permission] ?? null;
    }

    /**
     * Expande comodines a códigos concretos dentro de un ámbito.
     *
     * @param list<string> $grants
     *
     * @return list<string>
     */
    public function expandGrants(array $grants, string $scope): array
    {
        $catalog = $this->catalog();
        $out     = [];

        foreach ($catalog as $code => $meta) {
            if ($meta['scope'] !== $scope) {
                continue;
            }
            $isComponent = $meta['component'] !== null;

            foreach ($grants as $grant) {
                $matches = match (true) {
                    $grant === 'component:*'        => $isComponent,
                    $grant === 'component:*.view'   => $isComponent && str_ends_with($code, '.view'),
                    $grant === '*.view'             => ! $isComponent && str_ends_with($code, '.view'),
                    str_ends_with($grant, '.*')     => ! $isComponent && str_starts_with($code, substr($grant, 0, -1)),
                    default                         => $grant === $code,
                };
                if ($matches) {
                    $out[] = $code;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Catálogo completo: núcleo + componentes con Manifest.
     *
     * @return array<string, array{scope:string, description:string, component:?string}>
     */
    public function catalog(): array
    {
        $catalog = [];
        foreach (config(Rbac::class)->permissions as $code => [$scope, $description]) {
            $catalog[$code] = ['scope' => $scope, 'description' => $description, 'component' => null];
        }

        foreach (Services::components()->manifests() as $manifest) {
            $scope = ($manifest['scope'] ?? 'company') === 'tenant' ? 'tenant' : 'company';
            foreach ($manifest['permissions'] ?? [] as $code => $description) {
                $catalog[$code] = ['scope' => $scope, 'description' => $description, 'component' => $manifest['code']];
            }
        }

        return $catalog;
    }

    /**
     * Siembra permisos y roles de sistema y asigna sus concesiones. Idempotente.
     */
    public function sync(): void
    {
        $db      = db_connect();
        $now     = date('Y-m-d H:i:s');
        $catalog = $this->catalog();

        $db->transStart();

        // 1. Permisos
        $existing = array_column($db->table('permissions')->get()->getResultArray(), null, 'code');
        foreach ($catalog as $code => $meta) {
            $row = ['description' => $meta['description'], 'scope' => $meta['scope'], 'component' => $meta['component'], 'updated_at' => $now];
            if (isset($existing[$code])) {
                $db->table('permissions')->where('id', $existing[$code]['id'])->update($row);
            } else {
                $db->table('permissions')->insert($row + ['code' => $code, 'created_at' => $now]);
            }
        }
        // Permisos huérfanos de componentes retirados (los del núcleo nunca se borran aquí).
        foreach ($existing as $code => $row) {
            if (! isset($catalog[$code]) && $row['component'] !== null) {
                $db->table('permissions')->where('id', $row['id'])->delete();
            }
        }
        $permIds = array_column($db->table('permissions')->select('id, code')->get()->getResultArray(), 'id', 'code');

        // 2. Roles de sistema y concesiones
        $roleGrants = [];
        foreach (config(Rbac::class)->roles as $code => $def) {
            $roleGrants[$code] = ['scope' => $def['scope'], 'name' => $def['name'], 'grants' => $def['grants']];
        }
        foreach (Services::components()->manifests() as $manifest) {
            foreach ($manifest['role_grants'] ?? [] as $roleCode => $codes) {
                if (isset($roleGrants[$roleCode])) {
                    $roleGrants[$roleCode]['grants'] = array_merge($roleGrants[$roleCode]['grants'], $codes);
                }
            }
        }

        foreach ($roleGrants as $code => $def) {
            $role = $db->table('roles')->where('code', $code)->where('is_system', 1)->get()->getRowArray();
            if (! $role) {
                $db->table('roles')->insert([
                    'code' => $code, 'name' => $def['name'], 'scope' => $def['scope'],
                    'is_system' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $roleId = (int) $db->insertID();
            } else {
                $roleId = (int) $role['id'];
            }

            $wanted = [];
            foreach ($this->expandGrants($def['grants'], $def['scope']) as $permCode) {
                $wanted[(int) $permIds[$permCode]] = true;
            }
            $current = array_column($db->table('role_permissions')->where('role_id', $roleId)->get()->getResultArray(), 'permission_id');
            foreach ($current as $pid) {
                if (! isset($wanted[(int) $pid])) {
                    $db->table('role_permissions')->where('role_id', $roleId)->where('permission_id', $pid)->delete();
                }
                unset($wanted[(int) $pid]);
            }
            foreach (array_keys($wanted) as $pid) {
                $db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $pid]);
            }
        }

        $db->transComplete();
        $this->roleCache      = [];
        $this->componentIndex = null;
    }

    public function systemRoleId(string $code): int
    {
        $row = db_connect()->table('roles')->select('id')->where('code', $code)->where('is_system', 1)->get()->getRowArray();
        if (! $row) {
            throw new \RuntimeException('Rol de sistema inexistente: ' . $code . '. Ejecute php spark hv:install');
        }

        return (int) $row['id'];
    }

    /**
     * Roles asignables en un ámbito (sistema + personalizados del negocio).
     *
     * @return list<array<string, mixed>>
     */
    public function assignableRoles(string $scope, ?int $companyId = null): array
    {
        $b = db_connect()->table('roles')->where('scope', $scope)->groupStart()->where('is_system', 1);
        if ($companyId !== null) {
            $b->orWhere('company_id', $companyId);
        }

        return $b->groupEnd()->orderBy('is_system', 'DESC')->orderBy('name', 'ASC')->get()->getResultArray();
    }

    public function flush(): void
    {
        $this->roleCache      = [];
        $this->componentIndex = null;
    }
}
