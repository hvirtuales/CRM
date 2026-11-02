<?php

namespace Tests\Support;

use App\Database\Seeds\DemoSeeder;
use App\Libraries\Rls;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Base de pruebas HV CRM: instala la plataforma completa (núcleo + componentes + RLS) sobre la
 * base de pruebas UNA vez por proceso y siembra la demostración. Cada prueba crea los datos que
 * necesita y no depende de conteos globales.
 */
abstract class HvTestCase extends CIUnitTestCase
{
    use FeatureTestTrait;

    public const CSRF = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private static bool $installed = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$installed) {
            self::install();
            self::$installed = true;
        }
        Services::resetRequestState();
        Services::resetSingle('security'); // el hash CSRF se restaura de la sesión de cada prueba
        cache()->clean();                  // límites de tasa independientes por prueba
        Services::resetSingle('throttler'); // (el limitador guarda referencia a la caché anterior)
        $this->withSession(['csrf_hv' => self::CSRF]);
        Rls::setBypass(true); // las verificaciones directas en BD de las pruebas ven todo
    }

    private static function install(): void
    {
        $db = db_connect();
        if ($db->DBDriver === 'Postgre') {
            $db->query('DROP SCHEMA IF EXISTS public CASCADE');
            $db->query('CREATE SCHEMA public');
        } else {
            foreach ($db->listTables() as $t) {
                $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($t));
            }
        }
        Rls::setBypass(true);
        Services::components()->flush();
        Services::components()->sync();
        ob_start();
        (new DemoSeeder(config('Database')))->run();
        ob_end_clean();
    }

    // ------------------------------------------------------------------ ayudantes

    protected function userId(string $email): int
    {
        return (int) db_connect()->table('users')->where('email', $email)->get()->getRow()->id;
    }

    protected function companyId(string $slug): int
    {
        return (int) db_connect()->table('companies')->where('slug', $slug)->get()->getRow()->id;
    }

    protected function tenantId(string $slug = 'distribuidor-demo'): int
    {
        return (int) db_connect()->table('tenants')->where('slug', $slug)->get()->getRow()->id;
    }

    /** Membresía del usuario en un ámbito (y organización opcional). */
    protected function membershipId(string $email, string $scope, ?int $orgId = null): int
    {
        $b = db_connect()->table('memberships')->where('user_id', $this->userId($email))->where('scope', $scope);
        if ($orgId !== null) {
            $b->where($scope === 'tenant' ? 'tenant_id' : 'company_id', $orgId);
        }

        return (int) $b->get()->getRow()->id;
    }

    /**
     * Sesión autenticada (MFA superado) en el ámbito indicado.
     *
     * @return array<string, mixed>
     */
    protected function sessionFor(string $email, string $scope, ?int $orgId = null): array
    {
        $uid = $this->userId($email);
        $u   = db_connect()->table('users')->where('id', $uid)->get()->getRowArray();

        return [
            'user_id' => $uid, 'session_version' => (int) $u['session_version'], 'mfa_passed' => true,
            'membership_id' => $this->membershipId($email, $scope, $orgId), 'locale' => 'es', 'csrf_hv' => self::CSRF,
        ];
    }

    protected function actingAs(string $email, string $scope, ?int $orgId = null): static
    {
        return $this->withSession($this->sessionFor($email, $scope, $orgId));
    }

    /** POST con token CSRF válido. */
    protected function postForm(string $path, array $data = [])
    {
        return $this->post($path, $data + ['csrf_hv' => self::CSRF]);
    }

    /** Ejecuta código de servicio como si fuera una petición del usuario en ese ámbito. */
    protected function asContext(string $email, string $scope, ?int $orgId, callable $fn): mixed
    {
        $_SESSION = $this->sessionFor($email, $scope, $orgId);
        $ctx      = Services::resetRequestState();
        $ctx->loadFromSession();

        try {
            return $fn($ctx);
        } finally {
            $_SESSION = [];
            Services::resetRequestState();
        }
    }

    /** Conexión para verificaciones directas: omite RLS (la prueba inspecciona el estado real). */
    protected function db(): \CodeIgniter\Database\BaseConnection
    {
        Rls::setBypass(true);

        return db_connect();
    }

    /** Conteo directo en BD (omite RLS: la prueba verifica el estado real). */
    protected function rows(string $table, array $where): int
    {
        Rls::setBypass(true);
        $b = db_connect()->table($table);
        foreach ($where as $k => $v) {
            $b->where($k, $v);
        }

        return $b->countAllResults();
    }
}
