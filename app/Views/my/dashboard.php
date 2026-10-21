<?php
/**
 * @var array<string, mixed>               $user
 * @var array<string, mixed>               $employee
 * @var string                             $today
 * @var array<string, mixed>|null          $latest      slip final terakhir
 * @var array<string, mixed>|null          $previous
 * @var list<array<string, mixed>>         $recent
 * @var array{thp: int, pph21: int, months: int} $ytd
 * @var list<array<string, mixed>>         $attendance  catatan kehadiran bulan ini
 * @var array<string, int>                 $summary
 * @var int                                $workdaysDue hari kerja s.d. hari ini
 * @var array<string, string>              $holidays    tanggal → nama (bulan ini)
 * @var list<array<string, mixed>>         $upcoming
 * @var array<string, array<string, mixed>> $overtime   status → {total, total_hours}
 * @var list<array<string, mixed>>         $loans
 */

use App\Models\AttendanceModel;
use App\Models\HolidayModel;
use App\Models\LoanModel;

$this->extend('layouts/app');
$this->section('content');

$hour     = (int) date('G');
$greeting = match (true) {
    $hour < 11 => 'Selamat pagi',
    $hour < 15 => 'Selamat siang',
    $hour < 18 => 'Selamat sore',
    default    => 'Selamat malam',
};
$firstName = explode(' ', $employee['full_name'])[0];
$delta     = $latest && $previous && $previous['take_home_pay'] > 0 ? ($latest['take_home_pay'] - $previous['take_home_pay']) / $previous['take_home_pay'] * 100 : null;
$present   = $summary['present'] + $summary['late'];
$recorded  = array_sum($summary) - $summary['holiday'];
$byDate    = array_column($attendance, null, 'date');
$tenure    = (new DateTimeImmutable($employee['join_date']))->diff(new DateTimeImmutable($today));
$first     = new DateTimeImmutable(date('Y-m-01', strtotime($today)));
$approved  = $overtime['approved'] ?? ['total' => 0, 'total_hours' => 0];
$pendingOt = (int) ($overtime['pending']['total'] ?? 0);
?>
<?= component('page_header', [
    'eyebrow'  => tanggal_id($today, 'full'),
    'title'    => "{$greeting}, {$firstName}",
    'subtitle' => 'Ringkasan gaji, kehadiran, dan pinjaman Anda. Data ini hanya dapat dilihat oleh Anda dan tim HR/Finance.',
    'actions'  => '<a href="' . url_to('my.payslips') . '" class="btn btn-secondary">' . icon('receipt-text', 'size-4') . ' Semua Slip Gaji</a>',
]) ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Take home pay terakhir -->
    <section class="relative overflow-hidden rounded-2xl bg-linear-to-br from-brand-600 via-violet-600 to-fuchsia-600 p-6 text-white shadow-elevated lg:col-span-2" aria-labelledby="thp-heading">
        <svg class="pointer-events-none absolute inset-0 size-full opacity-15" aria-hidden="true"><defs><pattern id="my-dots" width="18" height="18" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.2" fill="white"/></pattern></defs><rect width="100%" height="100%" fill="url(#my-dots)"/></svg>
        <div class="relative">
            <?php if ($latest): ?>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p id="thp-heading" class="text-sm font-medium text-white/80">Take home pay terakhir · <?= esc($latest['period_name']) ?></p>
                        <p class="mt-2 text-4xl font-bold tracking-tight tabular-nums sm:text-5xl"><?= rupiah($latest['take_home_pay']) ?></p>
                        <p class="mt-2 text-sm text-white/80">
                            Dibayar <?= esc(tanggal_id($latest['pay_date'], 'long')) ?>
                            <?php if ($delta !== null): ?>
                                · <span class="font-semibold text-white"><?= ($delta >= 0 ? '+' : '') . number_id($delta, 1) ?>%</span> dari <?= esc($previous['period_name']) ?>
                            <?php endif ?>
                        </p>
                    </div>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/25"><?= esc(status_meta($latest['period_status'])['label']) ?></span>
                </div>
                <dl class="mt-6 grid grid-cols-3 gap-3">
                    <?php foreach ([['Pendapatan', $latest['total_earning']], ['Potongan', $latest['total_deduction']], ['PPh 21', $latest['pph21']]] as [$label, $value]): ?>
                        <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/15">
                            <dt class="text-xs text-white/75"><?= esc($label) ?></dt>
                            <dd class="mt-0.5 text-sm font-bold tabular-nums sm:text-base"><?= rupiah($value) ?></dd>
                        </div>
                    <?php endforeach ?>
                </dl>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="<?= url_to('payslips.show', $latest['id']) ?>" class="btn bg-white text-brand-700 shadow-sm hover:bg-brand-50"><?= icon('receipt-text', 'size-4') ?> Lihat Slip</a>
                    <a href="<?= url_to('payslips.pdf', $latest['id']) ?>" class="btn bg-white/10 text-white ring-1 ring-white/30 hover:bg-white/20"><?= icon('file-down', 'size-4') ?> Unduh PDF</a>
                </div>
            <?php else: ?>
                <p id="thp-heading" class="text-sm font-medium text-white/80">Take home pay terakhir</p>
                <p class="mt-3 text-2xl font-bold">Belum ada slip gaji</p>
                <p class="mt-2 max-w-md text-sm text-white/80">Slip gaji tampil di sini setelah payroll periode pertama Anda dibayarkan.</p>
            <?php endif ?>
        </div>
    </section>

    <!-- Profil -->
    <section class="card p-6" aria-labelledby="profile-heading">
        <div class="flex items-center gap-4">
            <?= avatar($employee['full_name'], 'size-14 text-base') ?>
            <div class="min-w-0">
                <h2 id="profile-heading" class="truncate text-base font-semibold text-slate-900"><?= esc($employee['full_name']) ?></h2>
                <p class="truncate text-sm text-slate-500"><?= esc($employee['position_name']) ?></p>
                <p class="mt-1"><?= status_badge($employee['employment_type']) ?></p>
            </div>
        </div>
        <dl class="mt-5 divide-y divide-slate-100 text-sm">
            <?php foreach ([
                ['NIP', $employee['nip']],
                ['Departemen', $employee['department_name']],
                ['Masa kerja', ($tenure->y > 0 ? $tenure->y . ' th ' : '') . $tenure->m . ' bln · sejak ' . tanggal_id($employee['join_date'])],
                ['Status PTKP', $employee['ptkp_status']],
                ['Rekening gaji', trim(($employee['bank_name'] ?? '') . ' ' . mask_id($employee['bank_account_no'] ?? '', 3, 4))],
            ] as [$label, $value]): ?>
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-slate-500"><?= esc($label) ?></dt>
                    <dd class="text-right font-medium text-slate-900"><?= esc((string) $value) ?></dd>
                </div>
            <?php endforeach ?>
        </dl>
        <p class="mt-3 text-xs text-slate-500">Data tidak sesuai? Hubungi HR untuk pembaruan.</p>
    </section>

    <!-- Kehadiran bulan ini -->
    <section class="card p-6 lg:col-span-2" aria-labelledby="attendance-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="attendance-heading" class="text-base font-semibold text-slate-900">Kehadiran <?= esc(periode_id((int) $first->format('Y'), (int) $first->format('n'))) ?></h2>
            <p class="text-sm text-slate-500"><?= $recorded ?> dari <?= $workdaysDue ?> hari kerja tercatat</p>
        </div>

        <dl class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
            <?php foreach (['present' => 'Hadir', 'late' => 'Terlambat', 'sick' => 'Sakit', 'leave' => 'Cuti', 'permit' => 'Izin', 'absent' => 'Alpha'] as $status => $label): ?>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-100">
                    <dt class="flex items-center gap-1.5 text-[11px] text-slate-500"><span class="size-2 rounded-full <?= tone_classes(status_meta($status)['tone'], 'dot') ?>"></span><?= $label ?></dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900 tabular-nums"><?= $status === 'present' ? $present : $summary[$status] ?></dd>
                </div>
            <?php endforeach ?>
        </dl>

        <div class="mt-5">
            <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-semibold text-slate-500" aria-hidden="true">
                <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day): ?>
                    <span><?= $day ?></span>
                <?php endforeach ?>
            </div>
            <ol class="mt-1.5 grid grid-cols-7 gap-1.5" aria-label="Kalender kehadiran">
                <?php for ($i = 1; $i < (int) $first->format('N'); $i++): ?>
                    <li aria-hidden="true"></li>
                <?php endfor ?>
                <?php for ($d = $first; $d->format('m') === $first->format('m'); $d = $d->modify('+1 day')): ?>
                    <?php
                    $date    = $d->format('Y-m-d');
                    $record  = $byDate[$date] ?? null;
                    $weekend = (int) $d->format('N') >= 6;
                    $label   = tanggal_id($date, 'full') . ': ' . ($record ? AttendanceModel::STATUSES[$record['status']] : ($holidays[$date] ?? ($weekend ? 'Akhir pekan' : ($date > $today ? 'Belum terjadi' : 'Belum tercatat'))));
                    $classes = match (true) {
                        $record !== null          => attendance_cell_class($record['status']),
                        isset($holidays[$date])   => 'bg-rose-50 text-rose-600',
                        $weekend                  => 'bg-slate-50 text-slate-500',
                        $date > $today            => 'text-slate-500 ring-1 ring-slate-100',
                        default                   => 'border border-dashed border-slate-300 text-slate-500',
                    };
                    ?>
                    <li class="flex h-11 flex-col items-center justify-center rounded-lg text-xs font-semibold tabular-nums <?= $classes ?> <?= $date === $today ? 'outline-2 outline-offset-1 outline-brand-500' : '' ?>" title="<?= esc($label, 'attr') ?>">
                        <span><?= $d->format('j') ?></span>
                        <?php if ($record): ?>
                            <span class="text-[9px] leading-none font-bold opacity-80"><?= AttendanceModel::CODES[$record['status']] ?></span>
                        <?php endif ?>
                        <span class="sr-only"><?= esc($label) ?></span>
                    </li>
                <?php endfor ?>
            </ol>
        </div>
    </section>

    <!-- Pinjaman & lembur -->
    <div class="space-y-6">
        <section class="card p-6" aria-labelledby="loan-heading">
            <h2 id="loan-heading" class="text-base font-semibold text-slate-900">Pinjaman</h2>
            <?php if ($loans === []): ?>
                <p class="mt-3 text-sm text-slate-500">Anda tidak memiliki pinjaman berjalan.</p>
            <?php endif ?>
            <?php foreach ($loans as $loan): ?>
                <?php $paid = $loan['amount'] > 0 ? 1 - $loan['remaining'] / $loan['amount'] : 0; ?>
                <div class="mt-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs text-slate-500"><?= esc($loan['loan_no']) ?></p>
                            <p class="truncate text-sm font-semibold text-slate-900"><?= esc($loan['purpose'] ?? 'Pinjaman karyawan') ?></p>
                        </div>
                        <?= status_badge($loan['status'], $loan['status'] === LoanModel::STATUS_PENDING ? 'Menunggu' : LoanModel::STATUSES[$loan['status']]) ?>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Sisa pinjaman</p>
                    <p class="text-xl font-bold text-slate-900 tabular-nums"><?= rupiah($loan['remaining']) ?></p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($paid * 100) ?>" aria-label="Pinjaman terbayar">
                        <div class="h-full rounded-full bg-linear-to-r from-brand-500 to-violet-500" style="width: <?= number_format($paid * 100, 1, '.', '') ?>%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500"><?= number_id($paid * 100) ?>% dari <?= rupiah($loan['amount']) ?> · cicilan <?= rupiah($loan['installment']) ?>/bulan</p>
                </div>
            <?php endforeach ?>
        </section>

        <section class="card p-6" aria-labelledby="overtime-heading">
            <h2 id="overtime-heading" class="text-base font-semibold text-slate-900">Lembur Bulan Ini</h2>
            <div class="mt-3 flex items-end justify-between gap-3">
                <div>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums"><?= rtrim(rtrim(number_id((float) $approved['total_hours'], 2), '0'), ',') ?> <span class="text-sm font-medium text-slate-500">jam disetujui</span></p>
                    <p class="text-xs text-slate-500"><?= (int) $approved['total'] ?> pengajuan disetujui<?= $pendingOt > 0 ? " · {$pendingOt} menunggu persetujuan" : '' ?></p>
                </div>
                <span class="flex size-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><?= icon('clock-4', 'size-5') ?></span>
            </div>
            <?php if (! $employee['overtime_eligible']): ?>
                <p class="mt-3 text-xs text-slate-500">Jabatan Anda tidak termasuk penerima upah lembur.</p>
            <?php endif ?>
        </section>
    </div>

    <!-- Slip gaji terbaru -->
    <section class="card overflow-hidden lg:col-span-2" aria-labelledby="slips-heading">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
            <h2 id="slips-heading" class="text-base font-semibold text-slate-900">Slip Gaji Terbaru</h2>
            <a href="<?= url_to('my.payslips') ?>" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Lihat semua →</a>
        </div>
        <?php if ($recent === []): ?>
            <?= component('empty_state', ['title' => 'Belum ada slip gaji', 'icon' => 'receipt-text', 'compact' => true]) ?>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($recent as $slip): ?>
                    <li class="flex items-center gap-4 px-6 py-3.5">
                        <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <span class="text-[10px] font-bold uppercase"><?= esc(bulan_id($slip['month'], true)) ?></span>
                            <span class="text-xs font-bold"><?= $slip['year'] ?></span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <a href="<?= url_to('payslips.show', $slip['id']) ?>" class="font-semibold text-slate-900 hover:text-brand-700"><?= esc($slip['period_name']) ?></a>
                            <p class="text-xs text-slate-500">Dibayar <?= esc(tanggal_id($slip['pay_date'])) ?> · <?= $slip['present_days'] ?>/<?= $slip['active_workdays'] ?> hari</p>
                        </div>
                        <span class="text-right font-bold whitespace-nowrap text-slate-900 tabular-nums"><?= rupiah($slip['take_home_pay']) ?></span>
                        <a href="<?= url_to('payslips.pdf', $slip['id']) ?>" class="rounded-lg p-2 text-brand-600 hover:bg-brand-50" aria-label="Unduh PDF slip <?= esc($slip['period_name'], 'attr') ?>"><?= icon('file-down', 'size-4') ?></a>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <div class="space-y-6">
        <section class="card p-6" aria-labelledby="ytd-heading">
            <h2 id="ytd-heading" class="text-base font-semibold text-slate-900">Tahun <?= date('Y', strtotime($today)) ?></h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Total gaji diterima</dt><dd class="font-bold text-slate-900 tabular-nums"><?= rupiah($ytd['thp']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">PPh 21 dipotong</dt><dd class="font-bold text-slate-900 tabular-nums"><?= rupiah($ytd['pph21']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Slip di PayrollKu</dt><dd class="font-bold text-slate-900"><?= $ytd['months'] ?> bulan</dd></div>
            </dl>
        </section>

        <section class="card p-6" aria-labelledby="holiday-heading">
            <h2 id="holiday-heading" class="text-base font-semibold text-slate-900">Hari Libur Mendatang</h2>
            <?php if ($upcoming === []): ?>
                <p class="mt-3 text-sm text-slate-500">Tidak ada hari libur terjadwal.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-3">
                    <?php foreach ($upcoming as $holiday): ?>
                        <?php $date = new DateTimeImmutable($holiday['date']); ?>
                        <li class="flex items-center gap-3">
                            <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-rose-50 text-rose-700 ring-1 ring-rose-100">
                                <span class="text-[10px] font-semibold uppercase"><?= esc(bulan_id((int) $date->format('n'), true)) ?></span>
                                <span class="text-sm leading-none font-bold"><?= $date->format('j') ?></span>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800"><?= esc($holiday['name']) ?></p>
                                <p class="text-xs text-slate-500"><?= esc(hari_id((int) $date->format('N'))) ?> · <?= esc(HolidayModel::TYPES[$holiday['type']] ?? $holiday['type']) ?></p>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>
</div>
<?php $this->endSection() ?>
