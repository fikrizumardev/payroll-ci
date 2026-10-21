<?php
/**
 * Select dengan label, placeholder, bantuan, dan error validasi.
 *
 * @var string                    $name
 * @var string                    $label
 * @var array<int|string, string> $options     value => label
 * @var string|int|null           $value
 * @var string|null               $placeholder opsi kosong di awal
 * @var string|null               $help
 * @var bool|null                 $required
 * @var string|null               $error
 * @var array<string, scalar>|null $attrs
 */
$value       ??= null;
$placeholder ??= null;
$help        ??= null;
$required    ??= false;
$attrs       ??= [];
$error       ??= (session('errors') ?? [])[$name] ?? null;
$id          = $attrs['id'] ?? 'field-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
$current     = (string) old($name, $value, false);

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
        <select id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>"
                class="form-control appearance-none pr-10 <?= $error ? 'ring-rose-400 focus:ring-rose-500' : '' ?>"
                <?= $required ? 'required' : '' ?> <?= $error ? 'aria-invalid="true"' : '' ?> <?= $extra ?>>
            <?php if ($placeholder !== null): ?>
                <option value=""><?= esc($placeholder) ?></option>
            <?php endif ?>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= esc((string) $optionValue, 'attr') ?>" <?= (string) $optionValue === $current ? 'selected' : '' ?>><?= esc($optionLabel) ?></option>
            <?php endforeach ?>
        </select>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
    </div>
    <?php if ($error): ?>
        <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600"><?= icon('circle-alert', 'size-3.5') ?> <?= esc($error) ?></p>
    <?php elseif ($help): ?>
        <p class="mt-1.5 text-xs text-slate-500"><?= esc($help) ?></p>
    <?php endif ?>
</div>
