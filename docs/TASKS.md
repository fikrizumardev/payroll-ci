# TASKS — Build Sistem Payroll (CodeIgniter 4)

Acuan: [`PRD.md`](./PRD.md). Kerjakan berurutan per fase. Setiap task punya **AC** (*acceptance criteria*) dan baru boleh dicentang jika AC terpenuhi.

**Legenda:** `[ ]` belum · `[x]` selesai · **P0** wajib v1 · **P1** penting · **P2** nice-to-have

---

## Fase 0 — Setup Proyek

- [x] **T-001 (P0) Inisialisasi CodeIgniter 4**
  - `composer create-project codeigniter4/appstarter .` di `C:\Laravel-herd\payroll-app` (folder `docs/` dipindah sementara atau dipertahankan).
  - Salin `env` → `.env`, set `CI_ENVIRONMENT = development`, `app.baseURL = 'http://localhost:8080/'`, `app.appTimezone = 'Asia/Jakarta'`, `app.defaultLocale = 'id'`.
  - AC: `php spark serve` atau `http://payroll-app.test` menampilkan halaman welcome CI4.

- [x] **T-002 (P0) Konfigurasi database**
  - Buat DB `payroll_app` (utf8mb4_unicode_ci) di MySQL/MariaDB (Herd Pro / DBngin / XAMPP).
  - Isi `database.default.*` di `.env`.
  - AC: `php spark db:table --show` terhubung tanpa error.

- [x] **T-003 (P0) Paket Composer**
  - `composer require dompdf/dompdf phpoffice/phpspreadsheet`.
  - AC: autoload jalan dan kedua kelas bisa di-instantiate.

- [x] **T-004 (P1) Konvensi & tooling**
  - Tambahkan `.gitignore` (vendor, writable, .env), `git init`, dan commit awal.
  - Buat `README.md` berisi langkah instalasi dan tabel akun default (PRD §7.3).
  - AC: repo bersih, dan README bisa diikuti dari nol.

## Fase 1 — Migrasi Database

> Satu file migrasi per tabel, urut sesuai dependensi FK. Uang = `BIGINT`, persentase = `DECIMAL(6,3)`, semua tabel punya `created_at`/`updated_at`.

- [x] **T-101 (P0)** `settings`, `ptkp_rates`, `ter_rates`, `holidays`
- [x] **T-102 (P0)** `departments`, `positions`
- [x] **T-103 (P0)** `employees` (soft delete; FK ke departments & positions; unique `nip`, `nik`)
- [x] **T-104 (P0)** Tambah FK `departments.head_employee_id → employees.id` (migrasi terpisah karena sirkular)
- [x] **T-105 (P0)** `users` (**`username` UNIQUE, tanpa kolom email**), `user_tokens`
- [x] **T-106 (P0)** `salary_components`, `employee_components`
- [x] **T-107 (P0)** `attendances` (UNIQUE employee_id+date), `overtimes`
- [x] **T-108 (P0)** `loans`, `loan_installments` (migrasi `loan_installments` dijalankan setelah `payrolls` karena FK `payroll_id`)
- [x] **T-109 (P0)** `payroll_periods` (UNIQUE year+month), `payrolls` (UNIQUE period_id+employee_id), `payroll_items`, `payroll_adjustments`
- [x] **T-110 (P0)** `audit_logs`
- AC fase: `php spark migrate:refresh` sukses dua kali berturut-turut, dan `php spark db:table users` menunjukkan tidak ada kolom `email`.

## Fase 2 — Model & Helper

- [x] **T-201 (P0)** Model untuk setiap tabel (`$allowedFields`, `$validationRules`, `$useTimestamps`, `$returnType = 'array'`, `$useSoftDeletes` untuk Employee).
- [x] **T-202 (P0)** `format_helper.php`: `rupiah(int): string` → `Rp 9.574.785`, `tanggal_id(date, format)` → `21 Okt 2026`, `bulan_id(int)`, `terbilang(int)` → "sembilan juta lima ratus tujuh puluh empat ribu tujuh ratus delapan puluh lima rupiah".
- [x] **T-203 (P0)** `ui_helper.php`: `status_badge(string)`, `initials(name)`, `avatar_color(name)`, `mask_id(string)`.
- [x] **T-204 (P0)** `SettingService` dengan cache per request: `get('bpjs.kes.company_rate')`, dst.
- AC: unit test `terbilang` dan `rupiah` lulus untuk 0, 1.000, 1.000.000, 9.574.785, dan 1.250.000.000.

## Fase 3 — Seeder Data Realistis (sebelum UI, supaya UI langsung dibangun di atas data nyata)

- [x] **T-301 (P0)** `SettingSeeder`: profil PT Nusantara Digital Solusi, tarif BPJS, batas upah, UMP, denda telat Rp 25.000. *(Tarif transport Rp 30.000 & makan Rp 35.000 per hari disimpan di `salary_components.default_amount` agar hanya ada satu sumber nilai.)*
- [x] **T-302 (P0)** `PtkpSeeder`: 8 status beserta nominal dan kategori TER.
- [x] **T-303 (P0)** `TerRateSeeder`: seluruh baris TER A/B/C dari PRD Lampiran A. Baris terakhir memakai `max_income = NULL`.
  - AC: query tarif untuk bruto 10.608.600 kategori A mengembalikan 2,5%, dan untuk 5.400.000 mengembalikan 0%.
- [x] **T-304 (P0)** `HolidaySeeder`: libur nasional & cuti bersama 2026 (verifikasi dengan SKB 3 Menteri 2026).
- [x] **T-305 (P0)** `DepartmentSeeder` + `PositionSeeder` (PRD Lampiran B.1–B.2).
- [x] **T-306 (P0)** `SalaryComponentSeeder`: `BASIC`, `POS_ALW`, `COMM_ALW`, `TRANSPORT` (daily), `MEAL` (daily), `OVERTIME`, `ADJ`, `BPJS_KES_EE`, `JHT_EE`, `JP_EE`, `PPH21`, `LATE_PEN`, `ABSENT_DED`, `LOAN`, dan kontribusi perusahaan `BPJS_KES_ER`, `JKK_ER`, `JKM_ER`, `JHT_ER`, `JP_ER`.
- [x] **T-307 (P0)** `EmployeeSeeder`: 31 karyawan sesuai Lampiran B.3. Generator deterministik untuk NIK (prefix kode wilayah DKI `3171`–`3175` + tanggal lahir + urut), NPWP 16 digit = NIK, HP `0812…`, alamat Jakarta/Tangerang/Bekasi/Depok, dan no rekening sesuai panjang digit bank (BCA 10, Mandiri 13, BNI 10, BRI 15). Set kepala departemen setelah insert.
- [x] **T-308 (P0)** `UserSeeder`: `admin` (superadmin), `sinta.maharani` & `putri.lestari` (hr), `budi.santoso` (finance), sisanya `employee`. Password di-hash. `hadi.gunawan` dibuat dengan `is_active = 0`.
- [x] **T-309 (P0)** `AttendanceSeeder`: hari kerja Agu–Okt 2026 (Oktober s.d. kemarin), RNG deterministik (`Random\Randomizer` + `Mt19937`, seed 20261021), distribusi sesuai PRD §7.1, tidak ada record sebelum `join_date` atau sesudah `resign_date`, dan Galih wajib 1× `absent` di September. Lembur acak untuk jabatan eligible, status `approved` (lembur 7 hari terakhir dibiarkan `pending` untuk demo persetujuan).
- [x] **T-310 (P0)** `LoanSeeder`: Kevin 12.000.000 / 12 bulan (mulai Juli 2026), Rizky 6.000.000 / 6 bulan (mulai Agu 2026), Dewi 3.000.000 / 3 bulan (lunas Juli 2026, status `paid_off`), Fajar 2.000.000 (status `pending`).
- [x] **T-311 (P0)** `PayrollHistorySeeder`: buat periode Agu, Sep, Okt 2026, lalu panggil `PayrollService::generate()` (**dependensi: Fase 6**). Bawa Agustus ke `locked`, September ke `paid`, dan Oktober tetap `draft`. Approver = `budi.santoso`.
- [x] **T-312 (P1)** `AuditLogSeeder`: ±20 aktivitas contoh (login, master data, pinjaman). Aktivitas generate/approve payroll ditambahkan oleh T-311.
- [x] **T-313 (P0)** `DatabaseSeeder` memanggil semua seeder dengan urutan yang benar di dalam transaksi, dengan guard: tolak jalan di `production` kecuali diberi `--force`.
- AC fase: `php spark migrate:refresh && php spark db:seed DatabaseSeeder` menghasilkan 31 employees, 32 users (31 aktif termasuk `admin`, 1 nonaktif), ±1.600–1.900 attendances (tergantung tanggal seeding), 3 payroll_periods, serta payrolls sebanyak 31 (Agu), 31 (Sep, Hadi prorata), dan 30 (Okt). Menjalankan ulang menghasilkan angka yang sama persis.

> Catatan urutan kerja: T-301..T-310 dikerjakan sekarang. T-311 ditulis setelah Fase 6 selesai, lalu seed ulang.

## Fase 4 — Autentikasi Username + Password

- [x] **T-401 (P0) `AuthService`**: `attempt(username, password, remember)`, `logout()`, `user()`, `check()`, `changePassword()`. Normalisasi username dengan `strtolower(trim())`. Gunakan `password_verify` dan `password_needs_rehash`. Kelola `failed_attempts`/`locked_until`. Panggil `session()->regenerate(true)`.
- [x] **T-402 (P0) `AuthController`**: `GET /login`, `POST /login` (validasi: username `required|regex_match[/^[a-z0-9._]{4,30}$/i]`, password `required`), `POST /logout`. Pakai Throttler maks 5 request/menit per IP+username.
  - Gagal login tidak memakai `withInput()` (agar password tidak tersimpan di flash session); hanya username yang di-flash. Throttler tambahan 20 percobaan/menit per IP.
- [x] **T-403 (P0) Filter**: `AuthFilter` (redirect ke `/login`, cek `is_active`, paksa redirect ke ganti password jika `must_change_password`) dan `RoleFilter` (`role:hr,superadmin` → 403 jika tidak cocok). Daftarkan di `Config/Filters.php`. CSRF diaktifkan global.
- [x] **T-404 (P0) Halaman Login premium**: layout `auth.php` split screen sesuai PRD §8.2. Field **Username** (ikon user, autocomplete `username`) dan **Password** (toggle show/hide via Alpine), "Ingat saya", pesan error generik, loading state pada tombol.
- [x] **T-405 (P1) Remember me**: token di cookie `HttpOnly; SameSite=Lax`, hash disimpan di `user_tokens`, dan dirotasi saat dipakai.
- [x] **T-406 (P0) Ganti password** `/account/password`: password lama, password baru, konfirmasi.
- [x] **T-407 (P0) Audit**: login sukses/gagal/logout tercatat.
- AC fase: login `admin`/`Admin#2026` sukses; 6× gagal dalam 1 menit menghasilkan 429; user nonaktif ditolak; tidak ada string "email" di form maupun tabel; feature test `AuthTest` lulus.

## Fase 5 — Layout & UI Kit (Tailwind CDN)

- [x] **T-501 (P0) `layouts/partials/head.php`**: Tailwind v4 browser CDN, `@theme` token (PRD §8.1), Plus Jakarta Sans, Alpine, Lucide, Chart.js, dan `<meta name="csrf">`.
- [x] **T-502 (P0) `layouts/app.php`**: sidebar gelap collapsible (state di `localStorage`), menu dikelompokkan (Utama / Master Data / Kehadiran / Payroll / Laporan / Sistem) dan disaring berdasarkan peran, item aktif dengan indikator gradien, topbar sticky `backdrop-blur` (breadcrumb, pencarian, avatar + dropdown), dan drawer mobile.
- [x] **T-503 (P0) Komponen view** (`app/Views/components/`), dipanggil via helper `component('x', $data)` (scope terisolasi, data halaman tidak bocor):
  - `kpi_card` (ikon gradien, nilai, delta, sparkline opsional)
  - `badge` (draft/review/approved/paid/locked/active/resigned/…)
  - `data_table` (header sticky, empty state, slot aksi) + `pagination` (style kustom untuk Pager CI4)
  - `form_input`, `form_select`, `form_money` (format ribuan otomatis dengan Alpine)
  - `modal_confirm`, `toast` (flashdata `success|error|warning`), `empty_state`, `page_header` (judul, subjudul, tombol aksi)
- [x] **T-504 (P0) Halaman error** 403/404 bergaya sesuai tema.
- [x] **T-505 (P1) Halaman "UI Kit"** `/dev/ui-kit` (hanya environment development) untuk review visual semua komponen.
- AC fase: tampilan konsisten di 375px, 768px, dan 1440px; tidak ada error di console; skor aksesibilitas Lighthouse ≥ 90.
  - Diverifikasi dengan screenshot headless Chrome (375/768/1440, tanpa overflow horizontal, tanpa error JS) dan audit axe-core WCAG 2 A/AA: 0 pelanggaran di login, dashboard, UI Kit, dan ganti password.

## Fase 6 — Engine Payroll (inti, wajib unit test)

- [x] **T-601 (P0) `WorkdayCalendar`**: hari kerja (Senin–Jumat dikurangi holidays) untuk satu rentang, serta hari kerja aktif karyawan (memperhitungkan join/resign).
- [x] **T-602 (P0) `EarningCalculator`**: gaji pokok prorata, tunjangan tetap (prorata), tunjangan harian × hari hadir, dan penyesuaian manual.
- [x] **T-603 (P0) `OvertimeCalculator`**: upah sejam 1/173 dengan tarif 1,5× pada jam pertama dan 2× pada jam berikutnya (per hari lembur), hanya untuk jabatan eligible.
- [x] **T-604 (P0) `BpjsCalculator`**: porsi perusahaan & karyawan per program dengan batas upah Kes & JP, serta menghormati flag enrolled.
- [x] **T-605 (P0) `Pph21Calculator`**: `monthly(bruto, ptkpStatus)` dengan TER, dan `annual(employeeId, year)` untuk Desember/bulan terakhir (biaya jabatan, iuran pensiun, PTKP, Pasal 17, dikurangi PPh Jan–Nov).
- [x] **T-606 (P0) `PayrollService`**:
  - `generate(periodId)`: transaksi DB, hapus hasil lama (khusus `draft`), loop karyawan aktif di periode, panggil kalkulator, simpan `payrolls` + `payroll_items` + snapshot JSON, potong cicilan pinjaman, dan alpha/telat.
  - Mengembalikan ringkasan: jumlah karyawan, total bruto, total netto, dan daftar warning.
- [x] **T-607 (P0) `PayrollStateMachine`**: transisi valid `draft→review→approved→paid→locked` dan `review→draft` (reject + catatan). Cek peran per transisi. Saat `locked`, buat `loan_installments`, kurangi `loans.remaining`, dan set `paid_off` bila lunas.
- [x] **T-608 (P0) Unit test** (`tests/unit/Payroll/`):
  - Contoh PRD §6.5 → PPh 265.215 dan THP 9.574.785
  - Batas upah JP & BPJS Kes (gaji 45 jt)
  - TER kategori B dan C (Sinta K/1, Hendra K/3)
  - Prorata Anisa (masuk 10 Agu → 14/19 hari kerja) dan Hadi (resign 15 Sep → 11/22)
  - Lembur 3 jam = 1,5 + 2 + 2 = 5,5 × upah sejam
  - PPh Desember: total setahun = Pasal 17
  - Transisi status ilegal melempar exception
- AC fase: `php spark test` hijau dan generate 30 karyawan selesai < 2 detik.
  - Tambahan: tabel `tax_ytd_balances` (saldo awal PPh 21 Jan–Jul) agar perhitungan setahun untuk karyawan resign / Desember benar; potongan alpha & telat mengurangi bruto PPh 21; seluruh aritmetika rupiah memakai integer (tanpa float).

> Setelah fase ini selesai, kerjakan **T-311**, lalu jalankan `php spark migrate:refresh && php spark db:seed DatabaseSeeder`.

## Fase 7 — Modul Master Data

- [x] **T-701 (P0) Dashboard** admin/HR/finance sesuai PRD F1. Semua angka dari query nyata.
  - Grafik **kolom bertumpuk** komposisi biaya payroll ≤ 6 periode (gaji bersih · PPh 21 + BPJS karyawan · BPJS perusahaan) dengan legenda, tooltip, label total, dan tampilan tabel. Palet divalidasi (CVD ΔE ≥ 9,2).
  - Biaya per departemen memakai **bar horizontal berurutan satu warna**, bukan donut, agar 6 kategori mudah dibandingkan dan dilabeli langsung.
- [x] **T-702 (P0) Departemen**: list + CRUD dalam modal, kepala departemen ditampilkan dengan avatar, dan jumlah karyawan per departemen.
- [x] **T-703 (P0) Jabatan**: list dikelompokkan per departemen beserta range gaji.
- [x] **T-704 (P0) Karyawan**:
  - List: pencarian (nama/NIP), filter departemen/status/tipe, avatar inisial, badge status, NIK disamarkan, paginasi 25, ekspor CSV.
  - Form multi-section (Data Pribadi, Kepegawaian, Gaji & Pajak, Rekening Bank) dengan validasi dan auto-isi gaji/tunjangan dari jabatan.
  - Detail: header profil dengan gradien, tab (Profil · Komponen Gaji · Kehadiran · Pinjaman · Riwayat Gaji).
  - Aksi resign: set `resign_date` & status, lalu nonaktifkan user terkait.
  - Tambahan: opsi buat akun login otomatis saat menambah karyawan; karyawan tidak dihapus (riwayat payroll/pajak), cukup resign/diberhentikan; NIK/NPWP/rekening disamarkan di audit log.
- [x] **T-705 (P0) Komponen Gaji**: CRUD (komponen `is_system` tidak bisa dihapus) dan override nominal per karyawan.
- [x] **T-706 (P1) Hari Libur**: CRUD dengan tampilan kalender tahunan.
- [x] **T-707 (P0) Pengaturan**: tab Perusahaan · BPJS · Pajak (PTKP & TER, dapat diubah Super Admin) · Payroll & Kehadiran. HR & Finance hanya melihat. Setiap perubahan dicatat di audit log.

## Fase 8 — Kehadiran, Lembur, Pinjaman

- [x] **T-801 (P0) Kehadiran harian**: pilih tanggal → tabel semua karyawan aktif dengan segmented control status. Simpan massal (upsert).
  - Jam masuk melewati toleransi otomatis dicatat Terlambat (menit terlambat dihitung dari `attendance.work_start`); tanggal yang belum terjadi hanya menerima sakit/cuti/izin/libur; penanda baris belum disimpan + peringatan saat meninggalkan halaman.
- [x] **T-802 (P0) Rekap bulanan**: grid karyawan × tanggal dengan sel berwarna dan tooltip, total per status di kolom akhir, filter departemen.
  - Indikator hari kerja yang belum tercatat, jumlah hadir per hari, dan tautan tanggal ke input harian.
- [x] **T-803 (P1) Import CSV kehadiran**: unduh template, preview + validasi per baris sebelum commit.
  - Template berisi karyawan aktif per tanggal; status menerima kode/label/huruf (H/T/S/C/I/A/L); baris ganda & periode final ditolak; validasi diulang saat menyimpan.
- [x] **T-804 (P0) Lembur**: CRUD, hitung jam otomatis, approve/reject.
  - Melewati tengah malam didukung, maks. 12 jam, tidak boleh bertumpuk, hanya jabatan berhak lembur; persetujuan massal; penolakan wajib catatan (kolom `review_note`); estimasi upah lembur memakai `OvertimeCalculator`.
- [x] **T-805 (P0) Pinjaman**: pengajuan, approve (finance), jadwal cicilan, progress bar sisa pinjaman.
  - Cicilan dibulatkan ke atas agar lunas tepat di akhir tenor; maks. 50% gaji pokok; satu pinjaman berjalan per karyawan; jadwal menggabungkan cicilan terbukukan, potongan di payroll belum terkunci, dan proyeksi (`LoanSchedule`).
- [x] **T-806 (P0) Guard**: tidak boleh mengubah kehadiran/lembur pada tanggal yang termasuk periode `approved`/`paid`/`locked`.
  - `PayrollPeriodGuard` dipakai bersama kehadiran, import, lembur, dan hari libur; perubahan pada periode draft yang sudah di-generate memunculkan pengingat generate ulang.

## Fase 9 — Modul Payroll (UI)

- [x] **T-901 (P0) List periode**: kartu per periode (bulan, status badge, jumlah karyawan, total netto) dan tombol "Buat Periode" (default bulan berikutnya, tanggal bayar = hari kerja terakhir).
  - Tanggal bayar dapat diubah saat membuat periode (dalam bulan periode s.d. +15 hari); kartu menampilkan progres status & jumlah peringatan.
- [x] **T-902 (P0) Detail/Review periode**:
  - Stepper status visual (Draft → Review → Approved → Paid → Locked).
  - KPI: total bruto, total potongan, total PPh 21, total BPJS perusahaan, total THP.
  - Tabel karyawan dengan pencarian, kolom angka `tabular-nums`, baris warning disorot, dan klik baris membuka rincian.
  - Tombol aksi sesuai peran & status (Generate/Regenerate, Ajukan Review, Approve, Reject + catatan, Tandai Dibayar, Kunci) dengan modal konfirmasi.
  - Stepper menampilkan waktu & pelaku setiap tahap; catatan penolakan Finance tampil di Draft; komponen `payroll_stepper` dipakai juga di dashboard.
- [x] **T-903 (P0) Rincian gaji karyawan**: daftar `payroll_items` dikelompokkan (Pendapatan, Potongan, Kontribusi Perusahaan) dan panel "Cara hitung PPh 21" (bruto, kategori TER, tarif).
  - Keterangan dasar hitung per baris (`PayrollItemPresenter`), perhitungan setahun untuk masa pajak terakhir, ringkasan kehadiran, dan navigasi antarkaryawan.
- [x] **T-904 (P0) Penyesuaian manual**: tambah bonus/THR/koreksi per karyawan (hanya status draft), lalu regenerate otomatis.
- [x] **T-905 (P0) Ekspor transfer bank**: CSV per bank (nama, no rekening, nominal, berita "Gaji Okt 2026").
  - Data rekening dari snapshot payroll; tersedia untuk Finance sejak status Disetujui; karyawan tanpa rekening dilaporkan terpisah; setiap ekspor tercatat di audit log.

## Fase 10 — Slip Gaji & Self-Service

- [x] **T-1001 (P0) Slip web** `/payslips/{id}` dengan tampilan dokumen A4 premium (PRD §8.2) dan tombol Cetak (`print:` styles).
  - Saat dicetak hanya dokumen slip yang tampil (A4); slip periode yang belum dibayar diberi watermark DRAFT (pratinjau HR/Finance).
- [x] **T-1002 (P0) Slip PDF** via Dompdf: view `payslips/pdf.php` dengan CSS inline/`<style>` biasa (tanpa Tailwind), logo base64, ukuran A4, nama file `Slip-Gaji-EMP-0014-2026-09.pdf`.
  - Memakai font inti PDF (Helvetica): ±0,15 dtk dan ±6 KB per slip (DejaVu ±0,4 dtk / 42 KB). Logo dari setting `company.logo` (hanya berkas di folder public) atau logo bawaan SVG. Setiap unduhan tercatat di audit log.
- [x] **T-1003 (P0) Otorisasi slip**: peran `employee` hanya untuk `employee_id` miliknya dan periode `paid`/`locked`; selain itu 403.
  - ID slip yang tidak ada juga menghasilkan 403 untuk karyawan agar ID slip orang lain tidak bisa ditebak.
- [x] **T-1004 (P0) Self-service**: `/my` dashboard pribadi (THP terakhir, kehadiran bulan ini, sisa pinjaman) dan `/my/payslips` (list kartu per bulan + unduh).
  - Karyawan yang membuka `/dashboard` diarahkan ke `/my`; ada kalender kehadiran bulan ini, lembur, ringkasan tahunan (total gaji & PPh 21 untuk SPT), dan filter tahun pada daftar slip.
- [x] **T-1005 (P2) Unduh massal** slip satu periode sebagai ZIP.

## Fase 11 — Laporan

- [ ] **T-1101 (P0) Rekap gaji** per periode, dengan grouping departemen, subtotal, dan grand total. Ekspor CSV + XLSX.
- [ ] **T-1102 (P0) Laporan BPJS** per program, porsi perusahaan vs karyawan, ekspor CSV.
- [ ] **T-1103 (P0) Laporan PPh 21** bulanan (NIK, NPWP, PTKP, bruto, kategori TER, tarif, PPh), ekspor CSV.
- [ ] **T-1104 (P1) Laporan kehadiran** bulanan per karyawan.
- [ ] **T-1105 (P1) Tren biaya** 12 bulan per departemen (grafik + tabel).

## Fase 12 — User Management & Audit

- [ ] **T-1201 (P0) User Management**: list (username, nama, peran, karyawan terkait, status, login terakhir), tambah/edit, reset password (set `must_change_password`), aktif/nonaktif. Super Admin tidak bisa menonaktifkan dirinya sendiri.
- [ ] **T-1202 (P0) `AuditLogger`** dipanggil di semua aksi tulis penting.
- [ ] **T-1203 (P1) Halaman Audit Log**: filter user/aksi/tanggal dan diff before/after.

## Fase 13 — Hardening & QA

- [ ] **T-1301 (P0)** Audit keamanan: `esc()` di semua output, tidak ada mass-assignment di luar `$allowedFields`, pengecekan kepemilikan data, CSRF, dan filter `secureheaders`.
- [ ] **T-1302 (P0)** Feature test akses peran: setiap route penting × setiap peran → 200/302/403 sesuai matriks PRD §3.
- [ ] **T-1303 (P0)** Uji end-to-end manual dengan data seed:
  1. Login `sinta.maharani` → buka Oktober 2026 → regenerate → tambah bonus Andi → ajukan review.
  2. Login `budi.santoso` → approve → tandai dibayar → kunci.
  3. Login `andi.pratama` → `/my/payslips` → unduh slip Oktober (PDF) → coba buka slip orang lain (harus 403).
  4. Cek sisa pinjaman Kevin & Rizky berkurang.
- [ ] **T-1304 (P1)** Performa: seed 500 karyawan dummy (seeder terpisah `LoadTestSeeder`), generate < 10 detik, cek N+1 dengan Debug Toolbar.
- [ ] **T-1305 (P0)** Review visual semua halaman (mobile & desktop) terhadap PRD §8.
- [ ] **T-1306 (P0)** Update README: instalasi, akun default, perintah reset data (`php spark migrate:refresh && php spark db:seed DatabaseSeeder`), dan catatan verifikasi tarif (TER, batas JP, UMP, hari libur).

---

## Urutan Eksekusi & Estimasi

| Fase | Fokus | Estimasi |
|---|---|---|
| 0–2 | Setup, migrasi, model, helper | 1 hari |
| 3 | Seeder (kecuali T-311) | 1 hari |
| 4–5 | Auth + layout/UI kit | 1,5 hari |
| 6 | Engine payroll + unit test, lalu T-311 | 2 hari |
| 7–8 | Master data, kehadiran, pinjaman | 2,5 hari |
| 9–10 | Payroll UI, slip, self-service | 2 hari |
| 11–12 | Laporan, user mgmt, audit | 1,5 hari |
| 13 | Hardening & QA | 1 hari |
| **Total** | | **± 12,5 hari kerja** (1 developer) |

## Definition of Done (global)
Lihat PRD §11. Ringkasnya: migrate + seed bersih, login username-only, data nyata tampil di semua halaman, alur payroll Oktober bisa dijalankan sampai locked, slip PDF benar, test hijau, dan UI premium konsisten.
