<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.commissions') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.commissions'), 'description' => lang('Tenant.commissionsHelp')]) ?>
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
