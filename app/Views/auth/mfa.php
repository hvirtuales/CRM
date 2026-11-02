<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Auth.mfaTitle') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('mfa') ?>" class="card space-y-4 rounded-2xl p-5">
  <?= csrf_field() ?>
  <h2 class="text-base font-semibold"><?= lang('Auth.mfaTitle') ?></h2>
  <p class="text-sm text-ink-soft"><?= lang('Auth.mfaHelp') ?></p>
  <?= view('partials/field', ['name' => 'code', 'label' => lang('Auth.mfaCode'), 'required' => true, 'placeholder' => '123456', 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'pattern' => '[0-9 ]{6,7}', 'maxlength' => 7, 'autofocus' => 'autofocus']]) ?>
  <button type="submit" class="btn btn-primary btn-lg w-full"><?= lang('Auth.verify') ?></button>
</form>
<form method="post" action="<?= site_url('salir') ?>" class="text-center"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= lang('Auth.logout') ?></button></form>
<?= $this->endSection() ?>
