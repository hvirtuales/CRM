<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.audit') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.audit'), 'description' => lang('Core.auditHelp')]) ?>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2">
  <input class="input max-w-xs" type="search" name="action" value="<?= esc($filters['action'], 'attr') ?>" placeholder="<?= esc(lang('Core.auditActionPh'), 'attr') ?>" aria-label="<?= esc(lang('Core.action'), 'attr') ?>">
  <input class="input w-40" type="date" name="from" value="<?= esc($filters['from'], 'attr') ?>" aria-label="<?= esc(lang('Core.from'), 'attr') ?>">
  <input class="input w-40" type="date" name="to" value="<?= esc($filters['to'], 'attr') ?>" aria-label="<?= esc(lang('Core.to'), 'attr') ?>">
  <button class="btn btn-secondary" type="submit"><?= icon('search', 14) ?><?= lang('Core.filter') ?></button>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Core.auditEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.date') ?></th><th><?= lang('Core.actor') ?></th><th><?= lang('Core.action') ?></th><th><?= lang('Core.entity') ?></th><th><?= lang('Core.origin') ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $a): ?>
    <tr>
      <td class="whitespace-nowrap"><?= dt($a['occurred_at']) ?></td>
      <td><?= esc($a['actor_name'] ?? lang('Core.system')) ?><span class="block text-xs text-ink-soft"><?= esc($a['actor_email'] ?? '') ?></span></td>
      <td><code class="text-xs"><?= esc($a['action']) ?></code><?php if ($a['data']): ?><details class="text-xs text-ink-soft"><summary class="cursor-pointer"><?= lang('Core.details') ?></summary><pre class="max-w-md overflow-x-auto whitespace-pre-wrap"><?= esc($a['data']) ?></pre></details><?php endif ?></td>
      <td class="text-xs"><?= esc(trim(($a['entity_type'] ?? '') . ' ' . ($a['entity_id'] ?? ''))) ?: '—' ?></td>
      <td class="text-xs text-ink-soft"><?= esc($a['ip'] ?? '—') ?><span class="block"><?= esc($a['request_id'] ?? '') ?></span></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>
<?= $this->endSection() ?>
