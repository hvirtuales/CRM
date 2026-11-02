<?php /** 403 propio con el layout de acceso (no revela detalles internos). */ ?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= lang('Core.forbiddenTitle') ?></title>
<script src="<?= base_url('assets/js/theme.js') ?>"></script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>"></head>
<body><main class="grid min-h-full place-items-center p-4"><div class="card w-full max-w-md p-6 text-center">
<div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-muted text-danger"><?= icon('lock', 22) ?></div>
<h1 class="text-xl font-semibold"><?= lang('Core.forbiddenTitle') ?></h1>
<p class="mt-2 text-sm text-ink-soft"><?= esc($message ?? lang('Core.forbidden')) ?></p>
<div class="mt-5 flex justify-center gap-2"><a class="btn btn-secondary" href="<?= site_url('/') ?>"><?= lang('Core.goHome') ?></a><a class="btn btn-ghost" href="<?= site_url('contexto') ?>"><?= lang('Core.switchContext') ?></a></div>
</div></main></body></html>
