<?php
/**
 * @var array<string, mixed>|null $period
 * @var bool                      $isFinal
 * @var array<string, mixed>|null $bpjs hasil ReportService::bpjs()
 */
$this->extend('layouts/app');
$this->section('content');

$bpjs ??= null;

// Warna mengikuti peran porsi (sama dengan grafik dashboard): karyawan = oranye, perusahaan = aqua
$companyColor  = '#1baf7a';
$employeeColor = '#eb6834';
?>
<?= $this->include('reports/_header') ?>

<?php if ($period === null): ?>
    <div class="card">
        <?= component('empty_state', ['title' => 'Belum ada payroll yang di-generate', 'description' => 'Laporan BPJS tersedia setelah payroll suatu periode di-generate.', 'icon' => 'shield-plus']) ?>
    </div>
<?php else: ?>
    <?= $this->include('reports/_period_filter') ?>
    <?php
    $total    = $bpjs['total'];
    $agencies = [];

    foreach ($bpjs['programs'] as $program) {
        $agencies[$program['agency']] ??= ['company' => 0, 'employee' => 0];
        $agencies[$program['agency']]['company'] += $program['company'];
        $agencies[$program['agency']]['employee'] += $program['employee'];
    }
    ?>

    <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan iuran BPJS">
        <?php foreach ([
            ['Total iuran', $total['total'], count($bpjs['rows']) . ' peserta · dipotong dari gaji ' . rupiah($total['employee'])],
            ['Porsi perusahaan', $total['company'], 'Beban perusahaan di luar gaji'],
            ...array_map(static fn (string $agency, array $sum): array => [$agency, $sum['company'] + $sum['employee'], 'Perusahaan ' . rupiah($sum['company']) . ' · karyawan ' . rupiah($sum['employee'])], array_keys($agencies), $agencies),
        ] as $i => [$label, $value, $hint]): ?>
            <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                <p class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= rupiah($value) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= esc($hint) ?></p>
            </div>
        <?php endforeach ?>
    </section>

    <!-- Per program -->
    <section class="card mb-6 overflow-hidden" aria-labelledby="program-title">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="program-title" class="text-base font-semibold text-slate-900">Iuran per Program</h2>
                <p class="mt-0.5 text-sm text-slate-500">Tarif & dasar upah mengikuti perhitungan saat payroll di-generate (batas upah Kesehatan & JP diterapkan).</p>
            </div>
            <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-600" aria-label="Legenda">
                <li class="flex items-center gap-2"><span class="h-2.5 w-3 rounded-[3px]" style="background: <?= $companyColor ?>" aria-hidden="true"></span>Porsi perusahaan</li>
                <li class="flex items-center gap-2"><span class="h-2.5 w-3 rounded-[3px]" style="background: <?= $employeeColor ?>" aria-hidden="true"></span>Porsi karyawan</li>
            </ul>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50/95">
                    <tr class="text-xs font-semibold tracking-wider whitespace-nowrap text-slate-500 uppercase">
                        <th scope="col" class="border-b border-slate-200 px-5 py-3 text-left">Program</th>
                        <th scope="col" class="border-b border-slate-200 px-3 py-3 text-center">Tarif P / K</th>
                        <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Peserta</th>
                        <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Perusahaan</th>
                        <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Karyawan</th>
                        <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Total</th>
                        <th scope="col" class="w-1/4 min-w-40 border-b border-slate-200 px-5 py-3 text-left">Komposisi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($bpjs['programs'] as $program): ?>
                        <?php
                        $share = $program['total'] > 0 ? $program['company'] / $program['total'] * 100 : 0;
                        $rate  = static fn (?float $value, bool $exists): string => ! $exists ? '—' : ($value === null ? 'beragam' : percent_id($value));
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <th scope="row" class="px-5 py-3 text-left font-normal">
                                <span class="block font-semibold text-slate-900"><?= esc($program['label']) ?></span>
                                <span class="text-xs text-slate-500"><?= esc($program['agency']) ?></span>
                            </th>
                            <td class="px-3 py-3 text-center whitespace-nowrap text-slate-700 tabular-nums"><?= esc($rate($program['er_rate'], $program['er'] !== null)) ?> / <?= esc($rate($program['ee_rate'], $program['ee'] !== null)) ?></td>
                            <td class="px-3 py-3 text-right text-slate-700 tabular-nums"><?= $program['participants'] ?></td>
                            <td class="px-3 py-3 text-right whitespace-nowrap text-slate-700 tabular-nums"><?= rupiah($program['company']) ?></td>
                            <td class="px-3 py-3 text-right whitespace-nowrap text-slate-700 tabular-nums"><?= $program['ee'] === null ? '—' : rupiah($program['employee']) ?></td>
                            <td class="px-3 py-3 text-right font-semibold whitespace-nowrap text-slate-900 tabular-nums"><?= rupiah($program['total']) ?></td>
                            <td class="px-5 py-3">
                                <div class="flex h-3 gap-0.5 overflow-hidden" role="img" aria-label="<?= esc(sprintf('%s: perusahaan %s%%, karyawan %s%%', $program['label'], number_id($share, 0), number_id(100 - $share, 0)), 'attr') ?>">
                                    <span class="h-full rounded-l-[4px] <?= $share >= 100 ? 'rounded-r-[4px]' : '' ?>" style="width: <?= number_format($share, 2, '.', '') ?>%; background: <?= $companyColor ?>"></span>
                                    <?php if ($share < 100): ?>
                                        <span class="h-full flex-1 rounded-r-[4px]" style="background: <?= $employeeColor ?>"></span>
                                    <?php endif ?>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500 tabular-nums"><?= number_id($share, 0) ?>% perusahaan<?= $share < 100 ? ' · ' . number_id(100 - $share, 0) . '% karyawan' : '' ?></p>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot class="bg-brand-50/70">
                    <tr class="font-bold text-slate-900">
                        <th scope="row" colspan="3" class="border-t-2 border-brand-200 px-5 py-3 text-left">Total</th>
                        <td class="border-t-2 border-brand-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($total['company']) ?></td>
                        <td class="border-t-2 border-brand-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($total['employee']) ?></td>
                        <td class="border-t-2 border-brand-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($total['total']) ?></td>
                        <td class="border-t-2 border-brand-200"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <!-- Per karyawan -->
    <?php
    $rows = [];

    foreach ($bpjs['rows'] as $row) {
        $cells = [
            '<span class="block font-semibold whitespace-nowrap text-slate-900">' . esc($row['name']) . '</span><span class="text-xs whitespace-nowrap text-slate-500">' . esc($row['nip']) . ' · upah ' . rupiah($row['wage']) . '</span>',
        ];

        // JKK & JKM digabung (keduanya hanya porsi perusahaan) agar tabel tetap muat di layar
        foreach ([['kes'], ['jht'], ['jkk', 'jkm'], ['jp']] as $keys) {
            $company  = array_sum(array_map(static fn (string $key): int => $row['programs'][$key]['company'], $keys));
            $employee = array_sum(array_map(static fn (string $key): int => $row['programs'][$key]['employee'], $keys));
            $detail   = 'Perusahaan ' . rupiah($company) . ' · karyawan ' . rupiah($employee) . ' · dasar ' . rupiah($row['programs'][$keys[0]]['base']);
            $cells[]  = $company + $employee === 0
                ? '<span class="text-slate-400">—</span>'
                : '<span title="' . esc($detail, 'attr') . '">' . rupiah($company + $employee) . '</span>';
        }

        $cells[] = rupiah($row['company']);
        $cells[] = rupiah($row['employee']);
        $cells[] = '<span class="font-semibold text-slate-900">' . rupiah($row['company'] + $row['employee']) . '</span>';
        $rows[]  = $cells;
    }
    ?>
    <?= component('data_table', [
        'title'    => 'Rincian per Karyawan',
        'subtitle' => 'Arahkan kursor ke nominal program untuk melihat porsi perusahaan, karyawan, dan dasar upahnya. Rincian lengkap ada di file ekspor.',
        'columns'  => [
            ['label' => 'Karyawan'],
            ['label' => 'Kesehatan', 'align' => 'right'],
            ['label' => 'JHT', 'align' => 'right'],
            ['label' => 'JKK + JKM', 'align' => 'right', 'mobile' => false],
            ['label' => 'JP', 'align' => 'right'],
            ['label' => 'Perusahaan', 'align' => 'right'],
            ['label' => 'Karyawan', 'align' => 'right'],
            ['label' => 'Total', 'align' => 'right'],
        ],
        'rows'  => $rows,
        'empty' => ['title' => 'Tidak ada peserta BPJS', 'icon' => 'shield-plus'],
    ]) ?>
<?php endif ?>
<?php $this->endSection() ?>
