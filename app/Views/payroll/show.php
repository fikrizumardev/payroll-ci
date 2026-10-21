<?php
/**
 * @var array<string, mixed>                $period
 * @var list<array<string, mixed>>          $payrolls     payrolls + nip/full_name (snapshot & warnings ter-decode)
 * @var array<string, int>                  $totals
 * @var list<array<string, mixed>>          $adjustments
 * @var array<string, string|null>          $actors       tahap → nama pelaku
 * @var list<string>                        $actions      transisi yang tersedia untuk user
 * @var bool                                $canGenerate
 * @var bool                                $canExport
 * @var array{banks: array<string, array{count: int, total: int}>, missing: list<array<string, mixed>>}|null $bank
 * @var list<string>                        $departments
 */

use App\Models\PayrollPeriodModel;
use App\Services\Payroll\PayrollStateMachine;

$this->extend('layouts/app');
$this->section('content');

$id        = (int) $period['id'];
$generated = $payrolls !== [];
$status    = $period['status'];

$confirm = static function (string $route, array $options) use ($id): string {
    $options['action'] = url_to($route, $id);

    return '$dispatch(\'confirm\', ' . esc(json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') . ')';
};

$summaryText = sprintf('%d karyawan · total take home pay %s', $totals['employees'], rupiah($totals['thp']));
$buttons     = [];

if ($canGenerate) {
    $buttons[] = '<button type="button" class="btn ' . ($generated ? 'btn-secondary' : 'btn-primary') . '" @click="' . $confirm('payroll.generate', [
        'title'        => $generated ? 'Generate ulang payroll ' . $period['name'] . '?' : 'Generate payroll ' . $period['name'] . '?',
        'message'      => $generated
            ? 'Hasil hitung saat ini diganti dengan perhitungan baru dari data kehadiran, lembur, pinjaman, dan penyesuaian terbaru. Penyesuaian manual tetap disimpan.'
            : 'Sistem menghitung gaji seluruh karyawan aktif di periode ini, termasuk BPJS, PPh 21, lembur, potongan, dan cicilan pinjaman.',
        'confirmLabel' => $generated ? 'Generate Ulang' : 'Generate',
    ]) . '">' . icon($generated ? 'refresh-cw' : 'calculator', 'size-4') . ($generated ? ' Generate Ulang' : ' Generate Payroll') . '</button>';
}

$transitionButtons = [
    'submit'  => ['payroll.submit', 'btn-primary', 'send', ['title' => 'Ajukan payroll ' . $period['name'] . ' ke Finance?', 'message' => $summaryText . '. Setelah diajukan, payroll tidak bisa di-generate ulang kecuali ditolak Finance.', 'confirmLabel' => 'Ajukan Review', 'withNote' => true, 'noteLabel' => 'Catatan untuk Finance (opsional)']],
    'reject'  => ['payroll.reject', 'btn-secondary text-rose-600', 'undo-2', ['title' => 'Tolak payroll ' . $period['name'] . '?', 'message' => 'Periode dikembalikan ke HR (Draft) untuk diperbaiki.', 'confirmLabel' => 'Tolak', 'tone' => 'danger', 'withNote' => true, 'noteRequired' => true, 'noteLabel' => 'Alasan penolakan', 'notePlaceholder' => 'Jelaskan bagian yang perlu diperbaiki…']],
    'approve' => ['payroll.approve', 'btn-primary', 'badge-check', ['title' => 'Setujui payroll ' . $period['name'] . '?', 'message' => $summaryText . '. Setelah disetujui, data kehadiran & lembur periode ini terkunci.', 'confirmLabel' => 'Setujui', 'withNote' => true, 'noteLabel' => 'Catatan (opsional)']],
    'pay'     => ['payroll.pay', 'btn-primary', 'banknote', ['title' => 'Tandai payroll ' . $period['name'] . ' sudah dibayar?', 'message' => 'Pastikan transfer ' . rupiah($totals['thp']) . ' ke ' . $totals['employees'] . ' karyawan sudah diproses bank.', 'confirmLabel' => 'Tandai Dibayar']],
    'lock'    => ['payroll.lock', 'btn-primary', 'lock', ['title' => 'Kunci payroll ' . $period['name'] . '?', 'message' => 'Periode menjadi read-only secara permanen dan cicilan pinjaman dibukukan. Tindakan ini tidak dapat dibatalkan.', 'confirmLabel' => 'Kunci Periode', 'tone' => 'danger']],
];

foreach ($transitionButtons as $action => [$route, $class, $iconName, $options]) {
    if (in_array($action, $actions, true)) {
        $buttons[] = '<button type="button" class="btn ' . $class . '" @click="' . $confirm($route, $options) . '">' . icon($iconName, 'size-4') . ' ' . esc(PayrollStateMachine::TRANSITIONS[$action]['label']) . '</button>';
    }
}

$stamps = [
    'draft'    => ['at' => $period['generated_at'] ?? $period['created_at'], 'by' => $actors['generated'] ?? $actors['created']],
    'review'   => ['at' => $period['submitted_at'], 'by' => $actors['submitted']],
    'approved' => ['at' => $period['approved_at'], 'by' => $actors['approved']],
    'paid'     => ['at' => $period['paid_at'], 'by' => $actors['paid']],
    'locked'   => ['at' => $period['locked_at'], 'by' => $actors['locked']],
];

$rows = [];

foreach ($payrolls as $p) {
    $snapshot = $p['snapshot'] ?? [];
    $rows[]   = [
        'p'      => $p,
        'name'   => $snapshot['full_name'] ?? $p['full_name'],
        'nip'    => $snapshot['nip'] ?? $p['nip'],
        'role'   => $snapshot['position'] ?? '',
        'dept'   => $snapshot['department'] ?? '',
        'url'    => url_to('payroll.employee', $id, $p['employee_id']),
        'search' => mb_strtolower(($snapshot['full_name'] ?? $p['full_name']) . ' ' . ($snapshot['nip'] ?? $p['nip']) . ' ' . ($snapshot['position'] ?? '')),
        'warn'   => $p['warnings'] ?? [],
    ];
}

$kpis = [
    ['Total pendapatan (bruto)', $totals['gross'], 'Gaji, tunjangan, lembur, penyesuaian'],
    ['Total potongan', $totals['deduction'], 'BPJS karyawan, PPh 21, pinjaman, dll.'],
    ['PPh 21', $totals['pph21'], 'Dipotong dari gaji (metode gross)'],
    ['BPJS perusahaan', $totals['company'], 'Kontribusi di luar gaji karyawan'],
];
?>
<div x-data="{ query: '', dept: '', onlyWarn: false,
               visible(el) { const q = this.query.trim().toLowerCase(); return (!q || el.dataset.search.includes(q)) && (!this.dept || el.dataset.dept === this.dept) && (!this.onlyWarn || el.dataset.warn === '1') } }">

    <?= component('page_header', [
        'eyebrow'  => 'Payroll · ' . (PayrollPeriodModel::STATUSES[$status] ?? $status),
        'title'    => 'Payroll ' . $period['name'],
        'subtitle' => tanggal_id($period['start_date']) . ' – ' . tanggal_id($period['end_date']) . ' · ' . (int) $period['workdays'] . ' hari kerja · gajian ' . tanggal_id($period['pay_date'], 'long') . ($period['notes'] ? ' · ' . $period['notes'] : ''),
        'actions'  => $buttons === [] ? null : implode('', $buttons),
    ]) ?>

    <?php if ($status === PayrollPeriodModel::STATUS_DRAFT && $period['rejection_note']): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200">
            <?= icon('message-square-warning', 'mt-0.5 size-5 shrink-0') ?>
            <div>
                <p class="font-semibold">Dikembalikan oleh Finance</p>
                <p class="mt-0.5"><?= esc($period['rejection_note']) ?></p>
            </div>
        </div>
    <?php elseif ($status === PayrollPeriodModel::STATUS_LOCKED): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-slate-100 p-4 text-sm text-slate-700 ring-1 ring-slate-200">
            <?= icon('lock', 'mt-0.5 size-5 shrink-0') ?>
            <p>Periode terkunci sejak <?= esc(tanggal_id($period['locked_at'], 'datetime')) ?>. Seluruh data periode bersifat read-only dan cicilan pinjaman sudah dibukukan.</p>
        </div>
    <?php endif ?>

    <section class="card mb-6 px-4 py-6 sm:px-8" aria-label="Alur status">
        <?= component('payroll_stepper', ['status' => $status, 'stamps' => $stamps]) ?>
    </section>

    <?php if (! $generated): ?>
        <div class="card">
            <?= component('empty_state', [
                'title'       => 'Payroll belum di-generate',
                'description' => $canGenerate
                    ? 'Pastikan data kehadiran, lembur, dan pinjaman bulan ini sudah lengkap, lalu klik Generate Payroll.'
                    : 'HR belum menjalankan perhitungan payroll untuk periode ini.',
                'icon'        => 'calculator',
                'action'      => $canGenerate ? $buttons[0] : null,
            ]) ?>
        </div>
    <?php else: ?>
        <!-- KPI -->
        <section class="card mb-6 grid overflow-hidden sm:grid-cols-2 lg:grid-cols-5" aria-label="Ringkasan periode">
            <?php foreach ($kpis as $i => [$label, $value, $hint]): ?>
                <div class="border-slate-100 p-5 <?= $i > 0 ? 'border-t sm:border-t-0' : '' ?> <?= $i % 2 === 1 ? 'sm:border-l' : '' ?> <?= $i >= 2 ? 'sm:border-t lg:border-t-0' : '' ?> lg:border-l lg:first:border-l-0">
                    <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
                    <p class="mt-1.5 text-xl font-bold tracking-tight text-slate-900 tabular-nums"><?= rupiah($value) ?></p>
                    <p class="mt-1 text-xs text-slate-500"><?= esc($hint) ?></p>
                </div>
            <?php endforeach ?>
            <div class="relative bg-linear-to-br from-brand-600 via-violet-600 to-fuchsia-600 p-5 text-white sm:col-span-2 lg:col-span-1">
                <p class="text-sm font-medium text-white/80">Total take home pay</p>
                <p class="mt-1.5 text-xl font-bold tracking-tight tabular-nums"><?= rupiah($totals['thp']) ?></p>
                <p class="mt-1 text-xs text-white/80"><?= $totals['employees'] ?> karyawan · rata-rata <?= rupiah(intdiv($totals['thp'], max(1, $totals['employees']))) ?></p>
            </div>
        </section>

        <!-- Tabel karyawan -->
        <section class="card overflow-hidden" aria-labelledby="payroll-table-title">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 id="payroll-table-title" class="text-base font-semibold text-slate-900">Rincian per Karyawan</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Klik baris untuk melihat rincian komponen & cara hitung PPh 21. Generate terakhir <?= esc(tanggal_id($period['generated_at'], 'datetime')) ?>.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="<?= url_to('payroll.payslips.zip', $id) ?>" class="btn btn-secondary py-2" title="PDF slip seluruh karyawan dalam satu berkas ZIP">
                        <?= icon('file-archive', 'size-4') ?> Slip (ZIP)
                    </a>
                    <div class="relative">
                        <label for="payroll-search" class="sr-only">Cari karyawan</label>
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500"><?= icon('search', 'size-4') ?></span>
                        <input id="payroll-search" type="search" x-model.debounce.150ms="query" placeholder="Cari nama / NIP…" class="form-control w-52 py-2 pl-9">
                    </div>
                    <label for="payroll-dept" class="sr-only">Departemen</label>
                    <select id="payroll-dept" x-model="dept" class="form-control w-auto py-2">
                        <option value="">Semua departemen</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= esc($department, 'attr') ?>"><?= esc($department) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($totals['warned'] > 0): ?>
                        <button type="button" class="btn py-2" :class="onlyWarn ? 'bg-amber-500 text-white hover:bg-amber-600' : 'btn-secondary'" @click="onlyWarn = !onlyWarn" :aria-pressed="onlyWarn">
                            <?= icon('triangle-alert', 'size-4') ?> <?= $totals['warned'] ?> peringatan
                        </button>
                    <?php endif ?>
                </div>
            </div>

            <!-- Desktop -->
            <div class="hidden max-h-[70vh] overflow-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
                        <tr class="text-xs font-semibold tracking-wider whitespace-nowrap text-slate-500 uppercase">
                            <th scope="col" class="border-b border-slate-200 px-5 py-3 text-left">Karyawan</th>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-center">Hadir / HK</th>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Pendapatan</th>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Potongan</th>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">PPh 21</th>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3 text-right">Take Home Pay</th>
                            <th scope="col" class="border-b border-slate-200 px-3 py-3"><span class="sr-only">Buka</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <?php $p = $row['p']; ?>
                            <tr x-show="visible($el)" data-search="<?= esc($row['search'], 'attr') ?>" data-dept="<?= esc($row['dept'], 'attr') ?>" data-warn="<?= $row['warn'] ? '1' : '0' ?>"
                                @click="if (!$event.target.closest('a')) window.location = '<?= esc($row['url'], 'js') ?>'"
                                class="cursor-pointer transition-colors <?= $row['warn'] ? 'bg-amber-50/70 hover:bg-amber-50' : 'hover:bg-slate-50/80' ?>">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <?= avatar($row['name']) ?>
                                        <div class="min-w-0">
                                            <a href="<?= esc($row['url'], 'attr') ?>" class="block max-w-44 truncate font-semibold text-slate-900 hover:text-brand-700 2xl:max-w-56"><?= esc($row['name']) ?></a>
                                            <p class="max-w-44 truncate text-xs text-slate-500 2xl:max-w-56"><?= esc($row['nip']) ?> · <?= esc($row['role']) ?></p>
                                            <?php if ($row['warn']): ?>
                                                <p class="mt-0.5 flex max-w-48 items-center gap-1 text-xs font-medium text-amber-800 2xl:max-w-64" title="<?= esc(implode("\n", $row['warn']), 'attr') ?>">
                                                    <?= icon('triangle-alert', 'size-3.5 shrink-0') ?><span class="truncate"><?= esc($row['warn'][0]) ?><?= count($row['warn']) > 1 ? ' (+' . (count($row['warn']) - 1) . ')' : '' ?></span>
                                                </p>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center whitespace-nowrap text-slate-700 tabular-nums">
                                    <?= (int) $p['present_days'] ?> / <?= (int) $p['active_workdays'] ?>
                                    <?php if ($p['active_workdays'] < $p['workdays']): ?>
                                        <span class="block text-[11px] font-semibold text-amber-700">prorata</span>
                                    <?php elseif ($p['absent_days'] > 0): ?>
                                        <span class="block text-[11px] font-semibold text-rose-600"><?= (int) $p['absent_days'] ?> alpha</span>
                                    <?php endif ?>
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap text-slate-700 tabular-nums"><?= rupiah($p['total_earning']) ?></td>
                                <td class="px-3 py-3 text-right whitespace-nowrap text-slate-700 tabular-nums"><?= rupiah($p['total_deduction']) ?></td>
                                <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums">
                                    <span class="text-slate-700"><?= rupiah($p['pph21']) ?></span>
                                    <span class="block text-[11px] text-slate-500"><?= $p['ter_rate'] === null ? 'Setahun' : 'TER ' . esc($p['ter_category']) . ' · ' . esc(percent_id($p['ter_rate'])) ?></span>
                                </td>
                                <td class="px-3 py-3 text-right font-bold whitespace-nowrap tabular-nums <?= $p['take_home_pay'] < 0 ? 'text-rose-600' : 'text-slate-900' ?>"><?= rupiah($p['take_home_pay']) ?></td>
                                <td class="px-3 py-3 text-slate-400"><?= icon('chevron-right', 'size-4') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-slate-50/95 backdrop-blur">
                        <tr class="font-semibold text-slate-900">
                            <th scope="row" class="border-t border-slate-200 px-5 py-3 text-left text-sm">Total <?= $totals['employees'] ?> karyawan</th>
                            <td class="border-t border-slate-200"></td>
                            <td class="border-t border-slate-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($totals['gross']) ?></td>
                            <td class="border-t border-slate-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($totals['deduction']) ?></td>
                            <td class="border-t border-slate-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($totals['pph21']) ?></td>
                            <td class="border-t border-slate-200 px-3 py-3 text-right whitespace-nowrap tabular-nums"><?= rupiah($totals['thp']) ?></td>
                            <td class="border-t border-slate-200"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Mobile -->
            <ul class="divide-y divide-slate-100 md:hidden">
                <?php foreach ($rows as $row): ?>
                    <?php $p = $row['p']; ?>
                    <li x-show="visible($el)" data-search="<?= esc($row['search'], 'attr') ?>" data-dept="<?= esc($row['dept'], 'attr') ?>" data-warn="<?= $row['warn'] ? '1' : '0' ?>" class="<?= $row['warn'] ? 'bg-amber-50/70' : '' ?>">
                        <a href="<?= esc($row['url'], 'attr') ?>" class="flex items-center gap-3 px-4 py-3.5">
                            <?= avatar($row['name']) ?>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900"><?= esc($row['name']) ?></span>
                                <span class="block truncate text-xs text-slate-500"><?= esc($row['nip']) ?> · <?= (int) $p['present_days'] ?>/<?= (int) $p['active_workdays'] ?> hari</span>
                                <?php if ($row['warn']): ?>
                                    <span class="block truncate text-xs font-medium text-amber-800"><?= esc($row['warn'][0]) ?></span>
                                <?php endif ?>
                            </span>
                            <span class="text-right">
                                <span class="block text-sm font-bold text-slate-900 tabular-nums"><?= rupiah($p['take_home_pay']) ?></span>
                                <span class="block text-[11px] text-slate-500 tabular-nums">PPh <?= rupiah($p['pph21']) ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
    <?php endif ?>

    <div class="mt-6 grid gap-6 <?= $bank !== null ? 'xl:grid-cols-2' : '' ?>">
        <!-- Penyesuaian manual -->
        <?php if ($adjustments !== [] || $canGenerate): ?>
            <section class="card overflow-hidden" aria-labelledby="adjustment-title">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 id="adjustment-title" class="text-base font-semibold text-slate-900">Penyesuaian Manual</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Bonus, THR, insentif, atau koreksi. <?= $canGenerate ? 'Tambahkan dari halaman rincian karyawan; payroll di-generate ulang otomatis.' : '' ?></p>
                </div>
                <?php if ($adjustments === []): ?>
                    <?= component('empty_state', ['title' => 'Belum ada penyesuaian', 'icon' => 'sliders-horizontal', 'compact' => true]) ?>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($adjustments as $adjustment): ?>
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg <?= $adjustment['type'] === 'earning' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                    <?= icon($adjustment['type'] === 'earning' ? 'plus' : 'minus', 'size-4') ?>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900"><?= esc($adjustment['name']) ?></p>
                                    <p class="truncate text-xs text-slate-500">
                                        <a href="<?= url_to('payroll.employee', $id, $adjustment['employee_id']) ?>" class="font-medium text-brand-700 hover:underline"><?= esc($adjustment['full_name']) ?></a>
                                        · <?= $adjustment['taxable'] ? 'Kena pajak' : 'Tidak kena pajak' ?><?= $adjustment['note'] ? ' · ' . esc($adjustment['note']) : '' ?>
                                    </p>
                                </div>
                                <span class="text-sm font-bold whitespace-nowrap tabular-nums <?= $adjustment['type'] === 'earning' ? 'text-emerald-700' : 'text-rose-600' ?>">
                                    <?= $adjustment['type'] === 'earning' ? '+' : '−' ?><?= rupiah($adjustment['amount']) ?>
                                </span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </section>
        <?php endif ?>

        <!-- Transfer bank -->
        <?php if ($bank !== null): ?>
            <section class="card overflow-hidden" aria-labelledby="bank-title">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 id="bank-title" class="text-base font-semibold text-slate-900">File Transfer Bank</h2>
                        <p class="mt-0.5 text-sm text-slate-500">CSV per bank berisi nama, no. rekening, nominal, dan berita "<?= esc(App\Services\Export\BankTransferExporter::remark($period)) ?>".</p>
                    </div>
                    <a href="<?= url_to('payroll.export.bank', $id) ?>" class="btn btn-secondary shrink-0 py-2"><?= icon('download', 'size-4') ?> Semua</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($bank['banks'] as $name => $info): ?>
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-bold text-slate-700"><?= esc(mb_substr($name, 0, 3)) ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900"><?= esc($name) ?></p>
                                <p class="text-xs text-slate-500"><?= $info['count'] ?> penerima</p>
                            </div>
                            <span class="text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums"><?= rupiah($info['total']) ?></span>
                            <a href="<?= esc(url_to('payroll.export.bank', $id) . '?bank=' . rawurlencode($name), 'attr') ?>" class="rounded-lg p-2 text-brand-600 hover:bg-brand-50" aria-label="Unduh CSV <?= esc($name, 'attr') ?>">
                                <?= icon('download', 'size-4') ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
                <?php if ($bank['missing'] !== []): ?>
                    <div class="flex gap-2 border-t border-amber-200 bg-amber-50 px-5 py-3 text-xs text-amber-800">
                        <?= icon('triangle-alert', 'size-4 shrink-0') ?>
                        <p><?= count($bank['missing']) ?> karyawan tanpa rekening tidak masuk file: <?= esc(implode(', ', array_column($bank['missing'], 'name'))) ?>. Bayarkan secara manual.</p>
                    </div>
                <?php endif ?>
            </section>
        <?php endif ?>
    </div>
</div>
<?php $this->endSection() ?>
