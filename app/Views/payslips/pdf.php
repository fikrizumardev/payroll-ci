<?php
/**
 * Slip gaji untuk Dompdf (A4). CSS biasa (tanpa Tailwind, flex, maupun grid) karena
 * Dompdf hanya mendukung CSS 2.1 + sebagian CSS3; tata letak memakai tabel.
 *
 * @var array<string, mixed>                       $payroll
 * @var array<string, mixed>                       $period
 * @var array<string, mixed>                       $employee
 * @var array<string, list<array<string, mixed>>>  $items
 * @var array<string, string|null>                 $company
 * @var bool                                       $final
 * @var string                                     $number
 */

use App\Models\EmployeeModel;
use App\Services\Payroll\PayrollItemPresenter;

$hours    = static fn (float|int|string $h): string => rtrim(rtrim(number_id((float) $h, 2), '0'), ',');
$earnings = $items['earning'] ?? [];
$deducts  = $items['deduction'] ?? [];
$rowCount = max(count($earnings), count($deducts), 1);

$cell = static function (?array $item): string {
    if ($item === null) {
        return '<td class="item">&nbsp;</td><td class="amount">&nbsp;</td>';
    }

    $description = PayrollItemPresenter::describe($item);

    return '<td class="item">' . esc($item['name']) . ($description ? '<div class="desc">' . esc($description) . '</div>' : '') . '</td>'
        . '<td class="amount">' . rupiah($item['amount']) . '</td>';
};

$info = [
    ['Nama', $employee['full_name'] ?? '—', 'Tanggal bayar', tanggal_id($period['pay_date'], 'long')],
    ['NIP', $employee['nip'] ?? '—', 'Kehadiran', $payroll['present_days'] . ' / ' . $payroll['active_workdays'] . ' hari kerja' . ($payroll['active_workdays'] < $payroll['workdays'] ? ' (prorata)' : '')],
    ['Jabatan', $employee['position'] ?? '—', 'Lembur', $payroll['overtime_hours'] > 0 ? $hours($payroll['overtime_hours']) . ' jam' : '—'],
    ['Departemen', $employee['department'] ?? '—', 'Bank', $employee['bank_name'] ?? '—'],
    ['Status kerja', EmployeeModel::EMPLOYMENT_TYPES[$employee['employment_type'] ?? ''] ?? '—', 'No. rekening', mask_id($employee['bank_account_no'] ?? '', 3, 4)],
    ['Status PTKP', $employee['ptkp_status'] ?? '—', 'NPWP', ($employee['npwp'] ?? '') !== '' ? mask_id($employee['npwp']) : '—'],
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Slip Gaji <?= esc(($employee['full_name'] ?? '') . ' ' . $period['name']) ?></title>
<style>
    @page { margin: 14mm 14mm 16mm; }
    * { font-family: Helvetica, Arial, sans-serif; }
    body { margin: 0; color: #0f172a; font-size: 8.6pt; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; padding: 0; }
    .band { height: 5px; background: #4f46e5; margin-bottom: 14px; }
    .muted { color: #64748b; }
    .small { font-size: 7.4pt; }
    .company-name { font-size: 13pt; font-weight: bold; }
    .doc-label { font-size: 7.5pt; font-weight: bold; letter-spacing: 2px; color: #4f46e5; text-transform: uppercase; }
    .doc-period { font-size: 15pt; font-weight: bold; margin-top: 2px; }
    .mono { font-family: Courier, monospace; font-size: 7.6pt; }
    .badge { display: inline-block; margin-top: 4px; padding: 1px 7px; border-radius: 8px; background: #f1f5f9; color: #475569; font-size: 7pt; font-weight: bold; }
    .rule { border-bottom: 1px solid #e2e8f0; }
    .info td { padding: 3px 0; border-bottom: 1px solid #f1f5f9; }
    .info .label { color: #64748b; width: 18%; }
    .info .value { font-weight: bold; width: 30%; }
    .info .gap { width: 4%; border-bottom: none; }
    .section-title { font-size: 7.6pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1.5px solid #0f172a; padding-bottom: 3px; }
    .lines td.item { padding: 4px 6px 4px 0; border-bottom: 1px solid #f1f5f9; width: 33%; }
    .lines td.amount { padding: 4px 0; border-bottom: 1px solid #f1f5f9; text-align: right; white-space: nowrap; width: 15%; }
    .lines td.spacer { width: 4%; border-bottom: none; }
    .desc { color: #64748b; font-size: 6.8pt; }
    .total td { padding-top: 5px; border-top: 1px solid #94a3b8; font-weight: bold; }
    .earning { color: #047857; }
    .deduction { color: #e11d48; }
    .thp { margin-top: 14px; background: #4f46e5; color: #ffffff; border-radius: 10px; }
    .thp td { padding: 11px 14px; vertical-align: middle; }
    .thp .label { font-size: 7.5pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
    .thp .words { font-size: 7.4pt; font-style: italic; color: #e0e7ff; margin-top: 2px; }
    .thp .amount { font-size: 17pt; font-weight: bold; text-align: right; white-space: nowrap; }
    .facts td { padding: 2px 0; }
    .facts td.num { text-align: right; white-space: nowrap; }
    .footer { margin-top: 18px; padding-top: 7px; border-top: 1px dashed #cbd5e1; color: #64748b; font-size: 7pt; }
    .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 90pt; font-weight: bold; color: #f1f5f9; transform: rotate(-30deg); z-index: -1; }
</style>
</head>
<body>
<?php if (! $final): ?>
    <div class="watermark">DRAFT</div>
<?php endif ?>

<div class="band"></div>

<table>
    <tr>
        <td style="width: 58%;">
            <table>
                <tr>
                    <td style="width: 46px;"><img src="<?= esc($company['logo'], 'attr') ?>" width="38" height="38" alt=""></td>
                    <td>
                        <div class="company-name"><?= esc($company['name']) ?></div>
                        <div class="muted small"><?= esc($company['address']) ?></div>
                        <div class="muted small"><?= esc(implode(' · ', array_filter([$company['phone'], $company['website'], $company['npwp'] ? 'NPWP ' . $company['npwp'] : null]))) ?></div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="text-align: right;">
            <div class="doc-label">Slip Gaji</div>
            <div class="doc-period"><?= esc($period['name']) ?></div>
            <div class="mono muted"><?= esc($number) ?></div>
            <div class="badge">RAHASIA<?= $final ? '' : ' · DRAFT' ?></div>
        </td>
    </tr>
</table>

<div class="rule" style="margin: 12px 0 8px;"></div>

<table class="info">
    <?php foreach ($info as [$l1, $v1, $l2, $v2]): ?>
        <tr>
            <td class="label"><?= esc($l1) ?></td>
            <td class="value"><?= esc((string) $v1) ?></td>
            <td class="gap"></td>
            <td class="label"><?= esc($l2) ?></td>
            <td class="value" style="text-align: right;"><?= esc((string) $v2) ?></td>
        </tr>
    <?php endforeach ?>
</table>

<table class="lines" style="margin-top: 14px;">
    <tr>
        <td colspan="2" class="section-title">Pendapatan</td>
        <td class="spacer"></td>
        <td colspan="2" class="section-title">Potongan</td>
    </tr>
    <?php for ($i = 0; $i < $rowCount; $i++): ?>
        <tr>
            <?= $cell($earnings[$i] ?? null) ?>
            <td class="spacer"></td>
            <?= $cell($deducts[$i] ?? null) ?>
        </tr>
    <?php endfor ?>
    <tr class="total">
        <td>Total pendapatan</td>
        <td style="text-align: right;" class="earning"><?= rupiah($payroll['total_earning']) ?></td>
        <td style="border-top: none;"></td>
        <td>Total potongan</td>
        <td style="text-align: right;" class="deduction"><?= rupiah($payroll['total_deduction']) ?></td>
    </tr>
</table>

<table class="thp">
    <tr>
        <td>
            <div class="label">Take Home Pay</div>
            <div class="words"><?= esc(terbilang_rupiah(max(0, (int) $payroll['take_home_pay']))) ?></div>
        </td>
        <td class="amount"><?= rupiah($payroll['take_home_pay']) ?></td>
    </tr>
</table>

<table style="margin-top: 14px;">
    <tr>
        <td style="width: 48%;">
            <div class="section-title" style="border-bottom-width: 1px; color: #64748b;">Informasi PPh 21</div>
            <table class="facts" style="margin-top: 3px;">
                <tr><td class="muted">Penghasilan bruto</td><td class="num"><?= rupiah($payroll['bruto_pph']) ?></td></tr>
                <tr><td class="muted">Metode</td><td class="num"><?= $payroll['ter_rate'] === null ? 'Perhitungan setahun' : 'TER ' . esc($payroll['ter_category']) . ' · ' . esc(percent_id($payroll['ter_rate'])) ?></td></tr>
                <tr><td class="muted">PPh 21 dipotong</td><td class="num"><?= rupiah($payroll['pph21']) ?></td></tr>
            </table>
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 48%;">
            <div class="section-title" style="border-bottom-width: 1px; color: #64748b;">Dibayar perusahaan (tidak dipotong)</div>
            <table class="facts" style="margin-top: 3px;">
                <?php foreach ($items['company_contribution'] ?? [] as $item): ?>
                    <tr><td class="muted"><?= esc(str_replace(' (Perusahaan)', '', $item['name'])) ?></td><td class="num"><?= rupiah($item['amount']) ?></td></tr>
                <?php endforeach ?>
            </table>
        </td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td>Dokumen ini diterbitkan secara elektronik oleh <?= esc($company['name']) ?> dan sah tanpa tanda tangan.</td>
        <td style="text-align: right; white-space: nowrap;"><?= esc(($company['city'] ? $company['city'] . ', ' : '') . tanggal_id($period['pay_date'], 'long')) ?></td>
    </tr>
</table>
</body>
</html>
