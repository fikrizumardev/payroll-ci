<?php
/**
 * @var list<array<string, mixed>> $departments
 * @var array<int, string>         $employees
 * @var bool                       $canManage
 */
$this->extend('layouts/app');
$this->section('content');

$items = [];

foreach ($departments as $department) {
    $items[$department['id']] = [
        'id'               => $department['id'],
        'code'             => $department['code'],
        'name'             => $department['name'],
        'description'      => $department['description'] ?? '',
        'head_employee_id' => (string) ($department['head_employee_id'] ?? ''),
    ];
}

$totalEmployees = array_sum(array_column($departments, 'employee_count'));
?>
<div x-data="crudModal({
        empty: { id: null, code: '', name: '', description: '', head_employee_id: '' },
        items: <?= js_data($items) ?>,
        storeUrl: '<?= esc(url_to('departments.store'), 'js') ?>',
        updateUrl: '<?= esc(site_url('departments/__ID__'), 'js') ?>',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Master Data',
        'title'    => 'Departemen',
        'subtitle' => count($departments) . ' departemen · ' . $totalEmployees . ' karyawan aktif. Kepala departemen menjadi penanggung jawab persetujuan di unitnya.',
        'actions'  => $canManage ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Tambah Departemen</button>' : null,
    ]) ?>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($departments as $department): ?>
            <?php $share = $totalEmployees > 0 ? $department['employee_count'] / $totalEmployees * 100 : 0; ?>
            <article class="card group flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br <?= avatar_color($department['code']) ?> text-sm font-bold text-white shadow-sm">
                            <?= esc($department['code']) ?>
                        </span>
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold text-slate-900"><?= esc($department['name']) ?></h2>
                            <p class="text-xs text-slate-500"><?= $department['position_count'] ?> jabatan · <?= $department['employee_count'] ?> karyawan</p>
                        </div>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="edit(<?= (int) $department['id'] ?>)" aria-label="Ubah <?= esc($department['name'], 'attr') ?>">
                                <?= icon('pencil', 'size-4') ?>
                            </button>
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus <?= esc($department['name'], 'attr') ?>"
                                    @click="$dispatch('confirm', { title: 'Hapus departemen <?= esc($department['name'], 'js') ?>?', message: 'Departemen hanya bisa dihapus bila tidak memiliki jabatan maupun karyawan.', action: '<?= esc(url_to('departments.delete', $department['id']), 'js') ?>', confirmLabel: 'Hapus', tone: 'danger' })">
                                <?= icon('trash-2', 'size-4') ?>
                            </button>
                        </div>
                    <?php endif ?>
                </div>

                <p class="mt-4 line-clamp-2 min-h-10 text-sm text-slate-600"><?= esc($department['description'] ?? 'Belum ada deskripsi.') ?></p>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>Porsi karyawan</span>
                        <span class="font-semibold text-slate-700"><?= number_id($share, 1) ?>%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-linear-to-r from-brand-500 to-violet-500" style="width: <?= number_format($share, 1, '.', '') ?>%"></div>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3 border-t border-slate-100 pt-4">
                    <?php if ($department['head_name']): ?>
                        <?= avatar($department['head_name'], 'size-8 text-[11px]') ?>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Kepala departemen</p>
                            <a href="<?= url_to('employees.show', $department['head_employee_id']) ?>" class="truncate text-sm font-semibold text-slate-800 hover:text-brand-700"><?= esc($department['head_name']) ?></a>
                        </div>
                    <?php else: ?>
                        <span class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-500"><?= icon('user-round-x', 'size-4') ?></span>
                        <p class="text-sm text-slate-500">Kepala departemen belum ditentukan</p>
                    <?php endif ?>
                    <a href="<?= url_to('employees.index') ?>?department_id=<?= (int) $department['id'] ?>" class="ml-auto text-xs font-semibold text-brand-600 hover:text-brand-700">Lihat karyawan →</a>
                </div>
            </article>
        <?php endforeach ?>
    </div>

    <?php if ($departments === []): ?>
        <div class="card">
            <?= component('empty_state', ['title' => 'Belum ada departemen', 'icon' => 'building-2', 'description' => 'Tambahkan departemen pertama untuk mulai menyusun struktur organisasi.']) ?>
        </div>
    <?php endif ?>

    <?php if ($canManage): ?>
        <?= component('modal_open', [
            'title'     => 'Departemen',
            'titleExpr' => "mode === 'create' ? 'Tambah Departemen' : 'Ubah Departemen'",
            'subtitle'  => 'Kode singkat dipakai sebagai label di laporan, mis. ENG atau FIN.',
            'action'    => 'action',
        ]) ?>
            <div class="grid gap-5 sm:grid-cols-3">
                <?= component('form_input', ['name' => 'code', 'label' => 'Kode', 'required' => true, 'placeholder' => 'ENG', 'attrs' => ['id' => 'dept-code', 'x-model' => 'form.code', 'maxlength' => 10, 'autocapitalize' => 'characters']]) ?>
                <div class="sm:col-span-2">
                    <?= component('form_input', ['name' => 'name', 'label' => 'Nama departemen', 'required' => true, 'placeholder' => 'Engineering', 'attrs' => ['id' => 'dept-name', 'x-model' => 'form.name', 'maxlength' => 100]]) ?>
                </div>
            </div>
            <?= component('form_select', ['name' => 'head_employee_id', 'label' => 'Kepala departemen', 'options' => $employees, 'placeholder' => '— Belum ditentukan —', 'attrs' => ['id' => 'dept-head', 'x-model' => 'form.head_employee_id']]) ?>
            <div>
                <label for="dept-description" class="form-label">Deskripsi</label>
                <textarea id="dept-description" name="description" rows="3" maxlength="255" class="form-control" x-model="form.description" placeholder="Ruang lingkup tugas departemen"></textarea>
            </div>
        <?= component('modal_close', ['submitLabel' => 'Simpan Departemen']) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
