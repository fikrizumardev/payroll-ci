<?php
/**
 * Judul halaman dengan subjudul dan area tombol aksi.
 *
 * @var string      $title
 * @var string|null $subtitle
 * @var string|null $eyebrow  teks kecil di atas judul
 * @var string|null $actions  HTML tombol aksi (sudah aman)
 */
$subtitle ??= null;
$eyebrow  ??= null;
$actions  ??= null;
?>
<div class="mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <?php if ($eyebrow): ?>
            <p class="text-sm font-semibold text-brand-600"><?= esc($eyebrow) ?></p>
        <?php endif ?>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-[1.75rem]"><?= esc($title) ?></h1>
        <?php if ($subtitle): ?>
            <p class="mt-1.5 max-w-2xl text-sm text-slate-500"><?= esc($subtitle) ?></p>
        <?php endif ?>
    </div>
    <?php if ($actions): ?>
        <div class="flex shrink-0 flex-wrap items-center gap-2"><?= $actions ?></div>
    <?php endif ?>
</div>
