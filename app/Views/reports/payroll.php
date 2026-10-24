<?php
/**
 * @var array<string, mixed>|null $period
 * @var bool                      $isFinal
 * @var array<string, mixed>|null $recap  hasil ReportService::payrollRecap()
 */
$this->extend('layouts/app');
$this->section('content');

$recap ??= null;

/** Kolom angka yang tampil di layar: label → kunci (dijumlah bila lebih dari satu). */
$columns = [
    'Gaji Pokok'      => ['basic'],
    'Tunjangan'       => ['allowance', 'daily'],
    'Lembur & Bonus'  => ['overtime', 'other_earning'],
    'Pendapatan'      => ['gross'],
    'BPJS Karyawan'   => ['bpjs_employee'],
    'PPh 21'          => ['pph21'],
    'Potongan Lain'   => ['other_deduction'],
    'Take Home Pay'   => ['thp'],
    'BPJS Perusahaan' => ['bpjs_company'],
];
$sum = static fn (array $amounts, array $keys): int => array_sum(array_map(static fn (string $key): int => $amounts[$key], $keys));
?>
<?= $this->include('reports/_header') ?>

<?php if ($period === null): ?>
    <div class="card">
        <?= component('empty_state', ['title' => 'Belum ada payroll yang di-generate', 'description' => 'Rekap gaji tersedia setelah payroll suatu periode di-generate.', 'icon' => 'table-2']) ?>
    </div>
<?php else: ?>
    <?= $this->include('reports/_period_filter') ?>
    <?php $total = $recap['total']; ?>

    <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan rekap gaji">
        <?php foreach ([
            ['Total pendapatan', $total['gross'], 'Gaji pokok ' . rupiah($total['basic'])],
            ['Total potongan', $total['deduction'], 'BPJS ' . rupiah($total['bpjs_employee']) . ' · PPh ' . rupiah($total['pph21'])],
            ['Take home pay', $total['thp'], 'Rata-rata ' . rupiah(intdiv($total['thp'], max(1, $recap['employees']))) . '/orang'],
            ['Biaya perusahaan', $total['employer_cost'], 'Termasuk BPJS perusahaan ' . rupiah($total['bpjs_company'])],
        ] as $i => [$label, $value, $hint]): ?>
            <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                <p class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= rupiah($value) ?></p>
                <p class="mt-1 truncate text-xs text-slate-500"><?= esc($hint) ?></p>
            </div>
        <?php endforeach ?>
    </section>

    <section class="card overflow-hidden" aria-labelledby="recap-title">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 id="recap-title" class="text-base font-semibold text-slate-900">Rekap Gaji per Departemen</h2>
            <p class="mt-0.5 text-sm text-slate-500"><?= count($recap['groups']) ?> departemen · <?= $recap['employees'] ?> karyawan. Data karyawan sesuai snapshot saat payroll dihitung.</p>
        </div>

        <!-- Desktop -->
        <div class="hidden max-h-[75vh] overflow-auto md:block">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
                    <tr class="text-xs font-semibold tracking-wider whitespace-nowrap text-slate-500 uppercase">
                        <th scope="col" class="border-b border-slate-200 px-5 py-3 text-left">Karyawan</th>
                        <th scope="col" class="border-b border-slate-200 px-3 py-3 text-center">Hadir / HK</th>
                        <?php foreach (array_keys($columns) as $label): ?>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right"><?= esc($label) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <?php foreach ($recap['groups'] as $group): ?>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="bg-brand-50/60">
                            <th scope="rowgroup" colspan="<?= count($columns) + 2 ?>" class="px-5 py-2 text-left text-xs font-bold tracking-wider text-brand-800 uppercase">
                                <?= esc($group['department']) ?> <span class="font-semibold text-brand-600 normal-case">· <?= count($group['rows']) ?> karyawan</span>
                            </th>
                        </tr>
                        <?php foreach ($group['rows'] as $row): ?>
                            <tr class="transition-colors hover:bg-slate-50/80">
                                <td class="px-5 py-2.5">
                                    <a href="<?= url_to('payroll.employee', $period['id'], $row['employee_id']) ?>" class="block max-w-56 truncate font-semibold text-slate-900 hover:text-brand-700"><?= esc($row['name']) ?></a>
                                    <p class="max-w-56 truncate text-xs text-slate-500"><?= esc($row['nip']) ?> · <?= esc($row['position']) ?></p>
                                </td>
                                <td class="px-3 py-2.5 text-center whitespace-nowrap text-slate-700 tabular-nums">
                                    <?= $row['present_days'] ?> / <?= $row['workdays'] ?>
                                    <?php if ($row['prorated']): ?><span class="block text-[11px] font-semibold text-amber-700">prorata</span><?php endif ?>
                                </td>
                                <?php foreach ($columns as $label => $keys): ?>
                                    <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums <?= $keys === ['thp'] ? 'font-bold text-slate-900' : 'text-slate-700' ?>"><?= rupiah($sum($row['amounts'], $keys)) ?></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                        <tr class="bg-slate-50 font-semibold text-slate-900">
                            <th scope="row" colspan="2" class="px-5 py-2.5 text-left text-sm">Subtotal <?= esc($group['department']) ?></th>
                            <?php foreach ($columns as $keys): ?>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"><?= rupiah($sum($group['subtotal'], $keys)) ?></td>
                            <?php endforeach ?>
                        </tr>
                    </tbody>
                <?php endforeach ?>
                <tfoot class="sticky bottom-0 bg-brand-50/95 backdrop-blur">
                    <tr class="font-bold text-slate-900">
                        <th scope="row" colspan="2" class="border-t-2 border-brand-200 px-5 py-3 text-left text-sm">Total <?= $recap['employees'] ?> karyawan</th>
                        <?php foreach ($columns as $keys): ?>
                            <td class="border-t-2 border-brand-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($sum($total, $keys)) ?></td>
                        <?php endforeach ?>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Mobile -->
        <div class="md:hidden">
            <?php foreach ($recap['groups'] as $group): ?>
                <h3 class="flex items-center justify-between bg-brand-50/60 px-4 py-2 text-xs font-bold tracking-wider text-brand-800 uppercase">
                    <span><?= esc($group['department']) ?></span>
                    <span class="tabular-nums"><?= rupiah($group['subtotal']['thp']) ?></span>
                </h3>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($group['rows'] as $row): ?>
                        <li>
                            <a href="<?= url_to('payroll.employee', $period['id'], $row['employee_id']) ?>" class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-900"><?= esc($row['name']) ?></span>
                                    <span class="block truncate text-xs text-slate-500">Bruto <?= rupiah($row['amounts']['gross']) ?> · PPh <?= rupiah($row['amounts']['pph21']) ?></span>
                                </span>
                                <span class="shrink-0 text-sm font-bold text-slate-900 tabular-nums"><?= rupiah($row['amounts']['thp']) ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endforeach ?>
            <div class="flex items-center justify-between border-t-2 border-brand-200 bg-brand-50 px-4 py-3 text-sm font-bold text-slate-900">
                <span>Total take home pay</span>
                <span class="tabular-nums"><?= rupiah($total['thp']) ?></span>
            </div>
        </div>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
