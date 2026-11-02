<?php

namespace Config;

use App\Libraries\ApiTokenService;
use App\Libraries\AuditService;
use App\Libraries\AuthService;
use App\Libraries\ComponentManager;
use App\Libraries\Context;
use App\Libraries\ContextSwitcher;
use App\Libraries\PlanService;
use App\Libraries\ProvisioningService;
use App\Libraries\RbacService;
use App\Libraries\SubscriptionService;
use App\Libraries\SupportGrantService;
use App\Libraries\UserService;
use CodeIgniter\Config\BaseService;

/**
 * Servicios del núcleo. Los componentes registran los suyos en su propio Config/Services.php
 * con nombres prefijados por su `code`.
 */
class Services extends BaseService
{
    public static function context(bool $getShared = true): Context
    {
        return $getShared ? static::getSharedInstance('context') : new Context();
    }

    /** Sustituye el contexto compartido (tras login o cambio de ámbito). */
    public static function resetContext(): Context
    {
        $ctx = new Context();
        static::$instances['context'] = $ctx;

        return $ctx;
    }

    /**
     * Estado por petición: nuevo contexto y cachés vacías. Evita que el ámbito o los permisos de
     * una petición se filtren a la siguiente (modo worker de CI 4.7, pruebas, procesos largos).
     */
    public static function resetRequestState(): Context
    {
        foreach (['rbac', 'plans', 'components'] as $name) {
            if (isset(static::$instances[$name])) {
                static::$instances[$name]->flush();
            }
        }
        unset(static::$instances['invitations']);

        return static::resetContext();
    }

    public static function rbac(bool $getShared = true): RbacService
    {
        return $getShared ? static::getSharedInstance('rbac') : new RbacService();
    }

    public static function components(bool $getShared = true): ComponentManager
    {
        return $getShared ? static::getSharedInstance('components') : new ComponentManager();
    }

    public static function plans(bool $getShared = true): PlanService
    {
        return $getShared ? static::getSharedInstance('plans') : new PlanService();
    }

    public static function subscriptions(bool $getShared = true): SubscriptionService
    {
        return $getShared ? static::getSharedInstance('subscriptions') : new SubscriptionService();
    }

    public static function audit(bool $getShared = true): AuditService
    {
        return $getShared ? static::getSharedInstance('audit') : new AuditService();
    }

    public static function auth(bool $getShared = true): AuthService
    {
        return $getShared ? static::getSharedInstance('auth') : new AuthService();
    }

    public static function users(bool $getShared = true): UserService
    {
        return $getShared ? static::getSharedInstance('users') : new UserService();
    }

    public static function provisioning(bool $getShared = true): ProvisioningService
    {
        return $getShared ? static::getSharedInstance('provisioning') : new ProvisioningService();
    }

    public static function contextSwitcher(bool $getShared = true): ContextSwitcher
    {
        return $getShared ? static::getSharedInstance('contextSwitcher') : new ContextSwitcher();
    }

    public static function supportGrants(bool $getShared = true): SupportGrantService
    {
        return $getShared ? static::getSharedInstance('supportGrants') : new SupportGrantService();
    }

    public static function invitations(bool $getShared = true): \App\Libraries\InvitationService
    {
        return $getShared ? static::getSharedInstance('invitations') : new \App\Libraries\InvitationService();
    }

    public static function apiTokens(bool $getShared = true): ApiTokenService
    {
        return $getShared ? static::getSharedInstance('apiTokens') : new ApiTokenService();
    }
}
