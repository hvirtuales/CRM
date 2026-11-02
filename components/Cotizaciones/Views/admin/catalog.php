<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Quote.menuCatalog') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $ro = ctx()->isReadOnly(); ?>
<?= view('partials/page_header', ['title' => lang('Quote.menuCatalog'), 'description' => lang('Quote.catalogHelp'),
    'actions' => $ro ? '' : '<a class="btn btn-primary" href="' . site_url('cotizaciones/catalogo?new=1') . '">' . icon('plus') . esc(lang('Quote.newService')) . '</a>']) ?>
<?php if ($services === []): ?>
  <?= view('partials/empty', ['message' => lang('Quote.catalogEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Quote.service') ?></th><th><?= lang('Quote.billing') ?></th><th class="num"><?= lang('Quote.unitPrice') ?></th><th class="num"><?= lang('Quote.taxPercent') ?></th><th><?= lang('Quote.status') ?></th><th class="num"></th></tr></thead>
  <tbody><?php foreach ($services as $s): ?>
    <tr>
      <td><span class="font-medium"><?= esc($s['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($s['sku'] ?? '') ?></span></td>
      <td><?= lang('Quote.billings.' . $s['billing']) ?></td>
      <td class="num"><?= money($s['unit_price'], $s['currency']) ?></td>
      <td class="num"><?= num((float) $s['tax_rate'] * 100, 2) ?> %</td>
      <td><?= status_badge((int) $s['is_active'] === 1 ? 'active' : 'disabled') ?></td>
      <td class="num"><?php if (! $ro): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('cotizaciones/catalogo?edit=' . (int) $s['id']) ?>"><?= icon('pencil', 14) ?><?= lang('Quote.edit') ?></a><?php endif ?></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>
<?php if (! $ro && ($edit || $isNew)): $s = $edit ?? ['name' => '', 'sku' => '', 'description' => '', 'unit_price' => '0', 'currency' => $currency, 'tax_rate' => '0.19', 'billing' => 'monthly', 'is_active' => 1]; ?>
<?= view('partials/drawer_start', ['id' => 'dlg-service', 'title' => $edit ? lang('Quote.editService') : lang('Quote.newService'), 'open' => true, 'closeTo' => site_url('cotizaciones/catalogo')]) ?>
<form method="post" action="<?= site_url($edit ? 'cotizaciones/catalogo/' . (int) $edit['id'] : 'cotizaciones/catalogo') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Quote.serviceName'), 'value' => $s['name'], 'required' => true, 'placeholder' => lang('Quote.serviceNamePh')]) ?>
    <?= view('partials/field', ['name' => 'sku', 'label' => lang('Quote.sku'), 'value' => $s['sku'], 'placeholder' => 'VD-PRO-M']) ?>
    <?= view('partials/field', ['name' => 'description', 'label' => lang('Quote.description'), 'type' => 'textarea', 'value' => $s['description']]) ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <?= view('partials/field', ['name' => 'unit_price', 'label' => lang('Quote.unitPrice'), 'type' => 'number', 'value' => $s['unit_price'], 'required' => true, 'attrs' => ['step' => '0.01', 'min' => '0']]) ?>
      <?= view('partials/field', ['name' => 'currency', 'label' => lang('Quote.currency'), 'value' => $s['currency'], 'required' => true, 'attrs' => ['maxlength' => 3]]) ?>
      <?= view('partials/field', ['name' => 'tax_percent', 'label' => lang('Quote.taxPercent'), 'type' => 'number', 'value' => round((float) $s['tax_rate'] * 100, 2), 'hint' => lang('Quote.taxHint'), 'attrs' => ['step' => '0.01', 'min' => '0', 'max' => '100']]) ?>
      <?= view('partials/field', ['name' => 'billing', 'label' => lang('Quote.billing'), 'type' => 'select', 'value' => $s['billing'], 'options' => ['one_time' => lang('Quote.billings.one_time'), 'monthly' => lang('Quote.billings.monthly'), 'yearly' => lang('Quote.billings.yearly')]]) ?>
    </div>
    <?= view('partials/toggle', ['name' => 'is_active', 'label' => lang('Quote.serviceActive'), 'description' => lang('Quote.serviceActiveHint'), 'checked' => (int) $s['is_active'] === 1]) ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Quote.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Quote.saveService') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
