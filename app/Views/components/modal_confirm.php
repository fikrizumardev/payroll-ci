<?php
/**
 * Modal konfirmasi global untuk aksi destruktif / perubahan status.
 * Dipicu dari mana saja dengan:
 *
 *   <button type="button" @click="$dispatch('confirm', {
 *       title: 'Kunci periode?', message: 'Data tidak bisa diubah lagi.',
 *       action: '/payroll/3/lock', confirmLabel: 'Ya, kunci', tone: 'danger',
 *       fields: { note: '' }, withNote: true
 *   })">…</button>
 */
?>
<div x-data="confirmModal()" @confirm.window="show($event.detail)" @keydown.escape.window="open && close()"
     x-show="open" x-cloak class="fixed inset-0 z-[65] overflow-y-auto" role="dialog" aria-modal="true" :aria-labelledby="'confirm-title'">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/50 backdrop-blur-sm" @click="close()" aria-hidden="true"></div>

    <div class="flex min-h-full items-end justify-center p-4 sm:items-center sm:p-6">
        <div x-show="open"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 sm:scale-95"
             class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-elevated ring-1 ring-slate-200">
            <form :action="options.action" method="post" @submit="submitting = true">
                <?= csrf_field() ?>
                <template x-for="(value, name) in options.fields ?? {}" :key="name">
                    <input type="hidden" :name="name" :value="value">
                </template>

                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full"
                              :class="options.tone === 'danger' ? 'bg-rose-50 text-rose-600 ring-8 ring-rose-50/50' : 'bg-brand-50 text-brand-600 ring-8 ring-brand-50/50'">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path x-show="options.tone === 'danger'" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                                <path x-show="options.tone !== 'danger'" d="M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z"/>
                            </svg>
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <h3 id="confirm-title" class="text-base font-semibold text-slate-900" x-text="options.title"></h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-500" x-text="options.message"></p>
                        </div>
                    </div>

                    <div x-show="options.withNote" class="mt-5">
                        <label for="confirm-note" class="form-label" x-text="options.noteLabel ?? 'Catatan'"></label>
                        <textarea id="confirm-note" name="note" rows="3" class="form-control" :required="options.withNote && options.noteRequired"
                                  :placeholder="options.notePlaceholder ?? 'Tulis catatan…'"></textarea>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" class="btn btn-secondary" @click="close()">Batal</button>
                    <button type="submit" class="btn" :class="options.tone === 'danger' ? 'btn-danger' : 'btn-primary'" :disabled="submitting">
                        <svg x-show="submitting" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/>
                            <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        <span x-text="options.confirmLabel ?? 'Ya, lanjutkan'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmModal() {
        return {
            open: false,
            submitting: false,
            options: {},
            show(options) {
                this.options = options ?? {};
                this.submitting = false;
                this.open = true;
            },
            close() {
                if (!this.submitting) this.open = false;
            },
        };
    }
</script>
