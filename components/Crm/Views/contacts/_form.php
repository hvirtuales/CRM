<?php
/**
 * Contacto: identificación → datos de contacto → seguimiento → privacidad.
 *
 * @var array|null $c
 * @var array      $owners
 */
?>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionIdentity') ?></legend>
  <p class="form-section-hint"><?= lang('Crm.sectionIdentityHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'kind', 'label' => lang('Crm.kind'), 'type' => 'select', 'value' => $c['kind'] ?? 'person', 'options' => ['person' => lang('Crm.kinds.person'), 'organization' => lang('Crm.kinds.organization')]]) ?>
    <?= view('partials/field', ['name' => 'organization', 'label' => lang('Crm.organization'), 'value' => $c['organization'] ?? '', 'placeholder' => lang('Crm.organizationPh'), 'attrs' => ['maxlength' => 150]]) ?>
    <?= view('partials/field', ['name' => 'first_name', 'label' => lang('Crm.firstName'), 'value' => $c['first_name'] ?? '', 'required' => true, 'placeholder' => lang('Crm.firstNamePh'), 'attrs' => ['maxlength' => 80]]) ?>
    <?= view('partials/field', ['name' => 'last_name', 'label' => lang('Crm.lastName'), 'value' => $c['last_name'] ?? '', 'placeholder' => lang('Crm.lastNamePh'), 'attrs' => ['maxlength' => 80]]) ?>
  </div>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionReach') ?></legend>
  <p class="form-section-hint"><?= lang('Crm.sectionReachHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'email', 'label' => lang('Crm.email'), 'type' => 'email', 'value' => $c['email'] ?? '', 'placeholder' => lang('Crm.emailPh')]) ?>
    <?= view('partials/field', ['name' => 'phone', 'label' => lang('Crm.phone'), 'value' => $c['phone'] ?? '', 'placeholder' => lang('Crm.phonePh')]) ?>
    <?= view('partials/field', ['name' => 'city', 'label' => lang('Crm.city'), 'value' => $c['city'] ?? '', 'placeholder' => lang('Crm.cityPh')]) ?>
    <?= view('partials/field', ['name' => 'owner_user_id', 'label' => lang('Crm.owner'), 'type' => 'select', 'value' => $c['owner_user_id'] ?? ctx()->userId(), 'options' => $owners]) ?>
  </div>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionNotes') ?></legend>
  <?= view('partials/field', ['name' => 'notes', 'label' => lang('Crm.notes'), 'type' => 'textarea', 'value' => $c['notes'] ?? '', 'placeholder' => lang('Crm.notesPh')]) ?>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionPrivacy') ?></legend>
  <p class="form-section-hint"><?= lang('Crm.sectionPrivacyHint') ?></p>
  <?= view('partials/toggle', ['name' => 'consent_marketing', 'label' => lang('Crm.consentLabel'), 'description' => lang('Crm.consentHint'), 'checked' => (int) ($c['consent_marketing'] ?? 0) === 1]) ?>
</fieldset>
