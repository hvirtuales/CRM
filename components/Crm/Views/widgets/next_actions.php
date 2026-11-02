<?php /** Tarjeta del panel: próximas acciones del usuario. @var array $rows */ ?>
<div class="card h-full">
  <div class="card-header"><h2 class="card-title"><?= lang('Crm.nextActionsTitle') ?></h2><a class="text-xs text-accent" href="<?= site_url('crm/oportunidades?mine=1&view=list') ?>"><?= lang('Crm.seeAll') ?></a></div>
  <?php if ($rows === []): ?><p class="card-body text-sm text-ink-soft"><?= lang('Crm.noNextActions') ?></p><?php else: ?>
  <ul class="divide-y divide-line">
    <?php foreach ($rows as $r): $late = strtotime((string) $r['next_action_at']) < time(); ?>
      <li><a class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-muted" href="<?= site_url('crm/oportunidades?open=' . (int) $r['id']) ?>">
        <span class="min-w-0"><span class="block truncate font-medium"><?= esc($r['next_action'] ?: $r['title']) ?></span><span class="block truncate text-xs text-ink-soft"><?= esc($r['title']) ?> · <?= esc(trim($r['first_name'] . ' ' . ($r['last_name'] ?? ''))) ?></span></span>
        <span class="shrink-0 text-xs <?= $late ? 'text-danger' : 'text-ink-soft' ?>"><?= dt($r['next_action_at']) ?></span>
      </a></li>
    <?php endforeach ?>
  </ul><?php endif ?>
</div>
