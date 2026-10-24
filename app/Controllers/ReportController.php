<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Services\Export\ReportExporter;
use App\Services\Report\ReportService;
use App\Services\Report\ReportTables;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

/**
 * Laporan: rekap gaji, BPJS, PPh 21, kehadiran, dan tren biaya per departemen.
 * Akses: Super Admin, HR, Finance. Setiap ekspor tercatat di audit log.
 */
class ReportController extends BaseController
{
    /**
     * Katalog laporan (urutan = urutan tab).
     *
     * @var array<string, array{label: string, icon: string, route: string, description: string, scope: string}>
     */
    public const REPORTS = [
        'payroll' => [
            'label'       => 'Rekap Gaji',
            'icon'        => 'table-2',
            'route'       => 'reports.payroll',
            'description' => 'Pendapatan, potongan, dan take home pay per karyawan, dikelompokkan per departemen dengan subtotal.',
            'scope'       => 'period',
        ],
        'bpjs' => [
            'label'       => 'BPJS',
            'icon'        => 'shield-plus',
            'route'       => 'reports.bpjs',
            'description' => 'Iuran Kesehatan, JHT, JKK, JKM, dan JP — porsi perusahaan dan karyawan per program.',
            'scope'       => 'period',
        ],
        'pph21' => [
            'label'       => 'PPh 21',
            'icon'        => 'landmark',
            'route'       => 'reports.pph21',
            'description' => 'Pemotongan PPh 21 bulanan (NIK, NPWP, PTKP, bruto, TER) siap dipindahkan ke e-Bupot 21.',
            'scope'       => 'period',
        ],
        'attendance' => [
            'label'       => 'Kehadiran',
            'icon'        => 'calendar-check-2',
            'route'       => 'reports.attendance',
            'description' => 'Rekap kehadiran bulanan per karyawan: hadir, terlambat, cuti, alpha, dan jam lembur.',
            'scope'       => 'month',
        ],
        'cost-trend' => [
            'label'       => 'Tren Biaya',
            'icon'        => 'chart-column-stacked',
            'route'       => 'reports.cost-trend',
            'description' => 'Biaya perusahaan 12 bulan terakhir per departemen dalam grafik dan tabel.',
            'scope'       => 'month',
        ],
    ];

    private const FORMATS = ['csv' => 'CSV', 'xlsx' => 'XLSX'];

    private ?ReportService $reports = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $periods = null;

    public function index(): string
    {
        $periods = $this->periods();
        $latest  = $periods[0] ?? null;

        return view('reports/index', [
            'title'       => 'Laporan',
            'breadcrumbs' => [['label' => 'Payroll'], ['label' => 'Laporan']],
            'reports'     => self::REPORTS,
            'periods'     => $periods,
            'latest'      => $latest,
            'summary'     => $latest === null ? null : $this->service()->payrollRecap($latest['id'])['total'],
        ]);
    }

    public function payroll(): string
    {
        return $this->periodReport('payroll', fn (array $period): array => ['recap' => $this->service()->payrollRecap($period['id'])]);
    }

    public function bpjs(): string
    {
        return $this->periodReport('bpjs', fn (array $period): array => ['bpjs' => $this->service()->bpjs($period['id'])]);
    }

    public function pph21(): string
    {
        return $this->periodReport('pph21', fn (array $period): array => ['pph' => $this->service()->pph21($period['id'])]);
    }

    public function attendance(): string
    {
        [$year, $month] = $this->monthParam();
        $department     = (int) $this->request->getGet('department_id');
        $first          = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        return view('reports/attendance', $this->common('attendance') + [
            'year'         => $year,
            'month'        => $month,
            'prevMonth'    => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'    => $first->modify('+1 month')->format('Y-m'),
            'departmentId' => $department,
            'departments'  => (new DepartmentModel())->options(),
            'report'       => $this->service()->attendance($year, $month, $department ?: null),
            'filters'      => array_filter(['month' => $first->format('Y-m'), 'department_id' => $department ?: null]),
        ]);
    }

    public function costTrend(): string
    {
        [$year, $month] = $this->trendEnd();

        return view('reports/cost_trend', $this->common('cost-trend') + [
            'year'    => $year,
            'month'   => $month,
            'trend'   => $this->service()->costTrend($year, $month),
            'filters' => ['end' => sprintf('%04d-%02d', $year, $month)],
        ]);
    }

    public function export(string $report): ResponseInterface
    {
        if (! isset(self::REPORTS[$report])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $format = (string) $this->request->getGet('format');
        $format = array_key_exists($format, self::FORMATS) ? $format : 'csv';
        $label  = self::REPORTS[$report]['label'];

        if (self::REPORTS[$report]['scope'] === 'period') {
            $period = $this->selectedPeriod() ?? throw PageNotFoundException::forPageNotFound('Belum ada payroll yang bisa dilaporkan.');
            $table  = match ($report) {
                'payroll' => ReportTables::payroll($this->service()->payrollRecap($period['id'])),
                'bpjs'    => ReportTables::bpjs($this->service()->bpjs($period['id'])),
                'pph21'   => ReportTables::pph21($this->service()->pph21($period['id']), $period),
            };
            $scope    = $period['name'];
            $slug     = sprintf('%04d-%02d', $period['year'], $period['month']);
            $subtitle = 'Periode ' . $period['name'] . ' · status ' . status_meta($period['status'])['label']
                . (ReportService::isFinal($period) ? '' : ' (angka sementara)');
            $entity   = ['payroll_period', $period['id']];
        } elseif ($report === 'attendance') {
            [$year, $month] = $this->monthParam();
            $department     = (int) $this->request->getGet('department_id');
            $data           = $this->service()->attendance($year, $month, $department ?: null);
            $table          = ReportTables::attendance($data);
            $departmentName = $department > 0 ? ((new DepartmentModel())->find($department)['name'] ?? null) : null;
            $scope          = periode_id($year, $month) . ($departmentName ? " · {$departmentName}" : '');
            $slug           = sprintf('%04d-%02d', $year, $month) . ($departmentName ? '-' . url_title($departmentName, '-', true) : '');
            $subtitle       = 'Bulan ' . $scope . ' · hari kerja s.d. ' . tanggal_id($data['until'], 'long');
            $entity         = ['report', null];
        } else {
            [$year, $month] = $this->trendEnd();
            $trend          = $this->service()->costTrend($year, $month);
            $table          = ReportTables::costTrend($trend);
            $first          = $trend['months'][0];
            $scope          = periode_id($first['year'], $first['month'], true) . ' – ' . periode_id($year, $month, true);
            $slug           = sprintf('%04d-%02d', $year, $month);
            $subtitle       = $scope . ' · biaya perusahaan = total pendapatan + BPJS porsi perusahaan';
            $entity         = ['report', null];
        }

        $settings = service('appSettings');
        $heading  = [
            "Laporan {$label}",
            $settings->get('company.name', 'PayrollKu') . ' · NPWP ' . $settings->get('company.npwp', '—'),
            $subtitle,
            'Dicetak ' . tanggal_id(date('Y-m-d H:i:s'), 'datetime') . ' WIB oleh ' . ($this->currentUser()['name'] ?? '—'),
        ];

        $filename = 'laporan-' . $report . '-' . $slug . '.' . $format;
        $body     = $format === 'xlsx'
            ? ReportExporter::xlsx($label, $heading, $table['columns'], $table['rows'])
            : ReportExporter::csv($table['columns'], $table['rows']);

        $this->audit('report.export', $entity[0], $entity[1], "Mengekspor laporan {$label} {$scope} ({$this->formatLabel($format)})", null, ['report' => $report, 'format' => $format, 'rows' => count(array_filter($table['rows'], static fn (array $row): bool => ($row['type'] ?? 'data') === 'data'))]);

        return $this->response
            ->setHeader('Content-Type', $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($body);
    }

    // ------------------------------------------------------------------

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $data
     */
    private function periodReport(string $report, callable $data): string
    {
        $period = $this->selectedPeriod();

        return view('reports/' . $report, $this->common($report) + [
            'period'    => $period,
            'canExport' => $period !== null,
            'isFinal'   => $period !== null && ReportService::isFinal($period),
            'filters'   => $period === null ? [] : ['period' => $period['id']],
        ] + ($period === null ? [] : $data($period)));
    }

    /**
     * @return array<string, mixed>
     */
    private function common(string $report): array
    {
        return [
            'title'       => 'Laporan ' . self::REPORTS[$report]['label'],
            'breadcrumbs' => [['label' => 'Payroll'], ['label' => 'Laporan', 'url' => url_to('reports.index')], ['label' => self::REPORTS[$report]['label']]],
            'reports'     => self::REPORTS,
            'active'      => $report,
            'formats'     => self::FORMATS,
            'periods'     => $this->periods(),
        ];
    }

    /**
     * Periode dari query `?period=`; default periode terbaru yang sudah di-generate.
     *
     * @return array<string, mixed>|null
     */
    private function selectedPeriod(): ?array
    {
        $periods = $this->periods();
        $id      = (int) $this->request->getGet('period');

        foreach ($periods as $period) {
            if ($period['id'] === $id) {
                return $period;
            }
        }

        return $periods[0] ?? null;
    }

    /**
     * @return array{int, int}
     */
    private function monthParam(): array
    {
        $value = (string) $this->request->getGet('month');

        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) && (int) $m[1] >= 2000 && (int) $m[1] < 2100) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) date('Y'), (int) date('n')];
    }

    /**
     * Bulan terakhir grafik tren (`?end=YYYY-MM`); default periode payroll terbaru.
     *
     * @return array{int, int}
     */
    private function trendEnd(): array
    {
        $value = (string) $this->request->getGet('end');

        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) && (int) $m[1] >= 2000 && (int) $m[1] < 2100) {
            return [(int) $m[1], (int) $m[2]];
        }

        $latest = $this->periods()[0] ?? null;

        return $latest === null ? [(int) date('Y'), (int) date('n')] : [$latest['year'], $latest['month']];
    }

    private function formatLabel(string $format): string
    {
        return self::FORMATS[$format] ?? strtoupper($format);
    }

    private function service(): ReportService
    {
        return $this->reports ??= ReportService::make();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function periods(): array
    {
        return $this->periods ??= $this->service()->periods();
    }
}
