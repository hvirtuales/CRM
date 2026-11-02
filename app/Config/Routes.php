<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas del núcleo. Los componentes declaran las suyas en components/<X>/Config/Routes.php.
 *
 * @var RouteCollection $routes
 */
$routes->setAutoRoute(false);

$routes->get('/', 'Home::index');

// ---------------------------------------------------------------- Acceso
$routes->get('ingresar', 'Auth::login');
$routes->post('ingresar', 'Auth::attempt');
$routes->get('mfa', 'Auth::mfa', ['filter' => 'auth:noscope']);
$routes->post('mfa', 'Auth::verifyMfa', ['filter' => 'auth:noscope']);
$routes->post('salir', 'Auth::logout');
// Invitaciones: públicas (el token es la credencial); aceptar exige ser el titular del correo.
$routes->get('invitacion/(:segment)', 'Invitations::show/$1');
$routes->post('invitacion/(:segment)', 'Invitations::accept/$1');
$routes->post('idioma/(:segment)', 'Auth::locale/$1'); // POST + CSRF: cambia preferencias

$routes->group('', ['filter' => 'auth:noscope'], static function (RouteCollection $routes) {
    $routes->get('contexto', 'ContextController::index');
    $routes->post('contexto', 'ContextController::switch');
    $routes->post('contexto/soporte', 'ContextController::support');
    $routes->get('perfil', 'Profile::index');
    $routes->post('perfil', 'Profile::update');
    $routes->get('perfil/clave', 'Profile::password');
    $routes->post('perfil/clave', 'Profile::changePassword');
    $routes->get('perfil/mfa', 'Profile::mfa');
    $routes->post('perfil/mfa', 'Profile::enableMfa');
    $routes->post('perfil/mfa/desactivar', 'Profile::disableMfa');
});

// ---------------------------------------------------------------- Superadministrador
$routes->group('plataforma', ['namespace' => 'App\Controllers\Platform', 'filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:platform.dashboard.view']);

    $routes->get('distribuidores', 'Tenants::index', ['filter' => 'permission:platform.tenants.view']);
    $routes->post('distribuidores', 'Tenants::store', ['filter' => 'permission:platform.tenants.create']);
    $routes->post('distribuidores/(:num)', 'Tenants::update/$1', ['filter' => 'permission:platform.tenants.edit']);
    $routes->post('distribuidores/(:num)/estado', 'Tenants::status/$1', ['filter' => 'permission:platform.tenants.edit']);

    $routes->get('negocios', 'Companies::index', ['filter' => 'permission:platform.companies.view']);
    $routes->post('negocios', 'Companies::store', ['filter' => 'permission:platform.companies.create']);
    $routes->post('negocios/(:num)/estado', 'Companies::status/$1', ['filter' => 'permission:platform.companies.edit']);
    $routes->post('negocios/(:num)/traslado', 'Companies::transfer/$1', ['filter' => 'permission:platform.companies.edit']);
    $routes->post('negocios/(:num)/plan', 'Companies::plan/$1', ['filter' => 'permission:platform.subscriptions.edit']);

    $routes->get('planes', 'Plans::index', ['filter' => 'permission:platform.plans.view']);
    $routes->post('planes', 'Plans::store', ['filter' => 'permission:platform.plans.edit']);
    $routes->post('planes/(:num)', 'Plans::update/$1', ['filter' => 'permission:platform.plans.edit']);

    $routes->get('suscripciones', 'Subscriptions::index', ['filter' => 'permission:platform.subscriptions.view']);
    $routes->post('suscripciones/(:num)/estado', 'Subscriptions::transition/$1', ['filter' => 'permission:platform.subscriptions.edit']);
    $routes->post('suscripciones/(:num)/pago', 'Subscriptions::payment/$1', ['filter' => 'permission:platform.payments.create']);

    $routes->get('comisiones', 'Commissions::index', ['filter' => 'permission:platform.commissions.view']);
    $routes->post('comisiones/(:num)/estado', 'Commissions::status/$1', ['filter' => 'permission:platform.commissions.edit']);

    $routes->get('componentes', 'Components::index', ['filter' => 'permission:platform.components.view']);
    $routes->post('componentes/(:segment)/estado', 'Components::status/$1', ['filter' => 'permission:platform.components.edit']);

    $routes->get('equipo', 'Team::index', ['filter' => 'permission:platform.users.view']);
    $routes->post('equipo', 'Team::store', ['filter' => 'permission:platform.users.edit']);
    $routes->post('equipo/(:num)', 'Team::update/$1', ['filter' => 'permission:platform.users.edit']);
    $routes->post('equipo/invitaciones/(:num)/revocar', 'Team::revokeInvitation/$1', ['filter' => 'permission:platform.users.edit']);

    $routes->get('soporte', 'Support::index', ['filter' => 'permission:platform.support.request']);
    $routes->post('soporte', 'Support::store', ['filter' => 'permission:platform.support.request']);

    $routes->get('auditoria', 'Audit::index', ['filter' => 'permission:platform.audit.view']);
});

// ---------------------------------------------------------------- Distribuidor
$routes->group('distribuidor', ['namespace' => 'App\Controllers\Tenant', 'filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:tenant.dashboard.view']);
    $routes->get('negocios', 'Companies::index', ['filter' => 'permission:tenant.companies.view']);
    $routes->post('negocios', 'Companies::store', ['filter' => 'permission:tenant.companies.create']);
    $routes->post('negocios/(:num)/plan', 'Companies::plan/$1', ['filter' => 'permission:tenant.subscriptions.edit']);
    $routes->get('suscripciones', 'Subscriptions::index', ['filter' => 'permission:tenant.subscriptions.view']);
    $routes->get('comisiones', 'Commissions::index', ['filter' => 'permission:tenant.commissions.view']);
    $routes->get('equipo', 'Team::index', ['filter' => 'permission:tenant.users.view']);
    $routes->post('equipo', 'Team::store', ['filter' => 'permission:tenant.users.edit']);
    $routes->post('equipo/(:num)', 'Team::update/$1', ['filter' => 'permission:tenant.users.edit']);
    $routes->post('equipo/invitaciones/(:num)/revocar', 'Team::revokeInvitation/$1', ['filter' => 'permission:tenant.users.edit']);
    $routes->get('marca', 'Branding::index', ['filter' => 'permission:tenant.branding.edit']);
    $routes->post('marca', 'Branding::update', ['filter' => 'permission:tenant.branding.edit']);
    $routes->get('auditoria', 'Audit::index', ['filter' => 'permission:tenant.audit.view']);
});

// ---------------------------------------------------------------- Negocio
$routes->group('negocio', ['namespace' => 'App\Controllers\Company', 'filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:company.dashboard.view']);
    $routes->get('usuarios', 'Users::index', ['filter' => 'permission:company.users.view']);
    $routes->post('usuarios', 'Users::store', ['filter' => 'permission:company.users.edit']);
    $routes->post('usuarios/(:num)', 'Users::update/$1', ['filter' => 'permission:company.users.edit']);
    $routes->post('usuarios/invitaciones/(:num)/revocar', 'Users::revokeInvitation/$1', ['filter' => 'permission:company.users.edit']);
    $routes->get('plan', 'Plan::index', ['filter' => 'permission:company.subscription.view']);
    $routes->get('configuracion', 'Settings::index', ['filter' => 'permission:company.settings.edit']);
    $routes->post('configuracion', 'Settings::update', ['filter' => 'permission:company.settings.edit']);
    $routes->get('soporte', 'Support::index', ['filter' => 'permission:company.support.approve']);
    $routes->post('soporte/(:num)', 'Support::decide/$1', ['filter' => 'permission:company.support.approve']);
    $routes->get('tokens', 'Tokens::index', ['filter' => 'permission:company.tokens.manage']);
    $routes->post('tokens', 'Tokens::store', ['filter' => 'permission:company.tokens.manage']);
    $routes->post('tokens/(:num)/revocar', 'Tokens::revoke/$1', ['filter' => 'permission:company.tokens.manage']);
    $routes->get('auditoria', 'Audit::index', ['filter' => 'permission:company.audit.view']);
});
