<?php
/**
 * Pembuka modal form (pasangan: modal_close). Visibilitas dikendalikan
 * variabel Alpine pada scope pemanggil (default `modalOpen`).
 *
 *   <?= component('modal_open', ['title' => 'Tambah Departemen', 'action' => 'form.action']) ?>
 *       … field form …
 *   <?= component('modal_close', ['submitLabel' => 'Simpan']) ?>
 *
 * @var string      $title      teks judul (boleh ekspresi Alpine lewat $titleExpr)
 * @var string|null $titleExpr  ekspresi Alpine untuk judul dinamis
 * @var string      $action     ekspresi Alpine untuk URL action form
 * @var string|null $subtitle
 * @var string|null $show       nama variabel Alpine penanda modal terbuka
 * @var string|null $size       max-w-lg | max-w-2xl | max-w-3xl
 */
$titleExpr ??= null;
$subtitle  ??= null;
$show      ??= 'modalOpen';
$size      ??= 'max-w-lg';
?>
<div x-show="<?= esc($show, 'attr') ?>" x-cloak class="fixed inset-0 z-[65] overflow-y-auto" role="dialog" aria-modal="true"
     @keydown.escape.window="<?= esc($show, 'attr') ?> = false">
    <div x-show="<?= esc($show, 'attr') ?>" x-transition.opacity class="fixed inset-0 bg-ink-950/50 backdrop-blur-sm" @click="<?= esc($show, 'attr') ?> = false" aria-hidden="true"></div>
    <div class="flex min-h-full items-end justify-center p-4 sm:items-center sm:p-6">
        <div x-show="<?= esc($show, 'attr') ?>"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="relative w-full <?= esc($size, 'attr') ?> overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200">
            <form method="post" :action="<?= esc($action, 'attr') ?>" @submit="submitting = true" novalidate>
                <?= csrf_field() ?>
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900" <?= $titleExpr ? 'x-text="' . esc($titleExpr, 'attr') . '"' : '' ?>><?= esc($title) ?></h3>
                        <?php if ($subtitle): ?>
                            <p class="mt-1 text-sm text-slate-500"><?= esc($subtitle) ?></p>
                        <?php endif ?>
                    </div>
                    <button type="button" class="-m-1.5 rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700" @click="<?= esc($show, 'attr') ?> = false" aria-label="Tutup">
                        <?= icon('x', 'size-5') ?>
                    </button>
                </div>
                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">
