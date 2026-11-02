<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= $inv ? lang('Core.invitationTitle', [$inv['organization']]) : lang('Core.invitationInvalid') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card space-y-4 rounded-2xl p-5">
<?php if (! $inv): ?>
  <p class="text-sm"><?= lang('Core.invitationInvalid') ?></p>
  <a class="btn btn-secondary w-full" href="<?= site_url('ingresar') ?>"><?= lang('Auth.signIn') ?></a>
<?php else: $role = lang('Roles.' . (db_connect()->table('roles')->select('code')->where('id', $inv['role_id'])->get()->getRow()->code ?? '')); ?>
  <h2 class="text-base font-semibold"><?= lang('Core.invitationTitle', [$inv['organization']]) ?></h2>
  <p class="text-sm text-ink-soft"><?= lang('Core.invitationIntro', [$role, dt($inv['expires_at'])]) ?></p>
  <?php if (! $inv['has_account']): ?>
    <form method="post" action="<?= site_url('invitacion/' . $token) ?>" class="space-y-4">
      <?= csrf_field() ?>
      <p class="text-sm"><?= lang('Core.invitationSetPassword', [$inv['email']]) ?></p>
      <?= view('partials/field', ['name' => 'password', 'label' => lang('Profile.newPassword'), 'type' => 'password', 'required' => true, 'hint' => lang('Profile.passwordPolicy', [12]), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 128]]) ?>
      <?= view('partials/field', ['name' => 'password_confirm', 'label' => lang('Profile.confirmPassword'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
      <button type="submit" class="btn btn-primary w-full"><?= lang('Core.invitationAccept') ?></button>
    </form>
  <?php elseif ($sessionUser && strtolower($sessionUser['email']) === strtolower($inv['email'])): ?>
    <form method="post" action="<?= site_url('invitacion/' . $token) ?>"><?= csrf_field() ?>
      <button type="submit" class="btn btn-primary w-full"><?= lang('Core.invitationAccept') ?></button>
    </form>
  <?php else: ?>
    <p class="text-sm"><?= $sessionUser ? lang('Core.invitationLoginAs', [$inv['email']]) : lang('Core.invitationLoginFirst', [$inv['email']]) ?></p>
    <?php if (! $sessionUser): ?><a class="btn btn-primary w-full" href="<?= site_url('ingresar') ?>"><?= lang('Auth.signIn') ?></a><?php endif ?>
  <?php endif ?>
<?php endif ?>
</div>
<?= $this->endSection() ?>
