<?php
/**
 * @var array<string, array<string, string>> $reports
 * @var list<array<string, mixed>>           $periods
 * @var array<string, mixed>|null            $latest
 * @var array<string, int>|null              $summary total rekap periode terbaru
 */
$this->extend('layouts/app');
$this->section('content');

$tones = [
    'payroll'    => 'from-brand-500 to-violet-600',
    'bpjs'       => 'from-emerald-500 to-teal-600',
    'pph21'      => 'from-violet-500 to-fuchsia-600',
    'attendance' => 'from-sky-500 to-blue-600',
    'cost-trend' => 'from-amber-400 to-orange-500',
];
?>
<?= component('page_header', [
    'eyebrow'  => 'Payroll',
    'title'    => 'Laporan',
    'subtitle' => 'Rekap gaji, iuran BPJS, PPh 21, kehadiran, dan tren biaya. Semua laporan bisa diekspor ke CSV dan Excel (XLSX).',
]) ?>

<?php if ($latest !== null && $summary !== null): ?>
    <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan periode terbaru">
        <?php foreach ([
            ['Total pendapatan', $summary['gross'], $latest['employees'] . ' karyawan'],
            ['PPh 21 dipotong', $summary['pph21'], 'Disetor paling lambat tgl 15 bulan berikutnya'],
            ['Iuran BPJS', $summary['bpjs_company'] + $summary['bpjs_employee'], 'Perusahaan ' . rupiah($summary['bpjs_company'])],
            ['Biaya perusahaan', $summary['employer_cost'], 'Pendapatan + BPJS perusahaan'],
        ] as $i => [$label, $value, $hint]): ?>
            <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?> · <?= esc($latest['name']) ?></p>
                <p class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= rupiah($value) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= esc($hint) ?></p>
            </div>
        <?php endforeach ?>
    </section>
<?php endif ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($reports as $key => $report): ?>
        <a href="<?= url_to($report['route']) ?>" class="card group relative flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-elevated focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none">
            <div class="flex items-start justify-between gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br <?= $tones[$key] ?? $tones['payroll'] ?> text-white shadow-lg ring-1 ring-white/20">
                    <?= icon($report['icon'], 'size-5') ?>
                </span>
                <span class="flex gap-1">
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">CSV</span>
                    <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">XLSX</span>
                </span>
            </div>
            <h2 class="mt-4 font-semibold text-slate-900 group-hover:text-brand-700"><?= esc($report['label']) ?></h2>
            <p class="mt-1 flex-1 text-sm text-slate-500"><?= esc($report['description']) ?></p>
            <p class="mt-4 flex items-center gap-1 text-xs font-semibold text-brand-600">
                <?= $report['scope'] === 'period' ? 'Per periode payroll' : 'Per bulan' ?>
                <?= icon('arrow-right', 'size-3.5 transition group-hover:translate-x-0.5') ?>
            </p>
        </a>
    <?php endforeach ?>
</div>

<?php if ($periods === []): ?>
    <div class="card mt-6">
        <?= component('empty_state', [
            'title'       => 'Belum ada payroll yang di-generate',
            'description' => 'Laporan gaji, BPJS, dan PPh 21 tersedia setelah payroll suatu periode di-generate.',
            'icon'        => 'chart-column',
            'action'      => '<a href="' . url_to('payroll.index') . '" class="btn btn-primary">' . icon('banknote', 'size-4') . ' Ke Periode Payroll</a>',
        ]) ?>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
