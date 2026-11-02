<?php

/**
 * Componente Cotizaciones — catálogo de servicios y cotizaciones ligadas a oportunidades del CRM.
 * Totales siempre calculados en servidor; enlace público opaco de solo lectura para el cliente.
 */
return [
    'code'          => 'quote',
    'name'          => 'Cotizaciones',
    'name_key'      => 'Quote.componentName',
    'version'       => '1.0.0',
    'description'   => 'Catálogo de servicios con precios e impuestos y cotizaciones con seguimiento de estado.',
    'author'        => 'Herramientas Virtuales CO S.A.S.',
    'requires_core' => '1.0.0',
    'depends'       => ['crm'],
    'table_prefix'  => 'cmp_quote_',
    'scope'         => 'company',

    'permissions'   => [
        'quote.view'      => 'Ver cotizaciones y catálogo',
        'quote.create'    => 'Crear cotizaciones',
        'quote.edit'      => 'Editar, enviar y registrar respuesta del cliente',
        'quote.delete'    => 'Anular cotizaciones',
        'quote.configure' => 'Administrar el catálogo de servicios',
    ],
    'role_grants'   => [
        'sales_manager'   => ['quote.view', 'quote.create', 'quote.edit', 'quote.delete', 'quote.configure'],
        'sales_agent'     => ['quote.view', 'quote.create', 'quote.edit'],
        'project_manager' => ['quote.view'],
    ],
    'menus'         => [
        ['label' => 'Cotizaciones', 'label_key' => 'Quote.menu', 'icon' => 'icon-file-text', 'route' => 'cotizaciones', 'permission' => 'quote.view', 'order' => 30],
        ['label' => 'Catálogo de servicios', 'label_key' => 'Quote.menuCatalog', 'icon' => 'icon-package', 'route' => 'cotizaciones/catalogo', 'permission' => 'quote.configure', 'order' => 80],
    ],
    'routes'        => ['cotizaciones', 'quote-publico'],

    'plan_flag'     => 'quote_enabled',
    'plan_limits'   => [
        ['column' => 'max_quotes', 'label' => 'Cotizaciones', 'label_key' => 'Quote.limitQuotes', 'table' => 'cmp_quote_quotes', 'company_column' => 'company_id'],
    ],
    'settings'      => ['prefix' => 'COT', 'valid_days' => 15],
];
