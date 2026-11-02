<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.plans') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $canEdit = auth_can('platform.plans.edit'); $errors = session('errors') ?? []; $isNew = service('request')->getGet('new') === '1'; ?>
<?= view('partials/page_header', [
    'title' => lang('Nav.plans'), 'description' => lang('Platform.plansHelp'),
    'actions' => $canEdit ? '<a class="btn btn-primary" href="' . site_url('plataforma/planes?new=1') . '">' . icon('plus') . esc(lang('Platform.newPlan')) . '</a>' : '',
]) ?>
<?php if ($plans === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.plansEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.name') ?></th><th><?= lang('Platform.target') ?></th><th class="num"><?= lang('Core.price') ?></th><th><?= lang('Platform.services') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"><?= lang('Core.actions') ?></th></tr></thead>
  <tbody>
  <?php foreach ($plans as $p): ?>
    <tr>
      <td><span class="font-medium"><?= esc($p['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($p['code']) ?><?= (int) $p['trial_days'] > 0 ? ' · ' . lang('Platform.trialDaysShort', [(int) $p['trial_days']]) : '' ?></span></td>
      <td><?= lang('Platform.targets.' . $p['target']) ?></td>
      <td class="num"><?= money($p['price'], $p['currency']) ?><span class="block text-xs text-ink-soft"><?= lang('Platform.periods.' . $p['billing_period']) ?></span></td>
      <td class="text-xs">
        <?php foreach ($groups as $g): $on = ! $g['flag'] || (int) ($p[$g['flag']] ?? 1) === 1; ?>
          <span class="mr-1 inline-flex items-center gap-1 <?= $on ? '' : 'text-ink-soft line-through' ?>"><span class="dot <?= $on ? 'bg-ok' : 'bg-ink-soft' ?>" aria-hidden="true"></span><?= esc($g['name']) ?></span>
        <?php endforeach ?>
      </td>
      <td><?= status_badge((int) $p['is_active'] === 1 ? 'active' : 'disabled') ?><?= (int) $p['is_public'] === 1 ? '' : ' <span class="text-xs text-ink-soft">' . lang('Platform.private') . '</span>' ?></td>
      <td class="num"><?php if ($canEdit): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('plataforma/planes?edit=' . (int) $p['id']) ?>"><?= icon('pencil', 14) ?><?= lang('Core.edit') ?></a><?php endif ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>

<?php if ($canEdit && ($edit || $isNew)): $p = $edit ?? ['code' => '', 'name' => '', 'description' => '', 'target' => 'company', 'price' => '0', 'currency' => 'COP', 'billing_period' => 'monthly', 'trial_days' => 0, 'max_users' => 0, 'is_active' => 1, 'is_public' => 1]; ?>
<?= view('partials/drawer_start', ['id' => 'dlg-plan', 'title' => $edit ? lang('Platform.editPlan') : lang('Platform.newPlan'), 'wide' => true, 'open' => true, 'closeTo' => site_url('plataforma/planes')]) ?>
<form method="post" action="<?= site_url($edit ? 'plataforma/planes/' . (int) $edit['id'] : 'plataforma/planes') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionPlanData') ?></legend>
      <p class="form-section-hint"><?= lang('Platform.sectionPlanDataHint') ?></p>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= view('partials/field', ['name' => 'code', 'label' => lang('Platform.planCode'), 'value' => $p['code'], 'required' => true, 'placeholder' => 'profesional', 'hint' => lang('Platform.planCodeHint'), 'attrs' => $edit ? ['readonly' => 'readonly'] : ['maxlength' => 40]]) ?>
        <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'value' => $p['name'], 'required' => true, 'placeholder' => lang('Platform.planNamePh')]) ?>
        <?= view('partials/field', ['name' => 'description', 'label' => lang('Core.description'), 'type' => 'textarea', 'value' => $p['description'], 'class' => 'sm:col-span-2']) ?>
        <?= view('partials/field', ['name' => 'target', 'label' => lang('Platform.target'), 'type' => 'select', 'value' => $p['target'], 'options' => ['company' => lang('Platform.targets.company'), 'tenant' => lang('Platform.targets.tenant')]]) ?>
        <?= view('partials/field', ['name' => 'billing_period', 'label' => lang('Platform.period'), 'type' => 'select', 'value' => $p['billing_period'], 'options' => ['monthly' => lang('Platform.periods.monthly'), 'yearly' => lang('Platform.periods.yearly')]]) ?>
        <?= view('partials/field', ['name' => 'price', 'label' => lang('Core.price'), 'type' => 'number', 'value' => $p['price'], 'required' => true, 'attrs' => ['step' => '0.01', 'min' => '0']]) ?>
        <?= view('partials/field', ['name' => 'currency', 'label' => lang('Core.currency'), 'value' => $p['currency'], 'required' => true, 'hint' => lang('Platform.currencyHint'), 'attrs' => ['maxlength' => 3]]) ?>
        <?= view('partials/field', ['name' => 'trial_days', 'label' => lang('Platform.trialDays'), 'type' => 'number', 'value' => $p['trial_days'], 'hint' => lang('Platform.zeroNoTrial'), 'attrs' => ['min' => 0]]) ?>
        <?= view('partials/field', ['name' => 'max_users', 'label' => lang('Platform.maxUsers'), 'type' => 'number', 'value' => $p['max_users'], 'hint' => lang('Platform.zeroUnlimited'), 'attrs' => ['min' => 0]]) ?>
      </div>
    </fieldset>
    <?php foreach ($groups as $g): ?>
      <fieldset class="form-section"><legend class="form-section-title"><?= esc($g['name']) ?></legend>
        <p class="form-section-hint"><?= lang('Platform.componentBoxHint') ?></p>
        <?php if ($g['flag']): ?><?= view('partials/toggle', ['name' => $g['flag'], 'label' => lang('Platform.includeService', [$g['name']]), 'description' => lang('Platform.includeServiceHint'), 'checked' => (int) ($p[$g['flag']] ?? 1) === 1]) ?><?php endif ?>
        <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($g['limits'] as $l): ?>
          <?= view('partials/field', ['name' => $l['column'], 'label' => isset($l['label_key']) ? lang($l['label_key']) : $l['label'], 'type' => 'number', 'value' => $p[$l['column']] ?? 0, 'hint' => lang('Platform.zeroUnlimited'), 'attrs' => ['min' => 0]]) ?>
        <?php endforeach ?>
        </div>
      </fieldset>
    <?php endforeach ?>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionPublication') ?></legend>
      <?= view('partials/toggle', ['name' => 'is_active', 'label' => lang('Platform.planActive'), 'description' => lang('Platform.planActiveHint'), 'checked' => (int) $p['is_active'] === 1]) ?>
      <?= view('partials/toggle', ['name' => 'is_public', 'label' => lang('Platform.planPublic'), 'description' => lang('Platform.planPublicHint'), 'checked' => (int) $p['is_public'] === 1]) ?>
    </fieldset>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Core.saveChanges') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
