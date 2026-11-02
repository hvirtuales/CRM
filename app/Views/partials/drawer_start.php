<?php
/**
 * Apertura de Drawer lateral (edición/detalle). Cerrar con Esc, clic en el velo o el botón.
 *
 * @var string $id
 * @var string $title
 * @var bool   $wide
 * @var bool   $open abrir al cargar (p. ej. ?edit=ID o errores de validación)
 */
?>
<dialog id="<?= esc($id, 'attr') ?>" class="drawer<?= ! empty($wide) ? ' drawer-wide' : '' ?>" aria-labelledby="<?= esc($id, 'attr') ?>-title"<?= ! empty($open) ? ' data-open-on-load' : '' ?><?= ! empty($closeTo) ? ' data-close-to="' . esc($closeTo, 'attr') . '"' : '' ?>>
  <div class="drawer-inner">
    <div class="drawer-head">
      <h2 id="<?= esc($id, 'attr') ?>-title" class="text-base font-semibold"><?= esc($title) ?></h2>
      <button type="button" class="btn btn-ghost btn-sm" data-close-dialog aria-label="<?= esc(lang('Core.close'), 'attr') ?>"><?= icon('x', 16) ?><span class="kbd">Esc</span></button>
    </div>
