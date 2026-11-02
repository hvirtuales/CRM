<?php
/**
 * Campos de oportunidad: identificación → valor → clasificación → seguimiento.
 *
 * @var array|null $o
 * @var array|null $contactOptions null en edición (el contacto no se cambia)
 * @var array      $projectOptions
 * @var array|null $owners null = sin permiso para reasignar
 * @var string     $currency
 */
$nextAt = '';
if (! empty($o['next_action_at'])) {
    $tz = ctx()->company()['timezone'] ?? 'UTC';
    $nextAt = (new DateTimeImmutable($o['next_action_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($tz))->format('Y-m-d\TH:i');
}
?>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionOpportunity') ?></legend>
  <p class="form-section-hint"><?= lang('Crm.sectionOpportunityHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'title', 'label' => lang('Crm.title'), 'value' => $o['title'] ?? '', 'required' => true, 'placeholder' => lang('Crm.titlePh'), 'class' => 'sm:col-span-2', 'attrs' => ['maxlength' => 150]]) ?>
    <?php if ($contactOptions !== null): ?>
      <?= view('partials/field', ['name' => 'contact_id', 'label' => lang('Crm.contact'), 'type' => 'select', 'options' => $contactOptions, 'required' => true, 'class' => 'sm:col-span-2']) ?>
    <?php endif ?>
    <?= view('partials/field', ['name' => 'amount', 'label' => lang('Crm.amount'), 'type' => 'number', 'value' => $o['amount'] ?? '0', 'attrs' => ['step' => '0.01', 'min' => '0']]) ?>
    <?= view('partials/field', ['name' => 'currency', 'label' => lang('Crm.currency'), 'value' => $o['currency'] ?? $currency, 'attrs' => ['maxlength' => 3]]) ?>
  </div>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionClassification') ?></legend>
  <p class="form-section-hint"><?= lang('Crm.sectionClassificationHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'project_id', 'label' => lang('Crm.project'), 'type' => 'select', 'value' => $o['project_id'] ?? '', 'options' => $projectOptions]) ?>
    <?php if ($owners !== null): ?>
      <?= view('partials/field', ['name' => 'owner_user_id', 'label' => lang('Crm.owner'), 'type' => 'select', 'value' => $o['owner_user_id'] ?? ctx()->userId(), 'options' => $owners, 'hint' => lang('Crm.ownerHint')]) ?>
    <?php endif ?>
    <?= view('partials/field', ['name' => 'expected_close_date', 'label' => lang('Crm.expectedClose'), 'type' => 'date', 'value' => $o['expected_close_date'] ?? '']) ?>
  </div>
</fieldset>
<fieldset class="form-section"><legend class="form-section-title"><?= lang('Crm.sectionFollowUp') ?></legend>
  <p class="form-section-hint"><?= lang('Crm.sectionFollowUpHint') ?></p>
  <div class="grid gap-4 sm:grid-cols-2">
    <?= view('partials/field', ['name' => 'next_action', 'label' => lang('Crm.nextAction'), 'value' => $o['next_action'] ?? '', 'placeholder' => lang('Crm.nextActionPh'), 'attrs' => ['maxlength' => 190]]) ?>
    <?= view('partials/field', ['name' => 'next_action_at', 'label' => lang('Crm.nextActionAt'), 'type' => 'datetime-local', 'value' => $nextAt, 'hint' => lang('Crm.timezoneHint', [ctx()->company()['timezone'] ?? 'UTC'])]) ?>
  </div>
</fieldset>
