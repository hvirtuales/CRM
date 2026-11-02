<?php
/** Cambio de idioma por POST + CSRF (modifica la preferencia del usuario). @var string $class */
$to = service('request')->getLocale() === 'es' ? 'en' : 'es';
?>
<form method="post" action="<?= site_url('idioma/' . $to) ?>" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= esc(current_url(), 'attr') ?>">
  <button type="submit" class="<?= esc($class, 'attr') ?>" aria-label="<?= esc(lang('Core.switchLanguage'), 'attr') ?>"><?= icon('languages', 16) ?><span><?= $to === 'en' ? 'English' : 'Español' ?></span></button>
</form>
