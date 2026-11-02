<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.branding') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $swatches = ['#D80023' => 'HV', '#1D4ED8' => lang('Tenant.colorBlue'), '#047857' => lang('Tenant.colorGreen'), '#7C3AED' => lang('Tenant.colorPurple'), '#EA580C' => lang('Tenant.colorOrange'), '#0E7490' => lang('Tenant.colorTeal'), '#BE185D' => lang('Tenant.colorPink'), '#334155' => lang('Tenant.colorGraphite')]; ?>
<form method="post" action="<?= site_url('distribuidor/marca') ?>">
<?= csrf_field() ?>
<?= view('partials/page_header', ['title' => lang('Nav.branding'), 'description' => lang('Tenant.brandingHelp'), 'actions' => '<button type="submit" class="btn btn-primary">' . esc(lang('Core.saveChanges')) . '</button>']) ?>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card"><div class="card-header"><h2 class="card-title"><?= lang('Tenant.identity') ?></h2></div>
    <div class="card-body space-y-4">
      <?= view('partials/field', ['name' => 'brand_name', 'label' => lang('Tenant.brandName'), 'value' => $tenant['brand_name'], 'placeholder' => $tenant['name'], 'hint' => lang('Tenant.brandNameHint'), 'attrs' => ['maxlength' => 120]]) ?>
      <?= view('partials/field', ['name' => 'brand_color', 'label' => lang('Tenant.brandColor'), 'value' => $tenant['brand_color'] ?? '#D80023', 'placeholder' => '#D80023', 'hint' => lang('Tenant.brandColorHint'), 'attrs' => ['pattern' => '#[0-9A-Fa-f]{6}', 'maxlength' => 7]]) ?>
      <div class="flex flex-wrap gap-2" role="list">
        <?php foreach ($swatches as $hex => $label): ?>
          <span role="listitem" class="inline-flex items-center gap-1.5 rounded-xl border border-line px-2 py-1 text-xs"><span class="size-4 rounded-md border border-line" style="background:<?= $hex /* constante validada */ ?>"></span><?= esc($label) ?> <code><?= $hex ?></code></span>
        <?php endforeach ?>
      </div>
    </div>
  </div>
  <div class="card"><div class="card-header"><h2 class="card-title"><?= lang('Tenant.preview') ?></h2></div>
    <div class="card-body space-y-3">
      <p class="text-sm text-ink-soft"><?= lang('Tenant.previewHelp') ?></p>
      <div class="flex flex-wrap gap-2"><span class="btn btn-primary"><?= lang('Tenant.previewPrimary') ?></span><span class="btn btn-secondary"><?= lang('Tenant.previewSecondary') ?></span><span class="nav-item" aria-current="page"><?= icon('layout-dashboard', 18) ?><?= lang('Nav.portfolio') ?></span></div>
    </div>
  </div>
</div>
</form>
<?= $this->endSection() ?>
