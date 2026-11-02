<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Form.submissions') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => lang('Form.submissionsOf', [$form['name']]), 'description' => lang('Form.submissionsHelp'),
    'actions' => '<a class="btn btn-secondary" href="' . site_url('formularios') . '">' . icon('chevron-left') . esc(lang('Form.back')) . '</a>']) ?>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Form.submissionsEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Form.date') ?></th><?php foreach (array_slice($fields, 0, 4) as $fd): ?><th><?= esc($fd['label']) ?></th><?php endforeach ?><th><?= lang('Form.consent') ?></th><th><?= lang('Form.crm') ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $d = json_decode((string) $r['data'], true) ?: []; ?>
    <tr>
      <td class="whitespace-nowrap"><?= dt($r['created_at']) ?><span class="block text-xs text-ink-soft">v<?= (int) $r['form_version'] ?></span></td>
      <?php foreach (array_slice($fields, 0, 4) as $fd): ?><td class="max-w-48 truncate"><?= esc((string) ($d[$fd['key']] ?? '')) ?></td><?php endforeach ?>
      <td><?= (int) $r['consent'] === 1 ? status_badge('active', lang('Form.yes')) : status_badge('none', lang('Form.no')) ?></td>
      <td><?php if ($r['opportunity_id']): ?><a class="text-accent" href="<?= site_url('crm/oportunidades?open=' . (int) $r['opportunity_id']) ?>"><?= lang('Form.viewOpportunity') ?></a><?php elseif ($r['contact_id']): ?><a class="text-accent" href="<?= site_url('crm/contactos?edit=' . (int) $r['contact_id']) ?>"><?= lang('Form.viewContact') ?></a><?php endif ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
<?= $this->endSection() ?>
