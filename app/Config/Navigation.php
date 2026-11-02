<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Menú del núcleo por ámbito. Cada ítem se muestra solo si el contexto tiene el permiso;
 * ocultar el menú es presentación: la autorización real vive en filtros y servicios.
 */
class Navigation extends BaseConfig
{
    /**
     * @var array<string, list<array{group:string, items:list<array{label:string, route:string, icon:string, permission:string}>}>>
     */
    public array $menus = [
        'platform' => [
            ['group' => 'Nav.groupOperation', 'items' => [
                ['label' => 'Nav.dashboard', 'route' => 'plataforma', 'icon' => 'layout-dashboard', 'permission' => 'platform.dashboard.view'],
                ['label' => 'Nav.tenants', 'route' => 'plataforma/distribuidores', 'icon' => 'building-2', 'permission' => 'platform.tenants.view'],
                ['label' => 'Nav.companies', 'route' => 'plataforma/negocios', 'icon' => 'store', 'permission' => 'platform.companies.view'],
            ]],
            ['group' => 'Nav.groupBilling', 'items' => [
                ['label' => 'Nav.plans', 'route' => 'plataforma/planes', 'icon' => 'credit-card', 'permission' => 'platform.plans.view'],
                ['label' => 'Nav.subscriptions', 'route' => 'plataforma/suscripciones', 'icon' => 'receipt', 'permission' => 'platform.subscriptions.view'],
                ['label' => 'Nav.commissions', 'route' => 'plataforma/comisiones', 'icon' => 'percent', 'permission' => 'platform.commissions.view'],
            ]],
            ['group' => 'Nav.groupSettings', 'items' => [
                ['label' => 'Nav.components', 'route' => 'plataforma/componentes', 'icon' => 'puzzle', 'permission' => 'platform.components.view'],
                ['label' => 'Nav.team', 'route' => 'plataforma/equipo', 'icon' => 'users', 'permission' => 'platform.users.view'],
                ['label' => 'Nav.support', 'route' => 'plataforma/soporte', 'icon' => 'life-buoy', 'permission' => 'platform.support.request'],
                ['label' => 'Nav.audit', 'route' => 'plataforma/auditoria', 'icon' => 'shield-check', 'permission' => 'platform.audit.view'],
            ]],
        ],
        'tenant' => [
            ['group' => 'Nav.groupOperation', 'items' => [
                ['label' => 'Nav.portfolio', 'route' => 'distribuidor', 'icon' => 'layout-dashboard', 'permission' => 'tenant.dashboard.view'],
                ['label' => 'Nav.companies', 'route' => 'distribuidor/negocios', 'icon' => 'store', 'permission' => 'tenant.companies.view'],
                ['label' => 'Nav.subscriptions', 'route' => 'distribuidor/suscripciones', 'icon' => 'receipt', 'permission' => 'tenant.subscriptions.view'],
                ['label' => 'Nav.commissions', 'route' => 'distribuidor/comisiones', 'icon' => 'percent', 'permission' => 'tenant.commissions.view'],
            ]],
            ['group' => 'Nav.groupSettings', 'items' => [
                ['label' => 'Nav.team', 'route' => 'distribuidor/equipo', 'icon' => 'users', 'permission' => 'tenant.users.view'],
                ['label' => 'Nav.branding', 'route' => 'distribuidor/marca', 'icon' => 'palette', 'permission' => 'tenant.branding.edit'],
                ['label' => 'Nav.audit', 'route' => 'distribuidor/auditoria', 'icon' => 'shield-check', 'permission' => 'tenant.audit.view'],
            ]],
        ],
        'company' => [
            ['group' => 'Nav.groupOperation', 'items' => [
                ['label' => 'Nav.dashboard', 'route' => 'negocio', 'icon' => 'layout-dashboard', 'permission' => 'company.dashboard.view'],
            ]],
            // Aquí se insertan los grupos de componentes (ComponentManager::menusFor)
            ['group' => 'Nav.groupSettings', 'items' => [
                ['label' => 'Nav.users', 'route' => 'negocio/usuarios', 'icon' => 'users', 'permission' => 'company.users.view'],
                ['label' => 'Nav.subscription', 'route' => 'negocio/plan', 'icon' => 'credit-card', 'permission' => 'company.subscription.view'],
                ['label' => 'Nav.settings', 'route' => 'negocio/configuracion', 'icon' => 'settings', 'permission' => 'company.settings.edit'],
                ['label' => 'Nav.support', 'route' => 'negocio/soporte', 'icon' => 'life-buoy', 'permission' => 'company.support.approve'],
                ['label' => 'Nav.tokens', 'route' => 'negocio/tokens', 'icon' => 'key-round', 'permission' => 'company.tokens.manage'],
                ['label' => 'Nav.audit', 'route' => 'negocio/auditoria', 'icon' => 'shield-check', 'permission' => 'company.audit.view'],
            ]],
        ],
    ];

    /** Ruta de inicio por ámbito. */
    public array $home = [
        'platform' => 'plataforma',
        'tenant'   => 'distribuidor',
        'company'  => 'negocio',
    ];
}
