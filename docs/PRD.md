# PRD — Sistem Payroll (CodeIgniter 4)

| | |
|---|---|
| **Nama Produk** | PayrollKu — Sistem Penggajian Karyawan |
| **Versi Dokumen** | 1.0 |
| **Tanggal** | 21 Oktober 2026 |
| **Pemilik** | fikri@mitratek.com |
| **Status** | Draft — siap dieksekusi (lihat [`TASKS.md`](./TASKS.md)) |

---

## 1. Ringkasan

PayrollKu adalah aplikasi web penggajian karyawan untuk perusahaan di Indonesia (skala 10–500 karyawan). Aplikasi mengelola data karyawan, kehadiran, lembur, komponen gaji, pinjaman karyawan, perhitungan BPJS & PPh 21 (metode TER), proses payroll bulanan dengan alur persetujuan, slip gaji PDF, serta laporan.

Tiga syarat utama dari pemilik produk:

1. **Dibangun dengan CodeIgniter 4** dan **Tailwind CSS via CDN**, dengan tampilan **modern & premium**.
2. **Data realistis sudah masuk database sejak build pertama**, lewat Database Seeder (karyawan, kehadiran, dan riwayat payroll yang dihitung oleh engine yang sama dengan produksi, bukan angka karangan).
3. **Login memakai username + password**, tanpa email sama sekali.

## 2. Tujuan & Non-Tujuan

### Tujuan
- Satu kali klik "Generate Payroll" menghasilkan gaji seluruh karyawan aktif untuk satu periode, lengkap dengan BPJS, PPh 21, lembur, potongan, dan cicilan pinjaman.
- Hasil hitung bisa ditelusuri baris per baris (setiap komponen tersimpan sebagai `payroll_items`).
- Alur status periode: `draft → review → approved → paid → locked`, dan periode yang sudah dikunci tidak bisa diubah.
- Karyawan bisa login sendiri dan mengunduh slip gajinya.
- Begitu `php spark migrate && php spark db:seed DatabaseSeeder` dijalankan, aplikasi langsung bisa didemokan dengan data yang masuk akal.

### Non-Tujuan (v1)
- Integrasi langsung ke API bank / e-Bupot / SIPP BPJS (cukup ekspor file).
- Mesin absensi fingerprint (cukup input manual & import CSV).
- Metode gross-up PPh 21 (v1 hanya metode **gross**: pajak ditanggung karyawan).
- Multi-perusahaan / multi-tenant.
- Reset password via email (tidak ada email di sistem; reset dilakukan admin).

## 3. Pengguna & Peran

| Peran | Kode | Deskripsi | Contoh akun seed |
|---|---|---|---|
| Super Admin | `superadmin` | Akses penuh, kelola user & pengaturan sistem | `admin` |
| HR | `hr` | Kelola karyawan, kehadiran, lembur, generate payroll | `sinta.maharani`, `putri.lestari` |
| Finance | `finance` | Review & approve payroll, tandai dibayar, laporan, ekspor bank | `budi.santoso` |
| Karyawan | `employee` | Lihat profil, kehadiran, dan slip gaji milik sendiri | `andi.pratama`, dst. |

### Matriks Hak Akses

| Modul | superadmin | hr | finance | employee |
|---|:-:|:-:|:-:|:-:|
| Dashboard perusahaan | ✅ | ✅ | ✅ | — (dashboard pribadi) |
| Departemen & Jabatan | CRUD | CRUD | Lihat | — |
| Karyawan | CRUD | CRUD | Lihat | Profil sendiri |
| Komponen Gaji | CRUD | CRUD | Lihat | — |
| Kehadiran & Lembur | CRUD | CRUD | Lihat | Lihat sendiri |
| Pinjaman / Kasbon | CRUD | CRUD | Approve | Lihat sendiri |
| Payroll: generate / regenerate | ✅ | ✅ | — | — |
| Payroll: submit review | ✅ | ✅ | — | — |
| Payroll: approve / reject | ✅ | — | ✅ | — |
| Payroll: tandai dibayar / kunci | ✅ | — | ✅ | — |
| Slip gaji | Semua | Semua | Semua | Milik sendiri |
| Laporan & Ekspor | ✅ | ✅ | ✅ | — |
| User Management | ✅ | — | — | — |
| Pengaturan (tarif, PTKP, TER) | ✅ | Lihat | Lihat | — |
| Audit Log | ✅ | — | Lihat | — |

## 4. Autentikasi (Username + Password)

**Wajib:** tidak ada kolom `email` di tabel `users`, tidak ada field email di form login, dan tidak ada fitur yang bergantung pada email.

| Aspek | Spesifikasi |
|---|---|
| Field login | `username` dan `password` |
| Format username | `^[a-z0-9._]{4,30}$`, disimpan lowercase dan unik. Input login di-`strtolower()` + `trim()`. |
| Penyimpanan password | `password_hash($pw, PASSWORD_DEFAULT)` dan diverifikasi dengan `password_verify()`. `password_needs_rehash()` dicek setiap login sukses. |
| Kebijakan password | Minimal 8 karakter, mengandung huruf dan angka |
| Session | Session CI4 (driver file/database). `session()->regenerate(true)` setelah login. Idle timeout 2 jam. |
| Remember me | Opsional, berupa token acak (hash SHA-256 disimpan di `user_tokens`) dengan masa berlaku 30 hari |
| Brute-force | Throttler CI4: maks 5 percobaan gagal per menit per IP+username. Akun dikunci 15 menit setelah 10 gagal berturut-turut (`failed_attempts`, `locked_until`). |
| CSRF | Filter `csrf` aktif global untuk semua POST |
| Pesan error | Generik: "Username atau password salah" (tidak membocorkan username mana yang ada) |
| Lupa password | Tidak ada self-service. Super Admin me-reset dari User Management. User wajib ganti password saat login berikutnya (`must_change_password = 1`). |
| Akun nonaktif | `is_active = 0` membuat user tidak bisa login, dan sesi yang sedang berjalan diputus di request berikutnya |
| Filter route | `auth` (wajib login) dan `role:hr,superadmin` (cek peran) |
| Audit | Login sukses/gagal, logout, dan ganti password dicatat ke `audit_logs` |

> **Keputusan teknis:** pakai auth custom yang ringan (Filter + Model + Service), **bukan** CodeIgniter Shield. Shield memusatkan identitas pada email; menghilangkan email darinya butuh banyak override. Auth custom lebih sederhana dan sepenuhnya terkendali.

## 5. Kebutuhan Fungsional

### F1. Dashboard
- **Admin/HR/Finance:** kartu KPI (Total Karyawan Aktif, Total Gaji Bersih bulan berjalan, Total BPJS (perusahaan + karyawan), Total PPh 21), grafik tren biaya payroll 6 bulan (Chart.js, area chart), donut distribusi biaya per departemen, tabel "Periode Payroll Terakhir" dengan badge status, kehadiran hari ini (hadir/izin/sakit/alpha), karyawan baru & ulang tahun bulan ini, aktivitas terbaru (dari audit log).
- **Karyawan:** sapaan personal, take home pay bulan terakhir, rekap kehadiran bulan berjalan, sisa pinjaman, daftar slip gaji terbaru.

### F2. Master Data
- **Departemen:** kode, nama, kepala departemen (relasi karyawan).
- **Jabatan:** kode, nama, departemen, grade, gaji pokok standar, tunjangan jabatan, flag `overtime_eligible`.
- **Karyawan:** NIP (auto `EMP-0001`), nama, NIK KTP, NPWP, jenis kelamin, tempat/tanggal lahir, alamat, no HP, departemen, jabatan, status kerja (`permanent`, `contract`, `probation`), tanggal masuk, tanggal keluar, status (`active`, `resigned`, `terminated`), status PTKP (`TK/0`–`K/3`), gaji pokok, bank, no rekening, nama pemilik rekening, flag ikut BPJS Kes/TK, foto (opsional, ada fallback avatar inisial). Daftar karyawan punya pencarian, filter departemen/status, paginasi, dan ekspor CSV.
- **Komponen Gaji:** kode, nama, tipe (`earning`/`deduction`), sifat (`fixed` per bulan atau `daily` per hari hadir), `taxable`, dan `is_bpjs_base` (masuk dasar upah BPJS atau tidak). Nominal default per komponen bisa di-override per karyawan (`employee_components`).
- **Hari Libur:** tanggal & keterangan (libur nasional/cuti bersama).
- **Pengaturan:** profil perusahaan (nama, alamat, NPWP, logo), tarif BPJS, batas upah BPJS, UMP, jam kerja, aturan keterlambatan, tabel PTKP, dan tabel TER. Semua tarif disimpan di DB (tidak di-hardcode) supaya bisa diperbarui tiap tahun.

### F3. Kehadiran & Lembur
- Input kehadiran harian per karyawan: `present`, `late`, `sick`, `leave` (cuti), `permit` (izin), `absent` (alpha), `holiday`. Jam masuk/keluar opsional.
- Tampilan **rekap bulanan** berupa grid kalender (karyawan × tanggal) dengan sel berwarna per status.
- Import CSV kehadiran (template tersedia untuk diunduh).
- Lembur: tanggal, karyawan, jam mulai–selesai, total jam, alasan, status persetujuan.

### F4. Pinjaman / Kasbon
- Pengajuan pinjaman: nominal, tenor (bulan), cicilan per bulan, mulai potong di periode tertentu.
- Status: `pending → approved → active → paid_off` (atau `rejected`).
- Cicilan dipotong otomatis saat generate payroll. Sisa pinjaman diperbarui saat periode di-*lock*.

### F5. Proses Payroll (inti)
1. HR membuat **Periode** (mis. "Oktober 2026", 1–31 Okt 2026, tanggal bayar 30 Okt 2026).
2. **Generate:** sistem menghitung semua karyawan aktif pada periode itu, termasuk karyawan yang masuk atau keluar di tengah periode (prorata).
3. **Review:** tabel ringkasan per karyawan (bruto, potongan, PPh 21, netto) dan drill-down ke rincian komponen. HR bisa menambahkan **penyesuaian manual** (bonus, THR, koreksi) lalu regenerate.
4. **Submit → Finance approve/reject** (reject wajib menyertakan catatan dan mengembalikan status ke `draft`).
5. **Tandai dibayar**, lalu ekspor file transfer bank (CSV per bank).
6. **Lock:** semua data periode menjadi read-only, dan cicilan pinjaman tercatat terbayar.
- Regenerate hanya boleh pada status `draft`, dan selalu menghapus lalu menghitung ulang di dalam DB transaction.
- Setiap perubahan status dicatat di `audit_logs`.

### F6. Slip Gaji
- Tampilan web yang premium (header perusahaan, data karyawan, tabel pendapatan & potongan, take home pay dicetak besar, terbilang dalam Bahasa Indonesia).
- Unduh PDF (Dompdf, template dengan CSS inline karena Tailwind tidak dirender Dompdf).
- Karyawan hanya bisa mengakses slip miliknya sendiri, dan hanya untuk periode berstatus `paid`/`locked`.

### F7. Laporan
- Rekap gaji per periode (per karyawan, per departemen).
- Laporan BPJS (Kesehatan, JHT, JKK, JKM, JP), memisahkan porsi perusahaan dan karyawan.
- Laporan PPh 21 bulanan (format siap pindah ke e-Bupot: NIK/NPWP, bruto, TER, PPh).
- Ekspor: CSV untuk semua laporan dan XLSX (PhpSpreadsheet) untuk rekap gaji.

### F8. User Management & Audit
- CRUD user: username, nama tampilan, peran, relasi ke karyawan (opsional untuk admin, wajib untuk peran `employee`), aktif/nonaktif, reset password.
- Audit log: waktu, user, aksi, entitas, ID entitas, data sebelum/sesudah (JSON), IP, dan user agent.

## 6. Aturan Perhitungan Payroll

> Semua nominal disimpan sebagai **integer rupiah** (`BIGINT`). Pembulatan memakai `round()` ke rupiah terdekat di setiap komponen. Semua tarif dibaca dari tabel `settings`, `ptkp_rates`, dan `ter_rates`.

### 6.1 Pendapatan
| Komponen | Rumus |
|---|---|
| Gaji Pokok | `gaji_pokok × (hari_kerja_aktif / hari_kerja_periode)`. Prorata hanya berlaku jika masuk atau keluar di tengah periode. |
| Tunjangan Jabatan | Tetap per bulan (dari jabatan, bisa di-override per karyawan), ikut prorata |
| Tunjangan Komunikasi | Tetap per bulan untuk jabatan tertentu |
| Tunjangan Transport | `Rp 30.000 × hari hadir` (status `present` + `late`) |
| Uang Makan | `Rp 35.000 × hari hadir` |
| Lembur | Upah sejam = `1/173 × (gaji pokok + tunjangan tetap)`. Hari kerja: jam ke-1 = 1,5×, jam berikutnya = 2× (PP 35/2021). Hanya untuk jabatan `overtime_eligible`. |
| Penyesuaian | Bonus/THR/koreksi manual (taxable sesuai pilihan) |

**Hari kerja periode** = Senin–Jumat dalam periode dikurangi tanggal di tabel `holidays`.

### 6.2 BPJS (default, bisa diubah di Pengaturan)
Dasar upah BPJS = gaji pokok + tunjangan tetap (komponen `is_bpjs_base = 1`).

| Program | Perusahaan | Karyawan | Batas upah |
|---|---|---|---|
| BPJS Kesehatan | 4% | 1% | Maks Rp 12.000.000 |
| JHT | 3,7% | 2% | — |
| JKK (risiko sangat rendah) | 0,24% | — | — |
| JKM | 0,30% | — | — |
| JP | 2% | 1% | Maks **Rp 10.547.400** *(nilai 2025, perbarui sesuai ketetapan BPJS TK terbaru)* |

### 6.3 PPh 21 — Metode TER (PP 58/2023, PMK 168/2023)
**Masa Januari–November:**
```
bruto_pph = total pendapatan taxable
          + premi JKK + JKM (porsi perusahaan)
          + iuran BPJS Kesehatan (porsi perusahaan)
pph21     = round(bruto_pph × tarif_TER[kategori][bruto_pph])
```
Kategori TER ditentukan dari status PTKP:
- **TER A:** TK/0, TK/1, K/0
- **TER B:** TK/2, TK/3, K/1, K/2
- **TER C:** K/3

**Masa Desember (atau bulan terakhir bagi karyawan yang keluar):** hitung ulang setahun.
```
bruto_setahun   = Σ bruto_pph Jan–Des
biaya_jabatan   = min(5% × bruto_setahun, 6.000.000)
iuran_pensiun   = Σ iuran JHT + JP porsi karyawan
neto            = bruto_setahun − biaya_jabatan − iuran_pensiun
pkp             = floor((neto − PTKP) / 1000) × 1000   (min 0)
pph_setahun     = tarif progresif Pasal 17 atas pkp
pph21_desember  = pph_setahun − Σ pph21 Jan–Nov   (boleh negatif = lebih bayar)
```
Tarif Pasal 17: 5% (≤ 60 jt), 15% (60–250 jt), 25% (250–500 jt), 30% (500 jt–5 M), 35% (> 5 M).

**PTKP per tahun:** TK/0 = 54.000.000. Tambahan kawin = 4.500.000. Tambahan per tanggungan = 4.500.000 (maks 3 tanggungan). Jadi K/0 = 58,5 jt, K/1 = 63 jt, K/2 = 67,5 jt, K/3 = 72 jt, TK/1 = 58,5 jt, TK/2 = 63 jt, TK/3 = 67,5 jt.

Tabel TER lengkap ada di **Lampiran A** dan wajib di-seed ke tabel `ter_rates`.

### 6.4 Potongan
| Potongan | Rumus |
|---|---|
| BPJS porsi karyawan | Lihat 6.2 |
| PPh 21 | Lihat 6.3 |
| Alpha (tanpa keterangan) | `(gaji_pokok / hari_kerja_periode) × hari_alpha` |
| Keterlambatan | `Rp 25.000 × jumlah hari late` (configurable) |
| Cicilan pinjaman | Cicilan aktif bulan berjalan (tidak melebihi sisa pinjaman) |

**Take Home Pay** = total pendapatan − total potongan. Jika hasilnya negatif, tandai sebagai *warning* di halaman review.

### 6.5 Contoh Hitung (wajib jadi unit test)
Karyawan hipotetis (bukan data seed), TK/0 (TER A). Gaji pokok 8.000.000, tunjangan jabatan 1.000.000, transport + makan 1.200.000, tanpa lembur dan potongan lain.

| Item | Nominal |
|---|---:|
| Dasar upah BPJS | 9.000.000 |
| BPJS Kes perusahaan 4% | 360.000 |
| JKK 0,24% | 21.600 |
| JKM 0,3% | 27.000 |
| JHT perusahaan 3,7% (non-objek pajak) | 333.000 |
| JP perusahaan 2% (non-objek pajak) | 180.000 |
| **Bruto PPh** = 8.000.000 + 1.000.000 + 1.200.000 + 360.000 + 21.600 + 27.000 | **10.608.600** |
| Tarif TER A (10.350.001–10.700.000) | 2,5% |
| **PPh 21** | **265.215** |
| BPJS Kes karyawan 1% | 90.000 |
| JHT karyawan 2% | 180.000 |
| JP karyawan 1% | 90.000 |
| **Take Home Pay** = 10.200.000 − 360.000 − 265.215 | **9.574.785** |

## 7. Data Seed (Data Realistis)

`php spark db:seed DatabaseSeeder` wajib menghasilkan data yang konsisten dan langsung bisa didemokan. Seeder **deterministik** (`mt_srand(20261021)`), sehingga hasilnya sama setiap kali dijalankan.

### 7.1 Urutan Seeder
1. `SettingSeeder`: profil perusahaan, tarif BPJS, batas upah, UMP DKI (Rp 5.396.761, nilai 2025, perbarui), aturan keterlambatan
2. `PtkpSeeder`: 8 status PTKP
3. `TerRateSeeder`: tabel TER A/B/C lengkap (Lampiran A)
4. `HolidaySeeder`: libur nasional 2026 sesuai SKB 3 Menteri (minimal 17 Agu 2026 dan Maulid Nabi; **verifikasi tanggal**)
5. `DepartmentSeeder`: 6 departemen
6. `PositionSeeder`: 20 jabatan
7. `SalaryComponentSeeder`: komponen gaji standar
8. `EmployeeSeeder`: 31 karyawan (Lampiran B)
9. `UserSeeder`: 1 superadmin dan akun untuk setiap karyawan aktif
10. `AttendanceSeeder`: kehadiran Agustus, September, dan Oktober 2026 (Oktober s.d. H-1 saat seeding). Distribusi: ±92% hadir, ±4% late, ±2% sakit, ±1% izin/cuti, ±1% alpha. Lembur acak 0–12 jam/bulan untuk jabatan eligible.
11. `LoanSeeder`: 4 pinjaman (2 aktif, 1 lunas, 1 pending)
12. `PayrollHistorySeeder`: membuat periode **Agustus 2026 (locked)** dan **September 2026 (paid)** dengan **memanggil `PayrollService` yang sama** dengan produksi, serta **Oktober 2026 (draft)** yang sudah di-generate untuk demo approval
13. `AuditLogSeeder`: beberapa jejak aktivitas contoh

### 7.2 Perusahaan (fiktif)
**PT Nusantara Digital Solusi**, Gedung Graha Niaga Lt. 12, Jl. Jend. Sudirman Kav. 58, Jakarta Selatan 12190. NPWP perusahaan: `0012345678901000` (fiktif).

### 7.3 Akun Default
| Username | Password | Peran |
|---|---|---|
| `admin` | `Admin#2026` | superadmin |
| `sinta.maharani` | `Payroll#2026` | hr |
| `putri.lestari` | `Payroll#2026` | hr |
| `budi.santoso` | `Payroll#2026` | finance |
| *username karyawan lain* | `Payroll#2026` | employee |

> Tampilkan tabel ini di README. Di environment `production`, seeder akun harus memaksa `must_change_password = 1`.

## 8. Kebutuhan UI/UX: Modern & Premium

### 8.1 Stack Front-end (semua via CDN, tanpa build step)
```html
<!-- Tailwind CSS v4 (browser build) -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<!-- Font -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Interaksi ringan -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<!-- Ikon -->
<script src="https://unpkg.com/lucide@latest"></script>
<!-- Grafik -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
```
Design token didefinisikan sekali di `app/Views/layouts/partials/head.php`:
```html
<style type="text/tailwindcss">
  @theme {
    --font-sans: "Plus Jakarta Sans", ui-sans-serif, system-ui;
    --color-brand-50:  #eef2ff;  --color-brand-100: #e0e7ff;
    --color-brand-500: #6366f1;  --color-brand-600: #4f46e5;
    --color-brand-700: #4338ca;  --color-ink-950:   #0b1020;
  }
</style>
```

### 8.2 Bahasa Visual
| Elemen | Pedoman |
|---|---|
| Palet | Netral `slate`, aksen `brand` (indigo) dengan gradien `from-brand-600 to-violet-600` untuk elemen hero/CTA. Status: emerald (sukses/paid), amber (review/pending), rose (rejected/alpha), sky (info/draft). |
| Layout | Sidebar gelap `bg-ink-950` (lebar 272px, bisa di-collapse, jadi drawer di mobile), topbar putih transparan `backdrop-blur` sticky berisi breadcrumb, pencarian global, dan menu user. Konten di atas latar `bg-slate-50`. |
| Kartu | `rounded-2xl bg-white ring-1 ring-slate-200/70 shadow-[0_1px_2px_rgba(16,24,40,.04),0_8px_24px_-12px_rgba(16,24,40,.08)]` |
| KPI card | Ikon dalam kotak gradien, angka besar `text-2xl font-bold tracking-tight`, delta vs bulan lalu (panah hijau/merah), dan mini sparkline |
| Tabel | Header sticky `text-xs uppercase tracking-wider text-slate-500`, baris `hover:bg-slate-50`, angka rata kanan dengan `tabular-nums`, avatar inisial berwarna, badge status berbentuk pill, aksi di dropdown kebab |
| Form | Label di atas, input `rounded-xl ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500`, pesan error inline merah, input rupiah dengan prefix "Rp" dan format ribuan otomatis (Alpine) |
| Feedback | Toast pojok kanan atas (flashdata), modal konfirmasi untuk aksi destruktif/status, empty state dengan ilustrasi ikon dan CTA, loading state pada tombol submit |
| Login | Split screen. Kiri: panel gradien mesh (indigo → violet → fuchsia) dengan logo, tagline, dan kartu "glass" statistik ilustratif. Kanan: form minimalis berisi **Username** & **Password** (toggle show/hide), checkbox "Ingat saya", dan tombol gradien penuh. |
| Slip gaji | Tampilan dokumen A4 dengan header perusahaan, grid dua kolom Pendapatan/Potongan, kotak Take Home Pay bergradien, terbilang, dan tombol Unduh PDF / Cetak |
| Responsif | Mobile-first. Tabel jadi kartu bertumpuk di `< md`. |
| Aksesibilitas | Kontras minimal AA, fokus terlihat (`focus-visible:ring`), `aria-label` pada tombol ikon |
| Format | Rupiah `Rp 9.574.785`, tanggal `21 Okt 2026`, locale `id_ID` |
| Dark mode | *Nice-to-have* (v1.1), cukup siapkan kelas `dark:` di komponen inti |

### 8.3 Daftar Halaman
| # | Halaman | Route |
|---|---|---|
| 1 | Login | `GET/POST /login` |
| 2 | Ganti Password | `/account/password` |
| 3 | Dashboard | `/dashboard` |
| 4 | Departemen | `/departments` |
| 5 | Jabatan | `/positions` |
| 6 | Karyawan (list, detail, form) | `/employees`, `/employees/{id}` |
| 7 | Komponen Gaji | `/salary-components` |
| 8 | Kehadiran (harian & rekap) | `/attendance`, `/attendance/recap` |
| 9 | Lembur | `/overtime` |
| 10 | Pinjaman | `/loans` |
| 11 | Periode Payroll (list) | `/payroll` |
| 12 | Detail Periode / Review | `/payroll/{periodId}` |
| 13 | Rincian Gaji Karyawan | `/payroll/{periodId}/employees/{id}` |
| 14 | Slip Gaji (web & PDF) | `/payslips/{payrollId}`, `/payslips/{payrollId}/pdf` |
| 15 | Slip Saya (karyawan) | `/my/payslips` |
| 16 | Laporan | `/reports/*` |
| 17 | User Management | `/users` |
| 18 | Pengaturan | `/settings` |
| 19 | Audit Log | `/audit-logs` |
| 20 | Error 403/404 | halaman bergaya sesuai tema |

## 9. Arsitektur Teknis

| Lapisan | Pilihan |
|---|---|
| Framework | CodeIgniter **4.6+** (appstarter) |
| PHP | 8.2+ (lokal: PHP 8.3 via Laravel Herd) |
| Database | MySQL 8 / MariaDB 10.6+ (`utf8mb4_unicode_ci`) |
| Paket Composer | `dompdf/dompdf` (PDF), `phpoffice/phpspreadsheet` (XLSX) |
| Front-end | Tailwind v4 CDN, Alpine.js, Lucide, Chart.js (lihat 8.1) |
| Testing | PHPUnit bawaan CI4 (`CIUnitTestCase`, `DatabaseTestTrait`) |
| Server lokal | Laravel Herd (`payroll-app.test` → `public/`) atau `php spark serve` |

### 9.1 Struktur Kode
```
app/
├─ Config/Routes.php, Filters.php
├─ Controllers/
│  ├─ AuthController.php, DashboardController.php
│  ├─ Master/{Department,Position,Employee,SalaryComponent,Holiday}Controller.php
│  ├─ {Attendance,Overtime,Loan,Payroll,Payslip,Report,User,Setting,AuditLog}Controller.php
│  └─ My/{Dashboard,Payslip}Controller.php
├─ Filters/{AuthFilter,RoleFilter}.php
├─ Models/  (satu model per tabel, dengan $allowedFields & validation rules)
├─ Services/
│  ├─ AuthService.php
│  ├─ Payroll/{PayrollService,EarningCalculator,BpjsCalculator,Pph21Calculator,
│  │           OvertimeCalculator,WorkdayCalendar,PayrollStateMachine}.php
│  ├─ AuditLogger.php
│  └─ Export/{BankTransferExporter,ReportExporter}.php
├─ Helpers/{format_helper.php (rupiah, tanggal_id, terbilang), ui_helper.php}
├─ Views/
│  ├─ layouts/{app.php, auth.php, partials/{head,sidebar,topbar,toast}.php}
│  ├─ components/{kpi_card,badge,table,modal,empty_state,form_input,pagination}.php
│  └─ <modul>/...
└─ Database/{Migrations,Seeds}/
tests/unit/Payroll/*Test.php
```
Aturan: controller tetap tipis, seluruh logika hitung ada di `Services/Payroll`, dan service bersifat murni (input DTO/array → output array) sehingga mudah di-unit-test.

### 9.2 Skema Database (ringkas)
| Tabel | Kolom penting |
|---|---|
| `users` | id, **username (unique)**, password_hash, name, role, employee_id (nullable FK), is_active, must_change_password, failed_attempts, locked_until, last_login_at, timestamps. **Tanpa kolom email.** |
| `user_tokens` | id, user_id, token_hash, expires_at |
| `departments` | id, code, name, head_employee_id |
| `positions` | id, department_id, code, name, grade, base_salary, position_allowance, communication_allowance, overtime_eligible |
| `employees` | id, nip, full_name, nik, npwp, gender, birth_place, birth_date, address, phone, department_id, position_id, employment_type, status, join_date, resign_date, ptkp_status, basic_salary, bank_name, bank_account_no, bank_account_name, bpjs_kes_enrolled, bpjs_tk_enrolled, photo, timestamps, deleted_at |
| `salary_components` | id, code, name, type, calc_type, default_amount, taxable, is_bpjs_base, is_system, sort_order |
| `employee_components` | id, employee_id, salary_component_id, amount |
| `holidays` | id, date (unique), name |
| `attendances` | id, employee_id, date, status, check_in, check_out, note. UNIQUE(employee_id, date) |
| `overtimes` | id, employee_id, date, start_time, end_time, hours, reason, status, approved_by |
| `loans` | id, employee_id, amount, tenor_months, installment, start_period, remaining, status, approved_by |
| `loan_installments` | id, loan_id, payroll_id, amount, paid_at |
| `payroll_periods` | id, name, year, month, start_date, end_date, pay_date, status, generated_at, approved_by, approved_at, paid_at, locked_at, notes |
| `payrolls` | id, period_id, employee_id, snapshot (JSON: jabatan, dept, PTKP, bank saat itu), workdays, present_days, gross_income, bruto_pph, total_deduction, pph21, ter_category, ter_rate, bpjs_company_total, bpjs_employee_total, take_home_pay. UNIQUE(period_id, employee_id) |
| `payroll_items` | id, payroll_id, code, name, type (earning/deduction/company_contribution), amount, taxable, meta (JSON), sort_order |
| `payroll_adjustments` | id, period_id, employee_id, name, type, amount, taxable, created_by |
| `settings` | key (PK), value, group, type |
| `ptkp_rates` | status (PK), amount, ter_category |
| `ter_rates` | id, category (A/B/C), min_income, max_income (nullable = tak terhingga), rate (DECIMAL(5,2)) |
| `audit_logs` | id, user_id, action, entity, entity_id, before (JSON), after (JSON), ip_address, user_agent, created_at |

Semua FK diberi index. Nominal uang bertipe `BIGINT`, persentase `DECIMAL(6,3)`.

## 10. Kebutuhan Non-Fungsional
- **Keamanan:** CSRF, escaping output dengan `esc()` di semua view, Query Builder (tanpa raw SQL dari input), filter peran di level route **dan** pengecekan kepemilikan data (karyawan hanya bisa melihat data miliknya), security headers (`secureheaders` filter), dan NIK/NPWP disamarkan di daftar (`3174•••••••••012`).
- **Integritas:** generate/regenerate/lock dijalankan di dalam `db->transStart()`, dan periode `locked` ditolak oleh semua endpoint ubah data.
- **Performa:** generate payroll 500 karyawan < 10 detik, halaman list < 300 ms dengan paginasi 25 baris, dan eager-load relasi untuk menghindari N+1.
- **Kualitas:** unit test untuk BPJS, TER, PPh Desember, lembur, dan prorata (minimal mencakup contoh 6.5), serta feature test login dan akses peran.
- **Lokalisasi:** UI berbahasa Indonesia, zona waktu `Asia/Jakarta`.

## 11. Kriteria Penerimaan (Definition of Done v1)
1. `composer install && php spark migrate && php spark db:seed DatabaseSeeder` berjalan tanpa error di database kosong.
2. Login dengan `admin` / `Admin#2026` berhasil, form login tidak punya field email, dan tabel `users` tidak punya kolom email.
3. Dashboard menampilkan angka nyata dari DB: 30 karyawan aktif, 3 periode payroll, dan grafik terisi.
4. Periode Agustus (locked) dan September (paid) punya slip gaji untuk setiap karyawan, dengan angka yang sama persis dengan hasil regenerate oleh `PayrollService`.
5. Periode Oktober (draft) bisa dibawa sampai `locked` melalui alur HR → Finance.
6. Slip gaji bisa diunduh sebagai PDF, dan karyawan `andi.pratama` hanya bisa melihat slip miliknya (akses slip orang lain menghasilkan 403).
7. `php spark test` hijau, termasuk test contoh hitung 6.5.
8. Semua halaman responsif dan konsisten dengan pedoman visual di bagian 8.

---

## Lampiran A — Tabel TER Bulanan (PP 58/2023)

> ⚠️ Disalin untuk keperluan seeder. **Verifikasi ulang terhadap Lampiran PP 58/2023 sebelum dipakai di production.** Batas bawah setiap baris = batas atas baris sebelumnya + 1.

**Kategori A** (TK/0, TK/1, K/0), dengan batas atas penghasilan bruto per bulan:
| s.d. | Tarif | s.d. | Tarif | s.d. | Tarif |
|---:|---:|---:|---:|---:|---:|
| 5.400.000 | 0% | 15.100.000 | 6% | 62.200.000 | 20% |
| 5.650.000 | 0,25% | 16.950.000 | 7% | 68.600.000 | 21% |
| 5.950.000 | 0,5% | 19.750.000 | 8% | 77.500.000 | 22% |
| 6.300.000 | 0,75% | 24.150.000 | 9% | 89.000.000 | 23% |
| 6.750.000 | 1% | 26.450.000 | 10% | 103.000.000 | 24% |
| 7.500.000 | 1,25% | 28.000.000 | 11% | 125.000.000 | 25% |
| 8.550.000 | 1,5% | 30.050.000 | 12% | 157.000.000 | 26% |
| 9.650.000 | 1,75% | 32.400.000 | 13% | 206.000.000 | 27% |
| 10.050.000 | 2% | 35.400.000 | 14% | 337.000.000 | 28% |
| 10.350.000 | 2,25% | 39.100.000 | 15% | 454.000.000 | 29% |
| 10.700.000 | 2,5% | 43.850.000 | 16% | 550.000.000 | 30% |
| 11.050.000 | 3% | 47.800.000 | 17% | 695.000.000 | 31% |
| 11.600.000 | 3,5% | 51.400.000 | 18% | 910.000.000 | 32% |
| 12.500.000 | 4% | 56.300.000 | 19% | 1.400.000.000 | 33% |
| 13.750.000 | 5% | | | > 1.400.000.000 | 34% |

**Kategori B** (TK/2, TK/3, K/1, K/2):
| s.d. | Tarif | s.d. | Tarif | s.d. | Tarif |
|---:|---:|---:|---:|---:|---:|
| 6.200.000 | 0% | 18.450.000 | 7% | 71.000.000 | 21% |
| 6.500.000 | 0,25% | 21.850.000 | 8% | 80.000.000 | 22% |
| 6.850.000 | 0,5% | 26.000.000 | 9% | 93.000.000 | 23% |
| 7.300.000 | 0,75% | 27.700.000 | 10% | 109.000.000 | 24% |
| 9.200.000 | 1% | 29.350.000 | 11% | 129.000.000 | 25% |
| 10.750.000 | 1,5% | 31.450.000 | 12% | 163.000.000 | 26% |
| 11.250.000 | 2% | 33.950.000 | 13% | 211.000.000 | 27% |
| 11.600.000 | 2,5% | 37.100.000 | 14% | 374.000.000 | 28% |
| 12.600.000 | 3% | 41.100.000 | 15% | 459.000.000 | 29% |
| 13.600.000 | 4% | 45.800.000 | 16% | 555.000.000 | 30% |
| 14.950.000 | 5% | 49.500.000 | 17% | 704.000.000 | 31% |
| 16.400.000 | 6% | 53.800.000 | 18% | 957.000.000 | 32% |
| | | 58.500.000 | 19% | 1.405.000.000 | 33% |
| | | 64.000.000 | 20% | > 1.405.000.000 | 34% |

**Kategori C** (K/3):
| s.d. | Tarif | s.d. | Tarif | s.d. | Tarif |
|---:|---:|---:|---:|---:|---:|
| 6.600.000 | 0% | 17.050.000 | 6% | 66.700.000 | 20% |
| 6.950.000 | 0,25% | 19.500.000 | 7% | 74.500.000 | 21% |
| 7.350.000 | 0,5% | 22.700.000 | 8% | 83.200.000 | 22% |
| 7.800.000 | 0,75% | 26.600.000 | 9% | 95.600.000 | 23% |
| 8.850.000 | 1% | 28.100.000 | 10% | 110.000.000 | 24% |
| 9.800.000 | 1,25% | 30.100.000 | 11% | 134.000.000 | 25% |
| 10.950.000 | 1,5% | 32.600.000 | 12% | 169.000.000 | 26% |
| 11.200.000 | 1,75% | 35.400.000 | 13% | 221.000.000 | 27% |
| 12.050.000 | 2% | 38.900.000 | 14% | 390.000.000 | 28% |
| 12.950.000 | 3% | 43.000.000 | 15% | 463.000.000 | 29% |
| 14.150.000 | 4% | 47.400.000 | 16% | 561.000.000 | 30% |
| 15.550.000 | 5% | 51.200.000 | 17% | 709.000.000 | 31% |
| | | 55.800.000 | 18% | 965.000.000 | 32% |
| | | 60.400.000 | 19% | 1.419.000.000 | 33% |
| | | | | > 1.419.000.000 | 34% |

## Lampiran B — Data Seed Organisasi & Karyawan

### B.1 Departemen
| Kode | Nama |
|---|---|
| DIR | Direksi |
| HRD | Human Resources |
| FIN | Finance & Accounting |
| ENG | Engineering |
| SLS | Sales & Marketing |
| OPS | Operations & General Affair |

### B.2 Jabatan
| Kode | Jabatan | Dept | Grade | Gaji Pokok Std | Tunj. Jabatan | Tunj. Komunikasi | Lembur |
|---|---|---|---|---:|---:|---:|:-:|
| DIR-01 | Direktur Utama | DIR | 10 | 45.000.000 | 10.000.000 | 1.000.000 | — |
| DIR-02 | Direktur Operasional | DIR | 9 | 38.000.000 | 8.000.000 | 1.000.000 | — |
| HRD-01 | HR Manager | HRD | 7 | 18.000.000 | 4.000.000 | 500.000 | — |
| HRD-02 | HR Generalist | HRD | 4 | 8.500.000 | 1.000.000 | 200.000 | ✅ |
| HRD-03 | Payroll Officer | HRD | 4 | 8.000.000 | 1.000.000 | 200.000 | ✅ |
| HRD-04 | Talent Acquisition | HRD | 3 | 7.000.000 | 750.000 | 200.000 | ✅ |
| FIN-01 | Finance Manager | FIN | 7 | 20.000.000 | 4.000.000 | 500.000 | — |
| FIN-02 | Accountant | FIN | 5 | 9.500.000 | 1.250.000 | 200.000 | ✅ |
| FIN-03 | Finance Staff | FIN | 3 | 6.500.000 | 500.000 | 150.000 | ✅ |
| ENG-01 | Engineering Manager | ENG | 8 | 28.000.000 | 6.000.000 | 750.000 | — |
| ENG-02 | Senior Software Engineer | ENG | 6 | 19.000.000 | 2.500.000 | 300.000 | ✅ |
| ENG-03 | Software Engineer | ENG | 5 | 12.000.000 | 1.500.000 | 300.000 | ✅ |
| ENG-04 | QA Engineer | ENG | 4 | 10.000.000 | 1.250.000 | 200.000 | ✅ |
| ENG-05 | UI/UX Designer | ENG | 5 | 11.000.000 | 1.250.000 | 200.000 | ✅ |
| SLS-01 | Sales & Marketing Manager | SLS | 7 | 17.000.000 | 3.500.000 | 500.000 | — |
| SLS-02 | Account Executive | SLS | 3 | 7.500.000 | 750.000 | 300.000 | ✅ |
| SLS-03 | Digital Marketing Specialist | SLS | 3 | 8.000.000 | 750.000 | 200.000 | ✅ |
| OPS-01 | Operations Supervisor | OPS | 5 | 9.000.000 | 1.500.000 | 200.000 | ✅ |
| OPS-02 | Office Administrator | OPS | 2 | 5.800.000 | 400.000 | 100.000 | ✅ |
| OPS-03 | Driver / Office Support | OPS | 1 | 5.400.000 | 0 | 0 | ✅ |

### B.3 Karyawan
NIK KTP (16 digit), NPWP (16 digit), no HP, alamat, tanggal lahir, dan no rekening dibangkitkan **deterministik** oleh seeder dengan format valid tetapi **fiktif**.

| NIP | Nama Lengkap | Username | Jabatan | Status Kerja | Masuk | Gaji Pokok | PTKP | Bank |
|---|---|---|---|---|---|---:|---|---|
| EMP-0001 | Hendra Wijaya | hendra.wijaya | Direktur Utama | permanent | 2015-03-02 | 45.000.000 | K/3 | BCA |
| EMP-0002 | Ratna Kusumawati | ratna.kusumawati | Direktur Operasional | permanent | 2016-01-11 | 38.000.000 | K/2 | Mandiri |
| EMP-0003 | Sinta Maharani | sinta.maharani | HR Manager | permanent | 2018-07-02 | 18.000.000 | K/1 | BCA |
| EMP-0004 | Dimas Saputra | dimas.saputra | HR Generalist | permanent | 2022-02-14 | 8.500.000 | TK/0 | BNI |
| EMP-0005 | Putri Ayu Lestari | putri.lestari | Payroll Officer | permanent | 2021-09-01 | 8.000.000 | TK/0 | BCA |
| EMP-0006 | Anisa Rahmawati | anisa.rahmawati | Talent Acquisition | probation | **2026-08-03** | 7.000.000 | TK/0 | BNI |
| EMP-0007 | Budi Santoso | budi.santoso | Finance Manager | permanent | 2017-05-15 | 20.000.000 | K/2 | Mandiri |
| EMP-0008 | Yuliana Hartono | yuliana.hartono | Accountant | permanent | 2020-10-05 | 9.500.000 | K/0 | BCA |
| EMP-0009 | Fajar Nugroho | fajar.nugroho | Finance Staff | contract | 2023-03-20 | 6.500.000 | TK/0 | BRI |
| EMP-0010 | Arief Rahman | arief.rahman | Engineering Manager | permanent | 2017-08-01 | 28.000.000 | K/2 | BCA |
| EMP-0011 | Kevin Tanoto | kevin.tanoto | Senior Software Engineer | permanent | 2019-04-08 | 19.000.000 | K/1 | BCA |
| EMP-0012 | Nadia Putri Anggraini | nadia.anggraini | Senior Software Engineer | permanent | 2019-11-18 | 19.500.000 | TK/0 | Mandiri |
| EMP-0013 | Rizky Firmansyah | rizky.firmansyah | Software Engineer | permanent | 2021-06-07 | 12.500.000 | TK/0 | BNI |
| EMP-0014 | Andi Pratama | andi.pratama | Software Engineer | permanent | 2022-01-10 | 12.000.000 | TK/0 | BCA |
| EMP-0015 | Galih Prasetyo | galih.prasetyo | Software Engineer | permanent | 2023-02-01 | 11.500.000 | K/0 | BRI |
| EMP-0016 | Taufik Hidayat | taufik.hidayat | Software Engineer | contract | 2025-07-01 | 10.500.000 | TK/0 | BCA |
| EMP-0017 | Mega Safitri | mega.safitri | QA Engineer | permanent | 2022-08-15 | 10.000.000 | TK/1 | BCA |
| EMP-0018 | Yoga Aditya | yoga.aditya | QA Engineer | contract | 2024-01-08 | 9.800.000 | TK/0 | Mandiri |
| EMP-0019 | Larasati Wulandari | larasati.wulandari | UI/UX Designer | permanent | 2021-03-15 | 11.000.000 | TK/0 | BCA |
| EMP-0020 | Bayu Setiawan | bayu.setiawan | Sales & Marketing Manager | permanent | 2018-02-05 | 17.000.000 | K/2 | BNI |
| EMP-0021 | Citra Dewi | citra.dewi | Account Executive | permanent | 2023-05-02 | 7.500.000 | TK/0 | BCA |
| EMP-0022 | Eko Wahyudi | eko.wahyudi | Account Executive | permanent | 2022-11-14 | 7.800.000 | K/1 | BRI |
| EMP-0023 | Intan Permatasari | intan.permatasari | Account Executive | contract | 2024-04-01 | 7.500.000 | TK/0 | Mandiri |
| EMP-0024 | Reza Mahendra | reza.mahendra | Digital Marketing Specialist | permanent | 2023-09-11 | 8.000.000 | TK/0 | BCA |
| EMP-0025 | Siti Nurhaliza | siti.nurhaliza | Digital Marketing Specialist | permanent | 2022-07-04 | 8.200.000 | K/0 | BNI |
| EMP-0026 | Agus Salim | agus.salim | Operations Supervisor | permanent | 2016-10-03 | 9.000.000 | K/3 | BRI |
| EMP-0027 | Dewi Anjani | dewi.anjani | Office Administrator | permanent | 2023-01-16 | 5.800.000 | TK/0 | BCA |
| EMP-0028 | Wulan Sari | wulan.sari | Office Administrator | contract | 2024-06-03 | 5.800.000 | K/1 | Mandiri |
| EMP-0029 | Joko Susilo | joko.susilo | Driver / Office Support | permanent | 2019-03-04 | 5.400.000 | K/2 | BRI |
| EMP-0030 | Slamet Riyadi | slamet.riyadi | Driver / Office Support | permanent | 2020-08-18 | 5.400.000 | K/1 | BRI |
| EMP-0031 | Hadi Gunawan | hadi.gunawan | Software Engineer | **resigned 2026-09-15** | 2020-02-03 | 13.000.000 | K/1 | BCA |

Skenario khusus yang sengaja ditanam di seed:
- **Anisa** masuk 3 Agu 2026, sehingga gaji Agustus dihitung prorata.
- **Hadi** resign 15 Sep 2026, sehingga gaji September prorata dan namanya tidak muncul di Oktober. Akun loginnya nonaktif.
- **Kevin** & **Rizky** punya pinjaman aktif (cicilan terpotong di setiap periode).
- **Galih** punya 1 hari alpha di September (potongan alpha muncul di slip).
- Kepala departemen: DIR→Hendra, HRD→Sinta, FIN→Budi, ENG→Arief, SLS→Bayu, OPS→Agus.
