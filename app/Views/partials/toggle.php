<?php
/**
 * Toggle (fila completa con interruptor role=switch sobre checkbox nativo).
 *
 * @var string $name
 * @var string $label
 * @var string|null $description consecuencia de activarlo
 * @var bool $checked
 */
$id = 'tg-' . preg_replace('/[^a-z0-9_\-]/i', '-', $name);
$on = old($name) !== null ? (bool) old($name) : ! empty($checked);
?>
<label class="form-check cursor-pointer" for="<?= $id ?>">
  <span><span class="block text-sm font-medium"><?= esc($label) ?></span><?php if (! empty($description)): ?><span class="block text-xs text-ink-soft"><?= esc($description) ?></span><?php endif ?></span>
  <span class="relative inline-flex h-6 w-11 shrink-0">
    <input id="<?= $id ?>" type="checkbox" role="switch" name="<?= esc($name, 'attr') ?>" value="1" class="peer sr-only"<?= $on ? ' checked' : '' ?>>
    <span class="absolute inset-0 rounded-full bg-line transition peer-checked:bg-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent"></span>
    <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-surface shadow transition peer-checked:translate-x-5"></span>
  </span>
</label>
