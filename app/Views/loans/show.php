<?php
/**
 * @var array<string, mixed>                           $loan
 * @var list<array<string, mixed>>                     $installments
 * @var list<array<string, mixed>>                     $schedule     lihat LoanSchedule::build()
 * @var array<string, int>                             $periodIds    YYYY-MM → id periode payroll (potongan berjalan)
 * @var array<int, array{label: string, salary: int}>  $employees
 * @var string                                         $firstPeriod
 * @var bool                                           $canManage
 * @var bool                                           $canApprove
 */

use App\Models\LoanModel;
use App\Services\Loan\LoanSchedule;

$this->extend('layouts/app');
$this->section('content');

$period   = static fn (string $p, bool $short = false): string => periode_id((int) substr($p, 0, 4), (int) substr($p, 5, 2), $short);
$pending  = $loan['status'] === LoanModel::STATUS_PENDING;
$rejected = $loan['status'] === LoanModel::STATUS_REJECTED;
$paid     = $loan['amount'] > 0 ? 1 - $loan['remaining'] / $loan['amount'] : 0;
$end      = LoanSchedule::endPeriod($schedule);
$paidRows = count(array_filter($schedule, static fn (array $r): bool => $r['state'] === LoanSchedule::PAID));
$ratio    = $loan['basic_salary'] > 0 ? $loan['installment'] / $loan['basic_salary'] * 100 : 0;

$item = [
    'id'           => $loan['id'],
    'employee_id'  => (string) $loan['employee_id'],
    'amount'       => (string) $loan['amount'],
    'tenor_months' => (int) $loan['tenor_months'],
    'start_period' => $loan['start_period'],
    'purpose'      => (string) $loan['purpose'],
    'notes'        => (string) ($loan['notes'] ?? ''),
];

$actions = '<a href="' . url_to('loans.index') . '" class="btn btn-secondary">' . icon('arrow-left', 'size-4') . ' Daftar Pinjaman</a>';

if ($pending && $canManage) {
    $actions .= '<button type="button" class="btn btn-secondary" @click="edit(' . (int) $loan['id'] . ')">' . icon('pencil', 'size-4') . ' Ubah</button>';
}

if (($pending || $rejected) && $canManage) {
    $actions .= '<button type="button" class="btn btn-secondary text-rose-600" @click="$dispatch(\'confirm\', { title: \'Hapus pengajuan ' . esc($loan['loan_no'], 'js') . '?\', message: \'Pengajuan pinjaman akan dihapus permanen.\', action: \'' . esc(url_to('loans.delete', $loan['id']), 'js') . '\', confirmLabel: \'Hapus\', tone: \'danger\' })">' . icon('trash-2', 'size-4') . ' Hapus</button>';
}

if ($pending && $canApprove) {
    $actions .= '<button type="button" class="btn btn-danger" @click="$dispatch(\'confirm\', { title: \'Tolak pinjaman ' . esc($loan['loan_no'], 'js') . '?\', message: \'' . esc($loan['full_name'] . ' · ' . rupiah($loan['amount']), 'js') . '\', action: \'' . esc(url_to('loans.reject', $loan['id']), 'js') . '\', confirmLabel: \'Tolak\', tone: \'danger\', withNote: true, noteRequired: true, noteLabel: \'Alasan penolakan\' })">' . icon('x', 'size-4') . ' Tolak</button>'
        . '<button type="button" class="btn btn-primary" @click="$dispatch(\'confirm\', { title: \'Setujui pinjaman ' . esc($loan['loan_no'], 'js') . '?\', message: \'' . esc('Cicilan ' . rupiah($loan['installment']) . ' × ' . $loan['tenor_months'] . ' bulan dipotong dari gaji ' . $loan['full_name'] . ' mulai ' . $period($loan['start_period']) . '.', 'js') . '\', action: \'' . esc(url_to('loans.approve', $loan['id']), 'js') . '\', confirmLabel: \'Setujui\', withNote: true, noteLabel: \'Catatan (opsional)\' })">' . icon('check', 'size-4') . ' Setujui</button>';
}

$stateMeta = [
    LoanSchedule::PAID      => ['Terbayar', 'emerald'],
    LoanSchedule::DEDUCTING => ['Dipotong di payroll', 'amber'],
    LoanSchedule::SCHEDULED => ['Terjadwal', 'slate'],
];
?>
<div x-data="crudModal({
        empty: <?= js_data($item) ?>,
        items: <?= js_data([$loan['id'] => $item]) ?>,
        storeUrl: '',
        updateUrl: '<?= esc(site_url('loans/__ID__'), 'js') ?>',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Pinjaman · ' . $loan['loan_no'],
        'title'    => $loan['purpose'] ?: 'Pinjaman karyawan',
        'subtitle' => 'Diajukan ' . tanggal_id($loan['created_at'], 'long') . ($loan['notes'] ? ' · ' . $loan['notes'] : ''),
        'actions'  => $actions,
    ]) ?>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-1">
            <section class="card overflow-hidden">
                <div class="relative bg-linear-to-br from-brand-600 via-violet-600 to-fuchsia-600 p-6 text-white">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-white/75">Sisa pinjaman</p>
                            <p class="mt-1 text-3xl font-bold tracking-tight tabular-nums"><?= rupiah($loan['remaining']) ?></p>
                        </div>
                        <span class="rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold ring-1 ring-white/25"><?= esc(LoanModel::STATUSES[$loan['status']]) ?></span>
                    </div>
                    <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($paid * 100) ?>" aria-label="Persentase terbayar">
                        <div class="h-full rounded-full bg-white" style="width: <?= number_format($paid * 100, 1, '.', '') ?>%"></div>
                    </div>
                    <p class="mt-2 flex justify-between text-xs text-white/80">
                        <span><?= number_id($paid * 100) ?>% terbayar · <?= $paidRows ?> dari <?= count($schedule) ?: (int) $loan['tenor_months'] ?> cicilan</span>
                        <span>dari <?= rupiah($loan['amount']) ?></span>
                    </p>
                </div>
                <dl class="grid grid-cols-2 divide-x divide-y divide-slate-100 text-sm">
                    <?php foreach ([
                        ['Cicilan / bulan', rupiah($loan['installment']), number_id($ratio, 1) . '% gaji pokok'],
                        ['Tenor', $loan['tenor_months'] . ' bulan', 'Mulai ' . $period($loan['start_period'], true)],
                        ['Perkiraan lunas', $end ? $period($end) : '—', $rejected ? 'Pengajuan ditolak' : ($loan['status'] === LoanModel::STATUS_PAID_OFF ? 'Sudah lunas' : 'Sesuai jadwal')],
                        ['Terbayar', rupiah($loan['amount'] - $loan['remaining']), count($installments) . ' cicilan dibukukan'],
                    ] as [$label, $value, $hint]): ?>
                        <div class="p-4">
                            <dt class="text-xs text-slate-500"><?= esc($label) ?></dt>
                            <dd class="mt-0.5 font-bold text-slate-900 tabular-nums"><?= esc($value) ?></dd>
                            <dd class="text-xs text-slate-500"><?= esc($hint) ?></dd>
                        </div>
                    <?php endforeach ?>
                </dl>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Peminjam</h2>
                <a href="<?= url_to('employees.show', $loan['employee_id']) ?>#pinjaman" class="-mx-2 mt-1 flex items-center gap-3 rounded-xl p-2 hover:bg-slate-50">
                    <?= avatar($loan['full_name'], 'size-11 text-sm') ?>
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-slate-900"><?= esc($loan['full_name']) ?></span>
                        <span class="block truncate text-xs text-slate-500"><?= esc($loan['nip']) ?> · <?= esc($loan['position_name']) ?></span>
                        <span class="block text-xs text-slate-500">Gaji pokok <?= rupiah($loan['basic_salary']) ?></span>
                    </span>
                </a>
                <?php if ($loan['employee_status'] !== 'active' && ! in_array($loan['status'], [LoanModel::STATUS_PAID_OFF, LoanModel::STATUS_REJECTED], true)): ?>
                    <p class="mt-4 flex gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200"><?= icon('triangle-alert', 'size-4 shrink-0') ?> Karyawan sudah tidak aktif. Sisa pinjaman perlu diselesaikan di luar payroll.</p>
                <?php endif ?>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Persetujuan</h2>
                <?php if ($pending): ?>
                    <p class="mt-3 flex items-center gap-2 text-sm text-amber-700"><?= icon('hourglass', 'size-4') ?> Menunggu persetujuan Finance.</p>
                <?php else: ?>
                    <div class="mt-3 flex items-start gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full <?= $rejected ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' ?>"><?= icon($rejected ? 'circle-x' : 'circle-check', 'size-5') ?></span>
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold text-slate-900"><?= $rejected ? 'Ditolak' : 'Disetujui' ?> oleh <?= esc($loan['approver_name'] ?? '—') ?></p>
                            <p class="text-xs text-slate-500"><?= esc(tanggal_id($loan['approved_at'], 'datetime')) ?></p>
                            <?php if ($loan['review_note']): ?>
                                <p class="mt-2 rounded-lg bg-slate-50 p-2.5 text-slate-700 ring-1 ring-slate-100">“<?= esc($loan['review_note']) ?>”</p>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endif ?>
            </section>
        </div>

        <section class="card overflow-hidden xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900"><?= $pending ? 'Simulasi Jadwal Cicilan' : 'Jadwal Cicilan' ?></h2>
                <p class="mt-0.5 text-sm text-slate-500">Cicilan terbukukan saat periode payroll dikunci. Bila suatu bulan tidak ada payroll, jadwal bergeser ke bulan berikutnya.</p>
            </div>
            <?php if ($schedule === []): ?>
                <?= component('empty_state', ['title' => $rejected ? 'Pengajuan ditolak' : 'Tidak ada jadwal', 'description' => $rejected ? 'Pinjaman ini tidak akan dipotong dari gaji.' : null, 'icon' => 'calendar-x-2', 'compact' => true]) ?>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50/95">
                            <tr class="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                <th scope="col" class="px-5 py-3 text-left">Cicilan</th>
                                <th scope="col" class="px-5 py-3 text-left">Periode</th>
                                <th scope="col" class="px-5 py-3 text-right">Nominal</th>
                                <th scope="col" class="px-5 py-3 text-left">Status</th>
                                <th scope="col" class="px-5 py-3 text-right">Sisa setelahnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($schedule as $row): ?>
                                <?php [$label, $tone] = $stateMeta[$row['state']]; ?>
                                <tr class="<?= $row['state'] === LoanSchedule::SCHEDULED ? 'text-slate-500' : 'text-slate-800' ?>">
                                    <td class="px-5 py-3">
                                        <span class="flex size-7 items-center justify-center rounded-full text-xs font-bold <?= $row['state'] === LoanSchedule::PAID ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600' ?>">
                                            <?= $row['state'] === LoanSchedule::PAID ? icon('check', 'size-3.5') : $row['no'] ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 font-medium whitespace-nowrap"><?= esc($period($row['period'])) ?></td>
                                    <td class="px-5 py-3 text-right font-semibold tabular-nums"><?= rupiah($row['amount']) ?></td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= tone_classes($tone) ?>"><?= esc($label) ?></span>
                                        <?php if ($row['state'] === LoanSchedule::PAID): ?>
                                            <span class="ml-1 text-xs text-slate-500"><?= esc(tanggal_id($row['paid_at'])) ?></span>
                                        <?php elseif ($row['state'] === LoanSchedule::DEDUCTING && isset($periodIds[$row['period']]) && route_exists('payroll.show')): ?>
                                            <a href="<?= url_to('payroll.show', $periodIds[$row['period']]) ?>" class="ml-1 text-xs font-semibold text-brand-600 hover:text-brand-700"><?= esc(status_meta((string) $row['period_status'])['label']) ?> →</a>
                                        <?php endif ?>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums"><?= rupiah($row['balance']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    </div>

    <?php if ($pending && $canManage): ?>
        <?= view('loans/_form', ['employees' => $employees, 'firstPeriod' => $firstPeriod]) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
