<?php
/**
 * Campo de formulario (Field): etiqueta + control + ayuda/error asociados por id.
 *
 * @var string      $name
 * @var string      $label
 * @var string|null $type text|email|number|date|password|select|textarea|color
 * @var mixed       $value
 * @var bool|null   $required
 * @var string|null $hint
 * @var string|null $placeholder
 * @var array|null  $options value => label (select)
 * @var string|null $class contenedor extra (p. ej. sm:col-span-2)
 * @var array|null  $attrs atributos extra del control
 */
$type ??= 'text';
$id    = 'f-' . preg_replace('/[^a-z0-9_\-]/i', '-', $name);
$err   = (session('errors') ?? [])[$name] ?? null;
$val   = old($name, $value ?? '');
$extra = '';
foreach ($attrs ?? [] as $k => $v) { $extra .= ' ' . esc($k, 'attr') . '="' . esc((string) $v, 'attr') . '"'; }
$aria  = ($err ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : (! empty($hint) ? ' aria-describedby="' . $id . '-hint"' : ''));
?>
<div class="field <?= esc($class ?? '', 'attr') ?>">
  <label class="label" for="<?= $id ?>"><?= esc($label) ?><?php if (! empty($required)): ?> <span class="text-danger-star">*</span><?php endif ?></label>
  <?php if ($type === 'select'): ?>
    <select class="select" id="<?= $id ?>" name="<?= esc($name, 'attr') ?>"<?= ! empty($required) ? ' required' : '' ?><?= $aria . $extra ?>>
      <?php foreach ($options ?? [] as $ov => $ol): ?>
        <option value="<?= esc((string) $ov, 'attr') ?>"<?= (string) $ov === (string) $val ? ' selected' : '' ?>><?= esc($ol) ?></option>
      <?php endforeach ?>
    </select>
  <?php elseif ($type === 'textarea'): ?>
    <textarea class="textarea" id="<?= $id ?>" name="<?= esc($name, 'attr') ?>" placeholder="<?= esc($placeholder ?? '', 'attr') ?>"<?= ! empty($required) ? ' required' : '' ?><?= $aria . $extra ?>><?= esc((string) $val) ?></textarea>
  <?php else: ?>
    <input class="input" id="<?= $id ?>" type="<?= esc($type, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= $type === 'password' ? '' : esc((string) $val, 'attr') ?>" placeholder="<?= esc($placeholder ?? '', 'attr') ?>"<?= ! empty($required) ? ' required' : '' ?><?= $aria . $extra ?>>
  <?php endif ?>
  <?php if ($err): ?><p class="text-xs text-danger" id="<?= $id ?>-err"><?= esc($err) ?></p><?php elseif (! empty($hint)): ?><p class="hint" id="<?= $id ?>-hint"><?= esc($hint) ?></p><?php endif ?>
</div>
