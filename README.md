# SIPERMON · Kinerja Biller

Website rekap kinerja 25 biller ULP Kota Barat. Koordinator Admin mengunggah ekspor rekap ACMT (dan nanti AP2T). Sistem mengurai dan memvalidasi berkas, lalu setelah di-commit menghitung skor bulanan, dashboard, dan leaderboard. Biller hanya mengisi absensi hariannya sendiri; selebihnya mereka membaca skor, rincian poin, denda, dan peringkat miliknya.

Acuan: *PRD Backend SIPERMON — Kinerja Biller (Upload ACMT/AP2T)*, 17 Nov 2026.

Stack: Laravel 12, Inertia 2 + React 19 + TypeScript, Tailwind v4. Basis data: PostgreSQL (Supabase) untuk produksi; MySQL atau SQLite juga jalan untuk pengembangan.

## Struktur folder

```
biller-pln/
├── backend/    Laravel: app, routes, config, database, tests, public, resources/views
└── frontend/   React + TypeScript + Vite: src/ (Pages, Components, Layouts, hooks, lib)
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

PHP butuh ekstensi `zip` dan `gd`.

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

1. Unggah berkas (.xlsx/.xls/.csv, maks 20 MB). Berkas asli disimpan di disk `unggahan` beserta checksum SHA-256.
2. `ParseUploadJob` mengenali sheet dari judul dan header, lalu membacanya per potongan (sheet Prabayar 9.084 baris ±25 detik).
3. Validasi per baris menghasilkan error, warning, dan info. Batch dengan error berstatus **gagal**; batch bersih berstatus **siap commit**.
4. Koordinator memeriksa pratinjau, lalu commit atau batal. Commit menulis tabel final dalam satu transaksi, mengisi ulang `work_orders`, dan menghitung ulang `monthly_scores`.
5. Rollback (periode terbuka) menghapus data batch dan memulihkan batch yang digantikannya dari berkas aslinya.
6. Super Admin menutup periode. Syaratnya semua parameter wajib terisi; skor final lalu dikunci.

Modul yang sudah punya parser: Pascabayar Tunggal, Pascabayar Ganda Industri, Baca Ulang Pascabayar, dan Prabayar. Tabel untuk Koreksi AP2T, Invoice, dan PDIL sudah ada; parsernya dibuat setelah contoh berkasnya diterima.

## Absensi

Biller mengisi absensinya sendiri setiap hari di menu **Absensi**: hadir, izin, atau sakit (izin dan sakit wajib diberi keterangan). Aturannya:

- Hari kerja yang sudah lewat tanpa absensi dihitung **alpa**. Hari ini baru dihitung alpa setelah harinya berakhir.
- Skor periode terbuka dihitung ulang setiap hari pukul 00.15 WIB, dan setiap kali ada absensi masuk.
- Koordinator Admin melihat rekap semua biller di **Absensi biller**. Koordinator bisa mengisi atau mengoreksi absensi tanggal mana pun, termasuk menandai alpa. Absensi yang diisi koordinator tidak bisa ditimpa biller.
- Super Admin mengatur per periode, di **Periode & parameter**:
  - rentang tanggal yang dinilai (bawaan: bulan kalender BLTH, misalnya 202611 = 1–30 November);
  - hari kerja (bawaan Senin–Sabtu);
  - tanggal libur;
  - denda per hari alpa (bawaan 0).

```
Skor Akhir = … − Denda_Absensi,   Denda_Absensi = hari alpa × denda_alpa_per_hari
```

## Rumus skor (PRD 6.1)

```
Skor Akhir = Agregat(Σ Poin_m) + Bonus_J − Denda_AP2T − Denda_BU
Poin_m     = StanValid_m × w_stan + Eviden_m × w_eviden
```

Semua angka (bobot, bonus, denda, KDBACA normal, ambang) disimpan per periode di `scoring_rules` dan diatur Super Admin di **Periode & parameter**. `bonus_dasar_j` dan `poin_per_plg_j` belum ditetapkan, jadi bonus Hari J dihitung 0 dan periode belum bisa ditutup sampai keduanya diisi.

Kasus Baca Ulang terbukti salah memakai aturan usulan PRD 6.3: stan BU < stan awal, atau kenaikan > daya (kVA) × jam di antara dua pembacaan.

```bash
php artisan sipermon:hitung 202611     # hitung ulang manual
```

## Penyimpangan dari PRD yang perlu diketahui

- **Kunci unik `reread_cases`** memakai IDPEL + TGLBACA + TGLBACA BU, bukan IDPEL + TGLBACA. Berkas 202611 punya 3 pasang baris dengan IDPEL dan TGLBACA sama tetapi TGLBACA BU berbeda.
- **Jam di antara dua pembacaan = 0 dan stan naik** dihitung *salah lebih* (batas 0 kWh). Dengan cara ini hasilnya cocok dengan angka PRD (213 / 554 / 197 / 32).
- **Supabase Storage** belum dipasang. Berkas asli disimpan di disk lokal privat `storage/app/private/unggahan`. Untuk Supabase, tambahkan disk S3 (butuh paket `league/flysystem-aws-s3-v3`) lalu isi `SIPERMON_UPLOAD_DISK`.
- **Row Level Security** dan indeks "satu batch committed per periode + modul" hanya dibuat otomatis bila migrasi dijalankan di PostgreSQL.

## Tes

```bash
php artisan test
```

Tes memakai SQLite di memori. Tes penerimaan PRD bagian 10 (`tests/Feature/BerkasAsliTest.php`) membaca `tests/fixtures/DATA_REKAP_WBM.xlsx`. Berkas itu berisi data pelanggan, sehingga **tidak masuk git** (`.gitignore`) dan tesnya dilewati bila berkas tidak ada.
