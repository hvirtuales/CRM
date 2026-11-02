<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Crm.menuPipeline') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$canCreate = auth_can('crm.create'); $errors = session('errors') ?? [];
$byStage = [];
foreach ($opps as $o) { $byStage[$o['stage_id']][] = $o; }
$stageMap = array_column($stages, null, 'id');
$projectOptions = ['' => lang('Crm.noProject')];
foreach ($projects as $p) { $projectOptions[$p['id']] = $p['name']; }
$contactOptions = ['' => lang('Crm.chooseContact')];
foreach ($contacts as $c) { $contactOptions[$c['id']] = trim($c['first_name'] . ' ' . ($c['last_name'] ?? '')) . ($c['organization'] ? ' · ' . $c['organization'] : ''); }
$qs = static fn (array $extra) => site_url('crm/oportunidades') . '?' . http_build_query(array_filter($extra + ['view' => $view, 'project' => $filters['project'], 'q' => $filters['q'], 'mine' => $filters['mine'] ? '1' : null], static fn ($v) => $v !== null && $v !== ''));
$actions = '<div class="flex rounded-xl border border-line" role="group" aria-label="' . esc(lang('Crm.viewMode'), 'attr') . '">'
    . '<a class="btn btn-sm ' . ($view === 'board' ? 'btn-active' : 'btn-ghost') . '" href="' . esc($qs(['view' => 'board']), 'attr') . '">' . icon('kanban', 14) . esc(lang('Crm.board')) . '</a>'
    . '<a class="btn btn-sm ' . ($view === 'list' ? 'btn-active' : 'btn-ghost') . '" href="' . esc($qs(['view' => 'list']), 'attr') . '">' . icon('list', 14) . esc(lang('Crm.list')) . '</a></div>';
if ($canCreate && ($quota === null || $quota > 0)) {
    $actions .= '<button type="button" class="btn btn-primary" data-open-dialog="dlg-new-opp">' . icon('plus') . esc(lang('Crm.newOpportunity')) . '</button>';
}
?>
<?= view('partials/page_header', ['title' => lang('Crm.menuPipeline'), 'description' => lang('Crm.pipelineHelp'), 'actions' => $actions]) ?>
<?php if ($quota !== null): ?><div class="notice mb-3"><?= lang('Crm.opportunitiesLeft', [$quota]) ?></div><?php endif ?>

<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <input type="hidden" name="view" value="<?= esc($view, 'attr') ?>">
  <div class="relative w-full max-w-sm"><?= icon('search', 16, 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft') ?><input class="input pl-9" type="search" name="q" value="<?= esc((string) $filters['q'], 'attr') ?>" placeholder="<?= esc(lang('Crm.searchOpportunities'), 'attr') ?>" aria-label="<?= esc(lang('Crm.search'), 'attr') ?>"></div>
  <select class="select w-56" name="project" aria-label="<?= esc(lang('Crm.project'), 'attr') ?>">
    <option value=""><?= lang('Crm.allProjects') ?></option>
    <?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>"<?= (string) $filters['project'] === (string) $p['id'] ? ' selected' : '' ?>><?= esc($p['name']) ?></option><?php endforeach ?>
  </select>
  <a class="btn btn-sm <?= $filters['mine'] ? 'btn-active' : 'btn-secondary' ?>" href="<?= esc($qs(['mine' => $filters['mine'] ? null : '1']), 'attr') ?>"><?= lang('Crm.onlyMine') ?></a>
</form>

<?php if ($view === 'board'): ?>
<div class="kanban" data-kanban data-move-url="<?= esc(site_url('crm/oportunidades/__ID__/etapa'), 'attr') ?>" data-lost-prompt="<?= esc(lang('Crm.lostReasonPrompt'), 'attr') ?>">
  <?php foreach ($stages as $s): $items = $byStage[$s['id']] ?? []; $sum = array_sum(array_map(static fn ($o) => (float) $o['amount'], $items)); ?>
    <section class="kanban-col" data-stage="<?= (int) $s['id'] ?>" data-kind="<?= esc($s['kind'], 'attr') ?>" aria-label="<?= esc($s['name'], 'attr') ?>">
      <header class="flex items-center justify-between px-1 pt-1">
        <h2 class="text-xs font-semibold uppercase tracking-wide <?= $s['kind'] === 'won' ? 'text-ok' : ($s['kind'] === 'lost' ? 'text-danger' : 'text-ink-soft') ?>"><?= esc($s['name']) ?> <span class="font-normal">(<?= count($items) ?>)</span></h2>
        <span class="text-xs text-ink-soft"><?= money($sum, $currency) ?></span>
      </header>
      <?php foreach ($items as $o): ?>
        <article class="kanban-card" draggable="true" data-id="<?= (int) $o['id'] ?>" tabindex="0">
          <a class="block font-medium hover:text-accent" href="<?= site_url('crm/oportunidades?open=' . (int) $o['id']) ?>"><?= esc($o['title']) ?></a>
          <p class="mt-0.5 text-xs text-ink-soft"><?= esc(trim($o['first_name'] . ' ' . ($o['last_name'] ?? ''))) ?><?= $o['organization'] ? ' · ' . esc($o['organization']) : '' ?></p>
          <div class="mt-2 flex items-center justify-between text-xs">
            <span class="font-semibold"><?= money($o['amount'], $o['currency']) ?></span>
            <span class="text-ink-soft"><?= esc($o['owner_name'] ?? '') ?></span>
          </div>
          <?php if ($o['next_action_at'] && $s['kind'] === 'open'): $late = strtotime($o['next_action_at']) < time(); ?>
            <p class="mt-1 flex items-center gap-1 text-xs <?= $late ? 'text-danger' : 'text-ink-soft' ?>"><?= icon('calendar', 12) ?><?= dt($o['next_action_at']) ?></p>
          <?php endif ?>
          <?php if ($o['project_name']): ?><p class="mt-1"><span class="rounded-sm bg-muted px-1.5 py-0.5 text-xs text-ink-soft"><?= esc($o['project_name']) ?></span></p><?php endif ?>
        </article>
      <?php endforeach ?>
      <?php if ($items === []): ?><p class="px-1 py-4 text-center text-xs text-ink-soft"><?= lang('Crm.dropHere') ?></p><?php endif ?>
    </section>
  <?php endforeach ?>
</div>
<p class="mt-2 text-xs text-ink-soft"><?= lang('Crm.boardHelp') ?></p>
<?php else: ?>
  <?php if ($opps === []): ?>
    <?= view('partials/empty', ['message' => lang('Crm.opportunitiesEmpty')]) ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= lang('Crm.opportunity') ?></th><th><?= lang('Crm.stage') ?></th><th class="num"><?= lang('Crm.amount') ?></th><th><?= lang('Crm.owner') ?></th><th><?= lang('Crm.nextAction') ?></th></tr></thead>
    <tbody>
    <?php foreach ($opps as $o): $st = $stageMap[$o['stage_id']] ?? null; ?>
      <tr class="row-link">
        <td><a class="font-medium hover:text-accent" href="<?= site_url('crm/oportunidades?view=list&open=' . (int) $o['id']) ?>"><?= esc($o['title']) ?></a><span class="block text-xs text-ink-soft"><?= esc(trim($o['first_name'] . ' ' . ($o['last_name'] ?? ''))) ?><?= $o['project_name'] ? ' · ' . esc($o['project_name']) : '' ?></span></td>
        <td><?= $st ? status_badge($st['kind'] === 'open' ? 'open' : $st['kind'], $st['name']) : '—' ?></td>
        <td class="num"><?= money($o['amount'], $o['currency']) ?><span class="block text-xs text-ink-soft"><?= (int) $o['probability'] ?> %</span></td>
        <td><?= esc($o['owner_name'] ?? '—') ?></td>
        <td><?= esc($o['next_action'] ?? '') ?><span class="block text-xs text-ink-soft"><?= $o['next_action_at'] ? dt($o['next_action_at']) : '' ?></span></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <p class="mt-3 text-xs text-ink-soft"><?= lang('Crm.records', [count($opps)]) ?></p>
  <?php endif ?>
<?php endif ?>

<?php if ($canCreate): ?>
<?= view('partials/drawer_start', ['id' => 'dlg-new-opp', 'title' => lang('Crm.newOpportunity'), 'wide' => true, 'open' => $errors !== [] && ! $open]) ?>
<form method="post" action="<?= site_url('crm/oportunidades') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?php if (count($contactOptions) === 1): ?><div class="notice"><?= lang('Crm.needContactFirst') ?> <a class="text-accent" href="<?= site_url('crm/contactos') ?>"><?= lang('Crm.menuContacts') ?></a></div><?php endif ?>
    <?= view('Components\Crm\Views\opportunities\_form', ['o' => null, 'contactOptions' => $contactOptions, 'projectOptions' => $projectOptions, 'owners' => $owners, 'currency' => $currency]) ?>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Crm.cancel') ?></button><button type="submit" class="btn btn-primary" data-busy="<?= esc(lang('Crm.saving'), 'attr') ?>"><?= lang('Crm.createOpportunity') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?php endif ?>

<?php if ($open): $st = $stageMap[$open['stage_id']] ?? null; ?>
<?= view('partials/drawer_start', ['id' => 'dlg-opp', 'title' => $open['title'], 'wide' => true, 'open' => true, 'closeTo' => $qs([])]) ?>
<div class="drawer-body space-y-5">
  <div class="flex flex-wrap items-center gap-2">
    <?= $st ? status_badge($st['kind'] === 'open' ? 'open' : $st['kind'], $st['name']) : '' ?>
    <span class="text-sm font-semibold"><?= money($open['amount'], $open['currency']) ?></span>
    <span class="text-xs text-ink-soft"><?= lang('Crm.probabilityShort', [(int) $open['probability']]) ?> · <?= lang('Crm.createdOn', [dt($open['created_at'])]) ?></span>
  </div>
  <?php if ($open['lost_reason']): ?><div class="notice"><?= lang('Crm.lostReason') ?>: <?= esc($open['lost_reason']) ?></div><?php endif ?>
  <?php if ($openContact): ?>
  <div class="rounded-xl border border-line p-3 text-sm">
    <p class="font-medium"><?= esc(trim($openContact['first_name'] . ' ' . ($openContact['last_name'] ?? ''))) ?><?= $openContact['organization'] ? ' · ' . esc($openContact['organization']) : '' ?></p>
    <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-soft">
      <?php if ($openContact['email']): ?><span class="inline-flex items-center gap-1"><?= icon('mail', 12) ?><?= esc($openContact['email']) ?></span><?php endif ?>
      <?php if ($openContact['phone']): ?><span class="inline-flex items-center gap-1"><?= icon('phone', 12) ?><?= esc($openContact['phone']) ?></span><?php endif ?>
      <a class="text-accent" href="<?= site_url('crm/contactos?edit=' . (int) $openContact['id']) ?>"><?= lang('Crm.viewContact') ?></a>
    </p>
  </div>
  <?php endif ?>

  <?php if ($canTouch && auth_can('crm.edit') && ! ctx()->isReadOnly()): ?>
  <div class="flex flex-wrap gap-2">
    <?php foreach ($stages as $s): if ((int) $s['id'] === (int) $open['stage_id'] || $s['kind'] === 'lost') { continue; } ?>
      <?= view('partials/delete_button', ['action' => site_url('crm/oportunidades/' . (int) $open['id'] . '/etapa'), 'label' => $s['kind'] === 'won' ? lang('Crm.markWon') : $s['name'], 'fields' => ['stage_id' => $s['id']], 'variant' => $s['kind'] === 'won' ? 'secondary' : 'ghost']) ?>
    <?php endforeach ?>
  </div>
  <?php $lost = array_values(array_filter($stages, static fn ($s) => $s['kind'] === 'lost'))[0] ?? null; if ($lost && (int) $lost['id'] !== (int) $open['stage_id']): ?>
  <details class="rounded-xl border border-line p-3">
    <summary class="cursor-pointer text-sm text-danger"><?= lang('Crm.markLost') ?></summary>
    <form method="post" action="<?= site_url('crm/oportunidades/' . (int) $open['id'] . '/etapa') ?>" class="mt-3 flex flex-wrap items-end gap-2">
      <?= csrf_field() ?><input type="hidden" name="stage_id" value="<?= (int) $lost['id'] ?>">
      <div class="flex-1"><?= view('partials/field', ['name' => 'lost_reason', 'label' => lang('Crm.lostReason'), 'required' => true, 'placeholder' => lang('Crm.lostReasonPh')]) ?></div>
      <button type="submit" class="btn btn-danger"><?= lang('Crm.markLost') ?></button>
    </form>
  </details>
  <?php endif ?>

  <details class="rounded-xl border border-line p-3"<?= $errors !== [] ? ' open' : '' ?>>
    <summary class="cursor-pointer text-sm font-medium"><?= lang('Crm.editData') ?></summary>
    <form method="post" action="<?= site_url('crm/oportunidades/' . (int) $open['id']) ?>" class="mt-3 space-y-4">
      <?= csrf_field() ?>
      <?= view('Components\Crm\Views\opportunities\_form', ['o' => $open, 'contactOptions' => null, 'projectOptions' => $projectOptions, 'owners' => auth_can('crm.manage') ? $owners : null, 'currency' => $currency]) ?>
      <div class="flex justify-end"><button class="btn btn-primary" type="submit"><?= lang('Crm.saveChanges') ?></button></div>
    </form>
  </details>

  <form method="post" action="<?= site_url('crm/oportunidades/' . (int) $open['id'] . '/actividad') ?>" class="space-y-2">
    <?= csrf_field() ?>
    <div class="flex flex-wrap items-end gap-2">
      <div class="w-40"><?= view('partials/field', ['name' => 'type', 'label' => lang('Crm.activityType'), 'type' => 'select', 'options' => array_combine(['note', 'call', 'email', 'meeting', 'whatsapp'], array_map(static fn ($t) => lang('Crm.activityTypes.' . $t), ['note', 'call', 'email', 'meeting', 'whatsapp']))]) ?></div>
      <div class="min-w-48 flex-1"><?= view('partials/field', ['name' => 'body', 'label' => lang('Crm.activityBody'), 'required' => true, 'placeholder' => lang('Crm.activityPh'), 'attrs' => ['maxlength' => 5000]]) ?></div>
      <button class="btn btn-secondary" type="submit"><?= lang('Crm.addActivity') ?></button>
    </div>
  </form>
  <?php elseif (! $canTouch): ?>
    <div class="notice"><?= lang('Crm.readOnlyNotOwner') ?></div>
  <?php endif ?>

  <div>
    <h3 class="mb-2 text-sm font-semibold"><?= lang('Crm.history') ?></h3>
    <?php if ($activities === []): ?><p class="text-sm text-ink-soft"><?= lang('Crm.noActivities') ?></p><?php else: ?>
    <ol class="divide-y divide-line rounded-xl border border-line">
      <?php foreach ($activities as $a): ?>
        <li class="px-4 py-2.5 text-sm"><p class="whitespace-pre-line"><?= esc($a['body']) ?></p><p class="mt-0.5 text-xs text-ink-soft"><?= lang('Crm.activityTypes.' . $a['type']) ?> · <?= esc($a['user_name'] ?? lang('Crm.system')) ?> · <?= dt($a['created_at']) ?></p></li>
      <?php endforeach ?>
    </ol><?php endif ?>
  </div>
</div>
<?php if (auth_can('crm.delete') && ! ctx()->isReadOnly()): ?>
<div class="drawer-foot"><?= view('partials/delete_button', ['action' => site_url('crm/oportunidades/' . (int) $open['id'] . '/archivar'), 'label' => lang('Crm.archive'), 'confirm' => lang('Crm.confirmArchiveOpportunity')]) ?><span class="flex-1"></span><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Crm.close') ?></button></div>
<?php endif ?>
<?= view('partials/drawer_end') ?>
<?php endif ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= base_url('components/crm/js/kanban.js') ?>"></script><?= $this->endSection() ?>
