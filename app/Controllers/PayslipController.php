<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PayrollPeriodModel;
use App\Services\Payslip\PayslipService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Slip gaji (web & PDF) dan unduhan massal per periode.
 * HR, Finance, Super Admin: semua slip. Karyawan: slip miliknya pada periode dibayar/terkunci (selain itu 403).
 */
class PayslipController extends BaseController
{
    public function show(int $id): ResponseInterface|string
    {
        $slip = $this->slip($id);

        if ($slip === null || ! PayslipService::canView($this->currentUser(), $slip)) {
            return $this->forbidden();
        }

        $own  = $this->isOwn($slip);
        $name = $slip['employee']['full_name'] ?? 'Karyawan';

        return view('payslips/show', $slip + [
            'title'       => 'Slip Gaji ' . $slip['period']['name'],
            'breadcrumbs' => $own && ! $this->auth->hasRole('hr', 'finance')
                ? [['label' => 'Slip Gaji Saya', 'url' => url_to('my.payslips')], ['label' => $slip['period']['name']]]
                : [['label' => $slip['period']['name'], 'url' => url_to('payroll.show', $slip['period']['id'])], ['label' => $name, 'url' => url_to('payroll.employee', $slip['period']['id'], $slip['payroll']['employee_id'])], ['label' => 'Slip Gaji']],
            'backUrl'     => $own && ! $this->auth->hasRole('hr', 'finance')
                ? url_to('my.payslips')
                : url_to('payroll.employee', $slip['period']['id'], $slip['payroll']['employee_id']),
        ]);
    }

    public function pdf(int $id): ResponseInterface
    {
        $slip = $this->slip($id);

        if ($slip === null || ! PayslipService::canView($this->currentUser(), $slip)) {
            return $this->forbidden();
        }

        $pdf      = PayslipService::make()->pdf($slip);
        $filename = PayslipService::filename($slip);

        $this->audit('payslip.download', 'payroll', $id, sprintf(
            'Mengunduh slip gaji %s %s',
            $slip['employee']['full_name'] ?? '',
            $slip['period']['name'],
        ));

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', ($this->request->getGet('inline') ? 'inline' : 'attachment') . '; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($pdf);
    }

    /**
     * ZIP seluruh slip satu periode (HR, Finance, Super Admin).
     */
    public function zip(int $periodId): ResponseInterface
    {
        $period = (new PayrollPeriodModel())->find($periodId) ?? throw PageNotFoundException::forPageNotFound();
        $back   = url_to('payroll.show', $periodId);

        // Dompdf ±0,2 dtk per slip; beri ruang untuk periode dengan ratusan karyawan
        set_time_limit(max(60, (int) ini_get('max_execution_time')));

        $service = PayslipService::make();

        try {
            $slips = $service->forPeriod($periodId);
            $zip   = $service->zip($slips);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->audit('payslip.download_zip', 'payroll_period', $periodId, sprintf('Mengunduh %d slip gaji %s (ZIP)', count($slips), $period['name']));

        return $this->response
            ->setHeader('Content-Type', 'application/zip')
            ->setHeader('Content-Disposition', sprintf('attachment; filename="Slip-Gaji-%04d-%02d.zip"', $period['year'], $period['month']))
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($zip);
    }

    // ------------------------------------------------------------------

    /**
     * Slip tidak ditemukan: 404 untuk HR/Finance, 403 untuk karyawan (agar ID slip orang lain tidak bisa ditebak).
     *
     * @return array<string, mixed>|null
     */
    private function slip(int $id): ?array
    {
        $slip = PayslipService::make()->find($id);

        if ($slip === null && $this->auth->hasRole('hr', 'finance')) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $slip;
    }

    /**
     * @param array<string, mixed> $slip
     */
    private function isOwn(array $slip): bool
    {
        $user = $this->currentUser();

        return ! empty($user['employee_id']) && (int) $user['employee_id'] === (int) $slip['payroll']['employee_id'];
    }
}
