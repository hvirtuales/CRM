<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Auth.loginTitle') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('ingresar') ?>" class="card space-y-4 rounded-2xl p-5" novalidate>
  <?= csrf_field() ?>
  <h2 class="text-base font-semibold"><?= lang('Auth.loginTitle') ?></h2>
  <?= view('partials/field', ['name' => 'email', 'label' => lang('Auth.email'), 'type' => 'email', 'required' => true, 'placeholder' => lang('Auth.emailPlaceholder'), 'attrs' => ['autocomplete' => 'username', 'autofocus' => 'autofocus', 'maxlength' => 190]]) ?>
  <?= view('partials/field', ['name' => 'password', 'label' => lang('Auth.password'), 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password', 'maxlength' => 256]]) ?>
  <button type="submit" class="btn btn-primary btn-lg w-full" data-busy="<?= esc(lang('Auth.signingIn'), 'attr') ?>"><?= lang('Auth.signIn') ?></button>
  <p class="hint"><?= lang('Auth.loginHelp') ?></p>
</form>
<?= $this->endSection() ?>
