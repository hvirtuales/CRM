<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.subscription') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.subscription'), 'description' => lang('Company.planHelp')]) ?>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card"><div class="card-header"><h2 class="card-title"><?= $plan ? esc($plan['name']) : lang('Company.noPlan') ?></h2><?php if ($sub): ?><?= status_badge($sub['status']) ?><?php endif ?></div>
    <div class="card-body">
      <?php if ($sub): ?>
      <dl class="grid gap-3 sm:grid-cols-2">
        <div><dt class="text-xs text-ink-soft"><?= lang('Core.price') ?></dt><dd><?= money($sub['price'], $sub['currency']) ?> · <?= lang('Platform.periods.' . $sub['billing_period']) ?></dd></div>
        <div><dt class="text-xs text-ink-soft"><?= lang('Platform.periodEnd') ?></dt><dd><?= dt($sub['current_period_end'], false) ?></dd></div>
        <div><dt class="text-xs text-ink-soft"><?= lang('Platform.maxUsers') ?></dt><dd><?= $users === null ? lang('Company.unlimited') : lang('Company.usersLeft', [$users]) ?></dd></div>
      </dl>
      <?php else: ?><p class="text-sm text-ink-soft"><?= lang('Company.noPlanHelp') ?></p><?php endif ?>
    </div>
  </div>
  <div class="card"><div class="card-header"><h2 class="card-title"><?= lang('Company.planUsage') ?></h2></div><div class="card-body space-y-3"><?= view('company/_usage', ['planStatus' => $planStatus]) ?></div></div>
  <div class="card lg:col-span-2"><div class="card-header"><h2 class="card-title"><?= lang('Platform.paymentHistory') ?></h2></div>
    <?php if ($payments === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Platform.noPayments') ?></p><?php else: ?>
    <ul class="divide-y divide-line"><?php foreach ($payments as $p): ?><li class="flex justify-between px-4 py-2.5"><span><?= dt($p['paid_at'], false) ?><span class="block text-xs text-ink-soft"><?= esc($p['reference']) ?></span></span><span class="font-semibold"><?= money($p['amount'], $p['currency']) ?></span></li><?php endforeach ?></ul><?php endif ?>
  </div>
</div>
<?= $this->endSection() ?>
