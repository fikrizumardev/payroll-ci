<?php
/**
 * <head> bersama: Tailwind CSS v4 (CDN), design token, font, Alpine.js, Lucide, Chart.js.
 *
 * @var string|null $title
 */
$appName = 'PayrollKu';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-header" content="<?= esc(config('Security')->headerName, 'attr') ?>">
<meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b1020">
<title><?= esc(isset($title) && $title !== '' ? $title . ' · ' . $appName : $appName) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%234f46e5'/%3E%3Cstop offset='1' stop-color='%237c3aed'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='32' height='32' rx='9' fill='url(%23g)'/%3E%3Cpath d='M10 22V10h7a4.5 4.5 0 0 1 0 9h-4' fill='none' stroke='white' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style type="text/tailwindcss">
    @theme {
        --font-sans: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;

        --color-brand-50: #eef2ff;
        --color-brand-100: #e0e7ff;
        --color-brand-200: #c7d2fe;
        --color-brand-300: #a5b4fc;
        --color-brand-400: #818cf8;
        --color-brand-500: #6366f1;
        --color-brand-600: #4f46e5;
        --color-brand-700: #4338ca;
        --color-brand-800: #3730a3;
        --color-brand-900: #312e81;
        --color-brand-950: #1e1b4b;

        --color-ink-800: #161b2e;
        --color-ink-900: #10142a;
        --color-ink-950: #0b1020;

        --shadow-card: 0 1px 2px rgb(16 24 40 / 0.04), 0 8px 24px -12px rgb(16 24 40 / 0.10);
        --shadow-elevated: 0 4px 6px -2px rgb(16 24 40 / 0.05), 0 18px 40px -12px rgb(16 24 40 / 0.18);
        --shadow-glow: 0 10px 30px -10px rgb(79 70 229 / 0.55);
    }

    @layer base {
        html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body { font-feature-settings: "cv11", "ss01"; }
        [x-cloak] { display: none !important; }
        ::selection { background-color: var(--color-brand-200); color: var(--color-brand-950); }
    }

    @utility card {
        @apply rounded-2xl bg-white shadow-card ring-1 ring-slate-200/70;
    }

    @utility btn {
        @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60;
    }

    @utility btn-primary {
        @apply bg-linear-to-r from-brand-600 to-violet-600 text-white shadow-glow hover:from-brand-500 hover:to-violet-500 active:scale-[0.99];
    }

    @utility btn-secondary {
        @apply bg-white text-slate-700 shadow-sm ring-1 ring-slate-300/80 ring-inset hover:bg-slate-50 hover:text-slate-900;
    }

    @utility btn-ghost {
        @apply text-slate-600 hover:bg-slate-100 hover:text-slate-900;
    }

    @utility btn-danger {
        @apply bg-rose-600 text-white shadow-sm hover:bg-rose-500;
    }

    @utility form-label {
        @apply mb-1.5 block text-sm font-semibold text-slate-700;
    }

    @utility form-control {
        @apply block w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm ring-1 ring-slate-300 ring-inset transition placeholder:text-slate-500 focus:ring-2 focus:ring-brand-500 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500;
    }

    @utility text-gradient {
        @apply bg-linear-to-r from-brand-300 via-violet-300 to-fuchsia-300 bg-clip-text text-transparent;
    }
</style>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
