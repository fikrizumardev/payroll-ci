<?php
/**
 * @var int                  $year
 * @var int                  $month
 * @var array<string, mixed> $trend hasil ReportService::costTrend()
 */
$this->extend('layouts/app');
$this->section('content');

// Slot kategorikal 1–8 (urutan tetap, lolos validasi CVD untuk bentuk bertumpuk/berdampingan).
// Warna mengikuti departemen (urut ID), bukan peringkat biaya.
$palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

$departments = $trend['departments'];

// Lebih dari 8 departemen: sisanya dilipat menjadi "Lainnya" (tidak membuat warna baru)
if (count($departments) > count($palette)) {
    $head  = array_slice($departments, 0, count($palette) - 1);
    $tail  = array_slice($departments, count($palette) - 1);
    $other = ['key' => 'other', 'name' => 'Lainnya (' . count($tail) . ' departemen)', 'costs' => [], 'total' => 0];

    foreach ($trend['months'] as $i => $slot) {
        $other['costs'][$i] = $slot['period_id'] === null ? null : array_sum(array_map(static fn (array $d): int => (int) $d['costs'][$i], $tail));
    }

    $other['total'] = (int) array_sum($other['costs']);
    $departments    = [...$head, $other];
}

foreach ($departments as $i => &$department) {
    $department['color'] = $palette[$i];
}
unset($department);

$months   = $trend['months'];
$filled   = array_values(array_filter($months, static fn (array $m): bool => $m['period_id'] !== null));
$latest   = $filled === [] ? null : $filled[array_key_last($filled)];
$previous = count($filled) > 1 ? $filled[count($filled) - 2] : null;
$delta    = $latest !== null && $previous !== null && $previous['cost'] > 0 ? ($latest['cost'] - $previous['cost']) / $previous['cost'] * 100 : null;
$largest  = $departments === [] ? null : array_reduce($departments, static fn (?array $carry, array $d): array => $carry === null || $d['total'] > $carry['total'] ? $d : $carry);
$first    = $months[0];
$rangeTxt = periode_id($first['year'], $first['month'], true) . ' – ' . periode_id($year, $month, true);

/** Rupiah ringkas untuk label: 452,7 jt / 1,2 M */
$compact = static fn (int $amount): string => match (true) {
    $amount >= 1_000_000_000 => number_id($amount / 1_000_000_000, 1) . ' M',
    $amount >= 1_000_000     => number_id($amount / 1_000_000, 1) . ' jt',
    default                  => number_id($amount),
};
?>
<?= $this->include('reports/_header') ?>

<div class="card mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
    <form method="get" action="<?= url_to('reports.cost-trend') ?>" class="flex flex-wrap items-center gap-3">
        <label for="trend-end" class="text-sm font-semibold text-slate-700">12 bulan hingga</label>
        <input id="trend-end" type="month" name="end" value="<?= sprintf('%04d-%02d', $year, $month) ?>" class="form-control w-44" onchange="this.form.submit()">
        <noscript><button type="submit" class="btn btn-secondary">Tampilkan</button></noscript>
    </form>
    <p class="text-sm text-slate-500"><?= esc($rangeTxt) ?> · <?= count($filled) ?> dari 12 bulan memiliki payroll · biaya = pendapatan + BPJS perusahaan</p>
</div>

<?php if ($filled === []): ?>
    <div class="card">
        <?= component('empty_state', ['title' => 'Belum ada payroll pada rentang ini', 'description' => 'Pilih bulan akhir lain atau generate payroll terlebih dahulu.', 'icon' => 'chart-column-stacked']) ?>
    </div>
<?php else: ?>
    <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan tren biaya">
        <?php foreach ([
            ['Total biaya 12 bulan', rupiah($trend['grand']), count($filled) . ' periode payroll', null],
            ['Rata-rata per periode', rupiah(intdiv($trend['grand'], count($filled))), 'Hanya bulan yang memiliki payroll', null],
            ['Biaya ' . periode_id($latest['year'], $latest['month']), rupiah($latest['cost']), $latest['employees'] . ' karyawan', $delta],
            ['Departemen terbesar', $largest['name'], rupiah($largest['total']) . ' · ' . number_id($trend['grand'] > 0 ? $largest['total'] / $trend['grand'] * 100 : 0, 1) . '% dari total', null],
        ] as $i => [$label, $value, $hint, $change]): ?>
            <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                <p class="mt-1.5 truncate text-xl font-bold tracking-tight text-slate-900 tabular-nums" title="<?= esc($value, 'attr') ?>"><?= esc($value) ?></p>
                <p class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-500">
                    <?php if ($change !== null): ?>
                        <?php // Biaya naik = kurang baik (merah), turun = hijau; ikon + tanda agar tidak bergantung warna ?>
                        <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-semibold <?= $change > 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' ?>">
                            <?= icon($change >= 0 ? 'trending-up' : 'trending-down', 'size-3.5') ?><?= esc(($change > 0 ? '+' : '') . number_id($change, 1)) ?>%
                        </span>
                        <span>vs periode sebelumnya ·</span>
                    <?php endif ?>
                    <span><?= esc($hint) ?></span>
                </p>
            </div>
        <?php endforeach ?>
    </section>

    <section class="card mb-6 p-5" aria-labelledby="trend-chart-title">
        <h2 id="trend-chart-title" class="text-base font-semibold text-slate-900">Biaya Perusahaan per Departemen</h2>
        <p class="mt-0.5 text-sm text-slate-500"><?= esc($rangeTxt) ?> · angka di atas kolom = total biaya bulan tersebut · rincian lengkap di tabel</p>

        <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-600" aria-label="Legenda">
            <?php foreach ($departments as $department): ?>
                <li class="flex items-center gap-2"><span class="h-2.5 w-3 rounded-[3px]" style="background: <?= $department['color'] ?>" aria-hidden="true"></span><?= esc($department['name']) ?></li>
            <?php endforeach ?>
        </ul>

        <div class="relative mt-4 h-80">
            <canvas id="cost-trend-chart" role="img" aria-label="Grafik kolom bertumpuk biaya perusahaan per departemen selama 12 bulan. Rincian tersedia di tabel di bawahnya."></canvas>
        </div>
    </section>

    <section class="card overflow-hidden" aria-labelledby="trend-table-title">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 id="trend-table-title" class="text-base font-semibold text-slate-900">Tabel Biaya per Departemen</h2>
            <p class="mt-0.5 text-sm text-slate-500">Tanda “—” berarti belum ada payroll pada bulan tersebut.</p>
        </div>
        <!-- Digulir otomatis ke kanan agar bulan terbaru langsung terlihat -->
        <div class="overflow-x-auto" x-data x-init="$el.scrollLeft = $el.scrollWidth">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50/95">
                    <tr class="text-xs font-semibold tracking-wider whitespace-nowrap text-slate-500 uppercase">
                        <th scope="col" class="sticky left-0 z-10 w-44 min-w-44 sm:w-64 sm:min-w-64 border-b border-slate-200 bg-slate-50 px-5 py-3 text-left">Departemen</th>
                        <th scope="col" class="z-10 sm:sticky sm:left-64 border-r border-b border-slate-200 bg-slate-50 px-5 py-3 text-right">Total 12 bulan</th>
                        <?php foreach ($months as $slot): ?>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right <?= $slot['period_id'] === null ? 'text-slate-400' : '' ?>">
                                <?php if ($slot['period_id'] !== null): ?>
                                    <a href="<?= url_to('reports.payroll') ?>?period=<?= $slot['period_id'] ?>" class="hover:text-brand-700"><?= esc(periode_id($slot['year'], $slot['month'], true)) ?></a>
                                <?php else: ?>
                                    <?= esc(periode_id($slot['year'], $slot['month'], true)) ?>
                                <?php endif ?>
                            </th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($departments as $department): ?>
                        <tr class="hover:bg-slate-50/80">
                            <th scope="row" class="sticky left-0 w-44 min-w-44 sm:w-64 sm:min-w-64 bg-white px-5 py-3 text-left font-semibold text-slate-800">
                                <span class="mr-2 inline-block h-2.5 w-3 rounded-[3px] align-middle" style="background: <?= $department['color'] ?>" aria-hidden="true"></span><?= esc($department['name']) ?>
                            </th>
                            <td class="sm:sticky sm:left-64 border-r border-slate-200 bg-white px-5 py-3 text-right font-semibold whitespace-nowrap text-slate-900 tabular-nums"><?= rupiah($department['total']) ?></td>
                            <?php foreach ($department['costs'] as $cost): ?>
                                <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums <?= $cost === null ? 'text-slate-400' : 'text-slate-700' ?>"><?= $cost === null ? '—' : esc($compact($cost)) ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot class="bg-brand-50/70">
                    <tr class="font-bold text-slate-900">
                        <th scope="row" class="sticky left-0 w-44 min-w-44 sm:w-64 sm:min-w-64 border-t-2 border-brand-200 bg-brand-50 px-5 py-3 text-left">Total</th>
                        <td class="sm:sticky sm:left-64 border-t-2 border-r border-brand-200 bg-brand-50 px-5 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($trend['grand']) ?></td>
                        <?php foreach ($trend['totals'] as $cost): ?>
                            <td class="border-t-2 border-brand-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= $cost === null ? '—' : esc($compact($cost)) ?></td>
                        <?php endforeach ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
<?php endif ?>
<?php $this->endSection() ?>

<?php if ($filled !== []): ?>
    <?php $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('cost-trend-chart');
            if (!canvas || !window.Chart) return;

            const months = <?= json_encode(array_map(static fn (array $m): array => [
                'label' => bulan_id($m['month'], true) . ' ' . substr((string) $m['year'], 2),
                'name'  => periode_id($m['year'], $m['month']),
                'total' => $m['cost'],
            ], $months), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
            const series = <?= json_encode(array_map(static fn (array $d): array => [
                'label' => $d['name'],
                'color' => $d['color'],
                'data'  => $d['costs'],
            ], $departments), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

            const ink = { primary: '#0f172a', secondary: '#64748b', grid: '#eef0f4', surface: '#ffffff' };
            const rupiah = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
            const compact = (v) => v >= 1e9 ? (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M'
                : v >= 1e6 ? (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' jt' : v.toLocaleString('id-ID');

            // Label total di atas kolom yang memiliki payroll (rincian per departemen di tooltip & tabel)
            const totalLabels = {
                id: 'totalLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = '600 11px "Plus Jakarta Sans", system-ui, sans-serif';
                    ctx.fillStyle = ink.primary;
                    ctx.textAlign = 'center';
                    months.forEach((month, i) => {
                        if (month.total === null) return;
                        let top = null;
                        chart.data.datasets.forEach((_, d) => {
                            const bar = chart.getDatasetMeta(d).data[i];
                            if (bar && chart.isDatasetVisible(d) && (top === null || bar.y < top.y)) top = bar;
                        });
                        if (top) ctx.fillText(compact(month.total), top.x, top.y - 8);
                    });
                    ctx.restore();
                },
            };

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: months.map((m) => m.label),
                    datasets: series.map((s, index) => ({
                        label: s.label,
                        data: s.data,
                        backgroundColor: s.color,
                        hoverBackgroundColor: s.color + 'd9',
                        // Celah 2px warna permukaan antar segmen bertumpuk
                        borderColor: ink.surface,
                        borderWidth: index === 0 ? 0 : { top: 0, bottom: 2, left: 0, right: 0 },
                        borderSkipped: false,
                        borderRadius: index === series.length - 1 ? { topLeft: 4, topRight: 4 } : 0,
                        maxBarThickness: 28,
                        categoryPercentage: 0.62,
                    })),
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { top: 22 } },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            filter: (item) => item.raw !== null,
                            backgroundColor: '#ffffff',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            titleColor: ink.secondary,
                            bodyColor: ink.primary,
                            footerColor: ink.primary,
                            padding: 12,
                            boxPadding: 4,
                            usePointStyle: true,
                            titleFont: { weight: '500' },
                            bodyFont: { weight: '600' },
                            footerFont: { weight: '700' },
                            callbacks: {
                                title: (items) => months[items[0]?.dataIndex ?? 0].name,
                                label: (item) => rupiah(item.raw) + '  ·  ' + item.dataset.label,
                                labelPointStyle: () => ({ pointStyle: 'rect', rotation: 0 }),
                                footer: (items) => {
                                    const month = months[items[0]?.dataIndex ?? 0];
                                    return month.total === null ? 'Belum ada payroll' : 'Total  ' + rupiah(month.total);
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            border: { color: '#cbd5e1' },
                            ticks: { color: ink.secondary, font: { size: 11 } },
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            border: { display: false },
                            grid: { color: ink.grid, lineWidth: 1 },
                            ticks: { color: ink.secondary, font: { size: 11 }, maxTicksLimit: 6, callback: (v) => compact(v) },
                        },
                    },
                },
                plugins: [totalLabels],
            });
        });
    </script>
    <?php $this->endSection() ?>
<?php endif ?>
