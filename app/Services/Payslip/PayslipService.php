<?php

declare(strict_types=1);

namespace App\Services\Payslip;

use App\Models\PayrollItemModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;
use App\Models\SalaryComponentModel;
use App\Models\UserModel;
use App\Services\SettingService;
use CodeIgniter\Database\BaseConnection;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use ZipArchive;

/**
 * Slip gaji: penyusunan data (web, PDF, ZIP), aturan akses, dan render PDF.
 *
 * Data karyawan diambil dari snapshot payroll agar slip selalu sama dengan
 * yang dihitung & disetujui, walaupun data karyawan berubah kemudian.
 */
class PayslipService
{
    /** Status periode yang slipnya final dan boleh dilihat karyawan. */
    public const FINAL_STATUSES = [PayrollPeriodModel::STATUS_PAID, PayrollPeriodModel::STATUS_LOCKED];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly SettingService $settings,
    ) {
    }

    public static function make(): self
    {
        return new self(db_connect(), service('appSettings'));
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null data slip, atau null bila payroll tidak ada
     */
    public function find(int $payrollId): ?array
    {
        $payroll = (new PayrollModel())->find($payrollId);

        if ($payroll === null) {
            return null;
        }

        $period = (new PayrollPeriodModel())->find($payroll['period_id']);

        return $this->compose($payroll, $period, (new PayrollItemModel())->groupedForPayroll($payrollId));
    }

    /**
     * Seluruh slip satu periode (dimuat massal: 3 query, tanpa N+1).
     *
     * @return list<array<string, mixed>>
     */
    public function forPeriod(int $periodId): array
    {
        $period   = (new PayrollPeriodModel())->find($periodId);
        $payrolls = (new PayrollModel())->withEmployee()->where('payrolls.period_id', $periodId)->orderBy('employees.nip')->findAll();

        if ($period === null || $payrolls === []) {
            return [];
        }

        $grouped = [];

        foreach ((new PayrollItemModel())->whereIn('payroll_id', array_column($payrolls, 'id'))->orderBy('sort_order')->findAll() as $item) {
            $grouped[$item['payroll_id']][$item['type']][] = $item;
        }

        $empty = array_fill_keys(array_keys(SalaryComponentModel::TYPES), []);
        $slips = [];

        foreach ($payrolls as $payroll) {
            $slips[] = $this->compose($payroll, $period, ($grouped[$payroll['id']] ?? []) + $empty);
        }

        return $slips;
    }

    /**
     * Slip final milik seorang karyawan (terbaru dahulu) untuk halaman self-service.
     *
     * @return list<array<string, mixed>>
     */
    public function listForEmployee(int $employeeId, ?int $year = null): array
    {
        $builder = $this->db->table('payrolls p')
            ->select('p.id, p.take_home_pay, p.total_earning, p.total_deduction, p.pph21, p.present_days, p.active_workdays, p.workdays, p.overtime_hours')
            ->select('pp.id AS period_id, pp.name AS period_name, pp.year, pp.month, pp.pay_date, pp.status AS period_status')
            ->join('payroll_periods pp', 'pp.id = p.period_id')
            ->where('p.employee_id', $employeeId)
            ->whereIn('pp.status', self::FINAL_STATUSES)
            ->orderBy('pp.year', 'DESC')->orderBy('pp.month', 'DESC');

        if ($year !== null) {
            $builder->where('pp.year', $year);
        }

        return array_map(static function (array $row): array {
            foreach (['id', 'take_home_pay', 'total_earning', 'total_deduction', 'pph21', 'present_days', 'active_workdays', 'workdays', 'period_id', 'year', 'month'] as $key) {
                $row[$key] = (int) $row[$key];
            }

            return $row;
        }, $builder->get()->getResultArray());
    }

    /**
     * @param array<string, mixed>                       $payroll
     * @param array<string, mixed>                       $period
     * @param array<string, list<array<string, mixed>>>  $items
     *
     * @return array<string, mixed>
     */
    private function compose(array $payroll, array $period, array $items): array
    {
        return [
            'payroll'  => $payroll,
            'period'   => $period,
            'employee' => $payroll['snapshot'] ?? [],
            'items'    => $items,
            'final'    => in_array($period['status'], self::FINAL_STATUSES, true),
            'number'   => sprintf('SG/%04d/%02d/%s', $period['year'], $period['month'], $payroll['snapshot']['nip'] ?? $payroll['employee_id']),
            'company'  => $this->company(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function company(): array
    {
        return [
            'name'    => (string) $this->settings->get('company.name', 'PayrollKu'),
            'address' => $this->settings->get('company.address'),
            'city'    => $this->settings->get('company.city'),
            'phone'   => $this->settings->get('company.phone'),
            'website' => $this->settings->get('company.website'),
            'npwp'    => $this->settings->get('company.npwp'),
            'logo'    => $this->logoDataUri(),
        ];
    }

    /**
     * Logo perusahaan sebagai data URI (setting `company.logo`, path relatif folder public),
     * atau logo bawaan berbentuk SVG.
     */
    public function logoDataUri(): string
    {
        $path = (string) $this->settings->get('company.logo', '');
        $file = $path === '' ? null : realpath(FCPATH . ltrim($path, '/\\'));

        // Hanya berkas di dalam folder public yang boleh dibaca
        if ($file !== null && $file !== false && str_starts_with($file, realpath(FCPATH)) && is_file($file)) {
            $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                'png'         => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'svg'         => 'image/svg+xml',
                default       => null,
            };

            if ($mime !== null) {
                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
            }
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="64" height="64">'
            . '<rect width="32" height="32" rx="9" fill="#4f46e5"/>'
            . '<path d="M10 22V10h7a4.5 4.5 0 0 1 0 9h-4" fill="none" stroke="#ffffff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // ------------------------------------------------------------------
    // Akses
    // ------------------------------------------------------------------

    /**
     * HR, Finance, dan Super Admin melihat semua slip (termasuk pratinjau periode belum final).
     * Karyawan hanya slip miliknya sendiri pada periode yang sudah dibayar/terkunci.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $slip
     */
    public static function canView(array $user, array $slip): bool
    {
        if (in_array($user['role'] ?? null, [UserModel::ROLE_SUPERADMIN, UserModel::ROLE_HR, UserModel::ROLE_FINANCE], true)) {
            return true;
        }

        return ! empty($user['employee_id'])
            && (int) $user['employee_id'] === (int) $slip['payroll']['employee_id']
            && $slip['final'];
    }

    // ------------------------------------------------------------------
    // PDF & ZIP
    // ------------------------------------------------------------------

    /**
     * Nama berkas: Slip-Gaji-EMP-0014-2026-09.pdf
     *
     * @param array<string, mixed> $slip
     */
    public static function filename(array $slip): string
    {
        $nip = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($slip['employee']['nip'] ?? $slip['payroll']['employee_id']));

        return sprintf('Slip-Gaji-%s-%04d-%02d.pdf', $nip, $slip['period']['year'], $slip['period']['month']);
    }

    /**
     * @param array<string, mixed> $slip
     */
    public function pdf(array $slip, ?Dompdf $dompdf = null): string
    {
        $dompdf ??= $this->dompdf();
        $dompdf->loadHtml(view('payslips/pdf', $slip, ['saveData' => false]), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * ZIP berisi PDF slip seluruh karyawan satu periode.
     *
     * @param list<array<string, mixed>> $slips
     *
     * @return string isi berkas ZIP
     */
    public function zip(array $slips): string
    {
        if ($slips === []) {
            throw new RuntimeException('Belum ada slip gaji pada periode ini.');
        }

        $path = tempnam(sys_get_temp_dir(), 'slip');
        $zip  = new ZipArchive();

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas ZIP tidak dapat dibuat.');
        }

        try {
            foreach ($slips as $slip) {
                $zip->addFromString(self::filename($slip), $this->pdf($slip));
            }

            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function dompdf(): Dompdf
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('Helvetica'); // font inti PDF (Windows-1252): ±2× lebih cepat & 4× lebih kecil dari DejaVu
        $options->setTempDir(sys_get_temp_dir());
        $options->setChroot([FCPATH]);

        return new Dompdf($options);
    }
}
