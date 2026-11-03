<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.outbox') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Nav.outbox'), 'description' => lang('Platform.outboxHelp')]) ?>
<?php if (! $configured): ?><div class="notice-accent mb-4"><?= icon('triangle-alert', 16, 'mt-0.5 text-accent') ?><span><?= lang('Platform.outboxNotConfigured') ?></span></div><?php endif ?>
<form method="get" class="mb-3 flex gap-2" data-autosubmit>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Core.statusLabel'), 'attr') ?>"><option value=""><?= lang('Core.all') ?></option>
  <?php foreach (['queued', 'sent', 'failed'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= lang('Platform.outboxStatus.' . $s) ?></option><?php endforeach ?></select>
</form>
<?php if ($rows === []): ?><?= view('partials/empty', ['message' => lang('Platform.outboxEmpty')]) ?><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.date') ?></th><th><?= lang('Platform.outboxTo') ?></th><th><?= lang('Platform.outboxSubject') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"></th></tr></thead>
  <tbody><?php foreach ($rows as $m): ?>
    <tr>
      <td class="whitespace-nowrap"><?= dt($m['created_at']) ?><span class="block text-xs text-ink-soft"><?= esc($m['kind']) ?></span></td>
      <td><?= esc($m['to_email']) ?><span class="block text-xs text-ink-soft"><?= esc($m['company_name'] ?? '') ?></span></td>
      <td class="max-w-xs truncate" title="<?= esc($m['subject'], 'attr') ?>"><?= esc($m['subject']) ?></td>
      <td><?= status_badge(['queued' => 'pending', 'sent' => 'active', 'failed' => 'rejected'][$m['status']], lang('Platform.outboxStatus.' . $m['status'])) ?>
        <span class="block text-xs text-ink-soft"><?= $m['status'] === 'sent' ? dt($m['sent_at']) : lang('Platform.outboxAttempts', [(int) $m['attempts']]) ?></span>
        <?php if ($m['last_error']): ?><span class="block max-w-xs truncate text-xs text-danger" title="<?= esc($m['last_error'], 'attr') ?>"><?= esc($m['last_error']) ?></span><?php endif ?></td>
      <td class="num"><?php if ($m['status'] === 'failed' && auth_can('platform.notifications.edit')): ?><?= view('partials/delete_button', ['action' => site_url('plataforma/correos/' . (int) $m['id'] . '/reintentar'), 'label' => lang('Platform.outboxRetry'), 'variant' => 'secondary']) ?><?php endif ?></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>
<?= $this->endSection() ?>
