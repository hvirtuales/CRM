<?php

namespace Components\Cotizaciones\Config;

use CodeIgniter\Config\BaseService;
use Components\Cotizaciones\Libraries\CatalogService;
use Components\Cotizaciones\Libraries\QuoteService;

class Services extends BaseService
{
    public static function quoteQuotes(bool $getShared = true): QuoteService
    {
        return $getShared ? static::getSharedInstance('quoteQuotes') : new QuoteService();
    }

    public static function quoteCatalog(bool $getShared = true): CatalogService
    {
        return $getShared ? static::getSharedInstance('quoteCatalog') : new CatalogService();
    }
}
