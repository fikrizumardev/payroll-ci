<?php
/**
 * Akses ditolak — dirender oleh RoleFilter.
 */
$code        = '403';
$heading     = 'Akses ditolak';
$description = 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Hubungi administrator bila Anda merasa memerlukan akses.';
$iconPath    = 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z M9.5 9.5l5 5 M14.5 9.5l-5 5';
$links       = [
    ['label' => 'Kembali ke Dashboard', 'url' => 'dashboard', 'primary' => true],
];

include __DIR__ . '/_shell.php';
