<?php
/**
 * Form tambah / ubah karyawan.
 *
 * @var string                     $mode       create | edit
 * @var array<string, mixed>       $employee
 * @var array<int, string>         $departments
 * @var list<array<string, mixed>> $positions
 * @var array<string, string>      $ptkp
 * @var bool                       $hasAccount
 */

use App\Models\EmployeeModel;

$this->extend('layouts/app');
$this->section('content');

$errors = (array) (session('errors') ?? []);
$value  = static fn (string $key, mixed $default = '') => old($key, $employee[$key] ?? $default, false);
$action = $mode === 'create' ? url_to('employees.store') : url_to('employees.update', $employee['id']);

$sections = [
    'pribadi'     => ['Data Pribadi', 'user-round', ['full_name', 'nik', 'npwp', 'gender', 'birth_place', 'birth_date', 'phone', 'address']],
    'kepegawaian' => ['Kepegawaian', 'briefcase-business', ['department_id', 'position_id', 'employment_type', 'join_date']],
    'gaji'        => ['Gaji & Pajak', 'banknote', ['basic_salary', 'position_allowance', 'communication_allowance', 'ptkp_status']],
    'bank'        => ['Rekening Bank', 'landmark', ['bank_name', 'bank_account_no', 'bank_account_name']],
];
$sectionErrors = array_map(static fn (array $s): int => count(array_intersect_key($errors, array_flip($s[2]))), $sections);

$alpine = [
    'departmentId'  => (string) $value('department_id'),
    'positionId'    => (string) $value('position_id'),
    'basicSalary'   => (string) $value('basic_salary'),
    'positionAllow' => (string) $value('position_allowance', 0),
    'commAllow'     => (string) $value('communication_allowance', 0),
    'positions'     => $positions,
    'fullName'      => (string) $value('full_name'),
    'accountName'   => (string) $value('bank_account_name'),
];
?>
<form method="post" action="<?= esc($action, 'attr') ?>" novalidate
      x-data="employeeForm(<?= js_data($alpine) ?>)" @submit="submitting = true">
    <?= csrf_field() ?>

    <?= component('page_header', [
        'eyebrow'  => $mode === 'create' ? 'Karyawan baru · NIP ' . $employee['nip'] : $employee['nip'],
        'title'    => $mode === 'create' ? 'Tambah Karyawan' : 'Ubah Data ' . $employee['full_name'],
        'subtitle' => 'Kolom bertanda * wajib diisi. Gaji pokok & tunjangan terisi otomatis dari standar jabatan dan tetap bisa disesuaikan.',
        'actions'  => '<a href="' . esc($mode === 'create' ? url_to('employees.index') : url_to('employees.show', $employee['id']), 'attr') . '" class="btn btn-secondary">Batal</a>'
            . '<button type="submit" class="btn btn-primary" :disabled="submitting">' . icon('save', 'size-4') . ' Simpan</button>',
    ]) ?>

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        <!-- Navigasi section -->
        <nav class="hidden lg:block" aria-label="Bagian formulir">
            <ul class="sticky top-24 space-y-1">
                <?php foreach ($sections as $anchor => [$label, $iconName]): ?>
                    <li>
                        <a href="#<?= $anchor ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-white hover:text-slate-900 hover:shadow-card">
                            <?= icon($iconName, 'size-4 text-slate-500') ?> <?= esc($label) ?>
                            <?php if ($sectionErrors[$anchor] > 0): ?>
                                <span class="ml-auto rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700"><?= $sectionErrors[$anchor] ?></span>
                            <?php endif ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </nav>

        <div class="space-y-6">
            <!-- Data pribadi -->
            <section id="pribadi" class="card scroll-mt-24" aria-labelledby="section-pribadi">
                <header class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><?= icon('user-round', 'size-4') ?></span>
                    <div>
                        <h2 id="section-pribadi" class="text-base font-semibold text-slate-900">Data Pribadi</h2>
                        <p class="text-xs text-slate-500">Sesuai KTP. NIK juga berfungsi sebagai NPWP orang pribadi.</p>
                    </div>
                </header>
                <div class="grid gap-5 p-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <?= component('form_input', ['name' => 'full_name', 'label' => 'Nama lengkap', 'value' => $employee['full_name'] ?? '', 'required' => true, 'attrs' => ['x-model' => 'fullName', 'autocomplete' => 'off', 'maxlength' => 150]]) ?>
                    </div>
                    <?= component('form_input', ['name' => 'nik', 'label' => 'NIK', 'value' => $employee['nik'] ?? '', 'required' => true, 'icon' => 'id-card', 'help' => '16 digit sesuai KTP', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 16, 'autocomplete' => 'off']]) ?>
                    <?= component('form_input', ['name' => 'npwp', 'label' => 'NPWP', 'value' => $employee['npwp'] ?? '', 'help' => 'Kosongkan bila sama dengan NIK', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 16, 'autocomplete' => 'off']]) ?>
                    <fieldset>
                        <legend class="form-label">Jenis kelamin <span class="text-rose-500" aria-hidden="true">*</span></legend>
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach (EmployeeModel::GENDERS as $code => $label): ?>
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm ring-1 ring-slate-300 ring-inset has-[:checked]:bg-brand-50 has-[:checked]:font-semibold has-[:checked]:text-brand-700 has-[:checked]:ring-2 has-[:checked]:ring-brand-500">
                                    <input type="radio" name="gender" value="<?= $code ?>" class="accent-brand-600" <?= $value('gender', 'L') === $code ? 'checked' : '' ?>> <?= esc($label) ?>
                                </label>
                            <?php endforeach ?>
                        </div>
                        <?php if (isset($errors['gender'])): ?><p class="mt-1.5 text-xs font-medium text-rose-600"><?= esc($errors['gender']) ?></p><?php endif ?>
                    </fieldset>
                    <?= component('form_input', ['name' => 'phone', 'label' => 'No. HP', 'value' => $employee['phone'] ?? '', 'icon' => 'phone', 'placeholder' => '0812…', 'attrs' => ['inputmode' => 'tel', 'maxlength' => 20]]) ?>
                    <?= component('form_input', ['name' => 'birth_place', 'label' => 'Tempat lahir', 'value' => $employee['birth_place'] ?? '', 'attrs' => ['maxlength' => 100]]) ?>
                    <?= component('form_input', ['name' => 'birth_date', 'label' => 'Tanggal lahir', 'type' => 'date', 'value' => $employee['birth_date'] ?? '', 'attrs' => ['max' => date('Y-m-d', strtotime('-15 years'))]]) ?>
                    <div class="md:col-span-2">
                        <label for="field-address" class="form-label">Alamat domisili</label>
                        <textarea id="field-address" name="address" rows="2" maxlength="500" class="form-control <?= isset($errors['address']) ? 'ring-rose-400' : '' ?>"><?= esc((string) $value('address')) ?></textarea>
                    </div>
                </div>
            </section>

            <!-- Kepegawaian -->
            <section id="kepegawaian" class="card scroll-mt-24" aria-labelledby="section-kepegawaian">
                <header class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><?= icon('briefcase-business', 'size-4') ?></span>
                    <div>
                        <h2 id="section-kepegawaian" class="text-base font-semibold text-slate-900">Kepegawaian</h2>
                        <p class="text-xs text-slate-500">Jabatan menentukan gaji pokok & tunjangan standar serta hak lembur.</p>
                    </div>
                </header>
                <div class="grid gap-5 p-6 md:grid-cols-2">
                    <?= component('form_select', ['name' => 'department_id', 'label' => 'Departemen', 'options' => $departments, 'value' => $employee['department_id'] ?? '', 'placeholder' => 'Pilih departemen', 'required' => true, 'attrs' => ['x-model' => 'departmentId', '@change' => 'positionId = ""']]) ?>
                    <div>
                        <label for="field-position_id" class="form-label">Jabatan <span class="text-rose-500" aria-hidden="true">*</span></label>
                        <div class="relative">
                            <select id="field-position_id" name="position_id" x-model="positionId" @change="applyPosition()" class="form-control appearance-none pr-10 <?= isset($errors['position_id']) ? 'ring-rose-400' : '' ?>" :disabled="!departmentId">
                                <option value="" x-text="departmentId ? 'Pilih jabatan' : 'Pilih departemen dahulu'">Pilih jabatan</option>
                                <template x-for="position in availablePositions" :key="position.id">
                                    <option :value="String(position.id)" x-text="position.name + ' · Grade ' + position.grade" :selected="String(position.id) === positionId"></option>
                                </template>
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
                        </div>
                        <?php if (isset($errors['position_id'])): ?><p class="mt-1.5 text-xs font-medium text-rose-600"><?= esc($errors['position_id']) ?></p><?php endif ?>
                    </div>
                    <?= component('form_select', ['name' => 'employment_type', 'label' => 'Status kerja', 'options' => EmployeeModel::EMPLOYMENT_TYPES, 'value' => $employee['employment_type'] ?? 'probation', 'required' => true]) ?>
                    <?= component('form_input', ['name' => 'join_date', 'label' => 'Tanggal masuk', 'type' => 'date', 'value' => $employee['join_date'] ?? '', 'required' => true, 'icon' => 'calendar']) ?>
                </div>
            </section>

            <!-- Gaji & pajak -->
            <section id="gaji" class="card scroll-mt-24" aria-labelledby="section-gaji">
                <header class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><?= icon('banknote', 'size-4') ?></span>
                    <div>
                        <h2 id="section-gaji" class="text-base font-semibold text-slate-900">Gaji & Pajak</h2>
                        <p class="text-xs text-slate-500">Tunjangan transport & uang makan dihitung per hari hadir dari pengaturan komponen gaji.</p>
                    </div>
                </header>
                <div class="grid gap-5 p-6 md:grid-cols-3">
                    <?= component('form_money', ['name' => 'basic_salary', 'label' => 'Gaji pokok', 'value' => $employee['basic_salary'] ?? '', 'required' => true, 'model' => 'basicSalary']) ?>
                    <?= component('form_money', ['name' => 'position_allowance', 'label' => 'Tunjangan jabatan', 'value' => $employee['position_allowance'] ?? 0, 'model' => 'positionAllow']) ?>
                    <?= component('form_money', ['name' => 'communication_allowance', 'label' => 'Tunjangan komunikasi', 'value' => $employee['communication_allowance'] ?? 0, 'model' => 'commAllow']) ?>
                    <div class="md:col-span-3">
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-linear-to-r from-brand-50 to-violet-50 px-4 py-3 ring-1 ring-brand-100">
                            <p class="text-sm text-slate-700">Upah tetap sebulan <span class="text-xs text-slate-500">(dasar BPJS & lembur)</span></p>
                            <p class="text-lg font-bold text-slate-900 tabular-nums" x-text="'Rp ' + PayrollKu.formatNumber(fixedWage)"></p>
                        </div>
                        <p x-show="belowMinimum" x-cloak class="mt-2 flex items-center gap-1.5 text-xs font-medium text-amber-700">
                            <?= icon('triangle-alert', 'size-3.5') ?> Gaji pokok di bawah UMP acuan <?= rupiah(service('appSettings')->int('payroll.minimum_wage')) ?>.
                        </p>
                    </div>
                    <?= component('form_select', ['name' => 'ptkp_status', 'label' => 'Status PTKP', 'options' => $ptkp, 'value' => $employee['ptkp_status'] ?? 'TK/0', 'required' => true, 'help' => 'Menentukan kategori TER PPh 21']) ?>
                    <div class="space-y-2 md:col-span-2">
                        <p class="form-label">Kepesertaan BPJS</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <?php foreach (['bpjs_kes_enrolled' => 'BPJS Kesehatan', 'bpjs_tk_enrolled' => 'BPJS Ketenagakerjaan'] as $field => $label): ?>
                                <label class="flex items-center gap-2.5 rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm ring-1 ring-slate-200">
                                    <input type="hidden" name="<?= $field ?>" value="0">
                                    <input type="checkbox" name="<?= $field ?>" value="1" class="size-4 rounded accent-brand-600" <?= old($field, (int) ($employee[$field] ?? 1), false) ? 'checked' : '' ?>>
                                    <?= esc($label) ?>
                                </label>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Rekening -->
            <section id="bank" class="card scroll-mt-24" aria-labelledby="section-bank">
                <header class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><?= icon('landmark', 'size-4') ?></span>
                    <div>
                        <h2 id="section-bank" class="text-base font-semibold text-slate-900">Rekening Bank</h2>
                        <p class="text-xs text-slate-500">Dipakai untuk file transfer gaji.</p>
                    </div>
                </header>
                <div class="grid gap-5 p-6 md:grid-cols-3">
                    <?= component('form_select', ['name' => 'bank_name', 'label' => 'Bank', 'options' => array_combine(EmployeeModel::BANKS, EmployeeModel::BANKS), 'value' => $employee['bank_name'] ?? '', 'placeholder' => 'Pilih bank']) ?>
                    <?= component('form_input', ['name' => 'bank_account_no', 'label' => 'No. rekening', 'value' => $employee['bank_account_no'] ?? '', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 30, 'autocomplete' => 'off']]) ?>
                    <div>
                        <?= component('form_input', ['name' => 'bank_account_name', 'label' => 'Nama pemilik rekening', 'value' => $employee['bank_account_name'] ?? '', 'attrs' => ['x-model' => 'accountName', 'maxlength' => 150, 'autocapitalize' => 'characters']]) ?>
                        <button type="button" class="mt-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700" @click="accountName = fullName.toUpperCase()">Samakan dengan nama karyawan</button>
                    </div>
                </div>
            </section>

            <?php if ($mode === 'create'): ?>
                <section class="card p-6" aria-label="Akun login">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="create_account" value="1" class="mt-0.5 size-4 rounded accent-brand-600" <?= old('create_account', '1', false) ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Buatkan akun login karyawan</span>
                            <span class="block text-sm text-slate-500">Username dibuat dari nama depan dan belakang (mis. <span class="font-mono" x-text="usernamePreview || 'nama.belakang'"></span>). Password awal wajib diganti saat login pertama.</span>
                        </span>
                    </label>
                </section>
            <?php elseif (! $hasAccount): ?>
                <p class="text-sm text-slate-500">Karyawan ini belum memiliki akun login. Akun dapat dibuat dari menu Pengguna.</p>
            <?php endif ?>

            <div class="flex justify-end gap-3">
                <a href="<?= esc($mode === 'create' ? url_to('employees.index') : url_to('employees.show', $employee['id']), 'attr') ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary" :disabled="submitting"><?= icon('save', 'size-4') ?> <?= $mode === 'create' ? 'Simpan Karyawan' : 'Simpan Perubahan' ?></button>
            </div>
        </div>
    </div>
</form>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    function employeeForm(state) {
        const minimumWage = <?= (int) service('appSettings')->int('payroll.minimum_wage') ?>;

        return {
            ...state,
            submitting: false,

            get availablePositions() {
                return this.positions.filter((p) => String(p.department_id) === String(this.departmentId));
            },

            get fixedWage() {
                return ['basicSalary', 'positionAllow', 'commAllow']
                    .reduce((sum, key) => sum + Number(String(this[key] ?? '').replace(/\D/g, '') || 0), 0);
            },

            get belowMinimum() {
                const basic = Number(String(this.basicSalary ?? '').replace(/\D/g, '') || 0);
                return basic > 0 && basic < minimumWage;
            },

            get usernamePreview() {
                const parts = this.fullName.toLowerCase().replace(/[^a-z\s]/g, '').trim().split(/\s+/).filter(Boolean);
                return parts.length > 1 ? parts[0] + '.' + parts[parts.length - 1] : (parts[0] ?? '');
            },

            applyPosition() {
                const position = this.positions.find((p) => String(p.id) === String(this.positionId));
                if (!position) return;
                this.basicSalary = String(position.base_salary);
                this.positionAllow = String(position.position_allowance);
                this.commAllow = String(position.communication_allowance);
                window.PayrollKu.toast('info', 'Gaji pokok & tunjangan diisi dari standar jabatan ' + position.name + '.');
            },
        };
    }
</script>
<?php $this->endSection() ?>
