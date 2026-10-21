<?php
/**
 * @var array<string, array{label: string, icon: string, groups: list<string>}> $tabs
 * @var string                                         $activeTab
 * @var array<string, list<array<string, mixed>>>      $settings
 * @var list<array<string, mixed>>                     $ptkp
 * @var array<string, list<array<string, mixed>>>      $ter
 * @var bool                                           $canEdit
 */
$this->extend('layouts/app');
$this->section('content');

$errors   = (array) (session('errors') ?? []);
$oldPost  = old_input() ?? [];
$disabled = $canEdit ? '' : 'disabled';

$field = static function (array $row) use ($errors, $oldPost, $disabled): string {
    $id    = 'setting-' . str_replace('.', '-', $row['key']);
    $name  = 'settings[' . $row['key'] . ']';
    $value = $oldPost['settings'][$row['key']] ?? $row['value'];
    $error = $errors['settings.' . $row['key']] ?? null;
    $ring  = $error ? 'ring-rose-400 focus:ring-rose-500' : '';

    $input = match ($row['type']) {
        'text'    => '<textarea id="' . $id . '" name="' . esc($name, 'attr') . '" rows="3" class="form-control ' . $ring . '" ' . $disabled . '>' . esc((string) $value) . '</textarea>',
        'decimal' => '<div class="relative"><input id="' . $id . '" name="' . esc($name, 'attr') . '" type="text" inputmode="decimal" value="' . esc(rtrim(rtrim((string) $value, '0'), '.'), 'attr') . '" class="form-control pr-10 text-right tabular-nums ' . $ring . '" ' . $disabled . '><span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-sm text-slate-500">%</span></div>',
        'int'     => '<input id="' . $id . '" name="' . esc($name, 'attr') . '" type="text" inputmode="numeric" value="' . esc((string) $value, 'attr') . '" class="form-control text-right tabular-nums ' . $ring . '" ' . $disabled . '>',
        'time'    => '<input id="' . $id . '" name="' . esc($name, 'attr') . '" type="time" value="' . esc((string) $value, 'attr') . '" class="form-control ' . $ring . '" ' . $disabled . '>',
        default   => '<input id="' . $id . '" name="' . esc($name, 'attr') . '" type="text" value="' . esc((string) $value, 'attr') . '" class="form-control ' . $ring . '" ' . $disabled . '>',
    };

    $hint = '';

    if ($row['type'] === 'int' && is_numeric($row['value']) && (int) $row['value'] >= 10_000) {
        $hint = '<span class="text-slate-700">' . rupiah((int) $row['value']) . '</span>' . ($row['description'] ? ' · ' : '');
    }

    return '<div><label for="' . $id . '" class="form-label">' . esc($row['label']) . '</label>' . $input
        . ($error
            ? '<p class="mt-1.5 text-xs font-medium text-rose-600">' . esc($error) . '</p>'
            : (($hint !== '' || $row['description']) ? '<p class="mt-1.5 text-xs text-slate-500">' . $hint . esc($row['description'] ?? '') . '</p>' : ''))
        . '</div>';
};

$formOpen = static fn (string $tab): string => '<form method="post" action="' . esc(url_to('settings.update', $tab), 'attr') . '" class="card" novalidate>' . csrf_field();
$formFoot = static fn (): string => '<div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">'
    . '<p class="text-xs text-slate-500">Perubahan berlaku untuk payroll yang di-generate berikutnya. Payroll yang sudah dihitung tidak berubah.</p>'
    . ($canEdit ? '<button type="submit" class="btn btn-primary">' . icon('save', 'size-4') . ' Simpan Perubahan</button>' : '')
    . '</div>';
?>
<?= component('page_header', [
    'eyebrow'  => 'Sistem',
    'title'    => 'Pengaturan',
    'subtitle' => 'Profil perusahaan, tarif BPJS, PTKP & tarif TER PPh 21, serta aturan kehadiran.',
]) ?>

<?php if (! $canEdit): ?>
    <div class="mb-6 flex gap-3 rounded-xl bg-sky-50 p-3.5 text-sm text-sky-800 ring-1 ring-sky-200">
        <?= icon('eye', 'mt-0.5 size-4 shrink-0') ?>
        <p>Anda dapat melihat pengaturan, tetapi hanya <span class="font-semibold">Super Admin</span> yang dapat mengubahnya.</p>
    </div>
<?php endif ?>

<div x-data="{ tab: '<?= esc($activeTab, 'js') ?>' }" x-init="$watch('tab', (t) => history.replaceState(null, '', '?tab=' + t))">
    <div class="mb-6 overflow-x-auto">
        <nav class="inline-flex min-w-full gap-1 rounded-2xl bg-white p-1.5 shadow-card ring-1 ring-slate-200/70 sm:min-w-0" role="tablist" aria-label="Kategori pengaturan">
            <?php foreach ($tabs as $key => $tab): ?>
                <button type="button" role="tab" @click="tab = '<?= $key ?>'" :aria-selected="tab === '<?= $key ?>'"
                        class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition"
                        :class="tab === '<?= $key ?>' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'">
                    <?= icon($tab['icon'], 'size-4') ?> <?= esc($tab['label']) ?>
                </button>
            <?php endforeach ?>
        </nav>
    </div>

    <!-- Perusahaan -->
    <section x-show="tab === 'company'" role="tabpanel">
        <?= $formOpen('company') ?>
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-900">Profil Perusahaan</h2>
                <p class="mt-0.5 text-sm text-slate-500">Ditampilkan di slip gaji, laporan, dan halaman login.</p>
            </div>
            <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                <?php foreach ($settings['company'] ?? [] as $row): ?>
                    <div class="<?= $row['type'] === 'text' ? 'md:col-span-2' : '' ?>"><?= $field($row) ?></div>
                <?php endforeach ?>
            </div>
            <?= $formFoot() ?>
        </form>
    </section>

    <!-- BPJS -->
    <section x-show="tab === 'bpjs'" x-cloak role="tabpanel">
        <?= $formOpen('bpjs') ?>
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-900">Tarif BPJS</h2>
                <p class="mt-0.5 text-sm text-slate-500">Persentase dari upah sebulan (gaji pokok + tunjangan tetap). JKK menyesuaikan kelompok risiko perusahaan.</p>
            </div>
            <?php
            $bpjsGroups = [
                'BPJS Kesehatan'     => ['bpjs.kes.company_rate', 'bpjs.kes.employee_rate', 'bpjs.kes.max_wage'],
                'Jaminan Hari Tua'   => ['bpjs.jht.company_rate', 'bpjs.jht.employee_rate'],
                'JKK & JKM'          => ['bpjs.jkk.company_rate', 'bpjs.jkm.company_rate'],
                'Jaminan Pensiun'    => ['bpjs.jp.company_rate', 'bpjs.jp.employee_rate', 'bpjs.jp.max_wage'],
            ];
            $bpjsRows = array_column($settings['bpjs'] ?? [], null, 'key');
            ?>
            <div class="grid gap-6 px-6 py-6 lg:grid-cols-2">
                <?php foreach ($bpjsGroups as $groupLabel => $keys): ?>
                    <fieldset class="rounded-2xl bg-slate-50/70 p-5 ring-1 ring-slate-200/70">
                        <legend class="sr-only"><?= esc($groupLabel) ?></legend>
                        <p class="mb-4 text-sm font-semibold text-slate-800"><?= esc($groupLabel) ?></p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <?php foreach ($keys as $key): ?>
                                <?php if (isset($bpjsRows[$key])): ?>
                                    <div class="<?= $bpjsRows[$key]['type'] === 'int' ? 'sm:col-span-2' : '' ?>"><?= $field($bpjsRows[$key]) ?></div>
                                <?php endif ?>
                            <?php endforeach ?>
                        </div>
                    </fieldset>
                <?php endforeach ?>
            </div>
            <?= $formFoot() ?>
        </form>
    </section>

    <!-- Pajak -->
    <section x-show="tab === 'tax'" x-cloak role="tabpanel" x-data="{ category: 'A' }">
        <?= $formOpen('tax') ?>
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-base font-semibold text-slate-900">PPh 21 — Metode TER</h2>
                <p class="mt-0.5 text-sm text-slate-500">PP 58/2023 & PMK 168/2023. Tarif TER dipakai Januari–November; masa pajak terakhir dihitung ulang setahun dengan tarif Pasal 17.</p>
            </div>
            <div class="grid gap-5 px-6 py-6 md:grid-cols-3">
                <?php foreach ($settings['tax'] ?? [] as $row): ?>
                    <?= $field($row) ?>
                <?php endforeach ?>
            </div>

            <div class="border-t border-slate-100 px-6 py-6">
                <h3 class="text-sm font-semibold text-slate-900">Penghasilan Tidak Kena Pajak (PTKP) per tahun</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($ptkp as $row): ?>
                        <?php $error = $errors['ptkp.' . $row['status']] ?? null; ?>
                        <div class="rounded-xl bg-white p-4 ring-1 <?= $error ? 'ring-rose-300' : 'ring-slate-200' ?>">
                            <div class="flex items-center justify-between">
                                <label for="ptkp-<?= esc(str_replace('/', '-', $row['status']), 'attr') ?>" class="text-sm font-bold text-slate-900"><?= esc($row['status']) ?></label>
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">TER <?= esc($row['ter_category']) ?></span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500"><?= esc($row['description']) ?></p>
                            <input id="ptkp-<?= esc(str_replace('/', '-', $row['status']), 'attr') ?>" name="ptkp[<?= esc($row['status'], 'attr') ?>]" type="text" inputmode="numeric"
                                   value="<?= esc((string) ($oldPost['ptkp'][$row['status']] ?? number_id($row['amount'])), 'attr') ?>"
                                   class="form-control mt-3 text-right font-semibold tabular-nums" <?= $disabled ?>>
                            <?php if ($error): ?><p class="mt-1 text-xs text-rose-600"><?= esc($error) ?></p><?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>

            <div class="border-t border-slate-100 px-6 py-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Tarif Efektif Rata-rata (TER) Bulanan</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Kategori A: TK/0, TK/1, K/0 · B: TK/2, TK/3, K/1, K/2 · C: K/3</p>
                    </div>
                    <div class="inline-flex rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Kategori TER">
                        <?php foreach (array_keys($ter) as $category): ?>
                            <button type="button" role="tab" @click="category = '<?= $category ?>'" :aria-selected="category === '<?= $category ?>'"
                                    class="rounded-lg px-4 py-1.5 text-sm font-semibold" :class="category === '<?= $category ?>' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'">
                                Kategori <?= $category ?>
                            </button>
                        <?php endforeach ?>
                    </div>
                </div>
                <?php foreach ($ter as $category => $rows): ?>
                    <div x-show="category === '<?= $category ?>'" <?= $category === 'A' ? '' : 'x-cloak' ?> class="mt-4 grid gap-x-6 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($rows as $row): ?>
                            <?php $error = $errors['ter.' . $row['id']] ?? null; ?>
                            <div class="flex items-center justify-between gap-3 border-b border-dashed border-slate-200 py-1.5 text-sm">
                                <label for="ter-<?= (int) $row['id'] ?>" class="text-slate-600 tabular-nums">
                                    <?= $row['max_income'] === null ? '&gt; ' . rupiah($row['min_income'] - 1) : rupiah($row['min_income']) . ' – ' . number_id($row['max_income']) ?>
                                </label>
                                <div class="relative w-24 shrink-0">
                                    <input id="ter-<?= (int) $row['id'] ?>" name="ter[<?= (int) $row['id'] ?>]" type="text" inputmode="decimal"
                                           value="<?= esc((string) ($oldPost['ter'][$row['id']] ?? rtrim(rtrim(number_format((float) $row['rate'], 3, '.', ''), '0'), '.')), 'attr') ?>"
                                           class="block w-full rounded-lg border-0 bg-transparent py-1 pr-6 text-right text-sm font-semibold text-slate-900 tabular-nums ring-1 ring-inset <?= $error ? 'ring-rose-400' : 'ring-transparent hover:ring-slate-200 focus:ring-2 focus:ring-brand-500' ?> disabled:hover:ring-transparent" <?= $disabled ?>>
                                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-slate-500">%</span>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endforeach ?>
                <p class="mt-4 flex items-center gap-1.5 text-xs text-amber-700"><?= icon('triangle-alert', 'size-3.5') ?> Verifikasi tabel dengan Lampiran PP 58/2023 sebelum digunakan di production.</p>
            </div>
            <?= $formFoot() ?>
        </form>
    </section>

    <!-- Payroll & Kehadiran -->
    <section x-show="tab === 'attendance'" x-cloak role="tabpanel">
        <?= $formOpen('attendance') ?>
            <div class="grid gap-0 lg:grid-cols-2 lg:divide-x lg:divide-slate-100">
                <?php foreach (['payroll' => ['Payroll', 'Upah minimum acuan, pembagi lembur, dan tanggal gajian.'], 'attendance' => ['Kehadiran', 'Jam kerja dan denda keterlambatan.']] as $group => [$heading, $desc]): ?>
                    <div>
                        <div class="border-b border-slate-100 px-6 py-5">
                            <h2 class="text-base font-semibold text-slate-900"><?= esc($heading) ?></h2>
                            <p class="mt-0.5 text-sm text-slate-500"><?= esc($desc) ?></p>
                        </div>
                        <div class="grid gap-5 px-6 py-6 sm:grid-cols-2">
                            <?php foreach ($settings[$group] ?? [] as $row): ?>
                                <?= $field($row) ?>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <?= $formFoot() ?>
        </form>
    </section>
</div>
<?php $this->endSection() ?>
