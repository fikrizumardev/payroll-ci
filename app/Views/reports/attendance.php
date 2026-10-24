<?php
/**
 * @var int                  $year
 * @var int                  $month
 * @var string               $prevMonth
 * @var string               $nextMonth
 * @var int                  $departmentId
 * @var array<int, string>   $departments
 * @var array<string, mixed> $report hasil ReportService::attendance()
 */
$this->extend('layouts/app');
$this->section('content');

$total  = $report['total'];
$link   = static fn (string $month): string => url_to('reports.attendance') . '?' . http_build_query(array_filter(['month' => $month, 'department_id' => $departmentId ?: null]));
$future = $report['start'] > date('Y-m-d');

$rows       = [];
$rowClasses = [];
$count      = static fn (int $value, string $tone = 'text-slate-700'): string => $value === 0 ? '<span class="text-slate-400">0</span>' : '<span class="' . $tone . '">' . $value . '</span>';

foreach ($report['rows'] as $row) {
    $days = $row['days'];
    $rate = $row['rate'];
    $bar  = $rate === null ? '<span class="text-slate-400">—</span>' : '<div class="flex items-center justify-end gap-2">'
        . '<span class="hidden h-1.5 w-16 overflow-hidden rounded-full bg-slate-100 lg:block"><span class="block h-full rounded-full ' . ($rate >= 95 ? 'bg-emerald-500' : ($rate >= 85 ? 'bg-amber-500' : 'bg-rose-500')) . '" style="width: ' . number_format($rate, 1, '.', '') . '%"></span></span>'
        . '<span class="font-semibold text-slate-900">' . number_id($rate, 1) . '%</span></div>';

    $rows[] = [
        '<a href="' . url_to('employees.show', $row['employee_id']) . '" class="group block min-w-0"><span class="block truncate font-semibold text-slate-900 group-hover:text-brand-700">' . esc($row['name']) . '</span>'
            . '<span class="block truncate text-xs text-slate-500">' . esc($row['nip']) . ' · ' . esc($row['department']) . '</span>'
            . ($row['status'] !== 'active' ? '<span class="mt-0.5 inline-block">' . status_badge($row['status']) . '</span>' : '') . '</a>',
        $row['workdays'] . ($row['unrecorded'] > 0 ? '<span class="block text-[11px] font-semibold whitespace-nowrap text-amber-700" title="Hari kerja tanpa catatan kehadiran">' . $row['unrecorded'] . ' blm tercatat</span>' : ''),
        $count($days['present'] + $days['late'], 'font-semibold text-slate-900'),
        $days['late'] === 0 ? '<span class="text-slate-400">0</span>' : '<span class="text-amber-700">' . $days['late'] . '×</span><span class="block text-[11px] text-slate-500">' . number_id($days['late_minutes']) . ' mnt</span>',
        $count($days['sick'], 'text-sky-700'),
        $count($days['leave'], 'text-violet-700'),
        $count($days['permit'], 'text-brand-700'),
        $count($days['absent'], 'font-semibold text-rose-600'),
        $row['overtime'] > 0 ? number_id($row['overtime'], 1) . ' jam' : '<span class="text-slate-400">—</span>',
        $bar,
    ];
    $rowClasses[] = $days['absent'] > 0 ? 'bg-rose-50/40' : '';
}

$attendedTotal = $total['present'] + $total['late'];
?>
<?= $this->include('reports/_header') ?>

<div class="card mb-6 flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex items-center gap-2">
        <a href="<?= esc($link($prevMonth), 'attr') ?>" class="rounded-lg p-2 text-slate-500 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900" aria-label="Bulan sebelumnya"><?= icon('chevron-left', 'size-4') ?></a>
        <p class="min-w-36 text-center text-base font-semibold text-slate-900"><?= esc(periode_id($year, $month)) ?></p>
        <a href="<?= esc($link($nextMonth), 'attr') ?>" class="rounded-lg p-2 text-slate-500 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900" aria-label="Bulan berikutnya"><?= icon('chevron-right', 'size-4') ?></a>
    </div>
    <form method="get" action="<?= url_to('reports.attendance') ?>" class="flex flex-wrap items-center gap-2">
        <label for="report-month" class="sr-only">Bulan</label>
        <input id="report-month" type="month" name="month" value="<?= sprintf('%04d-%02d', $year, $month) ?>" class="form-control w-44" onchange="this.form.submit()">
        <label for="report-department" class="sr-only">Departemen</label>
        <div class="relative">
            <select id="report-department" name="department_id" class="form-control w-56 appearance-none pr-10" onchange="this.form.submit()">
                <option value="">Semua departemen</option>
                <?php foreach ($departments as $id => $name): ?>
                    <option value="<?= (int) $id ?>" <?= $id === $departmentId ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
        </div>
        <noscript><button type="submit" class="btn btn-secondary">Tampilkan</button></noscript>
    </form>
</div>

<?php if ($future): ?>
    <div class="card">
        <?= component('empty_state', ['title' => 'Bulan ini belum berjalan', 'description' => 'Rekap kehadiran tersedia mulai tanggal 1 ' . periode_id($year, $month) . '.', 'icon' => 'calendar-clock']) ?>
    </div>
<?php else: ?>
    <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan kehadiran">
        <?php foreach ([
            ['Tingkat kehadiran', $total['rate'] === null ? '—' : number_id($total['rate'], 1) . '%', number_id($attendedTotal) . ' dari ' . number_id($total['workdays']) . ' hari kerja karyawan'],
            ['Keterlambatan', number_id($total['late']) . '×', number_id($total['late_minutes']) . ' menit total'],
            ['Alpha', number_id($total['absent']) . ' hari', 'Sakit ' . $total['sick'] . ' · cuti ' . $total['leave'] . ' · izin ' . $total['permit']],
            ['Lembur disetujui', number_id($total['overtime'], 1) . ' jam', $total['unrecorded'] > 0 ? number_id($total['unrecorded']) . ' hari kerja belum tercatat' : 'Seluruh hari kerja sudah tercatat'],
        ] as $i => [$label, $value, $hint]): ?>
            <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                <p class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= esc($value) ?></p>
                <p class="mt-1 truncate text-xs text-slate-500"><?= esc($hint) ?></p>
            </div>
        <?php endforeach ?>
    </section>

    <?= component('data_table', [
        'title'      => 'Kehadiran per Karyawan',
        'subtitle'   => sprintf('%d hari kerja s.d. %s · tingkat kehadiran = hadir (termasuk terlambat) ÷ hari kerja aktif karyawan.', $report['workdays'], tanggal_id($report['until'], 'long')),
        'toolbar'    => '<a href="' . esc(url_to('attendance.recap') . '?' . http_build_query(array_filter(['month' => sprintf('%04d-%02d', $year, $month), 'department_id' => $departmentId ?: null])), 'attr') . '" class="btn btn-secondary py-2">' . icon('calendar-range', 'size-4') . ' Grid harian</a>',
        'columns'    => [
            ['label' => 'Karyawan'],
            ['label' => 'HK', 'align' => 'center'],
            ['label' => 'Hadir', 'align' => 'center'],
            ['label' => 'Terlambat', 'align' => 'center'],
            ['label' => 'Sakit', 'align' => 'center'],
            ['label' => 'Cuti', 'align' => 'center'],
            ['label' => 'Izin', 'align' => 'center'],
            ['label' => 'Alpha', 'align' => 'center'],
            ['label' => 'Lembur', 'align' => 'right'],
            ['label' => 'Kehadiran', 'align' => 'right'],
        ],
        'rows'       => $rows,
        'rowClasses' => $rowClasses,
        'empty'      => ['title' => 'Tidak ada karyawan', 'description' => 'Tidak ada karyawan yang bekerja pada bulan & departemen ini.', 'icon' => 'calendar-x-2'],
    ]) ?>
<?php endif ?>
<?php $this->endSection() ?>
