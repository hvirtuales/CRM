<?php /** Campos de alta de negocio (compartidos por plataforma y distribuidor). @var array $planOptions */ ?>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionIdentity') ?></legend>
  <p class="form-section-hint"><?= lang('Platform.companyIdentityHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'required' => true, 'placeholder' => lang('Platform.companyNamePh'), 'class' => 'sm:col-span-2', 'attrs' => ['maxlength' => 150]]) ?>
    <?= view('partials/field', ['name' => 'tax_id', 'label' => lang('Core.taxId'), 'placeholder' => lang('Core.taxIdPh')]) ?>
    <?= view('partials/field', ['name' => 'email', 'label' => lang('Core.email'), 'type' => 'email', 'placeholder' => lang('Core.emailPh')]) ?>
    <?= view('partials/field', ['name' => 'phone', 'label' => lang('Core.phone'), 'placeholder' => lang('Core.phonePh')]) ?>
  </div>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionRegional') ?></legend>
  <p class="form-section-hint"><?= lang('Platform.sectionRegionalHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-3">
    <?= view('partials/field', ['name' => 'timezone', 'label' => lang('Core.timezone'), 'value' => 'America/Bogota', 'required' => true]) ?>
    <?= view('partials/field', ['name' => 'currency', 'label' => lang('Core.currency'), 'value' => 'COP', 'required' => true, 'attrs' => ['maxlength' => 3, 'pattern' => '[A-Za-z]{3}']]) ?>
    <?= view('partials/field', ['name' => 'locale', 'label' => lang('Profile.language'), 'type' => 'select', 'value' => 'es', 'options' => ['es' => 'Español', 'en' => 'English']]) ?>
  </div>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Core.plan') ?></legend>
  <p class="form-section-hint"><?= lang('Platform.companyPlanHint') ?></p>
  <?= view('partials/field', ['name' => 'plan_id', 'label' => lang('Core.plan'), 'type' => 'select', 'options' => $planOptions]) ?>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Platform.sectionOwner') ?></legend>
  <p class="form-section-hint"><?= lang('Platform.companyOwnerHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'owner_name', 'label' => lang('Core.name'), 'required' => true, 'placeholder' => lang('Core.personNamePh')]) ?>
    <?= view('partials/field', ['name' => 'owner_email', 'label' => lang('Core.email'), 'type' => 'email', 'required' => true, 'placeholder' => lang('Core.emailPh')]) ?>
  </div>
</fieldset>
