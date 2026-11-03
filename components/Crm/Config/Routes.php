<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

// Admin — sesión + permiso en CADA ruta; cambios de estado y borrados por POST + CSRF.
$routes->group('crm', ['namespace' => 'Components\Crm\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('seguimiento', 'FollowUp::index', ['filter' => 'permission:crm.view']);
    $routes->post('seguimiento/ajustes', 'Setup::followUpSettings', ['filter' => 'permission:crm.configure']);
    $routes->post('proyectos/(:num)/equipo', 'Setup::team/$1', ['filter' => 'permission:crm.configure']);
    $routes->get('oportunidades', 'Opportunities::index', ['filter' => 'permission:crm.view']);
    $routes->post('oportunidades', 'Opportunities::store', ['filter' => 'permission:crm.create']);
    $routes->post('oportunidades/(:num)', 'Opportunities::update/$1', ['filter' => 'permission:crm.edit']);
    $routes->post('oportunidades/(:num)/etapa', 'Opportunities::move/$1', ['filter' => 'permission:crm.edit']);
    $routes->post('oportunidades/(:num)/actividad', 'Opportunities::activity/$1', ['filter' => 'permission:crm.edit']);
    $routes->post('oportunidades/(:num)/archivar', 'Opportunities::archive/$1', ['filter' => 'permission:crm.delete']);

    $routes->get('contactos', 'Contacts::index', ['filter' => 'permission:crm.view']);
    $routes->get('contactos/exportar', 'Contacts::export', ['filter' => 'permission:crm.export']);
    $routes->post('contactos', 'Contacts::store', ['filter' => 'permission:crm.create']);
    $routes->post('contactos/(:num)', 'Contacts::update/$1', ['filter' => 'permission:crm.edit']);
    $routes->post('contactos/(:num)/archivar', 'Contacts::archive/$1', ['filter' => 'permission:crm.delete']);

    $routes->get('configuracion', 'Setup::index', ['filter' => 'permission:crm.configure']);
    $routes->post('proyectos', 'Setup::storeProject', ['filter' => 'permission:crm.configure']);
    $routes->post('proyectos/(:num)', 'Setup::updateProject/$1', ['filter' => 'permission:crm.configure']);
    $routes->post('etapas', 'Setup::storeStage', ['filter' => 'permission:crm.configure']);
    $routes->post('etapas/(:num)', 'Setup::updateStage/$1', ['filter' => 'permission:crm.configure']);
    $routes->post('etapas/(:num)/eliminar', 'Setup::deleteStage/$1', ['filter' => 'permission:crm.configure']);
});

// API — Bearer (apiauth), mismos permisos que la web, sin CSRF.
$routes->group('api/v1/crm', ['namespace' => 'Components\Crm\Controllers\Api', 'filter' => 'apiauth'], static function ($routes) {
    $routes->get('contacts', 'Contacts::index', ['filter' => 'permission:crm.view']);
    $routes->get('contacts/(:num)', 'Contacts::show/$1', ['filter' => 'permission:crm.view']);
    $routes->post('contacts', 'Contacts::create', ['filter' => 'permission:crm.create']);
    $routes->get('opportunities', 'Opportunities::index', ['filter' => 'permission:crm.view']);
    $routes->post('opportunities', 'Opportunities::create', ['filter' => 'permission:crm.create']);
});
