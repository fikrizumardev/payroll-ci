<?php
/**
 * @var bool        $mustChange
 * @var string|null $lastChanged
 */
$this->extend('layouts/app');
$this->section('content');

$errors = (array) (session('errors') ?? []);
?>
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Ganti Password</h1>
        <p class="mt-1 text-sm text-slate-500">
            Terakhir diganti: <?= esc(tanggal_id($lastChanged, 'datetime')) ?>
        </p>
    </div>

    <?php if ($mustChange): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
            <?= icon('shield-alert', 'mt-0.5 size-5 shrink-0') ?>
            <p>Akun Anda memakai password sementara. Ganti password terlebih dahulu sebelum melanjutkan.</p>
        </div>
    <?php endif ?>

    <form method="post" action="<?= url_to('account.password.update') ?>" class="card p-6 sm:p-8"
          x-data="{ show: false, password: '' }">
        <?= csrf_field() ?>

        <?php if ($errors !== []): ?>
            <div class="mb-6 rounded-xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200" role="alert">
                <p class="font-semibold">Password belum bisa disimpan:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <div class="space-y-5">
            <div>
                <label for="current_password" class="form-label">Password saat ini</label>
                <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" type="password"
                       class="form-control" autocomplete="current-password" required>
            </div>
            <div>
                <label for="new_password" class="form-label">Password baru</label>
                <input id="new_password" name="new_password" :type="show ? 'text' : 'password'" type="password"
                       class="form-control" autocomplete="new-password" x-model="password" required>
                <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-2">
                    <li class="flex items-center gap-1.5" :class="password.length >= 8 ? 'text-emerald-600' : 'text-slate-400'">
                        <?= icon('circle-check', 'size-3.5') ?> Minimal 8 karakter
                    </li>
                    <li class="flex items-center gap-1.5" :class="/[A-Za-z]/.test(password) && /\d/.test(password) ? 'text-emerald-600' : 'text-slate-400'">
                        <?= icon('circle-check', 'size-3.5') ?> Mengandung huruf dan angka
                    </li>
                </ul>
            </div>
            <div>
                <label for="new_password_confirmation" class="form-label">Konfirmasi password baru</label>
                <input id="new_password_confirmation" name="new_password_confirmation" :type="show ? 'text' : 'password'" type="password"
                       class="form-control" autocomplete="new-password" required>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" x-model="show" class="size-4 rounded accent-brand-600"> Tampilkan password
            </label>
        </div>

        <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
            <?php if (! $mustChange): ?>
                <a href="<?= url_to('dashboard') ?>" class="btn btn-secondary">Batal</a>
            <?php endif ?>
            <button type="submit" class="btn btn-primary"><?= icon('save', 'size-4') ?> Simpan password</button>
        </div>
    </form>
</div>
<?php $this->endSection() ?>
