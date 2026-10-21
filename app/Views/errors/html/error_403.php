<?php
$this->extend('layouts/auth');
$this->section('content');
?>
<main class="flex min-h-full items-center justify-center bg-slate-50 px-6 py-24">
    <div class="text-center">
        <p class="text-sm font-semibold text-brand-600">403</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Akses ditolak</h1>
        <p class="mt-3 text-sm text-slate-500">Anda tidak memiliki izin untuk membuka halaman ini.</p>
        <a href="<?= url_to('dashboard') ?>" class="btn btn-primary mt-8">Kembali ke Dashboard</a>
    </div>
</main>
<?php $this->endSection() ?>
