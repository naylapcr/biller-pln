<?php

/*
 * Pengaturan SIPERMON (PRD Backend, bagian 3, 6, dan 9).
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

    // Disk tempat berkas asli disimpan selama 2 tahun. Untuk Supabase Storage, isi disk S3
    // (endpoint Supabase) di config/filesystems.php lalu ganti nilai ini.
    'disk_unggahan' => env('SIPERMON_UPLOAD_DISK', 'unggahan'),

    // Ukuran berkas maksimal (KB) dan jumlah baris per potongan saat membaca berkas.
    'maks_berkas_kb' => 20 * 1024,
    'baris_per_potongan' => 3000,

    // Staging dihapus setelah commit atau setelah berumur sekian hari.
    'umur_staging_hari' => 30,

    /*
     * Parameter skor bawaan (PRD 6.2). Disalin ke scoring_rules saat periode pertama dibuat;
     * periode berikutnya menyalin dari periode sebelumnya. null = belum ditetapkan.
     */
    'parameter' => [
        'w_stan' => 4.0,
        'w_eviden' => 1.5,
        'mode_agregasi' => 'jumlah', // jumlah | rata_rata_modul
        'bonus_min_j' => 50,
        'bonus_max_j' => 150,
        'bonus_dasar_j' => null,
        'poin_per_plg_j' => null,
        'denda_bu_per_kasus' => 10,
        // Denda AP2T per kasus menurut tingkat, contoh {"ringan": 15, "berat": 50}. Batas tiap tingkat belum ditetapkan.
        'denda_ap2t_tingkat' => ['min' => 15, 'max' => 50, 'tingkat' => (object) []],
        'kdbaca_normal' => ['Z'],
        // Absensi diisi biller sendiri. Hari kerja tanpa absensi = alpa.
        'denda_alpa_per_hari' => 0,
        'absensi_hari_kerja' => [1, 2, 3, 4, 5, 6], // ISO: 1 = Senin … 7 = Minggu
        'absensi_hari_libur' => [],                  // tanggal libur, contoh ["2026-11-10"]
        'ambang_evaluasi' => 1800,
        'jumlah_top' => 5,
        'jumlah_bottom' => 5,
    ],

    // Parameter yang wajib terisi sebelum periode bisa ditutup.
    'parameter_wajib' => [
        'w_stan', 'w_eviden', 'mode_agregasi', 'bonus_min_j', 'bonus_max_j', 'bonus_dasar_j',
        'poin_per_plg_j', 'denda_bu_per_kasus', 'kdbaca_normal', 'ambang_evaluasi', 'jumlah_top', 'jumlah_bottom',
    ],
];
