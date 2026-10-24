<?php
/**
 * @var list<array<string, mixed>>             $logs
 * @var CodeIgniter\Pager\Pager                $pager
 * @var array{user_id: int, action: string, from: string, to: string, q: string} $filters
 * @var array<int, string>                     $users
 * @var array<string, array<string, string>>   $actions  label modul → [nilai → label]
 * @var array<string, array{string, string}>   $entities entity → [route, label]
 */

use App\Models\AuditLogModel;
use App\Services\AuditDiff;

$this->extend('layouts/app');
$this->section('content');

$total     = $pager->getTotal();
$page      = $pager->getCurrentPage();
$perPage   = $pager->getPerPage();
$hasFilter = $filters['user_id'] > 0 || $filters['action'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['q'] !== '';

$byDate = [];

foreach ($logs as $log) {
    $byDate[substr((string) $log['created_at'], 0, 10)][] = $log;
}

$value = static fn (?string $v): string => $v === null || $v === 'null' || $v === ''
    ? '<span class="text-slate-400">—</span>'
    : esc(mb_strlen($v) > 300 ? mb_substr($v, 0, 300) . '…' : $v);

$changeStyle = [
    AuditDiff::CHANGED => ['bg-amber-50/70', 'Diubah'],
    AuditDiff::ADDED   => ['bg-emerald-50/70', 'Baru'],
    AuditDiff::REMOVED => ['bg-rose-50/70', 'Dihapus'],
    AuditDiff::SAME    => ['', ''],
];
?>
<?= component('page_header', [
    'eyebrow'  => 'Sistem',
    'title'    => 'Audit Log',
    'subtitle' => 'Jejak seluruh aktivitas penting: siapa, kapan, dari mana, dan data apa yang berubah. Rahasia (password, token) tidak pernah dicatat; NIK/NPWP/rekening disamarkan.',
]) ?>

<form method="get" action="<?= url_to('audit-logs.index') ?>" class="card mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_12.5rem_12.5rem_auto] 2xl:grid-cols-[1fr_14rem_16rem_12.5rem_12.5rem_auto]" role="search">
    <div class="relative sm:col-span-2 lg:col-span-5 2xl:col-span-1">
        <label for="audit-q" class="sr-only">Cari</label>
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500"><?= icon('search', 'size-4') ?></span>
        <input id="audit-q" name="q" type="search" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Cari deskripsi, username, IP, atau ID…" class="form-control pl-10">
    </div>
    <div class="relative">
        <label for="audit-user" class="sr-only">Pengguna</label>
        <select id="audit-user" name="user_id" class="form-control appearance-none pr-10" onchange="this.form.submit()">
            <option value="">Semua pengguna</option>
            <?php foreach ($users as $id => $label): ?>
                <option value="<?= (int) $id ?>" <?= $id === $filters['user_id'] ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
    </div>
    <div class="relative">
        <label for="audit-action" class="sr-only">Aksi</label>
        <select id="audit-action" name="action" class="form-control appearance-none pr-10" onchange="this.form.submit()">
            <option value="">Semua aksi</option>
            <?php foreach ($actions as $group => $options): ?>
                <optgroup label="<?= esc($group, 'attr') ?>">
                    <?php foreach ($options as $optionValue => $optionLabel): ?>
                        <option value="<?= esc($optionValue, 'attr') ?>" <?= $optionValue === $filters['action'] ? 'selected' : '' ?>><?= esc($optionLabel) ?></option>
                    <?php endforeach ?>
                </optgroup>
            <?php endforeach ?>
        </select>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500"><?= icon('chevrons-up-down', 'size-4') ?></span>
    </div>
    <div class="relative">
        <label for="audit-from" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-xs font-semibold text-slate-500">Dari</label>
        <input id="audit-from" name="from" type="date" value="<?= esc($filters['from'], 'attr') ?>" class="form-control pl-14" title="Dari tanggal" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="relative">
        <label for="audit-to" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-xs font-semibold text-slate-500">Sampai</label>
        <input id="audit-to" name="to" type="date" value="<?= esc($filters['to'], 'attr') ?>" class="form-control pl-16" title="Sampai tanggal" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-secondary flex-1 lg:flex-none"><?= icon('filter', 'size-4') ?> Terapkan</button>
        <?php if ($hasFilter): ?>
            <a href="<?= url_to('audit-logs.index') ?>" class="btn btn-ghost" aria-label="Reset filter"><?= icon('x', 'size-4') ?></a>
        <?php endif ?>
    </div>
</form>

<section class="card overflow-hidden" aria-labelledby="audit-title">
    <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="audit-title" class="text-base font-semibold text-slate-900">Riwayat Aktivitas</h2>
            <p class="mt-0.5 text-sm text-slate-500"><?= $total === 0 ? 'Tidak ada aktivitas' : sprintf('Menampilkan %s–%s dari %s aktivitas', number_id(($page - 1) * $perPage + 1), number_id(min($page * $perPage, $total)), number_id($total)) ?> · klik baris untuk melihat rincian.</p>
        </div>
    </div>

    <?php if ($logs === []): ?>
        <?= component('empty_state', [
            'title'       => $hasFilter ? 'Tidak ada aktivitas yang cocok' : 'Belum ada aktivitas',
            'description' => $hasFilter ? 'Coba ubah filter pengguna, aksi, atau rentang tanggal.' : null,
            'icon'        => 'scroll-text',
            'compact'     => true,
        ]) ?>
    <?php else: ?>
        <?php foreach ($byDate as $date => $entries): ?>
            <h3 class="sticky top-0 z-[1] border-b border-slate-100 bg-slate-50/95 px-5 py-2 text-xs font-semibold tracking-wider text-slate-500 uppercase backdrop-blur"><?= esc(tanggal_id($date, 'full')) ?></h3>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($entries as $log): ?>
                    <?php
                    $actor   = $log['user_name'] ?? $log['username'] ?? 'Sistem';
                    $tone    = AuditLogModel::tone($log['action']);
                    $diff    = AuditDiff::rows($log['before'], $log['after']);
                    $route   = $entities[$log['entity']] ?? null;
                    $hasLink = $route !== null && $log['entity_id'] !== null && ctype_digit((string) $log['entity_id']);
                    ?>
                    <li>
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-start gap-3 px-5 py-3.5 transition-colors hover:bg-slate-50/80 focus-visible:bg-slate-50 focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                                <?= avatar($actor, 'size-8 text-[11px]') ?>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-sm font-semibold text-slate-900"><?= esc($actor) ?></span>
                                        <?php if ($log['username']): ?>
                                            <span class="font-mono text-xs text-slate-500">@<?= esc($log['username']) ?></span>
                                        <?php endif ?>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset <?= tone_classes($tone) ?>"><?= esc(AuditLogModel::label($log['action'])) ?></span>
                                    </div>
                                    <p class="mt-0.5 text-sm break-words text-slate-600"><?= esc((string) ($log['description'] ?? '')) ?></p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-medium text-slate-700 tabular-nums"><?= esc(date('H:i', strtotime((string) $log['created_at']))) ?></p>
                                    <p class="hidden text-xs text-slate-500 sm:block"><?= esc((string) ($log['ip_address'] ?? '—')) ?></p>
                                </div>
                                <span class="mt-1 shrink-0 text-slate-400 transition group-open:rotate-90" aria-hidden="true"><?= icon('chevron-right', 'size-4') ?></span>
                            </summary>

                            <div class="space-y-4 border-t border-dashed border-slate-200 bg-slate-50/60 px-5 py-4 sm:pl-16">
                                <dl class="grid gap-x-6 gap-y-2 text-xs sm:grid-cols-2 xl:grid-cols-4">
                                    <div>
                                        <dt class="font-semibold text-slate-500">Waktu</dt>
                                        <dd class="text-slate-800"><?= esc(tanggal_id($log['created_at'], 'full')) ?>, <?= esc(date('H:i:s', strtotime((string) $log['created_at']))) ?> WIB</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-500">Aksi</dt>
                                        <dd class="font-mono text-slate-800"><?= esc($log['action']) ?> <span class="font-sans text-slate-400">· log #<?= (int) $log['id'] ?></span></dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-500">Entitas</dt>
                                        <dd class="text-slate-800">
                                            <?php if ($log['entity'] === null): ?>
                                                —
                                            <?php elseif ($hasLink): ?>
                                                <a href="<?= url_to($route[0], (int) $log['entity_id']) ?>" class="font-semibold text-brand-700 hover:underline"><?= esc($route[1]) ?> #<?= esc((string) $log['entity_id']) ?></a>
                                            <?php else: ?>
                                                <span class="font-mono"><?= esc($log['entity']) ?><?= $log['entity_id'] !== null ? ' #' . esc((string) $log['entity_id']) : '' ?></span>
                                            <?php endif ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-500">Alamat IP</dt>
                                        <dd class="font-mono text-slate-800"><?= esc((string) ($log['ip_address'] ?? '—')) ?></dd>
                                    </div>
                                    <div class="sm:col-span-2 xl:col-span-4">
                                        <dt class="font-semibold text-slate-500">Perangkat (user agent)</dt>
                                        <dd class="break-all text-slate-600"><?= esc((string) ($log['user_agent'] ?? '—')) ?></dd>
                                    </div>
                                </dl>

                                <?php if ($diff === []): ?>
                                    <p class="text-xs text-slate-500">Tidak ada data sebelum/sesudah yang dicatat untuk aktivitas ini.</p>
                                <?php else: ?>
                                    <?php $twoSided = $log['before'] !== null && $log['after'] !== null; ?>
                                    <div class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
                                        <table class="min-w-full text-xs">
                                            <thead class="bg-slate-50">
                                                <tr class="text-left font-semibold tracking-wider text-slate-500 uppercase">
                                                    <th scope="col" class="w-1/4 px-3 py-2">Kolom</th>
                                                    <?php if ($log['before'] !== null): ?><th scope="col" class="px-3 py-2"><?= $twoSided ? 'Sebelum' : 'Data (sebelum dihapus)' ?></th><?php endif ?>
                                                    <?php if ($log['after'] !== null): ?><th scope="col" class="px-3 py-2"><?= $twoSided ? 'Sesudah' : 'Data' ?></th><?php endif ?>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <?php foreach ($diff as $row): ?>
                                                    <?php [$rowClass, $changeLabel] = $twoSided ? $changeStyle[$row['change']] : ['', '']; ?>
                                                    <tr class="<?= $rowClass ?>">
                                                        <th scope="row" class="px-3 py-2 text-left align-top font-mono font-medium whitespace-nowrap text-slate-700">
                                                            <?= esc($row['key']) ?>
                                                            <?php if ($changeLabel !== ''): ?><span class="ml-1 font-sans text-[10px] font-semibold tracking-wide text-slate-500 uppercase"><?= $changeLabel ?></span><?php endif ?>
                                                        </th>
                                                        <?php if ($log['before'] !== null): ?>
                                                            <td class="px-3 py-2 align-top break-all <?= $twoSided && $row['change'] === AuditDiff::CHANGED ? 'text-rose-700 line-through decoration-rose-300' : 'text-slate-700' ?>"><?= $value($row['before']) ?></td>
                                                        <?php endif ?>
                                                        <?php if ($log['after'] !== null): ?>
                                                            <td class="px-3 py-2 align-top break-all <?= $twoSided && $row['change'] === AuditDiff::CHANGED ? 'font-semibold text-emerald-800' : 'text-slate-700' ?>"><?= $value($row['after']) ?></td>
                                                        <?php endif ?>
                                                    </tr>
                                                <?php endforeach ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif ?>
                            </div>
                        </details>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endforeach ?>
    <?php endif ?>

    <?php if ($pager->getPageCount() > 1): ?>
        <div class="border-t border-slate-100 px-5 py-3"><?= $pager->links('default', 'tailwind') ?></div>
    <?php endif ?>
</section>
<?php $this->endSection() ?>
