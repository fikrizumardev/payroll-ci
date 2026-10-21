<?php
/**
 * Script bersama di akhir <body>: render ikon Lucide & helper kecil global.
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
    };

    document.addEventListener('DOMContentLoaded', () => window.PayrollKu.refreshIcons());
    document.addEventListener('alpine:initialized', () => window.PayrollKu.refreshIcons());
</script>
