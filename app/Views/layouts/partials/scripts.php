<?php
/**
 * Script bersama di akhir <body>: state shell aplikasi (Alpine), ikon Lucide,
 * toast, dan helper format angka. Harus dimuat sebelum Alpine (defer) berjalan.
 */
?>
<script>
    window.PayrollKu = {
        refreshIcons() {
            if (window.lucide) {
                window.lucide.createIcons({ attrs: { 'stroke-width': 1.85 } });
            }
        },
        csrf() {
            return {
                header: document.querySelector('meta[name="csrf-header"]')?.content,
                token: document.querySelector('meta[name="csrf-token"]')?.content,
            };
        },
        /** PayrollKu.toast('success', 'Data tersimpan') */
        toast(type, message) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
        },
        /** PayrollKu.confirm({ title, message, action, confirmLabel, tone }) */
        confirm(options) {
            window.dispatchEvent(new CustomEvent('confirm', { detail: options }));
        },
        formatNumber(value) {
            const digits = String(value ?? '').replace(/\D/g, '');
            return digits === '' ? '' : Number(digits).toLocaleString('id-ID');
        },
    };

    /**
     * State halaman CRUD dengan modal tambah/ubah.
     * `reopen` ('create' | 'edit:ID') + `old` membuka ulang modal setelah validasi gagal.
     */
    function crudModal({ empty, items = {}, storeUrl, updateUrl, reopen = null, old = null }) {
        return {
            modalOpen: false,
            submitting: false,
            mode: 'create',
            form: { ...empty },
            action: storeUrl,

            create() {
                this.mode = 'create';
                this.form = { ...empty };
                this.action = storeUrl;
                this.open();
            },

            edit(id) {
                this.mode = 'edit';
                this.form = { ...empty, ...(items[id] ?? {}) };
                this.action = updateUrl.replace('__ID__', id);
                this.open();
            },

            open() {
                this.submitting = false;
                this.modalOpen = true;
                this.$nextTick(() => {
                    window.PayrollKu.refreshIcons();
                    this.$root.querySelector('[role=dialog] input:not([type=hidden]), [role=dialog] select')?.focus();
                });
            },

            init() {
                if (!reopen) return;
                reopen.startsWith('edit:') ? this.edit(reopen.slice(5)) : this.create();
                if (!old) return;
                const restored = { ...old };
                // Checkbox terkirim sebagai '0' / '1' → kembalikan ke boolean
                Object.keys(empty).forEach((key) => {
                    if (typeof empty[key] === 'boolean' && key in restored) restored[key] = restored[key] === '1';
                });
                this.form = { ...this.form, ...restored };
            },
        };
    }

    /** State shell aplikasi: sidebar ringkas, drawer mobile, command palette. */
    function appShell(links = []) {
        return {
            collapsed: document.documentElement.classList.contains('sidebar-collapsed'),
            mobileOpen: false,
            paletteOpen: false,
            query: '',
            cursor: 0,
            links,

            toggleCollapse() {
                this.collapsed = !this.collapsed;
                document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
                localStorage.setItem('payrollku.sidebar', this.collapsed ? 'collapsed' : 'expanded');
            },

            get results() {
                const q = this.query.trim().toLowerCase();
                return q === '' ? this.links : this.links.filter((l) => (l.label + ' ' + l.group).toLowerCase().includes(q));
            },

            openPalette() {
                this.paletteOpen = true;
                this.query = '';
                this.cursor = 0;
                this.$nextTick(() => {
                    this.$refs.paletteInput?.focus();
                    window.PayrollKu.refreshIcons();
                });
            },

            closePalette() {
                this.paletteOpen = false;
            },

            move(step) {
                const total = this.results.length;
                if (total > 0) {
                    this.cursor = (this.cursor + step + total) % total;
                }
            },

            go() {
                const link = this.results[this.cursor];
                if (link) {
                    window.location.href = link.url;
                }
            },

            init() {
                this.$watch('query', () => this.$nextTick(() => window.PayrollKu.refreshIcons()));
            },
        };
    }

    document.addEventListener('DOMContentLoaded', () => window.PayrollKu.refreshIcons());
    document.addEventListener('alpine:initialized', () => window.PayrollKu.refreshIcons());
</script>
