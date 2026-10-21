<?php
/**
 * Error tak terduga di environment production (detail error tidak ditampilkan).
 */
$code        = '500';
$heading     = 'Terjadi kendala pada sistem';
$description = 'Maaf, permintaan Anda belum bisa diproses. Tim kami sudah mencatat kejadian ini. Silakan coba beberapa saat lagi.';
$iconPath    = 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z';
$links       = [
    ['label' => 'Muat ulang Dashboard', 'url' => 'dashboard', 'primary' => true],
];

include __DIR__ . '/_shell.php';
