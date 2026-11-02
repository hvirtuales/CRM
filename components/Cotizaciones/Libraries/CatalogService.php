<?php

namespace Components\Cotizaciones\Libraries;

use Config\Services;

/** Catálogo de servicios del negocio (precios e impuestos parametrizables, no fijados en código). */
class CatalogService
{
    public const BILLING = ['one_time', 'monthly', 'yearly'];

    /** @return list<array<string,mixed>> */
    public function list(int $companyId, bool $onlyActive = false): array
    {
        $b = db_connect()->table('cmp_quote_services')->where('company_id', $companyId)->where('deleted_at', null);
        if ($onlyActive) {
            $b->where('is_active', 1);
        }

        return $b->orderBy('name')->get()->getResultArray();
    }

    public function find(int $companyId, int $id): ?array
    {
        return db_connect()->table('cmp_quote_services')->where('company_id', $companyId)->where('id', $id)->where('deleted_at', null)->get()->getRowArray() ?: null;
    }

    public function save(int $companyId, array $d, ?int $id = null): Result
    {
        if ($id !== null && ! $this->find($companyId, $id)) {
            return Result::notFound();
        }
        $errors = [];
        $name   = trim((string) ($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = lang('Quote.validation.name');
        }
        $price = str_replace(',', '.', trim((string) ($d['unit_price'] ?? '0')));
        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $price)) {
            $errors['unit_price'] = lang('Quote.validation.amount');
        }
        $tax = str_replace(',', '.', trim((string) ($d['tax_percent'] ?? '0')));
        if (! preg_match('/^\d{1,2}(\.\d{1,2})?$|^100$/', $tax)) {
            $errors['tax_percent'] = lang('Quote.validation.tax');
        }
        $currency = strtoupper(trim((string) ($d['currency'] ?? 'COP')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = lang('Quote.validation.currency');
        }
        $sku = trim((string) ($d['sku'] ?? ''));
        if ($sku !== '' && ! preg_match('/^[A-Za-z0-9._\-]{1,40}$/', $sku)) {
            $errors['sku'] = lang('Quote.validation.sku');
        }
        $billing = in_array($d['billing'] ?? '', self::BILLING, true) ? $d['billing'] : 'one_time';
        if ($errors) {
            return Result::fail(lang('Quote.validation.fix'), $errors);
        }

        $row = [
            'name' => $name, 'sku' => $sku ?: null, 'description' => mb_substr(trim((string) ($d['description'] ?? '')), 0, 2000) ?: null,
            'unit_price' => $price, 'currency' => $currency, 'tax_rate' => number_format((float) $tax / 100, 4, '.', ''),
            'billing' => $billing, 'is_active' => ! empty($d['is_active']) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db = db_connect();
        if ($id === null) {
            $db->table('cmp_quote_services')->insert($row + ['company_id' => $companyId, 'created_at' => date('Y-m-d H:i:s')]);
            $id = (int) $db->insertID();
        } else {
            $db->table('cmp_quote_services')->where('id', $id)->where('company_id', $companyId)->update($row);
        }
        Services::audit()->log('quote.service_saved', 'quote_service', $id, [], $companyId);

        return Result::success(['id' => $id], lang('Quote.serviceSaved'));
    }
}
