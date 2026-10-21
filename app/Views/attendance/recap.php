<?php
/**
 * @var int                                              $year
 * @var int                                              $month
 * @var string                                           $prevMonth
 * @var string                                           $nextMonth
 * @var list<array<string, mixed>>                       $employees
 * @var array<int, array<string, array<string, mixed>>>  $grid       employee_id → tanggal → kehadiran
 * @var list<array{date: string, day: int, weekday: int, workday: bool, holiday: string|null, future: bool}> $days
 * @var array<int, array<string, int|float|null>>        $totals
 * @var array<string, int>                               $daily      tanggal → jumlah hadir
 * @var int                                              $workdays
 * @var array<string, mixed>|null                        $period
 * @var int                                              $departmentId
 * @var array<int, string>                               $departments
 * @var bool                                             $canManage
 */

use App\Models\AttendanceModel;

$this->extend('layouts/app');
$this->section('content');

$cell = [
    'present' => 'bg-emerald-100 text-emerald-800',
    'late'    => 'bg-amber-200 text-amber-900',
    'sick'    => 'bg-sky-200 text-sky-900',
    'leave'   => 'bg-violet-200 text-violet-900',
    'permit'  => 'bg-brand-200 text-brand-900',
    'absent'  => 'bg-rose-600 text-white',
    'holiday' => 'bg-slate-200 text-slate-700',
];

$monthKey   = sprintf('%04d-%02d', $year, $month);
$recapUrl   = static fn (string $m): string => url_to('attendance.recap') . '?' . http_build_query(array_filter(['month' => $m, 'department_id' => $departmentId ?: null]));
$present    = array_sum(array_map(static fn (array $t): int => (int) $t['present'] + (int) $t['late'], $totals));
$recorded   = array_sum(array_column($totals, 'recorded'));
$lateTotal  = array_sum(array_column($totals, 'late'));
$absent     = array_sum(array_column($totals, 'absent'));
$missing    = array_sum(array_map(static fn (array $t): int => max(0, (int) $t['expected'] - (int) $t['recorded']), $totals));
$shortDay   = [1 => 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$totalCodes = ['present', 'late', 'sick', 'leave', 'permit', 'absent'];
?>
<?= component('page_header', [
    'eyebrow'  => 'Kehadiran',
    'title'    => 'Rekap Kehadiran ' . periode_id($year, $month),
    'subtitle' => 'Grid karyawan × tanggal. Arahkan kursor ke sel untuk melihat jam masuk/pulang dan keterangan; klik tanggal untuk membuka input harian.',
    'actions'  => '<a href="' . esc(url_to('attendance.index') . '?date=' . ($monthKey === date('Y-m') ? date('Y-m-d') : $monthKey . '-01'), 'attr') . '" class="btn btn-secondary">' . icon('calendar-check-2', 'size-4') . ' Input Harian</a>'
        . ($canManage ? '<a href="' . url_to('attendance.import') . '" class="btn btn-secondary">' . icon('file-up', 'size-4') . ' Import CSV</a>' : ''),
]) ?>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= component('kpi_card', ['label' => 'Hari kerja', 'value' => $workdays . ' hari', 'icon' => 'calendar-days', 'tone' => 'brand', 'hint' => count($employees) . ' karyawan pada periode ini']) ?>
    <?= component('kpi_card', ['label' => 'Tingkat kehadiran', 'value' => $recorded > 0 ? number_id($present / $recorded * 100, 1) . '%' : '—', 'icon' => 'user-check', 'tone' => 'emerald', 'hint' => number_id($present) . ' dari ' . number_id($recorded) . ' hari tercatat']) ?>
    <?= component('kpi_card', ['label' => 'Terlambat', 'value' => number_id($lateTotal) . ' kali', 'icon' => 'alarm-clock', 'tone' => 'amber', 'hint' => 'Kena denda per hari terlambat']) ?>
    <?= component('kpi_card', ['label' => 'Alpha', 'value' => number_id($absent) . ' hari', 'icon' => 'user-x', 'tone' => 'rose', 'hint' => $missing > 0 ? number_id($missing) . ' hari kerja belum tercatat' : 'Semua hari kerja sudah tercatat']) ?>
</div>

<div class="card mb-6 flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
    <form method="get" action="<?= url_to('attendance.recap') ?>" class="flex flex-wrap items-center gap-2">
        <a href="<?= esc($recapUrl($prevMonth), 'attr') ?>" class="btn btn-secondary px-3" aria-label="Bulan sebelumnya"><?= icon('chevron-left', 'size-4') ?></a>
        <label for="recap-month" class="sr-only">Bulan</label>
        <input id="recap-month" type="month" name="month" value="<?= esc($monthKey, 'attr') ?>" class="form-control w-auto font-semibold" onchange="this.form.submit()">
        <a href="<?= esc($recapUrl($nextMonth), 'attr') ?>" class="btn btn-secondary px-3" aria-label="Bulan berikutnya"><?= icon('chevron-right', 'size-4') ?></a>
        <label for="recap-dept" class="sr-only">Departemen</label>
        <select id="recap-dept" name="department_id" class="form-control w-auto" onchange="this.form.submit()">
            <option value="">Semua departemen</option>
            <?php foreach ($departments as $id => $name): ?>
                <option value="<?= (int) $id ?>" <?= $id === $departmentId ? 'selected' : '' ?>><?= esc($name) ?></option>
            <?php endforeach ?>
        </select>
        <?php if ($period): ?>
            <span class="ml-1 flex items-center gap-2 text-sm text-slate-600">Payroll <?= status_badge($period['status']) ?></span>
        <?php endif ?>
    </form>
    <ul class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600" aria-label="Keterangan warna">
        <?php foreach (AttendanceModel::STATUSES as $status => $label): ?>
            <li class="flex items-center gap-1.5">
                <span class="flex size-5 items-center justify-center rounded text-[10px] font-bold <?= $cell[$status] ?>" aria-hidden="true"><?= AttendanceModel::CODES[$status] ?></span><?= esc($label) ?>
            </li>
        <?php endforeach ?>
        <li class="flex items-center gap-1.5"><span class="size-5 rounded border border-dashed border-slate-300" aria-hidden="true"></span>Belum tercatat</li>
    </ul>
</div>

<?php if ($employees === []): ?>
    <div class="card"><?= component('empty_state', ['title' => 'Tidak ada karyawan pada periode ini', 'icon' => 'calendar-x-2']) ?></div>
<?php else: ?>
    <section class="card relative overflow-hidden" x-data="recapTooltip()" @mouseover="show($event)" @mouseleave="hide()" aria-label="Grid kehadiran <?= esc(periode_id($year, $month), 'attr') ?>">
        <div class="max-h-[75vh] overflow-auto">
            <table class="min-w-full border-separate border-spacing-0 text-xs">
                <thead class="sticky top-0 z-20">
                    <tr>
                        <th scope="col" class="sticky left-0 z-30 min-w-56 border-b border-slate-200 bg-slate-50 px-4 py-2 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Karyawan</th>
                        <?php foreach ($days as $day): ?>
                            <th scope="col" class="border-b border-slate-200 px-0.5 py-1.5 text-center font-semibold <?= $day['holiday'] ? 'bg-rose-50 text-rose-600' : ($day['workday'] ? 'bg-slate-50 text-slate-600' : 'bg-slate-100 text-slate-700') ?>"
                                <?= $day['holiday'] ? 'data-tip="' . esc(tanggal_id($day['date'], 'full') . "\n" . $day['holiday'], 'attr') . '"' : '' ?>>
                                <a href="<?= esc(url_to('attendance.index') . '?date=' . $day['date'], 'attr') ?>" class="block rounded px-1 hover:bg-white hover:text-brand-700" aria-label="Input kehadiran <?= esc(tanggal_id($day['date'], 'full'), 'attr') ?>">
                                    <span class="block text-[10px] font-medium"><?= $shortDay[$day['weekday']] ?></span>
                                    <span class="block text-sm tabular-nums"><?= $day['day'] ?></span>
                                </a>
                            </th>
                        <?php endforeach ?>
                        <?php foreach ($totalCodes as $index => $status): ?>
                            <th scope="col" class="border-b border-slate-200 bg-slate-50 px-2 py-1.5 text-center font-bold text-slate-600 <?= $index === 0 ? 'border-l' : '' ?>">
                                <abbr title="<?= esc(AttendanceModel::STATUSES[$status], 'attr') ?>" class="no-underline"><?= AttendanceModel::CODES[$status] ?></abbr>
                            </th>
                        <?php endforeach ?>
                        <th scope="col" class="border-b border-l border-slate-200 bg-slate-50 px-3 py-1.5 text-right font-semibold whitespace-nowrap text-slate-600">% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <?php
                        $id     = (int) $employee['id'];
                        $total  = $totals[$id];
                        $gap    = max(0, (int) $total['expected'] - (int) $total['recorded']);
                        ?>
                        <tr class="group">
                            <th scope="row" class="sticky left-0 z-10 border-b border-slate-100 bg-white px-4 py-1.5 text-left font-normal group-hover:bg-slate-50">
                                <a href="<?= url_to('employees.show', $id) ?>#kehadiran" class="flex items-center gap-2.5">
                                    <?= avatar($employee['full_name'], 'size-7 text-[10px] ring-1') ?>
                                    <span class="min-w-0">
                                        <span class="block max-w-44 truncate text-sm font-semibold text-slate-900 hover:text-brand-700"><?= esc($employee['full_name']) ?></span>
                                        <span class="block truncate text-[11px] text-slate-500"><?= esc($employee['nip']) ?> · <?= esc($employee['department_code']) ?></span>
                                    </span>
                                </a>
                            </th>
                            <?php foreach ($days as $day): ?>
                                <?php
                                $record   = $grid[$id][$day['date']] ?? null;
                                $employed = $day['date'] >= $employee['join_date'] && ($employee['resign_date'] === null || $day['date'] <= $employee['resign_date']);
                                $tip      = null;

                                if ($record) {
                                    $times = $record['check_in'] ? substr($record['check_in'], 0, 5) . '–' . ($record['check_out'] ? substr($record['check_out'], 0, 5) : '…') : null;
                                    $tip   = $employee['full_name'] . ' · ' . tanggal_id($day['date'], 'full') . "\n"
                                        . AttendanceModel::STATUSES[$record['status']]
                                        . ($times ? ' · ' . $times : '')
                                        . ((int) $record['late_minutes'] > 0 ? ' (+' . $record['late_minutes'] . ' mnt)' : '')
                                        . ($record['note'] ? "\n" . $record['note'] : '');
                                } elseif (! $employed) {
                                    $tip = $employee['full_name'] . ' · ' . tanggal_id($day['date'], 'full') . "\n" . ($day['date'] < $employee['join_date'] ? 'Belum bergabung' : 'Sudah tidak bekerja');
                                } elseif ($day['workday'] && ! $day['future']) {
                                    $tip = $employee['full_name'] . ' · ' . tanggal_id($day['date'], 'full') . "\nBelum tercatat";
                                }
                                ?>
                                <td class="border-b border-slate-100 p-0.5 text-center <?= $day['workday'] ? '' : 'bg-slate-50/80' ?>" <?= $tip ? 'data-tip="' . esc($tip, 'attr') . '"' : '' ?>>
                                    <?php if ($record): ?>
                                        <span class="mx-auto flex size-6 items-center justify-center rounded text-[10px] font-bold <?= $cell[$record['status']] ?>" aria-hidden="true"><?= AttendanceModel::CODES[$record['status']] ?></span>
                                        <span class="sr-only"><?= esc(AttendanceModel::STATUSES[$record['status']]) ?></span>
                                    <?php elseif (! $employed): ?>
                                        <span class="mx-auto block size-6 rounded bg-[repeating-linear-gradient(135deg,#e2e8f0_0_2px,transparent_2px_6px)]" aria-hidden="true"></span>
                                        <span class="sr-only">Tidak bekerja</span>
                                    <?php elseif ($day['workday'] && ! $day['future']): ?>
                                        <span class="mx-auto block size-6 rounded border border-dashed border-slate-300" aria-hidden="true"></span>
                                        <span class="sr-only">Belum tercatat</span>
                                    <?php endif ?>
                                </td>
                            <?php endforeach ?>
                            <?php foreach ($totalCodes as $index => $status): ?>
                                <td class="border-b border-slate-100 px-2 py-1.5 text-center font-semibold tabular-nums <?= $index === 0 ? 'border-l border-l-slate-200' : '' ?> <?= (int) $total[$status] === 0 ? 'text-slate-300' : ($status === 'absent' ? 'text-rose-600' : 'text-slate-800') ?>">
                                    <?= (int) $total[$status] ?>
                                </td>
                            <?php endforeach ?>
                            <td class="border-b border-l border-slate-100 border-l-slate-200 px-3 py-1.5 text-right whitespace-nowrap tabular-nums">
                                <span class="font-bold text-slate-900"><?= $total['rate'] === null ? '—' : number_id($total['rate'], 1) . '%' ?></span>
                                <?php if ($gap > 0): ?>
                                    <span class="block text-[10px] font-semibold text-amber-700"><?= $gap ?> hari kosong</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot class="sticky bottom-0 z-20">
                    <tr>
                        <th scope="row" class="sticky left-0 z-30 border-t border-slate-200 bg-slate-50 px-4 py-2 text-left text-xs font-semibold text-slate-600">Hadir per hari</th>
                        <?php foreach ($days as $day): ?>
                            <td class="border-t border-slate-200 bg-slate-50 py-2 text-center text-[11px] font-semibold tabular-nums <?= $daily[$day['date']] > 0 ? 'text-slate-700' : 'text-slate-300' ?>"><?= $daily[$day['date']] ?: '·' ?></td>
                        <?php endforeach ?>
                        <td colspan="<?= count($totalCodes) + 1 ?>" class="border-t border-l border-slate-200 bg-slate-50 px-3 py-2 text-right text-[11px] text-slate-500"><?= count($employees) ?> karyawan</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div x-show="open" x-cloak class="pointer-events-none fixed z-[60] max-w-xs rounded-xl bg-ink-950 px-3 py-2 text-xs leading-relaxed whitespace-pre-line text-white shadow-elevated"
             :style="`left: ${x}px; top: ${y}px`" x-text="text" role="tooltip"></div>
    </section>
<?php endif ?>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    /** Satu tooltip melayang untuk seluruh sel grid (lebih ringan daripada tooltip per sel). */
    function recapTooltip() {
        return {
            open: false,
            text: '',
            x: 0,
            y: 0,
            show(event) {
                const cell = event.target.closest('[data-tip]');
                if (!cell) return this.hide();
                const rect = cell.getBoundingClientRect();
                this.text = cell.dataset.tip;
                this.x = Math.min(rect.left, window.innerWidth - 260);
                this.y = rect.bottom + 6 > window.innerHeight - 80 ? rect.top - 70 : rect.bottom + 6;
                this.open = true;
            },
            hide() {
                this.open = false;
            },
        };
    }
</script>
<?php $this->endSection() ?>
