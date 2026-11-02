<?php

namespace App\Controllers\Company;

use App\Controllers\BaseController;
use Config\Services;

class Tokens extends BaseController
{
    public function index()
    {
        $ctx = Services::context();
        // Solo habilidades que la API expone y que el actor tiene.
        $apiAbilities = array_values(array_filter($ctx->permissions(), static fn ($p) => preg_match('/^[a-z]{3,8}\.(view|create|edit)$/', $p) && ! str_starts_with($p, 'company.')));

        return view('company/tokens', [
            'tokens'    => Services::apiTokens()->forCompany((int) $ctx->companyId()),
            'abilities' => $apiAbilities,
        ]);
    }

    public function store()
    {
        $p = $this->request->getPost();
        $r = Services::apiTokens()->create(Services::context(), (string) ($p['name'] ?? ''), (array) ($p['abilities'] ?? []), (int) ($p['days'] ?? 90));
        if ($r->ok) {
            session()->setFlashdata('api_token', $r->data['token']);
        }

        return $this->afterResult($r, 'negocio/tokens', lang('Core.tokenCreated'));
    }

    public function revoke(int $id)
    {
        return $this->afterResult(Services::apiTokens()->revoke($id, (int) Services::context()->companyId()), 'negocio/tokens', lang('Core.tokenRevoked'));
    }
}
