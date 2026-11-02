<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.support') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Company.supportTitle'), 'description' => lang('Company.supportHelp')]) ?>
<?php if ($grants === []): ?><?= view('partials/empty', ['message' => lang('Company.supportEmpty')]) ?><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Company.requestedBy') ?></th><th><?= lang('Core.reason') ?></th><th><?= lang('Platform.hours') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"><?= lang('Core.actions') ?></th></tr></thead>
  <tbody><?php foreach ($grants as $g): $expired = $g['status'] === 'approved' && strtotime((string) $g['expires_at']) < time(); ?>
    <tr>
      <td><span class="font-medium"><?= esc($g['user_name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($g['user_email']) ?> · <?= dt($g['created_at']) ?></span></td>
      <td class="max-w-sm"><?= esc($g['reason']) ?><?php if ($g['ticket_ref']): ?><span class="block text-xs text-ink-soft"><?= esc($g['ticket_ref']) ?></span><?php endif ?></td>
      <td><?= (int) $g['hours'] ?></td>
      <td><?= status_badge($expired ? 'expired' : $g['status']) ?><?php if ($g['status'] === 'approved' && ! $expired): ?><span class="block text-xs text-ink-soft"><?= lang('Core.supportUntil', [dt($g['expires_at'])]) ?></span><?php endif ?></td>
      <td class="num"><div class="flex justify-end gap-1">
        <?php if ($g['status'] === 'pending'): ?>
          <?= view('partials/delete_button', ['action' => site_url('negocio/soporte/' . (int) $g['id']), 'label' => lang('Core.approve'), 'fields' => ['decision' => 'approve'], 'variant' => 'secondary', 'confirm' => lang('Company.confirmApproveSupport', [(int) $g['hours']])]) ?>
          <?= view('partials/delete_button', ['action' => site_url('negocio/soporte/' . (int) $g['id']), 'label' => lang('Core.reject'), 'fields' => ['decision' => 'reject']]) ?>
        <?php elseif ($g['status'] === 'approved' && ! $expired): ?>
          <?= view('partials/delete_button', ['action' => site_url('negocio/soporte/' . (int) $g['id']), 'label' => lang('Core.revoke'), 'fields' => ['decision' => 'revoke'], 'confirm' => lang('Company.confirmRevokeSupport')]) ?>
        <?php endif ?>
      </div></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>
<?= $this->endSection() ?>
