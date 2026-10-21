<?php
/**
 * @var list<array<string, mixed>>       $periods
 * @var array<int, array<string, int>>   $stats        period_id → ringkasan payroll
 * @var string                           $defaultMonth YYYY-MM
 * @var array<string, string>            $payDates     YYYY-MM → tanggal bayar default
 * @var list<string>                     $existing     YYYY-MM periode yang sudah ada
 * @var bool                             $canCreate
 */
$this->extend('layouts/app');
$this->section('content');

$open    = array_values(array_filter($periods, static fn (array $p): bool => $p['status'] !== 'locked'));
$errors  = session('errors') ?? [];
$hints   = [
    'draft'    => 'Generate, periksa, lalu ajukan ke Finance',
    'review'   => 'Menunggu persetujuan Finance',
    'approved' => 'Ekspor file bank & tandai dibayar',
    'paid'     => 'Kunci periode untuk membukukan cicilan',
    'locked'   => 'Final · read-only',
];
?>
<div x-data="crudModal({
        empty: { month: '<?= esc($defaultMonth, 'js') ?>', pay_date: '<?= esc($payDates[$defaultMonth] ?? '', 'js') ?>', notes: '' },
        storeUrl: '<?= esc(url_to('payroll.store'), 'js') ?>',
        updateUrl: '',
        reopen: <?= js_data(session('open_modal')) ?>,
        old: <?= js_data(old_input()) ?>,
     })">

    <?= component('page_header', [
        'eyebrow'  => 'Payroll',
        'title'    => 'Periode Payroll',
        'subtitle' => 'Setiap periode melalui alur Draft → Review → Disetujui → Dibayar → Terkunci. Periode terkunci menjadi read-only.',
        'actions'  => $canCreate ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Buat Periode</button>' : null,
    ]) ?>

    <?php if ($periods === []): ?>
        <div class="card">
            <?= component('empty_state', [
                'title'       => 'Belum ada periode payroll',
                'description' => 'Buat periode pertama untuk menghitung gaji seluruh karyawan aktif.',
                'icon'        => 'banknote',
                'action'      => $canCreate ? '<button type="button" class="btn btn-primary" @click="create()">' . icon('plus', 'size-4') . ' Buat Periode</button>' : null,
            ]) ?>
        </div>
    <?php else: ?>
        <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
            <?php foreach ($periods as $period): ?>
                <?php
                $s       = $stats[$period['id']] ?? ['employees' => 0, 'thp' => 0, 'gross' => 0, 'pph21' => 0, 'company' => 0, 'warned' => 0];
                $current = $open !== [] && $period['id'] === $open[0]['id'];
                ?>
                <article class="card group relative flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-elevated <?= $current ? 'ring-2 ring-brand-500/60' : '' ?>">
                    <?php if ($current): ?>
                        <span class="absolute inset-x-0 top-0 h-1 bg-linear-to-r from-brand-600 via-violet-600 to-fuchsia-600" aria-hidden="true"></span>
                    <?php endif ?>
                    <div class="flex items-start justify-between gap-3 p-5 pb-4">
                        <div class="flex min-w-0 items-center gap-3.5">
                            <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl <?= $current ? 'bg-linear-to-br from-brand-600 to-violet-600 text-white shadow-glow' : 'bg-slate-100 text-slate-700' ?>">
                                <span class="text-[10px] font-bold uppercase"><?= esc(bulan_id((int) $period['month'], true)) ?></span>
                                <span class="text-xs font-bold"><?= (int) $period['year'] ?></span>
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-slate-900">
                                    <a href="<?= url_to('payroll.show', $period['id']) ?>" class="after:absolute after:inset-0 focus-visible:outline-none"><?= esc($period['name']) ?></a>
                                </h2>
                                <p class="text-xs text-slate-500"><?= esc(tanggal_id($period['start_date'])) ?> – <?= esc(tanggal_id($period['end_date'])) ?> · <?= (int) $period['workdays'] ?> hari kerja</p>
                            </div>
                        </div>
                        <?= status_badge($period['status']) ?>
                    </div>

                    <div class="px-5">
                        <p class="text-xs text-slate-500">Total take home pay</p>
                        <p class="mt-0.5 text-2xl font-bold tracking-tight text-slate-900 tabular-nums"><?= $s['employees'] > 0 ? rupiah($s['thp']) : '—' ?></p>
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-px border-y border-slate-100 bg-slate-100 text-center">
                        <?php foreach ([['Karyawan', $s['employees'] > 0 ? (string) $s['employees'] : '—'], ['PPh 21', $s['employees'] > 0 ? rupiah($s['pph21']) : '—'], ['Gajian', tanggal_id($period['pay_date'])]] as [$label, $value]): ?>
                            <div class="bg-white px-2 py-3">
                                <dt class="text-[11px] text-slate-500"><?= esc($label) ?></dt>
                                <dd class="mt-0.5 truncate text-sm font-semibold text-slate-900 tabular-nums"><?= esc($value) ?></dd>
                            </div>
                        <?php endforeach ?>
                    </dl>

                    <div class="mt-auto flex items-center justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <?= component('payroll_stepper', ['status' => $period['status'], 'compact' => true]) ?>
                            <p class="mt-1.5 truncate text-xs text-slate-500">
                                <?php if ($s['employees'] === 0 && $period['status'] === 'draft'): ?>
                                    Belum di-generate
                                <?php elseif ($s['warned'] > 0 && in_array($period['status'], ['draft', 'review'], true)): ?>
                                    <span class="font-semibold text-amber-700"><?= $s['warned'] ?> peringatan</span> · <?= esc($hints[$period['status']]) ?>
                                <?php else: ?>
                                    <?= esc($hints[$period['status']]) ?>
                                <?php endif ?>
                            </p>
                        </div>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500 ring-1 ring-slate-200 transition group-hover:bg-brand-600 group-hover:text-white group-hover:ring-brand-600" aria-hidden="true">
                            <?= icon('arrow-right', 'size-4') ?>
                        </span>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($canCreate): ?>
        <?= component('modal_open', [
            'title'    => 'Buat Periode Payroll',
            'subtitle' => 'Periode mencakup satu bulan kalender. Tanggal bayar default = hari kerja terakhir bulan itu.',
            'action'   => 'action',
        ]) ?>
            <div x-data="{ payDates: <?= js_data((object) $payDates) ?>, existing: <?= js_data($existing) ?> }" class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="period-month" class="form-label">Bulan <span class="text-rose-500" aria-hidden="true">*</span></label>
                        <input id="period-month" type="month" name="month" required x-model="form.month" @change="if (payDates[form.month]) form.pay_date = payDates[form.month]"
                               class="form-control <?= isset($errors['month']) ? 'ring-rose-400' : '' ?>">
                        <p x-show="existing.includes(form.month)" x-cloak class="mt-1.5 text-xs font-medium text-rose-600">Periode bulan ini sudah ada.</p>
                        <?php if (isset($errors['month'])): ?>
                            <p class="mt-1.5 text-xs font-medium text-rose-600"><?= esc($errors['month']) ?></p>
                        <?php endif ?>
                    </div>
                    <?= component('form_input', ['name' => 'pay_date', 'label' => 'Tanggal bayar', 'type' => 'date', 'required' => true, 'attrs' => ['id' => 'period-pay-date', 'x-model' => 'form.pay_date']]) ?>
                </div>
                <div>
                    <label for="period-notes" class="form-label">Catatan</label>
                    <textarea id="period-notes" name="notes" rows="2" maxlength="500" class="form-control" x-model="form.notes" placeholder="mis. Termasuk pembayaran THR"></textarea>
                </div>
                <p class="flex gap-2 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 ring-1 ring-slate-200/70">
                    <?= icon('info', 'size-4 shrink-0 text-brand-500') ?>
                    Setelah dibuat, klik Generate di halaman periode. Karyawan yang masuk/keluar di tengah bulan otomatis dihitung prorata.
                </p>
            </div>
        <?= component('modal_close', ['submitLabel' => 'Buat Periode']) ?>
    <?php endif ?>
</div>
<?php $this->endSection() ?>
