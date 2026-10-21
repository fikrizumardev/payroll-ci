<?php
/**
 * @var array<string, mixed>                  $user
 * @var string                                $today
 * @var list<array<string, mixed>>            $holidays
 * @var array<string, mixed>|null             $company
 * @var list<array<string, mixed>>|null       $activities
 * @var array<string, mixed>|null             $personal
 */
$this->extend('layouts/app');
$this->section('content');

$company    ??= null;
$activities ??= [];
$personal   ??= null;

$hour     = (int) date('G');
$greeting = match (true) {
    $hour < 11 => 'Selamat pagi',
    $hour < 15 => 'Selamat siang',
    $hour < 18 => 'Selamat sore',
    default    => 'Selamat malam',
};
$firstName = explode(' ', $user['name'])[0];
?>

<?= component('page_header', [
    'eyebrow'  => tanggal_id($today, 'full'),
    'title'    => "{$greeting}, {$firstName}",
    'subtitle' => $company
        ? 'Ringkasan operasional SDM dan penggajian ' . service('appSettings')->get('company.name') . ' hari ini.'
        : 'Ringkasan kehadiran dan informasi kepegawaian Anda bulan ini.',
]) ?>

<?php if ($company): ?>
    <?php
    $lastDay   = $company['lastDay'];
    $attended  = ($lastDay['present'] ?? 0) + ($lastDay['late'] ?? 0);
    $recorded  = array_sum($lastDay);
    $rate      = $recorded > 0 ? $attended / $recorded * 100 : 0;
    $types     = $company['byType'];
    ?>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?= component('kpi_card', [
            'label' => 'Karyawan Aktif',
            'value' => number_id($company['activeEmployees']) . ' orang',
            'icon'  => 'users-round',
            'tone'  => 'brand',
            'hint'  => sprintf('%d tetap · %d kontrak · %d percobaan', $types['permanent'] ?? 0, $types['contract'] ?? 0, $types['probation'] ?? 0),
        ]) ?>
        <?= component('kpi_card', [
            'label' => 'Kehadiran ' . ($company['lastDate'] ? tanggal_id($company['lastDate']) : '—'),
            'value' => number_id($rate, 1) . '%',
            'icon'  => 'calendar-check-2',
            'tone'  => 'emerald',
            'hint'  => sprintf('%d dari %d karyawan hadir', $attended, $recorded),
            'trend' => $company['attendanceTrend'],
        ]) ?>
        <?= component('kpi_card', [
            'label' => 'Lembur Menunggu Persetujuan',
            'value' => number_id($company['pendingOvertime']['total']) . ' pengajuan',
            'icon'  => 'clock-4',
            'tone'  => 'amber',
            'hint'  => 'Total ' . number_id($company['pendingOvertime']['hours'], 1) . ' jam',
        ]) ?>
        <?= component('kpi_card', [
            'label' => 'Sisa Pinjaman Karyawan',
            'value' => rupiah($company['activeLoans']['remaining']),
            'icon'  => 'hand-coins',
            'tone'  => 'violet',
            'hint'  => number_id($company['activeLoans']['total']) . ' pinjaman berjalan',
        ]) ?>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Kehadiran hari kerja terakhir -->
        <section class="card p-5 lg:col-span-1" aria-labelledby="attendance-heading">
            <div class="flex items-center justify-between">
                <h2 id="attendance-heading" class="text-base font-semibold text-slate-900">Status Kehadiran</h2>
                <span class="text-xs font-medium text-slate-500"><?= esc(tanggal_id($company['lastDate'])) ?></span>
            </div>
            <ul class="mt-5 space-y-3.5">
                <?php foreach (['present', 'late', 'sick', 'leave', 'permit', 'absent'] as $status): ?>
                    <?php
                    $count   = $lastDay[$status] ?? 0;
                    $percent = $recorded > 0 ? $count / $recorded * 100 : 0;
                    $meta    = status_meta($status);
                    ?>
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-slate-600">
                                <span class="size-2 rounded-full <?= tone_classes($meta['tone'], 'dot') ?>"></span><?= esc($meta['label']) ?>
                            </span>
                            <span class="font-semibold text-slate-900 tabular-nums"><?= $count ?></span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full <?= tone_classes($meta['tone'], 'dot') ?>" style="width: <?= number_format($percent, 1, '.', '') ?>%"></div>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>

        <!-- Aktivitas terbaru -->
        <section class="card lg:col-span-2" aria-labelledby="activity-heading">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 id="activity-heading" class="text-base font-semibold text-slate-900">Aktivitas Terbaru</h2>
                <span class="text-xs text-slate-500">Audit log</span>
            </div>
            <?php if ($activities === []): ?>
                <?= component('empty_state', ['title' => 'Belum ada aktivitas', 'icon' => 'scroll-text', 'compact' => true]) ?>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($activities as $activity): ?>
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <?= avatar($activity['user_name'] ?? $activity['username'] ?? 'Sistem', 'size-8 text-[11px]') ?>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-700">
                                    <span class="font-semibold text-slate-900"><?= esc($activity['user_name'] ?? $activity['username'] ?? 'Sistem') ?></span>
                                    — <?= esc($activity['description']) ?>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500"><?= esc(tanggal_id($activity['created_at'], 'datetime')) ?> · <?= esc($activity['action']) ?></p>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>
<?php endif ?>

<div class="mt-6 grid gap-6 <?= $personal ? 'lg:grid-cols-3' : '' ?>">
    <?php if ($personal): ?>
        <?php $attendance = $personal['attendance']; ?>
        <section class="card relative overflow-hidden p-6 lg:col-span-2" aria-labelledby="personal-heading">
            <div class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-brand-100/60 blur-3xl" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <?= avatar($personal['employee']['full_name'], 'size-14 text-base') ?>
                <div class="min-w-0">
                    <h2 id="personal-heading" class="text-lg font-semibold text-slate-900"><?= esc($personal['employee']['full_name']) ?></h2>
                    <p class="text-sm text-slate-500">
                        <?= esc($personal['employee']['position_name']) ?> · <?= esc($personal['employee']['department_name']) ?> · <?= esc($personal['employee']['nip']) ?>
                    </p>
                </div>
                <div class="ml-auto"><?= status_badge($personal['employee']['employment_type']) ?></div>
            </div>

            <dl class="relative mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach ([
                    ['Hadir', $attendance['present'] + $attendance['late'], 'hari', 'text-emerald-600'],
                    ['Terlambat', $attendance['late'], 'kali', 'text-amber-600'],
                    ['Sakit / Izin / Cuti', $attendance['sick'] + $attendance['permit'] + $attendance['leave'], 'hari', 'text-sky-600'],
                    ['Lembur disetujui', number_id($personal['overtime'], 1), 'jam', 'text-violet-600'],
                ] as [$label, $value, $unit, $color]): ?>
                    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
                        <dt class="text-xs font-medium text-slate-500"><?= esc($label) ?></dt>
                        <dd class="mt-1 text-xl font-bold tabular-nums <?= $color ?>"><?= esc((string) $value) ?> <span class="text-xs font-medium text-slate-500"><?= esc($unit) ?></span></dd>
                    </div>
                <?php endforeach ?>
            </dl>
            <p class="relative mt-4 text-xs text-slate-500">Periode <?= esc(tanggal_id(date('Y-m-01', strtotime($today)))) ?> – <?= esc(tanggal_id($today)) ?></p>

            <?php if ($personal['loan']): ?>
                <?php $paid = 1 - ($personal['loan']['remaining'] / max($personal['loan']['amount'], 1)); ?>
                <div class="relative mt-5 rounded-xl bg-linear-to-r from-brand-50 to-violet-50 p-4 ring-1 ring-brand-100">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-800">Pinjaman <?= esc($personal['loan']['loan_no']) ?></span>
                        <span class="text-slate-600">Sisa <span class="font-semibold text-slate-900"><?= rupiah($personal['loan']['remaining']) ?></span></span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-linear-to-r from-brand-500 to-violet-500" style="width: <?= number_format($paid * 100, 1, '.', '') ?>%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Cicilan <?= rupiah($personal['loan']['installment']) ?>/bulan · <?= esc(status_meta($personal['loan']['status'])['label']) ?></p>
                </div>
            <?php endif ?>
        </section>
    <?php endif ?>

    <!-- Hari libur mendatang -->
    <section class="card p-5" aria-labelledby="holiday-heading">
        <h2 id="holiday-heading" class="text-base font-semibold text-slate-900">Hari Libur Mendatang</h2>
        <?php if ($holidays === []): ?>
            <p class="mt-4 text-sm text-slate-500">Tidak ada hari libur terjadwal.</p>
        <?php else: ?>
            <ul class="mt-4 <?= $personal ? 'space-y-3' : 'grid gap-3 sm:grid-cols-3' ?>">
                <?php foreach ($holidays as $holiday): ?>
                    <?php $date = new DateTimeImmutable($holiday['date']); ?>
                    <li class="flex items-center gap-3">
                        <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                            <span class="text-[10px] font-semibold uppercase"><?= esc(bulan_id((int) $date->format('n'), true)) ?></span>
                            <span class="text-base leading-none font-bold"><?= $date->format('j') ?></span>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800"><?= esc($holiday['name']) ?></p>
                            <p class="text-xs text-slate-500"><?= esc(hari_id((int) $date->format('N'))) ?> · <?= esc(App\Models\HolidayModel::TYPES[$holiday['type']] ?? $holiday['type']) ?></p>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</div>
<?php $this->endSection() ?>
