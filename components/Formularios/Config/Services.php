<?php

namespace Components\Formularios\Config;

use CodeIgniter\Config\BaseService;
use Components\Formularios\Libraries\FormService;
use Components\Formularios\Libraries\SubmissionService;

class Services extends BaseService
{
    public static function formForms(bool $getShared = true): FormService
    {
        return $getShared ? static::getSharedInstance('formForms') : new FormService();
    }

    public static function formSubmissions(bool $getShared = true): SubmissionService
    {
        return $getShared ? static::getSharedInstance('formSubmissions') : new SubmissionService();
    }
}
