<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.components') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $canEdit = auth_can('platform.components.edit'); ?>
<?= view('partials/page_header', ['title' => lang('Nav.components'), 'description' => lang('Platform.componentsHelp')]) ?>
<div class="notice-accent mb-4"><?= icon('info', 16, 'mt-0.5 text-accent') ?><span><?= lang('Platform.componentsInstallNote') ?></span></div>
<?php if ($components === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.componentsEmpty')]) ?>
<?php else: ?>
<div class="grid gap-3 lg:grid-cols-2">
<?php foreach ($components as $c): ?>
  <div class="card">
    <div class="card-header">
      <div><h2 class="card-title"><?= esc($c['name']) ?> <span class="text-xs font-normal text-ink-soft">v<?= esc($c['version']) ?> · <?= esc($c['code']) ?></span></h2></div>
      <?= status_badge($c['enabled'] ? 'enabled' : 'disabled') ?>
    </div>
    <div class="card-body space-y-2 text-sm">
      <p class="text-ink-soft"><?= esc($c['description']) ?></p>
      <p class="text-xs text-ink-soft"><?= lang('Platform.componentMeta', [lang('Core.scope.' . $c['scope']), $c['permissions'], $c['depends'] ? implode(', ', $c['depends']) : '—']) ?></p>
      <?php if ($c['pendingSync']): ?><p class="text-xs text-warn"><?= lang('Platform.componentPendingSync') ?></p><?php endif ?>
      <?php if ($c['tampered']): ?><p class="text-xs text-danger"><?= lang('Platform.componentTampered') ?></p><?php endif ?>
      <?php if ($canEdit && $c['installed']): ?>
        <div class="pt-1"><?= view('partials/delete_button', $c['enabled']
            ? ['action' => site_url('plataforma/componentes/' . $c['code'] . '/estado'), 'label' => lang('Core.disable'), 'fields' => ['enabled' => '0'], 'confirm' => lang('Platform.confirmDisableComponent')]
            : ['action' => site_url('plataforma/componentes/' . $c['code'] . '/estado'), 'label' => lang('Core.enable'), 'fields' => ['enabled' => '1'], 'variant' => 'secondary']) ?></div>
      <?php endif ?>
    </div>
  </div>
<?php endforeach ?>
</div>
<?php endif ?>
<?= $this->endSection() ?>
