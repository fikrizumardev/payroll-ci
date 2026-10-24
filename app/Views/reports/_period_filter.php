<?php
/**
 * Pemilih periode payroll untuk laporan berbasis periode + penanda angka sementara.
 *
 * @var list<array<string, mixed>> $periods
 * @var array<string, mixed>|null  $period
 * @var bool                       $isFinal
 * @var string                     $active
 * @var array<string, array<string, string>> $reports
 */
?>
<div class="card mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
    <form method="get" action="<?= url_to($reports[$active]['route']) ?>" class="flex flex-wrap items-center gap-3">
        <label for="report-period" class="text-sm font-semibold text-slate-700">Periode</label>
        <div class="relative">
            <select id="report-period" name="period" class="form-control w-60 appearance-none pr-10" onchange="this.form.submit()">
                <?php foreach ($periods as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= $option['id'] === $period['id'] ? 'selected' : '' ?>>
                        <?= esc($option['name']) ?> · <?= esc(status_meta($option['status'])['label']) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
        </div>
        <noscript><button type="submit" class="btn btn-secondary">Tampilkan</button></noscript>
    </form>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
        <span class="flex items-center gap-2">Status <?= status_badge($period['status']) ?></span>
        <span><?= (int) $period['employees'] ?> karyawan</span>
        <span>Gajian <?= esc(tanggal_id($period['pay_date'])) ?></span>
        <a href="<?= url_to('payroll.show', $period['id']) ?>" class="font-semibold text-brand-600 hover:text-brand-700">Buka periode →</a>
    </div>
</div>

<?php if (! $isFinal): ?>
    <div class="mb-6 flex gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
        <?= icon('triangle-alert', 'mt-0.5 size-5 shrink-0') ?>
        <p><span class="font-semibold">Angka sementara.</span> Payroll <?= esc($period['name']) ?> masih berstatus <?= esc(status_meta($period['status'])['label']) ?> dan dapat berubah bila di-generate ulang. Gunakan laporan setelah payroll disetujui Finance untuk pelaporan resmi.</p>
    </div>
<?php endif ?>
