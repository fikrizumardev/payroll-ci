<?php
/**
 * Input teks/angka/tanggal dengan label, ikon, bantuan, dan error validasi.
 *
 * @var string                $name
 * @var string                $label
 * @var string|null           $type        text | number | date | password | time | …
 * @var string|int|float|null $value       nilai awal (old() didahulukan)
 * @var string|null           $placeholder
 * @var string|null           $help
 * @var string|null           $icon        ikon Lucide di kiri input
 * @var string|null           $suffix      teks di kanan input (mis. "jam")
 * @var bool|null             $required
 * @var string|null           $error       error manual; default dari session('errors')[$name]
 * @var array<string, scalar>|null $attrs  atribut tambahan
 */
$type        ??= 'text';
$value       ??= null;
$placeholder ??= null;
$help        ??= null;
$icon        ??= null;
$suffix      ??= null;
$required    ??= false;
$attrs       ??= [];
$error       ??= (session('errors') ?? [])[$name] ?? null;
$id          = $attrs['id'] ?? 'field-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
$current     = old($name, $value, false);

$extra = '';

foreach ($attrs as $attr => $attrValue) {
    // Nama atribut divalidasi (bukan di-escape) agar directive Alpine seperti @change / :disabled tetap utuh
    if ($attr === 'id' || ! preg_match('/^[@:a-zA-Z_][\w:.@-]*$/', (string) $attr)) {
        continue;
    }

    $extra .= ' ' . $attr . '="' . esc((string) $attrValue, 'attr') . '"';
}
?>
<div>
    <label for="<?= esc($id, 'attr') ?>" class="form-label">
        <?= esc($label) ?><?php if ($required): ?> <span class="text-rose-500" aria-hidden="true">*</span><?php endif ?>
    </label>
    <div class="relative">
        <?php if ($icon): ?>
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"><?= icon($icon, 'size-4') ?></span>
        <?php endif ?>
        <input id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" type="<?= esc($type, 'attr') ?>"
               value="<?= esc((string) ($current ?? ''), 'attr') ?>"
               class="form-control <?= $icon ? 'pl-10' : '' ?> <?= $suffix ? 'pr-14' : '' ?> <?= $error ? 'ring-rose-400 focus:ring-rose-500' : '' ?>"
               <?= $placeholder ? 'placeholder="' . esc($placeholder, 'attr') . '"' : '' ?>
               <?= $required ? 'required' : '' ?>
               <?= $error ? 'aria-invalid="true" aria-describedby="' . esc($id, 'attr') . '-error"' : ($help ? 'aria-describedby="' . esc($id, 'attr') . '-help"' : '') ?>
               <?= $extra ?>>
        <?php if ($suffix): ?>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-sm text-slate-500"><?= esc($suffix) ?></span>
        <?php endif ?>
    </div>
    <?php if ($error): ?>
        <p id="<?= esc($id, 'attr') ?>-error" class="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600"><?= icon('circle-alert', 'size-3.5') ?> <?= esc($error) ?></p>
    <?php elseif ($help): ?>
        <p id="<?= esc($id, 'attr') ?>-help" class="mt-1.5 text-xs text-slate-500"><?= esc($help) ?></p>
    <?php endif ?>
</div>
