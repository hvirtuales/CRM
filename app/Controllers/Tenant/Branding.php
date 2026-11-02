<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use App\Libraries\BrandService;
use Config\Services;

/** Marca blanca del distribuidor: nombre visible y color de acento (validado #RRGGBB). */
class Branding extends BaseController
{
    public function index()
    {
        return view('tenant/branding', ['tenant' => Services::context()->tenant()]);
    }

    public function update()
    {
        $color = strtoupper(trim((string) $this->request->getPost('brand_color')));
        $name  = trim((string) $this->request->getPost('brand_name'));
        if ($color !== '' && ! BrandService::valid($color)) {
            return redirect()->back()->withInput()->with('error', lang('Core.validation.color'))->with('errors', ['brand_color' => lang('Core.validation.color')]);
        }
        if (mb_strlen($name) > 120) {
            return redirect()->back()->withInput()->with('errors', ['brand_name' => lang('Core.validation.name')]);
        }
        $tid = (int) Services::context()->tenantId();
        db_connect()->table('tenants')->where('id', $tid)->update([
            'brand_color' => $color ?: null, 'brand_name' => $name ?: null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Services::audit()->log('tenant.branding_updated', 'tenant', $tid, ['brand_color' => $color]);

        return redirect()->to(site_url('distribuidor/marca'))->with('message', lang('Core.saved'));
    }
}
