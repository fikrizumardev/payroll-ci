<?php
/**
 * Sidebar navigasi gelap. Mode ringkas (desktop) dikendalikan class
 * `.sidebar-collapsed` di <html>; drawer mobile dikendalikan Alpine `mobileOpen`.
 *
 * @var list<array{label: string, items: list<array<string, mixed>>}> $navigation
 * @var array<string, mixed>|null                                     $user
 */
$companyName = service('appSettings')->get('company.name', 'PayrollKu');
?>
<aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink-950 text-slate-300 shadow-2xl transition-[width,transform] duration-300 lg:w-68 lg:translate-x-0 lg:shadow-none data-[open=true]:translate-x-0 [.sidebar-collapsed_&]:lg:w-20"
       :data-open="mobileOpen ? 'true' : 'false'" aria-label="Navigasi utama">
    <!-- Glow dekoratif -->
    <div class="pointer-events-none absolute inset-x-0 top-0 h-56 bg-[radial-gradient(60%_100%_at_0%_0%,rgb(79_70_229/0.35),transparent)]" aria-hidden="true"></div>

    <!-- Brand -->
    <div class="relative flex h-16 shrink-0 items-center gap-3 px-5 [.sidebar-collapsed_&]:lg:justify-center [.sidebar-collapsed_&]:lg:px-0">
        <a href="<?= url_to('dashboard') ?>" class="flex min-w-0 items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-500 to-violet-600 shadow-glow ring-1 ring-white/15">
                <?= icon('wallet-cards', 'size-5 text-white') ?>
            </span>
            <span class="min-w-0 [.sidebar-collapsed_&]:lg:hidden">
                <span class="block truncate text-[15px] font-bold tracking-tight text-white">PayrollKu</span>
                <span class="block truncate text-[11px] text-slate-400"><?= esc($companyName) ?></span>
            </span>
        </a>
        <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden" @click="mobileOpen = false" aria-label="Tutup menu">
            <?= icon('x', 'size-5') ?>
        </button>
    </div>

    <!-- Menu -->
    <nav class="relative flex-1 overflow-y-auto px-3 pt-2 pb-6 [.sidebar-collapsed_&]:lg:overflow-visible [scrollbar-width:thin] [scrollbar-color:rgb(255_255_255/0.1)_transparent]">
        <?php foreach ($navigation as $group): ?>
            <div class="mt-4 first:mt-1">
                <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-slate-400 uppercase [.sidebar-collapsed_&]:lg:hidden"><?= esc($group['label']) ?></p>
                <div class="mx-auto mb-2 hidden h-px w-8 bg-white/10 [.sidebar-collapsed_&]:lg:block" aria-hidden="true"></div>

                <ul class="space-y-0.5">
                    <?php foreach ($group['items'] as $item): ?>
                        <li class="group/item relative">
                            <?php if ($item['ready']): ?>
                                <a href="<?= esc($item['url'], 'attr') ?>"
                                   class="relative flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition [.sidebar-collapsed_&]:lg:justify-center <?= $item['active'] ? 'bg-white/[0.08] text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.06)]' : 'text-slate-400 hover:bg-white/[0.05] hover:text-white' ?>"
                                   <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                                    <?php if ($item['active']): ?>
                                        <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-linear-to-b from-brand-400 to-violet-500" aria-hidden="true"></span>
                                    <?php endif ?>
                                    <?= icon($item['icon'], 'size-[18px] shrink-0 ' . ($item['active'] ? 'text-brand-300' : 'text-slate-500 group-hover/item:text-slate-300')) ?>
                                    <span class="truncate [.sidebar-collapsed_&]:lg:sr-only"><?= esc($item['label']) ?></span>
                                </a>
                            <?php else: ?>
                                <span class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-400 [.sidebar-collapsed_&]:lg:justify-center" aria-disabled="true">
                                    <?= icon($item['icon'], 'size-[18px] shrink-0 text-slate-600') ?>
                                    <span class="truncate [.sidebar-collapsed_&]:lg:sr-only"><?= esc($item['label']) ?></span>
                                    <span class="ml-auto rounded-md bg-white/[0.04] px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-slate-400 ring-1 ring-white/5 [.sidebar-collapsed_&]:lg:hidden">Segera</span>
                                </span>
                            <?php endif ?>

                            <!-- Tooltip saat sidebar ringkas -->
                            <span class="pointer-events-none absolute top-1/2 left-full z-50 ml-3 hidden -translate-y-1/2 rounded-lg bg-ink-800 px-2.5 py-1.5 text-xs font-medium whitespace-nowrap text-white opacity-0 shadow-elevated ring-1 ring-white/10 transition group-hover/item:opacity-100 [.sidebar-collapsed_&]:lg:block" role="tooltip">
                                <?= esc($item['label']) ?><?= $item['ready'] ? '' : ' · Segera' ?>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endforeach ?>
    </nav>

    <!-- Footer sidebar -->
    <div class="relative shrink-0 border-t border-white/5 p-3">
        <?php if ($user): ?>
            <div class="flex items-center gap-3 rounded-xl bg-white/[0.04] p-2.5 ring-1 ring-white/5 [.sidebar-collapsed_&]:lg:justify-center [.sidebar-collapsed_&]:lg:bg-transparent [.sidebar-collapsed_&]:lg:p-0 [.sidebar-collapsed_&]:lg:ring-0">
                <?= avatar($user['name'], 'size-9 text-xs ring-ink-950') ?>
                <div class="min-w-0 flex-1 [.sidebar-collapsed_&]:lg:hidden">
                    <p class="truncate text-sm font-semibold text-white"><?= esc($user['name']) ?></p>
                    <p class="truncate text-xs text-slate-400">@<?= esc($user['username']) ?> · <?= esc(role_label($user['role'])) ?></p>
                </div>
            </div>
        <?php endif ?>
        <button type="button" @click="toggleCollapse()"
                class="mt-2 hidden w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-slate-400 transition hover:bg-white/5 hover:text-slate-200 lg:flex"
                :aria-label="collapsed ? 'Lebarkan sidebar' : 'Ringkas sidebar'" aria-label="Ringkas sidebar">
            <span class="[.sidebar-collapsed_&]:hidden"><?= icon('panel-left-close', 'size-4') ?></span>
            <span class="hidden [.sidebar-collapsed_&]:inline"><?= icon('panel-left-open', 'size-4') ?></span>
            <span class="[.sidebar-collapsed_&]:hidden">Ringkas sidebar</span>
        </button>
    </div>
</aside>
