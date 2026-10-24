<?php
/**
 * @var array{rows: list<array<string, mixed>>, summary: array<string, int>, filename: string}|null $preview
 * @var int          $maxRows
 * @var list<string> $columns
 * @var string       $today
 */

use App\Models\AttendanceModel;

$this->extend('layouts/app');
$this->section('content');

$columnHelp = [
    'nip'        => ['Wajib', 'NIP karyawan', 'EMP-0014'],
    'nama'       => ['Opsional', 'Hanya informasi, tidak dibaca', 'Andi Pratama'],
    'tanggal'    => ['Wajib', 'YYYY-MM-DD atau DD/MM/YYYY', '2026-10-05'],
    'status'     => ['Wajib', 'hadir, terlambat, sakit, cuti, izin, alpha, libur (atau H/T/S/C/I/A/L)', 'hadir'],
    'jam_masuk'  => ['Opsional', 'JJ:MM — hanya untuk hadir/terlambat', '07:55'],
    'jam_pulang' => ['Opsional', 'JJ:MM — setelah jam masuk', '17:10'],
    'keterangan' => ['Opsional', 'Maks. 255 karakter', 'Surat dokter'],
];

$actionMeta = [
    'insert' => ['Baru', 'emerald'],
    'update' => ['Diubah', 'amber'],
    'same'   => ['Tetap', 'slate'],
    'error'  => ['Error', 'rose'],
];
?>
<?= component('page_header', [
    'eyebrow'  => 'Kehadiran',
    'title'    => 'Import Kehadiran (CSV)',
    'subtitle' => 'Unggah rekap mesin absensi atau spreadsheet. Setiap baris divalidasi dan ditampilkan sebagai pratinjau sebelum disimpan.',
    'actions'  => '<a href="' . url_to('attendance.index') . '" class="btn btn-secondary">' . icon('arrow-left', 'size-4') . ' Kembali</a>',
]) ?>

<div class="grid gap-6 xl:grid-cols-3">
    <section class="card p-6 xl:col-span-1">
        <ol class="space-y-6">
            <li class="flex gap-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700 ring-1 ring-brand-100">1</span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-slate-900">Unduh template</h2>
                    <p class="mt-1 text-sm text-slate-500">Template berisi seluruh karyawan aktif pada tanggal yang dipilih, siap diisi.</p>
                    <form method="get" action="<?= url_to('attendance.import.template') ?>" class="mt-3 flex gap-2">
                        <label for="template-date" class="sr-only">Tanggal template</label>
                        <input id="template-date" type="date" name="date" value="<?= esc($today, 'attr') ?>" class="form-control w-auto">
                        <button type="submit" class="btn btn-secondary"><?= icon('download', 'size-4') ?> Template</button>
                    </form>
                </div>
            </li>
            <li class="flex gap-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700 ring-1 ring-brand-100">2</span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-slate-900">Isi data</h2>
                    <p class="mt-1 text-sm text-slate-500">Satu baris = satu karyawan per tanggal. Pemisah <code class="rounded bg-slate-100 px-1">;</code> atau <code class="rounded bg-slate-100 px-1">,</code>, maks. <?= number_id($maxRows) ?> baris / 1 MB.</p>
                    <dl class="mt-3 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200">
                        <?php foreach ($columns as $column): ?>
                            <?php [$required, $format, $example] = $columnHelp[$column]; ?>
                            <div class="px-3 py-2 text-xs">
                                <dt class="flex items-center justify-between gap-2">
                                    <code class="font-semibold text-slate-800"><?= esc($column) ?></code>
                                    <span class="<?= $required === 'Wajib' ? 'text-rose-600' : 'text-slate-500' ?>"><?= $required ?></span>
                                </dt>
                                <dd class="mt-0.5 text-slate-500"><?= esc($format) ?> · <span class="font-mono text-slate-600"><?= esc($example) ?></span></dd>
                            </div>
                        <?php endforeach ?>
                    </dl>
                </div>
            </li>
            <li class="flex gap-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700 ring-1 ring-brand-100">3</span>
                <div class="min-w-0 flex-1" x-data="{ name: '', submitting: false }">
                    <h2 class="font-semibold text-slate-900">Unggah & periksa</h2>
                    <form method="post" action="<?= url_to('attendance.import.upload') ?>" enctype="multipart/form-data" class="mt-3 space-y-3" @submit="submitting = true">
                        <?= csrf_field() ?>
                        <label for="import-file" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 px-4 py-6 text-center transition hover:border-brand-400 hover:bg-brand-50/40">
                            <?= icon('file-spreadsheet', 'size-7 text-brand-500') ?>
                            <span class="mt-2 text-sm font-semibold text-slate-800" x-text="name || 'Pilih file CSV'">Pilih file CSV</span>
                            <span class="text-xs text-slate-500">.csv / .txt, maks. 1 MB</span>
                            <input id="import-file" type="file" name="file" accept=".csv,.txt,text/csv" required class="sr-only" @change="name = $event.target.files[0]?.name ?? ''">
                        </label>
                        <button type="submit" class="btn btn-primary w-full" :disabled="!name || submitting"><?= icon('scan-search', 'size-4') ?> Validasi & Pratinjau</button>
                    </form>
                </div>
            </li>
        </ol>
    </section>

    <section id="pratinjau" class="scroll-mt-24 xl:col-span-2" x-data="{ filter: 'all' }">
        <?php if ($preview === null): ?>
            <div class="card h-full">
                <?= component('empty_state', [
                    'title'       => 'Belum ada file yang diunggah',
                    'description' => 'Pratinjau validasi per baris akan tampil di sini. Data baru disimpan setelah Anda menekan tombol Simpan.',
                    'icon'        => 'table-properties',
                ]) ?>
            </div>
        <?php else: ?>
            <?php $s = $preview['summary']; ?>
            <div class="card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-slate-900">Pratinjau: <?= esc($preview['filename']) ?></h2>
                        <p class="mt-0.5 text-sm text-slate-500"><?= number_id($s['total']) ?> baris · <?= number_id($s['valid']) ?> valid · <?= number_id($s['errors']) ?> error</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="post" action="<?= url_to('attendance.import.cancel') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary">Batalkan</button>
                        </form>
                        <?php $saveable = $s['insert'] + $s['update']; ?>
                        <button type="button" class="btn btn-primary" <?= $saveable === 0 ? 'disabled' : '' ?>
                                @click="$dispatch('confirm', {
                                    title: 'Simpan <?= $saveable ?> baris kehadiran?',
                                    message: '<?= esc(sprintf('%d baris baru dan %d baris diubah akan disimpan.%s', $s['insert'], $s['update'], $s['errors'] > 0 ? " {$s['errors']} baris error dilewati." : ''), 'js') ?>',
                                    action: '<?= esc(url_to('attendance.import.commit'), 'js') ?>',
                                    confirmLabel: 'Ya, simpan'
                                })">
                            <?= icon('check', 'size-4') ?> Simpan <?= number_id($saveable) ?> baris
                        </button>
                    </div>
                </div>

                <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-100 px-3 pt-3" role="tablist" aria-label="Filter hasil validasi">
                    <?php foreach (['all' => ['Semua', $s['total']], 'error' => ['Error', $s['errors']], 'insert' => ['Baru', $s['insert']], 'update' => ['Diubah', $s['update']], 'same' => ['Tetap', $s['same']]] as $key => [$label, $count]): ?>
                        <button type="button" role="tab" @click="filter = '<?= $key ?>'" :aria-selected="filter === '<?= $key ?>'"
                                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 pb-3 text-sm font-semibold transition"
                                :class="filter === '<?= $key ?>' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'">
                            <?= esc($label) ?> <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><?= number_id($count) ?></span>
                        </button>
                    <?php endforeach ?>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
                            <tr class="text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                <th scope="col" class="px-4 py-3 text-right">Baris</th>
                                <th scope="col" class="px-4 py-3">Karyawan</th>
                                <th scope="col" class="px-4 py-3">Tanggal</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Jam</th>
                                <th scope="col" class="px-4 py-3">Hasil</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($preview['rows'] as $row): ?>
                                <?php
                                [$label, $tone] = $actionMeta[$row['action']];
                                $data           = $row['data'];
                                ?>
                                <tr x-show="filter === 'all' || filter === '<?= $row['action'] ?>'" class="<?= $row['action'] === 'error' ? 'bg-rose-50/50' : '' ?>">
                                    <td class="px-4 py-2.5 text-right text-xs text-slate-500 tabular-nums"><?= (int) $row['line'] ?></td>
                                    <td class="px-4 py-2.5">
                                        <p class="font-semibold text-slate-900"><?= esc($row['name'] ?: '—') ?></p>
                                        <p class="font-mono text-xs text-slate-500"><?= esc($row['nip'] ?: '—') ?></p>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-slate-700"><?= $data ? esc(tanggal_id($data['date'])) : esc($row['date'] ?: '—') ?></td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <?php if ($data): ?>
                                            <?= status_badge($data['status']) ?>
                                        <?php else: ?>
                                            <span class="text-slate-600"><?= esc($row['raw']['status'] ?? '—') ?></span>
                                        <?php endif ?>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-slate-600 tabular-nums">
                                        <?= $data && $data['check_in'] ? esc(substr($data['check_in'], 0, 5) . '–' . ($data['check_out'] ? substr($data['check_out'], 0, 5) : '…')) : '—' ?>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset <?= tone_classes($tone) ?>"><?= $label ?></span>
                                        <?php if ($row['action'] === 'update' && ! empty($row['existing'])): ?>
                                            <span class="ml-1 text-xs text-slate-500">dari <?= esc(AttendanceModel::STATUSES[$row['existing']] ?? $row['existing']) ?></span>
                                        <?php endif ?>
                                        <?php foreach ($row['errors'] as $error): ?>
                                            <p class="mt-1 text-xs font-medium text-rose-700"><?= esc($error) ?></p>
                                        <?php endforeach ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif ?>
    </section>
</div>
<?php $this->endSection() ?>
