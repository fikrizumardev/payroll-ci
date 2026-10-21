<?php
/**
 * @var array<string, mixed>       $employee
 * @var list<array<string, mixed>> $components
 * @var array<int, int|string>     $overrides  salary_component_id → nominal
 * @var list<array{label: string, summary: array<string, int>}> $months
 * @var list<array<string, mixed>> $recent
 * @var list<array<string, mixed>> $loans
 * @var list<array<string, mixed>> $payrolls
 * @var array<string, mixed>|null  $account
 * @var bool                       $isHead
 * @var bool                       $canManage
 */

use App\Models\EmployeeModel;
use App\Models\LoanModel;
use App\Models\SalaryComponentModel;

$this->extend('layouts/app');
$this->section('content');

$active = $employee['status'] === EmployeeModel::STATUS_ACTIVE;
$tenure = (new DateTimeImmutable($employee['join_date']))->diff(new DateTimeImmutable($employee['resign_date'] ?? 'today'));
$age    = $employee['birth_date'] ? (new DateTimeImmutable($employee['birth_date']))->diff(new DateTimeImmutable('today'))->y : null;

$fixedWage = (int) $employee['basic_salary'];

foreach ($components as $component) {
    if ($component['calc_type'] === 'fixed' && $component['type'] === 'earning' && $component['is_bpjs_base']) {
        $fixedWage += (int) ($overrides[$component['id']] ?? 0);
    }
}

$tabs = [
    'profil'    => ['Profil', 'user-round'],
    'komponen'  => ['Komponen Gaji', 'layers'],
    'kehadiran' => ['Kehadiran', 'calendar-check-2'],
    'pinjaman'  => ['Pinjaman', 'hand-coins'],
    'gaji'      => ['Riwayat Gaji', 'receipt-text'],
];

$detail = static fn (string $label, ?string $value, bool $mono = false): string => '<div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">'
    . '<dt class="text-sm text-slate-500">' . esc($label) . '</dt>'
    . '<dd class="mt-0.5 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0 ' . ($mono ? 'font-mono' : '') . '">' . ($value === null || $value === '' ? '<span class="text-slate-500">—</span>' : esc($value)) . '</dd></div>';
?>
<div x-data="{ tab: (location.hash || '#profil').slice(1), showId: false, resignOpen: false, submitting: false }"
     x-init="if (!<?= js_data(array_keys($tabs)) ?>.includes(tab)) tab = 'profil'; $watch('tab', (t) => history.replaceState(null, '', '#' + t))">

    <!-- Header profil -->
    <section class="card relative mb-6 overflow-hidden">
        <div class="h-28 bg-linear-to-r from-brand-600 via-violet-600 to-fuchsia-600 sm:h-32">
            <svg class="size-full opacity-20" aria-hidden="true"><defs><pattern id="hdr-dots" width="18" height="18" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.2" fill="white"/></pattern></defs><rect width="100%" height="100%" fill="url(#hdr-dots)"/></svg>
        </div>
        <div class="px-6 pb-6">
            <div class="-mt-12 flex flex-col gap-4 sm:-mt-10 sm:flex-row sm:items-end">
                <?= avatar($employee['full_name'], 'size-24 text-2xl ring-4 shadow-elevated') ?>
                <div class="min-w-0 flex-1 sm:pb-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?= esc($employee['full_name']) ?></h1>
                        <?= status_badge($employee['status']) ?>
                        <?= status_badge($employee['employment_type']) ?>
                        <?php if ($isHead): ?><?= status_badge('approved', 'Kepala Departemen') ?><?php endif ?>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        <?= esc($employee['position_name']) ?> · <?= esc($employee['department_name']) ?> · <span class="font-mono"><?= esc($employee['nip']) ?></span>
                    </p>
                </div>
                <?php if ($canManage): ?>
                    <div class="flex flex-wrap gap-2 sm:pb-1">
                        <a href="<?= url_to('employees.edit', $employee['id']) ?>" class="btn btn-secondary"><?= icon('pencil', 'size-4') ?> Ubah Data</a>
                        <?php if ($active): ?>
                            <button type="button" class="btn btn-secondary text-rose-600" @click="resignOpen = true"><?= icon('log-out', 'size-4') ?> Catat Resign</button>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <?php foreach ([
                    ['Masa kerja', ($tenure->y > 0 ? $tenure->y . ' tahun ' : '') . $tenure->m . ' bulan', 'Sejak ' . tanggal_id($employee['join_date'])],
                    ['Gaji pokok', rupiah($employee['basic_salary']), 'Upah tetap ' . rupiah($fixedWage)],
                    ['Status PTKP', $employee['ptkp_status'], 'Kategori TER ' . service('payroll')->rates()->terCategoryFor($employee['ptkp_status'])],
                    ['Hak lembur', $employee['overtime_eligible'] ? 'Ya' : 'Tidak', 'Grade ' . $employee['grade']],
                ] as [$label, $value, $hint]): ?>
                    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
                        <dt class="text-xs font-medium text-slate-500"><?= esc($label) ?></dt>
                        <dd class="mt-1 text-lg font-bold text-slate-900 tabular-nums"><?= esc($value) ?></dd>
                        <dd class="text-xs text-slate-500"><?= esc($hint) ?></dd>
                    </div>
                <?php endforeach ?>
            </dl>

            <?php if (! $active): ?>
                <div class="mt-4 flex gap-3 rounded-xl bg-slate-100 p-3.5 text-sm text-slate-700">
                    <?= icon('info', 'mt-0.5 size-4 shrink-0') ?>
                    <p>Karyawan berstatus <?= esc(EmployeeModel::STATUSES[$employee['status']]) ?> per <span class="font-semibold"><?= esc(tanggal_id($employee['resign_date'], 'long')) ?></span>. Data tetap disimpan untuk riwayat payroll & pajak.</p>
                </div>
            <?php endif ?>
        </div>

        <!-- Tab -->
        <nav class="flex gap-1 overflow-x-auto border-t border-slate-100 px-4" role="tablist" aria-label="Detail karyawan">
            <?php foreach ($tabs as $key => [$label, $iconName]): ?>
                <button type="button" role="tab" @click="tab = '<?= $key ?>'" :aria-selected="tab === '<?= $key ?>'"
                        class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-3.5 text-sm font-semibold transition"
                        :class="tab === '<?= $key ?>' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'">
                    <?= icon($iconName, 'size-4') ?> <?= esc($label) ?>
                </button>
            <?php endforeach ?>
        </nav>
    </section>

    <!-- Profil -->
    <div x-show="tab === 'profil'" role="tabpanel" class="grid gap-6 lg:grid-cols-2">
        <section class="card px-6 py-2">
            <h2 class="pt-4 pb-1 text-base font-semibold text-slate-900">Data Pribadi</h2>
            <dl class="divide-y divide-slate-100">
                <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-slate-500">NIK</dt>
                    <dd class="mt-0.5 flex items-center gap-2 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0">
                        <span class="font-mono" x-text="showId ? '<?= esc($employee['nik'], 'js') ?>' : '<?= esc(mask_id($employee['nik']), 'js') ?>'"><?= esc(mask_id($employee['nik'])) ?></span>
                        <?php if ($canManage): ?>
                            <button type="button" class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="showId = !showId" :aria-label="showId ? 'Sembunyikan NIK' : 'Tampilkan NIK'" aria-label="Tampilkan NIK">
                                <span x-show="!showId"><?= icon('eye', 'size-4') ?></span><span x-show="showId" x-cloak><?= icon('eye-off', 'size-4') ?></span>
                            </button>
                        <?php endif ?>
                    </dd>
                </div>
                <?= $detail('NPWP', $employee['npwp'] === $employee['nik'] ? 'Sama dengan NIK' : mask_id($employee['npwp']), $employee['npwp'] !== $employee['nik']) ?>
                <?= $detail('Jenis kelamin', EmployeeModel::GENDERS[$employee['gender']] ?? null) ?>
                <?= $detail('Tempat, tanggal lahir', implode(', ', array_filter([$employee['birth_place'], $employee['birth_date'] ? tanggal_id($employee['birth_date'], 'long') . ($age ? " ({$age} tahun)" : '') : null]))) ?>
                <?= $detail('No. HP', $employee['phone']) ?>
                <?= $detail('Alamat', $employee['address']) ?>
            </dl>
        </section>
        <div class="space-y-6">
            <section class="card px-6 py-2">
                <h2 class="pt-4 pb-1 text-base font-semibold text-slate-900">Rekening Gaji</h2>
                <dl class="divide-y divide-slate-100">
                    <?= $detail('Bank', $employee['bank_name']) ?>
                    <?= $detail('No. rekening', $employee['bank_account_no'] ? mask_id($employee['bank_account_no'], 3, 4) : null, true) ?>
                    <?= $detail('Atas nama', $employee['bank_account_name']) ?>
                </dl>
            </section>
            <section class="card px-6 py-2">
                <h2 class="pt-4 pb-1 text-base font-semibold text-slate-900">Kepegawaian & Akun</h2>
                <dl class="divide-y divide-slate-100">
                    <?= $detail('Tanggal masuk', tanggal_id($employee['join_date'], 'long')) ?>
                    <?= $detail('BPJS', implode(' · ', array_filter([$employee['bpjs_kes_enrolled'] ? 'Kesehatan' : null, $employee['bpjs_tk_enrolled'] ? 'Ketenagakerjaan' : null])) ?: 'Tidak terdaftar') ?>
                    <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm text-slate-500">Akun login</dt>
                        <dd class="mt-0.5 text-sm sm:col-span-2 sm:mt-0">
                            <?php if ($account): ?>
                                <span class="font-mono font-medium text-slate-900">@<?= esc($account['username']) ?></span>
                                <?= status_badge($account['is_active'] ? 'active' : 'inactive') ?>
                                <span class="block text-xs text-slate-500">Login terakhir: <?= esc(tanggal_id($account['last_login_at'], 'datetime')) ?></span>
                            <?php else: ?>
                                <span class="text-slate-500">Belum memiliki akun</span>
                            <?php endif ?>
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>

    <!-- Komponen gaji -->
    <div x-show="tab === 'komponen'" x-cloak role="tabpanel" id="komponen">
        <form method="post" action="<?= url_to('employees.components', $employee['id']) ?>" class="card" novalidate>
            <?= csrf_field() ?>
            <div class="flex flex-col gap-1 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Komponen Gaji Karyawan</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Komponen tetap per bulan dan tarif harian. Kosongkan tarif harian untuk memakai tarif default.</p>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($components as $component): ?>
                    <?php
                    $current = $overrides[$component['id']] ?? null;
                    $daily   = $component['calc_type'] === 'daily';
                    ?>
                    <div class="grid items-center gap-3 px-6 py-4 sm:grid-cols-[1fr_12rem_14rem]">
                        <div>
                            <label for="component-<?= (int) $component['id'] ?>" class="text-sm font-semibold text-slate-900"><?= esc($component['name']) ?></label>
                            <p class="text-xs text-slate-500">
                                <?= esc(SalaryComponentModel::TYPES[$component['type']]) ?> · <?= esc(SalaryComponentModel::CALC_TYPES[$component['calc_type']]) ?>
                                <?= $component['is_bpjs_base'] ? ' · Dasar BPJS' : '' ?>
                            </p>
                        </div>
                        <p class="text-xs text-slate-500 sm:text-right">
                            <?= $daily ? 'Default ' . rupiah($component['default_amount']) . '/hari' : ($current === null ? 'Tidak diberikan' : '') ?>
                            <?php if ($daily && $current !== null): ?><span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 font-semibold text-amber-700">Override</span><?php endif ?>
                        </p>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-semibold text-slate-500">Rp</span>
                            <input id="component-<?= (int) $component['id'] ?>" name="amounts[<?= (int) $component['id'] ?>]" type="text" inputmode="numeric"
                                   value="<?= $current === null ? '' : esc(number_id((int) $current), 'attr') ?>"
                                   placeholder="<?= $daily ? esc(number_id($component['default_amount']), 'attr') : '0' ?>"
                                   x-data @input="$el.value = PayrollKu.formatNumber($el.value)"
                                   class="form-control pl-11 text-right font-semibold tabular-nums" <?= $canManage ? '' : 'disabled' ?>>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <?php if ($canManage): ?>
                <div class="flex justify-end border-t border-slate-100 bg-slate-50/60 px-6 py-4">
                    <button type="submit" class="btn btn-primary"><?= icon('save', 'size-4') ?> Simpan Komponen</button>
                </div>
            <?php endif ?>
        </form>
    </div>

    <!-- Kehadiran -->
    <div x-show="tab === 'kehadiran'" x-cloak role="tabpanel" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <?php foreach ($months as $month): ?>
                <?php
                $s        = $month['summary'];
                $recorded = array_sum($s) - $s['holiday'];
                $present  = $s['present'] + $s['late'];
                ?>
                <section class="card p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900"><?= esc($month['label']) ?></h3>
                        <span class="text-xs text-slate-500"><?= $recorded ?> hari tercatat</span>
                    </div>
                    <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900 tabular-nums"><?= $recorded > 0 ? number_id($present / $recorded * 100, 1) : '0' ?>%</p>
                    <p class="text-xs text-slate-500">tingkat kehadiran</p>
                    <div class="mt-4 flex h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <?php foreach (['present', 'late', 'sick', 'leave', 'permit', 'absent'] as $status): ?>
                            <?php if ($recorded > 0 && $s[$status] > 0): ?>
                                <span class="<?= tone_classes(status_meta($status)['tone'], 'dot') ?>" style="width: <?= number_format($s[$status] / $recorded * 100, 2, '.', '') ?>%"></span>
                            <?php endif ?>
                        <?php endforeach ?>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <?php foreach (['present' => 'Hadir', 'late' => 'Telat', 'sick' => 'Sakit', 'leave' => 'Cuti', 'permit' => 'Izin', 'absent' => 'Alpha'] as $status => $label): ?>
                            <div class="rounded-lg bg-slate-50 py-2">
                                <dt class="text-[11px] text-slate-500"><?= $label ?></dt>
                                <dd class="text-sm font-bold text-slate-900 tabular-nums"><?= $s[$status] ?></dd>
                            </div>
                        <?php endforeach ?>
                    </dl>
                </section>
            <?php endforeach ?>
        </div>
        <?php
        $recentRows = array_map(static fn (array $a): array => [
            '<span class="font-medium text-slate-900">' . esc(tanggal_id($a['date'], 'full')) . '</span>',
            status_badge($a['status']),
            esc($a['check_in'] ? substr($a['check_in'], 0, 5) : '—'),
            esc($a['check_out'] ? substr($a['check_out'], 0, 5) : '—'),
            $a['late_minutes'] > 0 ? '<span class="font-semibold text-amber-700">' . $a['late_minutes'] . ' mnt</span>' : '—',
            esc($a['note'] ?? '—'),
        ], $recent);
        ?>
        <?= component('data_table', [
            'title'   => '10 Catatan Kehadiran Terakhir',
            'columns' => [['label' => 'Tanggal'], ['label' => 'Status'], ['label' => 'Masuk', 'align' => 'center'], ['label' => 'Pulang', 'align' => 'center'], ['label' => 'Terlambat', 'align' => 'right'], ['label' => 'Keterangan', 'mobile' => false]],
            'rows'    => $recentRows,
            'empty'   => ['title' => 'Belum ada data kehadiran', 'icon' => 'calendar-x-2'],
        ]) ?>
    </div>

    <!-- Pinjaman -->
    <div x-show="tab === 'pinjaman'" x-cloak role="tabpanel">
        <?php if ($loans === []): ?>
            <div class="card"><?= component('empty_state', ['title' => 'Tidak ada pinjaman', 'description' => 'Karyawan ini belum pernah mengajukan pinjaman.', 'icon' => 'hand-coins']) ?></div>
        <?php else: ?>
            <div class="grid gap-4 md:grid-cols-2">
                <?php foreach ($loans as $loan): ?>
                    <?php $paid = $loan['amount'] > 0 ? 1 - $loan['remaining'] / $loan['amount'] : 0; ?>
                    <section class="card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-xs text-slate-500"><?= esc($loan['loan_no']) ?></p>
                                <h3 class="mt-0.5 font-semibold text-slate-900"><?= esc($loan['purpose'] ?? 'Pinjaman karyawan') ?></h3>
                            </div>
                            <?= status_badge($loan['status'], LoanModel::STATUSES[$loan['status']] ?? null) ?>
                        </div>
                        <div class="mt-4 flex items-end justify-between">
                            <div>
                                <p class="text-xs text-slate-500">Sisa pinjaman</p>
                                <p class="text-xl font-bold text-slate-900 tabular-nums"><?= rupiah($loan['remaining']) ?></p>
                            </div>
                            <p class="text-right text-xs text-slate-500">dari <?= rupiah($loan['amount']) ?><br><?= rupiah($loan['installment']) ?> × <?= (int) $loan['tenor_months'] ?> bulan</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-linear-to-r from-emerald-500 to-teal-500" style="width: <?= number_format($paid * 100, 1, '.', '') ?>%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Mulai dipotong <?= esc(periode_id((int) substr($loan['start_period'], 0, 4), (int) substr($loan['start_period'], 5, 2))) ?> · <?= number_id($paid * 100, 0) ?>% terbayar</p>
                    </section>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <!-- Riwayat gaji -->
    <div x-show="tab === 'gaji'" x-cloak role="tabpanel">
        <?php
        $payrollRows = array_map(static fn (array $p): array => [
            '<a href="' . url_to('payroll.employee', $p['period_id'], $employee['id']) . '" class="font-semibold text-slate-900 hover:text-brand-700">' . esc($p['period_name']) . '</a><p class="text-xs text-slate-500">Dibayar ' . esc(tanggal_id($p['pay_date'])) . '</p>',
            $p['present_days'] . ' / ' . $p['workdays'] . ($p['active_workdays'] < $p['workdays'] ? ' <span class="text-xs text-amber-700">(prorata)</span>' : ''),
            rupiah($p['total_earning']),
            rupiah($p['total_deduction']),
            rupiah($p['pph21']) . '<span class="block text-xs text-slate-500">' . ($p['ter_rate'] === null ? 'Setahun' : 'TER ' . esc($p['ter_category']) . ' ' . esc(percent_id($p['ter_rate']))) . '</span>',
            '<span class="font-bold text-slate-900">' . rupiah($p['take_home_pay']) . '</span>',
            status_badge($p['period_status']),
        ], $payrolls);
        ?>
        <?= component('data_table', [
            'title'    => 'Riwayat Gaji',
            'subtitle' => 'Slip gaji PDF tersedia di modul Slip Gaji.',
            'columns'  => [
                ['label' => 'Periode'],
                ['label' => 'Hadir / Hari Kerja', 'align' => 'center'],
                ['label' => 'Pendapatan', 'align' => 'right'],
                ['label' => 'Potongan', 'align' => 'right'],
                ['label' => 'PPh 21', 'align' => 'right'],
                ['label' => 'Take Home Pay', 'align' => 'right'],
                ['label' => 'Status', 'align' => 'center'],
            ],
            'rows'  => $payrollRows,
            'empty' => ['title' => 'Belum ada riwayat gaji', 'icon' => 'receipt-text'],
        ]) ?>
    </div>

    <!-- Modal resign -->
    <?php if ($canManage && $active): ?>
        <div x-show="resignOpen" x-cloak class="fixed inset-0 z-[65] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="resign-title" @keydown.escape.window="resignOpen = false">
            <div class="fixed inset-0 bg-ink-950/50 backdrop-blur-sm" @click="resignOpen = false" aria-hidden="true"></div>
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <form method="post" action="<?= url_to('employees.resign', $employee['id']) ?>" @submit="submitting = true"
                      class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200">
                    <?= csrf_field() ?>
                    <div class="space-y-5 p-6">
                        <div class="flex items-start gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600 ring-8 ring-rose-50/50"><?= icon('log-out', 'size-5') ?></span>
                            <div>
                                <h3 id="resign-title" class="text-base font-semibold text-slate-900">Catat karyawan keluar</h3>
                                <p class="mt-1 text-sm text-slate-500">Akun login <?= esc($employee['full_name']) ?> akan dinonaktifkan. Gaji periode terakhir dihitung prorata dan PPh 21 dihitung setahun.</p>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="resign-date" class="form-label">Tanggal keluar <span class="text-rose-500">*</span></label>
                                <input id="resign-date" name="resign_date" type="date" required min="<?= esc($employee['join_date'], 'attr') ?>" value="<?= date('Y-m-d') ?>" class="form-control">
                            </div>
                            <div>
                                <label for="resign-status" class="form-label">Jenis</label>
                                <select id="resign-status" name="status" class="form-control">
                                    <option value="resigned">Resign</option>
                                    <option value="terminated">Diberhentikan</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="resign-note" class="form-label">Catatan</label>
                            <textarea id="resign-note" name="note" rows="2" maxlength="200" class="form-control" placeholder="mis. Pindah kerja ke perusahaan lain"></textarea>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:justify-end">
                        <button type="button" class="btn btn-secondary" @click="resignOpen = false">Batal</button>
                        <button type="submit" class="btn btn-danger" :disabled="submitting">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
