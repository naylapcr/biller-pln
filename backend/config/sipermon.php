<?php

/*
 * Pengaturan SIPERMON (PRD Backend, bagian 3, 4, 6, dan 9).
 */
return [
    // Kode UNITUP yang diterima (ULP Kota Barat). Berkas dengan UNITUP lain ditolak UNIT_MISMATCH.
    'unitup' => env('SIPERMON_UNITUP', '18111'),

    // Kotak wilayah Pekanbaru untuk memeriksa koordinat prabayar (KOORDINAT_LUAR_WILAYAH).
    'wilayah' => [
        'lintang_min' => 0.3,
        'lintang_max' => 0.7,
        'bujur_min' => 101.2,
        'bujur_max' => 101.6,
    ],

    // Disk berkas asli. Produksi: 's3' (bucket privat acmt-uploads di Supabase Storage).
    // Pengembangan tanpa Supabase: 'unggahan' (folder lokal storage/app/private/unggahan).
    'disk_unggahan' => env('SIPERMON_UPLOAD_DISK', 'unggahan'),

    // Unduhan berkas asli lewat URL bertanda tangan berumur sekian menit.
    'umur_url_unduhan_menit' => 5,

    // Ukuran berkas maksimal (KB) sesuai desain Portal Unggah, dan baris per potongan saat membaca berkas.
    'maks_berkas_kb' => 25 * 1024,
    'baris_per_potongan' => 3000,

    // Staging dihapus setelah commit atau setelah berumur sekian hari.
    'umur_staging_hari' => 30,

    /*
     * Absensi mandiri (PRD 4). Memengaruhi skor lewat parameter bobot_kehadiran. MarkAbsentJob mencatat
     * alpa pukul 23.59 WIB pada hari kerja untuk Biller dan Koordinator aktif yang belum absen. Daftar hari
     * kerja masih menunggu jawaban pertanyaan terbuka no. 8; sementara Senin–Sabtu.
     */
    'absensi' => [
        'hari_kerja' => [1, 2, 3, 4, 5, 6], // ISO: 1 = Senin … 7 = Minggu
        'hari_libur' => [],                  // tanggal libur, contoh ["2026-12-25"]
        'foto_maks_kb' => 5 * 1024,          // lampiran izin/sakit wajib (jpg, png, webp, pdf)
        'akurasi_maks_m' => 150,             // GPS lebih kabur dari ini ditolak saat absen masuk/pulang
        'foto_profil_maks_kb' => 1024,       // foto profil sudah diperkecil di browser (±256 px)
    ],

    /*
     * Toleransi (menit) jendela hari baca RBM saat menandai kasus Baca Ulang sebagai sisipan. PRD 6.2 tidak
     * menyebut angkanya; 5 menit diturunkan dari data BLTH 202611 agar hasilnya sama dengan kriteria terima
     * (19 sisipan, 17 di antaranya salah, 750 kasus masuk rasio).
     */
    'toleransi_sisipan_menit' => 5,

    /*
     * Parameter skor bawaan (PRD 6.3). Disalin ke scoring_rules saat periode pertama dibuat;
     * periode berikutnya menyalin dari periode sebelumnya. null = belum ditetapkan.
     */
    'parameter' => [
        // Bobot nilai pascabayar (persen, total 100).
        'w_realisasi' => 40,
        'w_eviden' => 30,
        'w_kecepatan' => 30,
        // Standar 200 pelanggan per 6 jam kerja → 33,3 pelanggan per jam.
        'jam_kerja_per_hari' => 6,
        'sla_plg_per_jam' => 33.3333,
        // Toleransi pembulatan saat menilai Baca Ulang (kWh).
        'toleransi_kwh' => 1,
        'kdbaca_normal' => ['Z'],
        // Jadwal baca prabayar: tanggal di bulan sebelum BLTH (BLTH 202611 → 5–20 Oktober 2026).
        'prabayar_mulai' => 5,
        'prabayar_selesai' => 20,
        // Bonus Hari J (poin) = TERBACA_J ÷ total JMLPLG biller × 100, paling besar bobot_bonus_j.
        // Satu pelanggan Hari J bernilai sama dengan satu pelanggan biasa, jadi tidak ada untung memindahkan bacaan.
        'bobot_bonus_j' => 2.0,
        // Kehadiran (persen dari skor akhir). Hari izin/sakit tanpa lampiran (mis. hasil koreksi) bernilai
        // nilai_izin_tanpa_foto; hadir yang terlambat, pulang cepat, atau tidak absen pulang bernilai nilai_terlambat.
        'bobot_kehadiran' => 10,
        'nilai_izin_tanpa_foto' => 50,
        'nilai_terlambat' => 75,
        // Jam kerja kantor (WIB): masuk tanpa toleransi, pulang setelah 8 jam kerja.
        'jam_masuk' => '07:30',
        'jam_pulang' => '16:30',
        // Lokasi kantor ULP untuk absen masuk/pulang. Diisi Super Admin di Periode & Parameter.
        'kantor_lat' => null,
        'kantor_lng' => null,
        'radius_absen_m' => 100,
        // Ambang kategori pada skala 0–100.
        'ambang_grade_a' => 85.0,
        'ambang_kritis' => 65.0,
        'jumlah_top' => 5,
        'jumlah_bottom' => 5,
    ],

    // Parameter yang wajib terisi sebelum periode bisa ditutup.
    'parameter_wajib' => [
        'w_realisasi', 'w_eviden', 'w_kecepatan', 'jam_kerja_per_hari', 'sla_plg_per_jam', 'kdbaca_normal',
        'prabayar_mulai', 'prabayar_selesai', 'bobot_bonus_j', 'bobot_kehadiran', 'nilai_izin_tanpa_foto',
        'nilai_terlambat', 'jam_masuk', 'jam_pulang', 'ambang_grade_a', 'ambang_kritis',
    ],
];
