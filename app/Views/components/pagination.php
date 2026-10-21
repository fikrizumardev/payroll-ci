<?php
/**
 * Template Pager CI4 bergaya Tailwind. Dipakai via $pager->links('default', 'tailwind').
 *
 * @var CodeIgniter\Pager\PagerRenderer $pager
 */
$pager->setSurroundCount(1);
?>
<nav class="flex items-center justify-between gap-4" aria-label="Navigasi halaman">
    <p class="hidden text-sm text-slate-500 sm:block">
        Halaman <span class="font-semibold text-slate-700"><?= $pager->getCurrentPageNumber() ?></span>
        dari <span class="font-semibold text-slate-700"><?= $pager->getPageCount() ?></span>
    </p>

    <ul class="flex items-center gap-1">
        <li>
            <?php if ($pager->hasPreviousPage()): ?>
                <a href="<?= $pager->getPreviousPage() ?>" class="flex size-9 items-center justify-center rounded-lg text-slate-500 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900" aria-label="Halaman sebelumnya">
                    <?= icon('chevron-left', 'size-4') ?>
                </a>
            <?php else: ?>
                <span class="flex size-9 items-center justify-center rounded-lg text-slate-300 ring-1 ring-slate-100" aria-hidden="true"><?= icon('chevron-left', 'size-4') ?></span>
            <?php endif ?>
        </li>

        <?php if ($pager->hasPrevious()): ?>
            <li><a href="<?= $pager->getFirst() ?>" class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium text-slate-600 hover:bg-slate-100">1</a></li>
            <li class="px-1 text-slate-500" aria-hidden="true">…</li>
        <?php endif ?>

        <?php foreach ($pager->links() as $link): ?>
            <li>
                <a href="<?= $link['uri'] ?>" <?= $link['active'] ? 'aria-current="page"' : '' ?>
                   class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-semibold tabular-nums <?= $link['active'] ? 'bg-linear-to-br from-brand-600 to-violet-600 text-white shadow-glow' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <?= $link['title'] ?>
                </a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNext()): ?>
            <li class="px-1 text-slate-500" aria-hidden="true">…</li>
            <li><a href="<?= $pager->getLast() ?>" class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium text-slate-600 hover:bg-slate-100"><?= $pager->getPageCount() ?></a></li>
        <?php endif ?>

        <li>
            <?php if ($pager->hasNextPage()): ?>
                <a href="<?= $pager->getNextPage() ?>" class="flex size-9 items-center justify-center rounded-lg text-slate-500 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900" aria-label="Halaman berikutnya">
                    <?= icon('chevron-right', 'size-4') ?>
                </a>
            <?php else: ?>
                <span class="flex size-9 items-center justify-center rounded-lg text-slate-300 ring-1 ring-slate-100" aria-hidden="true"><?= icon('chevron-right', 'size-4') ?></span>
            <?php endif ?>
        </li>
    </ul>
</nav>
