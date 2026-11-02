<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.companies') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$canCreate = auth_can('tenant.companies.create'); $canPlan = auth_can('tenant.subscriptions.edit');
$errors = session('errors') ?? []; $isNew = service('request')->getGet('new') === '1';
$planOptions = ['' => lang('Platform.noPlanYet')];
foreach ($plans as $p) { $planOptions[$p['id']] = $p['name'] . ' · ' . money($p['price'], $p['currency']); }
?>
<?= view('partials/page_header', ['title' => lang('Nav.companies'), 'description' => lang('Tenant.companiesHelp'),
    'actions' => $canCreate ? '<button type="button" class="btn btn-primary" data-open-dialog="dlg-new">' . icon('plus') . esc(lang('Tenant.newCompany')) . '</button>' : '']) ?>
<?= view('partials/temp_password') ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2">
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc(lang('Platform.searchCompanies'), 'attr') ?>" aria-label="<?= esc(lang('Core.search'), 'attr') ?>"></div>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Tenant.companiesEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.name') ?></th><th><?= lang('Core.plan') ?></th><th><?= lang('Platform.subscription') ?></th><th><?= lang('Platform.periodEnd') ?></th><th class="num"></th></tr></thead>
  <tbody><?php foreach ($rows as $c): ?>
    <tr>
      <td><span class="font-medium"><?= esc($c['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($c['email'] ?? $c['tax_id'] ?? '') ?></span></td>
      <td><?= esc($c['plan_name'] ?? '—') ?></td>
      <td><?= $c['sub_status'] ? status_badge($c['sub_status']) : '—' ?> <?= $c['status'] !== 'active' ? status_badge($c['status']) : '' ?></td>
      <td><?= dt($c['current_period_end'], false) ?></td>
      <td class="num"><?php if ($canPlan): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('distribuidor/negocios?edit=' . (int) $c['id']) ?>"><?= icon('credit-card', 14) ?><?= lang('Platform.assignPlan') ?></a><?php endif ?></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>
<?php if ($canCreate): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-new', 'title' => lang('Tenant.newCompany'), 'wide' => true, 'open' => ($errors !== [] && ! $edit) || $isNew, 'closeTo' => site_url('distribuidor/negocios')]) ?>
<form method="post" action="<?= site_url('distribuidor/negocios') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4"><?= view('shared/company_form', ['planOptions' => $planOptions]) ?></div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary" data-busy="<?= esc(lang('Core.saving'), 'attr') ?>"><?= lang('Platform.createCompany') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?php if ($edit && $canPlan): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-plan', 'title' => $edit['name'], 'open' => true, 'closeTo' => site_url('distribuidor/negocios')]) ?>
<form method="post" action="<?= site_url('distribuidor/negocios/' . (int) $edit['id'] . '/plan') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <p class="text-sm text-ink-soft"><?= lang('Platform.assignPlanHint') ?></p>
    <?= view('partials/field', ['name' => 'plan_id', 'label' => lang('Core.plan'), 'type' => 'select', 'value' => $edit['plan_id'], 'options' => $planOptions, 'required' => true]) ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Platform.assignPlan') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
