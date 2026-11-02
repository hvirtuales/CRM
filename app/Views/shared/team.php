<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$roleOptions = [];
foreach ($roles as $r) { $roleOptions[$r['id']] = lang('Roles.' . $r['code']) !== 'Roles.' . $r['code'] ? lang('Roles.' . $r['code']) : $r['name']; }
$quota = $quota ?? null;
?>
<?= view('partials/page_header', ['title' => $title, 'description' => lang('Core.teamHelp'),
    'actions' => $canEdit && ($quota === null || $quota > 0) ? '<button type="button" class="btn btn-primary" data-open-dialog="dlg-member">' . icon('user-plus') . esc(lang('Core.addMember')) . '</button>' : '']) ?>
<?= view('partials/temp_password') ?>
<?php if ($quota !== null): ?><div class="notice mb-3"><?= lang('Core.usersQuotaLeft', [$quota]) ?></div><?php endif ?>
<?php if ($members === []): ?>
  <?= view('partials/empty', ['message' => lang('Core.teamEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.name') ?></th><th><?= lang('Core.role') ?></th><th><?= lang('Core.lastLogin') ?></th><th><?= lang('Core.statusLabel') ?></th><?php if ($canEdit): ?><th class="num"><?= lang('Core.actions') ?></th><?php endif ?></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $self = (int) ctx()->userId() === (int) $m['user_id']; ?>
    <tr>
      <td><span class="font-medium"><?= esc($m['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($m['email']) ?><?= (int) $m['mfa_enabled'] === 1 ? ' · MFA' : '' ?></span></td>
      <td>
        <?php if ($canEdit && ! $self): ?>
          <form method="post" action="<?= site_url($base . '/' . (int) $m['id']) ?>" class="flex items-center gap-2">
            <?= csrf_field() ?>
            <select name="role_id" class="select w-56" aria-label="<?= esc(lang('Core.role'), 'attr') ?>">
              <?php foreach ($roleOptions as $rid => $rname): ?><option value="<?= (int) $rid ?>"<?= (int) $rid === (int) $m['role_id'] ? ' selected' : '' ?>><?= esc($rname) ?></option><?php endforeach ?>
            </select>
            <button class="btn btn-secondary btn-sm" type="submit"><?= lang('Core.save') ?></button>
          </form>
        <?php else: ?><?= esc($roleOptions[$m['role_id']] ?? $m['role_name']) ?><?php endif ?>
      </td>
      <td><?= dt($m['last_login_at']) ?></td>
      <td><?= status_badge($m['status']) ?></td>
      <?php if ($canEdit): ?><td class="num"><?php if (! $self): ?>
        <?= view('partials/delete_button', $m['status'] === 'active'
            ? ['action' => site_url($base . '/' . (int) $m['id']), 'label' => lang('Core.suspend'), 'fields' => ['status' => 'suspended'], 'confirm' => lang('Core.confirmSuspendMember')]
            : ['action' => site_url($base . '/' . (int) $m['id']), 'label' => lang('Core.activate'), 'fields' => ['status' => 'active'], 'variant' => 'secondary']) ?>
      <?php endif ?></td><?php endif ?>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
<?php if ($canEdit): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-member', 'title' => lang('Core.addMember'), 'open' => $errors !== []]) ?>
<form method="post" action="<?= site_url($base) ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'required' => true, 'placeholder' => lang('Core.personNamePh')]) ?>
    <?= view('partials/field', ['name' => 'email', 'label' => lang('Core.email'), 'type' => 'email', 'required' => true, 'placeholder' => lang('Core.emailPh'), 'hint' => lang('Core.memberEmailHint')]) ?>
    <?= view('partials/field', ['name' => 'role_id', 'label' => lang('Core.role'), 'type' => 'select', 'options' => $roleOptions, 'required' => true]) ?>
    <div class="notice"><?= lang('Core.tempPasswordExplain') ?></div>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Core.addMember') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
