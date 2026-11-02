<?php
/**
 * Cotización pública autosuficiente (estilos y SVG en línea, imprimible). Solo lectura.
 *
 * @var array|null $quote
 * @var list<array> $lines
 * @var string|null $brand
 */
$brandHex = is_string($brand) && preg_match('/^#[0-9a-fA-F]{6}$/', $brand) ? $brand : '#D80023';
$cur = $quote['currency'] ?? 'COP';
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= esc($quote ? lang('Quote.publicTitle', [$quote['number']]) : lang('Quote.publicNotFoundTitle')) ?></title>
<style>
  :root{--hq-brand:<?= $brandHex ?>;--hq-bg:#f7f7f8;--hq-surface:#fff;--hq-fg:#18181b;--hq-muted:#6b6b76;--hq-line:#e4e4e7;--hq-font:"Segoe UI Variable","Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif}
  @media (prefers-color-scheme:dark){:root{--hq-bg:#0f0f11;--hq-surface:#18181b;--hq-fg:#f4f4f5;--hq-muted:#a1a1aa;--hq-line:#2e2e33}}
  @media print{:root{--hq-bg:#fff;--hq-surface:#fff;--hq-fg:#000;--hq-muted:#444;--hq-line:#ccc}.hq-noprint{display:none}}
  *{box-sizing:border-box}body{margin:0;background:var(--hq-bg);color:var(--hq-fg);font:14px/1.5 var(--hq-font);font-variant-numeric:tabular-nums}
  .hq-wrap{max-width:820px;margin:0 auto;padding:24px 16px}.hq-card{background:var(--hq-surface);border:1px solid var(--hq-line);border-radius:8px;padding:24px}
  .hq-bar{height:4px;background:var(--hq-brand);border-radius:8px 8px 0 0;margin:-24px -24px 20px}
  .hq-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:16px}.hq-h1{margin:0;font-size:1.25rem}.hq-muted{color:var(--hq-muted);font-size:.875rem;margin:2px 0}
  table{width:100%;border-collapse:collapse;margin:20px 0}th,td{padding:8px;border-bottom:1px solid var(--hq-line);text-align:left}th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--hq-muted)}.r{text-align:right}
  .hq-tot{margin-left:auto;max-width:280px}.hq-tot div{display:flex;justify-content:space-between;padding:2px 0}.hq-big{font-size:1.1rem;font-weight:600}
  .hq-badge{display:inline-block;border-radius:2px;padding:2px 8px;font-size:12px;font-weight:600;background:var(--hq-line)}
  .hq-btn{display:inline-flex;gap:6px;align-items:center;border:1px solid var(--hq-line);background:var(--hq-surface);color:var(--hq-fg);border-radius:6px;padding:8px 14px;font:inherit;cursor:pointer}
  .hq-ico{width:16px;height:16px}
</style>
</head>
<body><main class="hq-wrap"><div class="hq-card"><div class="hq-bar"></div>
<?php if (! $quote): ?>
  <h1 class="hq-h1"><?= lang('Quote.publicNotFoundTitle') ?></h1><p class="hq-muted"><?= lang('Quote.publicNotFound') ?></p>
<?php else: ?>
  <div class="hq-head">
    <div><h1 class="hq-h1"><?= esc($quote['company_name']) ?></h1>
      <?php if ($quote['company_tax_id']): ?><p class="hq-muted"><?= lang('Quote.taxIdShort') ?> <?= esc($quote['company_tax_id']) ?></p><?php endif ?>
      <p class="hq-muted"><?= esc(trim(($quote['company_email'] ?? '') . ' ' . ($quote['company_phone'] ?? ''))) ?></p></div>
    <div style="text-align:right"><p class="hq-h1"><?= lang('Quote.quote') ?> <?= esc($quote['number']) ?></p>
      <p class="hq-muted"><?= lang('Quote.validUntil') ?>: <?= esc((string) $quote['valid_until']) ?></p>
      <span class="hq-badge"><?= lang('Quote.statuses.' . $quote['status']) ?></span></div>
  </div>
  <p style="margin-top:16px"><strong><?= lang('Quote.client') ?>:</strong> <?= esc(trim(($quote['contact_name'] ?? '') . ($quote['contact_org'] ? ' · ' . $quote['contact_org'] : ''))) ?></p>
  <table><thead><tr><th><?= lang('Quote.description') ?></th><th class="r"><?= lang('Quote.qty') ?></th><th class="r"><?= lang('Quote.unitPrice') ?></th><th class="r"><?= lang('Quote.taxPercent') ?></th><th class="r"><?= lang('Quote.lineTotal') ?></th></tr></thead>
  <tbody><?php foreach ($lines as $l): ?><tr><td><?= esc($l['description']) ?></td><td class="r"><?= num($l['quantity'], 2) ?></td><td class="r"><?= money($l['unit_price'], $cur) ?></td><td class="r"><?= num((float) $l['tax_rate'] * 100, 2) ?> %</td><td class="r"><?= money($l['line_total'], $cur) ?></td></tr><?php endforeach ?></tbody></table>
  <div class="hq-tot"><div><span><?= lang('Quote.subtotal') ?></span><span><?= money($quote['subtotal'], $cur) ?></span></div><div><span><?= lang('Quote.taxes') ?></span><span><?= money($quote['tax_total'], $cur) ?></span></div><div class="hq-big"><span><?= lang('Quote.total') ?></span><span><?= money($quote['total'], $cur) ?></span></div></div>
  <?php if ($quote['notes']): ?><p style="white-space:pre-line;margin-top:20px" class="hq-muted"><?= esc($quote['notes']) ?></p><?php endif ?>
  <p class="hq-muted" style="margin-top:20px"><?= lang('Quote.publicFooter') ?></p>
<?php endif ?>
</div></main></body></html>
