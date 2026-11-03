<?php

namespace Components\Formularios\Libraries;

use Config\Services;

/**
 * Recepción pública de respuestas (sin sesión). Defensa en capas:
 *  1. Token firmado HMAC (formulario + emisión + nonce) con edad mínima y vencimiento.
 *  2. Honeypot (campo oculto que un humano no llena).
 *  3. Límite por IP y formulario.
 *  4. Validación estricta contra la VERSIÓN PUBLICADA (no el borrador).
 *  5. Idempotencia por nonce: un doble clic o reintento no duplica la solicitud.
 * El flujo crea contacto/oportunidad mediante el servicio público del CRM, dentro del negocio dueño.
 */
class SubmissionService
{
    public const HONEYPOT = 'website_url';

    public function issueToken(string $publicId, ?int $now = null): string
    {
        $payload = ($now ?? time()) . '.' . bin2hex(random_bytes(12));

        return $payload . '.' . $this->sign($publicId, $payload);
    }

    /**
     * @return array{0:bool,1:string|null} [válido, nonce]
     */
    public function verifyToken(string $publicId, string $token, ?int $now = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_xdigit($parts[1])) {
            return [false, null];
        }
        [$ts, $nonce, $sig] = $parts;
        if (! hash_equals($this->sign($publicId, $ts . '.' . $nonce), $sig)) {
            return [false, null];
        }
        $age = ($now ?? time()) - (int) $ts;
        $cfg = $this->settings();

        return [$age >= $cfg['min_seconds'] && $age <= $cfg['token_ttl'], $nonce];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $utm
     */
    public function submit(array $form, array $input, string $ip, array $utm = []): Result
    {
        $publicId = (string) $form['public_id'];
        $cfg      = $this->settings();

        if (! empty($input[self::HONEYPOT])) {
            // Se responde como éxito para no dar señal al bot, pero no se guarda nada.
            return Result::success(['spam' => true], lang('Form.thanks'));
        }
        if (! Services::throttler()->check('form-' . md5($publicId . '|' . $ip), $cfg['per_ip_per_minute'], MINUTE)) {
            return Result::fail(lang('Form.tooMany'), [], 429);
        }
        [$valid, $nonce] = $this->verifyToken($publicId, (string) ($input['_hv_token'] ?? ''));
        if (! $valid) {
            return Result::fail(lang('Form.tokenInvalid'), [], 400);
        }

        $companyId = (int) $form['company_id'];
        $guard     = new PlanGuard();
        if (! $guard->serviceEnabled($companyId)) {
            return Result::fail(lang('Form.unavailable'), [], 403);
        }

        $fields = json_decode((string) $form['published_fields'], true) ?: [];
        $clean  = [];
        $errors = [];
        foreach ($fields as $f) {
            $raw   = $input['f'][$f['key']] ?? null;
            $value = is_string($raw) ? trim($raw) : ($f['type'] === 'checkbox' ? ($raw ? '1' : '') : '');
            if ($f['required'] && $value === '') {
                $errors[$f['key']] = lang('Form.validation.required');

                continue;
            }
            if ($value === '') {
                $clean[$f['key']] = '';

                continue;
            }
            $ok = match ($f['type']) {
                'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) !== false && strlen($value) <= 190,
                'phone'    => preg_match('/^[0-9+()\s\-]{7,40}$/', $value) === 1,
                'number'   => preg_match('/^-?\d{1,13}([.,]\d{1,4})?$/', $value) === 1,
                'date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false,
                'select'   => in_array($value, $f['options'], true),
                'checkbox' => $value === '1',
                'textarea' => mb_strlen($value) <= 5000,
                default    => mb_strlen($value) <= 255,
            };
            if (! $ok) {
                $errors[$f['key']] = lang('Form.validation.invalid.' . $f['type']);

                continue;
            }
            $clean[$f['key']] = $value;
        }
        $consent = ! empty($input['consent']);
        if (! empty($form['consent_text']) && ! $consent) {
            $errors['consent'] = lang('Form.validation.consent');
        }
        if ($errors) {
            return Result::fail(lang('Form.validation.fix'), $errors);
        }

        $db = db_connect();
        $idem = hash('sha256', (string) $nonce);
        if ($db->table('cmp_form_submissions')->where('form_id', $form['id'])->where('idempotency_key', $idem)->countAllResults()) {
            return Result::success(['duplicate' => true], $form['success_message'] ?: lang('Form.thanks'));
        }

        // Mapeo a contacto
        $contact = [];
        $notes   = [];
        foreach ($fields as $f) {
            $v = $clean[$f['key']] ?? '';
            if ($v === '') {
                continue;
            }
            if ($f['maps_to'] !== 'none') {
                $contact[$f['maps_to']] = $v;
            } elseif ($f['type'] !== 'checkbox') {
                $notes[] = $f['label'] . ': ' . $v;
            }
        }
        if ($notes) {
            $contact['notes'] = trim(($contact['notes'] ?? '') . "\n" . implode("\n", $notes));
        }

        $lead = service('crmLeads')->capture($companyId, $contact, [
            'consent' => $consent, 'consent_source' => 'form:' . $publicId . ':v' . (int) $form['version'],
            'project_id' => $form['project_id'] ? (int) $form['project_id'] : null,
            'create_opportunity' => (int) $form['create_opportunity'] === 1,
            'title' => $form['name'] . ' — ' . trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
        ], 'form');
        if (! $lead->ok) {
            return Result::fail($lead->message ?? lang('Form.unavailable'), $lead->errors, $lead->status);
        }

        $db->table('cmp_form_submissions')->insert([
            'company_id' => $companyId, 'form_id' => $form['id'], 'form_version' => (int) $form['version'],
            'idempotency_key' => $idem, 'data' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'contact_id' => $lead->data['contact_id'], 'opportunity_id' => $lead->data['opportunity_id'],
            'consent' => $consent ? 1 : 0, 'utm' => $utm ? json_encode($utm) : null,
            'ip_hash' => hash_hmac('sha256', $ip, $this->key('hv-ip-hash')), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('cmp_form_forms')->where('id', $form['id'])->set('submissions_count', 'submissions_count + 1', false)->update();
        $this->acknowledge($form, $contact);

        return Result::success($lead->data, $form['success_message'] ?: lang('Form.thanks'));
    }

    /**
     * Acuse de recibo inmediato al lead (transaccional: no requiere autorización de marketing).
     * Anti-abuso: como máximo UN acuse por formulario, destinatario y día (dedupe_key), además del
     * límite por IP del formulario; el texto no repite datos enviados por el visitante salvo su nombre.
     *
     * @param array<string, string> $contact datos mapeados (first_name, email…)
     */
    private function acknowledge(array $form, array $contact): void
    {
        $email = strtolower(trim((string) ($contact['email'] ?? '')));
        if ((int) ($form['send_ack'] ?? 1) !== 1 || $email === '' || ! class_exists(\App\Libraries\NotificationService::class)) {
            return;
        }
        $name    = mb_substr(trim((string) ($contact['first_name'] ?? '')), 0, 60);
        $company = (string) $form['company_name'];
        (new \App\Libraries\NotificationService())->queue(
            'lead-ack:' . $form['id'] . ':' . hash('sha256', $email) . ':' . date('Y-m-d'),
            'lead_ack', $email, (string) ($form['company_locale'] ?? 'es'),
            'Form.ack.subject', [$company], 'emails/message',
            static fn () => [
                'greeting' => $name !== '' ? lang('Form.ack.greeting', [$name]) : lang('Form.ack.greetingAnon'),
                'lines'    => [lang('Form.ack.received', [$company]), lang('Form.ack.next')],
                'note'     => lang('Form.ack.notYou'),
                'sender'   => $company,
            ],
            (int) $form['company_id']
        );
    }

    private function sign(string $publicId, string $payload): string
    {
        return hash_hmac('sha256', 'form|' . $publicId . '|' . $payload, $this->key('hv-form-token'));
    }

    /** Subclave derivada por propósito (HKDF): la clave de cifrado nunca se usa directamente como HMAC. */
    private function key(string $purpose): string
    {
        $key = (string) config('Encryption')->key;
        if ($key === '') {
            throw new \RuntimeException('encryption.key no configurada');
        }

        return hash_hkdf('sha256', $key, 32, $purpose);
    }

    /** @return array{min_seconds:int, token_ttl:int, per_ip_per_minute:int} */
    private function settings(): array
    {
        $m = require dirname(__DIR__) . '/Manifest.php';

        return array_map('intval', $m['settings']);
    }
}
