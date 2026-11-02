<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Catálogo de permisos del núcleo y roles de sistema por ámbito.
 *
 * Convención: `<ámbito>.<recurso>.<acción>` para el núcleo; `<code>.<acción>` para componentes
 * (los declara cada Manifest y los siembra ComponentManager).
 * Denegar por defecto: un rol solo tiene lo que aparece aquí o en `role_grants` de un Manifest.
 */
class Rbac extends BaseConfig
{
    /**
     * @var array<string, array{0:string,1:string}> código => [ámbito, descripción]
     */
    public array $permissions = [
        // Plataforma
        'platform.dashboard.view'     => ['platform', 'Ver panel de plataforma'],
        'platform.tenants.view'       => ['platform', 'Ver distribuidores'],
        'platform.tenants.create'     => ['platform', 'Crear distribuidores'],
        'platform.tenants.edit'       => ['platform', 'Editar y suspender distribuidores'],
        'platform.companies.view'     => ['platform', 'Ver negocios (metadatos)'],
        'platform.companies.create'   => ['platform', 'Crear negocios directos'],
        'platform.companies.edit'     => ['platform', 'Editar, suspender y trasladar negocios'],
        'platform.plans.view'         => ['platform', 'Ver planes'],
        'platform.plans.edit'         => ['platform', 'Crear y editar planes'],
        'platform.subscriptions.view' => ['platform', 'Ver suscripciones'],
        'platform.subscriptions.edit' => ['platform', 'Cambiar estado de suscripciones'],
        'platform.payments.view'      => ['platform', 'Ver pagos'],
        'platform.payments.create'    => ['platform', 'Registrar pagos'],
        'platform.commissions.view'   => ['platform', 'Ver comisiones'],
        'platform.commissions.edit'   => ['platform', 'Aprobar y liquidar comisiones'],
        'platform.components.view'    => ['platform', 'Ver componentes'],
        'platform.components.edit'    => ['platform', 'Habilitar y deshabilitar componentes'],
        'platform.users.view'         => ['platform', 'Ver equipo de plataforma'],
        'platform.users.edit'         => ['platform', 'Gestionar equipo de plataforma'],
        'platform.support.request'    => ['platform', 'Solicitar acceso de soporte a un negocio'],
        'platform.audit.view'         => ['platform', 'Ver auditoría global'],

        // Distribuidor
        'tenant.dashboard.view'       => ['tenant', 'Ver panel de cartera'],
        'tenant.companies.view'       => ['tenant', 'Ver negocios de la cartera'],
        'tenant.companies.create'     => ['tenant', 'Incorporar negocios'],
        'tenant.companies.edit'       => ['tenant', 'Editar negocios de la cartera'],
        'tenant.subscriptions.view'   => ['tenant', 'Ver suscripciones de la cartera'],
        'tenant.subscriptions.edit'   => ['tenant', 'Asignar y cambiar planes'],
        'tenant.commissions.view'     => ['tenant', 'Ver comisiones'],
        'tenant.users.view'           => ['tenant', 'Ver equipo del distribuidor'],
        'tenant.users.edit'           => ['tenant', 'Gestionar equipo del distribuidor'],
        'tenant.branding.edit'        => ['tenant', 'Configurar marca blanca'],
        'tenant.audit.view'           => ['tenant', 'Ver auditoría del distribuidor'],

        // Negocio
        'company.dashboard.view'      => ['company', 'Ver panel del negocio'],
        'company.users.view'          => ['company', 'Ver usuarios del negocio'],
        'company.users.edit'          => ['company', 'Gestionar usuarios y roles del negocio'],
        'company.settings.edit'       => ['company', 'Editar configuración del negocio'],
        'company.subscription.view'   => ['company', 'Ver plan y consumo'],
        'company.support.approve'     => ['company', 'Aprobar accesos de soporte'],
        'company.tokens.manage'       => ['company', 'Gestionar tokens de API'],
        'company.audit.view'          => ['company', 'Ver auditoría del negocio'],
    ];

    /**
     * Roles de sistema. Comodines admitidos: `platform.*`, `*.view` (solo lectura dentro del ámbito),
     * `component:*` (todos los permisos de componentes), `component:*.view`.
     *
     * @var array<string, array{scope:string, name:string, grants:list<string>}>
     */
    public array $roles = [
        'platform_owner'   => ['scope' => 'platform', 'name' => 'Propietario de plataforma', 'grants' => ['platform.*']],
        'platform_admin'   => ['scope' => 'platform', 'name' => 'Administrador de plataforma', 'grants' => ['platform.*']],
        'platform_support' => ['scope' => 'platform', 'name' => 'Soporte de plataforma', 'grants' => [
            'platform.dashboard.view', 'platform.tenants.view', 'platform.companies.view', 'platform.support.request',
        ]],
        'platform_billing' => ['scope' => 'platform', 'name' => 'Facturación de plataforma', 'grants' => [
            'platform.dashboard.view', 'platform.tenants.view', 'platform.companies.view', 'platform.plans.view',
            'platform.subscriptions.view', 'platform.subscriptions.edit', 'platform.payments.view',
            'platform.payments.create', 'platform.commissions.view', 'platform.commissions.edit',
        ]],
        'platform_auditor' => ['scope' => 'platform', 'name' => 'Auditor de plataforma', 'grants' => ['*.view']],

        'tenant_owner'     => ['scope' => 'tenant', 'name' => 'Propietario del distribuidor', 'grants' => ['tenant.*']],
        'tenant_admin'     => ['scope' => 'tenant', 'name' => 'Administrador del distribuidor', 'grants' => ['tenant.*']],
        'tenant_sales'     => ['scope' => 'tenant', 'name' => 'Comercial del distribuidor', 'grants' => [
            'tenant.dashboard.view', 'tenant.companies.view', 'tenant.companies.create',
            'tenant.subscriptions.view', 'tenant.subscriptions.edit',
        ]],
        'tenant_support'   => ['scope' => 'tenant', 'name' => 'Soporte del distribuidor', 'grants' => [
            'tenant.dashboard.view', 'tenant.companies.view', 'tenant.subscriptions.view',
        ]],
        'tenant_billing'   => ['scope' => 'tenant', 'name' => 'Facturación del distribuidor', 'grants' => [
            'tenant.dashboard.view', 'tenant.subscriptions.view', 'tenant.commissions.view',
        ]],
        'tenant_auditor'   => ['scope' => 'tenant', 'name' => 'Auditor del distribuidor', 'grants' => ['*.view']],

        'business_owner'   => ['scope' => 'company', 'name' => 'Propietario del negocio', 'grants' => ['company.*', 'component:*']],
        'business_admin'   => ['scope' => 'company', 'name' => 'Administrador del negocio', 'grants' => ['company.*', 'component:*']],
        'sales_manager'    => ['scope' => 'company', 'name' => 'Gerente comercial', 'grants' => ['company.dashboard.view']],
        'sales_agent'      => ['scope' => 'company', 'name' => 'Asesor comercial', 'grants' => ['company.dashboard.view']],
        'marketing'        => ['scope' => 'company', 'name' => 'Mercadeo', 'grants' => ['company.dashboard.view']],
        'support_agent'    => ['scope' => 'company', 'name' => 'Agente de servicio', 'grants' => ['company.dashboard.view']],
        'project_manager'  => ['scope' => 'company', 'name' => 'Líder de proyecto', 'grants' => ['company.dashboard.view']],
        'analyst'          => ['scope' => 'company', 'name' => 'Analista', 'grants' => ['*.view', 'component:*.view']],
        'viewer'           => ['scope' => 'company', 'name' => 'Consulta', 'grants' => ['company.dashboard.view', 'component:*.view']],
    ];

    /** Permisos que un acceso de soporte aprobado concede dentro del negocio (solo lectura). */
    public array $supportGrants = ['*.view', 'component:*.view'];

    /** Horas máximas de un acceso de soporte. */
    public int $supportMaxHours = 8;

    /** Exigir MFA (TOTP) a todo usuario con membresía de plataforma. */
    public bool $requireMfaForPlatform = true;
}
