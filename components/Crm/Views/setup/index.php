<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Crm.menuSetup') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $kinds = ['open' => lang('Crm.kindsStage.open'), 'won' => lang('Crm.kindsStage.won'), 'lost' => lang('Crm.kindsStage.lost')]; $ro = ctx()->isReadOnly(); ?>
<?= view('partials/page_header', ['title' => lang('Crm.menuSetup'), 'description' => lang('Crm.setupHelp')]) ?>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= lang('Crm.projects') ?></h2><?php if (! $ro): ?><button type="button" class="btn btn-secondary btn-sm" data-open-dialog="dlg-project"><?= icon('plus', 14) ?><?= lang('Crm.newProject') ?></button><?php endif ?></div>
    <?php if ($projects === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Crm.projectsEmpty') ?></p><?php else: ?>
    <ul class="divide-y divide-line"><?php foreach ($projects as $p): ?>
      <li class="flex items-center justify-between gap-3 px-4 py-2.5"><span class="min-w-0"><span class="block font-medium"><?= esc($p['name']) ?></span><span class="block truncate text-xs text-ink-soft"><?= esc($p['code']) ?><?= $p['description'] ? ' · ' . esc($p['description']) : '' ?></span>
        <span class="block truncate text-xs <?= empty($teams[$p['id']]) ? 'text-warn' : 'text-ink-soft' ?>"><?= empty($teams[$p['id']]) ? lang('Crm.teamEmpty') : lang('Crm.teamShort', [implode(', ', array_map('esc', $teams[$p['id']]))]) ?></span></span>
        <span class="flex items-center gap-2"><?= status_badge($p['status']) ?><?php if (! $ro): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('crm/configuracion?project=' . (int) $p['id']) ?>"><?= icon('pencil', 14) ?><span class="sr-only"><?= lang('Crm.edit') ?></span></a><?php endif ?></span></li>
    <?php endforeach ?></ul><?php endif ?>
  </div>
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= lang('Crm.stages') ?></h2><?php if (! $ro): ?><button type="button" class="btn btn-secondary btn-sm" data-open-dialog="dlg-stage"><?= icon('plus', 14) ?><?= lang('Crm.newStage') ?></button><?php endif ?></div>
    <ol class="divide-y divide-line"><?php foreach ($stages as $s): ?>
      <li class="flex items-center justify-between gap-3 px-4 py-2.5"><span><span class="font-medium"><?= esc($s['name']) ?></span><span class="block text-xs text-ink-soft"><?= $kinds[$s['kind']] ?> · <?= (int) $s['probability'] ?> % · <?= lang('Crm.positionShort', [(int) $s['position']]) ?></span></span>
        <?php if (! $ro): ?><span class="flex gap-1"><a class="btn btn-ghost btn-sm" href="<?= site_url('crm/configuracion?stage=' . (int) $s['id']) ?>"><?= icon('pencil', 14) ?><span class="sr-only"><?= lang('Crm.edit') ?></span></a>
          <?= view('partials/delete_button', ['action' => site_url('crm/etapas/' . (int) $s['id'] . '/eliminar'), 'label' => lang('Crm.delete'), 'confirm' => lang('Crm.confirmDeleteStage')]) ?></span><?php endif ?></li>
    <?php endforeach ?></ol>
  </div>
  <form method="post" action="<?= site_url('crm/seguimiento/ajustes') ?>" class="card lg:col-span-2">
    <?= csrf_field() ?>
    <div class="card-header"><h2 class="card-title"><?= lang('Crm.followUpRules') ?></h2><?php if (! $ro): ?><button class="btn btn-secondary btn-sm" type="submit"><?= lang('Crm.saveChanges') ?></button><?php endif ?></div>
    <div class="card-body grid gap-4 sm:grid-cols-2">
      <?= view('partials/field', ['name' => 'stale_days', 'label' => lang('Crm.staleDays'), 'type' => 'number', 'value' => $followUp['stale_days'], 'required' => true, 'hint' => lang('Crm.staleDaysHint'), 'attrs' => ['min' => 1, 'max' => 90]]) ?>
      <?= view('partials/field', ['name' => 'escalate_days', 'label' => lang('Crm.escalateDays'), 'type' => 'number', 'value' => $followUp['escalate_days'], 'required' => true, 'hint' => lang('Crm.escalateDaysHint'), 'attrs' => ['min' => 2, 'max' => 180]]) ?>
    </div>
  </form>
</div>
<?php if (! $ro): ?>
<?php $p = $editProject; ?>
<?= view('partials/drawer_start', ['id' => 'dlg-project', 'title' => $p ? lang('Crm.editProject') : lang('Crm.newProject'), 'open' => (bool) $p, 'closeTo' => site_url('crm/configuracion')]) ?>
<form method="post" action="<?= site_url($p ? 'crm/proyectos/' . (int) $p['id'] : 'crm/proyectos') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Crm.name'), 'value' => $p['name'] ?? '', 'required' => true, 'placeholder' => lang('Crm.projectNamePh')]) ?>
    <?= view('partials/field', ['name' => 'code', 'label' => lang('Crm.code'), 'value' => $p['code'] ?? '', 'placeholder' => 'vitrina-digital', 'hint' => lang('Crm.codeHint')]) ?>
    <?= view('partials/field', ['name' => 'description', 'label' => lang('Crm.description'), 'type' => 'textarea', 'value' => $p['description'] ?? '']) ?>
    <?= view('partials/field', ['name' => 'status', 'label' => lang('Crm.status'), 'type' => 'select', 'value' => $p['status'] ?? 'active', 'options' => ['active' => lang('Crm.statusActive'), 'archived' => lang('Crm.statusArchived')]]) ?>
    <?php if ($p): ?>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.team') ?></legend>
      <p class="form-section-hint"><?= lang('Crm.teamHint') ?></p>
      <?php foreach ($members as $uid => $uname): ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" form="team-form" name="team[]" value="<?= (int) $uid ?>"<?= in_array((int) $uid, $editTeam, true) ? ' checked' : '' ?>> <?= esc($uname) ?></label>
      <?php endforeach ?>
      <div class="flex justify-end"><button type="submit" form="team-form" class="btn btn-secondary btn-sm"><?= lang('Crm.saveTeam') ?></button></div>
    </fieldset>
    <?php endif ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Crm.cancel') ?></button><button class="btn btn-primary" type="submit"><?= lang('Crm.saveChanges') ?></button></div>
</form>
<?php if ($p): ?><form id="team-form" method="post" action="<?= site_url('crm/proyectos/' . (int) $p['id'] . '/equipo') ?>"><?= csrf_field() ?><input type="hidden" name="team[]" value=""></form><?php endif ?>
<?= view('partials/drawer_end') ?>
<?php $s = $editStage; ?>
<?= view('partials/drawer_start', ['id' => 'dlg-stage', 'title' => $s ? lang('Crm.editStage') : lang('Crm.newStage'), 'open' => (bool) $s, 'closeTo' => site_url('crm/configuracion')]) ?>
<form method="post" action="<?= site_url($s ? 'crm/etapas/' . (int) $s['id'] : 'crm/etapas') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Crm.name'), 'value' => $s['name'] ?? '', 'required' => true, 'placeholder' => lang('Crm.stageNamePh')]) ?>
    <?= view('partials/field', ['name' => 'kind', 'label' => lang('Crm.stageKind'), 'type' => 'select', 'value' => $s['kind'] ?? 'open', 'options' => $kinds, 'hint' => lang('Crm.stageKindHint')]) ?>
    <?= view('partials/field', ['name' => 'probability', 'label' => lang('Crm.probability'), 'type' => 'number', 'value' => $s['probability'] ?? 50, 'attrs' => ['min' => 0, 'max' => 100]]) ?>
    <?= view('partials/field', ['name' => 'position', 'label' => lang('Crm.position'), 'type' => 'number', 'value' => $s['position'] ?? 100, 'hint' => lang('Crm.positionHint'), 'attrs' => ['min' => 0]]) ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Crm.cancel') ?></button><button class="btn btn-primary" type="submit"><?= lang('Crm.saveChanges') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
