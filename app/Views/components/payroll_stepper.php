<?php
/**
 * Progres status periode payroll: Draft → Review → Disetujui → Dibayar → Terkunci.
 *
 * @var string                                                  $status
 * @var bool|null                                               $compact  true = bar kecil (kartu/daftar)
 * @var array<string, array{at: string|null, by: string|null}>|null $stamps waktu & pelaku per tahap (mode lengkap)
 */
$compact ??= false;
$stamps  ??= [];
$steps    = ['draft', 'review', 'approved', 'paid', 'locked'];
$position = (int) array_search($status, $steps, true);
$labels   = ['draft' => 'Draft', 'review' => 'Review', 'approved' => 'Disetujui', 'paid' => 'Dibayar', 'locked' => 'Terkunci'];
$icons    = ['draft' => 'file-pen-line', 'review' => 'scan-search', 'approved' => 'badge-check', 'paid' => 'banknote', 'locked' => 'lock'];
?>
<?php if ($compact): ?>
    <ol class="flex items-center gap-1" aria-label="Progres: <?= esc($labels[$status] ?? $status, 'attr') ?> (tahap <?= $position + 1 ?> dari 5)">
        <?php foreach ($steps as $i => $step): ?>
            <li class="h-1.5 w-8 rounded-full <?= $i <= $position ? 'bg-brand-500' : 'bg-slate-200' ?>" title="<?= esc($labels[$step], 'attr') ?>"></li>
        <?php endforeach ?>
    </ol>
<?php else: ?>
    <ol class="grid grid-cols-5" aria-label="Status periode payroll">
        <?php foreach ($steps as $i => $step): ?>
            <?php
            $done    = $i < $position || ($i === $position && $step === 'locked');
            $current = $i === $position && ! $done;
            $stamp   = $stamps[$step] ?? ['at' => null, 'by' => null];
            ?>
            <li class="relative flex flex-col items-center text-center" <?= $current ? 'aria-current="step"' : '' ?>>
                <?php if ($i > 0): ?>
                    <span class="absolute top-5 right-1/2 h-0.5 w-full -translate-y-1/2 <?= $i <= $position ? 'bg-brand-500' : 'bg-slate-200' ?>" aria-hidden="true"></span>
                <?php endif ?>
                <span class="relative z-10 flex size-10 items-center justify-center rounded-full ring-4 ring-white
                    <?= $done ? 'bg-brand-600 text-white' : ($current ? 'bg-linear-to-br from-brand-600 to-violet-600 text-white shadow-glow' : 'bg-slate-100 text-slate-500') ?>">
                    <?= icon($done ? 'check' : $icons[$step], 'size-4.5') ?>
                </span>
                <span class="mt-2 text-xs font-semibold sm:text-sm <?= $i <= $position ? 'text-slate-900' : 'text-slate-500' ?>"><?= esc($labels[$step]) ?></span>
                <?php if ($stamp['at']): ?>
                    <span class="hidden text-[11px] text-slate-500 sm:block"><?= esc(tanggal_id($stamp['at'], 'datetime')) ?></span>
                    <?php if ($stamp['by']): ?>
                        <span class="hidden max-w-32 truncate text-[11px] text-slate-500 md:block"><?= esc($stamp['by']) ?></span>
                    <?php endif ?>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
