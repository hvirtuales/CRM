<?php

namespace App\Libraries;

use Config\Services;

/**
 * Tokens de API por negocio. El token en claro se muestra una sola vez; se guarda SHA-256.
 * Las habilidades son un subconjunto de los permisos del creador en ese negocio.
 */
class ApiTokenService
{
    public const PREFIX = 'hvk_';

    /**
     * @param list<string> $abilities
     */
    public function create(Context $ctx, string $name, array $abilities, int $days): Result
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            return Result::fail(lang('Core.validation.fix'), ['name' => lang('Core.validation.name')]);
        }
        if ($days < 1 || $days > 365) {
            return Result::fail(lang('Core.validation.fix'), ['days' => lang('Core.validation.days')]);
        }
        $allowed   = $ctx->permissions();
        $abilities = array_values(array_unique(array_filter($abilities, static fn ($a) => is_string($a) && in_array($a, $allowed, true))));
        if ($abilities === []) {
            return Result::fail(lang('Core.validation.fix'), ['abilities' => lang('Core.validation.abilities')]);
        }

        $plain = self::PREFIX . bin2hex(random_bytes(24));
        $now   = date('Y-m-d H:i:s');
        db_connect()->table('api_tokens')->insert([
            'user_id' => $ctx->userId(), 'membership_id' => $ctx->membership()['id'], 'company_id' => $ctx->companyId(),
            'name' => $name, 'token_hash' => hash('sha256', $plain), 'abilities' => json_encode($abilities),
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) db_connect()->insertID();
        Services::audit()->log('api_token.created', 'api_token', $id, ['name' => $name, 'abilities' => $abilities]);

        return Result::success(['id' => $id, 'token' => $plain]);
    }

    public function revoke(int $tokenId, int $companyId): Result
    {
        $db = db_connect();
        if (! $db->table('api_tokens')->where('id', $tokenId)->where('company_id', $companyId)->where('revoked_at', null)->countAllResults()) {
            return Result::notFound();
        }
        $db->table('api_tokens')->where('id', $tokenId)->update(['revoked_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        Services::audit()->log('api_token.revoked', 'api_token', $tokenId);

        return Result::success();
    }

    /**
     * Resuelve un token Bearer. Devuelve [usuario, membresía, habilidades] o null.
     *
     * @return array{0: array<string,mixed>, 1: int, 2: list<string>, 3: int}|null
     */
    public function resolve(string $plain): ?array
    {
        if (! str_starts_with($plain, self::PREFIX) || strlen($plain) !== strlen(self::PREFIX) + 48) {
            return null;
        }
        $db  = db_connect();
        $tok = $db->table('api_tokens')->where('token_hash', hash('sha256', $plain))->where('revoked_at', null)->get()->getRowArray();
        if (! $tok || strtotime((string) $tok['expires_at']) <= time()) {
            return null;
        }
        $user = $db->table('users')->where('id', $tok['user_id'])->where('status', 'active')->where('deleted_at', null)->get()->getRowArray();
        if (! $user) {
            return null;
        }
        $db->table('api_tokens')->where('id', $tok['id'])->update(['last_used_at' => date('Y-m-d H:i:s')]);

        return [$user, (int) $tok['membership_id'], json_decode((string) $tok['abilities'], true) ?: [], (int) $tok['id']];
    }

    /** @return list<array<string, mixed>> */
    public function forCompany(int $companyId): array
    {
        return db_connect()->table('api_tokens t')->select('t.id, t.name, t.abilities, t.last_used_at, t.expires_at, t.revoked_at, t.created_at, u.name AS user_name')
            ->join('users u', 'u.id = t.user_id')->where('t.company_id', $companyId)->orderBy('t.id', 'DESC')->get()->getResultArray();
    }
}
