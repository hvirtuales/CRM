<?php /** Confirm (alertdialog) compartido: lo abre app.js para formularios con data-confirm. */ ?>
<dialog id="confirm-dialog" class="modal" role="alertdialog" aria-labelledby="confirm-title" aria-describedby="confirm-message">
  <div class="space-y-3 p-5">
    <h2 id="confirm-title" class="text-base font-semibold"><?= lang('Core.confirmTitle') ?></h2>
    <p id="confirm-message" class="text-sm text-ink-soft"></p>
    <div class="flex justify-end gap-2 pt-2">
      <button type="button" class="btn btn-ghost" data-confirm-cancel><?= lang('Core.cancel') ?></button>
      <button type="button" class="btn btn-danger" data-confirm-accept><?= lang('Core.confirm') ?></button>
    </div>
  </div>
</dialog>
