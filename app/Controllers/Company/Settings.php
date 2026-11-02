<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use Config\Services;

class Settings extends BaseController
{
    public function index()
    {
        return view('company/settings', ['company' => Services::context()->company()]);
    }

    public function update()
    {
        $p      = $this->request->getPost();
        $errors = [];
        $name   = trim((string) ($p['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = lang('Core.validation.name');
        }
        if (! empty($p['email']) && ! filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = lang('Core.validation.email');
        }
        if (! empty($p['tax_id']) && ! preg_match('/^[0-9A-Za-z.\-]{3,40}$/', (string) $p['tax_id'])) {
            $errors['tax_id'] = lang('Core.validation.taxId');
        }
        if (! empty($p['phone']) && ! preg_match('/^[0-9+()\s\-]{7,40}$/', (string) $p['phone'])) {
            $errors['phone'] = lang('Core.validation.phone');
        }
        if (! in_array($p['timezone'] ?? '', \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = lang('Core.validation.timezone');
        }
        if (! preg_match('/^[A-Z]{3}$/', (string) ($p['currency'] ?? ''))) {
            $errors['currency'] = lang('Core.validation.currency');
        }
        if (! in_array($p['locale'] ?? '', ['es', 'en'], true)) {
            $errors['locale'] = lang('Core.validation.fix');
        }
        if ($errors) {
            return redirect()->back()->withInput()->with('error', lang('Core.validation.fix'))->with('errors', $errors);
        }

        $cid = (int) Services::context()->companyId();
        $data = [
            'name' => $name, 'email' => strtolower(trim((string) $p['email'])) ?: null, 'tax_id' => trim((string) $p['tax_id']) ?: null,
            'phone' => trim((string) $p['phone']) ?: null, 'timezone' => $p['timezone'], 'currency' => $p['currency'],
            'locale' => $p['locale'], 'updated_at' => date('Y-m-d H:i:s'),
        ];
        db_connect()->table('companies')->where('id', $cid)->update($data);
        Services::audit()->log('company.settings_updated', 'company', $cid, array_diff_key($data, ['updated_at' => 1]));

        return redirect()->to(site_url('negocio/configuracion'))->with('message', lang('Core.saved'));
    }
}
