<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('cotizaciones', ['namespace' => 'Components\Cotizaciones\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Quotes::index', ['filter' => 'permission:quote.view']);
    $routes->post('/', 'Quotes::store', ['filter' => 'permission:quote.create']);
    $routes->post('(:num)', 'Quotes::update/$1', ['filter' => 'permission:quote.edit']);
    $routes->post('(:num)/estado', 'Quotes::transition/$1', ['filter' => 'permission:quote.edit']);
    $routes->post('(:num)/anular', 'Quotes::void/$1', ['filter' => 'permission:quote.delete']);

    $routes->get('catalogo', 'Catalog::index', ['filter' => 'permission:quote.configure']);
    $routes->post('catalogo', 'Catalog::store', ['filter' => 'permission:quote.configure']);
    $routes->post('catalogo/(:num)', 'Catalog::update/$1', ['filter' => 'permission:quote.configure']);
});

// Público — enlace opaco de solo lectura para el cliente (sin auth, sin escritura).
$routes->group('quote-publico', ['namespace' => 'Components\Cotizaciones\Controllers'], static function ($routes) {
    $routes->get('(:segment)', 'PublicQuote::show/$1');
});
