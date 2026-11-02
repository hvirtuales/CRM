<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Crm.menuContacts') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$canCreate = auth_can('crm.create'); $canEdit = auth_can('crm.edit') && ! ctx()->isReadOnly(); $errors = session('errors') ?? [];
$actions = '';
if (auth_can('crm.export')) {
    $actions .= '<a class="btn btn-secondary" href="' . esc(site_url('crm/contactos/exportar') . '?' . http_build_query($filters), 'attr') . '">' . icon('download') . esc(lang('Crm.exportCsv')) . '</a>';
}
if ($canCreate && ($quota === null || $quota > 0)) {
    $actions .= '<button type="button" class="btn btn-primary" data-open-dialog="dlg-new">' . icon('plus') . esc(lang('Crm.newContact')) . '</button>';
}
?>
<?= view('partials/page_header', ['title' => lang('Crm.menuContacts'), 'description' => lang('Crm.contactsHelp'), 'actions' => $actions]) ?>
<?php if ($quota !== null): ?><div class="notice mb-3"><?= lang('Crm.contactsLeft', [$quota]) ?></div><?php endif ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="<?= esc(lang('Crm.searchContacts'), 'attr') ?>" aria-label="<?= esc(lang('Crm.search'), 'attr') ?>"></div>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Crm.status'), 'attr') ?>">
    <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>><?= lang('Crm.statusActive') ?></option>
    <option value="archived"<?= $filters['status'] === 'archived' ? ' selected' : '' ?>><?= lang('Crm.statusArchived') ?></option>
    <option value="all"<?= $filters['status'] === 'all' ? ' selected' : '' ?>><?= lang('Crm.all') ?></option>
  </select>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Crm.contactsEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Crm.name') ?></th><th><?= lang('Crm.contactData') ?></th><th><?= lang('Crm.source') ?></th><th><?= lang('Crm.consent') ?></th><th class="num"></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><span class="font-medium"><?= esc(trim($c['first_name'] . ' ' . ($c['last_name'] ?? ''))) ?></span><span class="block text-xs text-ink-soft"><?= esc($c['organization'] ?? '') ?><?= $c['city'] ? ' · ' . esc($c['city']) : '' ?></span></td>
      <td class="text-xs"><?= esc($c['email'] ?? '') ?><span class="block text-ink-soft"><?= esc($c['phone'] ?? '') ?></span></td>
      <td><?= lang('Crm.sources.' . $c['source']) ?></td>
      <td><?= (int) $c['consent_marketing'] === 1 ? status_badge('active', lang('Crm.consentYes')) : status_badge('none', lang('Crm.consentNo')) ?></td>
      <td class="num"><a class="btn btn-ghost btn-sm" href="<?= site_url('crm/contactos?edit=' . (int) $c['id']) ?>"><?= icon($canEdit ? 'pencil' : 'eye', 14) ?><?= $canEdit ? lang('Crm.edit') : lang('Crm.view') ?></a></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?= view('partials/pagination', ['page' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'total' => $pager->getTotal()]) ?>
<?php endif ?>

<?php if ($canCreate): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-new', 'title' => lang('Crm.newContact'), 'wide' => true, 'open' => $errors !== [] && ! $edit]) ?>
<form method="post" action="<?= site_url('crm/contactos') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4"><?= view('Components\Crm\Views\contacts\_form', ['c' => null, 'owners' => $owners]) ?></div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Crm.cancel') ?></button><button type="submit" class="btn btn-primary" data-busy="<?= esc(lang('Crm.saving'), 'attr') ?>"><?= lang('Crm.createContact') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>

<?php if ($edit): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-edit', 'title' => trim($edit['first_name'] . ' ' . ($edit['last_name'] ?? '')), 'wide' => true, 'open' => true, 'closeTo' => site_url('crm/contactos')]) ?>
<form method="post" action="<?= site_url('crm/contactos/' . (int) $edit['id']) ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <div class="flex flex-wrap items-center gap-2"><?= status_badge($edit['status']) ?><span class="text-xs text-ink-soft"><?= lang('Crm.sources.' . $edit['source']) ?> · <?= lang('Crm.createdOn', [dt($edit['created_at'])]) ?></span>
      <?php if ($edit['consent_at']): ?><span class="text-xs text-ink-soft"><?= lang('Crm.consentTrace', [dt($edit['consent_at']), esc($edit['consent_source'] ?? '')]) ?></span><?php endif ?></div>
    <?php if ($canEdit): ?>
      <?= view('Components\Crm\Views\contacts\_form', ['c' => $edit, 'owners' => $owners]) ?>
    <?php else: ?>
      <dl class="grid gap-3 sm:grid-cols-2 text-sm">
        <?php foreach (['organization', 'email', 'phone', 'city', 'notes'] as $f): ?><div><dt class="text-xs text-ink-soft"><?= lang('Crm.' . $f) ?></dt><dd><?= esc($edit[$f] ?? '—') ?></dd></div><?php endforeach ?>
      </dl>
    <?php endif ?>
    <div>
      <h3 class="mb-2 text-sm font-semibold"><?= lang('Crm.history') ?></h3>
      <?php if ($activities === []): ?><p class="text-sm text-ink-soft"><?= lang('Crm.noActivities') ?></p><?php else: ?>
      <ol class="divide-y divide-line rounded-xl border border-line"><?php foreach ($activities as $a): ?><li class="px-4 py-2.5 text-sm"><p><?= esc($a['body']) ?></p><p class="text-xs text-ink-soft"><?= lang('Crm.activityTypes.' . $a['type']) ?> · <?= dt($a['created_at']) ?></p></li><?php endforeach ?></ol><?php endif ?>
    </div>
  </div>
  <div class="drawer-foot">
    <?php if (auth_can('crm.delete') && ! ctx()->isReadOnly()): ?>
      <button type="submit" form="archive-form" class="btn btn-danger"><?= $edit['status'] === 'archived' ? lang('Crm.restore') : lang('Crm.archive') ?></button>
    <?php endif ?>
    <span class="flex-1"></span>
    <button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Crm.close') ?></button>
    <?php if ($canEdit): ?><button type="submit" class="btn btn-primary"><?= lang('Crm.saveChanges') ?></button><?php endif ?>
  </div>
</form>
<?php if (auth_can('crm.delete')): ?>
<form id="archive-form" method="post" action="<?= site_url('crm/contactos/' . (int) $edit['id'] . '/archivar') ?>" data-confirm="<?= esc($edit['status'] === 'archived' ? lang('Crm.confirmRestoreContact') : lang('Crm.confirmArchiveContact'), 'attr') ?>">
  <?= csrf_field() ?><?php if ($edit['status'] === 'archived'): ?><input type="hidden" name="restore" value="1"><?php endif ?>
</form>
<?php endif ?>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
