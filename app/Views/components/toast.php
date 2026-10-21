<?php
/**
 * Toast notifikasi: kanan atas di desktop, bawah layar di mobile (agar tidak menutupi topbar). Menampilkan flash message session
 * dan event JS `toast` (lihat PayrollKu.toast()).
 *
 * @var array<string, string|null> $flash type => pesan
 */
$initial = [];

foreach ($flash ?? [] as $type => $message) {
    if (is_string($message) && $message !== '') {
        $initial[] = ['type' => $type, 'message' => $message];
    }
}
?>
<div x-data="toastStack(<?= esc(json_encode($initial, JSON_UNESCAPED_UNICODE), 'attr') ?>)" @toast.window="push($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-0 z-[70] flex flex-col items-center gap-3 p-4 sm:top-0 sm:bottom-auto sm:items-end sm:p-6" aria-live="polite" aria-atomic="false">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transform ease-out duration-300" x-transition:enter-start="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0" x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200/80"
             role="status" @mouseenter="pause(toast)" @mouseleave="resume(toast)">
            <div class="flex items-start gap-3 p-4">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full" :class="styles[toast.type]?.icon ?? styles.info.icon">
                    <?php foreach ([
                        'success' => 'M20 6 9 17l-5-5',
                        'error'   => 'M18 6 6 18M6 6l12 12',
                        'warning' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
                        'info'    => 'M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
                    ] as $type => $path): ?>
                        <svg x-show="toast.type === '<?= $type ?>'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="<?= $path ?>"/>
                        </svg>
                    <?php endforeach ?>
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-sm font-semibold text-slate-900" x-text="styles[toast.type]?.title ?? 'Info'"></p>
                    <p class="mt-0.5 text-sm text-slate-600" x-text="toast.message"></p>
                </div>
                <button type="button" @click="dismiss(toast)" class="-m-1 rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup notifikasi">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="absolute inset-x-0 bottom-0 h-0.5 origin-left" :class="styles[toast.type]?.bar ?? styles.info.bar"
                 :style="`transform: scaleX(${toast.progress}); transition: transform 100ms linear`"></div>
        </div>
    </template>
</div>

<script>
    function toastStack(initial = []) {
        return {
            toasts: [],
            styles: {
                success: { title: 'Berhasil', icon: 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100', bar: 'bg-emerald-500' },
                error: { title: 'Terjadi kesalahan', icon: 'bg-rose-50 text-rose-600 ring-1 ring-rose-100', bar: 'bg-rose-500' },
                warning: { title: 'Perhatian', icon: 'bg-amber-50 text-amber-600 ring-1 ring-amber-100', bar: 'bg-amber-500' },
                info: { title: 'Info', icon: 'bg-brand-50 text-brand-600 ring-1 ring-brand-100', bar: 'bg-brand-500' },
            },
            init() {
                initial.forEach((toast, index) => setTimeout(() => this.push(toast), 150 + index * 120));
            },
            push({ type = 'info', message = '', duration = 5000 }) {
                const toast = { id: Date.now() + Math.random(), type, message, visible: true, progress: 1, remaining: duration, duration, paused: false };
                this.toasts.push(toast);
                this.tick(toast);
            },
            tick(toast) {
                const step = 100;
                toast.timer = setInterval(() => {
                    if (toast.paused) return;
                    toast.remaining -= step;
                    toast.progress = Math.max(0, toast.remaining / toast.duration);
                    if (toast.remaining <= 0) this.dismiss(toast);
                }, step);
            },
            pause(toast) { toast.paused = true; },
            resume(toast) { toast.paused = false; },
            dismiss(toast) {
                clearInterval(toast.timer);
                toast.visible = false;
                setTimeout(() => { this.toasts = this.toasts.filter((t) => t.id !== toast.id); }, 200);
            },
        };
    }
</script>
