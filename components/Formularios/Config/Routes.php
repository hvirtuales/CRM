<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('formularios', ['namespace' => 'Components\Formularios\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Forms::index', ['filter' => 'permission:form.view']);
    $routes->get('(:num)/respuestas', 'Forms::submissions/$1', ['filter' => 'permission:form.view']);
    $routes->post('/', 'Forms::store', ['filter' => 'permission:form.create']);
    $routes->post('(:num)', 'Forms::update/$1', ['filter' => 'permission:form.edit']);
    $routes->post('(:num)/publicar', 'Forms::publish/$1', ['filter' => 'permission:form.edit']);
    $routes->post('(:num)/cerrar', 'Forms::close/$1', ['filter' => 'permission:form.edit']);
    $routes->post('(:num)/archivar', 'Forms::archive/$1', ['filter' => 'permission:form.delete']);
});

// Público — sin auth, separado y evidente. CSRF exceptuado en el núcleo (embebible); ver SubmissionService.
$routes->group('form-publico', ['namespace' => 'Components\Formularios\Controllers'], static function ($routes) {
    $routes->get('(:segment)', 'PublicForm::show/$1');
    $routes->post('(:segment)', 'PublicForm::submit/$1');
});
