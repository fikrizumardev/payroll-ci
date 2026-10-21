<?php

declare(strict_types=1);

/**
 * Helper kecil untuk elemen UI yang berulang: badge status, avatar inisial,
 * penyamaran nomor identitas, dan ikon Lucide.
 */

if (! function_exists('status_meta')) {
    /**
     * Label dan tone warna untuk setiap status yang dipakai di aplikasi.
     *
     * @return array{label: string, tone: string}
     */
    function status_meta(string $status): array
    {
        $map = [
            // Periode payroll
            'draft'    => ['Draft', 'sky'],
            'review'   => ['Review', 'amber'],
            'approved' => ['Disetujui', 'indigo'],
            'paid'     => ['Dibayar', 'emerald'],
            'locked'   => ['Terkunci', 'slate'],
            // Karyawan
            'active'     => ['Aktif', 'emerald'],
            'resigned'   => ['Resign', 'slate'],
            'terminated' => ['Diberhentikan', 'rose'],
            'permanent'  => ['Tetap', 'indigo'],
            'contract'   => ['Kontrak', 'violet'],
            'probation'  => ['Percobaan', 'amber'],
            // Kehadiran
            'present' => ['Hadir', 'emerald'],
            'late'    => ['Terlambat', 'amber'],
            'sick'    => ['Sakit', 'sky'],
            'leave'   => ['Cuti', 'violet'],
            'permit'  => ['Izin', 'indigo'],
            'absent'  => ['Alpha', 'rose'],
            'holiday' => ['Libur', 'slate'],
            // Persetujuan & pinjaman
            'pending'  => ['Menunggu', 'amber'],
            'rejected' => ['Ditolak', 'rose'],
            'paid_off' => ['Lunas', 'emerald'],
            // Akun
            'inactive' => ['Nonaktif', 'slate'],
        ];

        [$label, $tone] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'slate'];

        return ['label' => $label, 'tone' => $tone];
    }
}

if (! function_exists('tone_classes')) {
    /**
     * Kelas Tailwind per tone. Ditulis lengkap (bukan disusun dinamis)
     * agar mudah dicari dan konsisten.
     */
    function tone_classes(string $tone, string $variant = 'badge'): string
    {
        $badge = [
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'amber'   => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'rose'    => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            'sky'     => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            'indigo'  => 'bg-brand-50 text-brand-700 ring-brand-600/20',
            'violet'  => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            'slate'   => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        ];
        $dot = [
            'emerald' => 'bg-emerald-500',
            'amber'   => 'bg-amber-500',
            'rose'    => 'bg-rose-500',
            'sky'     => 'bg-sky-500',
            'indigo'  => 'bg-brand-500',
            'violet'  => 'bg-violet-500',
            'slate'   => 'bg-slate-400',
        ];

        $set = $variant === 'dot' ? $dot : $badge;

        return $set[$tone] ?? $set['slate'];
    }
}

if (! function_exists('status_badge')) {
    /**
     * Badge pill dengan titik warna. Label bisa di-override.
     */
    function status_badge(string $status, ?string $label = null): string
    {
        $meta  = status_meta($status);
        $label = esc($label ?? $meta['label']);

        return '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '
            . tone_classes($meta['tone']) . '">'
            . '<span class="size-1.5 rounded-full ' . tone_classes($meta['tone'], 'dot') . '"></span>'
            . $label . '</span>';
    }
}

if (! function_exists('initials')) {
    /**
     * initials('Andi Pratama') → "AP", initials('Hendra') → "HE"
     */
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
}

if (! function_exists('avatar_color')) {
    /**
     * Gradien avatar yang konsisten untuk nama yang sama.
     */
    function avatar_color(?string $name): string
    {
        $palette = [
            'from-indigo-500 to-violet-500',
            'from-sky-500 to-indigo-500',
            'from-emerald-500 to-teal-500',
            'from-amber-500 to-orange-500',
            'from-rose-500 to-pink-500',
            'from-fuchsia-500 to-purple-500',
            'from-cyan-500 to-blue-500',
            'from-lime-500 to-emerald-500',
        ];

        return $palette[crc32((string) $name) % count($palette)];
    }
}

if (! function_exists('avatar')) {
    /**
     * Avatar inisial bulat bergradien.
     *
     * @param string $size kelas ukuran Tailwind, mis. "size-9 text-xs"
     */
    function avatar(?string $name, string $size = 'size-9 text-xs'): string
    {
        return '<span class="inline-flex shrink-0 items-center justify-center rounded-full bg-linear-to-br '
            . avatar_color($name) . ' ' . $size . ' font-semibold text-white shadow-sm ring-2 ring-white" aria-hidden="true">'
            . esc(initials($name)) . '</span>';
    }
}

if (! function_exists('mask_id')) {
    /**
     * Menyamarkan NIK/NPWP/rekening: mask_id('3174012345678012') → "3174•••••••••012"
     */
    function mask_id(?string $value, int $visibleStart = 4, int $visibleEnd = 3): string
    {
        $value  = (string) $value;
        $length = mb_strlen($value);

        if ($length === 0) {
            return '—';
        }

        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat('•', $length);
        }

        return mb_substr($value, 0, $visibleStart)
            . str_repeat('•', $length - $visibleStart - $visibleEnd)
            . mb_substr($value, -$visibleEnd);
    }
}

if (! function_exists('icon')) {
    /**
     * Placeholder ikon Lucide (dirender oleh lucide.createIcons()).
     */
    function icon(string $name, string $class = 'size-5'): string
    {
        return '<i data-lucide="' . esc($name, 'attr') . '" class="' . esc($class, 'attr') . '" aria-hidden="true"></i>';
    }
}

if (! function_exists('role_label')) {
    function role_label(string $role): string
    {
        return App\Models\UserModel::ROLES[$role] ?? ucfirst($role);
    }
}
