<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?><?= lang('Core.chooseContext') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="space-y-3">
  <h2 class="text-base font-semibold"><?= lang('Core.chooseContext') ?></h2>
  <p class="text-sm text-ink-soft"><?= lang('Core.chooseContextHelp') ?></p>
  <?php if (empty($available) && empty($support)): ?>
    <?= view('partials/empty', ['message' => lang('Core.noContexts')]) ?>
  <?php endif ?>
  <?php foreach ($available as $a): ?>
    <form method="post" action="<?= site_url('contexto') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="membership_id" value="<?= (int) $a['membership_id'] ?>">
      <button type="submit" class="flex w-full items-center gap-3 rounded-xl border border-line bg-surface p-4 text-left hover:bg-muted">
        <span class="grid size-10 place-items-center rounded-full bg-muted text-ink-soft"><?= icon(['platform' => 'shield-check', 'tenant' => 'building-2', 'company' => 'store'][$a['scope']], 18) ?></span>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium"><?= esc($a['name']) ?></span>
          <span class="block text-xs text-ink-soft"><?= lang('Core.scope.' . $a['scope']) ?> · <?= esc($a['role']) ?></span>
        </span>
        <?php if ($a['status'] !== 'active'): ?><?= status_badge($a['status']) ?><?php endif ?>
        <?= icon('chevron-right', 16, 'text-ink-soft') ?>
      </button>
    </form>
  <?php endforeach ?>
  <?php if (! empty($support)): ?>
    <h3 class="pt-3 text-xs font-semibold uppercase tracking-wide text-ink-soft"><?= lang('Core.supportAccesses') ?></h3>
    <?php foreach ($support as $s): ?>
      <form method="post" action="<?= site_url('contexto/soporte') ?>">
        <?= csrf_field() ?><input type="hidden" name="grant_id" value="<?= (int) $s['id'] ?>">
        <button type="submit" class="flex w-full items-center gap-3 rounded-xl border border-dashed border-line bg-surface p-4 text-left hover:bg-muted">
          <?= icon('life-buoy', 18, 'text-ink-soft') ?>
          <span class="flex-1"><span class="block font-medium"><?= esc($s['company_name']) ?></span><span class="block text-xs text-ink-soft"><?= lang('Core.supportUntil', [dt($s['expires_at'])]) ?></span></span>
        </button>
      </form>
    <?php endforeach ?>
  <?php endif ?>
  <div class="flex justify-between pt-2">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('perfil') ?>"><?= lang('Nav.profile') ?></a>
    <form method="post" action="<?= site_url('salir') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('log-out', 14) ?><?= lang('Auth.logout') ?></button></form>
  </div>
</div>
<?= $this->endSection() ?>
