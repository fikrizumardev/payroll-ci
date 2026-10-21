<?php
/**
 * Input nominal rupiah: tampil "Rp 1.250.000", terkirim sebagai angka murni (1250000).
 *
 * @var string          $name
 * @var string          $label
 * @var int|string|null $value
 * @var string|null     $help
 * @var bool|null       $required
 * @var string|null     $error
 * @var string|null     $placeholder
 */
$value       ??= null;
$help        ??= null;
$required    ??= false;
$placeholder ??= '0';
$error       ??= (session('errors') ?? [])[$name] ?? null;
$id          = 'field-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
$raw         = preg_replace('/\D/', '', (string) old($name, $value, false));
?>
<div x-data="{ raw: '<?= esc($raw, 'js') ?>', get display() { return PayrollKu.formatNumber(this.raw) } }">
    <label for="<?= esc($id, 'attr') ?>" class="form-label">
        <?= esc($label) ?><?php if ($required): ?> <span class="text-rose-500" aria-hidden="true">*</span><?php endif ?>
    </label>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-semibold text-slate-500">Rp</span>
        <input id="<?= esc($id, 'attr') ?>" type="text" inputmode="numeric" autocomplete="off"
               :value="display" @input="raw = $event.target.value.replace(/\D/g, '').replace(/^0+(?=\d)/, ''); $event.target.value = display"
               value="<?= esc($raw === '' ? '' : number_id((int) $raw), 'attr') ?>"
               class="form-control pl-11 text-right font-semibold tabular-nums <?= $error ? 'ring-rose-400 focus:ring-rose-500' : '' ?>"
               placeholder="<?= esc($placeholder, 'attr') ?>" <?= $required ? 'required' : '' ?> <?= $error ? 'aria-invalid="true"' : '' ?>>
        <input type="hidden" name="<?= esc($name, 'attr') ?>" :value="raw" value="<?= esc($raw, 'attr') ?>">
    </div>
    <?php if ($error): ?>
        <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600"><?= icon('circle-alert', 'size-3.5') ?> <?= esc($error) ?></p>
    <?php elseif ($help): ?>
        <p class="mt-1.5 text-xs text-slate-500"><?= esc($help) ?></p>
    <?php endif ?>
</div>
