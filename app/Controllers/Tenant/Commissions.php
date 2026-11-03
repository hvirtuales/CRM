<?php

namespace App\Controllers\Tenant;

use App\Controllers\BaseController;
use Config\Services;

class Commissions extends BaseController
{
    public function index()
    {
        $tid = (int) Services::context()->tenantId();
        $b   = db_connect()->table('commissions m')
            ->select('m.*, c.name AS company_name, p.paid_at AS payment_date')
            ->join('companies c', 'c.id = m.company_id', 'left')->join('payments p', 'p.id = m.payment_id')
            ->where('m.tenant_id', $tid);
        $status = (string) $this->request->getGet('status');
        if (in_array($status, ['pending', 'approved', 'paid', 'void'], true)) {
            $b->where('m.status', $status);
        }

        return view('tenant/commissions', $this->paginate($b->orderBy('m.id', 'DESC')) + [
            'status' => $status, 'settlements' => (new \App\Libraries\CommissionService())->settlements($tid),
        ]);
    }

    /** Extracto mensual en CSV (solo del propio distribuidor; separador ; y BOM para Excel). */
    public function statement(int $id)
    {
        $tid = (int) Services::context()->tenantId();
        $s   = db_connect()->table('commission_settlements')->where('id', $id)->where('tenant_id', $tid)->get()->getRowArray();
        if (! $s) {
            return redirect()->to(site_url('distribuidor/comisiones'))->with('error', lang('Core.notFound'));
        }
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [lang('Platform.company'), lang('Platform.reference'), lang('Core.date'), lang('Platform.base'), lang('Platform.rate'), lang('Platform.commission'), lang('Core.currency')], ';');
        foreach ((new \App\Libraries\CommissionService())->settlementLines($id, $tid) as $l) {
            $row = [$l['company_name'], $l['payment_reference'], substr((string) $l['payment_date'], 0, 10), $l['base_amount'], $l['rate'], $l['amount'], $l['currency']];
            fputcsv($fh, array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $row), ';');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="extracto-comisiones-' . $s['period'] . '-' . $s['currency'] . '.csv"')->setBody($csv);
    }
}
