<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.dashboard') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Platform.dashboardTitle'), 'description' => lang('Platform.dashboardHelp')]) ?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <div class="kpi"><div class="kpi-label"><?= lang('Platform.kpiTenants') ?></div><div class="kpi-value"><?= num($tenants) ?></div></div>
  <div class="kpi"><div class="kpi-label"><?= lang('Platform.kpiCompanies') ?></div><div class="kpi-value"><?= num($companies) ?></div><div class="kpi-hint"><?= lang('Platform.kpiDirect', [num($direct)]) ?></div></div>
  <div class="kpi"><div class="kpi-label"><?= lang('Platform.kpiMrr') ?></div>
    <?php if ($mrr === []): ?><div class="kpi-value">—</div><?php endif ?>
    <?php foreach ($mrr as $cur => $v): ?><div class="kpi-value"><?= money($v, $cur) ?></div><?php endforeach ?>
    <div class="kpi-hint"><?= lang('Platform.kpiMrrHint') ?></div></div>
  <div class="kpi"><div class="kpi-label"><?= lang('Platform.kpiPastDue') ?></div><div class="kpi-value <?= ($statusCounts['past_due'] ?? 0) > 0 ? 'text-warn' : '' ?>"><?= num($statusCounts['past_due'] ?? 0) ?></div><div class="kpi-hint"><?= lang('Platform.kpiSuspended', [num($statusCounts['suspended'] ?? 0)]) ?></div></div>
</div>
<?php if (! $emailConfigured || $failedMails > 0): ?>
  <a href="<?= site_url('plataforma/correos') ?>" class="mt-4 flex items-center gap-3 rounded-xl border border-line bg-surface px-4 py-3 text-sm hover:bg-muted">
    <?= icon('mail', 16, 'text-warn') ?><span class="flex-1"><?= ! $emailConfigured ? lang('Platform.alertEmailOff', [num($queuedMails)]) : lang('Platform.alertEmailFailed', [num($failedMails)]) ?></span><span class="text-accent"><?= lang('Core.review') ?></span>
  </a>
<?php endif ?>
<?php if (($statusCounts['past_due'] ?? 0) > 0): ?>
  <a href="<?= site_url('plataforma/suscripciones?status=past_due') ?>" class="mt-4 flex items-center gap-3 rounded-xl border border-line bg-surface px-4 py-3 text-sm hover:bg-muted">
    <?= icon('triangle-alert', 16, 'text-warn') ?><span class="flex-1"><?= lang('Platform.alertPastDue', [num($statusCounts['past_due'])]) ?></span><span class="text-accent"><?= lang('Core.review') ?></span>
  </a>
<?php endif ?>
<div class="mt-4 grid gap-4 lg:grid-cols-3">
  <div class="card lg:col-span-2">
    <div class="card-header"><h2 class="card-title"><?= lang('Platform.renewals') ?></h2></div>
    <?php if ($renewals === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Platform.noRenewals') ?></p><?php else: ?>
    <ul class="divide-y divide-line">
      <?php foreach ($renewals as $r): ?>
        <li class="flex items-center justify-between gap-3 px-4 py-2.5"><span><span class="font-medium"><?= esc($r['company_name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($r['plan_name']) ?></span></span><span class="text-right text-xs text-ink-soft"><?= status_badge($r['status']) ?><span class="block"><?= dt($r['current_period_end'], false) ?></span></span></li>
      <?php endforeach ?>
    </ul><?php endif ?>
  </div>
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= lang('Platform.pendingCommissions') ?></h2></div>
    <div class="card-body space-y-1">
      <?php if ($pendingComm === []): ?><p class="text-sm text-ink-soft"><?= lang('Platform.noPendingCommissions') ?></p><?php endif ?>
      <?php foreach ($pendingComm as $c): ?><p class="flex justify-between"><span class="text-ink-soft"><?= esc($c['currency']) ?></span><span class="font-semibold"><?= money($c['total'], $c['currency']) ?></span></p><?php endforeach ?>
      <a class="btn btn-ghost btn-sm mt-2" href="<?= site_url('plataforma/comisiones?status=pending') ?>"><?= lang('Core.review') ?><?= icon('chevron-right', 14) ?></a>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
