<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Automatización comercial de la plataforma (Bloque 1).
 *
 * El negocio «casa» es Herramientas Virtuales dentro de su propia plataforma: las oportunidades de
 * renovación, conversión de prueba y ampliación de los negocios DIRECTOS se crean en su CRM.
 * Los negocios de distribuidores no generan oportunidades en el CRM de HV: el distribuidor las ve
 * en su panel de cartera (la relación comercial es suya).
 */
class Commercial extends BaseConfig
{
    /** Slug del negocio de Herramientas Virtuales (sobrescribible con HV_HOUSE_COMPANY). */
    public string $houseCompanySlug = 'herramientas-virtuales';

    /** Proyecto del CRM de HV donde caen estas oportunidades (se crea si no existe). */
    public string $houseProjectCode = 'suscripciones-hv-crm';
    public string $houseProjectName = 'Suscripciones HV CRM';

    /** Días antes del corte para abrir la oportunidad de renovación. */
    public int $renewalDays = 30;

    /** Días antes del fin de la prueba para abrir la oportunidad de conversión. */
    public int $trialDays = 3;

    /** Porcentaje de uso de un cupo del plan que dispara la oportunidad de ampliación. */
    public float $usageThreshold = 0.8;

    public function __construct()
    {
        parent::__construct();
        if (($slug = env('HV_HOUSE_COMPANY')) && preg_match('/^[a-z0-9\-]{2,80}$/', (string) $slug)) {
            $this->houseCompanySlug = (string) $slug;
        }
    }
}
