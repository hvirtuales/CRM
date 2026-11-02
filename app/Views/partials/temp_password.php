<?php /** Contraseña temporal recién generada: se muestra UNA vez. */ ?>
<?php if ($tp = session()->getFlashdata('temp_password')): ?>
  <div class="notice-accent mb-4" role="status">
    <?= icon('key-round', 16, 'mt-0.5 text-accent') ?>
    <div><p><?= lang('Core.tempPasswordNotice') ?></p><p class="mt-1 font-mono font-semibold select-all"><?= esc($tp) ?></p></div>
  </div>
<?php endif ?>
