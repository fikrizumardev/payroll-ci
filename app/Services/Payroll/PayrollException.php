<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis payroll (status tidak valid, peran tidak berhak, dll.).
 * Pesannya aman ditampilkan ke pengguna.
 */
final class PayrollException extends RuntimeException
{
}
