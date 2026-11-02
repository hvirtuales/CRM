<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.subscriptions') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.subscriptions'), 'description' => lang('Tenant.subscriptionsHelp')]) ?>
<form method="get" class="mb-3 flex gap-2" data-autosubmit>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Core.statusLabel'), 'attr') ?>"><option value=""><?= lang('Core.all') ?></option>
  <?php foreach (['trial', 'active', 'past_due', 'suspended', 'cancelled'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= lang('Core.status.' . $s) ?></option><?php endforeach ?></select>
</form>
<?php if ($rows === []): ?><?= view('partials/empty', ['message' => lang('Platform.subscriptionsEmpty')]) ?><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Platform.company') ?></th><th><?= lang('Core.plan') ?></th><th class="num"><?= lang('Core.price') ?></th><th><?= lang('Platform.periodEnd') ?></th><th><?= lang('Core.statusLabel') ?></th></tr></thead>
  <tbody><?php foreach ($rows as $s): ?>
    <tr><td class="font-medium"><?= esc($s['company_name']) ?></td><td><?= esc($s['plan_name']) ?></td><td class="num"><?= money($s['price'], $s['currency']) ?></td><td><?= dt($s['current_period_end'], false) ?></td><td><?= status_badge($s['status']) ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>
<?= $this->endSection() ?>
