<?php

namespace App\Libraries;

/**
 * PostgreSQL Row-Level Security como SEGUNDA barrera de aislamiento (la primera son los servicios).
 *
 * Se activa con `HV_RLS=true` y solo con el driver Postgre; en MySQL/SQL Server es un no-op.
 * Política: fila visible si company_id = app.company_id de la sesión de BD, o si se activó
 * explícitamente el bypass (procesos de plataforma y CLI). Sin company fijado ⇒ cero filas.
 *
 * Excepción documentada al contrato de portabilidad (SQL crudo): solo se ejecuta en PostgreSQL.
 */
final class Rls
{
    public static function supported(): bool
    {
        return filter_var(env('HV_RLS', false), FILTER_VALIDATE_BOOL)
            && db_connect()->DBDriver === 'Postgre';
    }

    /** Fija el negocio activo para la conexión (llamado al resolver el contexto). */
    public static function setCompany(?int $companyId): void
    {
        if (! self::supported()) {
            return;
        }
        db_connect()->query("SELECT set_config('app.company_id', ?, false)", [$companyId === null ? '' : (string) $companyId]);
    }

    public static function setBypass(bool $on): void
    {
        if (! self::supported()) {
            return;
        }
        db_connect()->query("SELECT set_config('app.rls_bypass', ?, false)", [$on ? 'on' : 'off']);
    }

    /**
     * Ejecuta $fn con el negocio indicado y restaura el valor previo.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public static function withCompany(int $companyId, callable $fn): mixed
    {
        if (! self::supported()) {
            return $fn();
        }
        $db   = db_connect();
        $prev = (string) ($db->query("SELECT current_setting('app.company_id', true) AS v")->getRow()->v ?? '');
        self::setCompany($companyId);

        try {
            return $fn();
        } finally {
            $db->query("SELECT set_config('app.company_id', ?, false)", [$prev]);
        }
    }

    /**
     * Crea/actualiza políticas en toda tabla `cmp_*` que tenga company_id. Idempotente.
     *
     * @return list<string>
     */
    public static function apply(ComponentManager $manager): array
    {
        $db  = db_connect();
        $log = [];
        foreach ($db->listTables() as $table) {
            if (! preg_match('/^cmp_[a-z]{3,8}_[a-z_]+$/', $table) || ! $db->fieldExists('company_id', $table)) {
                continue;
            }
            $t = $db->escapeIdentifiers($table);
            $db->query("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            $db->query("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            $db->query("DROP POLICY IF EXISTS hv_company_isolation ON {$t}");
            $db->query("CREATE POLICY hv_company_isolation ON {$t} USING ("
                . "current_setting('app.rls_bypass', true) = 'on' "
                . "OR company_id = NULLIF(current_setting('app.company_id', true), '')::int"
                . ") WITH CHECK ("
                . "current_setting('app.rls_bypass', true) = 'on' "
                . "OR company_id = NULLIF(current_setting('app.company_id', true), '')::int)");
            $log[] = "rls: {$table}";
        }

        return $log;
    }
}
