<?php
/**
 * @var int                        $year
 * @var list<int>                  $years
 * @var list<array<string, mixed>> $holidays
 * @var string|null                $lockedUntil
 */

use App\Models\HolidayModel;

$this->extend('layouts/app');
$this->section('content');

$byDate = array_column($holidays, null, 'date');
$items  = [];

foreach ($holidays as $holiday) {
    $items[$holiday['id']] = ['id' => $holiday['id'], 'date' => $holiday['date'], 'name' => $holiday['name'], 'type' => $holiday['type']];
}

$typeStyle = [
    'national'         => ['bg-rose-600 text-white', 'bg-rose-600'],
    'collective_leave' => ['bg-amber-300 text-amber-950', 'bg-amber-300'],
    'company'          => ['bg-brand-600 text-white', 'bg-brand-600'],
];

$today       = date('Y-m-d');
$countByType = array_count_values(array_column($holidays, 'type'));
$weekdayOff  = count(array_filter($holidays, static fn (array $h): bool => (int) date('N', strtotime($h['date'])) <= 5));
?>
<div x-data="crudModal({
        empty: { id: null, date: '', name: '', type: 'national' },
        items: <?= js_data($items) ?>,
        storeUrl: '<?= esc(url_to('holidays.store'), 'js') ?>',
        updateUrl: '<?= esc(site_url('holidays/__ID__'), 'js') ?>',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Master Data',
        'title'    => 'Hari Libur ' . $year,
        'subtitle' => 'Libur nasional, cuti bersama, dan libur perusahaan mengurangi jumlah hari kerja saat menghitung prorata dan tunjangan harian.',
        'actions'  => '<div class="flex items-center gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200">'
            . implode('', array_map(static fn (int $y): string => '<a href="' . esc(url_to('holidays.index') . '?year=' . $y, 'attr') . '" class="rounded-lg px-3 py-1.5 text-sm font-semibold ' . ($y === $year ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100') . '"' . ($y === $year ? ' aria-current="page"' : '') . '>' . $y . '</a>', $years))
            . '</div><button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Tambah Hari Libur</button>',
    ]) ?>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?= component('kpi_card', ['label' => 'Total hari libur', 'value' => count($holidays) . ' hari', 'icon' => 'calendar-days', 'tone' => 'brand', 'hint' => $weekdayOff . ' jatuh di hari kerja']) ?>
        <?= component('kpi_card', ['label' => 'Libur nasional', 'value' => ($countByType['national'] ?? 0) . ' hari', 'icon' => 'flag', 'tone' => 'rose']) ?>
        <?= component('kpi_card', ['label' => 'Cuti bersama', 'value' => ($countByType['collective_leave'] ?? 0) . ' hari', 'icon' => 'tree-palm', 'tone' => 'amber']) ?>
        <?= component('kpi_card', ['label' => 'Libur perusahaan', 'value' => ($countByType['company'] ?? 0) . ' hari', 'icon' => 'building-2', 'tone' => 'violet']) ?>
    </div>

    <?php if ($lockedUntil): ?>
        <div class="mb-6 flex gap-3 rounded-xl bg-sky-50 p-3.5 text-sm text-sky-800 ring-1 ring-sky-200">
            <?= icon('info', 'mt-0.5 size-4 shrink-0') ?>
            <p>Hari libur sampai <span class="font-semibold"><?= esc(tanggal_id($lockedUntil, 'long')) ?></span> tidak bisa diubah karena periode payroll-nya sudah disetujui/dibayar.</p>
        </div>
    <?php endif ?>

    <div class="grid gap-6 xl:grid-cols-3">
        <!-- Kalender tahunan -->
        <section class="card p-5 xl:col-span-2" aria-label="Kalender <?= $year ?>">
            <div class="mb-4 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                <?php foreach (HolidayModel::TYPES as $type => $label): ?>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm <?= $typeStyle[$type][1] ?>"></span><?= esc($label) ?></span>
                <?php endforeach ?>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-slate-200"></span>Akhir pekan</span>
                <span class="ml-auto text-slate-500">Klik tanggal untuk menambah / mengubah</span>
            </div>
            <div class="grid gap-x-6 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
                <?php for ($month = 1; $month <= 12; $month++): ?>
                    <?php
                    $first  = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
                    $offset = (int) $first->format('N') - 1;
                    $days   = (int) $first->format('t');
                    ?>
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-800"><?= esc(bulan_id($month)) ?></p>
                        <div class="grid grid-cols-7 gap-0.5 text-center text-[11px]">
                            <?php foreach (['S', 'S', 'R', 'K', 'J', 'S', 'M'] as $i => $dayLabel): ?>
                                <span class="pb-1 font-semibold <?= $i >= 5 ? 'text-rose-500' : 'text-slate-500' ?>"><?= $dayLabel ?></span>
                            <?php endforeach ?>
                            <?php for ($i = 0; $i < $offset; $i++): ?><span></span><?php endfor ?>
                            <?php for ($day = 1; $day <= $days; $day++): ?>
                                <?php
                                $date    = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                $holiday = $byDate[$date] ?? null;
                                $weekend = (int) date('N', strtotime($date)) >= 6;
                                $classes = $holiday
                                    ? $typeStyle[$holiday['type']][0] . ' font-bold shadow-sm'
                                    : ($weekend ? 'bg-slate-100 text-slate-600' : 'text-slate-700 hover:bg-brand-50 hover:text-brand-700');
                                ?>
                                <button type="button"
                                        class="aspect-square rounded-md text-[11px] tabular-nums transition <?= $classes ?> <?= $date === $today ? 'ring-2 ring-brand-500 ring-offset-1' : '' ?>"
                                        title="<?= esc($holiday ? tanggal_id($date, 'long') . ' — ' . $holiday['name'] : tanggal_id($date, 'full'), 'attr') ?>"
                                        aria-label="<?= esc($holiday ? $holiday['name'] . ', ' . tanggal_id($date, 'long') : 'Tambah libur ' . tanggal_id($date, 'long'), 'attr') ?>"
                                        @click="<?= $holiday ? 'edit(' . (int) $holiday['id'] . ')' : "create(); form.date = '{$date}'" ?>"><?= $day ?></button>
                            <?php endfor ?>
                        </div>
                    </div>
                <?php endfor ?>
            </div>
        </section>

        <!-- Daftar -->
        <section class="card flex flex-col" aria-labelledby="holiday-list">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 id="holiday-list" class="text-base font-semibold text-slate-900">Daftar Hari Libur</h2>
                <p class="mt-0.5 text-xs text-slate-500">Verifikasi dengan SKB 3 Menteri tahun berjalan.</p>
            </div>
            <?php if ($holidays === []): ?>
                <?= component('empty_state', ['title' => 'Belum ada hari libur ' . $year, 'icon' => 'calendar-x-2', 'compact' => true, 'action' => '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Tambah</button>']) ?>
            <?php else: ?>
                <ul class="max-h-[46rem] divide-y divide-slate-100 overflow-y-auto">
                    <?php foreach ($holidays as $holiday): ?>
                        <?php $date = new DateTimeImmutable($holiday['date']); ?>
                        <li class="group flex items-center gap-3 px-5 py-3">
                            <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl <?= $typeStyle[$holiday['type']][0] ?>">
                                <span class="text-[9px] font-bold uppercase"><?= esc(bulan_id((int) $date->format('n'), true)) ?></span>
                                <span class="text-sm leading-none font-bold"><?= $date->format('j') ?></span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-800"><?= esc($holiday['name']) ?></p>
                                <p class="text-xs text-slate-500"><?= esc(hari_id((int) $date->format('N'))) ?> · <?= esc(HolidayModel::TYPES[$holiday['type']]) ?></p>
                            </div>
                            <div class="flex gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100">
                                <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="edit(<?= (int) $holiday['id'] ?>)" aria-label="Ubah <?= esc($holiday['name'], 'attr') ?>"><?= icon('pencil', 'size-4') ?></button>
                                <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus <?= esc($holiday['name'], 'attr') ?>"
                                        @click="$dispatch('confirm', { title: 'Hapus hari libur?', message: '<?= esc($holiday['name'] . ' — ' . tanggal_id($holiday['date'], 'long'), 'js') ?>', action: '<?= esc(url_to('holidays.delete', $holiday['id']), 'js') ?>', confirmLabel: 'Hapus', tone: 'danger' })">
                                    <?= icon('trash-2', 'size-4') ?>
                                </button>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>
    </div>

    <?= component('modal_open', [
        'title'     => 'Hari Libur',
        'titleExpr' => "mode === 'create' ? 'Tambah Hari Libur' : 'Ubah Hari Libur'",
        'action'    => 'action',
    ]) ?>
        <?= component('form_input', ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true, 'attrs' => ['id' => 'holiday-date', 'x-model' => 'form.date']]) ?>
        <?= component('form_input', ['name' => 'name', 'label' => 'Keterangan', 'required' => true, 'placeholder' => 'mis. Hari Raya Natal', 'attrs' => ['id' => 'holiday-name', 'x-model' => 'form.name', 'maxlength' => 150]]) ?>
        <?= component('form_select', ['name' => 'type', 'label' => 'Jenis libur', 'options' => HolidayModel::TYPES, 'required' => true, 'attrs' => ['id' => 'holiday-type', 'x-model' => 'form.type']]) ?>
    <?= component('modal_close', ['submitLabel' => 'Simpan Hari Libur']) ?>
</div>
<?php $this->endSection() ?>
