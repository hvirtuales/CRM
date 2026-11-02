# Componentes drop-in

Un componente es una carpeta `components/<PascalCase>/` con `Manifest.php`. El núcleo la registra
como `Components\<Carpeta>` y CI4 descubre sus rutas, servicios, eventos e idiomas.

## Ciclo de vida

| Paso | Quién | Cómo |
|---|---|---|
| Instalar / actualizar código | Equipo de plataforma (despliegue revisado) | copiar la carpeta + `php spark hv:install` |
| Verificar contrato | Plataforma | `php spark hv:contract <code>` (superficie del núcleo + incumplimientos) |
| Habilitar / deshabilitar global | `platform.components.edit` | Plataforma → Componentes (o `php spark hv:components enable|disable <code>`) |
| Incluir en un plan | `platform.plans.edit` | recuadro del componente en el formulario de planes (`plan_flag`, `plan_limits`) |

Deshabilitar **no borra datos**: oculta menús y el filtro `permission:` cierra rutas web y API.
No se puede deshabilitar un componente del que dependen otros habilitados. La huella SHA-256 del
código se registra al sincronizar; el panel alerta si cambió fuera del despliegue.

## Reglas de seguridad aplicadas por `ComponentManager::validate()`

- `code` 3–8 letras; `table_prefix` = `cmp_<code>_`; versión SemVer.
- Permisos **solo** `<code>.<accion>` (un componente no puede declarar permisos de plataforma).
- Menús con permiso declarado e ícono existente (`Config\Icons`).

## Extensiones del contrato en HV CRM

- `name_key`, `label_key`: claves de idioma para nombre y menús (i18n completo).
- `role_grants`: permisos del componente concedidos a roles de negocio (además de propietario y admin).
- Interfaz pública entre componentes por servicios: `crmLeads()->capture()`, `crmContacts()->summaries()`,
  `crmOpportunities()->titles()/openOptions()/log()`, `crmSetup()->projectOf()`.

## Componentes incluidos

| code | Carpeta | Depende | Plan | Permisos |
|---|---|---|---|---|
| `crm` | `Crm` | — | `crm_enabled`, `max_contacts`, `max_opportunities` | view, create, edit, delete, manage, configure, export |
| `form` | `Formularios` | crm | `form_enabled`, `max_forms` | view, create, edit, delete |
| `quote` | `Cotizaciones` | crm | `quote_enabled`, `max_quotes` | view, create, edit, delete, configure |

Superficie del núcleo usada (salida de `hv:contract`): `App\Controllers\BaseController`,
`App\Libraries\Rls` (si existe), filtros `auth`, `permission`, `apiauth`, tablas `users`, `companies`,
`plans`, `tenants` (marca), `memberships`/`roles` (responsables del CRM), sesión `active_company_id`,
helpers `icon`, `money`, `num`, `dt`, `status_badge`, `field_error`, `auth_can`, `ctx`, vistas
`layouts/app` y `partials/*`.
