<?php

/**
 * Componente CRM comercial — contactos, proyectos (líneas de servicio), pipeline y oportunidades.
 * Ámbito: negocio (company_id). Datos nunca compartidos entre negocios.
 */
return [
    'code'          => 'crm',
    'name'          => 'CRM comercial',
    'name_key'      => 'Crm.componentName',
    'version'       => '1.1.0',
    'description'   => 'Contactos, proyectos, embudo de oportunidades y actividades de seguimiento.',
    'author'        => 'Herramientas Virtuales CO S.A.S.',
    'requires_core' => '1.0.0',
    'depends'       => [],
    'table_prefix'  => 'cmp_crm_',
    'scope'         => 'company',

    'permissions'   => [
        'crm.view'      => 'Ver contactos y oportunidades',
        'crm.create'    => 'Crear contactos y oportunidades',
        'crm.edit'      => 'Editar y mover oportunidades propias, registrar actividades',
        'crm.delete'    => 'Archivar contactos y oportunidades',
        'crm.manage'    => 'Gestionar oportunidades de otros asesores',
        'crm.configure' => 'Configurar proyectos y etapas del embudo',
        'crm.export'    => 'Exportar contactos',
    ],

    // Concesiones por rol de negocio (extensión del contrato del núcleo HV CRM).
    'role_grants'   => [
        'sales_manager'   => ['crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.manage', 'crm.export'],
        'sales_agent'     => ['crm.view', 'crm.create', 'crm.edit'],
        'marketing'       => ['crm.view', 'crm.create'],
        'support_agent'   => ['crm.view', 'crm.edit'],
        'project_manager' => ['crm.view', 'crm.edit', 'crm.manage', 'crm.configure'],
    ],

    'menus'         => [
        ['label' => 'Seguimiento', 'label_key' => 'Crm.menuFollowUp', 'icon' => 'icon-activity', 'route' => 'crm/seguimiento', 'permission' => 'crm.view', 'order' => 5],
        ['label' => 'Oportunidades', 'label_key' => 'Crm.menuPipeline', 'icon' => 'icon-kanban', 'route' => 'crm/oportunidades', 'permission' => 'crm.view', 'order' => 10],
        ['label' => 'Contactos', 'label_key' => 'Crm.menuContacts', 'icon' => 'icon-contact', 'route' => 'crm/contactos', 'permission' => 'crm.view', 'order' => 20],
        ['label' => 'Proyectos y etapas', 'label_key' => 'Crm.menuSetup', 'icon' => 'icon-folder-kanban', 'route' => 'crm/configuracion', 'permission' => 'crm.configure', 'order' => 90],
    ],

    'routes'        => ['crm', 'api/v1/crm'],

    'plan_flag'     => 'crm_enabled',
    'plan_limits'   => [
        ['column' => 'max_contacts', 'label' => 'Contactos', 'label_key' => 'Crm.limitContacts', 'table' => 'cmp_crm_contacts', 'company_column' => 'company_id'],
        ['column' => 'max_opportunities', 'label' => 'Oportunidades', 'label_key' => 'Crm.limitOpportunities', 'table' => 'cmp_crm_opportunities', 'company_column' => 'company_id'],
    ],

    'settings'      => [],
];
