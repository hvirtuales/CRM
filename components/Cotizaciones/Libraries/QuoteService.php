<?php

namespace Components\Cotizaciones\Libraries;

use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Services;

/**
 * Cotizaciones. Los totales se calculan SIEMPRE aquí, en centavos enteros (sin errores de punto
 * flotante); el navegador solo muestra una vista previa. Estados: draft → sent → accepted|rejected; void.
 */
class QuoteService
{
    public const TRANSITIONS = [
        'draft'    => ['sent', 'void'],
        'sent'     => ['accepted', 'rejected', 'void', 'draft'],
        'accepted' => [],
        'rejected' => ['draft'],
        'void'     => [],
    ];
    public const MAX_LINES = 50;

    /**
     * @param array<string,mixed> $filters status, q
     *
     * @return list<array<string,mixed>>
     */
    public function list(int $companyId, array $filters = []): array
    {
        // Sin SQL contra tablas del CRM: los nombres se resuelven con sus servicios públicos.
        $b = db_connect()->table('cmp_quote_quotes q')->where('q.company_id', $companyId)->where('q.deleted_at', null);
        if (isset(self::TRANSITIONS[$filters['status'] ?? ''])) {
            $b->where('q.status', $filters['status']);
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $b->like('q.number', $q);
        }
        $rows     = $b->orderBy('q.id', 'DESC')->get(300)->getResultArray();
        $contacts = service('crmContacts')->summaries($companyId, array_column($rows, 'contact_id'));
        $titles   = service('crmOpportunities')->titles($companyId, array_column($rows, 'opportunity_id'));
        foreach ($rows as &$r) {
            $r['contact_name']      = $contacts[(int) $r['contact_id']]['name'] ?? null;
            $r['contact_org']       = $contacts[(int) $r['contact_id']]['organization'] ?? null;
            $r['opportunity_title'] = $titles[(int) $r['opportunity_id']] ?? null;
        }

        return $rows;
    }

    public function find(int $companyId, int $id): ?array
    {
        return db_connect()->table('cmp_quote_quotes')->where('company_id', $companyId)->where('id', $id)->where('deleted_at', null)->get()->getRowArray() ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function lines(int $companyId, int $quoteId): array
    {
        return db_connect()->table('cmp_quote_lines')->where('company_id', $companyId)->where('quote_id', $quoteId)->orderBy('position')->get()->getResultArray();
    }

    /** Vista pública: solo cotizaciones enviadas o decididas, por identificador opaco. */
    public function findPublic(string $publicId): ?array
    {
        if (! preg_match('/^[0-9a-z]{20,32}$/', $publicId)) {
            return null;
        }
        $row = db_connect()->table('cmp_quote_quotes q')
            ->select('q.*, co.name AS company_name, co.tax_id AS company_tax_id, co.email AS company_email, co.phone AS company_phone, co.tenant_id')
            ->join('companies co', 'co.id = q.company_id')
            ->where('q.public_id', $publicId)->whereIn('q.status', ['sent', 'accepted', 'rejected'])->where('q.deleted_at', null)
            ->get()->getRowArray();
        if (! $row) {
            return null;
        }
        $c = service('crmContacts')->summaries((int) $row['company_id'], [(int) $row['contact_id']])[(int) $row['contact_id']] ?? null;

        return $row + ['contact_name' => $c['name'] ?? null, 'contact_org' => $c['organization'] ?? null, 'contact_email' => $c['email'] ?? null];
    }

    public function save(int $companyId, array $d, int $actorId, ?int $id = null): Result
    {
        $guard = new PlanGuard();
        if (! $guard->serviceEnabled($companyId)) {
            return Result::fail(lang('Quote.serviceDisabled'), [], 403);
        }
        if ($id === null) {
            $left = $guard->quotesLeft($companyId);
            if ($left !== null && $left <= 0) {
                return Result::fail(lang('Quote.quotaExhausted'), [], 409);
            }
        } else {
            $current = $this->find($companyId, $id);
            if (! $current) {
                return Result::notFound();
            }
            if ($current['status'] !== 'draft') {
                return Result::fail(lang('Quote.onlyDraftEditable'), [], 409);
            }
        }

        $errors = [];
        // Oportunidad y contacto: deben ser del mismo negocio (vía servicios públicos del CRM).
        $oppId = (string) ($d['opportunity_id'] ?? '');
        $contactId = null;
        $opportunityId = null;
        if ($oppId !== '' && $oppId !== '0') {
            $opp = ctype_digit($oppId) ? service('crmOpportunities')->find($companyId, (int) $oppId) : null;
            if (! $opp) {
                $errors['opportunity_id'] = lang('Quote.validation.opportunity');
            } else {
                $opportunityId = (int) $opp['id'];
                $contactId     = (int) $opp['contact_id'];
            }
        } else {
            $errors['opportunity_id'] = lang('Quote.validation.opportunity');
        }
        $currency = strtoupper(trim((string) ($d['currency'] ?? 'COP')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = lang('Quote.validation.currency');
        }
        $valid = trim((string) ($d['valid_until'] ?? ''));
        if ($valid !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid) || strtotime($valid) === false)) {
            $errors['valid_until'] = lang('Quote.validation.date');
        }
        [$lines, $totals] = $this->computeLines($companyId, (array) ($d['lines'] ?? []), $errors);
        if ($lines === [] && ! isset($errors['lines'])) {
            $errors['lines'] = lang('Quote.validation.noLines');
        }
        if ($errors) {
            return Result::fail(lang('Quote.validation.fix'), $errors);
        }

        $cfg = (require dirname(__DIR__) . '/Manifest.php')['settings'];
        $row = [
            'opportunity_id' => $opportunityId, 'contact_id' => $contactId, 'currency' => $currency,
            'subtotal' => $totals['subtotal'], 'tax_total' => $totals['tax'], 'total' => $totals['total'],
            'valid_until' => $valid ?: date('Y-m-d', strtotime('+' . (int) $cfg['valid_days'] . ' days')),
            'notes' => mb_substr(trim((string) ($d['notes'] ?? '')), 0, 5000) ?: null, 'updated_at' => date('Y-m-d H:i:s'),
        ];

        $db = db_connect();
        if ($id === null) {
            $id = $this->insertWithNumber($companyId, $row + ['created_by' => $actorId, 'status' => 'draft', 'created_at' => date('Y-m-d H:i:s')], (string) $cfg['prefix']);
            if ($id === null) {
                return Result::fail(lang('Quote.numberConflict'), [], 409);
            }
        } else {
            $db->table('cmp_quote_quotes')->where('id', $id)->where('company_id', $companyId)->update($row);
        }

        $db->transStart();
        $db->table('cmp_quote_lines')->where('quote_id', $id)->where('company_id', $companyId)->delete();
        foreach ($lines as $i => $l) {
            $db->table('cmp_quote_lines')->insert($l + ['company_id' => $companyId, 'quote_id' => $id, 'position' => $i]);
        }
        $db->transComplete();
        Services::audit()->log('quote.saved', 'quote', $id, ['total' => $totals['total']], $companyId);

        return Result::success(['id' => $id], lang('Quote.saved'));
    }

    public function transition(int $companyId, int $id, string $to, int $actorId): Result
    {
        $q = $this->find($companyId, $id);
        if (! $q) {
            return Result::notFound();
        }
        if (! in_array($to, self::TRANSITIONS[$q['status']] ?? [], true)) {
            return Result::fail(lang('Quote.transitionInvalid'), [], 409);
        }
        $data = ['status' => $to, 'updated_at' => date('Y-m-d H:i:s')];
        if ($to === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($to, ['accepted', 'rejected'], true)) {
            $data['decided_at'] = date('Y-m-d H:i:s');
        }
        db_connect()->table('cmp_quote_quotes')->where('id', $id)->where('company_id', $companyId)->update($data);

        if ($q['opportunity_id'] && in_array($to, ['sent', 'accepted', 'rejected'], true)) {
            service('crmOpportunities')->log($companyId, (int) $q['opportunity_id'], $q['contact_id'] ? (int) $q['contact_id'] : null, $actorId, 'system',
                lang('Quote.activity.' . $to, [$q['number'], money($q['total'], $q['currency'])]));
        }
        Services::audit()->log('quote.' . $to, 'quote', $id, [], $companyId);

        return Result::success(null, lang('Quote.moved.' . $to));
    }

    /**
     * Normaliza y calcula líneas en centavos. Precios del catálogo se toman del servidor si se
     * indica un servicio (el cliente no puede rebajar el precio del catálogo alterando el POST
     * salvo que tenga permiso de editar precios: aquí se respeta el precio enviado solo para
     * líneas libres sin servicio).
     *
     * @param array<string,string> $errors
     *
     * @return array{0: list<array<string,mixed>>, 1: array{subtotal:string,tax:string,total:string}}
     */
    public function computeLines(int $companyId, array $raw, array &$errors): array
    {
        $catalog = array_column((new CatalogService())->list($companyId), null, 'id');
        $out = [];
        $sub = 0;
        $tax = 0;
        foreach (array_slice(array_values($raw), 0, self::MAX_LINES + 1) as $i => $l) {
            if (! is_array($l)) {
                continue;
            }
            $desc = trim((string) ($l['description'] ?? ''));
            $sid  = (string) ($l['service_id'] ?? '');
            $qty  = str_replace(',', '.', trim((string) ($l['quantity'] ?? '1')));
            $price = str_replace(',', '.', trim((string) ($l['unit_price'] ?? '0')));
            $taxP = str_replace(',', '.', trim((string) ($l['tax_percent'] ?? '0')));
            if ($desc === '' && $sid === '') {
                continue; // fila vacía
            }
            if ($i >= self::MAX_LINES) {
                $errors['lines'] = lang('Quote.validation.tooManyLines', [self::MAX_LINES]);
                break;
            }
            $service = null;
            if ($sid !== '' && $sid !== '0') {
                $service = ctype_digit($sid) ? ($catalog[(int) $sid] ?? null) : null;
                if (! $service) {
                    $errors['lines'] = lang('Quote.validation.lineService', [$i + 1]);
                    break;
                }
                $price = (string) $service['unit_price'];
                $taxP  = (string) round((float) $service['tax_rate'] * 100, 2);
                $desc  = $desc !== '' ? $desc : $service['name'];
            }
            if (mb_strlen($desc) > 255 || ! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $qty) || (float) $qty <= 0
                || ! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $price) || ! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $taxP) || (float) $taxP > 100) {
                $errors['lines'] = lang('Quote.validation.line', [$i + 1]);
                break;
            }
            $qtyH       = (int) round((float) $qty * 100);
            $priceC     = (int) round((float) $price * 100);
            $lineC      = intdiv($qtyH * $priceC + 50, 100);
            $taxRate    = round((float) $taxP / 100, 4);
            $taxC       = (int) round($lineC * $taxRate);
            $sub       += $lineC;
            $tax       += $taxC;
            $out[] = [
                'service_id' => $service ? (int) $service['id'] : null, 'description' => $desc,
                'quantity' => number_format($qtyH / 100, 2, '.', ''), 'unit_price' => number_format($priceC / 100, 2, '.', ''),
                'tax_rate' => number_format($taxRate, 4, '.', ''), 'line_total' => number_format($lineC / 100, 2, '.', ''),
            ];
        }
        $fmt = static fn (int $c) => number_format($c / 100, 2, '.', '');

        return [$out, ['subtotal' => $fmt($sub), 'tax' => $fmt($tax), 'total' => $fmt($sub + $tax)]];
    }

    /** Numeración consecutiva por negocio y año; reintenta ante colisión concurrente (índice único). */
    private function insertWithNumber(int $companyId, array $row, string $prefix): ?int
    {
        $db   = db_connect();
        $year = date('Y');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $last = $db->table('cmp_quote_quotes')->select('number')->where('company_id', $companyId)
                ->like('number', $prefix . '-' . $year . '-', 'after')->orderBy('number', 'DESC')->get(1)->getRow();
            $seq  = $last ? ((int) substr((string) $last->number, -5)) + 1 + $attempt : 1 + $attempt;
            $number = sprintf('%s-%s-%05d', $prefix, $year, $seq);

            try {
                $ok = $db->table('cmp_quote_quotes')->insert($row + ['company_id' => $companyId, 'number' => $number, 'public_id' => $this->publicId()]);
                if ($ok) {
                    return (int) $db->insertID();
                }
            } catch (DatabaseException) {
                // Colisión del índice (company_id, number): reintentar con el siguiente consecutivo.
            }
        }

        return null;
    }

    private function publicId(): string
    {
        $alphabet = '0123456789abcdefghjkmnpqrstvwxyz';
        $bytes    = random_bytes(26);
        $id       = '';
        for ($i = 0; $i < 26; $i++) {
            $id .= $alphabet[ord($bytes[$i]) & 31];
        }

        return $id;
    }
}
