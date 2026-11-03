<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Signup.sentTitle') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card space-y-3 rounded-2xl p-5">
  <h2 class="text-base font-semibold"><?= lang('Signup.sentTitle') ?></h2>
  <p class="text-sm text-ink-soft"><?= lang('Signup.sentBody', [\App\Libraries\SignupService::TTL_HOURS]) ?></p>
  <?php if (! empty($devLink)): ?>
    <div class="notice-accent"><div class="min-w-0"><p class="text-xs"><?= lang('Core.invitationDevLink') ?></p><p class="mt-1 break-all font-mono text-xs select-all"><?= esc($devLink) ?></p></div></div>
  <?php endif ?>
</div>
<?= $this->endSection() ?>
