<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Signup.title') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card space-y-3 rounded-2xl p-5">
  <p class="text-sm"><?= esc($message ?? lang('Signup.closed')) ?></p>
  <a class="btn btn-secondary w-full" href="<?= site_url('ingresar') ?>"><?= lang('Auth.signIn') ?></a>
</div>
<?= $this->endSection() ?>
