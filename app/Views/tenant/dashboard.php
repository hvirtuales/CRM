<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.portfolio') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Tenant.dashboardTitle'), 'description' => lang('Tenant.dashboardHelp'),
    'actions' => auth_can('tenant.companies.create') ? '<a class="btn btn-primary" href="' . site_url('distribuidor/negocios?new=1') . '">' . icon('plus') . esc(lang('Tenant.newCompany')) . '</a>' : '']) ?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <div class="kpi"><div class="kpi-label"><?= lang('Tenant.kpiCompanies') ?></div><div class="kpi-value"><?= num($companies) ?></div></div>
  <div class="kpi"><div class="kpi-label"><?= lang('Platform.kpiMrr') ?></div><?php if ($mrr === []): ?><div class="kpi-value">—</div><?php endif ?><?php foreach ($mrr as $cur => $v): ?><div class="kpi-value"><?= money($v, $cur) ?></div><?php endforeach ?><div class="kpi-hint"><?= lang('Platform.kpiMrrHint') ?></div></div>
  <div class="kpi"><div class="kpi-label"><?= lang('Tenant.kpiTrials') ?></div><div class="kpi-value"><?= num($trials) ?></div></div>
  <div class="kpi"><div class="kpi-label"><?= lang('Platform.kpiPastDue') ?></div><div class="kpi-value <?= $pastDue > 0 ? 'text-warn' : '' ?>"><?= num($pastDue) ?></div></div>
</div>
<div class="mt-4 grid gap-4 lg:grid-cols-3">
  <div class="card lg:col-span-2">
    <div class="card-header"><h2 class="card-title"><?= lang('Platform.renewals') ?></h2></div>
    <?php if ($renewals === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Platform.noRenewals') ?></p><?php else: ?>
    <ul class="divide-y divide-line"><?php foreach ($renewals as $r): ?>
      <li class="flex items-center justify-between gap-3 px-4 py-2.5"><span><span class="font-medium"><?= esc($r['company_name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($r['plan_name']) ?></span></span><span class="text-right text-xs text-ink-soft"><?= status_badge($r['status']) ?><span class="block"><?= dt($r['current_period_end'], false) ?></span></span></li>
    <?php endforeach ?></ul><?php endif ?>
  </div>
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= lang('Nav.commissions') ?></h2></div>
    <div class="card-body space-y-1">
      <?php if ($commissions === []): ?><p class="text-sm text-ink-soft"><?= lang('Tenant.noCommissions') ?></p><?php endif ?>
      <?php foreach ($commissions as $c): ?><p class="flex justify-between"><span class="text-ink-soft"><?= lang('Core.status.' . $c['status']) ?></span><span class="font-semibold"><?= money($c['total'], $c['currency']) ?></span></p><?php endforeach ?>
    </div>
  </div>
</div>
<div class="card mt-4">
  <div class="card-header"><h2 class="card-title"><?= lang('Tenant.signalsTitle') ?></h2><span class="text-sm font-semibold <?= $signals ? 'text-warn' : 'text-ink-soft' ?>"><?= count($signals) ?></span></div>
  <?php if ($signals === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Tenant.signalsEmpty') ?></p><?php else: ?>
  <ul class="divide-y divide-line"><?php foreach ($signals as $s): ?>
    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5">
      <span class="min-w-0"><span class="block font-medium"><?= esc($s['company']['name']) ?></span><span class="block truncate text-xs text-ink-soft"><?= esc($s['detail']) ?></span></span>
      <span class="flex items-center gap-2"><?= status_badge($s['kind'] === 'upsell' ? 'open' : 'pending', lang('Commercial.kind.' . $s['kind'])) ?>
        <?php if (auth_can('tenant.subscriptions.edit')): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('distribuidor/negocios?edit=' . (int) $s['company']['id']) ?>"><?= lang('Core.manage') ?></a><?php endif ?></span>
    </li>
  <?php endforeach ?></ul><?php endif ?>
</div>
<p class="mt-4 text-xs text-ink-soft"><?= lang('Tenant.privacyNote') ?></p>
<?= $this->endSection() ?>
