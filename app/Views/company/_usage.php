<?php /** Barras de consumo por componente (plan_limits). @var array $planStatus */ ?>
<?php foreach ($planStatus as $ps): ?>
  <div>
    <p class="flex items-center justify-between text-sm"><span class="font-medium"><?= esc($ps['name']) ?></span><?= $ps['enabled'] ? '' : status_badge('disabled', lang('Company.notIncluded')) ?></p>
    <?php foreach ($ps['limits'] as $l): $pct = $l['max'] > 0 ? min(100, (int) round($l['used'] * 100 / $l['max'])) : 0; ?>
      <div class="mt-1">
        <p class="flex justify-between text-xs text-ink-soft"><span><?= esc($l['label_key'] ? lang($l['label_key']) : $l['label']) ?></span><span><?= num($l['used']) ?> / <?= $l['max'] > 0 ? num($l['max']) : lang('Company.unlimited') ?></span></p>
        <?php if ($l['max'] > 0): ?><div class="mt-1 h-2 rounded-full bg-muted" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= esc($l['label'], 'attr') ?>"><div class="h-2 rounded-full <?= $pct >= 90 ? 'bg-warn' : 'bg-accent' ?>" style="width:<?= $pct ?>%"></div></div><?php endif ?>
      </div>
    <?php endforeach ?>
  </div>
<?php endforeach ?>
