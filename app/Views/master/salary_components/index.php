<?php
/**
 * @var array<string, list<array<string, mixed>>> $grouped
 * @var bool                                      $canManage
 */

use App\Models\SalaryComponentModel;

$this->extend('layouts/app');
$this->section('content');

$items = [];

foreach ($grouped as $components) {
    foreach ($components as $component) {
        $items[$component['id']] = [
            'id'             => $component['id'],
            'code'           => $component['code'],
            'name'           => $component['name'],
            'type'           => $component['type'],
            'calc_type'      => $component['calc_type'],
            'default_amount' => (string) $component['default_amount'],
            'taxable'        => (bool) $component['taxable'],
            'is_bpjs_base'   => (bool) $component['is_bpjs_base'],
            'is_active'      => (bool) $component['is_active'],
            'sort_order'     => (string) $component['sort_order'],
            'description'    => $component['description'] ?? '',
            'is_system'      => (bool) $component['is_system'],
        ];
    }
}

$typeMeta = [
    'earning'              => ['Pendapatan', 'Ditambahkan ke gaji karyawan.', 'trending-up', 'emerald'],
    'deduction'            => ['Potongan', 'Mengurangi gaji yang diterima karyawan.', 'trending-down', 'rose'],
    'company_contribution' => ['Kontribusi Perusahaan', 'Ditanggung perusahaan, tidak memengaruhi take home pay.', 'building-2', 'indigo'],
];
?>
<div x-data="crudModal({
        empty: { id: null, code: '', name: '', type: 'earning', calc_type: 'fixed', default_amount: '0', taxable: true, is_bpjs_base: false, is_active: true, sort_order: '100', description: '', is_system: false },
        items: <?= js_data($items) ?>,
        storeUrl: '<?= esc(url_to('salary-components.store'), 'js') ?>',
        updateUrl: '<?= esc(site_url('salary-components/__ID__'), 'js') ?>',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Master Data',
        'title'    => 'Komponen Gaji',
        'subtitle' => 'Komponen bertanda Sistem dihitung otomatis oleh engine payroll. Nominal per karyawan dapat di-override di halaman detail karyawan.',
        'actions'  => $canManage ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Tambah Komponen</button>' : null,
    ]) ?>

    <div class="space-y-6">
        <?php foreach ($grouped as $type => $components): ?>
            <?php
            [$label, $description, $iconName, $tone] = $typeMeta[$type];
            $rows                                    = [];

            foreach ($components as $component) {
                $flags = [];

                if ($component['taxable']) {
                    $flags[] = $type === 'deduction' ? 'Kurangi bruto PPh' : 'Objek PPh 21';
                }

                if ($component['is_bpjs_base']) {
                    $flags[] = 'Dasar BPJS';
                }

                $actions = '';

                if ($canManage) {
                    $actions = '<div class="flex justify-end gap-1">'
                        . '<button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="edit(' . (int) $component['id'] . ')" aria-label="Ubah ' . esc($component['name'], 'attr') . '">' . icon('pencil', 'size-4') . '</button>';

                    if (! $component['is_system']) {
                        $actions .= '<button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus ' . esc($component['name'], 'attr') . '"'
                            . ' @click="$dispatch(\'confirm\', { title: \'Hapus komponen ' . esc($component['name'], 'js') . '?\', message: \'Komponen yang masih dipakai karyawan tidak bisa dihapus.\', action: \'' . esc(url_to('salary-components.delete', $component['id']), 'js') . '\', confirmLabel: \'Hapus\', tone: \'danger\' })">'
                            . icon('trash-2', 'size-4') . '</button>';
                    }

                    $actions .= '</div>';
                }

                $row = [
                    '<div class="flex items-center gap-2"><p class="font-semibold text-slate-900">' . esc($component['name']) . '</p>'
                        . ($component['is_system'] ? '<span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-slate-600 uppercase">Sistem</span>' : '')
                        . '</div><p class="font-mono text-xs text-slate-500">' . esc($component['code']) . '</p>',
                    esc(SalaryComponentModel::CALC_TYPES[$component['calc_type']] ?? $component['calc_type']),
                    $component['default_amount'] > 0 ? '<span class="font-semibold text-slate-900">' . rupiah($component['default_amount']) . '</span>' : '<span class="text-slate-500">—</span>',
                    $flags === [] ? '<span class="text-slate-500">—</span>' : implode(' ', array_map(static fn (string $f): string => '<span class="inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">' . esc($f) . '</span>', $flags)),
                    $component['usage'] > 0 ? $component['usage'] . ' karyawan' : '<span class="text-slate-500">—</span>',
                    $component['is_active'] ? status_badge('active') : status_badge('inactive'),
                ];

                if ($canManage) {
                    $row[] = $actions;
                }

                $rows[] = $row;
            }
            ?>
            <?= component('data_table', [
                'title'    => $label,
                'subtitle' => $description,
                'toolbar'  => '<span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold ' . tone_classes($tone) . ' ring-1">' . icon($iconName, 'size-3.5') . count($components) . ' komponen</span>',
                'columns'  => array_values(array_filter([
                    ['label' => 'Komponen'],
                    ['label' => 'Cara Hitung'],
                    ['label' => 'Nominal Default', 'align' => 'right'],
                    ['label' => 'Sifat'],
                    ['label' => 'Override', 'align' => 'center'],
                    ['label' => 'Status', 'align' => 'center'],
                    $canManage ? ['label' => 'Aksi', 'align' => 'right', 'mobile' => false] : null,
                ])),
                'rows'     => $rows,
                'empty'    => ['title' => 'Belum ada komponen', 'icon' => 'layers'],
            ]) ?>
        <?php endforeach ?>
    </div>

    <?php if ($canManage): ?>
        <?= component('modal_open', [
            'title'     => 'Komponen Gaji',
            'titleExpr' => "mode === 'create' ? 'Tambah Komponen Gaji' : 'Ubah Komponen Gaji'",
            'action'    => 'action',
            'size'      => 'max-w-2xl',
        ]) ?>
            <div x-show="form.is_system" class="flex gap-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-800 ring-1 ring-amber-200">
                <?= icon('lock', 'mt-0.5 size-4 shrink-0') ?>
                <p>Komponen sistem: tipe, cara hitung, dan sifat pajak dikunci karena dipakai langsung oleh engine payroll.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <?= component('form_input', ['name' => 'code', 'label' => 'Kode', 'required' => true, 'placeholder' => 'TUNJ_KELUARGA', 'help' => 'Huruf besar, angka, garis bawah', 'attrs' => ['id' => 'sc-code', 'x-model' => 'form.code', 'maxlength' => 30, ':readonly' => 'form.is_system']]) ?>
                <div class="sm:col-span-2">
                    <?= component('form_input', ['name' => 'name', 'label' => 'Nama komponen', 'required' => true, 'attrs' => ['id' => 'sc-name', 'x-model' => 'form.name', 'maxlength' => 100]]) ?>
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <?= component('form_select', ['name' => 'type', 'label' => 'Tipe', 'options' => ['earning' => 'Pendapatan', 'deduction' => 'Potongan'], 'attrs' => ['id' => 'sc-type', 'x-model' => 'form.type', ':disabled' => 'form.is_system']]) ?>
                <?= component('form_select', ['name' => 'calc_type', 'label' => 'Cara hitung', 'options' => ['fixed' => 'Tetap per bulan', 'daily' => 'Per hari hadir', 'manual' => 'Input manual'], 'attrs' => ['id' => 'sc-calc', 'x-model' => 'form.calc_type', ':disabled' => 'form.is_system']]) ?>
                <?= component('form_money', ['name' => 'default_amount', 'label' => 'Nominal default', 'model' => 'form.default_amount', 'id' => 'sc-default', 'help' => 'Per bulan / per hari']) ?>
            </div>
            <div class="grid gap-3 sm:grid-cols-3" x-show="!form.is_system">
                <label class="flex items-center gap-2.5 rounded-xl bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                    <input type="hidden" name="taxable" value="0">
                    <input type="checkbox" name="taxable" value="1" x-model="form.taxable" class="size-4 rounded accent-brand-600">
                    <span x-text="form.type === 'deduction' ? 'Kurangi bruto PPh 21' : 'Objek PPh 21'"></span>
                </label>
                <label class="flex items-center gap-2.5 rounded-xl bg-slate-50 p-3 text-sm ring-1 ring-slate-200" x-show="form.type === 'earning'">
                    <input type="hidden" name="is_bpjs_base" value="0">
                    <input type="checkbox" name="is_bpjs_base" value="1" x-model="form.is_bpjs_base" class="size-4 rounded accent-brand-600">
                    Dasar upah BPJS & lembur
                </label>
                <label class="flex items-center gap-2.5 rounded-xl bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="size-4 rounded accent-brand-600">
                    Aktif
                </label>
            </div>
            <div class="grid gap-5 sm:grid-cols-4">
                <?= component('form_input', ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'attrs' => ['id' => 'sc-sort', 'x-model' => 'form.sort_order', 'min' => 0, 'max' => 999]]) ?>
                <div class="sm:col-span-3">
                    <?= component('form_input', ['name' => 'description', 'label' => 'Deskripsi', 'attrs' => ['id' => 'sc-description', 'x-model' => 'form.description', 'maxlength' => 255]]) ?>
                </div>
            </div>
        <?= component('modal_close', ['submitLabel' => 'Simpan Komponen']) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
