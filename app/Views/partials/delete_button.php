<?php
/**
 * Botón de acción destructiva o de estado por POST + CSRF con confirmación (nunca GET).
 *
 * @var string      $action URL
 * @var string      $label
 * @var string|null $confirm mensaje de confirmación
 * @var array       $fields  campos ocultos adicionales
 * @var string|null $variant danger|secondary|ghost
 */
$variant ??= 'danger';
$fields  ??= [];
?>
<form method="post" action="<?= esc($action, 'attr') ?>" class="inline"<?= ! empty($confirm) ? ' data-confirm="' . esc($confirm, 'attr') . '"' : '' ?>>
  <?= csrf_field() ?>
  <?php foreach ($fields as $k => $v): ?><input type="hidden" name="<?= esc($k, 'attr') ?>" value="<?= esc((string) $v, 'attr') ?>"><?php endforeach ?>
  <button type="submit" class="btn btn-sm btn-<?= esc($variant, 'attr') ?>"><?= esc($label) ?></button>
</form>
