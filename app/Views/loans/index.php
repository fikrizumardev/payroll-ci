<?php
/**
 * @var list<array<string, mixed>>                     $loans
 * @var CodeIgniter\Pager\Pager                        $pager
 * @var string                                         $status
 * @var string                                         $q
 * @var array<string, int>                             $counts
 * @var array{outstanding: int, principal: int}        $totals
 * @var array<int, array{label: string, salary: int}>  $employees
 * @var string                                         $firstPeriod
 * @var bool                                           $canManage
 * @var bool                                           $canApprove
 */

use App\Models\LoanModel;

$this->extend('layouts/app');
$this->section('content');

$period = static fn (string $p): string => periode_id((int) substr($p, 0, 4), (int) substr($p, 5, 2), true);
$query  = static fn (array $overrides): string => url_to('loans.index') . '?' . http_build_query(array_filter($overrides + ['status' => $status, 'q' => $q], static fn ($v) => $v !== '' && $v !== null));

$tabs = [
    'open'     => ['Berjalan & Menunggu', $counts['pending'] + $counts['approved'] + $counts['active']],
    'pending'  => ['Menunggu', $counts['pending']],
    'active'   => ['Berjalan', $counts['active']],
    'approved' => ['Disetujui', $counts['approved']],
    'paid_off' => ['Lunas', $counts['paid_off']],
    'rejected' => ['Ditolak', $counts['rejected']],
    'all'      => ['Semua', array_sum($counts)],
];

$rows = [];

foreach ($loans as $loan) {
    $paid = $loan['amount'] > 0 ? 1 - $loan['remaining'] / $loan['amount'] : 0;
    $tone = $loan['status'] === LoanModel::STATUS_PAID_OFF ? 'from-emerald-500 to-teal-500' : 'from-brand-500 to-violet-500';

    $rows[] = [
        '<a href="' . url_to('loans.show', $loan['id']) . '" class="group block"><span class="font-mono text-xs font-semibold text-brand-700 group-hover:underline">' . esc($loan['loan_no']) . '</span>'
            . '<span class="block max-w-40 truncate text-slate-700">' . esc($loan['purpose'] ?? '—') . '</span></a>',
        '<div class="flex items-center gap-3">' . avatar($loan['full_name']) . '<span class="min-w-0 max-w-40"><a href="' . url_to('employees.show', $loan['employee_id']) . '#pinjaman" class="block truncate font-semibold text-slate-900 hover:text-brand-700">' . esc($loan['full_name']) . '</a>'
            . '<span class="block truncate text-xs text-slate-500">' . esc($loan['nip']) . ' · ' . esc($loan['department_name']) . '</span></span></div>',
        rupiah($loan['installment']) . '<span class="block text-xs text-slate-500">× ' . (int) $loan['tenor_months'] . ' bln · mulai ' . esc($period($loan['start_period'])) . '</span>',
        '<div class="min-w-36"><div class="flex items-baseline justify-between gap-2"><span class="font-semibold text-slate-900">' . rupiah($loan['remaining']) . '</span><span class="text-xs text-slate-500">' . number_id($paid * 100) . '%</span></div>'
            . '<div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) round($paid * 100) . '" aria-label="Terbayar"><div class="h-full rounded-full bg-linear-to-r ' . $tone . '" style="width: ' . number_format($paid * 100, 1, '.', '') . '%"></div></div>'
            . '<span class="mt-1 block text-xs text-slate-500">dari pokok ' . rupiah($loan['amount']) . '</span></div>',
        status_badge($loan['status'], $loan['status'] === LoanModel::STATUS_PENDING ? 'Menunggu' : LoanModel::STATUSES[$loan['status']]),
    ];
}

$total   = $pager->getTotal();
$page    = $pager->getCurrentPage();
$perPage = $pager->getPerPage();
?>
<div x-data="crudModal({
        empty: { id: null, employee_id: '', amount: '', tenor_months: 6, start_period: '<?= esc($firstPeriod, 'js') ?>', purpose: '', notes: '' },
        storeUrl: '<?= esc(url_to('loans.store'), 'js') ?>',
        updateUrl: '',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Kehadiran',
        'title'    => 'Pinjaman Karyawan',
        'subtitle' => 'Kasbon & pinjaman dengan cicilan potong gaji. Diajukan HR, disetujui Finance, dan dibukukan otomatis saat periode payroll dikunci.',
        'actions'  => $canManage ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Ajukan Pinjaman</button>' : null,
    ]) ?>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <?= component('kpi_card', ['label' => 'Sisa pinjaman berjalan', 'value' => rupiah($totals['outstanding']), 'icon' => 'hand-coins', 'tone' => 'brand', 'hint' => 'Dari pokok ' . rupiah($totals['principal'])]) ?>
        <?= component('kpi_card', ['label' => 'Pinjaman berjalan', 'value' => ($counts['active'] + $counts['approved']) . ' pinjaman', 'icon' => 'repeat', 'tone' => 'sky', 'hint' => $counts['approved'] . ' belum mulai dipotong']) ?>
        <?= component('kpi_card', ['label' => 'Menunggu persetujuan', 'value' => $counts['pending'] . ' pengajuan', 'icon' => 'hourglass', 'tone' => 'amber', 'href' => $query(['status' => 'pending', 'page' => null]), 'hint' => $canApprove ? 'Perlu tindakan Anda' : 'Disetujui oleh Finance']) ?>
        <?= component('kpi_card', ['label' => 'Lunas', 'value' => $counts['paid_off'] . ' pinjaman', 'icon' => 'badge-check', 'tone' => 'emerald']) ?>
    </div>

    <div class="card mb-6 overflow-hidden">
        <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3" role="tablist" aria-label="Status pinjaman">
            <?php foreach ($tabs as $key => [$label, $count]): ?>
                <a href="<?= esc($query(['status' => $key, 'page' => null]), 'attr') ?>" role="tab" aria-selected="<?= $status === $key ? 'true' : 'false' ?>"
                   class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 pb-3 text-sm font-semibold transition <?= $status === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <?= esc($label) ?>
                    <span class="rounded-full px-2 py-0.5 text-xs <?= $status === $key ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' ?>"><?= $count ?></span>
                </a>
            <?php endforeach ?>
        </div>
        <form method="get" action="<?= url_to('loans.index') ?>" class="flex gap-3 p-4" role="search">
            <input type="hidden" name="status" value="<?= esc($status, 'attr') ?>">
            <div class="relative flex-1">
                <label for="loan-q" class="sr-only">Cari pinjaman</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"><?= icon('search', 'size-4') ?></span>
                <input id="loan-q" type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="Cari nama, NIP, atau no. pinjaman…" class="form-control pl-10">
            </div>
            <button type="submit" class="btn btn-secondary"><?= icon('filter', 'size-4') ?> Cari</button>
        </form>
    </div>

    <?= component('data_table', [
        'title'    => 'Daftar Pinjaman',
        'subtitle' => $total === 0 ? 'Tidak ada data' : sprintf('Menampilkan %d–%d dari %d pinjaman', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total),
        'columns'  => [
            ['label' => 'No. Pinjaman'],
            ['label' => 'Karyawan'],
            ['label' => 'Cicilan', 'align' => 'right'],
            ['label' => 'Sisa Pinjaman'],
            ['label' => 'Status'],
        ],
        'rows'   => $rows,
        'empty'  => [
            'title'       => 'Tidak ada pinjaman',
            'description' => $q !== '' ? 'Coba ubah kata kunci pencarian.' : 'Belum ada pinjaman pada kategori ini.',
            'icon'        => 'hand-coins',
        ],
        'footer' => $pager->getPageCount() > 1 ? $pager->links('default', 'tailwind') : null,
    ]) ?>

    <?php if ($canManage): ?>
        <?= view('loans/_form', ['employees' => $employees, 'firstPeriod' => $firstPeriod]) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
