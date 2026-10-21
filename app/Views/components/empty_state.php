<?php
/**
 * Tampilan saat data kosong.
 *
 * @var string      $title
 * @var string|null $description
 * @var string|null $icon   ikon Lucide
 * @var string|null $action HTML tombol (sudah aman)
 * @var bool|null   $compact
 */
$icon        ??= 'inbox';
$description ??= null;
$action      ??= null;
$compact     ??= false;
?>
<div class="flex flex-col items-center justify-center text-center <?= $compact ? 'px-6 py-10' : 'px-6 py-16' ?>">
    <div class="relative">
        <div class="absolute inset-0 -m-3 rounded-full bg-brand-100/60 blur-xl" aria-hidden="true"></div>
        <span class="relative flex size-14 items-center justify-center rounded-2xl bg-white text-brand-500 shadow-card ring-1 ring-slate-200">
            <?= icon($icon, 'size-6') ?>
        </span>
    </div>
    <h3 class="mt-5 text-base font-semibold text-slate-900"><?= esc($title) ?></h3>
    <?php if ($description): ?>
        <p class="mt-1.5 max-w-sm text-sm text-slate-500"><?= esc($description) ?></p>
    <?php endif ?>
    <?php if ($action): ?>
        <div class="mt-6"><?= $action ?></div>
    <?php endif ?>
</div>
