<?php
/**
 * Tabel data responsif: tabel di ≥ md, kartu bertumpuk di layar kecil.
 *
 * @var list<array{label: string, align?: 'left'|'right'|'center', class?: string, mobile?: bool}> $columns
 *      `mobile => false` menyembunyikan kolom pada tampilan kartu.
 * @var list<list<string>> $rows     isi sel berupa HTML yang SUDAH di-escape oleh pemanggil
 * @var string|null        $title    judul di header kartu
 * @var string|null        $subtitle
 * @var string|null        $toolbar  HTML (filter, tombol) di kanan header
 * @var string|null        $footer   HTML (mis. pagination)
 * @var array{title?: string, description?: string, icon?: string, action?: string}|null $empty
 * @var list<string>|null  $rowClasses kelas tambahan per baris (mis. highlight warning)
 */
$title      ??= null;
$subtitle   ??= null;
$toolbar    ??= null;
$footer     ??= null;
$empty      ??= [];
$rowClasses ??= [];

$alignClass = static fn (array $column): string => match ($column['align'] ?? 'left') {
    'right'  => 'text-right',
    'center' => 'text-center',
    default  => 'text-left',
};
?>
<div class="card overflow-hidden">
    <?php if ($title || $toolbar): ?>
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <?php if ($title): ?>
                    <h2 class="text-base font-semibold text-slate-900"><?= esc($title) ?></h2>
                <?php endif ?>
                <?php if ($subtitle): ?>
                    <p class="mt-0.5 text-sm text-slate-500"><?= esc($subtitle) ?></p>
                <?php endif ?>
            </div>
            <?php if ($toolbar): ?>
                <div class="flex flex-wrap items-center gap-2"><?= $toolbar ?></div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <?php if ($rows === []): ?>
        <?= component('empty_state', [
            'title'       => $empty['title'] ?? 'Belum ada data',
            'description' => $empty['description'] ?? null,
            'icon'        => $empty['icon'] ?? 'inbox',
            'action'      => $empty['action'] ?? null,
            'compact'     => true,
        ]) ?>
    <?php else: ?>
        <!-- Desktop -->
        <div class="hidden max-h-[70vh] overflow-auto md:block">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th scope="col" class="border-b border-slate-200 px-5 py-3 text-xs font-semibold tracking-wider whitespace-nowrap text-slate-500 uppercase <?= $alignClass($column) ?> <?= esc($column['class'] ?? '', 'attr') ?>">
                                <?= esc($column['label']) ?>
                            </th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $index => $row): ?>
                        <tr class="transition-colors hover:bg-slate-50/80 <?= esc($rowClasses[$index] ?? '', 'attr') ?>">
                            <?php foreach ($row as $cellIndex => $cell): ?>
                                <td class="px-5 py-3.5 align-middle text-slate-700 <?= $alignClass($columns[$cellIndex] ?? []) ?> <?= ($columns[$cellIndex]['align'] ?? '') === 'right' ? 'tabular-nums' : '' ?> <?= esc($columns[$cellIndex]['class'] ?? '', 'attr') ?>">
                                    <?= $cell ?>
                                </td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile: kartu bertumpuk -->
        <ul class="divide-y divide-slate-100 md:hidden">
            <?php foreach ($rows as $index => $row): ?>
                <li class="space-y-2 px-4 py-4 <?= esc($rowClasses[$index] ?? '', 'attr') ?>">
                    <?php foreach ($row as $cellIndex => $cell): ?>
                        <?php if (($columns[$cellIndex]['mobile'] ?? true) === false) {
                            continue;
                        } ?>
                        <?php if ($cellIndex === 0): ?>
                            <div class="text-sm"><?= $cell ?></div>
                        <?php else: ?>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-xs font-medium text-slate-500"><?= esc($columns[$cellIndex]['label'] ?? '') ?></span>
                                <span class="text-right text-slate-700 tabular-nums"><?= $cell ?></span>
                            </div>
                        <?php endif ?>
                    <?php endforeach ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($footer): ?>
        <div class="border-t border-slate-100 px-5 py-3"><?= $footer ?></div>
    <?php endif ?>
</div>
