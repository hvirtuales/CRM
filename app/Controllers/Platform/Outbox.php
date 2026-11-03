<?php

namespace App\Controllers\Platform;

use App\Controllers\BaseController;
use App\Libraries\NotificationService;

/** Bandeja de salida: trazabilidad de correos transaccionales (sin mostrar el cuerpo completo). */
class Outbox extends BaseController
{
    public function index()
    {
        $b = db_connect()->table('outbox o')->select('o.id, o.kind, o.to_email, o.subject, o.status, o.attempts, o.last_error, o.send_after, o.sent_at, o.created_at, c.name AS company_name')
            ->join('companies c', 'c.id = o.company_id', 'left');
        $status = (string) $this->request->getGet('status');
        if (in_array($status, ['queued', 'sent', 'failed'], true)) {
            $b->where('o.status', $status);
        }

        return view('platform/outbox', $this->paginate($b->orderBy('o.id', 'DESC')) + [
            'status' => $status, 'configured' => NotificationService::emailConfigured(),
        ]);
    }

    public function retry(int $id)
    {
        return $this->afterResult((new NotificationService())->retry($id), 'plataforma/correos');
    }
}
