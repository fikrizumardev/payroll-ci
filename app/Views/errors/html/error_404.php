<?php
/**
 * Halaman tidak ditemukan.
 *
 * @var string|null $message
 */
$code        = '404';
$heading     = 'Halaman tidak ditemukan';
$description = 'Halaman yang Anda cari tidak tersedia, sudah dipindahkan, atau alamatnya salah ketik.';
$iconPath    = 'M21 21l-4.35-4.35M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z M8.5 11h5';
$links       = [
    ['label' => 'Ke Dashboard', 'url' => 'dashboard', 'primary' => true],
    ['label' => 'Halaman Login', 'url' => 'login'],
];

include __DIR__ . '/_shell.php';
