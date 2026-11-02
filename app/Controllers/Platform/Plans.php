<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\Result;
use Config\Services;

/**
 * Planes y entitlements. Los recuadros de cada componente se generan desde su Manifest
 * (ComponentManager::planFormGroups), así el formulario no se toca al instalar uno nuevo.
 */
class Plans extends BaseController
{
    public function index()
    {
        $editId = (int) $this->request->getGet('edit');

        return view('platform/plans', [
            'plans'  => db_connect()->table('plans')->orderBy('target')->orderBy('price')->get()->getResultArray(),
            'groups' => Services::components()->planFormGroups(),
            'edit'   => $editId ? db_connect()->table('plans')->where('id', $editId)->get()->getRowArray() : null,
        ]);
    }

    public function store()
    {
        $data = $this->collect();
        if ($data instanceof Result) {
            return $this->afterResult($data, '', null, 'plataforma/planes?new=1');
        }
        $db = db_connect();
        if ($db->table('plans')->where('code', $data['code'])->countAllResults()) {
            return $this->afterResult(Result::fail(lang('Core.validation.fix'), ['code' => lang('Core.validation.codeTaken')]), '', null, 'plataforma/planes?new=1');
        }
        $db->table('plans')->insert($data + ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log('plan.created', 'plan', (int) $db->insertID(), ['code' => $data['code']]);

        return redirect()->to(site_url('plataforma/planes'))->with('message', lang('Core.saved'));
    }

    public function update(int $id)
    {
        $db = db_connect();
        $plan = $db->table('plans')->where('id', $id)->get()->getRowArray();
        if (! $plan) {
            return $this->afterResult(Result::notFound(), 'plataforma/planes');
        }
        $data = $this->collect();
        if ($data instanceof Result) {
            return $this->afterResult($data, '', null, 'plataforma/planes?edit=' . $id);
        }
        unset($data['code']); // el código es estable
        $db->table('plans')->where('id', $id)->update($data + ['updated_at' => date('Y-m-d H:i:s')]);
        Services::plans()->flush();
        Services::audit()->log('plan.updated', 'plan', $id, $data);

        return redirect()->to(site_url('plataforma/planes'))->with('message', lang('Core.saved'));
    }

    /** @return array<string, mixed>|Result */
    private function collect(): array|Result
    {
        $p      = $this->request->getPost();
        $errors = [];
        $code   = strtolower(trim((string) ($p['code'] ?? '')));
        if (! preg_match('/^[a-z0-9_\-]{2,40}$/', $code)) {
            $errors['code'] = lang('Core.validation.code');
        }
        $name = trim((string) ($p['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = lang('Core.validation.name');
        }
        $price = (string) ($p['price'] ?? '0');
        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $price)) {
            $errors['price'] = lang('Core.validation.amount');
        }
        $currency = strtoupper((string) ($p['currency'] ?? 'COP'));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = lang('Core.validation.currency');
        }
        $target = in_array($p['target'] ?? '', ['company', 'tenant'], true) ? $p['target'] : 'company';
        $period = in_array($p['billing_period'] ?? '', ['monthly', 'yearly'], true) ? $p['billing_period'] : 'monthly';
        foreach (['trial_days', 'max_users'] as $f) {
            if (! ctype_digit((string) ($p[$f] ?? '0')) || (int) $p[$f] > 100000) {
                $errors[$f] = lang('Core.validation.integer');
            }
        }
        if ($errors) {
            return Result::fail(lang('Core.validation.fix'), $errors);
        }

        $data = [
            'code' => $code, 'name' => $name, 'description' => trim((string) ($p['description'] ?? '')) ?: null,
            'target' => $target, 'price' => $price, 'currency' => $currency, 'billing_period' => $period,
            'trial_days' => (int) ($p['trial_days'] ?? 0), 'max_users' => (int) ($p['max_users'] ?? 0),
            'is_active' => isset($p['is_active']) ? 1 : 0, 'is_public' => isset($p['is_public']) ? 1 : 0,
        ];
        // Columnas de componentes: solo las que existen en `plans` (planColumns).
        foreach (Services::components()->planFormGroups() as $g) {
            if ($g['flag']) {
                $data[$g['flag']] = isset($p[$g['flag']]) ? 1 : 0;
            }
            foreach ($g['limits'] as $l) {
                $v = (string) ($p[$l['column']] ?? '0');
                $data[$l['column']] = ctype_digit($v) ? min((int) $v, 10_000_000) : 0;
            }
        }

        return $data;
    }
}
