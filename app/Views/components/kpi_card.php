<?php
/**
 * Kartu KPI.
 *
 * @var string          $label
 * @var string          $value      nilai yang sudah diformat
 * @var string          $icon       nama ikon Lucide
 * @var string|null     $tone       brand | emerald | amber | rose | sky | violet
 * @var float|null      $delta      perubahan dalam persen (positif = naik)
 * @var string|null     $deltaLabel mis. "vs bulan lalu"
 * @var bool|null       $inverse    true bila kenaikan dianggap buruk (mis. biaya lembur)
 * @var string|null     $hint       teks kecil di bawah nilai
 * @var list<int|float>|null $trend data sparkline
 * @var string|null     $href
 */
$tone       ??= 'brand';
$delta      ??= null;
$deltaLabel ??= 'vs bulan lalu';
$inverse    ??= false;
$hint       ??= null;
$trend      ??= null;
$href       ??= null;

$gradients = [
    'brand'   => ['from-brand-500 to-violet-600', 'text-brand-500'],
    'emerald' => ['from-emerald-500 to-teal-600', 'text-emerald-500'],
    'amber'   => ['from-amber-400 to-orange-500', 'text-amber-500'],
    'rose'    => ['from-rose-500 to-pink-600', 'text-rose-500'],
    'sky'     => ['from-sky-500 to-blue-600', 'text-sky-500'],
    'violet'  => ['from-violet-500 to-fuchsia-600', 'text-violet-500'],
];
[$gradient, $strokeClass] = $gradients[$tone] ?? $gradients['brand'];

$sparkline = null;

if (is_array($trend) && count($trend) > 1) {
    $width  = 112;
    $height = 36;
    $min    = min($trend);
    $range  = max(max($trend) - $min, 1);
    $step   = $width / (count($trend) - 1);
    $points = [];

    foreach (array_values($trend) as $i => $point) {
        $points[] = round($i * $step, 1) . ',' . round($height - 3 - (($point - $min) / $range) * ($height - 6), 1);
    }

    $sparkline = ['line' => implode(' ', $points), 'area' => '0,' . $height . ' ' . implode(' ', $points) . ' ' . $width . ',' . $height, 'w' => $width, 'h' => $height];
}

$tag = $href ? 'a' : 'div';
?>
<<?= $tag ?> <?= $href ? 'href="' . esc($href, 'attr') . '"' : '' ?>
    class="card group relative block overflow-hidden p-5 transition <?= $href ? 'hover:-translate-y-0.5 hover:shadow-elevated' : '' ?>">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500"><?= esc($label) ?></p>
            <p class="mt-2 truncate text-2xl font-bold tracking-tight text-slate-900 tabular-nums xl:text-[1.375rem] 2xl:text-2xl" title="<?= esc($value, 'attr') ?>"><?= esc($value) ?></p>
        </div>
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br <?= $gradient ?> text-white shadow-lg ring-1 ring-white/20">
            <?= icon($icon, 'size-5') ?>
        </span>
    </div>

    <div class="mt-4 flex items-end justify-between gap-3">
        <div class="min-w-0 text-xs">
            <?php if ($delta !== null): ?>
                <?php $good = $inverse ? $delta <= 0 : $delta >= 0; ?>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold <?= $good ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>">
                    <?= icon($delta >= 0 ? 'trending-up' : 'trending-down', 'size-3.5') ?>
                    <?= esc(($delta > 0 ? '+' : '') . number_id($delta, 1)) ?>%
                </span>
                <span class="ml-1 whitespace-nowrap text-slate-500"><?= esc($deltaLabel) ?></span>
            <?php elseif ($hint !== null): ?>
                <span class="text-slate-500"><?= esc($hint) ?></span>
            <?php endif ?>
            <?php if ($delta !== null && $hint !== null): ?>
                <p class="mt-1.5 truncate text-slate-500"><?= esc($hint) ?></p>
            <?php endif ?>
        </div>

        <?php if ($sparkline): ?>
            <svg class="h-9 w-24 shrink-0 <?= $strokeClass ?>" viewBox="0 0 <?= $sparkline['w'] ?> <?= $sparkline['h'] ?>" preserveAspectRatio="none" aria-hidden="true">
                <polygon points="<?= $sparkline['area'] ?>" fill="currentColor" opacity="0.08"/>
                <polyline points="<?= $sparkline['line'] ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
            </svg>
        <?php endif ?>
    </div>
</<?= $tag ?>>
