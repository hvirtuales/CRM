<?php

namespace App\Controllers;

use Config\Navigation;
use Config\Services;

class Home extends BaseController
{
    public function index()
    {
        $ctx = Services::context();
        if (! $ctx->loadFromSession()) {
            return redirect()->to(site_url('ingresar'));
        }
        if ($ctx->scope() === null) {
            return redirect()->to(site_url('contexto'));
        }

        return redirect()->to(site_url(config(Navigation::class)->home[$ctx->scope()]));
    }
}
