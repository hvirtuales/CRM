<?= $this->extend(ctx()->scope() && ! $forced ? 'layouts/app' : 'layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Profile.changePassword') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('perfil/clave') ?>" class="card mx-auto max-w-md space-y-4 rounded-2xl p-5">
  <?= csrf_field() ?>
  <h2 class="text-base font-semibold"><?= lang('Profile.changePassword') ?></h2>
  <?php if ($forced): ?><div class="notice"><?= lang('Auth.mustChangePassword') ?></div><?php endif ?>
  <?= view('partials/field', ['name' => 'current_password', 'label' => lang('Profile.currentPassword'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
  <?= view('partials/field', ['name' => 'new_password', 'label' => lang('Profile.newPassword'), 'type' => 'password', 'required' => true, 'hint' => lang('Profile.passwordPolicy', [12]), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 128]]) ?>
  <?= view('partials/field', ['name' => 'new_password_confirm', 'label' => lang('Profile.confirmPassword'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <button type="submit" class="btn btn-primary w-full"><?= lang('Profile.changePassword') ?></button>
</form>
<?= $this->endSection() ?>
