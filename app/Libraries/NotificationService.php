<?php

namespace App\Libraries;

use Config\Services;

/**
 * Correo transaccional por bandeja de salida (outbox).
 *
 * - queue(): idempotente por dedupe_key (un reintento del proceso nunca envía dos veces lo mismo);
 *   renderiza la plantilla en el idioma del destinatario e intenta el envío inmediato.
 * - deliverDue(): procesa la cola con reintentos exponenciales (5, 15, 45 min… máx. 6 intentos).
 * - Sin correo configurado los mensajes quedan en cola (no se pierden) hasta configurarlo.
 * Solo comunicaciones transaccionales (no marketing): no requieren autorización de campañas.
 */
class NotificationService
{
    public const MAX_ATTEMPTS = 6;

    /** Tipos con enlaces de un solo uso: el cuerpo se borra al enviarse (no quedan credenciales en BD). */
    private const REDACT_AFTER_SEND = ['invitation', 'signup_verify'];

    public static function emailConfigured(): bool
    {
        $c = config('Email');

        return (string) $c->fromEmail !== '' && ($c->protocol !== 'smtp' || (string) $c->SMTPHost !== '');
    }

    /**
     * Encola (y si se puede, envía) un correo. Devuelve el id o null si estaba duplicado/ inválido.
     *
     * @param array<string, mixed>|callable(): array<string, mixed> $data variables de la plantilla (se escapan en la
     *        vista); si es callable se evalúa YA en el idioma del destinatario (textos con lang()).
     */
    public function queue(string $dedupeKey, string $kind, string $to, string $locale, string $subjectKey, array $subjectArgs, string $template, array|callable $data, ?int $companyId = null, ?int $tenantId = null, ?int $sendAfter = null): ?int
    {
        $to = strtolower(trim($to));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || ! preg_match('#^emails/[a-z_]+$#', $template)) {
            return null;
        }
        $db = db_connect();
        $key = substr($dedupeKey, 0, 150);
        if ($db->table('outbox')->where('dedupe_key', $key)->countAllResults() > 0) {
            return null;
        }

        [$subject, $body] = $this->render($locale, $subjectKey, $subjectArgs, $template, $data);
        $now = date('Y-m-d H:i:s');

        try {
            $db->table('outbox')->insert([
                'dedupe_key' => $key, 'kind' => substr($kind, 0, 40), 'company_id' => $companyId, 'tenant_id' => $tenantId,
                'to_email' => $to, 'subject' => mb_substr($subject, 0, 255), 'body_html' => $body, 'status' => 'queued',
                'attempts' => 0, 'send_after' => date('Y-m-d H:i:s', $sendAfter ?? time()), 'created_at' => $now,
            ]);
        } catch (\Throwable) {
            return null; // carrera con otro proceso sobre la misma dedupe_key: ya está en cola
        }
        $id = (int) $db->insertID();
        if (($sendAfter ?? 0) <= time()) {
            $this->deliver($id);
        }

        return $id;
    }

    /** Procesa la cola pendiente. @return array{sent:int, failed:int, waiting:int} */
    public function deliverDue(int $limit = 200): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'waiting' => 0];
        $rows  = db_connect()->table('outbox')->select('id')->where('status', 'queued')
            ->where('send_after <=', date('Y-m-d H:i:s'))->orderBy('id')->get($limit)->getResultArray();
        foreach ($rows as $r) {
            $res = $this->deliver((int) $r['id']);
            $stats[$res]++;
        }

        return $stats;
    }

    /** @return 'sent'|'failed'|'waiting' */
    public function deliver(int $id): string
    {
        $db  = db_connect();
        $msg = $db->table('outbox')->where('id', $id)->where('status', 'queued')->get()->getRowArray();
        if (! $msg) {
            return 'waiting';
        }
        if (! self::emailConfigured()) {
            return 'waiting'; // queda en cola hasta que se configure el correo saliente
        }

        $attempts = (int) $msg['attempts'] + 1;
        $error    = null;

        try {
            $cfg  = config('Email');
            $mail = Services::email();
            $mail->initialize(['mailType' => 'html', 'charset' => 'UTF-8']);
            $mail->setFrom($cfg->fromEmail, $cfg->fromName !== '' ? $cfg->fromName : 'HV CRM');
            $mail->setTo($msg['to_email']);
            $mail->setSubject($msg['subject']);
            $mail->setMessage($msg['body_html']);
            $ok = $mail->send(false);
            if (! $ok) {
                $error = mb_substr(strip_tags((string) $mail->printDebugger([])), 0, 250);
            }
        } catch (\Throwable $e) {
            $ok    = false;
            $error = mb_substr($e->getMessage(), 0, 250);
        }

        if ($ok) {
            $update = ['status' => 'sent', 'attempts' => $attempts, 'sent_at' => date('Y-m-d H:i:s'), 'last_error' => null];
            if (in_array($msg['kind'], self::REDACT_AFTER_SEND, true)) {
                $update['body_html'] = '[redactado tras el envío]';
            }
            $db->table('outbox')->where('id', $id)->update($update);

            return 'sent';
        }
        $final = $attempts >= self::MAX_ATTEMPTS;
        $db->table('outbox')->where('id', $id)->update([
            'status' => $final ? 'failed' : 'queued', 'attempts' => $attempts, 'last_error' => $error ?: 'send failed',
            'send_after' => date('Y-m-d H:i:s', time() + 300 * (3 ** ($attempts - 1))),
        ]);
        log_message('warning', 'Correo {id} no enviado (intento {n}): {e}', ['id' => $id, 'n' => $attempts, 'e' => $error]);

        return $final ? 'failed' : 'waiting';
    }

    public function retry(int $id): Result
    {
        $db = db_connect();
        if (! $db->table('outbox')->where('id', $id)->where('status', 'failed')->countAllResults()) {
            return Result::notFound();
        }
        $db->table('outbox')->where('id', $id)->update(['status' => 'queued', 'attempts' => 0, 'send_after' => date('Y-m-d H:i:s')]);
        Services::audit()->log('outbox.retry', 'outbox', $id);

        return Result::success(null, lang('Core.outboxRetried'));
    }

    /** Correos a quienes administran un negocio (correo del negocio + propietarios activos). @return list<string> */
    public function companyRecipients(int $companyId): array
    {
        $db   = db_connect();
        $list = [];
        $c    = $db->table('companies')->select('email')->where('id', $companyId)->get()->getRow();
        if ($c && $c->email) {
            $list[] = strtolower($c->email);
        }
        foreach ($db->table('memberships m')->select('u.email')->join('users u', 'u.id = m.user_id')->join('roles r', 'r.id = m.role_id')
            ->where('m.company_id', $companyId)->where('m.scope', 'company')->where('m.status', 'active')->where("u.status", 'active')
            ->whereIn('r.code', ['business_owner', 'business_admin'])->get()->getResultArray() as $u) {
            $list[] = strtolower($u['email']);
        }

        return array_values(array_unique($list));
    }

    /** Correos del distribuidor (contacto + propietarios/facturación activos). @return list<string> */
    public function tenantRecipients(int $tenantId): array
    {
        $db   = db_connect();
        $list = [];
        $t    = $db->table('tenants')->select('contact_email')->where('id', $tenantId)->get()->getRow();
        if ($t && $t->contact_email) {
            $list[] = strtolower($t->contact_email);
        }
        foreach ($db->table('memberships m')->select('u.email')->join('users u', 'u.id = m.user_id')->join('roles r', 'r.id = m.role_id')
            ->where('m.tenant_id', $tenantId)->where('m.scope', 'tenant')->where('m.status', 'active')->where('u.status', 'active')
            ->whereIn('r.code', ['tenant_owner', 'tenant_billing'])->get()->getResultArray() as $u) {
            $list[] = strtolower($u['email']);
        }

        return array_values(array_unique($list));
    }

    /** @return array{0:string, 1:string} [asunto, html] en el idioma indicado */
    private function render(string $locale, string $subjectKey, array $subjectArgs, string $template, array|callable $data): array
    {
        $language = service('language');
        $previous = $language->getLocale();
        $language->setLocale(in_array($locale, ['es', 'en'], true) ? $locale : 'es');

        try {
            $subject = lang($subjectKey, $subjectArgs);
            $vars    = is_callable($data) ? $data() : $data;
            $body    = view($template, $vars + ['subject' => $subject]);
        } finally {
            $language->setLocale($previous);
        }

        return [$subject, $body];
    }
}
