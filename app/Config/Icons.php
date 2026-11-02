<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Íconos disponibles en public/assets/img/icons.svg (Lucide 0.468.0, licencia ISC).
 * Los Manifest declaran `icon-<nombre>`; ComponentManager rechaza íconos inexistentes.
 */
class Icons extends BaseConfig
{
    /** @var list<string> */
    public array $available = [
        'activity',
        'arrow-down',
        'arrow-left-right',
        'arrow-up',
        'ban',
        'briefcase',
        'building-2',
        'calendar',
        'check',
        'chevron-down',
        'chevron-left',
        'chevron-right',
        'circle-check',
        'circle-dot',
        'circle-x',
        'clipboard-list',
        'contact',
        'copy',
        'credit-card',
        'download',
        'external-link',
        'eye',
        'file-text',
        'folder-kanban',
        'grip-vertical',
        'handshake',
        'info',
        'kanban',
        'key-round',
        'languages',
        'layout-dashboard',
        'life-buoy',
        'list-ordered',
        'list',
        'lock',
        'log-out',
        'mail',
        'menu',
        'moon',
        'package',
        'palette',
        'pencil',
        'percent',
        'phone',
        'play',
        'plus',
        'printer',
        'puzzle',
        'receipt',
        'refresh-cw',
        'search',
        'send',
        'settings',
        'shield-check',
        'store',
        'sun',
        'trash-2',
        'triangle-alert',
        'trophy',
        'user-cog',
        'user-plus',
        'users',
        'wallet',
        'x',
    ];
}
