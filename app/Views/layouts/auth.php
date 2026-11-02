<?php
/** Layout de acceso y asistentes (design system §9): columna centrada max-w-md. */
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc(trim($this->renderSection('title')) . ' · ' . lang('Core.appName')) ?></title>
<script src="<?= base_url('assets/js/theme.js') ?>"></script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<main class="grid min-h-full place-items-center p-4">
  <div class="w-full max-w-md space-y-6">
    <div class="text-center">
      <div class="mx-auto grid size-16 place-items-center rounded-full bg-accent text-xl font-semibold text-accent-ink" aria-hidden="true">HV</div>
      <h1 class="mt-4 text-2xl font-semibold"><?= lang('Core.appName') ?></h1>
      <p class="mt-1 text-sm text-ink-soft"><?= lang('Core.tagline') ?></p>
    </div>
    <?= $this->renderSection('content') ?>
    <p class="text-center text-xs text-ink-soft">
      <?= view('partials/locale_switch', ['class' => 'inline-flex items-center gap-1 hover:text-ink']) ?>
    </p>
  </div>
</main>
<?= view('partials/toasts') ?>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
