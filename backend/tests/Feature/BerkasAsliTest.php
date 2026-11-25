<?php

use App\Enums\Modul;
use App\Parsers\KonteksParsing;
use App\Parsers\PembacaBerkas;
use App\Parsers\PenguraiBerkas;
use Database\Seeders\DatabaseSeeder;

/*
 * Kriteria terima PRD bagian 10 terhadap berkas asli DATA_REKAP_WBM.xlsx (BLTH 202611).
 * Berkas memuat data pelanggan sehingga tidak masuk git; letakkan di tests/fixtures/ untuk menjalankan tes ini.
 */
const BERKAS_ASLI = __DIR__.'/../fixtures/DATA_REKAP_WBM.xlsx';

beforeEach(function () {
    if (! is_file(BERKAS_ASLI)) {
        $this->markTestSkipped('tests/fixtures/DATA_REKAP_WBM.xlsx tidak ada.');
    }
    buatBillers(DatabaseSeeder::PETUGAS);
});

function uraiAsli(Modul $modul)
{
    static $cache = [];

    return $cache[$modul->value] ??= (function () use ($modul) {
        $pembaca = new PembacaBerkas(BERKAS_ASLI, 3000);
        $pengurai = new PenguraiBerkas;
        $sheet = collect($pengurai->deteksi($pembaca))->firstWhere('modul', $modul)['sheet'];

        return $pengurai->urai($pembaca, $sheet, $modul, KonteksParsing::untuk('202611'));
    })();
}

test('PASCA TUNGGAL: 188 baris, 25 login, 8 hari baca, jumlah sama dengan GRAND TOTAL', function () {
    $h = uraiAsli(Modul::PascabayarTunggal);

    expect($h->ringkasan['baris'])->toBe(188)
        ->and($h->ringkasan['login_unik'])->toBe(25)
        ->and($h->ringkasan['hari_baca'])->toBe(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J'])
        ->and($h->ringkasan['jumlah'])->toBe(['jml_plg' => 40323, 'terbaca' => 40323, 'foto_berhasil' => 40131])
        ->and($h->ringkasan['hari_j'])->toBe(['baris' => 13, 'terbaca' => 26])
        ->and($h->issues)->toBe([]);

    $akmal = collect($h->baris)->pluck('data')->where('petugas_login', '18111.akmal')->keyBy('hari_baca');
    expect($akmal['A']['durasi_detik'])->toBe(367431)
        ->and($akmal['J']['durasi_detik'])->toBe(14638);
});

test('PASCA GANDAINDUSTRI: 7 baris, 5 login, Σ TERBACA = 7; KODE_RBM cocok di semua baris pascabayar', function () {
    $h = uraiAsli(Modul::PascabayarGanda);

    expect($h->ringkasan['baris'])->toBe(7)
        ->and($h->ringkasan['login_unik'])->toBe(5)
        ->and($h->ringkasan['jumlah']['terbaca'])->toBe(7)
        ->and(collect($h->issues)->where('kode', 'RBM_TIDAK_COCOK'))->toHaveCount(0)
        ->and(collect(uraiAsli(Modul::PascabayarTunggal)->issues)->where('kode', 'RBM_TIDAK_COCOK'))->toHaveCount(0);
});

test('BACA ULANG PASCA: 996 baris, 934 IDPEL unik, dan hasil penilaian aturan 6.2', function () {
    $h = uraiAsli(Modul::BacaUlang);

    expect($h->ringkasan['baris'])->toBe(996)
        ->and($h->ringkasan['idpel_unik'])->toBe(934)
        ->and($h->ringkasan['sudah_baca'])->toBe(970)
        ->and($h->ringkasan['belum_baca'])->toBe(26)
        ->and($h->ringkasan['hasil'])->toBe(['salah_turun' => 213, 'salah_lebih' => 554, 'sama' => 104, 'wajar' => 93, 'tidak_dinilai' => 32])
        ->and(collect($h->issues)->where('kode', 'PETUGAS_TIDAK_DIKENAL')->count())->toBe(48)
        ->and(collect($h->issues)->where('kode', 'PETUGAS_TIDAK_DIKENAL')->every(fn ($i) => str_contains($i['pesan'], '18111.ilmenhadi')))->toBeTrue()
        ->and($h->adaError())->toBeFalse()
        ->and($h->baris[0]['data']['idpel'])->toBe('181101389783');
});

test('PRABAYAR: 9.084 baris, 9.084 IDPEL unik, 25 login cocok, koordinat dinormalisasi', function () {
    $h = uraiAsli(Modul::Prabayar);
    $data = collect($h->baris)->pluck('data');

    expect($h->ringkasan['baris'])->toBe(9084)
        ->and($h->ringkasan['idpel_unik'])->toBe(9084)
        ->and($h->ringkasan['login_unik'])->toBe(25)
        ->and(collect($h->issues)->where('kode', 'PETUGAS_TIDAK_DIKENAL'))->toHaveCount(0)
        ->and($h->ringkasan['akurasi_kosong'])->toBe(8954)
        ->and($data[0]['latitude'])->toBe(0.5159556)
        ->and($data[0]['longitude'])->toBe(101.444303)
        ->and($data->firstWhere('idpel', '181110831348')['longitude'])->toBe(101.4214288);
})->group('lambat');
