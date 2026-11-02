<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.tenants') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $canCreate = auth_can('platform.tenants.create'); $canEdit = auth_can('platform.tenants.edit'); $errors = session('errors') ?? []; ?>
<?= view('partials/page_header', [
    'title' => lang('Nav.tenants'), 'description' => lang('Platform.tenantsHelp'),
    'actions' => $canCreate ? '<button type="button" class="btn btn-primary" data-open-dialog="dlg-new">' . icon('plus') . esc(lang('Platform.newTenant')) . '</button>' : '',
]) ?>
<?= view('partials/invitation_notice') ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc(lang('Platform.searchTenants'), 'attr') ?>" aria-label="<?= esc(lang('Core.search'), 'attr') ?>"></div>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Core.statusLabel'), 'attr') ?>">
    <option value=""><?= lang('Core.all') ?></option>
    <?php foreach (['active', 'suspended', 'archived'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= lang('Core.status.' . $s) ?></option><?php endforeach ?>
  </select>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.tenantsEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.name') ?></th><th><?= lang('Platform.companiesCount') ?></th><th class="num"><?= lang('Platform.commissionRate') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"><?= lang('Core.actions') ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $t): ?>
    <tr>
      <td><span class="font-medium"><?= esc($t['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($t['contact_email'] ?? $t['slug']) ?></span></td>
      <td><a class="hover:text-accent" href="<?= site_url('plataforma/negocios?tenant=' . (int) $t['id']) ?>"><?= num($t['companies_count']) ?></a></td>
      <td class="num"><?= num((float) $t['commission_rate'] * 100, 2) ?> %</td>
      <td><?= status_badge($t['status']) ?></td>
      <td class="num"><div class="flex justify-end gap-1">
        <?php if ($canEdit): ?>
          <a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/distribuidores?edit=' . (int) $t['id']) ?>"><?= icon('pencil', 14) ?><?= lang('Core.edit') ?></a>
          <?= view('partials/delete_button', $t['status'] === 'active'
              ? ['action' => site_url('plataforma/distribuidores/' . $t['id'] . '/estado'), 'label' => lang('Core.suspend'), 'fields' => ['status' => 'suspended'], 'confirm' => lang('Platform.confirmSuspendTenant')]
              : ['action' => site_url('plataforma/distribuidores/' . $t['id'] . '/estado'), 'label' => lang('Core.activate'), 'fields' => ['status' => 'active'], 'variant' => 'secondary']) ?>
        <?php endif ?>
      </div></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>

<?php if ($canCreate): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-new', 'title' => lang('Platform.newTenant'), 'wide' => true, 'open' => $errors !== [] && ! $edit]) ?>
<form method="post" action="<?= site_url('plataforma/distribuidores') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionIdentity') ?></legend>
      <p class="form-section-hint"><?= lang('Platform.sectionIdentityHint') ?></p>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'required' => true, 'placeholder' => lang('Platform.tenantNamePh'), 'class' => 'sm:col-span-2', 'attrs' => ['maxlength' => 150]]) ?>
        <?= view('partials/field', ['name' => 'tax_id', 'label' => lang('Core.taxId'), 'placeholder' => lang('Core.taxIdPh')]) ?>
        <?= view('partials/field', ['name' => 'contact_email', 'label' => lang('Core.email'), 'type' => 'email', 'placeholder' => lang('Core.emailPh')]) ?>
      </div>
    </fieldset>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionCommercial') ?></legend>
      <p class="form-section-hint"><?= lang('Platform.sectionCommercialHint') ?></p>
      <?= view('partials/field', ['name' => 'commission_rate', 'label' => lang('Platform.commissionRate'), 'type' => 'number', 'value' => '0.2000', 'required' => true, 'hint' => lang('Platform.commissionRateHint'), 'attrs' => ['step' => '0.0001', 'min' => '0', 'max' => '1']]) ?>
    </fieldset>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionOwner') ?></legend>
      <p class="form-section-hint"><?= lang('Platform.sectionOwnerHint') ?></p>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= view('partials/field', ['name' => 'owner_name', 'label' => lang('Core.name'), 'required' => true, 'placeholder' => lang('Core.personNamePh')]) ?>
        <?= view('partials/field', ['name' => 'owner_email', 'label' => lang('Core.email'), 'type' => 'email', 'required' => true, 'placeholder' => lang('Core.emailPh')]) ?>
      </div>
    </fieldset>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary" data-busy="<?= esc(lang('Core.saving'), 'attr') ?>"><?= lang('Platform.createTenant') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>

<?php if ($edit): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-edit', 'title' => lang('Platform.editTenant'), 'open' => true, 'closeTo' => site_url('plataforma/distribuidores')]) ?>
<form method="post" action="<?= site_url('plataforma/distribuidores/' . (int) $edit['id']) ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'value' => $edit['name'], 'required' => true]) ?>
    <?= view('partials/field', ['name' => 'tax_id', 'label' => lang('Core.taxId'), 'value' => $edit['tax_id']]) ?>
    <?= view('partials/field', ['name' => 'contact_email', 'label' => lang('Core.email'), 'type' => 'email', 'value' => $edit['contact_email']]) ?>
    <?= view('partials/field', ['name' => 'commission_rate', 'label' => lang('Platform.commissionRate'), 'type' => 'number', 'value' => $edit['commission_rate'], 'required' => true, 'hint' => lang('Platform.commissionRateHint'), 'attrs' => ['step' => '0.0001', 'min' => '0', 'max' => '1']]) ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Core.saveChanges') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
