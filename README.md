# SIPERMON · Kinerja Biller

Website rekap kinerja 25 biller ULP Kota Barat. Koordinator Admin mengunggah ekspor rekap ACMT (dan nanti AP2T). Sistem mengurai dan memvalidasi berkas, lalu setelah di-commit menghitung skor bulanan, dashboard, dan leaderboard. Biller dan Koordinator absen harian sendiri; selebihnya Biller membaca skor, rincian, histori WO, dan peringkat miliknya.

Acuan: *PRD Backend SIPERMON — Kinerja Biller (Upload ACMT/AP2T)*, 17 Nov 2026.

Stack: Laravel 12, Inertia 2 + React 19 + TypeScript, Tailwind v4. Basis data dan berkas unggahan: Supabase (PostgreSQL + Storage). Tes memakai SQLite.

## Struktur folder

```
biller-pln/
├── backend/    Laravel: app, routes, config, database, tests, public, resources/views
└── frontend/   React + TypeScript + Vite: src/ (assets, components, layouts, pages, hooks, lib, types)
```

Frontend tetap dirender lewat Inertia. Vite berjalan dari `frontend/`, membaca `backend/.env`, dan menulis hasil build ke `backend/public/build` (mode dev: `backend/public/hot`).

## Menjalankan di lokal

```bash
# frontend
cd frontend
npm install
npm run build               # atau `npm run dev`

# backend
cd ../backend
composer install
cp .env.example .env        # sesuaikan DB_*
php artisan key:generate
php artisan migrate:fresh --seed
composer dev                # server + queue worker + vite (frontend)
```

Parsing berkas berjalan di antrean, jadi **queue worker harus hidup**. `composer dev` sudah menjalankannya. Kalau memakai `php artisan serve` saja, jalankan juga `php artisan queue:work` di terminal lain. Tanpa worker, batch akan tertahan di status "Sedang diproses".

PHP butuh ekstensi `zip`, `gd`, serta `pdo_pgsql` dan `pgsql` untuk Supabase (hapus tanda `;` di `php.ini` Laragon).

`.env` yang dibaca Laravel ada di `backend/.env`. Paket Supabase Storage (`league/flysystem-aws-s3-v3`) sudah ada di `composer.json`. Tanpa Supabase, pakai `DB_CONNECTION=sqlite`, `SIPERMON_UPLOAD_DISK=unggahan`, dan `FILESYSTEM_DISK=local`.

Scheduler (`php artisan schedule:work`) harus hidup agar `MarkAbsentJob` mencatat alpa pukul 23.59 WIB.

## Menyambungkan ke Supabase

1. **Buat proyek** di supabase.com, region **Southeast Asia (Singapore)**. Simpan kata sandi database di pengelola kata sandi.
2. **Database:** klik **Connect** → **Session pooler**. Salin host, port `5432`, database `postgres`, dan user `postgres.<project-ref>` ke `DB_*` di `backend/.env`. Jangan pakai port `6543` (transaction pooler): Laravel memakai prepared statement.
3. **Storage:** menu **Storage** → **New bucket** bernama `acmt-uploads`, **Public bucket mati**.
4. **Kunci S3:** **Project Settings → Storage → S3 Connection**. Salin endpoint ke `AWS_ENDPOINT`, region ke `AWS_DEFAULT_REGION`, lalu buat access key dan isi `AWS_ACCESS_KEY_ID` serta `AWS_SECRET_ACCESS_KEY`. Kunci ini melewati RLS Storage, jadi hanya disimpan di server.
5. Di `backend/.env` set `DB_CONNECTION=pgsql`, `SIPERMON_UPLOAD_DISK=s3`, dan `AWS_USE_PATH_STYLE_ENDPOINT=true`.
6. Jalankan dari folder `backend`:

```bash
php artisan config:clear
php artisan sipermon:cek-supabase --tanpa-storage   # koneksi, SSL, zona waktu
php artisan migrate --force                          # membuat tabel + RLS
SIPERMON_DEMO=false php artisan db:seed --force      # akun awal + 25 biller, tanpa data demo
php artisan sipermon:cek-supabase                    # ulangi: tabel, RLS, dan uji tulis/baca/hapus Storage
```

Migrasi mengaktifkan **Row Level Security tanpa kebijakan** di semua tabel, sehingga kunci publik Supabase (`anon`) tidak bisa membaca data lewat Data API. Aplikasi tetap bisa karena tersambung langsung sebagai pemilik tabel. Seeder membuat akun dengan kata sandi `password`: segera ganti lewat Profil Saya atau Manajemen Akun.

## Akun demo (kata sandi: `password`)

| Peran | Username |
|---|---|
| Super Admin | `superadmin` |
| Koordinator Admin | `koordinator` |
| Biller | login ACMT, contoh `18111.akmal`, `18111.joni` |

Ke-25 biller diambil dari sheet PASCA TUNGGAL (login ACMT dan kode petugas). Nama sementara dibentuk dari login karena nama dan NIP asli belum diterima (pertanyaan terbuka no. 8). Ubah lewat menu **Master biller**.

Seeder juga membuka periode **202611** dan mengunggah serta meng-commit berkas contoh **sintetis** lewat jalur unggah biasa. Isi `SIPERMON_DEMO=false` bila ingin database kosong.

## Memakai berkas ACMT asli

Lewat menu **Unggah data**, atau dari terminal:

```bash
php artisan sipermon:unggah "DATA REKAP WBM.xlsx" 202611 --commit
```

Satu berkas dengan beberapa sheet menghasilkan satu batch per modul. Commit untuk periode dan modul yang sama **menggantikan** batch lama, misalnya data demo; batch lama bisa dipulihkan lewat rollback selama periode terbuka.

## Alur

1. Koordinator mengunggah berkas (.xlsx/.csv, maks 25 MB). Berkas asli disimpan privat beserta checksum SHA-256.
2. `ParseUploadJob` mengenali sheet dari judul dan header, lalu membacanya per potongan ke staging.
3. Validasi menghasilkan error, warning, dan info. Batch dengan error ditolak; batch bersih menunggu commit.
4. Commit menulis tabel final dalam satu transaksi, mengganti batch lama modul yang sama, dan menghitung ulang skor.
5. Rollback (periode terbuka) menghapus data batch dan memulihkan batch yang digantikannya.
6. Super Admin menutup periode dengan mengetik kode periode; skor final dikunci.

Unggah, commit, dan rollback khusus Koordinator. Super Admin mendapat 403 di `/biller`, `/unggah`, `/pemetaan`, `/absensi`, dan `/laporan`.

## Absensi

Biller dan Koordinator absen sekali sehari (hadir, izin, sakit); absen kedua ditolak 409. Absensi **tidak memengaruhi skor**. Koordinator hanya memantau; koreksi dilakukan Super Admin dengan alasan wajib. Hari kerja (Senin–Sabtu) diatur di `config/sipermon.php` sambil menunggu pertanyaan terbuka no. 8.

## Rumus skor (PRD 6.1)

```
Skor Akhir  = rata-rata nilai modul yang punya data + Bonus_J
Nilai_pasca = (0,4 R + 0,3 E + 0,3 K) x (1 - rasio Baca Ulang) x (1 - rasio AP2T)
Nilai_pra   = (R + E) / 2
Bonus_J     = 5,0 x TERBACA_J / JMLPLG_J
```

Semua parameter disimpan per periode dan diatur Super Admin di **Periode & Parameter**. Kasus Baca Ulang dibebankan ke pemilik KODE RBM; kasus sisipan tidak memotong skor.

```bash
php artisan sipermon:hitung 202611     # hitung ulang manual
php artisan sipermon:alpa 2026-11-24   # catat alpa satu tanggal secara manual
```

## Penyimpangan dari PRD yang perlu diketahui

- **Kunci unik `reread_cases`** memakai IDPEL + TGLBACA + TGLBACA BU. Berkas 202611 punya 3 pasang baris dengan IDPEL dan TGLBACA sama tetapi TGLBACA BU berbeda.
- **Jendela sisipan** memakai jam mulai–selesai per KODE RBM dengan toleransi 5 menit (`toleransi_sisipan_menit`). Angka 5 menit tidak ada di PRD; dipilih karena menghasilkan 19 sisipan dan 750 kasus sesuai kriteria terima.
- **Endpoint** berupa aksi controller Inertia, bukan JSON `/api` (diizinkan PRD 8).
- **Row Level Security** dan indeks `lower(login_acmt)` hanya dibuat bila migrasi dijalankan di PostgreSQL.

## Tes

```bash
php artisan test
```

Tes memakai SQLite di memori. Tes penerimaan PRD bagian 10 (`tests/Feature/BerkasAsliTest.php`) membaca `tests/fixtures/DATA_REKAP_WBM.xlsx`. Berkas itu berisi data pelanggan, sehingga **tidak masuk git** (`.gitignore`) dan tesnya dilewati bila berkas tidak ada.
