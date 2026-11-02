<?= $this->extend(ctx()->scope() ? 'layouts/app' : 'layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Nav.profile') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.profile'), 'description' => lang('Profile.description')]) ?>
<div class="grid gap-4 lg:grid-cols-2">
  <form method="post" action="<?= site_url('perfil') ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-header"><h2 class="card-title"><?= lang('Profile.personal') ?></h2></div>
    <div class="card-body space-y-4">
      <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'value' => $user['name'], 'required' => true, 'attrs' => ['maxlength' => 120]]) ?>
      <div class="field"><span class="label"><?= lang('Auth.email') ?></span><p class="input flex items-center bg-muted"><?= esc($user['email']) ?></p><p class="hint"><?= lang('Profile.emailHint') ?></p></div>
      <?= view('partials/field', ['name' => 'locale', 'label' => lang('Profile.language'), 'type' => 'select', 'value' => $user['locale'], 'options' => ['es' => 'Español', 'en' => 'English']]) ?>
    </div>
    <div class="drawer-foot justify-end"><button class="btn btn-primary" type="submit"><?= lang('Core.saveChanges') ?></button></div>
  </form>
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= lang('Profile.security') ?></h2></div>
    <div class="card-body space-y-3">
      <a class="flex items-center justify-between rounded-xl border border-line p-3 hover:bg-muted" href="<?= site_url('perfil/clave') ?>">
        <span><span class="block font-medium"><?= lang('Profile.changePassword') ?></span><span class="block text-xs text-ink-soft"><?= lang('Profile.passwordChangedAt', [dt($user['password_changed_at'] ?? $user['created_at'])]) ?></span></span><?= icon('chevron-right', 16) ?>
      </a>
      <a class="flex items-center justify-between rounded-xl border border-line p-3 hover:bg-muted" href="<?= site_url('perfil/mfa') ?>">
        <span><span class="block font-medium"><?= lang('Profile.mfa') ?></span><span class="block text-xs text-ink-soft"><?= (int) $user['mfa_enabled'] === 1 ? lang('Profile.mfaOn') : lang('Profile.mfaOff') ?></span></span>
        <?= status_badge((int) $user['mfa_enabled'] === 1 ? 'enabled' : 'disabled') ?>
      </a>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
