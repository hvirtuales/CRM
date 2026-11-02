<?php
/**
 * Paginación: ≤1 página → "N registro(s)"; si no, rango + Anterior/Siguiente.
 *
 * @var int $page
 * @var int $perPage
 * @var int $total
 */
$pages = max(1, (int) ceil($total / $perPage));
$query = service('request')->getGet();
$link  = static function (int $p) use ($query) { $query['page'] = $p; return current_url() . '?' . http_build_query($query); };
?>
<div class="mt-3 flex items-center justify-between gap-2 text-xs text-ink-soft">
<?php if ($pages <= 1): ?>
  <span><?= lang('Core.records', [$total]) ?></span>
<?php else: ?>
  <span><?= lang('Core.range', [($page - 1) * $perPage + 1, min($page * $perPage, $total), $total]) ?></span>
  <div class="flex gap-1">
    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= esc($link($page - 1), 'attr') ?>"><?= icon('chevron-left', 14) ?><?= lang('Core.previous') ?></a><?php endif ?>
    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= esc($link($page + 1), 'attr') ?>"><?= lang('Core.next') ?><?= icon('chevron-right', 14) ?></a><?php endif ?>
  </div>
<?php endif ?>
</div>
