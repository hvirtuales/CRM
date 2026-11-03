<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Crm.menuFollowUp') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Crm.menuFollowUp'), 'description' => lang('Crm.followUpHelp', [$settings['stale_days'], $settings['escalate_days']])]) ?>
<?php
$sections = [
    ['overdue', lang('Crm.alertOverdue'), lang('Crm.alertOverdueEmpty'), 'text-danger'],
    ['stale', lang('Crm.alertStale', [$settings['stale_days']]), lang('Crm.alertStaleEmpty'), 'text-warn'],
];
if (auth_can('crm.manage')) {
    $sections[] = ['escalated', lang('Crm.alertEscalated', [$settings['escalate_days']]), lang('Crm.alertEscalatedEmpty'), 'text-danger'];
}
$rows = compact('overdue', 'stale', 'escalated');
?>
<div class="grid gap-4 <?= count($sections) === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2' ?>">
<?php foreach ($sections as [$key, $title, $empty, $tone]): ?>
  <div class="card">
    <div class="card-header"><h2 class="card-title"><?= esc($title) ?></h2><span class="text-sm font-semibold <?= $rows[$key] ? $tone : 'text-ink-soft' ?>"><?= count($rows[$key]) ?></span></div>
    <?php if ($rows[$key] === []): ?><p class="card-body text-sm text-ink-soft"><?= esc($empty) ?></p><?php else: ?>
    <ul class="divide-y divide-line">
      <?php foreach ($rows[$key] as $o): ?>
        <li><a class="block px-4 py-2.5 hover:bg-muted" href="<?= site_url('crm/oportunidades?open=' . (int) $o['id']) ?>">
          <span class="block truncate font-medium"><?= esc($o['title']) ?></span>
          <span class="block truncate text-xs text-ink-soft"><?= esc(trim($o['first_name'] . ' ' . ($o['last_name'] ?? ''))) ?><?= $key === 'escalated' ? ' · ' . esc($o['owner_name'] ?? '') : '' ?></span>
          <span class="block text-xs <?= $tone ?>"><?= $key === 'overdue'
              ? esc(($o['next_action'] ?: lang('Crm.nextAction')) . ' · ' . dt($o['next_action_at']))
              : lang('Crm.lastActivity', [dt($o['last_activity_at'])]) ?></span>
        </a></li>
      <?php endforeach ?>
    </ul><?php endif ?>
  </div>
<?php endforeach ?>
</div>
<?= $this->endSection() ?>
