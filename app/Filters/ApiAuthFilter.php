<?php

namespace App\Filters;

use App\Libraries\Rls;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * `apiauth`: Authorization: Bearer hvk_… → contexto de negocio del token, restringido a sus
 * habilidades. Sin sesión ni CSRF. Límite de 120 peticiones/minuto por token.
 */
class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(\S+)$/', $header, $m)) {
            return $this->fail(401, lang('Core.apiUnauthorized'));
        }

        $resolved = Services::apiTokens()->resolve($m[1]);
        if ($resolved === null) {
            return $this->fail(401, lang('Core.apiUnauthorized'));
        }
        [$user, $membershipId, $abilities, $tokenId] = $resolved;

        if (! Services::throttler()->check('api-' . $tokenId, 120, MINUTE)) {
            return $this->fail(429, lang('Core.tooManyRequests'));
        }

        $ctx = Services::resetContext();
        if (! $ctx->loadForMembership($user, $membershipId, true) || $ctx->companyId() === null) {
            return $this->fail(401, lang('Core.apiUnauthorized'));
        }
        $ctx->restrictTo($abilities);
        Rls::setCompany($ctx->companyId());

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function fail(int $status, string $message)
    {
        return Services::response()->setStatusCode($status)->setJSON(['messages' => ['error' => $message]]);
    }
}
