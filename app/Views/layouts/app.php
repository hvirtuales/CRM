<?php
/**
 * Shell administrativo (design system §1): sidebar 256 px con grupos plegables, header de una fila
 * y contenido centrado a max-w-6xl. El menú depende del ámbito, del permiso y del plan.
 *
 * @var \CodeIgniter\View\View $this
 */
use App\Libraries\BrandService;
use Config\Navigation;
use Config\Services;

$ctx    = ctx();
$scope  = $ctx->scope();
$user   = $ctx->user();
$tenant = $ctx->tenant();
$brand  = BrandService::variables($tenant['brand_color'] ?? null); // validado: se emite crudo
$appName = $tenant['brand_name'] ?? lang('Core.appName');
$path   = trim(service('request')->getUri()->getPath(), '/');

$groups = [];
foreach (config(Navigation::class)->menus[$scope] ?? [] as $i => $group) {
    $items = array_values(array_filter($group['items'], static fn ($it) => $ctx->can($it['permission'])));
    if ($scope === 'company' && $i === 1) {
        foreach (Services::components()->menusFor($ctx) as $cg) {
            $groups[] = [
                'label' => $cg['name_key'] ? lang($cg['name_key']) : $cg['name'],
                'items' => array_map(static fn ($m) => [
                    'label' => isset($m['label_key']) ? lang($m['label_key']) : $m['label'],
                    'route' => $m['route'], 'icon' => $m['icon'] ?? 'circle-dot',
                ], $cg['items']),
            ];
        }
    }
    if ($items) {
        $groups[] = ['label' => lang($group['group']), 'items' => array_map(static fn ($it) => ['label' => lang($it['label']), 'route' => $it['route'], 'icon' => $it['icon']], $items)];
    }
}
// Ítem activo: el de ruta más larga que prefija la URL actual.
$activeRoute = '';
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        if (($path === $it['route'] || str_starts_with($path, $it['route'] . '/')) && strlen($it['route']) > strlen($activeRoute)) {
            $activeRoute = $it['route'];
        }
    }
}
$section = $this->renderSection('title');
?>
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-header" content="<?= esc(config('Security')->headerName) ?>">
<title><?= esc(trim($section) !== '' ? trim($section) . ' · ' . $appName : $appName) ?></title>
<script src="<?= base_url('assets/js/theme.js') ?>"></script>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<style>:root{--brand:<?= $brand['brand'] ?>;--brand-ink:<?= $brand['brandInk'] ?>;--brand-dark:<?= $brand['brandDark'] ?>;--brand-ink-dark:<?= $brand['brandInkDark'] ?>}</style>
<?= $this->renderSection('head') ?>
</head>
<body>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 btn btn-secondary"><?= lang('Core.skipToContent') ?></a>
<div class="flex h-full">
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col overflow-y-auto border-r border-line bg-surface p-4 lg:static lg:flex lg:w-64" aria-label="<?= esc(lang('Core.mainNav')) ?>">
    <div class="mb-6 flex items-center gap-3">
      <div class="grid size-9 place-items-center rounded-full bg-accent text-sm font-semibold text-accent-ink" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($appName, 0, 2))) ?></div>
      <div class="min-w-0">
        <div class="truncate font-semibold"><?= esc($appName) ?></div>
        <div class="truncate text-xs text-ink-soft"><?= esc($ctx->organizationName() ?? '') ?></div>
      </div>
    </div>
    <nav class="space-y-1">
      <?php foreach ($groups as $gi => $g): $gid = 'navg-' . $gi; ?>
        <button type="button" class="nav-group" aria-expanded="true" aria-controls="<?= $gid ?>" data-nav-group="<?= esc(md5($scope . $g['label']), 'attr') ?>">
          <?= icon('chevron-down', 14, 'transition') ?><span><?= esc($g['label']) ?></span>
        </button>
        <ul id="<?= $gid ?>" class="space-y-0.5">
          <?php foreach ($g['items'] as $it): ?>
            <li><a class="nav-item" href="<?= site_url($it['route']) ?>"<?= $it['route'] === $activeRoute ? ' aria-current="page"' : '' ?>><?= icon($it['icon'], 18) ?><span><?= esc($it['label']) ?></span></a></li>
          <?php endforeach ?>
        </ul>
      <?php endforeach ?>
    </nav>
  </aside>
  <div id="sidebar-veil" class="fixed inset-0 z-30 hidden bg-black/30 lg:hidden" data-close-sidebar></div>

  <div class="flex min-w-0 flex-1 flex-col">
    <header class="flex items-center gap-2 border-b border-line bg-surface px-4 py-3">
      <button type="button" class="btn btn-ghost btn-sm lg:hidden" data-open-sidebar aria-label="<?= esc(lang('Core.openMenu'), 'attr') ?>"><?= icon('menu', 18) ?></button>
      <span class="truncate text-sm text-ink-soft"><?= esc(trim($section)) ?></span>
      <div class="ml-auto flex items-center gap-1">
        <a href="<?= site_url('contexto') ?>" class="btn btn-ghost btn-sm" title="<?= esc(lang('Core.switchContext'), 'attr') ?>">
          <?= icon('arrow-left-right', 14) ?><span class="hidden max-w-48 truncate sm:inline"><?= esc($ctx->organizationName() ?? lang('Core.chooseContext')) ?></span>
        </a>
        <button type="button" class="btn btn-ghost btn-sm" data-theme-toggle aria-label="<?= esc(lang('Core.toggleTheme'), 'attr') ?>"><?= icon('sun', 16) ?></button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('idioma/' . (service('request')->getLocale() === 'es' ? 'en' : 'es')) ?>" aria-label="<?= esc(lang('Core.switchLanguage'), 'attr') ?>"><?= icon('languages', 16) ?><span class="uppercase"><?= service('request')->getLocale() === 'es' ? 'en' : 'es' ?></span></a>
        <a class="btn btn-ghost btn-sm hidden sm:inline-flex" href="<?= site_url('perfil') ?>"><?= esc($user['name'] ?? '') ?></a>
        <form method="post" action="<?= site_url('salir') ?>"><?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" type="submit" aria-label="<?= esc(lang('Auth.logout'), 'attr') ?>"><?= icon('log-out', 16) ?></button>
        </form>
      </div>
    </header>

    <main id="main" class="flex-1 overflow-y-auto">
      <div class="mx-auto max-w-6xl p-4 lg:p-6">
        <?php if ($ctx->isReadOnly()): ?>
          <div class="notice-accent mb-4" role="status"><?= icon('lock', 16, 'mt-0.5 text-accent') ?><span><?= esc($ctx->readOnlyReason()) ?></span></div>
        <?php endif ?>
        <?php if ($ctx->supportGrant()): ?>
          <div class="notice mb-4" role="status"><?= esc(lang('Core.supportBanner', [dt($ctx->supportGrant()['expires_at'])])) ?></div>
        <?php endif ?>
        <?= $this->renderSection('content') ?>
      </div>
    </main>
  </div>
</div>
<?= view('partials/toasts') ?>
<?= view('partials/confirm') ?>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
