# PayrollKu — Sistem Payroll (CodeIgniter 4)

Aplikasi penggajian karyawan untuk perusahaan di Indonesia: data karyawan, kehadiran, lembur, BPJS, PPh 21 (TER), proses payroll bulanan, dan slip gaji.

- Spesifikasi produk: [`docs/PRD.md`](docs/PRD.md)
- Rencana kerja: [`docs/TASKS.md`](docs/TASKS.md)

## Teknologi

| Lapisan | Pilihan |
|---|---|
| Backend | CodeIgniter 4.7, PHP 8.2+ |
| Database | MySQL 8 / MariaDB 10.6+ |
| UI | Tailwind CSS v4 (CDN), Alpine.js, Lucide Icons, Chart.js |
| PDF / Excel | Dompdf, PhpSpreadsheet |

## Instalasi

```bash
# 1. Dependensi
composer install

# 2. Environment
cp .env.example .env
php spark key:generate

# 3. Buat database (sesuaikan kredensial di .env)
#    CREATE DATABASE payroll_app      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#    CREATE DATABASE payroll_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. Migrasi + data contoh
php spark migrate
php spark db:seed DatabaseSeeder

# 5. Jalankan
php spark serve        # http://localhost:8080
```

Reset seluruh data ke kondisi awal:

```bash
php spark migrate:refresh && php spark db:seed DatabaseSeeder
```

## Akun Default (hasil seeder)

Login memakai **username + password** (tanpa email).

| Username | Password | Peran |
|---|---|---|
| `admin` | `Admin#2026` | Super Admin |
| `sinta.maharani` | `Payroll#2026` | HR |
| `putri.lestari` | `Payroll#2026` | HR |
| `budi.santoso` | `Payroll#2026` | Finance |
| username karyawan lain (mis. `andi.pratama`) | `Payroll#2026` | Karyawan |

> Ganti semua password default sebelum dipakai di luar lingkungan development.

## UI Kit

Di environment `development`, katalog komponen tersedia di `/dev/ui-kit` (setelah login). Komponen ada di `app/Views/components/` dan dirender dengan helper `component('nama', [...])`. Tekan **Ctrl/⌘ + K** untuk membuka pencarian menu cepat.

## Testing

```bash
composer test          # atau: vendor/bin/phpunit
```

Test memakai database `payroll_app_test` (lihat `database.tests.*` di `.env`).

## Catatan Tarif

Tarif BPJS, batas upah JP, UMP, tabel PTKP, dan tabel TER PPh 21 disimpan di database (menu **Pengaturan**). Verifikasi nilainya terhadap regulasi terbaru sebelum dipakai di production.
