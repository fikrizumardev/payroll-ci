<?php
/**
 * Layout utama aplikasi: sidebar gelap (collapsible + drawer mobile), topbar,
 * command palette (Ctrl/⌘ + K), toast flash message, dan modal konfirmasi global.
 *
 * Data opsional dari halaman:
 * @var string|null                                   $title
 * @var list<array{label: string, url?: string|null}> $breadcrumbs
 */
$user       = service('auth')->user();
$navigation = config('Navigation')->forUser($user ?? []);
$quickLinks = [];

foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if ($item['ready']) {
            $quickLinks[] = ['label' => $item['label'], 'group' => $group['label'], 'url' => $item['url'], 'icon' => $item['icon']];
        }
    }
}

$quickLinks[] = ['label' => 'Ganti Password', 'group' => 'Akun', 'url' => url_to('account.password'), 'icon' => 'key-round'];
?>
<!doctype html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <?= $this->include('layouts/partials/head') ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
    <script>
        // Terapkan state sidebar sebelum render agar tidak berkedip
        if (localStorage.getItem('payrollku.sidebar') === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
</head>
<body class="h-full font-sans text-slate-800" x-data="appShell(<?= esc(json_encode($quickLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>)"
      @keydown.window.prevent.ctrl.k="openPalette()" @keydown.window.prevent.meta.k="openPalette()" @keydown.window.escape="mobileOpen = false">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-elevated">Lewati ke konten</a>

    <!-- Overlay drawer mobile -->
    <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false"
         class="fixed inset-0 z-40 bg-ink-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    <?= component('layout/sidebar', ['navigation' => $navigation, 'user' => $user]) ?>

    <div class="flex min-h-full flex-col transition-[padding] duration-300 lg:pl-68 [.sidebar-collapsed_&]:lg:pl-20">
        <?= component('layout/topbar', ['user' => $user, 'breadcrumbs' => $breadcrumbs ?? [], 'title' => $title ?? null]) ?>

        <main id="main" class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mx-auto max-w-[90rem]">
                <?= $this->renderSection('content') ?>
            </div>
        </main>

        <footer class="border-t border-slate-200/70 px-4 py-5 text-xs text-slate-500 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-[90rem] flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; <?= date('Y') ?> <?= esc(service('appSettings')->get('company.name', 'PayrollKu')) ?></p>
                <p>PayrollKu v1.0 · Waktu server <?= esc(tanggal_id(date('Y-m-d H:i:s'), 'datetime')) ?> WIB</p>
            </div>
        </footer>
    </div>

    <?= component('layout/command_palette') ?>
    <?= component('toast', ['flash' => [
        'success' => session('success'),
        'error'   => session('error'),
        'warning' => session('warning'),
        'info'    => session('info'),
    ]]) ?>
    <?= component('modal_confirm') ?>

    <?= $this->include('layouts/partials/scripts') ?>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
