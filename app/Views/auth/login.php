<?php
/**
 * Halaman login — username + password (tanpa email).
 *
 * @var list<array{username: string, password: string, role: string}> $demoAccounts
 * @var string|null $errorMessage
 * @var string|null $oldUsername
 */
$this->extend('layouts/auth');
$this->section('content');

$errorMessage ??= session('error');
$errors       = (array) (session('errors') ?? []);
$username     = $oldUsername ?? session('old_username');
$companyName  = service('appSettings')->get('company.name', 'PayrollKu');
?>
<div class="flex min-h-full">
    <!-- Panel brand (desktop) -->
    <aside class="relative hidden overflow-hidden bg-ink-950 text-white lg:flex lg:w-[52%] lg:flex-col lg:justify-between lg:p-12 xl:p-16">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -left-40 -top-40 size-[34rem] rounded-full bg-brand-600/50 blur-3xl"></div>
            <div class="absolute -right-40 top-1/4 size-[30rem] rounded-full bg-violet-600/40 blur-3xl"></div>
            <div class="absolute -bottom-48 left-1/4 size-[32rem] rounded-full bg-fuchsia-600/30 blur-3xl"></div>
            <svg class="absolute inset-0 size-full opacity-[0.06]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="login-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M40 0H0V40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#login-grid)"/>
            </svg>
        </div>

        <div class="relative flex items-center gap-3">
            <span class="flex size-11 items-center justify-center rounded-xl bg-linear-to-br from-brand-500 to-violet-600 shadow-glow ring-1 ring-white/20">
                <?= icon('wallet-cards', 'size-6 text-white') ?>
            </span>
            <div>
                <p class="text-lg font-bold tracking-tight">PayrollKu</p>
                <p class="text-xs text-slate-400"><?= esc($companyName) ?></p>
            </div>
        </div>

        <div class="relative max-w-xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-brand-100 backdrop-blur">
                <span class="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgb(52_211_153/0.25)]"></span>
                Sistem Payroll Terintegrasi
            </span>
            <h1 class="mt-6 text-4xl leading-[1.1] font-extrabold tracking-tight xl:text-5xl">
                Penggajian akurat,<br><span class="text-gradient">tepat waktu</span>, tanpa ribet.
            </h1>
            <p class="mt-5 max-w-lg text-base leading-relaxed text-slate-300 xl:text-lg">
                BPJS, PPh 21 metode TER, lembur, dan slip gaji dihitung otomatis dalam satu alur persetujuan yang rapi dan terdokumentasi.
            </p>

            <div class="mt-10 rounded-2xl border border-white/10 bg-white/[0.06] p-5 shadow-2xl backdrop-blur-xl">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-white">Alur payroll bulanan</p>
                    <span class="rounded-full bg-emerald-400/15 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-300 ring-1 ring-emerald-400/30">Terkontrol</span>
                </div>
                <ol class="mt-5 grid grid-cols-5 gap-2 text-center text-[11px] font-medium text-slate-300">
                    <?php foreach (['Draft', 'Review', 'Disetujui', 'Dibayar', 'Terkunci'] as $i => $step): ?>
                        <li class="flex flex-col items-center gap-2">
                            <span class="flex size-8 items-center justify-center rounded-full <?= $i < 4 ? 'bg-linear-to-br from-brand-500 to-violet-500 text-white shadow-glow' : 'bg-white/10 text-slate-300 ring-1 ring-white/15' ?>">
                                <?= $i < 4 ? icon('check', 'size-4') : icon('lock', 'size-3.5') ?>
                            </span>
                            <?= esc($step) ?>
                        </li>
                    <?php endforeach ?>
                </ol>
                <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full w-4/5 rounded-full bg-linear-to-r from-brand-400 via-violet-400 to-fuchsia-400"></div>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-3">
                    <?php foreach ([
                        ['shield-check', 'BPJS & PPh 21', 'Tarif terbaru'],
                        ['file-text', 'Slip gaji PDF', 'Siap unduh'],
                        ['users-round', 'Self-service', 'Akses karyawan'],
                    ] as [$iconName, $label, $caption]): ?>
                        <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10">
                            <?= icon($iconName, 'size-5 text-brand-200') ?>
                            <p class="mt-2 text-xs font-semibold text-white"><?= esc($label) ?></p>
                            <p class="text-[11px] text-slate-400"><?= esc($caption) ?></p>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <p class="relative text-xs text-slate-500">&copy; <?= date('Y') ?> <?= esc($companyName) ?> · PayrollKu v1.0</p>
    </aside>

    <!-- Form login -->
    <main class="flex flex-1 items-center justify-center bg-slate-50 px-5 py-12 sm:px-10 lg:bg-white">
        <div class="w-full max-w-[26rem]">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="flex size-10 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-violet-600 shadow-glow">
                    <?= icon('wallet-cards', 'size-5 text-white') ?>
                </span>
                <div>
                    <p class="font-bold tracking-tight text-slate-900">PayrollKu</p>
                    <p class="text-xs text-slate-500"><?= esc($companyName) ?></p>
                </div>
            </div>

            <h2 class="text-3xl font-bold tracking-tight text-slate-900">Selamat datang kembali</h2>
            <p class="mt-2 text-sm text-slate-500">Masuk menggunakan <span class="font-semibold text-slate-700">username</span> dan password akun Anda.</p>

            <div class="mt-8 space-y-3">
                <?php if ($errorMessage): ?>
                    <div class="flex gap-3 rounded-xl bg-rose-50 p-3.5 text-sm text-rose-700 ring-1 ring-rose-200" role="alert">
                        <?= icon('circle-alert', 'mt-0.5 size-4 shrink-0') ?>
                        <p><?= esc($errorMessage) ?></p>
                    </div>
                <?php endif ?>
                <?php if ($errors !== []): ?>
                    <div class="flex gap-3 rounded-xl bg-rose-50 p-3.5 text-sm text-rose-700 ring-1 ring-rose-200" role="alert">
                        <?= icon('circle-alert', 'mt-0.5 size-4 shrink-0') ?>
                        <ul class="space-y-0.5">
                            <?php foreach ($errors as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                <?php endif ?>
                <?php if (session('warning')): ?>
                    <div class="flex gap-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-800 ring-1 ring-amber-200" role="status">
                        <?= icon('info', 'mt-0.5 size-4 shrink-0') ?>
                        <p><?= esc(session('warning')) ?></p>
                    </div>
                <?php endif ?>
                <?php if (session('success')): ?>
                    <div class="flex gap-3 rounded-xl bg-emerald-50 p-3.5 text-sm text-emerald-700 ring-1 ring-emerald-200" role="status">
                        <?= icon('circle-check', 'mt-0.5 size-4 shrink-0') ?>
                        <p><?= esc(session('success')) ?></p>
                    </div>
                <?php endif ?>
            </div>

            <form method="post" action="<?= url_to('login.attempt') ?>" class="mt-6 space-y-5" novalidate
                  x-data="{ showPassword: false, loading: false }" @submit="loading = true">
                <?= csrf_field() ?>

                <div>
                    <label for="username" class="form-label">Username</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <?= icon('at-sign', 'size-4') ?>
                        </span>
                        <input id="username" name="username" type="text" x-ref="username"
                               value="<?= esc($username ?? '', 'attr') ?>"
                               class="form-control py-3 pl-10" placeholder="mis. andi.pratama"
                               autocomplete="username" autocapitalize="none" spellcheck="false"
                               maxlength="30" required <?= $username ? '' : 'autofocus' ?>>
                    </div>
                </div>

                <div>
                    <label for="password" class="form-label">Password</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <?= icon('lock-keyhole', 'size-4') ?>
                        </span>
                        <input id="password" name="password" x-ref="password" :type="showPassword ? 'text' : 'password'" type="password"
                               class="form-control py-3 pr-11 pl-10" placeholder="••••••••"
                               autocomplete="current-password" required <?= $username ? 'autofocus' : '' ?>>
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center rounded-r-xl px-3.5 text-slate-400 transition hover:text-slate-600 focus-visible:text-brand-600 focus-visible:outline-none"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" aria-label="Tampilkan password">
                            <span x-show="!showPassword"><?= icon('eye', 'size-4') ?></span>
                            <span x-show="showPassword" x-cloak><?= icon('eye-off', 'size-4') ?></span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600 select-none">
                        <input type="checkbox" name="remember" value="1" <?= session('old_remember') ? 'checked' : '' ?>
                               class="size-4 rounded border-slate-300 text-brand-600 accent-brand-600 focus:ring-brand-500">
                        Ingat saya selama 30 hari
                    </label>
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="text-sm font-semibold text-brand-600 hover:text-brand-500">Lupa password?</button>
                        <div x-show="open" x-cloak x-transition.origin.top.right
                             class="absolute right-0 z-10 mt-2 w-64 rounded-xl bg-white p-3.5 text-xs leading-relaxed text-slate-600 shadow-elevated ring-1 ring-slate-200">
                            Reset password dilakukan oleh <span class="font-semibold text-slate-800">Super Admin</span>. Hubungi tim HR atau administrator sistem perusahaan Anda.
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full py-3 text-[15px]" :disabled="loading">
                    <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span x-text="loading ? 'Memproses…' : 'Masuk'">Masuk</span>
                    <span x-show="!loading"><?= icon('arrow-right', 'size-4') ?></span>
                </button>
            </form>

            <?php if ($demoAccounts !== []): ?>
                <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white/60 p-4">
                    <p class="flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        <?= icon('flask-conical', 'size-3.5') ?> Akun demo · development
                    </p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <?php foreach ($demoAccounts as $account): ?>
                            <button type="button"
                                    class="group rounded-xl bg-white px-3 py-2 text-left ring-1 ring-slate-200 transition hover:ring-brand-300 hover:shadow-card"
                                    @click="document.getElementById('username').value = '<?= esc($account['username'], 'js') ?>'; document.getElementById('password').value = '<?= esc($account['password'], 'js') ?>'; document.getElementById('password').focus()"
                                    x-data>
                                <span class="block truncate text-xs font-semibold text-slate-800 group-hover:text-brand-700"><?= esc($account['username']) ?></span>
                                <span class="block text-[11px] text-slate-500"><?= esc($account['role']) ?></span>
                            </button>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>

            <p class="mt-8 flex items-center justify-center gap-1.5 text-xs text-slate-400">
                <?= icon('shield-check', 'size-3.5') ?> Dilindungi CSRF, enkripsi password, dan pembatasan percobaan login.
            </p>
        </div>
    </main>
</div>
<?php $this->endSection() ?>
