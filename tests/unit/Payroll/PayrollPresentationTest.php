<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Services\Export\BankTransferExporter;
use App\Services\Payroll\PayrollItemPresenter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Keterangan dasar hitung baris slip & format file transfer bank.
 *
 * @internal
 */
final class PayrollPresentationTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['format', 'ui']);
    }

    public function testDescribesItemsFromMeta(): void
    {
        $this->assertSame('22 hari × Rp 30.000', PayrollItemPresenter::describe(['code' => 'TRANSPORT', 'meta' => ['qty' => 22, 'rate' => 30_000, 'unit' => 'hari']]));
        $this->assertSame('4% × Rp 9.000.000', PayrollItemPresenter::describe(['code' => 'BPJS_KES_ER', 'meta' => ['basis' => 9_000_000, 'rate' => 4.0]]));
        $this->assertSame('Prorata 14/19 hari kerja dari Rp 7.000.000', PayrollItemPresenter::describe(['code' => 'BASIC', 'meta' => ['monthly' => 7_000_000, 'prorated' => true, 'active_workdays' => 14, 'period_workdays' => 19]]));
        $this->assertSame('TER A 2,5% × bruto Rp 10.608.600', PayrollItemPresenter::describe(['code' => 'PPH21', 'meta' => ['method' => 'ter', 'basis' => 10_608_600, 'category' => 'A', 'rate' => 2.5]]));
        $this->assertSame('Perhitungan setahun (masa pajak terakhir)', PayrollItemPresenter::describe(['code' => 'PPH21_REFUND', 'meta' => ['method' => 'annual']]));
        $this->assertSame('3,5 jam dalam 2 hari · pengali 6,5× · upah sejam Rp 52.023', PayrollItemPresenter::describe(['code' => 'OVERTIME', 'meta' => ['hours' => 3.5, 'days' => 2, 'multiplier' => 6.5, 'hourly_rate' => 52_023]]));
        $this->assertNull(PayrollItemPresenter::describe(['code' => 'LOAN', 'meta' => ['loan_id' => 2, 'loan_no' => 'PJM-2026-0002']]));
        $this->assertSame('Pencapaian target 112%', PayrollItemPresenter::describe(['code' => 'ADJ', 'meta' => ['note' => 'Pencapaian target 112%']]));
        $this->assertNull(PayrollItemPresenter::describe(['code' => 'POS_ALW', 'meta' => null]));
    }

    public function testBankSummaryAndCsv(): void
    {
        $recipients = [
            ['nip' => 'EMP-0001', 'name' => 'Hendra Wijaya', 'bank' => 'BCA', 'account_no' => '1234567890', 'account_name' => 'HENDRA WIJAYA', 'amount' => 44_000_000],
            ['nip' => 'EMP-0009', 'name' => 'Fajar Nugroho', 'bank' => 'BRI', 'account_no' => '123456789012345', 'account_name' => '', 'amount' => 6_100_000],
            ['nip' => 'EMP-0014', 'name' => 'Andi Pratama', 'bank' => 'BCA', 'account_no' => '9050029693', 'account_name' => 'ANDI PRATAMA', 'amount' => 14_000_000],
            ['nip' => 'EMP-0032', 'name' => 'Baru Masuk', 'bank' => '', 'account_no' => '', 'account_name' => '', 'amount' => 5_000_000],
        ];

        $summary = BankTransferExporter::summary($recipients);
        $this->assertSame(['BCA' => ['count' => 2, 'total' => 58_000_000], 'BRI' => ['count' => 1, 'total' => 6_100_000]], $summary['banks']);
        $this->assertSame(['EMP-0032'], array_column($summary['missing'], 'nip'));

        $period = ['year' => 2026, 'month' => 10];
        $this->assertSame('Gaji Okt 2026', BankTransferExporter::remark($period));

        $bri = explode("\n", trim(BankTransferExporter::csv($recipients, $period, 'BRI')));
        $this->assertSame(['no,nip,nama_penerima,no_rekening,bank,nominal,berita', '1,EMP-0009,"FAJAR NUGROHO",123456789012345,BRI,6100000,"Gaji Okt 2026"'], $bri);

        $all = explode("\n", trim(BankTransferExporter::csv($recipients, $period)));
        $this->assertCount(4, $all); // header + 3 penerima ber-rekening
    }
}
