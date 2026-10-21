<?php
/**
 * @var list<array<string, mixed>>                                $employees
 * @var CodeIgniter\Pager\Pager                                   $pager
 * @var array{q: string, department_id: string, status: string, type: string} $filters
 * @var array<string, int>                                        $counts
 * @var array<int, string>                                        $departments
 * @var bool                                                      $canManage
 */

use App\Models\EmployeeModel;

$this->extend('layouts/app');
$this->section('content');

$query = static fn (array $overrides): string => url_to('employees.index') . '?' . http_build_query(array_filter($overrides + $filters, static fn ($v) => $v !== '' && $v !== null));

$statusTabs = [
    'active'     => ['Aktif', $counts['active'] ?? 0],
    'resigned'   => ['Resign', $counts['resigned'] ?? 0],
    'terminated' => ['Diberhentikan', $counts['terminated'] ?? 0],
    'all'        => ['Semua', array_sum($counts)],
];

$hasFilter = $filters['q'] !== '' || $filters['department_id'] !== '' || $filters['type'] !== '';
$total     = $pager->getTotal();
$page      = $pager->getCurrentPage();
$perPage   = $pager->getPerPage();

$exportUrl = url_to('employees.export') . '?' . http_build_query(array_filter($filters));
$actions   = '<a href="' . esc($exportUrl, 'attr') . '" class="btn btn-secondary">' . icon('download', 'size-4') . ' Ekspor CSV</a>';

if ($canManage) {
    $actions .= '<a href="' . url_to('employees.create') . '" class="btn btn-primary">' . icon('user-plus', 'size-4') . ' Tambah Karyawan</a>';
}

$rows = [];

foreach ($employees as $employee) {
    $years  = (new DateTimeImmutable($employee['join_date']))->diff(new DateTimeImmutable($employee['resign_date'] ?? 'today'));
    $tenure = $years->y > 0 ? $years->y . ' th ' . $years->m . ' bln' : $years->m . ' bln';

    $rows[] = [
        '<a href="' . url_to('employees.show', $employee['id']) . '" class="group flex items-center gap-3">' . avatar($employee['full_name'])
            . '<span class="min-w-0"><span class="block truncate font-semibold text-slate-900 group-hover:text-brand-700">' . esc($employee['full_name']) . '</span>'
            . '<span class="block text-xs text-slate-500">' . esc($employee['nip']) . '</span></span></a>',
        '<p class="text-slate-800">' . esc($employee['position_name']) . '</p><p class="text-xs text-slate-500">' . esc($employee['department_name']) . '</p>',
        '<span class="font-mono text-xs text-slate-600">' . esc(mask_id($employee['nik'])) . '</span>',
        status_badge($employee['employment_type']),
        '<p class="text-slate-800">' . esc(tanggal_id($employee['join_date'])) . '</p><p class="text-xs text-slate-500">' . esc($tenure) . '</p>',
        '<span class="font-semibold text-slate-900">' . rupiah($employee['basic_salary']) . '</span>',
        status_badge($employee['status']),
    ];
}
?>
<?= component('page_header', [
    'eyebrow'  => 'Master Data',
    'title'    => 'Karyawan',
    'subtitle' => 'Data kepegawaian, gaji pokok, status pajak, dan rekening pembayaran gaji.',
    'actions'  => $actions,
]) ?>

<div class="card mb-6 overflow-hidden">
    <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3" role="tablist" aria-label="Status karyawan">
        <?php foreach ($statusTabs as $status => [$label, $count]): ?>
            <a href="<?= esc($query(['status' => $status, 'page' => null]), 'attr') ?>" role="tab" aria-selected="<?= $filters['status'] === $status ? 'true' : 'false' ?>"
               class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 pb-3 text-sm font-semibold transition <?= $filters['status'] === $status ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                <?= esc($label) ?>
                <span class="rounded-full px-2 py-0.5 text-xs <?= $filters['status'] === $status ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' ?>"><?= $count ?></span>
            </a>
        <?php endforeach ?>
    </div>

    <form method="get" action="<?= url_to('employees.index') ?>" class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_14rem_12rem_auto]" role="search">
        <input type="hidden" name="status" value="<?= esc($filters['status'], 'attr') ?>">
        <div class="relative">
            <label for="filter-q" class="sr-only">Cari karyawan</label>
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"><?= icon('search', 'size-4') ?></span>
            <input id="filter-q" name="q" type="search" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Cari nama atau NIP…" class="form-control pl-10">
        </div>
        <div>
            <label for="filter-department" class="sr-only">Departemen</label>
            <select id="filter-department" name="department_id" class="form-control" onchange="this.form.submit()">
                <option value="">Semua departemen</option>
                <?php foreach ($departments as $id => $name): ?>
                    <option value="<?= (int) $id ?>" <?= (string) $id === $filters['department_id'] ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="filter-type" class="sr-only">Status kerja</label>
            <select id="filter-type" name="type" class="form-control" onchange="this.form.submit()">
                <option value="">Semua status kerja</option>
                <?php foreach (EmployeeModel::EMPLOYMENT_TYPES as $type => $label): ?>
                    <option value="<?= $type ?>" <?= $type === $filters['type'] ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-secondary flex-1 lg:flex-none"><?= icon('filter', 'size-4') ?> Terapkan</button>
            <?php if ($hasFilter): ?>
                <a href="<?= esc(url_to('employees.index') . '?status=' . $filters['status'], 'attr') ?>" class="btn btn-ghost" aria-label="Reset filter"><?= icon('x', 'size-4') ?></a>
            <?php endif ?>
        </div>
    </form>
</div>

<?= component('data_table', [
    'title'    => 'Daftar Karyawan',
    'subtitle' => $total === 0 ? 'Tidak ada data' : sprintf('Menampilkan %d–%d dari %d karyawan', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total),
    'columns'  => [
        ['label' => 'Karyawan'],
        ['label' => 'Jabatan'],
        ['label' => 'NIK', 'mobile' => false],
        ['label' => 'Status Kerja'],
        ['label' => 'Tgl Masuk'],
        ['label' => 'Gaji Pokok', 'align' => 'right'],
        ['label' => 'Status', 'align' => 'center'],
    ],
    'rows'   => $rows,
    'empty'  => [
        'title'       => $hasFilter ? 'Tidak ada karyawan yang cocok' : 'Belum ada karyawan',
        'description' => $hasFilter ? 'Coba ubah kata kunci atau filter.' : 'Tambahkan karyawan pertama untuk mulai menghitung payroll.',
        'icon'        => 'users-round',
    ],
    'footer' => $pager->getPageCount() > 1 ? $pager->links('default', 'tailwind') : null,
]) ?>
<?php $this->endSection() ?>
