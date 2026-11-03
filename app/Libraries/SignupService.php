<?php

namespace App\Libraries;

use Config\Commercial;
use Config\Services;

/**
 * Autorregistro «Pruébelo gratis»: un negocio directo nace en PRUEBA solo después de verificar el
 * correo (enlace de un solo uso, 24 h). Reglas anti-abuso:
 *  - Solo planes públicos y activos con días de prueba.
 *  - Honeypot + límite por IP (3/hora) en el controlador.
 *  - Una prueba por correo: un correo que ya verificó un registro no obtiene otra.
 *  - Respuesta idéntica exista o no el correo (sin enumeración).
 * Al verificar: crea el negocio con su plan en prueba, registra el prospecto en el CRM de HV y
 * continúa directamente a definir la contraseña (el correo ya quedó probado).
 */
class SignupService
{
    public const TTL_HOURS = 24;

    public ?string $lastLinkForDevelopment = null;

    /** @return list<array<string,mixed>> */
    public function plans(): array
    {
        return db_connect()->table('plans')->where('target', 'company')->where('is_active', 1)->where('is_public', 1)
            ->where('trial_days >', 0)->orderBy('price')->get()->getResultArray();
    }

    public function start(array $d, string $ip): Result
    {
        $errors  = [];
        $company = trim((string) ($d['company_name'] ?? ''));
        $name    = trim((string) ($d['owner_name'] ?? ''));
        $email   = strtolower(trim((string) ($d['email'] ?? '')));
        $phone   = trim((string) ($d['phone'] ?? ''));
        $planId  = (int) ($d['plan_id'] ?? 0);
        if ($company === '' || mb_strlen($company) > 150) {
            $errors['company_name'] = lang('Core.validation.name');
        }
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['owner_name'] = lang('Core.validation.name');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = lang('Core.validation.email');
        }
        if ($phone !== '' && ! preg_match('/^[0-9+()\s\-]{7,40}$/', $phone)) {
            $errors['phone'] = lang('Core.validation.phone');
        }
        if (! in_array($planId, array_map('intval', array_column($this->plans(), 'id')), true)) {
            $errors['plan_id'] = lang('Core.planInvalid');
        }
        if (empty($d['accept_terms'])) {
            $errors['accept_terms'] = lang('Signup.validation.terms');
        }
        if ($errors) {
            return Result::fail(lang('Core.validation.fix'), $errors);
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        // Anti-inundación del correo de terceros: máx. 3 solicitudes por destinatario en 24 h.
        // Se responde igual (sin revelar nada) pero no se envía más.
        if ($db->table('signups')->where('email', $email)->where('created_at >', date('Y-m-d H:i:s', time() - 86400))->countAllResults() >= 3) {
            return Result::success(['id' => null]);
        }
        // Un registro pendiente previo del mismo correo se reemplaza (evita acumular enlaces vivos).
        $db->table('signups')->where('email', $email)->where('status', 'pending')->update(['status' => 'cancelled']);
        $token = bin2hex(random_bytes(24));
        $db->table('signups')->insert([
            'token_hash' => hash('sha256', $token), 'email' => $email, 'owner_name' => $name, 'company_name' => $company,
            'phone' => $phone ?: null, 'plan_id' => $planId, 'ip_hash' => hash_hmac('sha256', $ip, hash_hkdf('sha256', (string) config('Encryption')->key, 32, 'hv-signup-ip')),
            'status' => 'pending', 'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_HOURS * 3600), 'created_at' => $now,
        ]);
        $id   = (int) $db->insertID();
        $link = site_url('registro/verificar/' . $token);
        if (ENVIRONMENT !== 'production') {
            $this->lastLinkForDevelopment = $link;
        }
        (new NotificationService())->queue('signup:' . $id, 'signup_verify', $email, (string) (service('request')->getLocale() ?: 'es'),
            'Signup.mail.subject', [], 'emails/message', static fn () => [
                'greeting'  => lang('Signup.mail.greeting', [$name]),
                'lines'     => [lang('Signup.mail.body', [$company])],
                'cta_label' => lang('Signup.mail.cta'),
                'cta_url'   => $link,
                'note'      => lang('Signup.mail.note', [self::TTL_HOURS]),
            ]);
        Services::audit()->log('signup.started', 'signup', $id);

        return Result::success(['id' => $id]);
    }

    /** Verifica el correo y aprovisiona. data: owner_token para definir la contraseña. */
    public function verify(string $token): Result
    {
        if (! preg_match('/^[0-9a-f]{48}$/', $token)) {
            return Result::fail(lang('Signup.invalid'), [], 404);
        }
        $db = db_connect();
        $s  = $db->table('signups')->where('token_hash', hash('sha256', $token))->where('status', 'pending')->get()->getRowArray();
        if (! $s || strtotime((string) $s['expires_at']) <= time()) {
            return Result::fail(lang('Signup.invalid'), [], 404);
        }
        // Consumo atómico del enlace.
        $db->table('signups')->where('id', $s['id'])->where('status', 'pending')->update(['status' => 'verified', 'verified_at' => date('Y-m-d H:i:s')]);
        if ($db->affectedRows() !== 1) {
            return Result::fail(lang('Signup.invalid'), [], 409);
        }
        if ($db->table('signups')->where('email', $s['email'])->where('status', 'verified')->where('company_id IS NOT NULL', null, false)->countAllResults() > 0) {
            return Result::fail(lang('Signup.alreadyUsed'), [], 409); // una prueba por correo
        }

        $r = Services::provisioning()->createCompany([
            'name' => $s['company_name'], 'email' => $s['email'], 'phone' => $s['phone'], 'plan_id' => (int) $s['plan_id'],
            'owner_name' => $s['owner_name'], 'owner_email' => $s['email'], 'timezone' => 'America/Bogota', 'currency' => 'COP',
            'locale' => service('request')->getLocale() === 'en' ? 'en' : 'es',
        ], null, null, true);
        if (! $r->ok) {
            return $r;
        }
        $db->table('signups')->where('id', $s['id'])->update(['company_id' => $r->data['id']]);
        // El enlace ya se usó: si su correo seguía en cola, no debe enviarse después.
        $db->table('outbox')->where('dedupe_key', 'signup:' . $s['id'])->where('status', 'queued')->delete();
        $plan = $db->table('plans')->select('name')->where('id', $s['plan_id'])->get()->getRow();
        (new CommercialAutomation())->captureHouseLead($s['company_name'], $s['owner_name'], $s['email'], $s['phone'],
            lang('Signup.leadTitle', [$s['company_name'], (string) ($plan->name ?? '')]));
        Services::audit()->log('signup.verified', 'company', (int) $r->data['id'], ['signup_id' => $s['id']], (int) $r->data['id']);

        return Result::success(['company_id' => $r->data['id'], 'owner_token' => $r->data['owner_token']]);
    }
}
