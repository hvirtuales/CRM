<?php

namespace Components\Crm\Config;

use CodeIgniter\Config\BaseService;
use Components\Crm\Libraries\ContactService;
use Components\Crm\Libraries\LeadCapture;
use Components\Crm\Libraries\OpportunityService;
use Components\Crm\Libraries\PlanGuard;
use Components\Crm\Libraries\SetupService;

/** Servicios del componente, prefijados con su code (crm). */
class Services extends BaseService
{
    public static function crmContacts(bool $getShared = true): ContactService
    {
        return $getShared ? static::getSharedInstance('crmContacts') : new ContactService();
    }

    public static function crmOpportunities(bool $getShared = true): OpportunityService
    {
        return $getShared ? static::getSharedInstance('crmOpportunities') : new OpportunityService();
    }

    public static function crmSetup(bool $getShared = true): SetupService
    {
        return $getShared ? static::getSharedInstance('crmSetup') : new SetupService();
    }

    public static function crmPlanGuard(bool $getShared = true): PlanGuard
    {
        return $getShared ? static::getSharedInstance('crmPlanGuard') : new PlanGuard();
    }

    /** Interfaz pública para otros componentes. */
    public static function crmLeads(bool $getShared = true): LeadCapture
    {
        return $getShared ? static::getSharedInstance('crmLeads') : new LeadCapture();
    }
}
