<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.support') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; $companyOptions = ['' => lang('Core.choose')]; foreach ($companies as $c) { $companyOptions[$c['id']] = $c['name']; } ?>
<?= view('partials/page_header', ['title' => lang('Nav.support'), 'description' => lang('Platform.supportHelp'),
    'actions' => '<button type="button" class="btn btn-primary" data-open-dialog="dlg-req">' . icon('plus') . esc(lang('Platform.requestAccess')) . '</button>']) ?>
<div class="notice mb-4"><?= lang('Platform.supportPolicy') ?></div>
<?php if ($grants === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.supportEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Platform.company') ?></th><th><?= lang('Core.reason') ?></th><th><?= lang('Platform.hours') ?></th><th><?= lang('Core.statusLabel') ?></th><th><?= lang('Platform.expiresAt') ?></th></tr></thead>
  <tbody><?php foreach ($grants as $g): ?>
    <tr><td class="font-medium"><?= esc($g['company_name']) ?></td><td class="max-w-xs truncate" title="<?= esc($g['reason'], 'attr') ?>"><?= esc($g['reason']) ?></td><td><?= (int) $g['hours'] ?></td><td><?= status_badge($g['status'] === 'approved' && strtotime((string) $g['expires_at']) < time() ? 'expired' : $g['status']) ?></td><td><?= dt($g['expires_at']) ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<p class="mt-3 text-sm"><a class="text-accent" href="<?= site_url('contexto') ?>"><?= lang('Platform.useApprovedAccess') ?></a></p>
<?php endif ?>
<?= view('partials/drawer_start', ['id' => 'dlg-req', 'title' => lang('Platform.requestAccess'), 'open' => $errors !== []]) ?>
<form method="post" action="<?= site_url('plataforma/soporte') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'company_id', 'label' => lang('Platform.company'), 'type' => 'select', 'options' => $companyOptions, 'required' => true]) ?>
    <?= view('partials/field', ['name' => 'reason', 'label' => lang('Core.reason'), 'type' => 'textarea', 'required' => true, 'placeholder' => lang('Platform.supportReasonPh'), 'hint' => lang('Platform.supportReasonHint')]) ?>
    <?= view('partials/field', ['name' => 'ticket_ref', 'label' => lang('Platform.ticket'), 'placeholder' => 'SOP-2026-0042']) ?>
    <?= view('partials/field', ['name' => 'hours', 'label' => lang('Platform.hours'), 'type' => 'number', 'value' => 2, 'required' => true, 'hint' => lang('Platform.hoursHint', [config('Rbac')->supportMaxHours]), 'attrs' => ['min' => 1, 'max' => config('Rbac')->supportMaxHours]]) ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Platform.sendRequest') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?= $this->endSection() ?>
