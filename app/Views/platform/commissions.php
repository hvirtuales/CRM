<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.commissions') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $canEdit = auth_can('platform.commissions.edit'); ?>
<?= view('partials/page_header', ['title' => lang('Nav.commissions'), 'description' => lang('Platform.commissionsHelp')]) ?>
<div class="card mb-4">
  <div class="card-header"><h2 class="card-title"><?= lang('Platform.settlements') ?></h2></div>
  <?php if ($settlements === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Platform.settlementsEmpty') ?></p><?php else: ?>
  <div class="overflow-x-auto"><table class="table">
    <thead><tr><th><?= lang('Platform.tenant') ?></th><th><?= lang('Platform.settlementPeriod') ?></th><th class="num"><?= lang('Platform.commissionsCount') ?></th><th class="num"><?= lang('Platform.commission') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"></th></tr></thead>
    <tbody><?php foreach ($settlements as $s): ?>
      <tr>
        <td class="font-medium"><?= esc($s['tenant_name']) ?></td>
        <td><?= esc($s['period']) ?></td>
        <td class="num"><?= num($s['commissions_count']) ?></td>
        <td class="num font-semibold"><?= money($s['total'], $s['currency']) ?></td>
        <td><?= status_badge($s['status']) ?><?= $s['reference'] ? '<span class="block text-xs text-ink-soft">' . esc($s['reference']) . '</span>' : '' ?></td>
        <td class="num"><?php if ($canEdit && $s['status'] === 'pending'): ?>
          <form method="post" action="<?= site_url('plataforma/comisiones/extractos/' . (int) $s['id'] . '/pagar') ?>" class="flex justify-end gap-1" data-confirm="<?= esc(lang('Platform.confirmSettlementPaid'), 'attr') ?>">
            <?= csrf_field() ?>
            <input class="input w-40" name="reference" required maxlength="100" placeholder="<?= esc(lang('Platform.settlementRefPh'), 'attr') ?>" aria-label="<?= esc(lang('Platform.reference'), 'attr') ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?= lang('Platform.markPaid') ?></button>
          </form>
        <?php endif ?></td>
      </tr>
    <?php endforeach ?></tbody>
  </table></div><?php endif ?>
</div>
<form method="get" class="mb-3 flex flex-wrap items-center gap-2" data-autosubmit>
  <select class="select w-56" name="tenant" aria-label="<?= esc(lang('Platform.tenant'), 'attr') ?>"><option value=""><?= lang('Platform.allTenants') ?></option>
    <?php foreach ($tenants as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $tenant === (int) $t['id'] ? ' selected' : '' ?>><?= esc($t['name']) ?></option><?php endforeach ?></select>
  <select class="select w-40" name="status" aria-label="<?= esc(lang('Core.statusLabel'), 'attr') ?>"><option value=""><?= lang('Core.all') ?></option>
    <?php foreach (['pending', 'approved', 'paid', 'void'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= lang('Core.status.' . $s) ?></option><?php endforeach ?></select>
</form>
<?php if ($rows === []): ?>
  <?= view('partials/empty', ['message' => lang('Platform.commissionsEmpty')]) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Platform.tenant') ?></th><th><?= lang('Platform.company') ?></th><th class="num"><?= lang('Platform.base') ?></th><th class="num"><?= lang('Platform.rate') ?></th><th class="num"><?= lang('Platform.commission') ?></th><th><?= lang('Core.statusLabel') ?></th><th class="num"></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $m): ?>
    <tr>
      <td class="font-medium"><?= esc($m['tenant_name']) ?></td>
      <td><?= esc($m['company_name'] ?? '—') ?><span class="block text-xs text-ink-soft"><?= esc($m['reference']) ?> · <?= dt($m['payment_date'], false) ?></span></td>
      <td class="num"><?= money($m['base_amount'], $m['currency']) ?></td>
      <td class="num"><?= num((float) $m['rate'] * 100, 2) ?> %</td>
      <td class="num font-semibold"><?= money($m['amount'], $m['currency']) ?></td>
      <td><?= status_badge($m['status']) ?></td>
      <td class="num"><?php if ($canEdit): ?><div class="flex justify-end gap-1">
        <?php if ($m['status'] === 'pending'): ?><?= view('partials/delete_button', ['action' => site_url('plataforma/comisiones/' . (int) $m['id'] . '/estado'), 'label' => lang('Core.approve'), 'fields' => ['status' => 'approved'], 'variant' => 'secondary']) ?><?php endif ?>
        <?php if ($m['status'] === 'approved'): ?><?= view('partials/delete_button', ['action' => site_url('plataforma/comisiones/' . (int) $m['id'] . '/estado'), 'label' => lang('Platform.markPaid'), 'fields' => ['status' => 'paid'], 'variant' => 'secondary', 'confirm' => lang('Platform.confirmPaid')]) ?><?php endif ?>
        <?php if (in_array($m['status'], ['pending', 'approved'], true)): ?><?= view('partials/delete_button', ['action' => site_url('plataforma/comisiones/' . (int) $m['id'] . '/estado'), 'label' => lang('Core.void'), 'fields' => ['status' => 'void'], 'confirm' => lang('Platform.confirmVoid')]) ?><?php endif ?>
      </div><?php endif ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?= view('partials/pagination', compact('page', 'perPage', 'total')) ?>
<?php endif ?>
<?= $this->endSection() ?>
