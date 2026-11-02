<?= $this->extend(ctx()->scope() && session('mfa_passed') ? 'layouts/app' : 'layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Profile.mfa') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card mx-auto max-w-md space-y-4 rounded-2xl p-5">
  <h2 class="text-base font-semibold"><?= lang('Profile.mfa') ?></h2>
  <?php if ($enabled): ?>
    <p class="text-sm text-ink-soft"><?= lang('Profile.mfaActive') ?></p>
    <form method="post" action="<?= site_url('perfil/mfa/desactivar') ?>" class="space-y-3" data-confirm="<?= esc(lang('Profile.mfaDisableConfirm'), 'attr') ?>">
      <?= csrf_field() ?>
      <?= view('partials/field', ['name' => 'code', 'label' => lang('Auth.mfaCode'), 'required' => true, 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 7]]) ?>
      <button type="submit" class="btn btn-danger w-full"><?= lang('Profile.mfaDisable') ?></button>
    </form>
  <?php else: ?>
    <p class="text-sm text-ink-soft"><?= lang('Profile.mfaSetupHelp') ?></p>
    <ol class="list-decimal space-y-2 pl-5 text-sm">
      <li><?= lang('Profile.mfaStep1') ?></li>
      <li><?= lang('Profile.mfaStep2') ?>
        <div class="mt-2 flex items-center gap-2"><code class="notice flex-1 break-all font-mono text-xs select-all"><?= esc(chunk_split($secret, 4, ' ')) ?></code>
          <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= esc($secret, 'attr') ?>" data-copied="<?= esc(lang('Core.copied'), 'attr') ?>"><?= icon('copy', 14) ?><?= lang('Core.copy') ?></button></div>
        <details class="mt-2 text-xs text-ink-soft"><summary class="cursor-pointer"><?= lang('Profile.mfaUri') ?></summary><code class="mt-1 block break-all select-all"><?= esc($uri) ?></code></details>
      </li>
      <li><?= lang('Profile.mfaStep3') ?></li>
    </ol>
    <form method="post" action="<?= site_url('perfil/mfa') ?>" class="space-y-3">
      <?= csrf_field() ?>
      <?= view('partials/field', ['name' => 'code', 'label' => lang('Auth.mfaCode'), 'required' => true, 'placeholder' => '123456', 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 7, 'autofocus' => 'autofocus']]) ?>
      <button type="submit" class="btn btn-primary w-full"><?= lang('Profile.mfaEnable') ?></button>
    </form>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
