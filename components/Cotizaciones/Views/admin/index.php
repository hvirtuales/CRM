<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Quote.menu') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$canCreate = auth_can('quote.create') && ($quota === null || $quota > 0); $canEdit = auth_can('quote.edit') && ! ctx()->isReadOnly();
$errors = session('errors') ?? [];
$oppOptions = ['' => lang('Quote.chooseOpportunity')] + $opportunities;
$editing = ($open && $open['status'] === 'draft' && $canEdit) ? $open : null;
?>
<?= view('partials/page_header', ['title' => lang('Quote.menu'), 'description' => lang('Quote.help'),
    'actions' => $canCreate ? '<a class="btn btn-primary" href="' . site_url('cotizaciones?new=1') . '">' . icon('plus') . esc(lang('Quote.new')) . '</a>' : '']) ?>
<?php if ($quota !== null): ?><div class="notice mb-3"><?= lang('Quote.quotaLeft', [$quota]) ?></div><?php endif ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="<?= esc(lang('Quote.searchPh'), 'attr') ?>" aria-label="<?= esc(lang('Quote.search'), 'attr') ?>"></div>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Quote.status'), 'attr') ?>"><option value=""><?= lang('Quote.all') ?></option>
    <?php foreach (array_keys($transitions) as $s): ?><option value="<?= $s ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= lang('Quote.statuses.' . $s) ?></option><?php endforeach ?></select>
</form>
<?php if ($quotes === []): ?>
  <?= view('partials/empty', ['message' => lang('Quote.empty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Quote.number') ?></th><th><?= lang('Quote.client') ?></th><th><?= lang('Quote.validUntil') ?></th><th class="num"><?= lang('Quote.total') ?></th><th><?= lang('Quote.status') ?></th></tr></thead>
  <tbody><?php foreach ($quotes as $q): ?>
    <tr>
      <td><a class="font-medium hover:text-accent" href="<?= site_url('cotizaciones?open=' . (int) $q['id']) ?>"><?= esc($q['number']) ?></a><span class="block text-xs text-ink-soft"><?= esc($q['opportunity_title'] ?? '') ?></span></td>
      <td><?= esc($q['contact_name'] ?? '—') ?><span class="block text-xs text-ink-soft"><?= esc($q['contact_org'] ?? '') ?></span></td>
      <td><?= dt($q['valid_until'], false) ?></td>
      <td class="num font-semibold"><?= money($q['total'], $q['currency']) ?></td>
      <td><?= status_badge($q['status'], lang('Quote.statuses.' . $q['status'])) ?></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>

<?php if (($isNew && $canCreate) || $editing):
    $lines = $editing ? $lines : [];
    $old   = old('lines');
    if (is_array($old)) { $lines = $old; }
?>
<?= view('partials/drawer_start', ['id' => 'dlg-quote', 'title' => $editing ? lang('Quote.editTitle', [$editing['number']]) : lang('Quote.new'), 'wide' => true, 'open' => true, 'closeTo' => site_url('cotizaciones')]) ?>
<form method="post" action="<?= site_url($editing ? 'cotizaciones/' . (int) $editing['id'] : 'cotizaciones') ?>" class="flex min-h-0 flex-1 flex-col" data-quote-editor data-catalog="<?= esc(json_encode(array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'price' => $s['unit_price'], 'tax' => round((float) $s['tax_rate'] * 100, 2)], $catalog)), 'attr') ?>">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Quote.sectionClient') ?></legend>
      <p class="form-section-hint"><?= lang('Quote.sectionClientHint') ?></p>
      <div class="grid gap-4 sm:grid-cols-3">
        <?= view('partials/field', ['name' => 'opportunity_id', 'label' => lang('Quote.opportunity'), 'type' => 'select', 'value' => $editing['opportunity_id'] ?? ($newFor ?: ''), 'options' => $oppOptions, 'required' => true, 'class' => 'sm:col-span-3']) ?>
        <?= view('partials/field', ['name' => 'currency', 'label' => lang('Quote.currency'), 'value' => $editing['currency'] ?? $currency, 'required' => true, 'attrs' => ['maxlength' => 3]]) ?>
        <?= view('partials/field', ['name' => 'valid_until', 'label' => lang('Quote.validUntil'), 'type' => 'date', 'value' => $editing['valid_until'] ?? '', 'hint' => lang('Quote.validUntilHint')]) ?>
      </div>
    </fieldset>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Quote.sectionLines') ?></legend>
      <p class="form-section-hint"><?= lang('Quote.sectionLinesHint') ?></p>
      <?= field_error('lines') ?>
      <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-ink-soft"><tr><th class="py-1 pr-2"><?= lang('Quote.service') ?></th><th class="py-1 pr-2"><?= lang('Quote.description') ?></th><th class="w-20 py-1 pr-2"><?= lang('Quote.qty') ?></th><th class="w-32 py-1 pr-2"><?= lang('Quote.unitPrice') ?></th><th class="w-20 py-1 pr-2"><?= lang('Quote.taxPercent') ?></th><th class="w-10"></th></tr></thead>
        <tbody data-lines>
        <?php foreach (array_pad(array_values($lines), max(1, count($lines)), []) as $i => $l): ?>
          <tr data-line>
            <td class="py-1 pr-2"><select class="select" name="lines[<?= $i ?>][service_id]" data-l="service" aria-label="<?= esc(lang('Quote.service'), 'attr') ?>"><option value=""><?= lang('Quote.freeLine') ?></option>
              <?php foreach ($catalog as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (string) ($l['service_id'] ?? '') === (string) $s['id'] ? ' selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?></select></td>
            <td class="py-1 pr-2"><input class="input" name="lines[<?= $i ?>][description]" value="<?= esc((string) ($l['description'] ?? ''), 'attr') ?>" maxlength="255" aria-label="<?= esc(lang('Quote.description'), 'attr') ?>"></td>
            <td class="py-1 pr-2"><input class="input" name="lines[<?= $i ?>][quantity]" value="<?= esc((string) ($l['quantity'] ?? '1'), 'attr') ?>" inputmode="decimal" data-l="qty" aria-label="<?= esc(lang('Quote.qty'), 'attr') ?>"></td>
            <td class="py-1 pr-2"><input class="input" name="lines[<?= $i ?>][unit_price]" value="<?= esc((string) ($l['unit_price'] ?? '0'), 'attr') ?>" inputmode="decimal" data-l="price" aria-label="<?= esc(lang('Quote.unitPrice'), 'attr') ?>"></td>
            <td class="py-1 pr-2"><input class="input" name="lines[<?= $i ?>][tax_percent]" value="<?= esc((string) (isset($l['tax_rate']) ? round((float) $l['tax_rate'] * 100, 2) : ($l['tax_percent'] ?? '0')), 'attr') ?>" inputmode="decimal" data-l="tax" aria-label="<?= esc(lang('Quote.taxPercent'), 'attr') ?>"></td>
            <td class="py-1"><button type="button" class="btn btn-ghost btn-sm" data-remove-line aria-label="<?= esc(lang('Quote.removeLine'), 'attr') ?>"><?= icon('trash-2', 14) ?></button></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <button type="button" class="btn btn-secondary btn-sm" data-add-line><?= icon('plus', 14) ?><?= lang('Quote.addLine') ?></button>
        <p class="text-sm"><?= lang('Quote.previewTotal') ?> <strong data-preview-total>—</strong> <span class="text-xs text-ink-soft"><?= lang('Quote.previewNote') ?></span></p>
      </div>
    </fieldset>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Quote.sectionTerms') ?></legend>
      <?= view('partials/field', ['name' => 'notes', 'label' => lang('Quote.notes'), 'type' => 'textarea', 'value' => $editing['notes'] ?? '', 'placeholder' => lang('Quote.notesPh')]) ?>
    </fieldset>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Quote.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Quote.saveDraft') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php elseif ($open): $publicUrl = site_url('quote-publico/' . $open['public_id']); ?>
<?= view('partials/drawer_start', ['id' => 'dlg-quote', 'title' => $open['number'], 'wide' => true, 'open' => true, 'closeTo' => site_url('cotizaciones')]) ?>
<div class="drawer-body space-y-4">
  <div class="flex flex-wrap items-center gap-2"><?= status_badge($open['status'], lang('Quote.statuses.' . $open['status'])) ?><span class="text-xs text-ink-soft"><?= lang('Quote.validUntil') ?>: <?= dt($open['valid_until'], false) ?></span></div>
  <?php if (in_array($open['status'], ['sent', 'accepted', 'rejected'], true)): ?>
    <div class="notice-accent"><?= icon('external-link', 16, 'mt-0.5 text-accent') ?><div class="min-w-0 flex-1"><p><?= lang('Quote.publicLinkHelp') ?></p><p class="mt-1 truncate font-mono text-xs"><?= esc($publicUrl) ?></p></div>
      <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= esc($publicUrl, 'attr') ?>" data-copied="<?= esc(lang('Quote.copied'), 'attr') ?>"><?= icon('copy', 14) ?><?= lang('Quote.copy') ?></button></div>
  <?php endif ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= lang('Quote.description') ?></th><th class="num"><?= lang('Quote.qty') ?></th><th class="num"><?= lang('Quote.unitPrice') ?></th><th class="num"><?= lang('Quote.taxPercent') ?></th><th class="num"><?= lang('Quote.lineTotal') ?></th></tr></thead>
    <tbody><?php foreach ($lines as $l): ?><tr><td><?= esc($l['description']) ?></td><td class="num"><?= num($l['quantity'], 2) ?></td><td class="num"><?= money($l['unit_price'], $open['currency']) ?></td><td class="num"><?= num((float) $l['tax_rate'] * 100, 2) ?> %</td><td class="num"><?= money($l['line_total'], $open['currency']) ?></td></tr><?php endforeach ?></tbody>
  </table></div>
  <dl class="ml-auto w-full max-w-xs space-y-1 text-sm">
    <div class="flex justify-between"><dt class="text-ink-soft"><?= lang('Quote.subtotal') ?></dt><dd><?= money($open['subtotal'], $open['currency']) ?></dd></div>
    <div class="flex justify-between"><dt class="text-ink-soft"><?= lang('Quote.taxes') ?></dt><dd><?= money($open['tax_total'], $open['currency']) ?></dd></div>
    <div class="flex justify-between text-base font-semibold"><dt><?= lang('Quote.total') ?></dt><dd><?= money($open['total'], $open['currency']) ?></dd></div>
  </dl>
  <?php if ($open['notes']): ?><div class="notice whitespace-pre-line"><?= esc($open['notes']) ?></div><?php endif ?>
  <?php if ($open['opportunity_id']): ?><a class="text-sm text-accent" href="<?= site_url('crm/oportunidades?open=' . (int) $open['opportunity_id']) ?>"><?= lang('Quote.viewOpportunity') ?></a><?php endif ?>
</div>
<?php if ($canEdit): ?>
<div class="drawer-foot flex-wrap">
  <?php if (in_array('void', $transitions[$open['status']], true) && auth_can('quote.delete')): ?>
    <?= view('partials/delete_button', ['action' => site_url('cotizaciones/' . (int) $open['id'] . '/anular'), 'label' => lang('Quote.void'), 'confirm' => lang('Quote.confirmVoid')]) ?>
  <?php endif ?>
  <span class="flex-1"></span>
  <?php foreach ($transitions[$open['status']] as $to): if ($to === 'void') { continue; } ?>
    <?= view('partials/delete_button', ['action' => site_url('cotizaciones/' . (int) $open['id'] . '/estado'), 'label' => lang('Quote.actions.' . $to), 'fields' => ['status' => $to], 'variant' => in_array($to, ['sent', 'accepted'], true) ? 'secondary' : 'ghost', 'confirm' => lang('Quote.confirm.' . $to)]) ?>
  <?php endforeach ?>
</div>
<?php endif ?>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= base_url('components/quote/js/quote.js') ?>"></script><?= $this->endSection() ?>
