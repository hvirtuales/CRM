<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.settings') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<form method="post" action="<?= site_url('negocio/configuracion') ?>">
<?= csrf_field() ?>
<?= view('partials/page_header', ['title' => lang('Nav.settings'), 'description' => lang('Company.settingsHelp'), 'actions' => '<button type="submit" class="btn btn-primary">' . esc(lang('Core.saveChanges')) . '</button>']) ?>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card"><div class="card-header"><h2 class="card-title"><?= lang('Platform.sectionIdentity') ?></h2></div>
    <div class="card-body space-y-4">
      <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'value' => $company['name'], 'required' => true]) ?>
      <?= view('partials/field', ['name' => 'tax_id', 'label' => lang('Core.taxId'), 'value' => $company['tax_id'], 'placeholder' => lang('Core.taxIdPh')]) ?>
      <?= view('partials/field', ['name' => 'email', 'label' => lang('Core.email'), 'type' => 'email', 'value' => $company['email'], 'placeholder' => lang('Core.emailPh')]) ?>
      <?= view('partials/field', ['name' => 'phone', 'label' => lang('Core.phone'), 'value' => $company['phone'], 'placeholder' => lang('Core.phonePh')]) ?>
    </div></div>
  <div class="card"><div class="card-header"><h2 class="card-title"><?= lang('Platform.sectionRegional') ?></h2></div>
    <div class="card-body space-y-4">
      <?= view('partials/field', ['name' => 'timezone', 'label' => lang('Core.timezone'), 'value' => $company['timezone'], 'required' => true, 'hint' => lang('Company.timezoneHint')]) ?>
      <?= view('partials/field', ['name' => 'currency', 'label' => lang('Core.currency'), 'value' => $company['currency'], 'required' => true, 'hint' => lang('Platform.currencyHint'), 'attrs' => ['maxlength' => 3]]) ?>
      <?= view('partials/field', ['name' => 'locale', 'label' => lang('Profile.language'), 'type' => 'select', 'value' => $company['locale'], 'options' => ['es' => 'Español', 'en' => 'English']]) ?>
      <div class="field"><span class="label"><?= lang('Company.publicId') ?></span><p class="input flex items-center bg-muted font-mono text-xs"><?= esc($company['public_id']) ?></p><p class="hint"><?= lang('Company.publicIdHint') ?></p></div>
    </div></div>
</div>
</form>
<?= $this->endSection() ?>
