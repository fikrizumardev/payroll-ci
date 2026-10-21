<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Struktur menu sidebar.
 *
 * - `route` : named route; menu otomatis ditandai "Segera" bila route belum terdaftar.
 * - `roles` : peran yang boleh melihat menu (superadmin selalu boleh); kosong = semua peran.
 * - `needsEmployee` : hanya tampil bila akun terhubung ke data karyawan.
 */
class Navigation extends BaseConfig
{
    /**
     * @var list<array{label: string, items: list<array{label: string, icon: string, route: string, roles?: list<string>, needsEmployee?: bool}>}>
     */
    public array $groups = [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Master Data',
            'items' => [
                ['label' => 'Karyawan', 'icon' => 'users-round', 'route' => 'employees.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Departemen', 'icon' => 'building-2', 'route' => 'departments.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Jabatan', 'icon' => 'briefcase-business', 'route' => 'positions.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Komponen Gaji', 'icon' => 'layers', 'route' => 'salary-components.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Hari Libur', 'icon' => 'calendar-days', 'route' => 'holidays.index', 'roles' => ['hr']],
            ],
        ],
        [
            'label' => 'Kehadiran',
            'items' => [
                ['label' => 'Kehadiran Harian', 'icon' => 'calendar-check-2', 'route' => 'attendance.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Rekap Bulanan', 'icon' => 'calendar-range', 'route' => 'attendance.recap', 'roles' => ['hr', 'finance']],
                ['label' => 'Lembur', 'icon' => 'clock-4', 'route' => 'overtime.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Pinjaman', 'icon' => 'hand-coins', 'route' => 'loans.index', 'roles' => ['hr', 'finance']],
            ],
        ],
        [
            'label' => 'Payroll',
            'items' => [
                ['label' => 'Periode Payroll', 'icon' => 'banknote', 'route' => 'payroll.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Laporan', 'icon' => 'chart-column', 'route' => 'reports.index', 'roles' => ['hr', 'finance']],
                ['label' => 'Slip Gaji Saya', 'icon' => 'receipt-text', 'route' => 'my.payslips', 'needsEmployee' => true],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'Pengguna', 'icon' => 'user-cog', 'route' => 'users.index', 'roles' => ['superadmin']],
                ['label' => 'Pengaturan', 'icon' => 'settings-2', 'route' => 'settings.index', 'roles' => ['superadmin', 'hr', 'finance']],
                ['label' => 'Audit Log', 'icon' => 'scroll-text', 'route' => 'audit-logs.index', 'roles' => ['superadmin', 'finance']],
            ],
        ],
    ];

    /**
     * Menu yang boleh dilihat user, dikelompokkan per grup (grup kosong dibuang).
     *
     * @param array<string, mixed> $user
     *
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public function forUser(array $user): array
    {
        $auth   = service('auth');
        $groups = [];

        foreach ($this->groups as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (isset($item['roles']) && ! $auth->hasRole(...$item['roles'])) {
                    continue;
                }

                if (($item['needsEmployee'] ?? false) && empty($user['employee_id'])) {
                    continue;
                }

                $item['ready']  = route_exists($item['route']);
                $item['active'] = $item['ready'] && nav_is_active($item['route']);
                $item['url']    = $item['ready'] ? site_url(ltrim((string) route_to($item['route']), '/')) : null;
                $items[]        = $item;
            }

            if ($items !== []) {
                $groups[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $this->keepMostSpecificActive($groups);
    }

    /**
     * /attendance juga cocok dengan /attendance/recap — hanya menu dengan path terpanjang yang aktif.
     *
     * @param list<array{label: string, items: list<array<string, mixed>>}> $groups
     *
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    private function keepMostSpecificActive(array $groups): array
    {
        $longest = 0;

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['active']) {
                    $longest = max($longest, strlen((string) parse_url((string) $item['url'], PHP_URL_PATH)));
                }
            }
        }

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                $item['active'] = $item['active'] && strlen((string) parse_url((string) $item['url'], PHP_URL_PATH)) === $longest;
            }
            unset($item);
        }
        unset($group);

        return $groups;
    }
}
