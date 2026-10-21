<?php
/**
 * Command palette (Ctrl/⌘ + K) untuk lompat cepat ke menu.
 * Data menu berasal dari appShell(quickLinks) di layout.
 */
?>
<div x-show="paletteOpen" x-cloak class="fixed inset-0 z-[60] overflow-y-auto p-4 pt-[12vh] sm:p-6 sm:pt-[15vh]" role="dialog" aria-modal="true" aria-label="Pencarian menu">
    <div x-show="paletteOpen" x-transition.opacity class="fixed inset-0 bg-ink-950/50 backdrop-blur-sm" @click="closePalette()" aria-hidden="true"></div>

    <div x-show="paletteOpen" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="relative mx-auto max-w-xl overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200"
         @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()" @keydown.escape="closePalette()">
        <div class="flex items-center gap-3 border-b border-slate-100 px-4">
            <?= icon('search', 'size-5 text-slate-500') ?>
            <input x-ref="paletteInput" x-model="query" @input="cursor = 0" type="text"
                   class="h-14 w-full border-0 bg-transparent text-sm text-slate-900 placeholder:text-slate-500 focus:ring-0 focus:outline-none"
                   placeholder="Ketik nama menu, mis. “karyawan” atau “password”" aria-label="Cari menu">
            <kbd class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-500">Esc</kbd>
        </div>

        <ul class="max-h-80 overflow-y-auto p-2" role="listbox">
            <template x-for="(link, index) in results" :key="link.url">
                <li role="option" :aria-selected="index === cursor">
                    <a :href="link.url" @mouseenter="cursor = index"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm"
                       :class="index === cursor ? 'bg-brand-50 text-brand-700' : 'text-slate-700'">
                        <span class="flex size-8 items-center justify-center rounded-lg" :class="index === cursor ? 'bg-white text-brand-600 shadow-sm' : 'bg-slate-100 text-slate-500'">
                            <i :data-lucide="link.icon" class="size-4"></i>
                        </span>
                        <span class="flex-1 font-medium" x-text="link.label"></span>
                        <span class="text-xs text-slate-500" x-text="link.group"></span>
                    </a>
                </li>
            </template>
            <li x-show="results.length === 0" class="px-3 py-10 text-center text-sm text-slate-500">
                Tidak ada menu yang cocok dengan “<span class="font-semibold text-slate-700" x-text="query"></span>”.
            </li>
        </ul>

        <div class="flex items-center gap-4 border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-[11px] text-slate-500">
            <span><kbd class="font-semibold">↑↓</kbd> pilih</span>
            <span><kbd class="font-semibold">Enter</kbd> buka</span>
            <span class="ml-auto">PayrollKu</span>
        </div>
    </div>
</div>
