<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.companies') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$canCreate = auth_can('platform.companies.create'); $canEdit = auth_can('platform.companies.edit'); $canPlan = auth_can('platform.subscriptions.edit');
$errors = session('errors') ?? [];
$planOptions = ['' => lang('Platform.noPlanYet')];
foreach ($plans as $p) { $planOptions[$p['id']] = $p['name'] . ' · ' . money($p['price'], $p['currency']); }
$tenantOptions = ['' => lang('Platform.direct')];
foreach ($tenants as $t) { $tenantOptions[$t['id']] = $t['name']; }
?>
<?= view('partials/page_header', [
    'title' => lang('Nav.companies'), 'description' => lang('Platform.companiesHelp'),
    'actions' => $canCreate ? '<button type="button" class="btn btn-primary" data-open-dialog="dlg-new">' . icon('plus') . esc(lang('Platform.newDirectCompany')) . '</button>' : '',
]) ?>
<?= view('partials/temp_password') ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc(lang('Platform.searchCompanies'), 'attr') ?>" aria-label="<?= esc(lang('Core.search'), 'attr') ?>"></div>
  <select class="select w-56" name="tenant" aria-label="<?= esc(lang('Platform.tenant'), 'attr') ?>">
    <option value=""><?= lang('Platform.allTenants') ?></option>
    <option value="direct"<?= $tenant === 'direct' ? ' selected' : '' ?>><?= lang('Platform.direct') ?></option>
    <?php foreach ($tenants as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $tenant === (string) $t['id'] ? ' selected' : '' ?>><?= esc($t['name']) ?></option><?php endforeach ?>
  </select>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.companiesEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.name') ?></th><th><?= lang('Platform.tenant') ?></th><th><?= lang('Core.plan') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"><?= lang('Core.actions') ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><span class="font-medium"><?= esc($c['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($c['tax_id'] ?? $c['slug']) ?></span></td>
      <td><?= $c['tenant_name'] ? esc($c['tenant_name']) : '<span class="text-ink-soft">' . lang('Platform.direct') . '</span>' ?></td>
      <td><?= esc($c['plan_name'] ?? '—') ?></td>
      <td><?= status_badge($c['status']) ?></td>
      <td class="num"><?php if ($canEdit || $canPlan): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/negocios?edit=' . (int) $c['id']) ?>"><?= icon('settings', 14) ?><?= lang('Core.manage') ?></a><?php endif ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>

<?php if ($canCreate): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-new', 'title' => lang('Platform.newDirectCompany'), 'wide' => true, 'open' => $errors !== [] && ! $edit]) ?>
<form method="post" action="<?= site_url('plataforma/negocios') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4"><?= view('shared/company_form', ['planOptions' => $planOptions]) ?></div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary" data-busy="<?= esc(lang('Core.saving'), 'attr') ?>"><?= lang('Platform.createCompany') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>

<?php if ($edit): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-edit', 'title' => $edit['name'], 'wide' => true, 'open' => true, 'closeTo' => site_url('plataforma/negocios')]) ?>
<div class="drawer-body space-y-4">
  <div class="flex flex-wrap items-center gap-2"><?= status_badge($edit['status']) ?><span class="text-xs text-ink-soft"><?= lang('Core.createdAt', [dt($edit['created_at'])]) ?></span></div>
  <div class="notice"><?= lang('Platform.privacyNotice') ?></div>
  <?php if ($canPlan): ?>
  <form method="post" action="<?= site_url('plataforma/negocios/' . (int) $edit['id'] . '/plan') ?>" class="form-section">
    <?= csrf_field() ?>
    <p class="form-section-title"><?= lang('Platform.assignPlan') ?></p>
    <p class="form-section-hint"><?= lang('Platform.assignPlanHint') ?></p>
    <?= view('partials/field', ['name' => 'plan_id', 'label' => lang('Core.plan'), 'type' => 'select', 'value' => $edit['plan_id'], 'options' => $planOptions, 'required' => true]) ?>
    <div class="flex justify-end"><button class="btn btn-secondary" type="submit"><?= lang('Platform.assignPlan') ?></button></div>
  </form>
  <?php endif ?>
  <?php if ($canEdit): ?>
  <form method="post" action="<?= site_url('plataforma/negocios/' . (int) $edit['id'] . '/traslado') ?>" class="form-section" data-confirm="<?= esc(lang('Platform.confirmTransfer'), 'attr') ?>">
    <?= csrf_field() ?>
    <p class="form-section-title"><?= lang('Platform.transfer') ?></p>
    <p class="form-section-hint"><?= lang('Platform.transferHint') ?></p>
    <?= view('partials/field', ['name' => 'tenant_id', 'label' => lang('Platform.tenant'), 'type' => 'select', 'value' => $edit['tenant_id'], 'options' => $tenantOptions]) ?>
    <?= view('partials/field', ['name' => 'reason', 'label' => lang('Core.reason'), 'type' => 'textarea', 'required' => true, 'placeholder' => lang('Platform.transferReasonPh')]) ?>
    <div class="flex justify-end"><button class="btn btn-secondary" type="submit"><?= lang('Platform.transfer') ?></button></div>
  </form>
  <?php endif ?>
</div>
<?php if ($canEdit): ?>
<div class="drawer-foot">
  <?php foreach (['active' => 'activate', 'suspended' => 'suspend', 'archived' => 'archive'] as $st => $verb): if ($st === $edit['status']) { continue; } ?>
    <?= view('partials/delete_button', ['action' => site_url('plataforma/negocios/' . (int) $edit['id'] . '/estado'), 'label' => lang('Core.' . $verb), 'fields' => ['status' => $st], 'variant' => $st === 'active' ? 'secondary' : 'danger', 'confirm' => lang('Platform.confirmCompanyStatus.' . $st)]) ?>
  <?php endforeach ?>
  <span class="flex-1"></span><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.close') ?></button>
</div>
<?php endif ?>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
