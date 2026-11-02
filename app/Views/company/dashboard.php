<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.dashboard') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Company.dashboardTitle', [ctx()->company()['name']]), 'description' => lang('Company.dashboardHelp')]) ?>
<?php if ($kpis !== []): ?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?php foreach ($kpis as $k): ?>
    <div class="kpi"><div class="kpi-label"><?= esc($k['label']) ?></div><div class="kpi-value <?= $k['tone'] ? 'text-' . esc($k['tone'], 'attr') : '' ?>"><?= esc($k['value']) ?></div><?php if ($k['hint']): ?><div class="kpi-hint"><?= esc($k['hint']) ?></div><?php endif ?></div>
  <?php endforeach ?>
</div>
<?php endif ?>
<div class="mt-4 grid gap-4 lg:grid-cols-3">
  <?php foreach ($cards as $c): ?><div class="<?= $c['wide'] ? 'lg:col-span-2' : '' ?>"><?= $c['html'] /* HTML renderizado y escapado por la vista del componente */ ?></div><?php endforeach ?>
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= lang('Company.planUsage') ?></h2><?php if ($sub): ?><?= status_badge($sub['status']) ?><?php endif ?></div>
    <div class="card-body space-y-3">
      <p class="text-sm"><?= $plan ? esc($plan['name']) : lang('Company.noPlan') ?></p>
      <?= view('company/_usage', ['planStatus' => $planStatus]) ?>
      <?php if (auth_can('company.subscription.view')): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('negocio/plan') ?>"><?= lang('Core.details') ?><?= icon('chevron-right', 14) ?></a><?php endif ?>
    </div>
  </div>
</div>
<?php if ($kpis === [] && $cards === []): ?>
  <div class="mt-4"><?= view('partials/empty', ['message' => lang('Company.dashboardEmpty')]) ?></div>
<?php endif ?>
<?= $this->endSection() ?>
