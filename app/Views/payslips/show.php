<?php
/**
 * Slip gaji web: dokumen A4 dengan tombol Cetak & Unduh PDF.
 *
 * @var array<string, mixed>                       $payroll
 * @var array<string, mixed>                       $period
 * @var array<string, mixed>                       $employee  snapshot karyawan saat payroll dihitung
 * @var array<string, list<array<string, mixed>>>  $items
 * @var array<string, string|null>                 $company
 * @var bool                                       $final
 * @var string                                     $number
 * @var string                                     $backUrl
 */

use App\Models\EmployeeModel;
use App\Models\PayrollPeriodModel;
use App\Services\Payroll\PayrollItemPresenter;

$this->extend('layouts/app');
$this->section('content');

$hours    = static fn (float|int|string $h): string => rtrim(rtrim(number_id((float) $h, 2), '0'), ',');
$earnings = $items['earning'] ?? [];
$deducts  = $items['deduction'] ?? [];
$company2 = $items['company_contribution'] ?? [];
$pdfUrl   = url_to('payslips.pdf', $payroll['id']);

$info = [
    ['Nama', $employee['full_name'] ?? '—'],
    ['NIP', $employee['nip'] ?? '—'],
    ['Jabatan', $employee['position'] ?? '—'],
    ['Departemen', $employee['department'] ?? '—'],
    ['Status kerja', EmployeeModel::EMPLOYMENT_TYPES[$employee['employment_type'] ?? ''] ?? '—'],
    ['Status PTKP', $employee['ptkp_status'] ?? '—'],
];
$info2 = [
    ['Tanggal bayar', tanggal_id($period['pay_date'], 'long')],
    ['Kehadiran', $payroll['present_days'] . ' dari ' . $payroll['active_workdays'] . ' hari kerja' . ($payroll['active_workdays'] < $payroll['workdays'] ? ' (prorata)' : '')],
    ['Lembur', $payroll['overtime_hours'] > 0 ? $hours($payroll['overtime_hours']) . ' jam' : '—'],
    ['Bank', $employee['bank_name'] ?? '—'],
    ['No. rekening', mask_id($employee['bank_account_no'] ?? '', 3, 4)],
    ['NPWP', ($employee['npwp'] ?? '') !== '' ? mask_id($employee['npwp']) : '—'],
];

$lines = static function (array $rows): string {
    $html = '';

    foreach ($rows as $item) {
        $description = PayrollItemPresenter::describe($item);
        $html .= '<li class="flex items-start justify-between gap-3 py-2">'
            . '<span class="min-w-0"><span class="block text-[13px] text-slate-800">' . esc($item['name']) . '</span>'
            . ($description ? '<span class="block text-[11px] text-slate-500">' . esc($description) . '</span>' : '')
            . '</span><span class="shrink-0 text-[13px] font-medium whitespace-nowrap text-slate-900 tabular-nums">' . rupiah($item['amount']) . '</span></li>';
    }

    return $html === '' ? '<li class="py-2 text-[13px] text-slate-500">—</li>' : $html;
};
?>
<style>
    @media print {
        @page { size: A4; margin: 10mm; }
        html, body { background: #fff !important; }
        body * { visibility: hidden; }
        #payslip, #payslip * { visibility: visible; }
        #payslip { position: absolute; inset: 0 auto auto 0; width: 100%; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="mx-auto max-w-[210mm]">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <a href="<?= esc($backUrl, 'attr') ?>" class="btn btn-ghost -ml-3 self-start"><?= icon('arrow-left', 'size-4') ?> Kembali</a>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary" onclick="window.print()"><?= icon('printer', 'size-4') ?> Cetak</button>
            <a href="<?= esc($pdfUrl, 'attr') ?>" class="btn btn-primary"><?= icon('file-down', 'size-4') ?> Unduh PDF</a>
        </div>
    </div>

    <?php if (! $final): ?>
        <div class="mb-5 flex gap-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-800 ring-1 ring-amber-200 print:hidden">
            <?= icon('triangle-alert', 'mt-0.5 size-4 shrink-0') ?>
            <p>Pratinjau: payroll <?= esc($period['name']) ?> masih berstatus <span class="font-semibold"><?= esc(PayrollPeriodModel::STATUSES[$period['status']]) ?></span>. Slip baru dapat dilihat karyawan setelah periode dibayar.</p>
        </div>
    <?php endif ?>

    <article id="payslip" class="relative overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200 print:rounded-none print:shadow-none print:ring-0" aria-label="Slip gaji <?= esc(($employee['full_name'] ?? '') . ' ' . $period['name'], 'attr') ?>">
        <div class="h-1.5 bg-linear-to-r from-brand-600 via-violet-600 to-fuchsia-600" aria-hidden="true"></div>

        <?php if (! $final): ?>
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="-rotate-30 text-[7rem] font-black tracking-widest text-slate-900/5">DRAFT</span>
            </div>
        <?php endif ?>

        <div class="relative px-6 py-7 sm:px-10">
            <!-- Kop -->
            <header class="flex flex-col gap-5 border-b border-slate-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <img src="<?= esc($company['logo'], 'attr') ?>" alt="" class="size-12 shrink-0 rounded-xl">
                    <div>
                        <p class="text-lg font-bold tracking-tight text-slate-900"><?= esc($company['name']) ?></p>
                        <p class="mt-0.5 max-w-sm text-xs leading-relaxed text-slate-500"><?= esc($company['address']) ?></p>
                        <p class="text-xs text-slate-500"><?= esc(implode(' · ', array_filter([$company['phone'], $company['website'], $company['npwp'] ? 'NPWP ' . $company['npwp'] : null]))) ?></p>
                    </div>
                </div>
                <div class="sm:text-right">
                    <p class="text-xs font-bold tracking-[0.2em] text-brand-600 uppercase">Slip Gaji</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900"><?= esc($period['name']) ?></p>
                    <p class="mt-1 font-mono text-xs text-slate-500"><?= esc($number) ?></p>
                    <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600"><?= icon('lock', 'size-3') ?> Rahasia</p>
                </div>
            </header>

            <!-- Data karyawan -->
            <section class="grid gap-x-10 gap-y-0 border-b border-slate-200 py-5 sm:grid-cols-2" aria-label="Data karyawan">
                <?php foreach ([$info, $info2] as $column): ?>
                    <dl class="divide-y divide-slate-100">
                        <?php foreach ($column as [$label, $value]): ?>
                            <div class="flex justify-between gap-4 py-1.5 text-[13px]">
                                <dt class="text-slate-500"><?= esc($label) ?></dt>
                                <dd class="text-right font-medium text-slate-900"><?= esc((string) $value) ?></dd>
                            </div>
                        <?php endforeach ?>
                    </dl>
                <?php endforeach ?>
            </section>

            <!-- Pendapatan & potongan -->
            <section class="grid gap-x-10 py-5 sm:grid-cols-2">
                <?php foreach ([['Pendapatan', $earnings, $payroll['total_earning'], 'text-emerald-700'], ['Potongan', $deducts, $payroll['total_deduction'], 'text-rose-600']] as [$heading, $rows, $total, $color]): ?>
                    <div class="flex flex-col">
                        <h2 class="border-b-2 border-slate-900 pb-1.5 text-xs font-bold tracking-wider text-slate-900 uppercase"><?= $heading ?></h2>
                        <ul class="flex-1 divide-y divide-slate-100"><?= $lines($rows) ?></ul>
                        <p class="mt-1 flex justify-between border-t border-slate-300 pt-2 text-[13px] font-bold">
                            <span>Total <?= strtolower($heading) ?></span><span class="tabular-nums <?= $color ?>"><?= rupiah($total) ?></span>
                        </p>
                    </div>
                <?php endforeach ?>
            </section>

            <!-- Take home pay -->
            <section class="rounded-2xl bg-linear-to-r from-brand-600 via-violet-600 to-fuchsia-600 p-5 text-white sm:flex sm:items-center sm:justify-between sm:gap-6">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-white/80 uppercase">Take home pay</p>
                    <p class="mt-1 text-xs text-white/85 italic"><?= esc(terbilang_rupiah(max(0, (int) $payroll['take_home_pay']))) ?></p>
                </div>
                <p class="mt-2 text-3xl font-bold tracking-tight whitespace-nowrap tabular-nums sm:mt-0"><?= rupiah($payroll['take_home_pay']) ?></p>
            </section>

            <!-- Informasi pajak & kontribusi perusahaan -->
            <section class="mt-5 grid gap-x-10 gap-y-4 text-[12px] sm:grid-cols-2">
                <div>
                    <h2 class="text-xs font-bold tracking-wider text-slate-500 uppercase">Informasi PPh 21</h2>
                    <dl class="mt-1.5 space-y-1">
                        <div class="flex justify-between"><dt class="text-slate-500">Penghasilan bruto</dt><dd class="font-medium text-slate-800 tabular-nums"><?= rupiah($payroll['bruto_pph']) ?></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Metode</dt><dd class="font-medium text-slate-800"><?= $payroll['ter_rate'] === null ? 'Perhitungan setahun' : 'TER kategori ' . esc($payroll['ter_category']) . ' · ' . esc(percent_id($payroll['ter_rate'])) ?></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">PPh 21 dipotong</dt><dd class="font-medium text-slate-800 tabular-nums"><?= rupiah($payroll['pph21']) ?></dd></div>
                    </dl>
                </div>
                <div>
                    <h2 class="text-xs font-bold tracking-wider text-slate-500 uppercase">Dibayar perusahaan (tidak dipotong)</h2>
                    <dl class="mt-1.5 space-y-1">
                        <?php foreach ($company2 as $item): ?>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500"><?= esc(str_replace(' (Perusahaan)', '', $item['name'])) ?></dt><dd class="font-medium text-slate-800 tabular-nums"><?= rupiah($item['amount']) ?></dd></div>
                        <?php endforeach ?>
                        <?php if ($company2 === []): ?>
                            <p class="text-slate-500">—</p>
                        <?php endif ?>
                    </dl>
                </div>
            </section>

            <footer class="mt-7 flex flex-col gap-1 border-t border-dashed border-slate-300 pt-4 text-[11px] text-slate-500 sm:flex-row sm:justify-between">
                <p>Dokumen ini diterbitkan secara elektronik oleh <?= esc($company['name']) ?> dan sah tanpa tanda tangan.</p>
                <p class="shrink-0"><?= esc(($company['city'] ? $company['city'] . ', ' : '') . tanggal_id($period['pay_date'], 'long')) ?></p>
            </footer>
        </div>
    </article>
</div>
<?php $this->endSection() ?>
