<?php

/**
 * Componente Formularios — constructor de formularios por proyecto, publicación versionada y
 * captura pública que crea contacto + oportunidad en el CRM del MISMO negocio.
 */
return [
    'code'          => 'form',
    'name'          => 'Formularios',
    'name_key'      => 'Form.componentName',
    'version'       => '1.0.0',
    'description'   => 'Formularios públicos de captación con consentimiento, antispam y envío al embudo comercial.',
    'author'        => 'Herramientas Virtuales CO S.A.S.',
    'requires_core' => '1.0.0',
    'depends'       => ['crm'],
    'table_prefix'  => 'cmp_form_',
    'scope'         => 'company',

    'permissions'   => [
        'form.view'   => 'Ver formularios y respuestas',
        'form.create' => 'Crear formularios',
        'form.edit'   => 'Editar, publicar y cerrar formularios',
        'form.delete' => 'Archivar formularios',
    ],
    'role_grants'   => [
        'marketing'       => ['form.view', 'form.create', 'form.edit', 'form.delete'],
        'sales_manager'   => ['form.view'],
        'project_manager' => ['form.view', 'form.create', 'form.edit'],
    ],

    'menus'         => [
        ['label' => 'Formularios', 'label_key' => 'Form.menu', 'icon' => 'icon-clipboard-list', 'route' => 'formularios', 'permission' => 'form.view', 'order' => 40],
    ],
    'routes'        => ['formularios', 'form-publico'],

    'plan_flag'     => 'form_enabled',
    'plan_limits'   => [
        ['column' => 'max_forms', 'label' => 'Formularios', 'label_key' => 'Form.limitForms', 'table' => 'cmp_form_forms', 'company_column' => 'company_id'],
    ],
    'settings'      => ['min_seconds' => 3, 'token_ttl' => 7200, 'per_ip_per_minute' => 5],
];
