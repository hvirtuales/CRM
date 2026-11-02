<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= lang('Nav.tokens') ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<?= view('partials/page_header', ['title' => lang('Nav.tokens'), 'description' => lang('Company.tokensHelp'),
    'actions' => '<button type="button" class="btn btn-primary" data-open-dialog="dlg-token">' . icon('plus') . esc(lang('Company.newToken')) . '</button>']) ?>
<?php if ($t = session()->getFlashdata('api_token')): ?>
  <div class="notice-accent mb-4" role="status"><?= icon('key-round', 16, 'mt-0.5 text-accent') ?>
    <div class="min-w-0 flex-1"><p><?= lang('Company.tokenOnce') ?></p><p class="mt-1 break-all font-mono text-xs font-semibold select-all"><?= esc($t) ?></p></div>
    <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= esc($t, 'attr') ?>" data-copied="<?= esc(lang('Core.copied'), 'attr') ?>"><?= icon('copy', 14) ?><?= lang('Core.copy') ?></button>
  </div>
<?php endif ?>
<div class="notice mb-4"><?= lang('Company.apiUsage', [site_url('api/v1/crm/contacts')]) ?></div>
<?php if ($tokens === []): ?><?= view('partials/empty', ['message' => lang('Company.tokensEmpty')]) ?><?php else: ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th><?= lang('Core.name') ?></th><th><?= lang('Company.abilities') ?></th><th><?= lang('Company.lastUsed') ?></th><th><?= lang('Platform.expiresAt') ?></th><th class="num"></th></tr></thead>
  <tbody><?php foreach ($tokens as $tk): $revoked = $tk['revoked_at'] !== null; $expired = strtotime((string) $tk['expires_at']) < time(); ?>
    <tr>
      <td><span class="font-medium"><?= esc($tk['name']) ?></span><span class="block text-xs text-ink-soft"><?= esc($tk['user_name']) ?></span></td>
      <td class="text-xs"><?= esc(implode(', ', json_decode((string) $tk['abilities'], true) ?: [])) ?></td>
      <td><?= dt($tk['last_used_at']) ?></td>
      <td><?= $revoked ? status_badge('revoked') : ($expired ? status_badge('expired') : dt($tk['expires_at'], false)) ?></td>
      <td class="num"><?php if (! $revoked && ! $expired): ?><?= view('partials/delete_button', ['action' => site_url('negocio/tokens/' . (int) $tk['id'] . '/revocar'), 'label' => lang('Core.revoke'), 'confirm' => lang('Company.confirmRevokeToken')]) ?><?php endif ?></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>
<?= view('partials/drawer_start', ['id' => 'dlg-token', 'title' => lang('Company.newToken'), 'open' => $errors !== []]) ?>
<form method="post" action="<?= site_url('negocio/tokens') ?>" class="flex min-h-0 flex-1 flex-col">
  <?= csrf_field() ?>
  <div class="drawer-body space-y-4">
    <?= view('partials/field', ['name' => 'name', 'label' => lang('Core.name'), 'required' => true, 'placeholder' => lang('Company.tokenNamePh')]) ?>
    <?= view('partials/field', ['name' => 'days', 'label' => lang('Company.tokenDays'), 'type' => 'number', 'value' => 90, 'required' => true, 'attrs' => ['min' => 1, 'max' => 365]]) ?>
    <fieldset class="form-section"><legend class="form-section-title"><?= lang('Company.abilities') ?></legend>
      <p class="form-section-hint"><?= lang('Company.abilitiesHint') ?></p>
      <?php if ($abilities === []): ?><p class="text-sm text-ink-soft"><?= lang('Company.noAbilities') ?></p><?php endif ?>
      <?php foreach ($abilities as $a): ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="abilities[]" value="<?= esc($a, 'attr') ?>"> <code><?= esc($a) ?></code></label>
      <?php endforeach ?>
      <?= field_error('abilities') ?>
    </fieldset>
  </div>
  <div class="drawer-foot justify-end"><button type="button" class="btn btn-ghost" data-close-dialog><?= lang('Core.cancel') ?></button><button type="submit" class="btn btn-primary"><?= lang('Company.createToken') ?></button></div>
</form>
<?= view('partials/drawer_end') ?>
<?= $this->endSection() ?>
