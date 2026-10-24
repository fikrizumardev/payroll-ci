<?php
/**
 * Kepala halaman laporan: judul, tombol ekspor (CSV/XLSX), dan tab antar-laporan.
 *
 * @var array<string, array<string, string>> $reports
 * @var string                               $active
 * @var array<string, string>                $formats
 * @var array<string, scalar>                $filters  query filter aktif (ikut ke URL ekspor)
 * @var bool|null                            $canExport
 */
$canExport ??= true;
$current   = $reports[$active];
$actions   = '';

if ($canExport) {
    foreach ($formats as $format => $label) {
        $url = url_to('reports.export', $active) . '?' . http_build_query($filters + ['format' => $format]);
        $actions .= '<a href="' . esc($url, 'attr') . '" class="btn ' . ($format === 'xlsx' ? 'btn-primary' : 'btn-secondary') . '">'
            . icon($format === 'xlsx' ? 'file-spreadsheet' : 'file-down', 'size-4') . ' Ekspor ' . esc($label) . '</a>';
    }
}
?>
<?= component('page_header', [
    'eyebrow'  => 'Laporan',
    'title'    => 'Laporan ' . $current['label'],
    'subtitle' => $current['description'],
    'actions'  => $actions === '' ? null : $actions,
]) ?>

<nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Jenis laporan">
    <ul class="flex w-max gap-1 rounded-xl bg-slate-200/60 p-1 sm:w-auto">
        <?php foreach ($reports as $key => $report): ?>
            <li>
                <a href="<?= url_to($report['route']) ?>" <?= $key === $active ? 'aria-current="page"' : '' ?>
                   class="flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition <?= $key === $active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-white/60 hover:text-slate-900' ?>">
                    <?= icon($report['icon'], 'size-4 ' . ($key === $active ? 'text-brand-600' : '')) ?>
                    <?= esc($report['label']) ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</nav>
