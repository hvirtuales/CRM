<?php
/**
 * PageHeader: título (text-xl), una frase de propósito y acciones a la derecha.
 *
 * @var string      $title
 * @var string|null $description
 * @var string|null $actions HTML ya escapado
 */
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div class="min-w-0">
    <h1 class="text-xl font-semibold"><?= esc($title) ?></h1>
    <?php if (! empty($description)): ?><p class="mt-1 text-sm text-ink-soft"><?= esc($description) ?></p><?php endif ?>
  </div>
  <?php if (! empty($actions)): ?><div class="flex flex-wrap items-center gap-2"><?= $actions ?></div><?php endif ?>
</div>
