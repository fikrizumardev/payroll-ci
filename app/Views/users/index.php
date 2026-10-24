<?php
/**
 * @var list<array<string, mixed>>                $users
 * @var CodeIgniter\Pager\Pager                   $pager
 * @var array{q: string, role: string, status: string} $filters
 * @var array<string, string>                     $statuses
 * @var array<string, int>                        $roleCounts
 * @var array<int, array<string, mixed>>          $employees  pilihan karyawan untuk modal
 * @var int                                       $currentId
 * @var array{username: string, name: string, password: string, reason: string}|null $credential
 */

use App\Models\UserModel;

$this->extend('layouts/app');
$this->section('content');

$query = static fn (array $overrides): string => url_to('users.index') . '?' . http_build_query(array_filter($overrides + $filters, static fn ($v) => $v !== '' && $v !== null));

$items = [];

foreach ($users as $user) {
    $items[$user['id']] = [
        'id'          => $user['id'],
        'username'    => $user['username'],
        'name'        => $user['name'],
        'role'        => $user['role'],
        'employee_id' => (string) ($user['employee_id'] ?? ''),
    ];
}

$total   = $pager->getTotal();
$page    = $pager->getCurrentPage();
$perPage = $pager->getPerPage();
$now     = time();

$confirm = static fn (array $options): string => '$dispatch(\'confirm\', ' . esc(json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') . ')';
$iconBtn = 'rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none';

$rows       = [];
$rowClasses = [];

foreach ($users as $user) {
    $self     = (int) $user['id'] === $currentId;
    $locked   = $user['locked_until'] !== null && strtotime($user['locked_until']) > $now;
    $username = $user['username'];

    $badges = status_badge($user['is_active'] ? 'active' : 'inactive');

    if ($locked) {
        $badges .= ' <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20 ring-inset" title="Terkunci hingga ' . esc(tanggal_id($user['locked_until'], 'datetime'), 'attr') . '">' . icon('lock', 'size-3') . ' Terkunci</span>';
    }

    if ($user['must_change_password']) {
        $badges .= ' <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 ring-inset">' . icon('key-round', 'size-3') . ' Ganti password</span>';
    }

    $employee = $user['employee_id'] === null
        ? '<span class="text-slate-400">—</span>'
        : '<a href="' . url_to('employees.show', $user['employee_id']) . '" class="group block"><span class="block font-medium text-slate-800 group-hover:text-brand-700">' . esc((string) $user['employee_name']) . '</span>'
            . '<span class="block text-xs text-slate-500">' . esc((string) $user['employee_nip']) . ' · ' . esc((string) $user['position_name']) . '</span>'
            . ($user['employee_status'] !== 'active' ? '<span class="mt-0.5 inline-block">' . status_badge((string) $user['employee_status']) . '</span>' : '') . '</a>';

    $lastLogin = $user['last_login_at'] === null
        ? '<span class="text-slate-400">Belum pernah</span>'
        : '<span class="block whitespace-nowrap text-slate-800">' . esc(tanggal_id($user['last_login_at'], 'datetime')) . '</span><span class="block text-xs text-slate-500">' . esc((string) $user['last_login_ip']) . '</span>';

    $actions = '<button type="button" class="' . $iconBtn . '" @click="edit(' . (int) $user['id'] . ')" aria-label="Ubah ' . esc($username, 'attr') . '" title="Ubah">' . icon('pencil', 'size-4') . '</button>';

    if (! $self) {
        $actions .= '<button type="button" class="' . $iconBtn . '" title="Reset password" aria-label="Reset password ' . esc($username, 'attr') . '" @click="' . $confirm([
            'title'        => "Reset password {$username}?",
            'message'      => 'Password sementara baru dibuat dan ditampilkan sekali. Pengguna wajib menggantinya saat login, dan semua sesi "Ingat saya" dicabut.',
            'action'       => url_to('users.reset-password', $user['id']),
            'confirmLabel' => 'Reset Password',
        ]) . '">' . icon('key-round', 'size-4') . '</button>';
    }

    if ($locked) {
        $actions .= '<button type="button" class="' . $iconBtn . '" title="Buka kunci" aria-label="Buka kunci ' . esc($username, 'attr') . '" @click="' . $confirm([
            'title'        => "Buka kunci akun {$username}?",
            'message'      => 'Akun terkunci otomatis karena terlalu banyak percobaan login gagal. Pastikan percobaan tersebut memang dilakukan pemilik akun.',
            'action'       => url_to('users.unlock', $user['id']),
            'confirmLabel' => 'Buka Kunci',
        ]) . '">' . icon('lock-open', 'size-4') . '</button>';
    }

    if (! $self) {
        $actions .= $user['is_active']
            ? '<button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-rose-50 hover:text-rose-600 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none" title="Nonaktifkan" aria-label="Nonaktifkan ' . esc($username, 'attr') . '" @click="' . $confirm([
                'title'        => "Nonaktifkan akun {$username}?",
                'message'      => 'Pengguna tidak bisa login dan sesi yang sedang berjalan langsung berakhir. Riwayat aktivitas tetap tersimpan.',
                'action'       => url_to('users.deactivate', $user['id']),
                'confirmLabel' => 'Nonaktifkan',
                'tone'         => 'danger',
            ]) . '">' . icon('user-x', 'size-4') . '</button>'
            : '<button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none" title="Aktifkan" aria-label="Aktifkan ' . esc($username, 'attr') . '" @click="' . $confirm([
                'title'        => "Aktifkan kembali akun {$username}?",
                'message'      => 'Pengguna dapat login lagi dengan password terakhirnya. Reset password bila password lama tidak diketahui.',
                'action'       => url_to('users.activate', $user['id']),
                'confirmLabel' => 'Aktifkan',
            ]) . '">' . icon('user-check', 'size-4') . '</button>';
    }

    $actions .= '<a href="' . esc(url_to('audit-logs.index') . '?user_id=' . (int) $user['id'], 'attr') . '" class="' . $iconBtn . '" title="Lihat aktivitas" aria-label="Aktivitas ' . esc($username, 'attr') . '">' . icon('scroll-text', 'size-4') . '</a>';

    $rows[] = [
        '<div class="flex items-center gap-3">' . avatar($user['name']) . '<div class="min-w-0">'
            . '<p class="truncate font-semibold text-slate-900">' . esc($user['name']) . ($self ? ' <span class="ml-1 rounded-md bg-brand-50 px-1.5 py-0.5 text-[11px] font-semibold text-brand-700">Anda</span>' : '') . '</p>'
            . '<p class="truncate font-mono text-xs text-slate-500">@' . esc($username) . '</p></div></div>',
        '<span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold whitespace-nowrap ' . match ($user['role']) {
            UserModel::ROLE_SUPERADMIN => 'bg-ink-950 text-white',
            UserModel::ROLE_HR         => 'bg-brand-50 text-brand-700',
            UserModel::ROLE_FINANCE    => 'bg-emerald-50 text-emerald-700',
            default                    => 'bg-slate-100 text-slate-700',
        } . '">' . esc(role_label($user['role'])) . '</span>',
        $employee,
        '<div class="flex flex-wrap gap-1">' . $badges . '</div>',
        $lastLogin,
        '<div class="flex justify-end gap-0.5">' . $actions . '</div>',
    ];
    $rowClasses[] = $user['is_active'] ? '' : 'bg-slate-50/70';
}

$roleTabs  = ['' => ['Semua', array_sum($roleCounts)]];
$hasFilter = $filters['q'] !== '' || $filters['status'] !== '';

foreach (UserModel::ROLES as $role => $label) {
    $roleTabs[$role] = [$label, $roleCounts[$role] ?? 0];
}
?>
<div x-data="{
        ...crudModal({
            empty: { id: null, username: '', name: '', role: 'employee', employee_id: '' },
            items: <?= js_data($items) ?>,
            storeUrl: '<?= esc(url_to('users.store'), 'js') ?>',
            updateUrl: '<?= esc(site_url('users/__ID__'), 'js') ?>',
            reopen: <?= js_data(session('open_modal')) ?>,
            old: <?= js_data(old_input()) ?>,
        }),
        employees: <?= js_data($employees) ?>,
        currentId: <?= $currentId ?>,
        employeeTaken(id) {
            const owner = this.employees[id]?.user_id;
            return owner !== null && owner !== undefined && owner !== Number(this.form.id);
        },
        pickEmployee() {
            const employee = this.employees[this.form.employee_id];
            if (!employee || this.mode !== 'create') return;
            if (this.form.name.trim() === '') this.form.name = employee.name;
            if (this.form.username.trim() === '') this.form.username = employee.username;
        },
     }">

    <?= component('page_header', [
        'eyebrow'  => 'Sistem',
        'title'    => 'Pengguna',
        'subtitle' => 'Akun login berbasis username. Password sementara dibuat otomatis dan wajib diganti saat login pertama.',
        'actions'  => '<button type="button" class="btn btn-primary" @click="create()">' . icon('user-plus', 'size-4') . ' Tambah Pengguna</button>',
    ]) ?>

    <?php if (is_array($credential)): ?>
        <section class="mb-6 overflow-hidden rounded-2xl bg-linear-to-br from-brand-600 via-violet-600 to-fuchsia-600 p-px shadow-glow" aria-labelledby="credential-title"
                 x-data="{ copied: false, copy() { navigator.clipboard?.writeText(this.$refs.password.textContent.trim()).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }">
            <div class="flex flex-col gap-4 rounded-[15px] bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><?= icon('key-round', 'size-5') ?></span>
                    <div>
                        <h2 id="credential-title" class="font-semibold text-slate-900"><?= $credential['reason'] === 'reset' ? 'Password baru' : 'Akun baru' ?> untuk <?= esc($credential['name']) ?></h2>
                        <p class="mt-0.5 text-sm text-slate-500">Sampaikan secara langsung/pribadi. Password ini <strong class="font-semibold text-slate-700">hanya ditampilkan sekali</strong> dan wajib diganti saat login.</p>
                    </div>
                </div>
                <dl class="flex flex-wrap items-center gap-3">
                    <div class="rounded-xl bg-slate-50 px-4 py-2 ring-1 ring-slate-200">
                        <dt class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Username</dt>
                        <dd class="font-mono text-sm font-semibold text-slate-900"><?= esc($credential['username']) ?></dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-2 ring-1 ring-slate-200">
                        <dt class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Password sementara</dt>
                        <dd class="font-mono text-sm font-semibold tracking-wide text-slate-900" x-ref="password"><?= esc($credential['password']) ?></dd>
                    </div>
                    <button type="button" class="btn btn-secondary" @click="copy()">
                        <span x-show="!copied" class="inline-flex items-center gap-2"><?= icon('copy', 'size-4') ?> Salin</span>
                        <span x-show="copied" x-cloak class="inline-flex items-center gap-2 text-emerald-700"><?= icon('check', 'size-4') ?> Tersalin</span>
                    </button>
                </dl>
            </div>
        </section>
    <?php endif ?>

    <div class="card mb-6 overflow-hidden">
        <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-100 px-3 pt-3" role="tablist" aria-label="Peran">
            <?php foreach ($roleTabs as $role => [$label, $count]): ?>
                <a href="<?= esc($query(['role' => $role, 'page' => null]), 'attr') ?>" role="tab" aria-selected="<?= $filters['role'] === $role ? 'true' : 'false' ?>"
                   class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 pb-3 text-sm font-semibold transition <?= $filters['role'] === $role ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <?= esc($label) ?>
                    <span class="rounded-full px-2 py-0.5 text-xs <?= $filters['role'] === $role ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' ?>"><?= $count ?></span>
                </a>
            <?php endforeach ?>
        </div>

        <form method="get" action="<?= url_to('users.index') ?>" class="grid gap-3 p-4 sm:grid-cols-[1fr_14rem_auto]" role="search">
            <input type="hidden" name="role" value="<?= esc($filters['role'], 'attr') ?>">
            <div class="relative">
                <label for="filter-q" class="sr-only">Cari pengguna</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"><?= icon('search', 'size-4') ?></span>
                <input id="filter-q" name="q" type="search" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Cari username, nama, atau NIP…" class="form-control pl-10">
            </div>
            <div class="relative">
                <label for="filter-status" class="sr-only">Status akun</label>
                <select id="filter-status" name="status" class="form-control appearance-none pr-10" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= esc($value, 'attr') ?>" <?= $value === $filters['status'] ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-secondary flex-1 sm:flex-none"><?= icon('filter', 'size-4') ?> Terapkan</button>
                <?php if ($hasFilter): ?>
                    <a href="<?= esc($query(['q' => null, 'status' => null, 'page' => null]), 'attr') ?>" class="btn btn-ghost" aria-label="Reset filter"><?= icon('x', 'size-4') ?></a>
                <?php endif ?>
            </div>
        </form>
    </div>

    <?= component('data_table', [
        'title'      => 'Daftar Pengguna',
        'subtitle'   => $total === 0 ? 'Tidak ada data' : sprintf('Menampilkan %d–%d dari %d akun', ($page - 1) * $perPage + 1, min($page * $perPage, $total), $total),
        'columns'    => [
            ['label' => 'Pengguna'],
            ['label' => 'Peran'],
            ['label' => 'Karyawan Terkait'],
            ['label' => 'Status'],
            ['label' => 'Login Terakhir'],
            ['label' => 'Aksi', 'align' => 'right'],
        ],
        'rows'       => $rows,
        'rowClasses' => $rowClasses,
        'empty'      => ['title' => 'Tidak ada pengguna yang cocok', 'description' => 'Coba ubah kata kunci atau filter.', 'icon' => 'user-cog'],
        'footer'     => $pager->getPageCount() > 1 ? $pager->links('default', 'tailwind') : null,
    ]) ?>

    <?= component('modal_open', [
        'title'     => 'Pengguna',
        'titleExpr' => "mode === 'create' ? 'Tambah Pengguna' : 'Ubah Pengguna'",
        'subtitle'  => 'Username dipakai untuk login: huruf kecil, angka, titik, atau underscore (4–30 karakter).',
        'action'    => 'action',
    ]) ?>
        <?php
        $employeeError = (session('errors') ?? [])['employee_id'] ?? null;
        ?>
        <div>
            <label for="user-employee" class="form-label">Karyawan terkait <span class="font-normal text-slate-500" x-text="form.role === 'employee' ? '(wajib)' : '(opsional)'"></span></label>
            <div class="relative">
                <select id="user-employee" name="employee_id" class="form-control appearance-none pr-10 <?= $employeeError ? 'ring-rose-400' : '' ?>" x-model="form.employee_id" @change="pickEmployee()" <?= $employeeError ? 'aria-invalid="true"' : '' ?>>
                    <option value="">— Tidak terhubung ke karyawan —</option>
                    <template x-for="[id, employee] in Object.entries(employees)" :key="id">
                        <option :value="id" x-text="employee.label + (employeeTaken(id) ? ' — sudah punya akun' : '')" :disabled="employeeTaken(id)" :selected="String(form.employee_id) === id"></option>
                    </template>
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
            </div>
            <?php if ($employeeError): ?>
                <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600"><?= icon('circle-alert', 'size-3.5') ?> <?= esc($employeeError) ?></p>
            <?php else: ?>
                <p class="mt-1.5 text-xs text-slate-500">Menghubungkan akun ke karyawan membuka menu Slip Gaji Saya & dashboard pribadi.</p>
            <?php endif ?>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <?= component('form_input', ['name' => 'username', 'label' => 'Username', 'required' => true, 'icon' => 'at-sign', 'placeholder' => 'andi.pratama', 'attrs' => ['id' => 'user-username', 'x-model' => 'form.username', 'maxlength' => 30, 'autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
            <?= component('form_select', ['name' => 'role', 'label' => 'Peran', 'required' => true, 'options' => UserModel::ROLES, 'attrs' => ['id' => 'user-role', 'x-model' => 'form.role', ':disabled' => 'mode === \'edit\' && Number(form.id) === currentId']]) ?>
        </div>
        <template x-if="mode === 'edit' && Number(form.id) === currentId">
            <input type="hidden" name="role" :value="form.role">
        </template>
        <?= component('form_input', ['name' => 'name', 'label' => 'Nama tampilan', 'required' => true, 'placeholder' => 'Nama lengkap', 'attrs' => ['id' => 'user-name', 'x-model' => 'form.name', 'maxlength' => 150]]) ?>
        <div x-show="mode === 'create'" class="flex gap-3 rounded-xl bg-brand-50 p-3.5 text-sm text-brand-900 ring-1 ring-brand-100">
            <?= icon('shield-check', 'mt-0.5 size-4 shrink-0 text-brand-600') ?>
            <p>Password sementara dibuat otomatis setelah disimpan dan ditampilkan sekali. Pengguna wajib menggantinya saat login pertama.</p>
        </div>
        <p x-show="mode === 'edit' && Number(form.id) === currentId" class="text-xs text-slate-500">Peran akun Anda sendiri tidak dapat diubah.</p>
    <?= component('modal_close', ['submitLabel' => 'Simpan Pengguna']) ?>
</div>
<?php $this->endSection() ?>
