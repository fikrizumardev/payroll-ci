<?php
/**
 * @var list<array<string, mixed>>                                         $overtimes
 * @var CodeIgniter\Pager\Pager                                            $pager
 * @var array{month: string, status: string, department_id: string, q: string} $filters
 * @var array<string, int>                                                 $counts
 * @var array{hours: float, cost: int, employees: int}                     $stats
 * @var array<int, string>                                                 $departments
 * @var array<int, string>                                                 $employees   karyawan aktif yang berhak lembur
 * @var string|null                                                        $closedUntil
 * @var bool                                                               $canManage
 */

use App\Models\OvertimeModel;

$this->extend('layouts/app');
$this->section('content');

$year      = (int) substr($filters['month'], 0, 4);
$month     = (int) substr($filters['month'], 5, 2);
$query     = static fn (array $overrides): string => url_to('overtime.index') . '?' . http_build_query(array_filter($overrides + $filters, static fn ($v) => $v !== '' && $v !== null));
$hhmm      = static fn (?string $time): string => $time ? substr($time, 0, 5) : '';
$hours     = static fn (float|string $h): string => rtrim(rtrim(number_id($h, 2), '0'), ',');
$reviewUrl = url_to('overtime.review');
$editable  = static fn (array $o): bool => $o['status'] !== OvertimeModel::STATUS_APPROVED && ($closedUntil === null || $o['date'] > $closedUntil);
$canReview = static fn (array $o): bool => $o['status'] === OvertimeModel::STATUS_PENDING && ($closedUntil === null || $o['date'] > $closedUntil);

$tabs = ['all' => ['Semua', array_sum($counts)]];

foreach (OvertimeModel::STATUSES as $status => $label) {
    $tabs[$status] = [$label, $counts[$status]];
}

$items = [];
$rows  = [];

foreach ($overtimes as $o) {
    $id         = (int) $o['id'];
    $items[$id] = [
        'id'          => $id,
        'employee_id' => (string) $o['employee_id'],
        'date'        => $o['date'],
        'start_time'  => $hhmm($o['start_time']),
        'end_time'    => $hhmm($o['end_time']),
        'reason'      => (string) $o['reason'],
    ];

    $actions = '';

    if ($canManage && $canReview($o)) {
        $actions .= '<button type="button" class="rounded-lg p-1.5 text-emerald-600 hover:bg-emerald-50" aria-label="Setujui lembur ' . esc($o['full_name'], 'attr') . '"'
            . ' @click="$dispatch(\'confirm\', { title: \'Setujui lembur?\', message: \'' . esc($o['full_name'] . ' · ' . tanggal_id($o['date']) . ' · ' . number_id($o['hours'], 2) . ' jam', 'js') . '\', action: \'' . esc($reviewUrl, 'js') . '\', confirmLabel: \'Setujui\', fields: { ids: \'' . $id . '\', action: \'approve\' } })">'
            . icon('check', 'size-4') . '</button>'
            . '<button type="button" class="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50" aria-label="Tolak lembur ' . esc($o['full_name'], 'attr') . '"'
            . ' @click="$dispatch(\'confirm\', { title: \'Tolak lembur?\', message: \'' . esc($o['full_name'] . ' · ' . tanggal_id($o['date']), 'js') . '\', action: \'' . esc($reviewUrl, 'js') . '\', confirmLabel: \'Tolak\', tone: \'danger\', withNote: true, noteRequired: true, noteLabel: \'Alasan penolakan\', fields: { ids: \'' . $id . '\', action: \'reject\' } })">'
            . icon('x', 'size-4') . '</button>';
    }

    if ($canManage && $editable($o)) {
        $actions .= '<button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="edit(' . $id . ')" aria-label="Ubah lembur ' . esc($o['full_name'], 'attr') . '">' . icon('pencil', 'size-4') . '</button>'
            . '<button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus lembur ' . esc($o['full_name'], 'attr') . '"'
            . ' @click="$dispatch(\'confirm\', { title: \'Hapus pengajuan lembur?\', message: \'' . esc($o['full_name'] . ' · ' . tanggal_id($o['date']), 'js') . '\', action: \'' . esc(url_to('overtime.delete', $id), 'js') . '\', confirmLabel: \'Hapus\', tone: \'danger\' })">'
            . icon('trash-2', 'size-4') . '</button>';
    }

    $statusCell = status_badge($o['status'], OvertimeModel::STATUSES[$o['status']]);

    if ($o['approver_name']) {
        $statusCell .= '<span class="mt-1 block text-[11px] text-slate-500">' . esc($o['approver_name']) . ' · ' . esc(tanggal_id($o['approved_at'])) . '</span>';
    }

    if ($o['review_note']) {
        $statusCell .= '<span class="mt-0.5 block max-w-48 truncate text-[11px] text-rose-700" title="' . esc($o['review_note'], 'attr') . '">' . esc($o['review_note']) . '</span>';
    }

    $rows[] = [
        ($canManage && $canReview($o)
            ? '<label class="flex items-center gap-3"><input type="checkbox" value="' . $id . '" x-model="selected" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-label="Pilih lembur ' . esc($o['full_name'], 'attr') . '">'
            : '<div class="flex items-center gap-3">' . ($canManage ? '<span class="size-4"></span>' : ''))
            . avatar($o['full_name']) . '<span class="min-w-0 max-w-40"><a href="' . url_to('employees.show', $o['employee_id']) . '" class="block truncate font-semibold text-slate-900 hover:text-brand-700">' . esc($o['full_name']) . '</a>'
            . '<span class="block truncate text-xs text-slate-500">' . esc($o['nip']) . ' · ' . esc($o['position_name']) . '</span></span>'
            . ($canManage && $canReview($o) ? '</label>' : '</div>'),
        '<p class="font-medium whitespace-nowrap text-slate-800">' . esc(tanggal_id($o['date'])) . ' · <span class="font-semibold text-slate-900">' . esc($hours($o['hours'])) . ' jam</span></p><p class="text-xs whitespace-nowrap text-slate-500 tabular-nums">' . esc(hari_id((int) date('N', strtotime($o['date']))) . ', ' . $hhmm($o['start_time']) . '–' . $hhmm($o['end_time'])) . '</p><p class="max-w-44 truncate text-xs text-slate-500 2xl:hidden" title="' . esc((string) $o['reason'], 'attr') . '">' . esc($o['reason'] ?: '') . '</p>',
        '<span class="line-clamp-2 max-w-52 text-slate-600" title="' . esc((string) $o['reason'], 'attr') . '">' . esc($o['reason'] ?: '—') . '</span>',
        $o['overtime_eligible'] ? rupiah($o['estimate']) : '<span class="text-xs text-rose-600">Tidak berhak</span>',
        $statusCell,
        $actions === '' ? '<span class="text-slate-300">—</span>' : '<div class="flex justify-end gap-0.5">' . $actions . '</div>',
    ];
}

$pending = $counts[OvertimeModel::STATUS_PENDING];
?>
<div x-data="crudModal({
        empty: { id: null, employee_id: '', date: '<?= esc(date('Y-m-d'), 'js') ?>', start_time: '17:00', end_time: '19:00', reason: '' },
        items: <?= js_data((object) $items) ?>,
        storeUrl: '<?= esc(url_to('overtime.store'), 'js') ?>',
        updateUrl: '<?= esc(site_url('overtime/__ID__'), 'js') ?>',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">
    <div x-data="{ selected: [] }">

    <?= component('page_header', [
        'eyebrow'  => 'Kehadiran',
        'title'    => 'Lembur ' . periode_id($year, $month),
        'subtitle' => 'Upah sejam = 1/173 × (gaji pokok + tunjangan tetap). Jam ke-1 dibayar 1,5×, jam berikutnya 2× (PP 35/2021). Hanya lembur disetujui yang masuk payroll.',
        'actions'  => $canManage ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Ajukan Lembur</button>' : null,
    ]) ?>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <?= component('kpi_card', ['label' => 'Jam lembur disetujui', 'value' => number_id($stats['hours'], 1) . ' jam', 'icon' => 'clock-4', 'tone' => 'brand', 'hint' => $stats['employees'] . ' karyawan']) ?>
        <?= component('kpi_card', ['label' => 'Estimasi upah lembur', 'value' => rupiah($stats['cost']), 'icon' => 'wallet', 'tone' => 'emerald', 'hint' => 'Dihitung per karyawan per hari']) ?>
        <?= component('kpi_card', ['label' => 'Menunggu persetujuan', 'value' => $pending . ' pengajuan', 'icon' => 'hourglass', 'tone' => 'amber', 'href' => $query(['status' => 'pending', 'page' => null])]) ?>
        <?= component('kpi_card', ['label' => 'Ditolak', 'value' => $counts[OvertimeModel::STATUS_REJECTED] . ' pengajuan', 'icon' => 'circle-x', 'tone' => 'rose']) ?>
    </div>

    <?php if ($closedUntil): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-sky-50 p-3.5 text-sm text-sky-800 ring-1 ring-sky-200">
            <?= icon('lock', 'mt-0.5 size-4 shrink-0') ?>
            <p>Lembur sampai <span class="font-semibold"><?= esc(tanggal_id($closedUntil, 'long')) ?></span> terkunci karena periode payroll-nya sudah disetujui/dibayar.</p>
        </div>
    <?php endif ?>

    <div class="card mb-6 overflow-hidden">
        <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3" role="tablist" aria-label="Status lembur">
            <?php foreach ($tabs as $status => [$label, $count]): ?>
                <a href="<?= esc($query(['status' => $status, 'page' => null]), 'attr') ?>" role="tab" aria-selected="<?= $filters['status'] === $status ? 'true' : 'false' ?>"
                   class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 pb-3 text-sm font-semibold transition <?= $filters['status'] === $status ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <?= esc($label) ?>
                    <span class="rounded-full px-2 py-0.5 text-xs <?= $filters['status'] === $status ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' ?>"><?= $count ?></span>
                </a>
            <?php endforeach ?>
        </div>
        <form method="get" action="<?= url_to('overtime.index') ?>" class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[11rem_1fr_14rem_auto]" role="search">
            <input type="hidden" name="status" value="<?= esc($filters['status'], 'attr') ?>">
            <div>
                <label for="ot-month" class="sr-only">Bulan</label>
                <input id="ot-month" type="month" name="month" value="<?= esc($filters['month'], 'attr') ?>" class="form-control" onchange="this.form.submit()">
            </div>
            <div class="relative">
                <label for="ot-q" class="sr-only">Cari karyawan</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"><?= icon('search', 'size-4') ?></span>
                <input id="ot-q" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Cari nama atau NIP…" class="form-control pl-10">
            </div>
            <div>
                <label for="ot-dept" class="sr-only">Departemen</label>
                <select id="ot-dept" name="department_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua departemen</option>
                    <?php foreach ($departments as $id => $name): ?>
                        <option value="<?= (int) $id ?>" <?= (string) $id === $filters['department_id'] ? 'selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary"><?= icon('filter', 'size-4') ?> Terapkan</button>
        </form>
    </div>

    <?php if ($canManage): ?>
        <div x-show="selected.length > 0" x-cloak x-transition
             class="sticky top-20 z-20 mb-4 flex flex-col gap-3 rounded-2xl bg-ink-950 px-5 py-3 text-white shadow-elevated sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm"><span class="font-bold" x-text="selected.length"></span> pengajuan dipilih</p>
            <div class="flex gap-2">
                <button type="button" class="btn bg-white/10 py-2 text-white hover:bg-white/20" @click="selected = []">Batal</button>
                <button type="button" class="btn bg-rose-500 py-2 text-white hover:bg-rose-400"
                        @click="$dispatch('confirm', { title: `Tolak ${selected.length} pengajuan lembur?`, message: 'Karyawan dapat memperbaiki lalu mengajukan ulang.', action: '<?= esc($reviewUrl, 'js') ?>', confirmLabel: 'Tolak', tone: 'danger', withNote: true, noteRequired: true, noteLabel: 'Alasan penolakan', fields: { ids: selected.join(','), action: 'reject' } })">
                    <?= icon('x', 'size-4') ?> Tolak
                </button>
                <button type="button" class="btn bg-emerald-500 py-2 text-white hover:bg-emerald-400"
                        @click="$dispatch('confirm', { title: `Setujui ${selected.length} pengajuan lembur?`, message: 'Lembur yang disetujui dibayarkan pada payroll periode tanggal lembur.', action: '<?= esc($reviewUrl, 'js') ?>', confirmLabel: 'Setujui', fields: { ids: selected.join(','), action: 'approve' } })">
                    <?= icon('check-check', 'size-4') ?> Setujui
                </button>
            </div>
        </div>
    <?php endif ?>

    <?php
    $total   = $pager->getTotal();
    $page    = $pager->getCurrentPage();
    $perPage = $pager->getPerPage();
    ?>
    <?= component('data_table', [
        'title'    => 'Daftar Lembur',
        'subtitle' => $total === 0 ? 'Tidak ada data' : sprintf('Menampilkan %d–%d dari %d pengajuan', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total),
        'toolbar'  => $canManage && $pending > 0 ? '<span class="text-xs text-slate-500">Centang pengajuan berstatus Menunggu untuk persetujuan massal.</span>' : null,
        'columns'  => [
            ['label' => 'Karyawan'],
            ['label' => 'Waktu'],
            ['label' => 'Alasan', 'mobile' => false, 'class' => 'hidden 2xl:table-cell'],
            ['label' => 'Estimasi Upah', 'align' => 'right'],
            ['label' => 'Status'],
            ['label' => 'Aksi', 'align' => 'right'],
        ],
        'rows'   => $rows,
        'empty'  => [
            'title'       => 'Belum ada lembur',
            'description' => 'Tidak ada pengajuan lembur yang cocok dengan filter pada ' . periode_id($year, $month) . '.',
            'icon'        => 'clock-4',
        ],
        'footer' => $pager->getPageCount() > 1 ? $pager->links('default', 'tailwind') : null,
    ]) ?>
    </div>

    <?php if ($canManage): ?>
        <?= component('modal_open', [
            'title'     => 'Lembur',
            'titleExpr' => "mode === 'create' ? 'Ajukan Lembur' : 'Ubah Pengajuan Lembur'",
            'subtitle'  => 'Hanya karyawan dengan jabatan berhak lembur yang dapat diajukan.',
            'action'    => 'action',
        ]) ?>
            <?= component('form_select', ['name' => 'employee_id', 'label' => 'Karyawan', 'options' => $employees, 'placeholder' => '— Pilih karyawan —', 'required' => true, 'attrs' => ['id' => 'ot-employee', 'x-model' => 'form.employee_id']]) ?>
            <div class="grid gap-5 sm:grid-cols-3">
                <?= component('form_input', ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true, 'attrs' => ['id' => 'ot-date', 'x-model' => 'form.date']]) ?>
                <?= component('form_input', ['name' => 'start_time', 'label' => 'Mulai', 'type' => 'time', 'required' => true, 'attrs' => ['id' => 'ot-start', 'x-model' => 'form.start_time']]) ?>
                <?= component('form_input', ['name' => 'end_time', 'label' => 'Selesai', 'type' => 'time', 'required' => true, 'attrs' => ['id' => 'ot-end', 'x-model' => 'form.end_time']]) ?>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-brand-50/70 p-3.5 text-sm text-brand-900 ring-1 ring-brand-100">
                <?= icon('timer', 'size-5 shrink-0 text-brand-600') ?>
                <p x-data="{ get h() { return overtimeHours(form.start_time, form.end_time) } }">
                    Durasi <span class="font-bold tabular-nums" x-text="h.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' jam'"></span>
                    <span class="text-brand-700" x-show="h > 0 && h <= <?= OvertimeModel::MAX_HOURS ?>">· pengali upah <span class="font-semibold" x-text="overtimeMultiplier(h).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '×'"></span> upah sejam</span>
                    <span class="font-semibold text-rose-700" x-show="h > <?= OvertimeModel::MAX_HOURS ?>" x-cloak>· melebihi batas <?= OvertimeModel::MAX_HOURS ?> jam</span>
                </p>
            </div>
            <div>
                <label for="ot-reason" class="form-label">Alasan / pekerjaan <span class="text-rose-500" aria-hidden="true">*</span></label>
                <textarea id="ot-reason" name="reason" rows="3" maxlength="255" class="form-control <?= isset((session('errors') ?? [])['reason']) ? 'ring-rose-400' : '' ?>" x-model="form.reason" placeholder="mis. Deploy rilis aplikasi versi 2.4"></textarea>
                <?php if ($error = (session('errors') ?? [])['reason'] ?? null): ?>
                    <p class="mt-1.5 text-xs font-medium text-rose-600"><?= esc($error) ?></p>
                <?php endif ?>
            </div>
        <?= component('modal_close', ['submitLabel' => 'Simpan Pengajuan']) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    /** Durasi (jam) dari "HH:MM"; selesai ≤ mulai dianggap melewati tengah malam — sama dengan server. */
    function overtimeHours(start, end) {
        const toMin = (v) => { const [h, m] = String(v || '').split(':').map(Number); return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null; };
        const from = toMin(start), to = toMin(end);
        if (from === null || to === null) return 0;
        return Math.round(((to <= from ? to + 1440 : to) - from) / 60 * 100) / 100;
    }

    /** Jam ke-1 = 1,5×, jam berikutnya 2×. */
    function overtimeMultiplier(hours) {
        return Math.min(hours, 1) * 1.5 + Math.max(hours - 1, 0) * 2;
    }
</script>
<?php $this->endSection() ?>
