<?php

namespace App\Libraries;

use Config\Rbac;
use Config\Services;

/**
 * Acceso de soporte: la plataforma NO ve datos de negocio por defecto. El soporte solicita acceso
 * justificado; el negocio lo aprueba con vencimiento; el acceso es de solo lectura y auditado.
 */
class SupportGrantService
{
    public function request(int $userId, int $companyId, string $reason, ?string $ticket, int $hours): Result
    {
        $max = config(Rbac::class)->supportMaxHours;
        $errors = [];
        if (mb_strlen(trim($reason)) < 15 || mb_strlen($reason) > 1000) {
            $errors['reason'] = lang('Core.validation.supportReason');
        }
        if ($hours < 1 || $hours > $max) {
            $errors['hours'] = lang('Core.validation.hours', [$max]);
        }
        if ($ticket !== null && $ticket !== '' && ! preg_match('/^[A-Za-z0-9#\-_.]{1,60}$/', $ticket)) {
            $errors['ticket_ref'] = lang('Core.validation.ticket');
        }
        $db = db_connect();
        if (! $db->table('companies')->where('id', $companyId)->where('deleted_at', null)->countAllResults()) {
            return Result::notFound();
        }
        if ($errors !== []) {
            return Result::fail(lang('Core.validation.fix'), $errors);
        }
        $pending = $db->table('support_grants')->where('user_id', $userId)->where('company_id', $companyId)->where('status', 'pending')->countAllResults();
        if ($pending > 0) {
            return Result::fail(lang('Core.supportAlreadyPending'), [], 409);
        }

        $now = date('Y-m-d H:i:s');
        $db->table('support_grants')->insert([
            'user_id' => $userId, 'company_id' => $companyId, 'reason' => trim($reason),
            'ticket_ref' => $ticket ?: null, 'status' => 'pending', 'hours' => $hours,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        Services::audit()->log('support.requested', 'support_grant', $id, ['hours' => $hours, 'ticket' => $ticket], $companyId);

        return Result::success(['id' => $id]);
    }

    /** Decisión del negocio sobre una solicitud que le pertenece. */
    public function decide(int $grantId, int $companyId, string $decision, int $actorId): Result
    {
        $db    = db_connect();
        $grant = $db->table('support_grants')->where('id', $grantId)->where('company_id', $companyId)->get()->getRowArray();
        if (! $grant) {
            return Result::notFound();
        }

        $now = date('Y-m-d H:i:s');
        if ($decision === 'approve' && $grant['status'] === 'pending') {
            $update = ['status' => 'approved', 'expires_at' => date('Y-m-d H:i:s', time() + (int) $grant['hours'] * 3600)];
        } elseif ($decision === 'reject' && $grant['status'] === 'pending') {
            $update = ['status' => 'rejected'];
        } elseif ($decision === 'revoke' && $grant['status'] === 'approved') {
            $update = ['status' => 'revoked'];
        } else {
            return Result::fail(lang('Core.transitionInvalid', [lang('Core.status.' . $grant['status']), $decision]), [], 409);
        }

        $db->table('support_grants')->where('id', $grantId)->update($update + ['decided_by' => $actorId, 'decided_at' => $now, 'updated_at' => $now]);
        Services::audit()->log('support.' . $update['status'], 'support_grant', $grantId, [], $companyId);

        return Result::success();
    }

    /** @return list<array<string, mixed>> */
    public function forCompany(int $companyId): array
    {
        return db_connect()->table('support_grants g')
            ->select('g.*, u.name AS user_name, u.email AS user_email')
            ->join('users u', 'u.id = g.user_id')
            ->where('g.company_id', $companyId)
            ->orderBy('g.id', 'DESC')->get(50)->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        return db_connect()->table('support_grants g')
            ->select('g.*, c.name AS company_name')
            ->join('companies c', 'c.id = g.company_id')
            ->where('g.user_id', $userId)
            ->orderBy('g.id', 'DESC')->get(50)->getResultArray();
    }
}
