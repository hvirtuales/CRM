# HV CRM — Gestión comercial multiempresa de Herramientas Virtuales

CRM SaaS modular para automatizar la gestión comercial de los servicios de Herramientas Virtuales y
de su red de distribuidores. Tres espacios operativos con aislamiento estricto:

| Espacio | Quién | Qué hace |
|---|---|---|
| **Superadministrador** (`/plataforma`) | Equipo HV | Distribuidores, negocios directos, planes, suscripciones, pagos, comisiones, componentes, soporte, auditoría |
| **Distribuidor** (`/distribuidor`) | Revendedores | Cartera de negocios, alta con plan, renovaciones, comisiones, equipo, marca blanca |
| **Negocio** (`/negocio`, `/crm`, `/formularios`, `/cotizaciones`) | Clientes finales (incluida HV) | Contactos, proyectos, embudo kanban, formularios de captación, cotizaciones, usuarios, tokens API |

**Stack:** PHP 8.3+ (objetivo 8.4) · CodeIgniter 4.7 · PostgreSQL (portable a MySQL/SQL Server) ·
Tailwind CSS 4 (CLI autónomo, **sin Node**) · JavaScript nativo · sin React/Python.

## Instalación

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env            # configure BD, baseURL y encryption.key
php spark key:generate          # genera encryption.key en .env
php spark hv:install            # migra núcleo + componentes, siembra permisos/roles, aplica RLS
php spark hv:create-owner admin@empresa.com "Nombre Apellido"   # pide la contraseña
```

Desarrollo con datos de demostración (se niega a correr en producción):

```bash
php spark db:seed DemoSeeder    # usuarios *@hv.test, contraseña Demo-HV-CRM-2026!
php spark serve
```

CSS (solo si cambian vistas): `./bin/build-css.sh` descarga el CLI de Tailwind v4.1.11 verificado por
SHA-256 y compila `public/assets/css/app.css` (el CSS compilado se versiona).

Tarea diaria (ciclo de vida de suscripciones):

```
15 2 * * * cd /var/www/hv-crm && php spark hv:lifecycle >> writable/logs/lifecycle.log 2>&1
```

## Pruebas

```bash
vendor/bin/phpunit --no-coverage          # PostgreSQL hvcrm_test con RLS (¡se borra en cada ejecución!)
```

66 pruebas / 732 aserciones: unitarias, contrato de componentes, i18n y **seguridad** (aislamiento
entre negocios y distribuidores, IDOR, RBAC/ABAC, CSRF, MFA, bloqueo, API, formularios públicos,
inyección, RLS). Informe: [`docs/security/informe-pruebas-seguridad.md`](docs/security/informe-pruebas-seguridad.md).

## Estructura

```
app/                      Núcleo (no lo modifican los componentes)
  Config/Rbac.php           Permisos del núcleo y roles por ámbito
  Config/Navigation.php     Menú por ámbito
  Libraries/                Context, RbacService, ComponentManager, PlanService, SubscriptionService,
                            AuthService, Totp, AuditService, Rls, ProvisioningService, …
  Filters/                  requeststate, auth, permission, apiauth, locale, hvheaders
  Controllers/{Platform,Tenant,Company}
components/               Componentes drop-in (contrato componentes-ci4)
  Crm/          code crm    Contactos, proyectos, embudo, actividades, API v1
  Formularios/  code form   Constructor, publicación versionada, captura pública
  Cotizaciones/ code quote  Catálogo de servicios, cotizaciones, enlace público
resources/css/app.css     Tokens del sistema de diseño
docs/                     ADR, seguridad, módulos, operación
tests/                    Unit + Security (PHPUnit)
```

## Documentación

- Decisiones de arquitectura: [`docs/adr/`](docs/adr/)
- Contrato de componentes y cómo crear uno: [`docs/modules/componentes.md`](docs/modules/componentes.md)
- Seguridad y resultados de pruebas: [`docs/security/`](docs/security/)
- Operación (despliegue, backup, incidentes): [`docs/runbooks/operacion.md`](docs/runbooks/operacion.md)
- Sistema de diseño aplicado: [`DESIGN.md`](DESIGN.md)
