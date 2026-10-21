<?php
/**
 * @var list<array<string, mixed>>                                   $slips   slip final tahun terpilih
 * @var list<int>                                                    $years
 * @var int                                                          $year
 * @var array{thp: int, earning: int, pph21: int, months: int}       $totals
 */
$this->extend('layouts/app');
$this->section('content');
?>
<?= component('page_header', [
    'eyebrow'  => 'Self-service',
    'title'    => 'Slip Gaji Saya',
    'subtitle' => 'Slip tersedia setelah payroll periode tersebut dibayarkan. Unduh PDF untuk arsip pribadi atau keperluan administrasi.',
    'actions'  => count($years) > 1
        ? '<div class="flex items-center gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200" role="tablist" aria-label="Tahun">'
            . implode('', array_map(static fn (int $y): string => '<a href="' . esc(url_to('my.payslips') . '?year=' . $y, 'attr') . '" role="tab" aria-selected="' . ($y === $year ? 'true' : 'false') . '" class="rounded-lg px-3 py-1.5 text-sm font-semibold ' . ($y === $year ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100') . '">' . $y . '</a>', $years))
            . '</div>'
        : null,
]) ?>

<?php if ($slips === []): ?>
    <div class="card">
        <?= component('empty_state', [
            'title'       => 'Belum ada slip gaji',
            'description' => 'Slip gaji akan muncul di sini setelah payroll periode pertama Anda dibayarkan.',
            'icon'        => 'receipt-text',
            'action'      => '<a href="' . url_to('my.dashboard') . '" class="btn btn-secondary">' . icon('arrow-left', 'size-4') . ' Kembali ke Dashboard</a>',
        ]) ?>
    </div>
<?php else: ?>
    <section class="card mb-6 grid overflow-hidden sm:grid-cols-3" aria-label="Ringkasan tahun <?= $year ?>">
        <?php foreach ([
            ['Total gaji diterima ' . $year, rupiah($totals['thp']), $totals['months'] . ' slip gaji'],
            ['Penghasilan bruto', rupiah($totals['earning']), 'Sebelum potongan'],
            ['PPh 21 dipotong', rupiah($totals['pph21']), 'Rujukan pengisian SPT Tahunan'],
        ] as $i => [$label, $value, $hint]): ?>
            <div class="p-5 <?= $i > 0 ? 'border-t border-slate-100 sm:border-t-0 sm:border-l' : '' ?>">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                <p class="mt-1 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= esc($value) ?></p>
                <p class="mt-0.5 text-xs text-slate-500"><?= esc($hint) ?></p>
            </div>
        <?php endforeach ?>
    </section>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($slips as $index => $slip): ?>
            <article class="card group relative flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-elevated">
                <?php if ($index === 0): ?>
                    <span class="absolute inset-x-0 top-0 h-1 bg-linear-to-r from-brand-600 via-violet-600 to-fuchsia-600" aria-hidden="true"></span>
                <?php endif ?>
                <div class="flex items-start justify-between gap-3 p-5 pb-3">
                    <div class="flex items-center gap-3.5">
                        <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl <?= $index === 0 ? 'bg-linear-to-br from-brand-600 to-violet-600 text-white shadow-glow' : 'bg-slate-100 text-slate-700' ?>">
                            <span class="text-[10px] font-bold uppercase"><?= esc(bulan_id($slip['month'], true)) ?></span>
                            <span class="text-xs font-bold"><?= $slip['year'] ?></span>
                        </span>
                        <div>
                            <h2 class="font-semibold text-slate-900">
                                <a href="<?= url_to('payslips.show', $slip['id']) ?>" class="after:absolute after:inset-0 focus-visible:outline-none"><?= esc($slip['period_name']) ?></a>
                            </h2>
                            <p class="text-xs text-slate-500">Dibayar <?= esc(tanggal_id($slip['pay_date'])) ?></p>
                        </div>
                    </div>
                    <?= status_badge($slip['period_status']) ?>
                </div>
                <div class="px-5">
                    <p class="text-xs text-slate-500">Take home pay</p>
                    <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums"><?= rupiah($slip['take_home_pay']) ?></p>
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-px border-y border-slate-100 bg-slate-100 text-center">
                    <?php foreach ([['Pendapatan', rupiah($slip['total_earning'])], ['Potongan', rupiah($slip['total_deduction'])], ['Hadir', $slip['present_days'] . '/' . $slip['active_workdays'] . ' hari']] as [$label, $value]): ?>
                        <div class="bg-white px-2 py-2.5">
                            <dt class="text-[11px] text-slate-500"><?= esc($label) ?></dt>
                            <dd class="mt-0.5 truncate text-xs font-semibold text-slate-900 tabular-nums"><?= esc($value) ?></dd>
                        </div>
                    <?php endforeach ?>
                </dl>
                <div class="relative z-10 mt-auto flex gap-2 p-4">
                    <a href="<?= url_to('payslips.show', $slip['id']) ?>" class="btn btn-secondary flex-1 py-2"><?= icon('eye', 'size-4') ?> Lihat</a>
                    <a href="<?= url_to('payslips.pdf', $slip['id']) ?>" class="btn btn-primary flex-1 py-2"><?= icon('file-down', 'size-4') ?> Unduh PDF</a>
                </div>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
