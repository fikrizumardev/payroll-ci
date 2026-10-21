<?php
/**
 * @var string                            $date
 * @var string                            $prevDate
 * @var string                            $nextDate
 * @var list<array<string, mixed>>        $employees
 * @var array<int, array<string, mixed>>  $records     employee_id → kehadiran
 * @var array<string, int>                $summary
 * @var array<string, mixed>|null         $holiday
 * @var bool                              $isWeekend
 * @var string|null                       $closedError
 * @var array<int, string>                $departments
 * @var string                            $workStart
 * @var int                               $tolerance
 * @var bool                              $canManage
 */

use App\Models\AttendanceModel;

$this->extend('layouts/app');
$this->section('content');

$editable = $canManage && $closedError === null;
$errors   = session('errors') ?? [];
$old      = old('rows', null, false);
$old      = is_array($old) ? $old : [];
$time     = static fn (?string $value): string => $value ? substr($value, 0, 5) : '';

$rows     = [];
$original = [];
$meta     = [];

foreach ($employees as $employee) {
    $id     = (int) $employee['id'];
    $record = $records[$id] ?? null;

    $original[$id] = [
        'status'    => $record['status'] ?? '',
        'check_in'  => $time($record['check_in'] ?? null),
        'check_out' => $time($record['check_out'] ?? null),
        'note'      => (string) ($record['note'] ?? ''),
    ];
    $rows[$id] = isset($old[$id]) && is_array($old[$id])
        ? array_map('strval', array_intersect_key($old[$id], $original[$id])) + $original[$id]
        : $original[$id];
    $meta[$id] = [
        'q'    => mb_strtolower($employee['full_name'] . ' ' . $employee['nip'] . ' ' . $employee['position_name']),
        'dept' => (string) $employee['department_id'],
    ];
}

$segment = [
    'present' => 'bg-emerald-700 text-white shadow-sm',
    'late'    => 'bg-amber-400 text-amber-950 shadow-sm',
    'sick'    => 'bg-sky-700 text-white shadow-sm',
    'leave'   => 'bg-violet-600 text-white shadow-sm',
    'permit'  => 'bg-brand-600 text-white shadow-sm',
    'absent'  => 'bg-rose-600 text-white shadow-sm',
    'holiday' => 'bg-slate-700 text-white shadow-sm',
];

$isToday  = $date === date('Y-m-d');
$dayLabel = tanggal_id($date, 'full');
$filled   = array_sum($summary);
$offDay   = $holiday !== null || $isWeekend;

$actions = '<a href="' . esc(url_to('attendance.recap') . '?month=' . substr($date, 0, 7), 'attr') . '" class="btn btn-secondary">' . icon('calendar-range', 'size-4') . ' Rekap Bulanan</a>';

if ($canManage) {
    $actions .= '<a href="' . url_to('attendance.import') . '" class="btn btn-secondary">' . icon('file-up', 'size-4') . ' Import CSV</a>';
}
?>
<div x-data="attendanceDaily({
        rows: <?= js_data((object) $rows) ?>,
        original: <?= js_data((object) $original) ?>,
        meta: <?= js_data((object) $meta) ?>,
        workStart: '<?= esc($workStart, 'js') ?>',
        tolerance: <?= (int) $tolerance ?>,
     })"
     @beforeunload.window="if (dirtyCount > 0 && !submitting) { $event.preventDefault(); $event.returnValue = ''; }">

    <?= component('page_header', [
        'eyebrow'  => 'Kehadiran',
        'title'    => 'Kehadiran Harian',
        'subtitle' => 'Catat status kehadiran seluruh karyawan aktif dalam satu layar. Jam masuk melewati toleransi ' . $tolerance . ' menit dari ' . $workStart . ' otomatis dicatat terlambat.',
        'actions'  => $actions,
    ]) ?>

    <!-- Navigasi tanggal -->
    <div class="card mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <a href="<?= esc(url_to('attendance.index') . '?date=' . $prevDate, 'attr') ?>" class="btn btn-secondary px-3" aria-label="Hari sebelumnya"><?= icon('chevron-left', 'size-4') ?></a>
            <form method="get" action="<?= url_to('attendance.index') ?>" class="flex items-center gap-2">
                <label for="attendance-date" class="sr-only">Tanggal</label>
                <input id="attendance-date" type="date" name="date" value="<?= esc($date, 'attr') ?>" class="form-control w-auto font-semibold tabular-nums" onchange="this.form.submit()">
            </form>
            <a href="<?= esc(url_to('attendance.index') . '?date=' . $nextDate, 'attr') ?>" class="btn btn-secondary px-3" aria-label="Hari berikutnya"><?= icon('chevron-right', 'size-4') ?></a>
            <?php if (! $isToday): ?>
                <a href="<?= url_to('attendance.index') ?>" class="btn btn-ghost whitespace-nowrap">Hari ini</a>
            <?php endif ?>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold text-slate-900"><?= esc($dayLabel) ?></span>
            <?php if ($holiday): ?>
                <?= status_badge('absent', $holiday['name']) ?>
            <?php elseif ($isWeekend): ?>
                <?= status_badge('holiday', 'Akhir pekan') ?>
            <?php else: ?>
                <?= status_badge('draft', 'Hari kerja') ?>
            <?php endif ?>
        </div>
    </div>

    <?php if ($closedError !== null): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-sky-50 p-3.5 text-sm text-sky-800 ring-1 ring-sky-200">
            <?= icon('lock', 'mt-0.5 size-4 shrink-0') ?>
            <p><?= esc($closedError) ?> Kehadiran hanya dapat dilihat.</p>
        </div>
    <?php elseif ($offDay && $canManage): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-800 ring-1 ring-amber-200">
            <?= icon('calendar-off', 'mt-0.5 size-4 shrink-0') ?>
            <p><?= $holiday ? 'Tanggal ini hari libur (' . esc($holiday['name']) . ').' : 'Tanggal ini akhir pekan.' ?> Catat kehadiran hanya untuk karyawan yang bertugas / lembur.</p>
        </div>
    <?php endif ?>

    <!-- Ringkasan -->
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
        <?php foreach (AttendanceModel::STATUSES as $status => $label): ?>
            <div class="card p-4">
                <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                    <span class="size-2 rounded-full <?= tone_classes(status_meta($status)['tone'], 'dot') ?>"></span><?= esc($label) ?>
                </p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums" x-text="count('<?= $status ?>')"><?= $summary[$status] ?></p>
            </div>
        <?php endforeach ?>
        <div class="card p-4 ring-amber-200">
            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500"><span class="size-2 rounded-full border border-dashed border-slate-400"></span>Belum diisi</p>
            <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums" x-text="count('')"><?= count($employees) - $filled ?></p>
        </div>
    </div>

    <form method="post" action="<?= url_to('attendance.save') ?>" class="card overflow-hidden" @submit="submitting = true" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="date" value="<?= esc($date, 'attr') ?>">

        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Daftar Karyawan</h2>
                <p class="mt-0.5 text-sm text-slate-500"><?= count($employees) ?> karyawan bekerja pada tanggal ini · <span x-text="visibleCount"></span> ditampilkan</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <label for="attendance-search" class="sr-only">Cari karyawan</label>
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500"><?= icon('search', 'size-4') ?></span>
                    <input id="attendance-search" type="search" x-model.debounce.150ms="query" placeholder="Cari nama / NIP…" class="form-control w-56 py-2 pl-9">
                </div>
                <label for="attendance-dept" class="sr-only">Departemen</label>
                <select id="attendance-dept" x-model="dept" class="form-control w-auto py-2">
                    <option value="">Semua departemen</option>
                    <?php foreach ($departments as $id => $name): ?>
                        <option value="<?= (int) $id ?>"><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($editable): ?>
                    <button type="button" class="btn btn-secondary py-2" @click="fillEmpty('<?= $offDay ? 'holiday' : 'present' ?>')">
                        <?= icon('wand-sparkles', 'size-4') ?> Isi yang kosong: <?= $offDay ? 'Libur' : 'Hadir' ?>
                    </button>
                <?php endif ?>
            </div>
        </div>

        <?php if ($employees === []): ?>
            <?= component('empty_state', ['title' => 'Tidak ada karyawan aktif pada tanggal ini', 'icon' => 'users-round', 'compact' => true]) ?>
        <?php else: ?>
            <div class="hidden grid-cols-[minmax(10rem,1fr)_22.75rem_5.75rem_5.75rem_minmax(8rem,1fr)] gap-3 border-b border-slate-200 bg-slate-50/95 px-5 py-3 text-xs font-semibold tracking-wider text-slate-500 uppercase xl:grid">
                <span>Karyawan</span><span>Status</span><span>Masuk</span><span>Pulang</span><span>Keterangan</span>
            </div>

            <ul class="divide-y divide-slate-100">
                <?php foreach ($employees as $employee): ?>
                    <?php
                    $id       = (int) $employee['id'];
                    $rowError = array_filter($errors, static fn (string $key): bool => str_starts_with($key, "rows.{$id}."), ARRAY_FILTER_USE_KEY);
                    ?>
                    <li x-show="visible(<?= $id ?>)" class="grid gap-3 px-5 py-3.5 transition-colors xl:grid-cols-[minmax(10rem,1fr)_22.75rem_5.75rem_5.75rem_minmax(8rem,1fr)] xl:items-center xl:gap-3 <?= $rowError ? 'bg-rose-50/60' : '' ?>"
                        :class="changed(<?= $id ?>) && 'bg-brand-50/40'">
                        <div class="flex min-w-0 items-center gap-3">
                            <?= avatar($employee['full_name']) ?>
                            <div class="min-w-0">
                                <a href="<?= url_to('employees.show', $id) ?>#kehadiran" class="block truncate text-sm font-semibold text-slate-900 hover:text-brand-700"><?= esc($employee['full_name']) ?></a>
                                <p class="truncate text-xs text-slate-500"><?= esc($employee['nip']) ?> · <?= esc($employee['position_name']) ?></p>
                            </div>
                            <span x-show="changed(<?= $id ?>)" x-cloak class="ml-auto size-2 shrink-0 rounded-full bg-brand-500 xl:hidden" title="Belum disimpan"></span>
                        </div>

                        <fieldset class="min-w-0" <?= $editable ? '' : 'disabled' ?>>
                            <legend class="sr-only">Status kehadiran <?= esc($employee['full_name']) ?></legend>
                            <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                                <?php foreach (AttendanceModel::STATUSES as $status => $label): ?>
                                    <label class="cursor-pointer has-[:disabled]:cursor-default">
                                        <input type="radio" class="peer sr-only" name="rows[<?= $id ?>][status]" value="<?= $status ?>" x-model="rows[<?= $id ?>].status">
                                        <span class="block rounded-lg px-2 py-1.5 text-xs font-semibold transition peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500"
                                              :class="rows[<?= $id ?>].status === '<?= $status ?>' ? '<?= $segment[$status] ?>' : 'text-slate-600 hover:bg-white hover:text-slate-900'" title="<?= esc($label, 'attr') ?>"><?= esc($status === 'late' ? 'Telat' : $label) ?></span>
                                    </label>
                                <?php endforeach ?>
                            </div>
                            <?php foreach (['status', 'date'] as $field): ?>
                                <?php if (isset($errors["rows.{$id}.{$field}"])): ?>
                                    <p class="mt-1 text-xs font-medium text-rose-600"><?= esc($errors["rows.{$id}.{$field}"]) ?></p>
                                <?php endif ?>
                            <?php endforeach ?>
                        </fieldset>

                        <div class="grid grid-cols-2 gap-3 xl:contents">
                            <?php foreach (['check_in' => 'Jam masuk', 'check_out' => 'Jam pulang'] as $field => $label): ?>
                                <div>
                                    <label for="att-<?= $field ?>-<?= $id ?>" class="mb-1 block text-xs font-medium text-slate-500 xl:sr-only"><?= $label ?></label>
                                    <input id="att-<?= $field ?>-<?= $id ?>" type="time" name="rows[<?= $id ?>][<?= $field ?>]" x-model="rows[<?= $id ?>].<?= $field ?>"
                                           <?= $field === 'check_in' ? '@change="autoLate(' . $id . ')"' : '' ?>
                                           :disabled="!clocked(<?= $id ?>)" <?= $editable ? '' : 'disabled' ?>
                                           class="form-control px-2.5 py-2 tabular-nums disabled:opacity-40 <?= isset($errors["rows.{$id}.{$field}"]) ? 'ring-rose-400' : '' ?>">
                                    <?php if ($field === 'check_in'): ?>
                                        <p x-show="rows[<?= $id ?>].status === 'late' && lateMinutes(<?= $id ?>) > 0" x-cloak class="mt-1 text-[11px] font-semibold text-amber-700" x-text="'Terlambat ' + lateMinutes(<?= $id ?>) + ' mnt'"></p>
                                    <?php endif ?>
                                    <?php if (isset($errors["rows.{$id}.{$field}"])): ?>
                                        <p class="mt-1 text-xs font-medium text-rose-600"><?= esc($errors["rows.{$id}.{$field}"]) ?></p>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>

                        <div>
                            <label for="att-note-<?= $id ?>" class="sr-only">Keterangan <?= esc($employee['full_name']) ?></label>
                            <div class="relative">
                                <input id="att-note-<?= $id ?>" type="text" name="rows[<?= $id ?>][note]" x-model="rows[<?= $id ?>].note" maxlength="255"
                                       placeholder="Keterangan" class="form-control py-2" <?= $editable ? '' : 'disabled' ?>>
                            </div>
                            <?php if (isset($errors["rows.{$id}.note"])): ?>
                                <p class="mt-1 text-xs font-medium text-rose-600"><?= esc($errors["rows.{$id}.note"]) ?></p>
                            <?php endif ?>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
            <div x-show="visibleCount === 0" x-cloak>
                <?= component('empty_state', ['title' => 'Tidak ada karyawan yang cocok', 'description' => 'Ubah kata kunci atau filter departemen.', 'icon' => 'search-x', 'compact' => true]) ?>
            </div>
        <?php endif ?>

        <?php if ($editable && $employees !== []): ?>
            <div class="sticky bottom-0 z-10 flex flex-col gap-3 border-t border-slate-200 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">
                    <span x-show="dirtyCount > 0"><span class="font-semibold text-brand-700" x-text="dirtyCount"></span> baris berubah dan belum disimpan.</span>
                    <span x-show="dirtyCount === 0">Tidak ada perubahan.</span>
                    <span class="hidden text-slate-500 md:inline">Baris tanpa status tidak disimpan.</span>
                </p>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost" x-show="dirtyCount > 0" x-cloak @click="reset()">Batalkan perubahan</button>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <svg x-show="submitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        <?= icon('save', 'size-4') ?> Simpan Kehadiran
                    </button>
                </div>
            </div>
        <?php endif ?>
    </form>
</div>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    function attendanceDaily({ rows, original, meta, workStart, tolerance }) {
        const minutes = (value) => {
            const [h, m] = String(value || '').split(':').map(Number);
            return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
        };

        return {
            rows,
            original,
            meta,
            query: '',
            dept: '',
            submitting: false,

            visible(id) {
                const q = this.query.trim().toLowerCase();
                return (this.dept === '' || this.meta[id].dept === this.dept) && (q === '' || this.meta[id].q.includes(q));
            },
            get visibleCount() {
                return Object.keys(this.meta).filter((id) => this.visible(id)).length;
            },
            count(status) {
                return Object.values(this.rows).filter((row) => row.status === status).length;
            },
            clocked(id) {
                return ['present', 'late'].includes(this.rows[id].status);
            },
            changed(id) {
                return ['status', 'check_in', 'check_out', 'note'].some((key) => (this.rows[id][key] ?? '') !== (this.original[id][key] ?? ''));
            },
            get dirtyCount() {
                return Object.keys(this.rows).filter((id) => this.changed(id)).length;
            },
            lateMinutes(id) {
                const diff = minutes(this.rows[id].check_in) - minutes(workStart);
                return Number.isFinite(diff) ? Math.max(0, diff) : 0;
            },
            autoLate(id) {
                if (!this.clocked(id) || minutes(this.rows[id].check_in) === null) return;
                this.rows[id].status = this.lateMinutes(id) > tolerance ? 'late' : 'present';
            },
            fillEmpty(status) {
                let filled = 0;
                Object.keys(this.rows).forEach((id) => {
                    if (this.rows[id].status === '' && this.visible(id)) {
                        this.rows[id].status = status;
                        filled++;
                    }
                });
                window.PayrollKu.toast(filled ? 'info' : 'warning', filled ? `${filled} karyawan diisi. Jangan lupa simpan.` : 'Semua karyawan yang tampil sudah memiliki status.');
            },
            reset() {
                this.rows = JSON.parse(JSON.stringify(this.original));
            },
        };
    }
</script>
<?php $this->endSection() ?>
