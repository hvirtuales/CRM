<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.commissions') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.commissions'), 'description' => lang('Tenant.commissionsHelp')]) ?>
<div class="card mb-4">
  <div class="card-header"><h2 class="card-title"><?= lang('Platform.settlements') ?></h2></div>
  <?php if ($settlements === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Tenant.settlementsEmpty') ?></p><?php else: ?>
  <ul class="divide-y divide-line"><?php foreach ($settlements as $s): ?>
    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5">
      <span><span class="font-medium"><?= esc($s['period']) ?></span><span class="block text-xs text-ink-soft"><?= lang('Tenant.settlementLine', [num($s['commissions_count']), $s['paid_at'] ? dt($s['paid_at'], false) : '—']) ?></span></span>
      <span class="flex items-center gap-3"><span class="font-semibold"><?= money($s['total'], $s['currency']) ?></span><?= status_badge($s['status']) ?>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('distribuidor/comisiones/extractos/' . (int) $s['id']) ?>"><?= icon('download', 14) ?><?= lang('Tenant.downloadStatement') ?></a></span>
    </li>
  <?php endforeach ?></ul><?php endif ?>
</div>
<form method="get" class="mb-3 flex gap-2" data-autosubmit>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Core.statusLabel'), 'attr') ?>"><option value=""><?= lang('Core.all') ?></option>
  <?php foreach (['pending', 'approved', 'paid', 'void'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= lang('Core.status.' . $s) ?></option><?php endforeach ?></select>
</form>
<?php if ($rows === []): ?><?= view('partials/empty', ['message' => lang('Tenant.noCommissions')]) ?><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Platform.company') ?></th><th><?= lang('Core.date') ?></th><th class="num"><?= lang('Platform.base') ?></th><th class="num"><?= lang('Platform.rate') ?></th><th class="num"><?= lang('Platform.commission') ?></th><th><?= lang('Core.statusLabel') ?></th></tr></thead>
  <tbody><?php foreach ($rows as $m): ?>
    <tr><td class="font-medium"><?= esc($m['company_name'] ?? '—') ?></td><td><?= dt($m['payment_date'], false) ?></td><td class="num"><?= money($m['base_amount'], $m['currency']) ?></td><td class="num"><?= num((float) $m['rate'] * 100, 2) ?> %</td><td class="num font-semibold"><?= money($m['amount'], $m['currency']) ?></td><td><?= status_badge($m['status']) ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>
<?= $this->endSection() ?>
