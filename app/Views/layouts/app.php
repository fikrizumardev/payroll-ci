<?php
/**
 * Layout aplikasi (sementara) — topbar, flash message, dan konten.
 * Sidebar & navigasi lengkap ditambahkan pada Fase 5.
 */
$user = service('auth')->user();
?>
<!doctype html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <?= $this->include('layouts/partials/head') ?>
</head>
<body class="min-h-full font-sans text-slate-800">
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="<?= url_to('dashboard') ?>" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-violet-600 shadow-glow">
                    <?= icon('wallet-cards', 'size-5 text-white') ?>
                </span>
                <span class="font-bold tracking-tight text-slate-900">PayrollKu</span>
            </a>

            <?php if ($user): ?>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-900"><?= esc($user['name']) ?></p>
                        <p class="text-xs text-slate-500"><?= esc(role_label($user['role'])) ?></p>
                    </div>
                    <?= avatar($user['name']) ?>
                    <a href="<?= url_to('account.password') ?>" class="btn btn-ghost px-2.5" title="Ganti password" aria-label="Ganti password"><?= icon('key-round', 'size-4') ?></a>
                    <form method="post" action="<?= url_to('logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary px-3"><?= icon('log-out', 'size-4') ?> Keluar</button>
                    </form>
                </div>
            <?php endif ?>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <?php foreach (['success' => 'emerald', 'warning' => 'amber', 'error' => 'rose'] as $key => $tone): ?>
            <?php if (session($key)): ?>
                <div class="mb-6 rounded-xl bg-<?= $tone ?>-50 p-3.5 text-sm text-<?= $tone ?>-800 ring-1 ring-<?= $tone ?>-200" role="status">
                    <?= esc(session($key)) ?>
                </div>
            <?php endif ?>
        <?php endforeach ?>

        <?= $this->renderSection('content') ?>
    </main>

    <?= $this->include('layouts/partials/scripts') ?>
</body>
</html>
