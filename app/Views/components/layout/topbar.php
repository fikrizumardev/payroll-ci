<?php
/**
 * Topbar: tombol menu mobile, breadcrumb, pemicu command palette, tanggal, dan menu user.
 *
 * @var array<string, mixed>|null                     $user
 * @var list<array{label: string, url?: string|null}> $breadcrumbs
 * @var string|null                                   $title
 */
$breadcrumbs = $breadcrumbs !== [] ? $breadcrumbs : ($title ? [['label' => $title]] : []);
?>
<header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/75 backdrop-blur-xl supports-[backdrop-filter]:bg-white/65">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <button type="button" class="-ml-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                @click="mobileOpen = true" aria-label="Buka menu">
            <?= icon('menu', 'size-5') ?>
        </button>

        <!-- Breadcrumb -->
        <nav class="hidden min-w-0 items-center gap-1.5 text-sm sm:flex" aria-label="Breadcrumb">
            <a href="<?= url_to('dashboard') ?>" class="rounded-md p-1 text-slate-500 hover:text-slate-700" aria-label="Dashboard">
                <?= icon('house', 'size-4') ?>
            </a>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?= icon('chevron-right', 'size-3.5 shrink-0 text-slate-300') ?>
                <?php if (! empty($crumb['url']) && $index < count($breadcrumbs) - 1): ?>
                    <a href="<?= esc($crumb['url'], 'attr') ?>" class="truncate font-medium text-slate-500 hover:text-slate-900"><?= esc($crumb['label']) ?></a>
                <?php else: ?>
                    <span class="truncate font-semibold text-slate-900" <?= $index === count($breadcrumbs) - 1 ? 'aria-current="page"' : '' ?>><?= esc($crumb['label']) ?></span>
                <?php endif ?>
            <?php endforeach ?>
        </nav>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            <!-- Pencarian cepat -->
            <button type="button" @click="openPalette()"
                    class="group hidden items-center gap-2.5 rounded-xl bg-slate-100/80 py-2 pr-2 pl-3 text-sm text-slate-600 ring-1 ring-slate-200/80 transition hover:bg-white hover:text-slate-700 hover:shadow-card md:flex md:w-64 lg:w-72">
                <?= icon('search', 'size-4 text-slate-500 group-hover:text-slate-500') ?>
                <span>Cari menu…</span>
                <kbd class="ml-auto rounded-md bg-white px-1.5 py-0.5 font-sans text-[11px] font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200">Ctrl K</kbd>
            </button>
            <button type="button" @click="openPalette()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 md:hidden" aria-label="Cari menu">
                <?= icon('search', 'size-5') ?>
            </button>

            <span class="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 xl:flex">
                <?= icon('calendar', 'size-4 text-slate-500') ?>
                <?= esc(tanggal_id(date('Y-m-d'), 'full')) ?>
            </span>

            <span class="hidden h-6 w-px bg-slate-200 sm:block" aria-hidden="true"></span>

            <?php if ($user): ?>
                <!-- Menu user -->
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu"
                            class="flex items-center gap-2.5 rounded-xl p-1 pr-2 transition hover:bg-slate-100" aria-label="Menu akun">
                        <?= avatar($user['name'], 'size-9 text-xs') ?>
                        <span class="hidden text-left leading-tight sm:block">
                            <span class="block max-w-40 truncate text-sm font-semibold text-slate-900"><?= esc($user['name']) ?></span>
                            <span class="block text-xs text-slate-500"><?= esc(role_label($user['role'])) ?></span>
                        </span>
                        <?= icon('chevron-down', 'hidden size-4 text-slate-500 sm:block') ?>
                    </button>

                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 origin-top-right overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200/80" role="menu">
                        <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/70 p-4">
                            <?= avatar($user['name'], 'size-10 text-sm') ?>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900"><?= esc($user['name']) ?></p>
                                <p class="truncate text-xs text-slate-500">@<?= esc($user['username']) ?></p>
                            </div>
                        </div>
                        <div class="p-1.5">
                            <p class="flex items-center justify-between px-3 py-2 text-xs text-slate-500">
                                Peran <?= status_badge('approved', role_label($user['role'])) ?>
                            </p>
                            <a href="<?= url_to('account.password') ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100" role="menuitem">
                                <?= icon('key-round', 'size-4 text-slate-500') ?> Ganti password
                            </a>
                            <?php if (route_exists('my.payslips') && ! empty($user['employee_id'])): ?>
                                <a href="<?= url_to('my.payslips') ?>" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100" role="menuitem">
                                    <?= icon('receipt-text', 'size-4 text-slate-500') ?> Slip gaji saya
                                </a>
                            <?php endif ?>
                        </div>
                        <div class="border-t border-slate-100 p-1.5">
                            <form method="post" action="<?= url_to('logout') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50" role="menuitem">
                                    <?= icon('log-out', 'size-4') ?> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>
</header>
