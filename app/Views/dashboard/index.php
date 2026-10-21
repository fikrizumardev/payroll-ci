<?php
/**
 * @var array<string, mixed>                  $user
 * @var string                                $today
 * @var list<array<string, mixed>>            $holidays
 * @var array<string, mixed>|null             $company
 * @var array<string, mixed>|null             $payroll
 * @var array<string, mixed>|null             $people
 * @var list<array<string, mixed>>|null       $activities
 * @var array<string, mixed>|null             $personal
 */

use App\Models\HolidayModel;

$this->extend('layouts/app');
$this->section('content');

$company    ??= null;
$payroll    ??= null;
$people     ??= null;
$activities ??= [];
$personal   ??= null;

$hour     = (int) date('G');
$greeting = match (true) {
    $hour < 11 => 'Selamat pagi',
    $hour < 15 => 'Selamat siang',
    $hour < 18 => 'Selamat sore',
    default    => 'Selamat malam',
};
$firstName = explode(' ', $user['name'])[0];
$latest    = $payroll['latest'] ?? null;

/** Rupiah ringkas untuk label: 452,7 jt / 1,2 M */
$compact = static fn (int $amount): string => match (true) {
    $amount >= 1_000_000_000 => number_id($amount / 1_000_000_000, 1) . ' M',
    $amount >= 1_000_000     => number_id($amount / 1_000_000, 1) . ' jt',
    default                  => number_id($amount),
};
?>

<?= component('page_header', [
    'eyebrow'  => tanggal_id($today, 'full'),
    'title'    => "{$greeting}, {$firstName}",
    'subtitle' => $company
        ? 'Ringkasan penggajian dan operasional SDM ' . service('appSettings')->get('company.name') . '.'
        : 'Ringkasan kehadiran dan informasi kepegawaian Anda bulan ini.',
]) ?>

<?php if ($company): ?>
    <?php
    $lastDay  = $company['lastDay'];
    $attended = ($lastDay['present'] ?? 0) + ($lastDay['late'] ?? 0);
    $recorded = array_sum($lastDay);
    $types    = $company['byType'];
    $trendThp = array_column($payroll['periods'], 'thp');
    ?>

    <!-- KPI payroll -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?= component('kpi_card', [
            'label' => 'Karyawan Aktif',
            'value' => number_id($company['activeEmployees']) . ' orang',
            'icon'  => 'users-round',
            'tone'  => 'brand',
            'hint'  => sprintf('%d tetap · %d kontrak · %d percobaan', $types['permanent'] ?? 0, $types['contract'] ?? 0, $types['probation'] ?? 0),
            'href'  => url_to('employees.index'),
        ]) ?>
        <?php if ($latest): ?>
            <?= component('kpi_card', [
                'label'      => 'Gaji Bersih · ' . $latest['name'],
                'value'      => rupiah($latest['thp']),
                'icon'       => 'banknote',
                'tone'       => 'emerald',
                'delta'      => $payroll['deltas']['thp'],
                'deltaLabel' => 'vs periode lalu',
                'hint'       => $latest['employees'] . ' karyawan · ' . status_meta($latest['status'])['label'],
                'trend'      => count($trendThp) > 1 ? $trendThp : null,
            ]) ?>
            <?= component('kpi_card', [
                'label'      => 'PPh 21 · ' . $latest['name'],
                'value'      => rupiah($latest['pph21']),
                'icon'       => 'landmark',
                'tone'       => 'violet',
                'delta'      => $payroll['deltas']['pph21'],
                'deltaLabel' => 'vs periode lalu',
                'inverse'    => true,
                'hint'       => 'BPJS karyawan ' . $compact($latest['bpjs_employee']),
            ]) ?>
            <?= component('kpi_card', [
                'label'      => 'Total Biaya Perusahaan',
                'value'      => rupiah($latest['employer_cost']),
                'icon'       => 'building-2',
                'tone'       => 'amber',
                'delta'      => $payroll['deltas']['employer_cost'],
                'deltaLabel' => 'vs periode lalu',
                'inverse'    => true,
                'hint'       => 'Termasuk BPJS perusahaan ' . $compact($latest['bpjs_company']),
            ]) ?>
        <?php else: ?>
            <div class="card p-5 xl:col-span-3">
                <?= component('empty_state', ['title' => 'Belum ada payroll', 'description' => 'Buat periode payroll pertama untuk melihat ringkasan biaya.', 'icon' => 'banknote', 'compact' => true]) ?>
            </div>
        <?php endif ?>
    </div>

    <?php if ($latest): ?>
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <!-- Komposisi biaya payroll -->
            <section class="card p-5 xl:col-span-2" aria-labelledby="cost-trend-title" x-data="{ view: 'chart' }">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="cost-trend-title" class="text-base font-semibold text-slate-900">Komposisi Biaya Payroll</h2>
                        <p class="mt-0.5 text-sm text-slate-500"><?= count($payroll['periods']) ?> periode terakhir · angka di atas kolom = total biaya perusahaan</p>
                    </div>
                    <div class="inline-flex shrink-0 rounded-lg bg-slate-100 p-0.5 text-xs font-semibold" role="tablist" aria-label="Tampilan data">
                        <button type="button" role="tab" @click="view = 'chart'" :aria-selected="view === 'chart'" class="rounded-md px-3 py-1.5" :class="view === 'chart' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'">Grafik</button>
                        <button type="button" role="tab" @click="view = 'table'" :aria-selected="view === 'table'" class="rounded-md px-3 py-1.5" :class="view === 'table' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'">Tabel</button>
                    </div>
                </div>

                <!-- Legenda (selalu ada untuk ≥ 2 seri) -->
                <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-600" aria-label="Legenda">
                    <li class="flex items-center gap-2"><span class="h-2.5 w-3 rounded-[3px] bg-[#2a78d6]" aria-hidden="true"></span>Gaji bersih diterima</li>
                    <li class="flex items-center gap-2"><span class="h-2.5 w-3 rounded-[3px] bg-[#eb6834]" aria-hidden="true"></span>PPh 21 & iuran BPJS karyawan</li>
                    <li class="flex items-center gap-2"><span class="h-2.5 w-3 rounded-[3px] bg-[#1baf7a]" aria-hidden="true"></span>Kontribusi BPJS perusahaan</li>
                </ul>

                <div x-show="view === 'chart'" class="relative mt-4 h-72">
                    <canvas id="payroll-cost-chart" role="img" aria-label="Grafik kolom bertumpuk komposisi biaya payroll per periode. Rincian tersedia di tampilan tabel."></canvas>
                </div>

                <div x-show="view === 'table'" x-cloak class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                <th scope="col" class="py-2 pr-4">Periode</th>
                                <th scope="col" class="py-2 pr-4 text-right">Gaji bersih</th>
                                <th scope="col" class="py-2 pr-4 text-right">PPh 21 + BPJS karyawan</th>
                                <th scope="col" class="py-2 pr-4 text-right">BPJS perusahaan</th>
                                <th scope="col" class="py-2 text-right">Biaya perusahaan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 tabular-nums">
                            <?php foreach (array_reverse($payroll['periods']) as $period): ?>
                                <tr>
                                    <th scope="row" class="py-2.5 pr-4 text-left font-medium text-slate-800"><?= esc($period['name']) ?> <?= status_badge($period['status']) ?></th>
                                    <td class="py-2.5 pr-4 text-right text-slate-700"><?= rupiah($period['thp']) ?></td>
                                    <td class="py-2.5 pr-4 text-right text-slate-700"><?= rupiah($period['pph21'] + $period['bpjs_employee']) ?></td>
                                    <td class="py-2.5 pr-4 text-right text-slate-700"><?= rupiah($period['bpjs_company']) ?></td>
                                    <td class="py-2.5 text-right font-semibold text-slate-900"><?= rupiah($period['employer_cost']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Biaya per departemen: bar horizontal satu warna, urut terbesar -->
            <section class="card p-5" aria-labelledby="dept-cost-title">
                <h2 id="dept-cost-title" class="text-base font-semibold text-slate-900">Biaya per Departemen</h2>
                <p class="mt-0.5 text-sm text-slate-500"><?= esc($latest['name']) ?> · pendapatan + BPJS perusahaan</p>
                <?php $maxCost = max(array_column($payroll['departments'], 'cost') ?: [1]); ?>
                <ul class="mt-5 space-y-4">
                    <?php foreach ($payroll['departments'] as $department): ?>
                        <?php $share = $latest['employer_cost'] > 0 ? $department['cost'] / $latest['employer_cost'] * 100 : 0; ?>
                        <li title="<?= esc($department['department'] . ': ' . rupiah($department['cost']) . ' (' . number_id($share, 1) . '%) · ' . $department['employees'] . ' karyawan', 'attr') ?>">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate font-medium text-slate-700"><?= esc($department['department']) ?></span>
                                <span class="shrink-0 font-semibold text-slate-900 tabular-nums"><?= esc($compact($department['cost'])) ?> <span class="font-normal text-slate-500">· <?= number_id($share, 0) ?>%</span></span>
                            </div>
                            <div class="mt-1.5 h-3">
                                <div class="h-full rounded-r-[4px] bg-[#2a78d6]" style="width: <?= number_format($department['cost'] / $maxCost * 100, 2, '.', '') ?>%"></div>
                            </div>
                            <p class="mt-1 text-xs text-slate-500"><?= $department['employees'] ?> karyawan · rata-rata <?= esc($compact(intdiv($department['cost'], max($department['employees'], 1)))) ?>/orang</p>
                        </li>
                    <?php endforeach ?>
                </ul>
            </section>
        </div>
    <?php endif ?>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <!-- Periode payroll terakhir -->
        <section class="card xl:col-span-2" aria-labelledby="period-title">
            <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="period-title" class="text-base font-semibold text-slate-900">Periode Payroll Terakhir</h2>
                <span class="text-xs text-slate-500">Draft → Review → Disetujui → Dibayar → Terkunci</span>
            </div>
            <?php if (($payroll['periods'] ?? []) === []): ?>
                <?= component('empty_state', ['title' => 'Belum ada periode payroll', 'icon' => 'banknote', 'compact' => true]) ?>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach (array_slice(array_reverse($payroll['periods']), 0, 4) as $period): ?>
                        <li class="relative grid gap-3 px-5 py-4 transition-colors hover:bg-slate-50/80 sm:grid-cols-[1fr_auto] sm:items-center">
                            <div class="flex items-center gap-4">
                                <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                                    <span class="text-[10px] font-bold uppercase"><?= esc(bulan_id((int) $period['month'], true)) ?></span>
                                    <span class="text-xs font-bold"><?= (int) $period['year'] ?></span>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="<?= url_to('payroll.show', $period['id']) ?>" class="font-semibold text-slate-900 after:absolute after:inset-0 hover:text-brand-700"><?= esc($period['name']) ?></a>
                                        <?= status_badge($period['status']) ?>
                                    </div>
                                    <p class="text-xs text-slate-500"><?= $period['employees'] ?> karyawan · gajian <?= esc(tanggal_id($period['pay_date'])) ?></p>
                                    <div class="mt-2"><?= component('payroll_stepper', ['status' => $period['status'], 'compact' => true]) ?></div>
                                </div>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="text-xs text-slate-500">Total gaji bersih</p>
                                <p class="text-lg font-bold text-slate-900 tabular-nums"><?= rupiah($period['thp']) ?></p>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <!-- Perlu tindakan -->
        <section class="card p-5" aria-labelledby="action-title">
            <h2 id="action-title" class="text-base font-semibold text-slate-900">Perlu Tindakan</h2>
            <?php
            $openPeriods = array_values(array_filter($payroll['periods'] ?? [], static fn (array $p): bool => in_array($p['status'], ['draft', 'review'], true)));
            $tasks       = array_filter([
                $openPeriods !== [] ? ['banknote', 'sky', 'Payroll ' . $openPeriods[0]['name'], 'Status ' . status_meta($openPeriods[0]['status'])['label'] . ' — ' . ($openPeriods[0]['status'] === 'draft' ? 'perlu diajukan ke Finance' : 'menunggu persetujuan Finance')] : null,
                $company['pendingOvertime']['total'] > 0 ? ['clock-4', 'amber', $company['pendingOvertime']['total'] . ' pengajuan lembur', 'Total ' . number_id($company['pendingOvertime']['hours'], 1) . ' jam menunggu persetujuan'] : null,
                $company['pendingLoans'] > 0 ? ['hand-coins', 'violet', $company['pendingLoans'] . ' pengajuan pinjaman', 'Menunggu persetujuan Finance'] : null,
                ['wallet', 'emerald', rupiah($company['activeLoans']['remaining']), 'Sisa ' . $company['activeLoans']['total'] . ' pinjaman berjalan'],
            ]);
            ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($tasks as [$iconName, $tone, $heading, $caption]): ?>
                    <li class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 <?= tone_classes($tone) ?>"><?= icon($iconName, 'size-4') ?></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900"><?= esc($heading) ?></p>
                            <p class="text-xs text-slate-500"><?= esc($caption) ?></p>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <!-- Status kehadiran -->
        <section class="card p-5" aria-labelledby="attendance-heading">
            <div class="flex items-center justify-between">
                <h2 id="attendance-heading" class="text-base font-semibold text-slate-900">Status Kehadiran</h2>
                <span class="text-xs font-medium text-slate-500"><?= esc(tanggal_id($company['lastDate'])) ?></span>
            </div>
            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900 tabular-nums"><?= $recorded > 0 ? number_id($attended / $recorded * 100, 1) : '0' ?>%</p>
            <p class="text-xs text-slate-500"><?= $attended ?> dari <?= $recorded ?> karyawan hadir</p>
            <ul class="mt-5 space-y-3">
                <?php foreach (['present', 'late', 'sick', 'leave', 'permit', 'absent'] as $status): ?>
                    <?php
                    $count   = $lastDay[$status] ?? 0;
                    $percent = $recorded > 0 ? $count / $recorded * 100 : 0;
                    $meta    = status_meta($status);
                    ?>
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full <?= tone_classes($meta['tone'], 'dot') ?>"></span><?= esc($meta['label']) ?></span>
                            <span class="font-semibold text-slate-900 tabular-nums"><?= $count ?></span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full <?= tone_classes($meta['tone'], 'dot') ?>" style="width: <?= number_format($percent, 1, '.', '') ?>%"></div>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>

        <!-- Aktivitas terbaru -->
        <section class="card xl:col-span-2" aria-labelledby="activity-heading">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 id="activity-heading" class="text-base font-semibold text-slate-900">Aktivitas Terbaru</h2>
                <span class="text-xs text-slate-500">Audit log</span>
            </div>
            <?php if ($activities === []): ?>
                <?= component('empty_state', ['title' => 'Belum ada aktivitas', 'icon' => 'scroll-text', 'compact' => true]) ?>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($activities as $activity): ?>
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <?= avatar($activity['user_name'] ?? $activity['username'] ?? 'Sistem', 'size-8 text-[11px]') ?>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-slate-700">
                                    <span class="font-semibold text-slate-900"><?= esc($activity['user_name'] ?? $activity['username'] ?? 'Sistem') ?></span>
                                    — <?= esc($activity['description']) ?>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500"><?= esc(tanggal_id($activity['created_at'], 'datetime')) ?> · <?= esc($activity['action']) ?></p>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>
<?php endif ?>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <?php if ($personal): ?>
        <?php $attendance = $personal['attendance']; ?>
        <section class="card relative overflow-hidden p-6 lg:col-span-2" aria-labelledby="personal-heading">
            <div class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-brand-100/60 blur-3xl" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <?= avatar($personal['employee']['full_name'], 'size-14 text-base') ?>
                <div class="min-w-0">
                    <h2 id="personal-heading" class="text-lg font-semibold text-slate-900"><?= esc($personal['employee']['full_name']) ?></h2>
                    <p class="text-sm text-slate-500">
                        <?= esc($personal['employee']['position_name']) ?> · <?= esc($personal['employee']['department_name']) ?> · <?= esc($personal['employee']['nip']) ?>
                    </p>
                </div>
                <div class="ml-auto"><?= status_badge($personal['employee']['employment_type']) ?></div>
            </div>

            <dl class="relative mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach ([
                    ['Hadir', $attendance['present'] + $attendance['late'], 'hari', 'text-emerald-700'],
                    ['Terlambat', $attendance['late'], 'kali', 'text-amber-700'],
                    ['Sakit / Izin / Cuti', $attendance['sick'] + $attendance['permit'] + $attendance['leave'], 'hari', 'text-sky-700'],
                    ['Lembur disetujui', number_id($personal['overtime'], 1), 'jam', 'text-violet-700'],
                ] as [$label, $value, $unit, $color]): ?>
                    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
                        <dt class="text-xs font-medium text-slate-500"><?= esc($label) ?></dt>
                        <dd class="mt-1 text-xl font-bold tabular-nums <?= $color ?>"><?= esc((string) $value) ?> <span class="text-xs font-medium text-slate-500"><?= esc($unit) ?></span></dd>
                    </div>
                <?php endforeach ?>
            </dl>
            <p class="relative mt-4 text-xs text-slate-500">Periode <?= esc(tanggal_id(date('Y-m-01', strtotime($today)))) ?> – <?= esc(tanggal_id($today)) ?></p>

            <?php if ($personal['loan']): ?>
                <?php $paid = 1 - ($personal['loan']['remaining'] / max($personal['loan']['amount'], 1)); ?>
                <div class="relative mt-5 rounded-xl bg-linear-to-r from-brand-50 to-violet-50 p-4 ring-1 ring-brand-100">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-800">Pinjaman <?= esc($personal['loan']['loan_no']) ?></span>
                        <span class="text-slate-600">Sisa <span class="font-semibold text-slate-900"><?= rupiah($personal['loan']['remaining']) ?></span></span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-linear-to-r from-brand-500 to-violet-500" style="width: <?= number_format($paid * 100, 1, '.', '') ?>%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Cicilan <?= rupiah($personal['loan']['installment']) ?>/bulan · <?= esc(status_meta($personal['loan']['status'])['label']) ?></p>
                </div>
            <?php endif ?>
        </section>
    <?php endif ?>

    <div class="<?= $personal ? 'space-y-6' : 'contents' ?>">
    <?php if ($people): ?>
        <section class="card p-5" aria-labelledby="people-heading">
            <h2 id="people-heading" class="text-base font-semibold text-slate-900">Sorotan Karyawan</h2>
            <p class="mt-4 text-xs font-semibold tracking-wider text-slate-500 uppercase">Ulang tahun <?= esc(bulan_id((int) date('n', strtotime($today)))) ?></p>
            <?php if ($people['birthdays'] === []): ?>
                <p class="mt-2 text-sm text-slate-500">Tidak ada yang berulang tahun bulan ini.</p>
            <?php else: ?>
                <ul class="mt-2 space-y-2.5">
                    <?php foreach ($people['birthdays'] as $person): ?>
                        <li class="flex items-center gap-3">
                            <?= avatar($person['full_name'], 'size-8 text-[11px]') ?>
                            <div class="min-w-0 flex-1">
                                <a href="<?= url_to('employees.show', $person['id']) ?>" class="block truncate text-sm font-semibold text-slate-800 hover:text-brand-700"><?= esc($person['full_name']) ?></a>
                                <p class="text-xs text-slate-500"><?= esc($person['department_name']) ?></p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-slate-600"><?= esc(tanggal_id(date('Y') . substr($person['birth_date'], 4))) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
            <p class="mt-5 text-xs font-semibold tracking-wider text-slate-500 uppercase">Karyawan baru (90 hari)</p>
            <?php if ($people['newHires'] === []): ?>
                <p class="mt-2 text-sm text-slate-500">Belum ada karyawan baru.</p>
            <?php else: ?>
                <ul class="mt-2 space-y-2.5">
                    <?php foreach ($people['newHires'] as $person): ?>
                        <li class="flex items-center gap-3">
                            <?= avatar($person['full_name'], 'size-8 text-[11px]') ?>
                            <div class="min-w-0 flex-1">
                                <a href="<?= url_to('employees.show', $person['id']) ?>" class="block truncate text-sm font-semibold text-slate-800 hover:text-brand-700"><?= esc($person['full_name']) ?></a>
                                <p class="text-xs text-slate-500"><?= esc($person['position_name']) ?></p>
                            </div>
                            <span class="shrink-0 text-xs text-slate-600">Masuk <?= esc(tanggal_id($person['join_date'])) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    <?php endif ?>

    <!-- Hari libur mendatang -->
    <section class="card p-5 <?= $personal ? '' : ($people ? 'lg:col-span-2' : 'lg:col-span-3') ?>" aria-labelledby="holiday-heading">
        <h2 id="holiday-heading" class="text-base font-semibold text-slate-900">Hari Libur Mendatang</h2>
        <?php if ($holidays === []): ?>
            <p class="mt-4 text-sm text-slate-500">Tidak ada hari libur terjadwal.</p>
        <?php else: ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($holidays as $holiday): ?>
                    <?php $date = new DateTimeImmutable($holiday['date']); ?>
                    <li class="flex items-center gap-3">
                        <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-rose-50 text-rose-700 ring-1 ring-rose-100">
                            <span class="text-[10px] font-semibold uppercase"><?= esc(bulan_id((int) $date->format('n'), true)) ?></span>
                            <span class="text-base leading-none font-bold"><?= $date->format('j') ?></span>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800"><?= esc($holiday['name']) ?></p>
                            <p class="text-xs text-slate-500"><?= esc(hari_id((int) $date->format('N'))) ?> · <?= esc(HolidayModel::TYPES[$holiday['type']] ?? $holiday['type']) ?></p>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
    </div>
</div>
<?php $this->endSection() ?>

<?php if ($latest): ?>
    <?php $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('payroll-cost-chart');
            if (!canvas || !window.Chart) return;

            const periods = <?= json_encode(array_map(static fn (array $p): array => [
                'label'   => bulan_id((int) $p['month'], true) . ' ' . $p['year'],
                'name'    => $p['name'],
                'thp'     => $p['thp'],
                'tax'     => $p['pph21'] + $p['bpjs_employee'],
                'company' => $p['bpjs_company'],
                'total'   => $p['employer_cost'],
            ], $payroll['periods']), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

            const ink = { primary: '#0f172a', secondary: '#64748b', grid: '#eef0f4', surface: '#ffffff' };
            const rupiah = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
            const compact = (v) => v >= 1e9 ? (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M'
                : v >= 1e6 ? (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' jt' : v.toLocaleString('id-ID');

            // Slot kategorikal 1–3 (lolos validasi all-pairs) dengan celah 2px warna permukaan antar segmen
            const series = [
                { key: 'thp', label: 'Gaji bersih diterima', color: '#2a78d6' },
                { key: 'tax', label: 'PPh 21 & iuran BPJS karyawan', color: '#eb6834' },
                { key: 'company', label: 'Kontribusi BPJS perusahaan', color: '#1baf7a' },
            ];

            // Label total di atas setiap kolom (label selektif; rincian di tooltip & tabel)
            const totalLabels = {
                id: 'totalLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    const meta = chart.getDatasetMeta(series.length - 1);
                    ctx.save();
                    ctx.font = '600 12px "Plus Jakarta Sans", system-ui, sans-serif';
                    ctx.fillStyle = ink.primary;
                    ctx.textAlign = 'center';
                    meta.data.forEach((bar, i) => ctx.fillText(compact(periods[i].total), bar.x, bar.y - 8));
                    ctx.restore();
                },
            };

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: periods.map((p) => p.label),
                    datasets: series.map((s, index) => ({
                        label: s.label,
                        data: periods.map((p) => p[s.key]),
                        backgroundColor: s.color,
                        hoverBackgroundColor: s.color + 'd9',
                        borderColor: ink.surface,
                        borderWidth: index === 0 ? 0 : { top: 0, bottom: 2, left: 0, right: 0 },
                        borderSkipped: false,
                        borderRadius: index === series.length - 1 ? { topLeft: 4, topRight: 4 } : 0,
                        maxBarThickness: 24,
                        categoryPercentage: 0.6,
                    })),
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { top: 24 } },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
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
                                title: (items) => periods[items[0].dataIndex].name,
                                label: (item) => rupiah(item.raw) + '  ·  ' + item.dataset.label,
                                labelPointStyle: () => ({ pointStyle: 'line', rotation: 0 }),
                                footer: (items) => 'Biaya perusahaan  ' + rupiah(periods[items[0].dataIndex].total),
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            border: { color: '#cbd5e1' },
                            ticks: { color: ink.secondary, font: { size: 12 } },
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
