<?php
/**
 * Modal form pengajuan pinjaman (dipakai di daftar & detail; scope Alpine: crudModal).
 *
 * @var array<int, array{label: string, salary: int}> $employees
 * @var string                                         $firstPeriod YYYY-MM
 */

use App\Models\LoanModel;

$salaries = array_map(static fn (array $e): int => $e['salary'], $employees);
?>
<?= component('modal_open', [
    'title'     => 'Pinjaman',
    'titleExpr' => "mode === 'create' ? 'Ajukan Pinjaman' : 'Ubah Pengajuan Pinjaman'",
    'subtitle'  => 'Cicilan dipotong otomatis dari gaji mulai bulan yang dipilih setelah disetujui Finance.',
    'action'    => 'action',
    'size'      => 'max-w-2xl',
]) ?>
    <?= component('form_select', [
        'name'        => 'employee_id',
        'label'       => 'Karyawan',
        'options'     => array_map(static fn (array $e): string => $e['label'], $employees),
        'placeholder' => '— Pilih karyawan —',
        'required'    => true,
        'attrs'       => ['id' => 'loan-employee', 'x-model' => 'form.employee_id'],
    ]) ?>
    <div class="grid gap-5 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <?= component('form_money', ['name' => 'amount', 'label' => 'Nominal pinjaman', 'required' => true, 'model' => 'form.amount', 'id' => 'loan-amount', 'help' => 'Minimal Rp 100.000']) ?>
        </div>
        <?= component('form_input', ['name' => 'tenor_months', 'label' => 'Tenor', 'type' => 'number', 'suffix' => 'bulan', 'required' => true, 'attrs' => ['id' => 'loan-tenor', 'x-model' => 'form.tenor_months', 'min' => 1, 'max' => 36]]) ?>
    </div>
    <?= component('form_input', ['name' => 'start_period', 'label' => 'Mulai dipotong', 'type' => 'month', 'required' => true, 'help' => 'Paling cepat ' . periode_id((int) substr($firstPeriod, 0, 4), (int) substr($firstPeriod, 5, 2)), 'attrs' => ['id' => 'loan-start', 'x-model' => 'form.start_period', 'min' => $firstPeriod]]) ?>

    <div x-data="{
            salaries: <?= js_data((object) $salaries) ?>,
            get amount() { return Number(String(form.amount || '').replace(/\D/g, '')) || 0 },
            get tenor() { return Math.max(1, Math.min(36, Number(form.tenor_months) || 1)) },
            get installment() { return Math.ceil(this.amount / this.tenor) },
            get salary() { return this.salaries[form.employee_id] ?? 0 },
            get ratio() { return this.salary > 0 ? this.installment / this.salary * 100 : 0 },
            get end() {
                if (!/^\d{4}-\d{2}$/.test(form.start_period || '')) return '—';
                const [y, m] = form.start_period.split('-').map(Number);
                return new Date(y, m - 1 + this.tenor - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            },
         }" class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">Simulasi</p>
        <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs text-slate-500">Cicilan per bulan</dt>
                <dd class="text-lg font-bold text-slate-900 tabular-nums" x-text="'Rp ' + installment.toLocaleString('id-ID')"></dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Terhadap gaji pokok</dt>
                <dd class="text-lg font-bold tabular-nums" :class="ratio > <?= LoanModel::MAX_INSTALLMENT_RATIO * 100 ?> ? 'text-rose-600' : (ratio > 30 ? 'text-amber-600' : 'text-emerald-600')"
                    x-text="salary ? ratio.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '—'"></dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Lunas</dt>
                <dd class="text-lg font-bold text-slate-900" x-text="end"></dd>
            </div>
        </dl>
        <p class="mt-3 text-xs" :class="ratio > <?= LoanModel::MAX_INSTALLMENT_RATIO * 100 ?> ? 'font-semibold text-rose-600' : 'text-slate-500'"
           x-text="ratio > <?= LoanModel::MAX_INSTALLMENT_RATIO * 100 ?> ? 'Cicilan melebihi batas 50% gaji pokok — perpanjang tenor atau kurangi nominal.' : (ratio > 30 ? 'Cicilan di atas 30% gaji pokok. Pastikan take home pay karyawan tetap memadai.' : 'Cicilan dibulatkan ke atas; cicilan terakhir menyesuaikan sisa pinjaman.')"></p>
    </div>

    <?= component('form_input', ['name' => 'purpose', 'label' => 'Keperluan', 'required' => true, 'placeholder' => 'mis. Renovasi rumah', 'attrs' => ['id' => 'loan-purpose', 'x-model' => 'form.purpose', 'maxlength' => 255]]) ?>
    <div>
        <label for="loan-notes" class="form-label">Catatan</label>
        <textarea id="loan-notes" name="notes" rows="2" maxlength="255" class="form-control" x-model="form.notes" placeholder="Catatan internal HR (opsional)"></textarea>
    </div>
<?= component('modal_close', ['submitLabel' => 'Simpan Pengajuan']) ?>
