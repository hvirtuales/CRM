<?php
/** Toasts (design system): arriba al centro, máx. 3, aria-live. Mensajes ya traducidos. */
$msgs = [];
if ($m = session()->getFlashdata('message')) { $msgs[] = ['ok', $m]; }
if ($m = session()->getFlashdata('error')) { $msgs[] = ['error', $m]; }
?>
<div id="toasts" class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="assertive" role="status">
<?php foreach (array_slice($msgs, 0, 3) as [$type, $text]): ?>
  <div class="pointer-events-auto flex max-w-lg items-start gap-2 rounded-xl border bg-surface px-4 py-3 text-sm shadow-lg <?= $type === 'error' ? 'border-danger text-danger' : 'border-ok' ?>" data-toast="<?= $type === 'error' ? 7000 : 3500 ?>">
    <?= icon($type === 'error' ? 'triangle-alert' : 'circle-check', 16, 'mt-0.5 ' . ($type === 'error' ? 'text-danger' : 'text-ok')) ?>
    <span><?= esc($text) ?></span>
  </div>
<?php endforeach ?>
</div>
