<?php
/**
 * @var array<int, array{name: string, code: string, items: list<array<string, mixed>>}> $grouped
 * @var int                $total
 * @var array<int, string> $departments
 * @var bool               $canManage
 */
$this->extend('layouts/app');
$this->section('content');

$items = [];

foreach ($grouped as $group) {
    foreach ($group['items'] as $position) {
        $items[$position['id']] = [
            'id'                      => $position['id'],
            'department_id'           => (string) $position['department_id'],
            'code'                    => $position['code'],
            'name'                    => $position['name'],
            'grade'                   => (string) $position['grade'],
            'base_salary'             => (string) $position['base_salary'],
            'position_allowance'      => (string) $position['position_allowance'],
            'communication_allowance' => (string) $position['communication_allowance'],
            'overtime_eligible'       => (bool) $position['overtime_eligible'],
        ];
    }
}
?>
<div x-data="crudModal({
        empty: { id: null, department_id: '', code: '', name: '', grade: '1', base_salary: '', position_allowance: '0', communication_allowance: '0', overtime_eligible: true },
        items: <?= js_data($items) ?>,
        storeUrl: '<?= esc(url_to('positions.store'), 'js') ?>',
        updateUrl: '<?= esc(site_url('positions/__ID__'), 'js') ?>',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Master Data',
        'title'    => 'Jabatan',
        'subtitle' => "{$total} jabatan di " . count($grouped) . ' departemen. Gaji pokok & tunjangan standar dipakai sebagai nilai awal saat menambah karyawan.',
        'actions'  => $canManage ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Tambah Jabatan</button>' : null,
    ]) ?>

    <div class="space-y-6">
        <?php foreach ($grouped as $departmentId => $group): ?>
            <?php
            $rows = [];

            foreach ($group['items'] as $position) {
                $range = $position['min_salary'] === null
                    ? '<span class="text-slate-500">—</span>'
                    : ($position['min_salary'] === $position['max_salary']
                        ? rupiah($position['min_salary'])
                        : rupiah($position['min_salary']) . ' – ' . rupiah($position['max_salary']));

                $actions = '';

                if ($canManage) {
                    $actions = '<div class="flex justify-end gap-1">'
                        . '<button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="edit(' . (int) $position['id'] . ')" aria-label="Ubah ' . esc($position['name'], 'attr') . '">' . icon('pencil', 'size-4') . '</button>'
                        . '<button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus ' . esc($position['name'], 'attr') . '"'
                        . ' @click="$dispatch(\'confirm\', { title: \'Hapus jabatan ' . esc($position['name'], 'js') . '?\', message: \'Jabatan yang masih dipakai karyawan tidak bisa dihapus.\', action: \'' . esc(url_to('positions.delete', $position['id']), 'js') . '\', confirmLabel: \'Hapus\', tone: \'danger\' })">'
                        . icon('trash-2', 'size-4') . '</button></div>';
                }

                $rows[] = array_filter([
                    '<p class="font-semibold text-slate-900">' . esc($position['name']) . '</p><p class="text-xs text-slate-500">' . esc($position['code']) . '</p>',
                    '<span class="inline-flex size-7 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-700">' . (int) $position['grade'] . '</span>',
                    '<span class="font-semibold text-slate-900">' . rupiah($position['base_salary']) . '</span>',
                    $range,
                    rupiah($position['position_allowance']),
                    rupiah($position['communication_allowance']),
                    $position['overtime_eligible'] ? status_badge('active', 'Ya') : status_badge('inactive', 'Tidak'),
                    '<span class="font-semibold text-slate-900">' . $position['employee_count'] . '</span>',
                    $canManage ? $actions : null,
                ], static fn ($cell) => $cell !== null);
            }
            ?>
            <?= component('data_table', [
                'title'    => $group['name'],
                'subtitle' => count($group['items']) . ' jabatan',
                'toolbar'  => '<span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">' . esc($group['code']) . '</span>',
                'columns'  => array_values(array_filter([
                    ['label' => 'Jabatan'],
                    ['label' => 'Grade', 'align' => 'center'],
                    ['label' => 'Gaji Pokok Standar', 'align' => 'right'],
                    ['label' => 'Rentang Aktual', 'align' => 'right'],
                    ['label' => 'Tunj. Jabatan', 'align' => 'right'],
                    ['label' => 'Tunj. Komunikasi', 'align' => 'right'],
                    ['label' => 'Lembur', 'align' => 'center'],
                    ['label' => 'Karyawan', 'align' => 'center'],
                    $canManage ? ['label' => 'Aksi', 'align' => 'right', 'mobile' => false] : null,
                ])),
                'rows'     => $rows,
            ]) ?>
        <?php endforeach ?>
    </div>

    <?php if ($canManage): ?>
        <?= component('modal_open', [
            'title'     => 'Jabatan',
            'titleExpr' => "mode === 'create' ? 'Tambah Jabatan' : 'Ubah Jabatan'",
            'action'    => 'action',
            'size'      => 'max-w-2xl',
        ]) ?>
            <div class="grid gap-5 sm:grid-cols-2">
                <?= component('form_select', ['name' => 'department_id', 'label' => 'Departemen', 'options' => $departments, 'placeholder' => 'Pilih departemen', 'required' => true, 'attrs' => ['id' => 'pos-department', 'x-model' => 'form.department_id']]) ?>
                <div class="grid grid-cols-2 gap-5">
                    <?= component('form_input', ['name' => 'code', 'label' => 'Kode', 'required' => true, 'placeholder' => 'ENG-06', 'attrs' => ['id' => 'pos-code', 'x-model' => 'form.code', 'maxlength' => 10]]) ?>
                    <?= component('form_input', ['name' => 'grade', 'label' => 'Grade', 'type' => 'number', 'required' => true, 'attrs' => ['id' => 'pos-grade', 'x-model' => 'form.grade', 'min' => 1, 'max' => 20]]) ?>
                </div>
            </div>
            <?= component('form_input', ['name' => 'name', 'label' => 'Nama jabatan', 'required' => true, 'placeholder' => 'Data Analyst', 'attrs' => ['id' => 'pos-name', 'x-model' => 'form.name', 'maxlength' => 100]]) ?>
            <div class="grid gap-5 sm:grid-cols-3">
                <?= component('form_money', ['name' => 'base_salary', 'label' => 'Gaji pokok standar', 'required' => true, 'model' => 'form.base_salary', 'id' => 'pos-base-salary']) ?>
                <?= component('form_money', ['name' => 'position_allowance', 'label' => 'Tunjangan jabatan', 'model' => 'form.position_allowance', 'id' => 'pos-position-allowance']) ?>
                <?= component('form_money', ['name' => 'communication_allowance', 'label' => 'Tunjangan komunikasi', 'model' => 'form.communication_allowance', 'id' => 'pos-comm-allowance']) ?>
            </div>
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <input type="hidden" name="overtime_eligible" value="0">
                <input type="checkbox" name="overtime_eligible" value="1" x-model="form.overtime_eligible" class="mt-0.5 size-4 rounded accent-brand-600">
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Berhak upah lembur</span>
                    <span class="block text-xs text-slate-500">Umumnya tidak berlaku untuk level manajer ke atas (PP 35/2021).</span>
                </span>
            </label>
        <?= component('modal_close', ['submitLabel' => 'Simpan Jabatan']) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
