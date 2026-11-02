<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.subscriptions') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php use App\Libraries\SubscriptionService; $canEdit = auth_can('platform.subscriptions.edit'); $canPay = auth_can('platform.payments.create'); ?>
<?= view('partials/page_header', ['title' => lang('Nav.subscriptions'), 'description' => lang('Platform.subscriptionsHelp')]) ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc(lang('Platform.searchCompanies'), 'attr') ?>" aria-label="<?= esc(lang('Core.search'), 'attr') ?>"></div>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Core.statusLabel'), 'attr') ?>">
    <option value=""><?= lang('Platform.openSubscriptions') ?></option>
    <?php foreach (array_keys(SubscriptionService::TRANSITIONS) as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= lang('Core.status.' . $s) ?></option><?php endforeach ?>
  </select>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.subscriptionsEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Platform.company') ?></th><th><?= lang('Core.plan') ?></th><th><?= lang('Platform.soldBy') ?></th><th class="num"><?= lang('Core.price') ?></th><th><?= lang('Platform.periodEnd') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $s): ?>
    <tr>
      <td class="font-medium"><?= esc($s['company_name'] ?? '—') ?></td>
      <td><?= esc($s['plan_name']) ?></td>
      <td><?= $s['tenant_name'] ? esc($s['tenant_name']) : '<span class="text-ink-soft">' . lang('Platform.direct') . '</span>' ?></td>
      <td class="num"><?= money($s['price'], $s['currency']) ?></td>
      <td><?= dt($s['current_period_end'], false) ?></td>
      <td><?= status_badge($s['status']) ?></td>
      <td class="num"><a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/suscripciones?edit=' . (int) $s['id']) ?>"><?= lang('Core.manage') ?></a></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>

<?php if ($edit): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-sub', 'title' => ($edit['company_name'] ?? '') . ' · ' . $edit['plan_name'], 'wide' => true, 'open' => true, 'closeTo' => site_url('plataforma/suscripciones')]) ?>
<div class="drawer-body space-y-4">
  <dl class="grid gap-3 sm:grid-cols-2">
    <div><dt class="text-xs text-ink-soft"><?= lang('Core.statusLabel') ?></dt><dd><?= status_badge($edit['status']) ?></dd></div>
    <div><dt class="text-xs text-ink-soft"><?= lang('Core.price') ?></dt><dd><?= money($edit['price'], $edit['currency']) ?> · <?= lang('Platform.periods.' . $edit['billing_period']) ?></dd></div>
    <div><dt class="text-xs text-ink-soft"><?= lang('Platform.startedAt') ?></dt><dd><?= dt($edit['started_at']) ?></dd></div>
    <div><dt class="text-xs text-ink-soft"><?= lang('Platform.periodEnd') ?></dt><dd><?= dt($edit['current_period_end']) ?></dd></div>
  </dl>
  <?php if ($canPay && in_array($edit['status'], SubscriptionService::OPEN_STATUSES, true)): ?>
  <form method="post" action="<?= site_url('plataforma/suscripciones/' . (int) $edit['id'] . '/pago') ?>" class="form-section">
    <?= csrf_field() ?>
    <p class="form-section-title"><?= lang('Platform.registerPayment') ?></p>
    <p class="form-section-hint"><?= lang('Platform.registerPaymentHint') ?></p>
    <div class="grid gap-4 sm:grid-cols-3">
      <?= view('partials/field', ['name' => 'amount', 'label' => lang('Core.amount'), 'type' => 'number', 'value' => $edit['price'], 'required' => true, 'attrs' => ['step' => '0.01', 'min' => '0.01']]) ?>
      <?= view('partials/field', ['name' => 'method', 'label' => lang('Platform.method'), 'type' => 'select', 'options' => ['transfer' => lang('Platform.methods.transfer'), 'manual' => lang('Platform.methods.manual'), 'wompi' => 'Wompi', 'paypal' => 'PayPal']]) ?>
      <?= view('partials/field', ['name' => 'reference', 'label' => lang('Platform.reference'), 'required' => true, 'placeholder' => 'TRX-2026-000123', 'hint' => lang('Platform.referenceHint')]) ?>
    </div>
    <div class="flex justify-end"><button class="btn btn-primary" type="submit"><?= lang('Platform.registerPayment') ?></button></div>
  </form>
  <?php endif ?>
  <div>
    <h3 class="mb-2 text-sm font-semibold"><?= lang('Platform.paymentHistory') ?></h3>
    <?php if ($payments === []): ?><p class="text-sm text-ink-soft"><?= lang('Platform.noPayments') ?></p><?php else: ?>
    <ul class="divide-y divide-line rounded-xl border border-line">
      <?php foreach ($payments as $p): ?><li class="flex items-center justify-between px-4 py-2.5"><span><span class="font-medium"><?= esc($p['reference']) ?></span><span class="block text-xs text-ink-soft"><?= lang('Platform.methods.' . $p['method']) ?> · <?= dt($p['paid_at']) ?></span></span><span class="font-semibold"><?= money($p['amount'], $p['currency']) ?></span></li><?php endforeach ?>
    </ul><?php endif ?>
  </div>
</div>
<?php if ($canEdit): ?>
<div class="drawer-foot flex-wrap">
  <?php foreach (SubscriptionService::TRANSITIONS[$edit['status']] ?? [] as $to): ?>
    <?= view('partials/delete_button', ['action' => site_url('plataforma/suscripciones/' . (int) $edit['id'] . '/estado'), 'label' => lang('Platform.moveTo', [lang('Core.status.' . $to)]), 'fields' => ['status' => $to], 'variant' => in_array($to, ['active'], true) ? 'secondary' : 'danger', 'confirm' => lang('Platform.confirmTransition', [lang('Core.status.' . $to)])]) ?>
  <?php endforeach ?>
</div>
<?php endif ?>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
