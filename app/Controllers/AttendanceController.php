<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AttendanceModel;
use App\Models\DepartmentModel;
use App\Models\EmployeeModel;
use App\Models\HolidayModel;
use App\Models\PayrollPeriodModel;
use App\Services\Attendance\AttendanceImporter;
use App\Services\Attendance\AttendanceService;
use App\Services\Payroll\WorkdayCalendar;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Kehadiran: input harian, rekap bulanan, dan import CSV.
 * Lihat: HR & Finance · ubah: HR (Super Admin selalu boleh).
 */
class AttendanceController extends BaseController
{
    private const IMPORT_SESSION_KEY = 'attendance_import';

    // ------------------------------------------------------------------
    // Input harian
    // ------------------------------------------------------------------

    public function index(): string
    {
        $date      = $this->dateParam('date', date('Y-m-d'));
        $service   = AttendanceService::make();
        $employees = (new EmployeeModel())->withRelations()->employedBetween($date, $date)->orderBy('employees.nip')->findAll();
        $records   = array_column(
            (new AttendanceModel())->where('date', $date)->whereIn('employee_id', array_column($employees, 'id') ?: [0])->findAll(),
            null,
            'employee_id',
        );

        $summary = array_fill_keys(array_keys(AttendanceModel::STATUSES), 0);

        foreach ($records as $record) {
            $summary[$record['status']]++;
        }

        $day = new DateTimeImmutable($date);

        return view('attendance/index', [
            'title'       => 'Kehadiran Harian',
            'breadcrumbs' => [['label' => 'Kehadiran'], ['label' => 'Kehadiran Harian']],
            'date'        => $date,
            'prevDate'    => $day->modify('-1 day')->format('Y-m-d'),
            'nextDate'    => $day->modify('+1 day')->format('Y-m-d'),
            'employees'   => $employees,
            'records'     => $records,
            'summary'     => $summary,
            'holiday'     => (new HolidayModel())->where('date', $date)->first(),
            'isWeekend'   => (int) $day->format('N') >= 6,
            'closedError' => $service->guard()->errorFor($date),
            'departments' => (new DepartmentModel())->options(),
            'workStart'   => $service->workStart(),
            'tolerance'   => $service->tolerance(),
            'canManage'   => $this->canManage(),
        ]);
    }

    public function save(): RedirectResponse
    {
        $date = (string) $this->request->getPost('date');

        if (AttendanceImporter::parseDate($date) !== $date) {
            return redirect()->route('attendance.index')->with('error', 'Tanggal kehadiran tidak valid.');
        }

        $back    = url_to('attendance.index') . '?date=' . $date;
        $service = AttendanceService::make();

        if (($error = $service->guard()->errorFor($date)) !== null) {
            return redirect()->to($back)->with('error', $error . ' Kehadiran tidak dapat diubah.');
        }

        $allowed = array_flip(array_map('intval', array_column(
            (new EmployeeModel())->select('employees.id')->employedBetween($date, $date)->findAll(),
            'id',
        )));

        $rows   = [];
        $errors = [];

        foreach ((array) $this->request->getPost('rows') as $employeeId => $input) {
            $employeeId = (int) $employeeId;

            if (! isset($allowed[$employeeId]) || ! is_array($input) || trim((string) ($input['status'] ?? '')) === '') {
                continue;
            }

            $result = $service->normalize($employeeId, $date, $input);

            if ($result['errors'] !== []) {
                foreach ($result['errors'] as $field => $message) {
                    $errors["rows.{$employeeId}.{$field}"] = $message;
                }

                continue;
            }

            $rows[] = $result['data'];
        }

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('errors', $errors)
                ->with('error', count($errors) . ' isian belum valid. Periksa baris yang ditandai merah.');
        }

        if ($rows === []) {
            return redirect()->to($back)->with('warning', 'Belum ada status kehadiran yang dipilih.');
        }

        $result = $service->upsert($rows);

        if ($result['inserted'] + $result['updated'] > 0) {
            $this->audit('attendance.save', 'attendance', $date, sprintf(
                'Menyimpan kehadiran %s: %d baru, %d diubah',
                tanggal_id($date),
                $result['inserted'],
                $result['updated'],
            ), null, $result + ['date' => $date]);
        }

        $message = sprintf('Kehadiran %s tersimpan: %d baru, %d diubah, %d tetap.', tanggal_id($date, 'long'), $result['inserted'], $result['updated'], $result['unchanged']);

        return redirect()->to($back)->with('success', $message)->with('warning', $this->regenerateHint([$date], 'perubahan kehadiran'));
    }

    // ------------------------------------------------------------------
    // Rekap bulanan
    // ------------------------------------------------------------------

    public function recap(): string
    {
        [$year, $month] = $this->monthParam();
        $start          = sprintf('%04d-%02d-01', $year, $month);
        $end            = date('Y-m-t', strtotime($start));
        $today          = date('Y-m-d');
        $departmentId   = (int) $this->request->getGet('department_id');

        $model = (new EmployeeModel())->withRelations()->employedBetween($start, $end);

        if ($departmentId > 0) {
            $model->where('employees.department_id', $departmentId);
        }

        $employees = $model->orderBy('departments.name')->orderBy('employees.full_name')->findAll();
        $ids       = array_map('intval', array_column($employees, 'id'));
        $grid      = [];

        if ($ids !== []) {
            $rows = (new AttendanceModel())
                ->select('employee_id, date, status, check_in, check_out, late_minutes, note')
                ->whereIn('employee_id', $ids)
                ->where('date >=', $start)->where('date <=', $end)
                ->findAll();

            foreach ($rows as $row) {
                $grid[$row['employee_id']][$row['date']] = $row;
            }
        }

        $holidays = array_column((new HolidayModel())->where('date >=', $start)->where('date <=', $end)->findAll(), 'name', 'date');
        $calendar = new WorkdayCalendar(array_keys($holidays));
        $days     = [];

        for ($d = new DateTimeImmutable($start); $d->format('Y-m-d') <= $end; $d = $d->modify('+1 day')) {
            $date   = $d->format('Y-m-d');
            $days[] = [
                'date'    => $date,
                'day'     => (int) $d->format('j'),
                'weekday' => (int) $d->format('N'),
                'workday' => $calendar->isWorkday($date),
                'holiday' => $holidays[$date] ?? null,
                'future'  => $date > $today,
            ];
        }

        $statuses = array_keys(AttendanceModel::STATUSES);
        $totals   = [];
        $daily    = array_fill_keys(array_column($days, 'date'), 0);

        foreach ($employees as $employee) {
            $id     = (int) $employee['id'];
            $counts = array_fill_keys($statuses, 0);

            foreach ($grid[$id] ?? [] as $date => $row) {
                $counts[$row['status']]++;

                if (in_array($row['status'], AttendanceModel::PRESENT_STATUSES, true)) {
                    $daily[$date]++;
                }
            }

            // Hari kerja yang seharusnya sudah tercatat (s.d. hari ini) — dasar indikator data belum lengkap
            $expected    = $calendar->activeWorkdays($start, min($end, $today), $employee['join_date'], $employee['resign_date']);
            $recorded    = array_sum($counts) - $counts['holiday'];
            $totals[$id] = $counts + [
                'recorded' => $recorded,
                'expected' => $expected,
                'rate'     => $recorded > 0 ? ($counts['present'] + $counts['late']) / $recorded * 100 : null,
            ];
        }

        $period = (new PayrollPeriodModel())->where(['year' => $year, 'month' => $month])->first();
        $first  = new DateTimeImmutable($start);

        return view('attendance/recap', [
            'title'        => 'Rekap Kehadiran',
            'breadcrumbs'  => [['label' => 'Kehadiran'], ['label' => 'Rekap Bulanan']],
            'year'         => $year,
            'month'        => $month,
            'prevMonth'    => $first->modify('-1 month')->format('Y-m'),
            'nextMonth'    => $first->modify('+1 month')->format('Y-m'),
            'employees'    => $employees,
            'grid'         => $grid,
            'days'         => $days,
            'totals'       => $totals,
            'daily'        => $daily,
            'workdays'     => $calendar->count($start, $end),
            'period'       => $period,
            'departmentId' => $departmentId,
            'departments'  => (new DepartmentModel())->options(),
            'canManage'    => $this->canManage(),
        ]);
    }

    // ------------------------------------------------------------------
    // Import CSV
    // ------------------------------------------------------------------

    public function import(): string
    {
        $session = session(self::IMPORT_SESSION_KEY);
        $preview = null;

        if (is_array($session) && isset($session['rows'])) {
            $preview = AttendanceImporter::make()->preview($session['rows']) + ['filename' => $session['filename']];
        }

        return view('attendance/import', [
            'title'       => 'Import Kehadiran',
            'breadcrumbs' => [['label' => 'Kehadiran'], ['label' => 'Kehadiran Harian', 'url' => url_to('attendance.index')], ['label' => 'Import CSV']],
            'preview'     => $preview,
            'maxRows'     => AttendanceImporter::MAX_ROWS,
            'columns'     => AttendanceImporter::COLUMNS,
            'today'       => date('Y-m-d'),
        ]);
    }

    public function template(): ResponseInterface
    {
        $date      = $this->dateParam('date', date('Y-m-d'));
        $employees = (new EmployeeModel())->select('employees.nip, employees.full_name')->employedBetween($date, $date)->orderBy('employees.nip')->findAll();

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="template-kehadiran-' . $date . '.csv"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(AttendanceImporter::template($employees, $date));
    }

    public function upload(): RedirectResponse
    {
        $back = url_to('attendance.import');
        $file = $this->request->getFile('file');

        if (! $this->validate(['file' => [
            'label' => 'File CSV',
            'rules' => 'uploaded[file]|max_size[file,1024]|ext_in[file,csv,txt]',
        ]])) {
            return redirect()->to($back)->with('error', $this->validator->getError('file'));
        }

        try {
            $rows = AttendanceImporter::make()->read((string) file_get_contents($file->getTempName()));
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        session()->set(self::IMPORT_SESSION_KEY, ['filename' => $file->getClientName(), 'rows' => $rows]);

        return redirect()->to($back . '#pratinjau')->with('info', count($rows) . ' baris dibaca. Periksa pratinjau sebelum menyimpan.');
    }

    public function commit(): RedirectResponse
    {
        $session = session(self::IMPORT_SESSION_KEY);
        $back    = url_to('attendance.import');

        if (! is_array($session) || ! isset($session['rows'])) {
            return redirect()->to($back)->with('error', 'Tidak ada data import yang menunggu disimpan. Unggah ulang file CSV.');
        }

        // Validasi ulang saat menyimpan: status periode / data bisa berubah sejak pratinjau
        $preview = AttendanceImporter::make()->preview($session['rows']);
        $rows    = [];

        foreach ($preview['rows'] as $row) {
            if (in_array($row['action'], ['insert', 'update'], true)) {
                $rows[] = $row['data'];
            }
        }

        if ($rows === []) {
            return redirect()->to($back)->with('warning', 'Tidak ada baris baru atau berubah yang bisa disimpan.');
        }

        $result        = AttendanceService::make()->upsert($rows);
        [$from, $till] = AttendanceImporter::dateRange($preview['rows']);
        session()->remove(self::IMPORT_SESSION_KEY);

        $this->audit('attendance.import', 'attendance', null, sprintf(
            'Import kehadiran %s: %d baru, %d diubah, %d baris dilewati (%s s.d. %s)',
            $session['filename'],
            $result['inserted'],
            $result['updated'],
            $preview['summary']['errors'],
            tanggal_id($from),
            tanggal_id($till),
        ), null, $result + ['skipped' => $preview['summary']['errors'], 'file' => $session['filename']]);

        $message = sprintf('Import selesai: %d baru, %d diubah', $result['inserted'], $result['updated'])
            . ($preview['summary']['errors'] > 0 ? ", {$preview['summary']['errors']} baris error dilewati." : '.');

        return redirect()->to(url_to('attendance.recap') . '?month=' . substr((string) $from, 0, 7))
            ->with('success', $message)
            ->with('warning', $this->regenerateHint(array_column($rows, 'date'), 'perubahan kehadiran'));
    }

    public function cancel(): RedirectResponse
    {
        session()->remove(self::IMPORT_SESSION_KEY);

        return redirect()->route('attendance.import')->with('info', 'Import dibatalkan.');
    }

    // ------------------------------------------------------------------

    private function dateParam(string $key, string $default): string
    {
        $value = (string) $this->request->getGet($key);

        return AttendanceImporter::parseDate($value) === $value && $value !== '' ? $value : $default;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function monthParam(): array
    {
        $value = (string) $this->request->getGet('month');

        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) && (int) $m[1] >= 2000 && (int) $m[1] < 2100) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) date('Y'), (int) date('n')];
    }
}
