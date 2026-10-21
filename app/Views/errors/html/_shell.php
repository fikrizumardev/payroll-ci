<?php
/**
 * Shell halaman error mandiri (tanpa layout/service) — tetap bisa tampil
 * walau database/session bermasalah. Dipakai oleh error_403, error_404, production.
 *
 * @var string                                   $code
 * @var string                                   $heading
 * @var string                                   $description
 * @var string                                   $iconPath   path SVG (viewBox 24)
 * @var list<array{label: string, url: string, primary?: bool}> $links
 */
$siteUrl = static function (string $path = ''): string {
    try {
        return site_url($path);
    } catch (Throwable) {
        return '/' . ltrim($path, '/');
    }
};
?>
<!doctype html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= htmlspecialchars($code . ' · ' . $heading, ENT_QUOTES) ?> · PayrollKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @theme {
            --font-sans: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
            --color-brand-500: #6366f1;
            --color-brand-600: #4f46e5;
            --color-ink-950: #0b1020;
        }
    </style>
    <style>
        /* Fallback bila CDN tidak terjangkau */
        body { font-family: system-ui, sans-serif; margin: 0; }
    </style>
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased">
    <main class="relative isolate flex min-h-full items-center justify-center overflow-hidden px-6 py-24">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute top-1/2 left-1/2 size-[40rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgb(99_102_241/0.18),transparent)]"></div>
        </div>

        <div class="max-w-md text-center">
            <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-white text-brand-600 shadow-[0_18px_40px_-12px_rgb(16_24_40/0.18)] ring-1 ring-slate-200">
                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="<?= htmlspecialchars($iconPath, ENT_QUOTES) ?>"/>
                </svg>
            </span>
            <p class="mt-8 bg-linear-to-r from-brand-600 to-violet-600 bg-clip-text text-7xl font-extrabold tracking-tight text-transparent"><?= htmlspecialchars($code, ENT_QUOTES) ?></p>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900"><?= htmlspecialchars($heading, ENT_QUOTES) ?></h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-500"><?= htmlspecialchars($description, ENT_QUOTES) ?></p>

            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <?php foreach ($links as $link): ?>
                    <a href="<?= htmlspecialchars($siteUrl($link['url']), ENT_QUOTES) ?>"
                       class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold transition <?= ($link['primary'] ?? false) ? 'bg-linear-to-r from-brand-600 to-violet-600 text-white shadow-[0_10px_30px_-10px_rgb(79_70_229/0.55)] hover:opacity-95' : 'bg-white text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50' ?>">
                        <?= htmlspecialchars($link['label'], ENT_QUOTES) ?>
                    </a>
                <?php endforeach ?>
            </div>

            <p class="mt-12 text-xs text-slate-500">PayrollKu · Sistem Payroll</p>
        </div>
    </main>
</body>
</html>
