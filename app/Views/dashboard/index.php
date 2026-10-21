<?php
/**
 * @var array<string, mixed> $user
 * @var int                  $activeEmployee
 */
$this->extend('layouts/app');
$this->section('content');
?>
<div class="card p-8">
    <p class="text-sm font-semibold text-brand-600"><?= esc(tanggal_id(date('Y-m-d'), 'full')) ?></p>
    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Halo, <?= esc($user['name']) ?></h1>
    <p class="mt-2 text-sm text-slate-500">
        Anda masuk sebagai <span class="font-semibold text-slate-700"><?= esc(role_label($user['role'])) ?></span>.
        Saat ini terdapat <span class="font-semibold text-slate-700"><?= number_id($activeEmployee) ?> karyawan aktif</span>.
    </p>
</div>
<?php $this->endSection() ?>
