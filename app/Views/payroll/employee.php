<?php
/**
 * @var array<string, mixed>                          $period
 * @var array<string, mixed>                          $payroll     snapshot & warnings ter-decode
 * @var array<string, list<array<string, mixed>>>     $items       tipe → payroll_items
 * @var list<array<string, mixed>>                    $adjustments
 * @var int                                           $ptkp        PTKP setahun
 * @var int|null                                      $prevId
 * @var int|null                                      $nextId
 * @var int|null                                      $position
 * @var int                                           $count
 * @var bool                                          $canAdjust
 */

use App\Models\PayrollPeriodModel;
use App\Models\SalaryComponentModel;
use App\Services\Payroll\PayrollItemPresenter;

$this->extend('layouts/app');
$this->section('content');

$s         = $payroll['snapshot'] ?? [];
$periodId  = (int) $period['id'];
$annual    = $payroll['ter_rate'] === null;
$taxItem   = null;
$taxLines  = [];
$errors    = session('errors') ?? [];
$employeeUrl = static fn (int $employeeId): string => url_to('payroll.employee', $periodId, $employeeId);

foreach ($items as $type => $group) {
    foreach ($group as $item) {
        if (in_array($item['code'], ['PPH21', 'PPH21_REFUND'], true)) {
            $taxItem = $item;
        } elseif ($item['taxable']) {
            $taxLines[] = $item + ['sign' => $type === 'deduction' ? -1 : 1];
        }
    }
}

$annualMeta = $annual && $taxItem !== null && ($taxItem['meta']['method'] ?? null) === 'annual' ? $taxItem['meta'] : null;
$sections   = [
    'earning'              => ['Pendapatan', 'emerald', 'trending-up', 'Total pendapatan'],
    'deduction'            => ['Potongan', 'rose', 'trending-down', 'Total potongan'],
    'company_contribution' => ['Kontribusi Perusahaan', 'sky', 'building-2', 'Total kontribusi (di luar gaji)'],
];

$actions = '<a href="' . url_to('payroll.show', $periodId) . '" class="btn btn-secondary">' . icon('arrow-left', 'size-4') . ' ' . esc($period['name']) . '</a>';
$actions .= '<a href="' . url_to('payslips.show', $payroll['id']) . '" class="btn btn-secondary">' . icon('receipt-text', 'size-4') . ' Slip Gaji</a>';
$actions .= '<div class="flex rounded-xl bg-white shadow-sm ring-1 ring-slate-300/80">'
    . ($prevId ? '<a href="' . esc($employeeUrl($prevId), 'attr') . '" class="rounded-l-xl px-3 py-2.5 text-slate-600 hover:bg-slate-50" aria-label="Karyawan sebelumnya">' . icon('chevron-left', 'size-4') . '</a>' : '<span class="px-3 py-2.5 text-slate-300" aria-hidden="true">' . icon('chevron-left', 'size-4') . '</span>')
    . '<span class="border-x border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 tabular-nums">' . (int) $position . ' / ' . $count . '</span>'
    . ($nextId ? '<a href="' . esc($employeeUrl($nextId), 'attr') . '" class="rounded-r-xl px-3 py-2.5 text-slate-600 hover:bg-slate-50" aria-label="Karyawan berikutnya">' . icon('chevron-right', 'size-4') . '</a>' : '<span class="px-3 py-2.5 text-slate-300" aria-hidden="true">' . icon('chevron-right', 'size-4') . '</span>')
    . '</div>';
?>
<?= component('page_header', [
    'eyebrow'  => 'Rincian Gaji · ' . $period['name'],
    'title'    => $s['full_name'] ?? 'Karyawan',
    'subtitle' => implode(' · ', array_filter([$s['nip'] ?? null, $s['position'] ?? null, $s['department'] ?? null, 'PTKP ' . ($s['ptkp_status'] ?? '—')])),
    'actions'  => $actions,
]) ?>

<?php if (! empty($payroll['warnings'])): ?>
    <div class="mb-6 flex gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
        <?= icon('triangle-alert', 'mt-0.5 size-5 shrink-0') ?>
        <div>
            <p class="font-semibold">Perlu diperiksa</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                <?php foreach ($payroll['warnings'] as $warning): ?>
                    <li><?= esc($warning) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>

<!-- Ringkasan -->
<section class="card mb-6 grid overflow-hidden lg:grid-cols-[1.2fr_2fr]">
    <div class="relative bg-linear-to-br from-brand-600 via-violet-600 to-fuchsia-600 p-6 text-white">
        <div class="flex items-center gap-3">
            <?= avatar($s['full_name'] ?? '', 'size-12 text-sm ring-white/40') ?>
            <div class="min-w-0">
                <p class="truncate font-semibold"><?= esc($s['full_name'] ?? '') ?></p>
                <p class="text-xs text-white/75"><?= esc(PayrollPeriodModel::STATUSES[$period['status']] ?? $period['status']) ?> · gajian <?= esc(tanggal_id($period['pay_date'])) ?></p>
            </div>
        </div>
        <p class="mt-6 text-sm text-white/80">Take home pay</p>
        <p class="mt-1 text-3xl font-bold tracking-tight tabular-nums sm:text-4xl"><?= rupiah($payroll['take_home_pay']) ?></p>
        <p class="mt-2 text-xs leading-relaxed text-white/80 italic"><?= esc(terbilang_rupiah(max(0, (int) $payroll['take_home_pay']))) ?></p>
    </div>
    <div class="flex flex-col">
        <dl class="grid flex-1 grid-cols-2 divide-slate-100 sm:grid-cols-4 sm:divide-x">
            <?php foreach ([
                ['Pendapatan', $payroll['total_earning'], 'text-emerald-700'],
                ['Potongan', $payroll['total_deduction'], 'text-rose-600'],
                ['PPh 21', $payroll['pph21'], 'text-slate-900'],
                ['BPJS perusahaan', $payroll['bpjs_company_total'], 'text-sky-700'],
            ] as [$label, $value, $class]): ?>
                <div class="flex flex-col justify-center p-5">
                    <dt class="text-xs font-medium text-slate-500"><?= esc($label) ?></dt>
                    <dd class="mt-1 text-lg font-bold tabular-nums <?= $class ?>"><?= rupiah($value) ?></dd>
                </div>
            <?php endforeach ?>
        </dl>
        <div class="flex flex-wrap gap-x-6 gap-y-2 border-t border-slate-100 px-5 py-4 text-xs text-slate-600">
            <span>Dasar upah BPJS <span class="font-semibold text-slate-900 tabular-nums"><?= rupiah($payroll['bpjs_base']) ?></span></span>
            <span>Bruto PPh 21 <span class="font-semibold text-slate-900 tabular-nums"><?= rupiah($payroll['bruto_pph']) ?></span></span>
            <span>Bank <span class="font-semibold text-slate-900"><?= esc(($s['bank_name'] ?? '—') . ' ' . mask_id($s['bank_account_no'] ?? '', 3, 4)) ?></span></span>
            <?php if (! empty($s['prorated'])): ?>
                <span class="font-semibold text-amber-700">Prorata <?= (int) $payroll['active_workdays'] ?>/<?= (int) $payroll['workdays'] ?> hari kerja</span>
            <?php endif ?>
        </div>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-3">
    <!-- Komponen -->
    <div class="space-y-6 xl:col-span-2">
        <?php foreach ($sections as $type => [$title, $tone, $iconName, $totalLabel]): ?>
            <?php
            $group = $items[$type] ?? [];
            $total = array_sum(array_column($group, 'amount'));
            ?>
            <section class="card overflow-hidden" aria-labelledby="section-<?= $type ?>">
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                    <span class="flex size-9 items-center justify-center rounded-lg <?= tone_classes($tone) ?> ring-1 ring-inset"><?= icon($iconName, 'size-4') ?></span>
                    <h2 id="section-<?= $type ?>" class="text-base font-semibold text-slate-900"><?= esc($title) ?></h2>
                    <?php if ($type === 'company_contribution'): ?>
                        <span class="ml-auto text-xs text-slate-500">Dibayar perusahaan, tidak mengurangi gaji</span>
                    <?php endif ?>
                </div>
                <?php if ($group === []): ?>
                    <p class="px-5 py-6 text-center text-sm text-slate-500">Tidak ada komponen.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($group as $item): ?>
                            <?php $description = PayrollItemPresenter::describe($item); ?>
                            <li class="flex items-start justify-between gap-4 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900">
                                        <?= esc($item['name']) ?>
                                        <?php if ($item['taxable'] && $type !== 'deduction'): ?>
                                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600" title="Menambah bruto PPh 21">Objek PPh</span>
                                        <?php endif ?>
                                    </p>
                                    <?php if ($description): ?>
                                        <p class="mt-0.5 text-xs text-slate-500"><?= esc($description) ?></p>
                                    <?php endif ?>
                                </div>
                                <span class="shrink-0 text-sm font-semibold whitespace-nowrap text-slate-900 tabular-nums"><?= rupiah($item['amount']) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
                <div class="flex items-center justify-between bg-slate-50/80 px-5 py-3 text-sm font-semibold">
                    <span class="text-slate-600"><?= esc($totalLabel) ?></span>
                    <span class="text-slate-900 tabular-nums"><?= rupiah($total) ?></span>
                </div>
            </section>
        <?php endforeach ?>

        <!-- Penyesuaian manual -->
        <section class="card overflow-hidden" id="penyesuaian" aria-labelledby="adjust-title">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 id="adjust-title" class="text-base font-semibold text-slate-900">Penyesuaian Manual</h2>
                <p class="mt-0.5 text-sm text-slate-500">
                    <?= $canAdjust ? 'Bonus, THR, insentif, atau koreksi untuk periode ini. Setelah disimpan, payroll seluruh periode di-generate ulang otomatis.' : 'Penyesuaian hanya dapat diubah saat periode berstatus Draft.' ?>
                </p>
            </div>
            <?php if ($adjustments !== []): ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($adjustments as $adjustment): ?>
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg <?= $adjustment['type'] === 'earning' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>"><?= icon($adjustment['type'] === 'earning' ? 'plus' : 'minus', 'size-4') ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900"><?= esc($adjustment['name']) ?></p>
                                <p class="truncate text-xs text-slate-500">
                                    <?= $adjustment['taxable'] ? 'Kena pajak' : 'Tidak kena pajak' ?>
                                    <?= $adjustment['note'] ? ' · ' . esc($adjustment['note']) : '' ?>
                                    · <?= esc($adjustment['creator_name'] ?? '—') ?>, <?= esc(tanggal_id($adjustment['created_at'])) ?>
                                </p>
                            </div>
                            <span class="text-sm font-bold whitespace-nowrap tabular-nums <?= $adjustment['type'] === 'earning' ? 'text-emerald-700' : 'text-rose-600' ?>"><?= $adjustment['type'] === 'earning' ? '+' : '−' ?><?= rupiah($adjustment['amount']) ?></span>
                            <?php if ($canAdjust): ?>
                                <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus penyesuaian <?= esc($adjustment['name'], 'attr') ?>"
                                        @click="$dispatch('confirm', { title: 'Hapus penyesuaian?', message: '<?= esc($adjustment['name'] . ' ' . rupiah($adjustment['amount']) . ' — payroll periode akan di-generate ulang.', 'js') ?>', action: '<?= esc(url_to('payroll.adjustments.delete', $periodId, $adjustment['id']), 'js') ?>', confirmLabel: 'Hapus', tone: 'danger' })">
                                    <?= icon('trash-2', 'size-4') ?>
                                </button>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php elseif (! $canAdjust): ?>
                <p class="px-5 py-6 text-center text-sm text-slate-500">Tidak ada penyesuaian.</p>
            <?php endif ?>

            <?php if ($canAdjust): ?>
                <form method="post" action="<?= url_to('payroll.adjustments.store', $periodId, $payroll['employee_id']) ?>" novalidate
                      x-data="{ type: '<?= esc((string) old('type', 'earning'), 'js') ?>', submitting: false }" @submit="submitting = true"
                      class="space-y-4 border-t border-slate-100 bg-slate-50/60 px-5 py-5">
                    <?= csrf_field() ?>
                    <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                        <div>
                            <?= component('form_input', ['name' => 'name', 'label' => 'Nama penyesuaian', 'required' => true, 'placeholder' => 'mis. Bonus Kinerja Q3', 'attrs' => ['id' => 'adj-name', 'list' => 'adj-presets', 'maxlength' => 100]]) ?>
                            <datalist id="adj-presets">
                                <?php foreach (['Bonus Kinerja', 'Tunjangan Hari Raya (THR)', 'Insentif Penjualan', 'Koreksi Gaji Bulan Lalu', 'Potongan Koperasi', 'Potongan Kerusakan Inventaris'] as $preset): ?>
                                    <option value="<?= esc($preset, 'attr') ?>"></option>
                                <?php endforeach ?>
                            </datalist>
                        </div>
                        <fieldset>
                            <legend class="form-label">Jenis</legend>
                            <div class="inline-flex rounded-xl bg-white p-1 ring-1 ring-slate-300">
                                <?php foreach (['earning' => 'Pendapatan', 'deduction' => 'Potongan'] as $value => $label): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="type" value="<?= $value ?>" x-model="type" class="peer sr-only">
                                        <span class="block rounded-lg px-3 py-1.5 text-sm font-semibold transition peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500"
                                              :class="type === '<?= $value ?>' ? '<?= $value === 'earning' ? 'bg-emerald-700' : 'bg-rose-600' ?> text-white' : 'text-slate-600 hover:bg-slate-100'"><?= $label ?></span>
                                    </label>
                                <?php endforeach ?>
                            </div>
                        </fieldset>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= component('form_money', ['name' => 'amount', 'label' => 'Nominal', 'required' => true, 'id' => 'adj-amount']) ?>
                        <?= component('form_input', ['name' => 'note', 'label' => 'Catatan', 'placeholder' => 'mis. Pencapaian target 112%', 'attrs' => ['id' => 'adj-note', 'maxlength' => 255]]) ?>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="flex items-start gap-2.5 text-sm text-slate-700">
                            <input type="checkbox" name="taxable" value="1" <?= old('taxable', session('errors') ? null : '1') ? 'checked' : '' ?> class="mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span><span class="font-semibold">Objek PPh 21</span><span class="block text-xs text-slate-500" x-text="type === 'earning' ? 'Menambah bruto PPh 21 (bonus, THR, insentif).' : 'Mengurangi bruto PPh 21 (koreksi upah).'"></span></span>
                        </label>
                        <button type="submit" class="btn btn-primary" :disabled="submitting">
                            <?= icon('refresh-cw', 'size-4') ?> Simpan & Generate Ulang
                        </button>
                    </div>
                </form>
            <?php endif ?>
        </section>
    </div>

    <!-- Kehadiran & PPh 21 -->
    <div class="space-y-6">
        <section class="card p-5" aria-labelledby="attendance-title">
            <h2 id="attendance-title" class="text-base font-semibold text-slate-900">Kehadiran Periode</h2>
            <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                <?php foreach ([
                    ['Hari kerja', $payroll['workdays']], ['Aktif', $payroll['active_workdays']], ['Hadir', $payroll['present_days']],
                    ['Terlambat', $payroll['late_days']], ['Sakit', $payroll['sick_days']], ['Cuti', $payroll['leave_days']],
                    ['Izin', $payroll['permit_days']], ['Alpha', $payroll['absent_days']], ['Lembur', rtrim(rtrim(number_id($payroll['overtime_hours'], 2), '0'), ',') . ' j'],
                ] as [$label, $value]): ?>
                    <div class="rounded-lg bg-slate-50 py-2.5 ring-1 ring-slate-100">
                        <dt class="text-[11px] text-slate-500"><?= esc($label) ?></dt>
                        <dd class="text-base font-bold text-slate-900 tabular-nums <?= $label === 'Alpha' && (int) $value > 0 ? 'text-rose-600' : '' ?>"><?= esc((string) $value) ?></dd>
                    </div>
                <?php endforeach ?>
            </dl>
        </section>

        <section class="card overflow-hidden" aria-labelledby="pph-title">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 id="pph-title" class="text-base font-semibold text-slate-900">Cara Hitung PPh 21</h2>
                <p class="mt-0.5 text-xs text-slate-500"><?= $annual ? 'Masa pajak terakhir: dihitung ulang setahun (Pasal 17).' : 'Tarif Efektif Rata-rata (TER) bulanan — PP 58/2023.' ?></p>
            </div>

            <?php if (! $annual): ?>
                <dl class="divide-y divide-slate-100 text-sm">
                    <?php foreach ($taxLines as $line): ?>
                        <div class="flex justify-between gap-3 px-5 py-2">
                            <dt class="min-w-0 truncate text-slate-600"><?= $line['sign'] < 0 ? '− ' : '+ ' ?><?= esc($line['name']) ?></dt>
                            <dd class="whitespace-nowrap text-slate-800 tabular-nums"><?= rupiah($line['amount']) ?></dd>
                        </div>
                    <?php endforeach ?>
                    <div class="flex justify-between gap-3 bg-slate-50 px-5 py-2.5 font-semibold">
                        <dt class="text-slate-700">Bruto PPh 21</dt>
                        <dd class="text-slate-900 tabular-nums"><?= rupiah($payroll['bruto_pph']) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-2.5">
                        <dt class="text-slate-600">Status PTKP → kategori</dt>
                        <dd class="font-semibold text-slate-900"><?= esc($s['ptkp_status'] ?? '—') ?> → TER <?= esc($payroll['ter_category']) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3 px-5 py-2.5">
                        <dt class="text-slate-600">Tarif TER untuk bruto ini</dt>
                        <dd class="font-semibold text-slate-900"><?= esc(percent_id($payroll['ter_rate'])) ?></dd>
                    </div>
                </dl>
                <div class="bg-linear-to-r from-brand-50 to-violet-50 px-5 py-4">
                    <p class="text-xs text-brand-800"><?= rupiah($payroll['bruto_pph']) ?> × <?= esc(percent_id($payroll['ter_rate'])) ?></p>
                    <p class="mt-0.5 flex items-baseline justify-between text-sm font-semibold text-brand-950">PPh 21 bulan ini <span class="text-lg font-bold tabular-nums"><?= rupiah($payroll['pph21']) ?></span></p>
                </div>
            <?php elseif ($annualMeta !== null): ?>
                <dl class="divide-y divide-slate-100 text-sm">
                    <?php foreach ([
                        ['Bruto setahun (' . (int) $annualMeta['months'] . ' bulan)', $annualMeta['gross_year'], ''],
                        ['Biaya jabatan', $annualMeta['position_cost'], '− '],
                        ['Iuran pensiun (JHT + JP karyawan)', $annualMeta['pension_year'], '− '],
                        ['Penghasilan neto', $annualMeta['net_income'], '= '],
                        ['PTKP ' . ($s['ptkp_status'] ?? ''), $annualMeta['ptkp'], '− '],
                        ['PKP (dibulatkan ribuan)', $annualMeta['taxable_income'], '= '],
                        ['PPh 21 setahun (Pasal 17)', $annualMeta['pph21_annual'], ''],
                        ['Sudah dipotong Jan s.d. bulan lalu', $annualMeta['pph21_paid'], '− '],
                    ] as [$label, $value, $sign]): ?>
                        <div class="flex justify-between gap-3 px-5 py-2">
                            <dt class="min-w-0 text-slate-600"><?= esc($sign . $label) ?></dt>
                            <dd class="whitespace-nowrap text-slate-800 tabular-nums"><?= rupiah($value) ?></dd>
                        </div>
                    <?php endforeach ?>
                </dl>
                <div class="bg-linear-to-r from-brand-50 to-violet-50 px-5 py-4">
                    <p class="flex items-baseline justify-between text-sm font-semibold text-brand-950">
                        <?= $annualMeta['pph21'] < 0 ? 'Lebih bayar (dikembalikan)' : 'PPh 21 masa ini' ?>
                        <span class="text-lg font-bold tabular-nums"><?= rupiah(abs((int) $annualMeta['pph21'])) ?></span>
                    </p>
                </div>
            <?php else: ?>
                <p class="px-5 py-4 text-sm text-slate-600">PPh 21 setahun sama dengan yang sudah dipotong pada bulan-bulan sebelumnya, sehingga tidak ada pemotongan masa ini. Bruto bulan ini <?= rupiah($payroll['bruto_pph']) ?>; PTKP setahun <?= rupiah($ptkp) ?>.</p>
            <?php endif ?>
        </section>

        <p class="px-1 text-xs leading-relaxed text-slate-500">
            <?= icon('info', 'mr-1 inline size-3.5 align-[-2px]') ?>
            Data jabatan, PTKP, dan rekening diambil dari snapshot saat payroll dihitung. <?= esc(SalaryComponentModel::TYPES['company_contribution']) ?> tidak dipotong dari gaji, tetapi BPJS Kesehatan, JKK, dan JKM perusahaan menambah bruto PPh 21.
        </p>
    </div>
</div>
<?php $this->endSection() ?>
