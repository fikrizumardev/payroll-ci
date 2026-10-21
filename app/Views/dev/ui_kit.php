<?php
/**
 * Katalog komponen UI (development).
 *
 * @var list<array<string, mixed>>  $employees
 * @var CodeIgniter\Pager\Pager     $pager
 * @var array<int, string>          $departments
 */
$this->extend('layouts/app');
$this->section('content');

$section = static fn (string $title, string $description): string => '<div class="mb-4"><h2 class="text-lg font-semibold text-slate-900">'
    . esc($title) . '</h2><p class="mt-0.5 text-sm text-slate-500">' . esc($description) . '</p></div>';
?>

<?= component('page_header', [
    'eyebrow'  => 'Developer',
    'title'    => 'UI Kit PayrollKu',
    'subtitle' => 'Katalog komponen yang dipakai di seluruh aplikasi. Halaman ini hanya tersedia di environment development.',
    'actions'  => '<button type="button" class="btn btn-secondary" @click="openPalette()">' . icon('command', 'size-4') . ' Command palette</button>'
        . '<button type="button" class="btn btn-primary" onclick="PayrollKu.toast(\'success\', \'Contoh toast berhasil ditampilkan.\')">' . icon('bell-ring', 'size-4') . ' Tampilkan toast</button>',
]) ?>

<div class="space-y-12">
    <!-- KPI -->
    <section>
        <?= $section('KPI Card', 'Ikon bergradien, nilai besar, delta, dan sparkline opsional.') ?>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?= component('kpi_card', ['label' => 'Total Gaji Bersih', 'value' => rupiah(312_480_550), 'icon' => 'banknote', 'tone' => 'brand', 'delta' => 3.4, 'trend' => [280, 284, 290, 296, 301, 312]]) ?>
            <?= component('kpi_card', ['label' => 'Total BPJS', 'value' => rupiah(41_870_200), 'icon' => 'shield-check', 'tone' => 'emerald', 'delta' => 1.2, 'trend' => [39, 40, 40, 41, 41, 42]]) ?>
            <?= component('kpi_card', ['label' => 'Biaya Lembur', 'value' => rupiah(8_150_000), 'icon' => 'clock-4', 'tone' => 'amber', 'delta' => 12.5, 'inverse' => true, 'trend' => [5, 7, 6, 8, 7, 8]]) ?>
            <?= component('kpi_card', ['label' => 'PPh 21 Bulan Ini', 'value' => rupiah(27_905_140), 'icon' => 'landmark', 'tone' => 'rose', 'hint' => 'Metode TER · kategori A/B/C']) ?>
        </div>
    </section>

    <!-- Tombol & badge -->
    <section class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <?= $section('Tombol', 'Primary bergradien, secondary, ghost, danger, dan state disabled.') ?>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-primary"><?= icon('plus', 'size-4') ?> Tambah Karyawan</button>
                <button type="button" class="btn btn-secondary"><?= icon('download', 'size-4') ?> Ekspor CSV</button>
                <button type="button" class="btn btn-ghost"><?= icon('filter', 'size-4') ?> Filter</button>
                <button type="button" class="btn btn-danger"><?= icon('trash-2', 'size-4') ?> Hapus</button>
                <button type="button" class="btn btn-primary" disabled>Disabled</button>
            </div>
        </div>
        <div class="card p-6">
            <?= $section('Badge Status', 'Warna konsisten untuk status payroll, karyawan, kehadiran, dan persetujuan.') ?>
            <div class="space-y-3">
                <?php foreach ([
                    ['draft', 'review', 'approved', 'paid', 'locked'],
                    ['active', 'resigned', 'permanent', 'contract', 'probation'],
                    ['present', 'late', 'sick', 'leave', 'permit', 'absent'],
                    ['pending', 'rejected', 'paid_off', 'inactive'],
                ] as $group): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($group as $status): ?>
                            <?= component('badge', ['status' => $status]) ?>
                        <?php endforeach ?>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </section>

    <!-- Form -->
    <section class="card p-6">
        <?= $section('Form', 'Input dengan ikon & suffix, select, input rupiah otomatis, dan state error.') ?>
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <?= component('form_input', ['name' => 'demo_name', 'label' => 'Nama lengkap', 'value' => 'Andi Pratama', 'icon' => 'user', 'required' => true]) ?>
            <?= component('form_select', ['name' => 'demo_department', 'label' => 'Departemen', 'options' => $departments, 'placeholder' => 'Pilih departemen', 'help' => 'Data dari tabel departments']) ?>
            <?= component('form_money', ['name' => 'demo_salary', 'label' => 'Gaji pokok', 'value' => 12_000_000, 'help' => 'Ketik angka, format ribuan otomatis']) ?>
            <?= component('form_input', ['name' => 'demo_hours', 'label' => 'Durasi lembur', 'type' => 'number', 'value' => 3, 'suffix' => 'jam', 'attrs' => ['min' => 1, 'max' => 12]]) ?>
            <?= component('form_input', ['name' => 'demo_date', 'label' => 'Tanggal masuk', 'type' => 'date', 'value' => '2022-01-10', 'icon' => 'calendar']) ?>
            <?= component('form_input', ['name' => 'demo_nik', 'label' => 'NIK', 'value' => '31740123', 'icon' => 'id-card', 'error' => 'NIK harus 16 digit.']) ?>
        </div>
    </section>

    <!-- Tabel -->
    <section>
        <?= $section('Data Table + Pagination', 'Data karyawan nyata dari database. Di layar kecil tabel berubah menjadi kartu bertumpuk.') ?>
        <?php
        $rows = [];

        foreach ($employees as $employee) {
            $rows[] = [
                '<div class="flex items-center gap-3">' . avatar($employee['full_name']) . '<div class="min-w-0"><p class="truncate font-semibold text-slate-900">'
                    . esc($employee['full_name']) . '</p><p class="text-xs text-slate-500">' . esc($employee['nip']) . '</p></div></div>',
                '<p class="text-slate-800">' . esc($employee['position_name']) . '</p><p class="text-xs text-slate-500">' . esc($employee['department_name']) . '</p>',
                '<span class="font-mono text-xs text-slate-500">' . esc(mask_id($employee['nik'])) . '</span>',
                status_badge($employee['employment_type']),
                '<span class="font-semibold text-slate-900">' . rupiah($employee['basic_salary']) . '</span>',
                status_badge($employee['status']),
            ];
        }
        ?>
        <?= component('data_table', [
            'title'    => 'Karyawan',
            'subtitle' => $pager->getTotal() . ' karyawan terdaftar',
            'toolbar'  => '<button type="button" class="btn btn-secondary py-2">' . icon('download', 'size-4') . ' Ekspor</button>',
            'columns'  => [
                ['label' => 'Karyawan'],
                ['label' => 'Jabatan'],
                ['label' => 'NIK', 'mobile' => false],
                ['label' => 'Status Kerja'],
                ['label' => 'Gaji Pokok', 'align' => 'right'],
                ['label' => 'Status', 'align' => 'center'],
            ],
            'rows'   => $rows,
            'footer' => $pager->links('default', 'tailwind'),
        ]) ?>
    </section>

    <!-- Empty state & modal -->
    <section class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <?= component('empty_state', [
                'title'       => 'Belum ada periode payroll',
                'description' => 'Buat periode pertama untuk mulai menghitung gaji karyawan bulan ini.',
                'icon'        => 'banknote',
                'action'      => '<button type="button" class="btn btn-primary">' . icon('plus', 'size-4') . ' Buat Periode</button>',
            ]) ?>
        </div>
        <div class="card p-6">
            <?= $section('Modal Konfirmasi', 'Dipakai untuk aksi destruktif & perubahan status. Mendukung kolom catatan.') ?>
            <div class="flex flex-wrap gap-3">
                <button type="button" class="btn btn-primary"
                        @click="$dispatch('confirm', { title: 'Setujui payroll Oktober 2026?', message: 'Payroll akan diteruskan ke tahap pembayaran.', action: '<?= esc(url_to('dev.ui-kit.confirm'), 'js') ?>', confirmLabel: 'Ya, setujui' })">
                    <?= icon('circle-check', 'size-4') ?> Konfirmasi biasa
                </button>
                <button type="button" class="btn btn-danger"
                        @click="$dispatch('confirm', { title: 'Tolak payroll?', message: 'Periode akan dikembalikan ke status draft.', action: '<?= esc(url_to('dev.ui-kit.confirm'), 'js') ?>', confirmLabel: 'Tolak', tone: 'danger', withNote: true, noteRequired: true, noteLabel: 'Alasan penolakan' })">
                    <?= icon('circle-x', 'size-4') ?> Dengan catatan
                </button>
            </div>
            <div class="mt-6 flex flex-wrap gap-2">
                <?php foreach (['success' => 'Data berhasil disimpan.', 'error' => 'Gagal menyimpan data.', 'warning' => 'Periode belum dikunci.', 'info' => 'Payroll sedang diproses.'] as $type => $message): ?>
                    <button type="button" class="btn btn-secondary py-2 text-xs" onclick="PayrollKu.toast('<?= $type ?>', '<?= esc($message, 'js') ?>')">Toast <?= $type ?></button>
                <?php endforeach ?>
            </div>
        </div>
    </section>

    <!-- Avatar & tipografi -->
    <section class="card p-6">
        <?= $section('Avatar & Tipografi', 'Avatar inisial bergradien yang konsisten untuk nama yang sama; angka memakai tabular-nums.') ?>
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex -space-x-2">
                <?php foreach (array_slice($employees, 0, 6) as $employee): ?>
                    <?= avatar($employee['full_name'], 'size-10 text-xs') ?>
                <?php endforeach ?>
            </div>
            <div>
                <p class="text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums"><?= rupiah(9_574_785) ?></p>
                <p class="text-sm text-slate-500 italic"><?= esc(terbilang_rupiah(9_574_785)) ?></p>
            </div>
        </div>
    </section>
</div>
<?php $this->endSection() ?>
