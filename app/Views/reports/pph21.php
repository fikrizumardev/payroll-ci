<?php
/**
 * @var array<string, mixed>|null $period
 * @var bool                      $isFinal
 * @var array<string, mixed>|null $pph hasil ReportService::pph21()
 */

use App\Services\Report\ReportService;

$this->extend('layouts/app');
$this->section('content');

$pph ??= null;
?>
<?= $this->include('reports/_header') ?>

<?php if ($period === null): ?>
    <div class="card">
        <?= component('empty_state', ['title' => 'Belum ada payroll yang di-generate', 'description' => 'Laporan PPh 21 tersedia setelah payroll suatu periode di-generate.', 'icon' => 'landmark']) ?>
    </div>
<?php else: ?>
    <?= $this->include('reports/_period_filter') ?>
    <?php
    $total    = $pph['total'];
    $withheld = count(array_filter($pph['rows'], static fn (array $row): bool => $row['pph21'] > 0));
    $deadline = (new DateTimeImmutable(sprintf('%04d-%02d-15', $period['year'], $period['month'])))->modify('+1 month');
    ?>

    <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan PPh 21">
        <?php foreach ([
            ['Penghasilan bruto', rupiah($total['bruto']), count($pph['rows']) . ' pegawai · termasuk premi BPJS perusahaan'],
            $total['refund'] > 0
                ? ['PPh 21 disetor (neto)', rupiah($total['net']), 'Dipotong ' . rupiah($total['pph21']) . ' − lebih potong dikembalikan ' . rupiah($total['refund'])]
                : ['PPh 21 dipotong', rupiah($total['pph21']), $withheld . ' pegawai dipotong · ' . (count($pph['rows']) - $withheld) . ' nihil'],
            ['Tarif efektif rata-rata', percent_id($total['bruto'] > 0 ? round($total['pph21'] / $total['bruto'] * 100, 2) : 0), 'PPh 21 ÷ penghasilan bruto'],
            ['Batas setor & lapor', tanggal_id($deadline->format('Y-m-d')), 'Setor tgl 15, lapor SPT Masa tgl 20'],
        ] as $i => [$label, $value, $hint]): ?>
            <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                <p class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= esc($value) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= esc($hint) ?></p>
            </div>
        <?php endforeach ?>
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <section class="card p-5" aria-labelledby="category-title">
            <h2 id="category-title" class="text-base font-semibold text-slate-900">Per Kategori TER</h2>
            <p class="mt-0.5 text-sm text-slate-500">PP 58/2023 — A: TK/0, TK/1, K/0 · B: TK/2, TK/3, K/1, K/2 · C: K/3</p>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($pph['categories'] as $category => $info): ?>
                    <li class="flex items-center gap-3 py-2.5">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-xs font-bold text-violet-700 ring-1 ring-violet-100"><?= esc(mb_substr((string) $category, 0, 3)) ?></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900"><?= $category === 'Setahun' ? 'Perhitungan setahun' : 'Kategori ' . esc((string) $category) ?></p>
                            <p class="text-xs text-slate-500"><?= $info['employees'] ?> pegawai · bruto <?= rupiah($info['bruto']) ?></p>
                        </div>
                        <span class="text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums"><?= rupiah($info['pph21']) ?></span>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>

        <section class="card p-5 lg:col-span-2" aria-labelledby="ebupot-title">
            <h2 id="ebupot-title" class="text-base font-semibold text-slate-900">Ekspor ke e-Bupot 21</h2>
            <ul class="mt-3 space-y-2.5 text-sm text-slate-600">
                <li class="flex gap-2.5"><?= icon('circle-check', 'mt-0.5 size-4 shrink-0 text-emerald-600') ?><span>File ekspor memuat NIK & NPWP lengkap, status PTKP, kode objek pajak <span class="font-semibold text-slate-800"><?= ReportService::TAX_OBJECT_CODE ?></span> (pegawai tetap), penghasilan bruto, kategori & tarif TER, serta PPh 21 dipotong per pegawai.</span></li>
                <li class="flex gap-2.5"><?= icon('circle-check', 'mt-0.5 size-4 shrink-0 text-emerald-600') ?><span>Penghasilan bruto mencakup premi JKK, JKM, dan BPJS Kesehatan porsi perusahaan, dikurangi potongan alpha & keterlambatan.</span></li>
                <li class="flex gap-2.5"><?= icon('circle-check', 'mt-0.5 size-4 shrink-0 text-emerald-600') ?><span>Masa pajak terakhir (Desember atau bulan keluar) memakai tarif Pasal 17 setahun; kelebihan potong dikembalikan lewat slip gaji.</span></li>
                <li class="flex gap-2.5"><?= icon('shield-alert', 'mt-0.5 size-4 shrink-0 text-amber-600') ?><span>Di layar ini NIK/NPWP disamarkan. File ekspor berisi data pribadi lengkap — setiap ekspor tercatat di audit log.</span></li>
            </ul>
        </section>
    </div>

    <?php
    $rows = [];

    foreach ($pph['rows'] as $row) {
        $rows[] = [
            '<span class="block font-semibold whitespace-nowrap text-slate-900">' . esc($row['name']) . '</span><span class="text-xs whitespace-nowrap text-slate-500">' . esc($row['nip']) . ' · ' . esc($row['position']) . '</span>',
            '<span class="font-mono text-xs text-slate-600">' . esc(mask_id($row['nik'])) . '</span>',
            '<span class="font-mono text-xs text-slate-600">' . esc(mask_id($row['npwp'])) . '</span>',
            esc($row['ptkp_status']),
            rupiah($row['bruto']),
            $row['method'] === 'ter'
                ? '<span class="font-semibold text-slate-800">' . esc((string) $row['ter_category']) . '</span> · ' . esc(percent_id($row['ter_rate']))
                : '<span class="rounded-md bg-violet-50 px-1.5 py-0.5 text-xs font-semibold text-violet-700">Pasal 17 setahun</span>',
            '<span class="font-semibold text-slate-900">' . rupiah($row['pph21']) . '</span>'
                . ($row['refund'] > 0 ? '<span class="block text-[11px] font-medium text-emerald-700">lebih potong dikembalikan ' . rupiah($row['refund']) . '</span>' : ''),
        ];
    }

    $rows[] = [
        '<span class="font-bold text-slate-900">Total ' . count($pph['rows']) . ' pegawai</span>', '', '', '',
        '<span class="font-bold text-slate-900">' . rupiah($total['bruto']) . '</span>', '',
        '<span class="font-bold text-slate-900">' . rupiah($total['pph21']) . '</span>'
            . ($total['refund'] > 0 ? '<span class="block text-[11px] font-medium text-slate-600">neto disetor ' . rupiah($total['net']) . '</span>' : ''),
    ];
    ?>
    <?= component('data_table', [
        'title'      => 'Daftar Pemotongan PPh 21 · Masa ' . $period['name'],
        'subtitle'   => 'Metode TER bulanan (PMK 168/2023). NIK/NPWP disamarkan.',
        'columns'    => [
            ['label' => 'Pegawai'],
            ['label' => 'NIK', 'mobile' => false],
            ['label' => 'NPWP', 'mobile' => false],
            ['label' => 'PTKP', 'align' => 'center'],
            ['label' => 'Bruto', 'align' => 'right'],
            ['label' => 'TER · Tarif', 'align' => 'center'],
            ['label' => 'PPh 21', 'align' => 'right'],
        ],
        'rows'       => $rows,
        'rowClasses' => [count($rows) - 1 => 'bg-brand-50/70 hover:bg-brand-50/70'],
    ]) ?>
<?php endif ?>
<?php $this->endSection() ?>
