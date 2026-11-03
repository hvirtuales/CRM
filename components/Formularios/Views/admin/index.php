<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Form.menu') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$canCreate = auth_can('form.create') && ($quota === null || $quota > 0); $canEdit = auth_can('form.edit') && ! ctx()->isReadOnly();
$errors = session('errors') ?? [];
$projectOptions = ['' => lang('Form.noProject')];
foreach ($projects as $p) { $projectOptions[$p['id']] = $p['name']; }
?>
<?= view('partials/page_header', ['title' => lang('Form.menu'), 'description' => lang('Form.help'),
    'actions' => $canCreate ? '<a class="btn btn-primary" href="' . site_url('formularios?new=1') . '">' . icon('plus') . esc(lang('Form.new')) . '</a>' : '']) ?>
<?php if ($quota !== null): ?><div class="notice mb-3"><?= lang('Form.quotaLeft', [$quota]) ?></div><?php endif ?>
<?php if ($forms === []): ?>
  <?= view('partials/empty', ['message' => lang('Form.empty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Form.name') ?></th><th><?= lang('Form.status') ?></th><th class="num"><?= lang('Form.submissions') ?></th><th><?= lang('Form.publicLink') ?></th><th class="num"><?= lang('Form.actions') ?></th></tr></thead>
  <tbody>
  <?php foreach ($forms as $f): $url = site_url('form-publico/' . $f['public_id']); ?>
    <tr>
      <td><span class="font-medium"><?= esc($f['name']) ?></span><span class="block text-xs text-ink-soft"><?= lang('Form.versionShort', [(int) $f['version']]) ?> · <?= dt($f['updated_at']) ?></span></td>
      <td><?= status_badge($f['status'], lang('Form.statuses.' . $f['status'])) ?></td>
      <td class="num"><a class="hover:text-accent" href="<?= site_url('formularios/' . (int) $f['id'] . '/respuestas') ?>"><?= num($f['submissions_count']) ?></a></td>
      <td><?php if ($f['status'] === 'published'): ?>
        <div class="flex items-center gap-1"><a class="btn btn-ghost btn-sm" href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener"><?= icon('external-link', 14) ?><?= lang('Form.open') ?></a>
        <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= esc($url, 'attr') ?>" data-copied="<?= esc(lang('Form.copied'), 'attr') ?>"><?= icon('copy', 14) ?><?= lang('Form.copy') ?></button></div>
      <?php else: ?><span class="text-xs text-ink-soft"><?= lang('Form.notPublished') ?></span><?php endif ?></td>
      <td class="num"><div class="flex justify-end gap-1">
        <?php if ($canEdit): ?><a class="btn btn-ghost btn-sm" href="<?= site_url('formularios?edit=' . (int) $f['id']) ?>"><?= icon('pencil', 14) ?><?= lang('Form.edit') ?></a><?php endif ?>
        <?php if ($canEdit): ?><?= view('partials/delete_button', ['action' => site_url('formularios/' . (int) $f['id'] . '/publicar'), 'label' => $f['status'] === 'published' ? lang('Form.republish') : lang('Form.publish'), 'variant' => 'secondary', 'confirm' => lang('Form.confirmPublish')]) ?><?php endif ?>
        <?php if ($canEdit && $f['status'] === 'published'): ?><?= view('partials/delete_button', ['action' => site_url('formularios/' . (int) $f['id'] . '/cerrar'), 'label' => lang('Form.close'), 'confirm' => lang('Form.confirmClose')]) ?><?php endif ?>
      </div></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>

<?php if (($edit && $canEdit) || ($isNew && $canCreate)):
    $f = $edit ?? ['name' => '', 'description' => '', 'project_id' => '', 'fields' => json_encode([
        ['key' => 'nombre', 'label' => lang('Form.defaultName'), 'type' => 'text', 'required' => true, 'maps_to' => 'first_name', 'options' => [], 'placeholder' => ''],
        ['key' => 'correo', 'label' => lang('Form.defaultEmail'), 'type' => 'email', 'required' => true, 'maps_to' => 'email', 'options' => [], 'placeholder' => ''],
        ['key' => 'telefono', 'label' => lang('Form.defaultPhone'), 'type' => 'phone', 'required' => false, 'maps_to' => 'phone', 'options' => [], 'placeholder' => ''],
        ['key' => 'mensaje', 'label' => lang('Form.defaultMessage'), 'type' => 'textarea', 'required' => false, 'maps_to' => 'notes', 'options' => [], 'placeholder' => ''],
    ]), 'consent_text' => lang('Form.defaultConsent'), 'success_message' => '', 'embed_domains' => null, 'create_opportunity' => 1, 'send_ack' => 1];
    $fieldsJson = old('fields_json') ?? $f['fields'];
    $domains = implode("\n", array_map(static fn ($d) => preg_replace('#^https://#', '', $d), json_decode((string) $f['embed_domains'], true) ?: []));
?>
<?= view('partials/drawer_start', ['id' => 'dlg-form', 'title' => $edit ? lang('Form.editTitle', [$edit['name']]) : lang('Form.new'), 'wide' => true, 'open' => true, 'closeTo' => site_url('formularios')]) ?>
<form method="post" action="<?= site_url($edit ? 'formularios/' . (int) $edit['id'] : 'formularios') ?>" class="flex min-h-0 flex-1 flex-col" data-form-builder>
  <?= csrf_field() ?>
  <input type="hidden" name="fields_json" value="<?= esc($fieldsJson, 'attr') ?>" data-fields-json>
  <div class="drawer-body space-y-4">
    <?php if ($edit && $edit['status'] === 'published'): ?><div class="notice"><?= lang('Form.draftNotice') ?></div><?php endif ?>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Form.sectionIdentity') ?></legend>
      <p class="form-section-hint"><?= lang('Form.sectionIdentityHint') ?></p>
      <div class="grid gap-4 sm:grid-cols-2">
        <?= view('partials/field', ['name' => 'name', 'label' => lang('Form.name'), 'value' => $f['name'], 'required' => true, 'placeholder' => lang('Form.namePh'), 'attrs' => ['maxlength' => 120]]) ?>
        <?= view('partials/field', ['name' => 'project_id', 'label' => lang('Form.project'), 'type' => 'select', 'value' => $f['project_id'], 'options' => $projectOptions, 'hint' => lang('Form.projectHint')]) ?>
        <?= view('partials/field', ['name' => 'description', 'label' => lang('Form.description'), 'type' => 'textarea', 'value' => $f['description'], 'placeholder' => lang('Form.descriptionPh'), 'class' => 'sm:col-span-2']) ?>
      </div>
    </fieldset>

    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Form.sectionFields') ?></legend>
      <p class="form-section-hint"><?= lang('Form.sectionFieldsHint') ?></p>
      <?= field_error('fields') ?>
      <div class="space-y-2" data-field-list></div>
      <button type="button" class="btn btn-secondary btn-sm" data-add-field><?= icon('plus', 14) ?><?= lang('Form.addField') ?></button>
      <template data-field-template>
        <div class="rounded-xl border border-line p-3" data-field-row>
          <div class="grid gap-2 sm:grid-cols-[1fr_9rem_10rem_auto]">
            <input class="input" data-k="label" maxlength="120" placeholder="<?= esc(lang('Form.fieldLabelPh'), 'attr') ?>" aria-label="<?= esc(lang('Form.fieldLabel'), 'attr') ?>">
            <select class="select" data-k="type" aria-label="<?= esc(lang('Form.fieldType'), 'attr') ?>"><?php foreach ($types as $t): ?><option value="<?= $t ?>"><?= lang('Form.types.' . $t) ?></option><?php endforeach ?></select>
            <select class="select" data-k="maps_to" aria-label="<?= esc(lang('Form.fieldMap'), 'attr') ?>"><?php foreach ($mappings as $m): ?><option value="<?= $m ?>"><?= lang('Form.maps.' . $m) ?></option><?php endforeach ?></select>
            <div class="flex items-center gap-1">
              <button type="button" class="btn btn-ghost btn-sm" data-up aria-label="<?= esc(lang('Form.moveUp'), 'attr') ?>"><?= icon('arrow-up', 14) ?></button>
              <button type="button" class="btn btn-ghost btn-sm" data-down aria-label="<?= esc(lang('Form.moveDown'), 'attr') ?>"><?= icon('arrow-down', 14) ?></button>
              <button type="button" class="btn btn-ghost btn-sm text-danger" data-remove aria-label="<?= esc(lang('Form.removeField'), 'attr') ?>"><?= icon('trash-2', 14) ?></button>
            </div>
          </div>
          <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]">
            <input class="input" data-k="options" placeholder="<?= esc(lang('Form.optionsPh'), 'attr') ?>" aria-label="<?= esc(lang('Form.options'), 'attr') ?>">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" data-k="required"> <?= lang('Form.required') ?></label>
          </div>
          <input type="hidden" data-k="key">
        </div>
      </template>
    </fieldset>

    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Form.sectionPrivacy') ?></legend>
      <p class="form-section-hint"><?= lang('Form.sectionPrivacyHint') ?></p>
      <?= view('partials/field', ['name' => 'consent_text', 'label' => lang('Form.consentText'), 'type' => 'textarea', 'value' => $f['consent_text'], 'hint' => lang('Form.consentTextHint')]) ?>
    </fieldset>

    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Form.sectionPublication') ?></legend>
      <p class="form-section-hint"><?= lang('Form.sectionPublicationHint') ?></p>
      <?= view('partials/field', ['name' => 'success_message', 'label' => lang('Form.successMessage'), 'value' => $f['success_message'], 'placeholder' => lang('Form.thanks'), 'attrs' => ['maxlength' => 255]]) ?>
      <?= view('partials/field', ['name' => 'embed_domains', 'label' => lang('Form.embedDomains'), 'type' => 'textarea', 'value' => $domains, 'placeholder' => "www.herramientasvirtuales.co\n*.vitrinadigital.co", 'hint' => lang('Form.embedDomainsHint')]) ?>
      <?= view('partials/toggle', ['name' => 'send_ack', 'label' => lang('Form.sendAck'), 'description' => lang('Form.sendAckHint'), 'checked' => (int) ($f['send_ack'] ?? 1) === 1]) ?>
      <?= view('partials/toggle', ['name' => 'create_opportunity', 'label' => lang('Form.createOpportunity'), 'description' => lang('Form.createOpportunityHint'), 'checked' => (int) $f['create_opportunity'] === 1]) ?>
    </fieldset>
  </div>
  <div class="drawer-foot">
    <?php if ($edit && auth_can('form.delete')): ?><button type="submit" form="form-archive" class="btn btn-danger"><?= lang('Form.archive') ?></button><?php endif ?>
    <span class="flex-1"></span>
    <button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Form.cancel') ?></button>
    <button type="submit" class="btn btn-primary"><?= lang('Form.saveDraft') ?></button>
  </div>
</form>
<?php if ($edit && auth_can('form.delete')): ?>
<form id="form-archive" method="post" action="<?= site_url('formularios/' . (int) $edit['id'] . '/archivar') ?>" data-confirm="<?= esc(lang('Form.confirmArchive'), 'attr') ?>"><?= csrf_field() ?></form>
<?php endif ?>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= base_url('components/form/js/builder.js') ?>"></script><?= $this->endSection() ?>
