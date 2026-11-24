<?php

use App\Enums\HasilBacaUlang;
use App\Enums\Modul;
use App\Parsers\KonteksParsing;
use App\Parsers\PembacaBerkas;
use App\Parsers\PenguraiBerkas;
use App\Services\PenilaiBacaUlang;
use Carbon\CarbonImmutable;

beforeEach(fn () => buatBillers(petugasKecil()));

function urai(callable $isi, Modul $modul, ?array $petugas = null)
{
    $berkas = berkasAcmt($isi, $petugas ?? petugasKecil());
    $pembaca = new PembacaBerkas($berkas->getRealPath(), 50);
    $pengurai = new PenguraiBerkas;
    $sheet = collect($pengurai->deteksi($pembaca))->firstWhere('modul', $modul)['sheet'];

    return $pengurai->urai($pembaca, $sheet, $modul, KonteksParsing::untuk('202611'));
}

test('sheet dikenali dari judul dan header, bukan nama sheet', function () {
    $berkas = berkasAcmt(fn ($c) => $c->tunggal()->ganda()->bacaUlang()->prabayar(2), petugasKecil());

    expect(collect((new PenguraiBerkas)->deteksi(new PembacaBerkas($berkas->getRealPath())))->pluck('modul')->all())
        ->toBe([Modul::PascabayarTunggal, Modul::PascabayarGanda, Modul::BacaUlang, Modul::Prabayar]);
});

test('label petugas dipecah menjadi login dan kode, hari baca diambil dari grup', function () {
    $h = urai(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 204, 'terbaca' => 204, 'foto' => 199, 'detik' => 367431],
        ['hari' => 'J', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 2, 'terbaca' => 2, 'foto' => 2, 'detik' => 14638],
    ]), Modul::PascabayarTunggal);

    $a = $h->baris[0]['data'];
    expect($a['petugas_login'])->toBe('18111.akmal')
        ->and($a['kode_petugas'])->toBe('AWA')
        ->and($a['kode_rbm'])->toBe('AJAAWAA')
        ->and($a['hari_baca'])->toBe('A')
        ->and($a['durasi_detik'])->toBe(367431)
        ->and($h->baris[1]['data']['durasi_detik'])->toBe(14638)
        ->and($h->ringkasan['hari_j'])->toBe(['baris' => 1, 'terbaca' => 2])
        ->and($h->adaError())->toBeFalse();
});

test('label hari baca Ganda Industri "Hari Baca - Hari Baca - A" terbaca sebagai A', function () {
    $h = urai(fn ($c) => $c->ganda(), Modul::PascabayarGanda);

    expect(collect($h->baris)->pluck('data.hari_baca')->unique()->all())->toBe(['A'])
        ->and(collect($h->baris)->pluck('data.jenis_meter')->unique()->all())->toBe(['ganda_industri']);
});

test('pencocokan login tidak peka huruf besar-kecil tetapi tanpa pencocokan mirip', function () {
    $h = urai(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.Deni', 'kode' => 'ALA', 'jml' => 5, 'terbaca' => 5, 'foto' => 5],
        ['hari' => 'A', 'login' => '18111.denni', 'kode' => 'AMA', 'jml' => 5, 'terbaca' => 5, 'foto' => 5],
    ]), Modul::PascabayarTunggal);

    $tidakDikenal = collect($h->issues)->where('kode', 'PETUGAS_TIDAK_DIKENAL');
    expect($tidakDikenal)->toHaveCount(1)
        ->and($tidakDikenal->first()['pesan'])->toContain('18111.denni');
});

test('KODE_RBM yang tidak cocok dengan petugas atau hari memberi warning RBM_TIDAK_COCOK', function () {
    $h = urai(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'rbm' => 'AJAARAA', 'jml' => 5, 'terbaca' => 5, 'foto' => 5],
    ]), Modul::PascabayarTunggal);

    expect(collect($h->issues)->pluck('kode')->all())->toContain('RBM_TIDAK_COCOK')
        ->and($h->adaError())->toBeFalse();
});

test('TERBACA melebihi JMLPLG adalah error TERBACA_LEBIH', function () {
    $h = urai(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 5, 'terbaca' => 6, 'foto' => 5],
    ]), Modul::PascabayarTunggal);

    expect(collect($h->issues)->where('kode', 'TERBACA_LEBIH'))->toHaveCount(1);
});

test('Baca Ulang: BELUM BACA disimpan sebagai info dan tidak dinilai; petugas di luar daftar tetap disimpan', function () {
    $tgl = CarbonImmutable::create(2026, 10, 26, 13, 42, 0, 'Asia/Jakarta');
    $h = urai(fn ($c) => $c->bacaUlang([
        ['idpel' => '181101389783', 'login' => '18111.joni', 'kode' => 'ARA', 'daya' => 1300, 'tgl' => $tgl, 'stan' => 1000, 'tgl_bu' => $tgl->addHours(12), 'stan_bu' => 1010],
        ['idpel' => '181101389784', 'login' => '18111.joni', 'kode' => 'ARA', 'daya' => 1300, 'tgl' => $tgl, 'stan' => 1000, 'ket' => 'BELUM BACA'],
        ['idpel' => '181101389785', 'login' => '18111.ilmenhadi', 'kode' => 'ATA', 'daya' => 1300, 'tgl' => $tgl, 'stan' => 1000, 'tgl_bu' => $tgl->addHours(1), 'stan_bu' => 900],
    ]), Modul::BacaUlang);

    expect(collect($h->baris)->pluck('data.hasil')->all())->toBe(['wajar', 'tidak_dinilai', 'salah_turun'])
        ->and($h->baris[0]['data']['batas_kwh'])->toBe(15.6)
        ->and($h->baris[0]['data']['idpel'])->toBe('181101389783')
        ->and(collect($h->issues)->pluck('kode')->sort()->values()->all())->toBe(['BU_BELUM_BACA', 'PETUGAS_TIDAK_DIKENAL'])
        ->and($h->adaError())->toBeFalse();
});

test('aturan Baca Ulang 6.3: stan turun, kenaikan melebihi daya × jam, atau wajar', function (float $stanBu, int $jam, HasilBacaUlang $hasil) {
    $t = CarbonImmutable::create(2026, 10, 26, 8, 0, 0, 'Asia/Jakarta');

    expect(PenilaiBacaUlang::nilai('SUDAH BACA', 1000, $stanBu, $t, $t->addHours($jam), 1300)['hasil'])->toBe($hasil);
})->with([
    'wajar (≤ 15,6 kWh dalam 12 jam)' => [1015.6, 12, HasilBacaUlang::Wajar],
    'kenaikan melebihi batas' => [1016, 12, HasilBacaUlang::SalahLebih],
    'stan BU lebih kecil' => [999, 12, HasilBacaUlang::SalahTurun],
    'dibaca di jam yang sama, stan naik' => [1001, 0, HasilBacaUlang::SalahLebih],
]);

test('Prabayar: koordinat dinormalisasi dan di luar kotak Pekanbaru diberi warning', function () {
    $h = urai(fn ($c) => $c->prabayar(10), Modul::Prabayar);

    $pertama = $h->baris[0]['data'];
    expect($pertama['latitude'])->toBe(0.5159556)
        ->and($pertama['longitude'])->toBe(101.444303)
        ->and($pertama['koordinat_valid'])->toBeTrue()
        ->and($pertama['raw'])->toHaveKey('NAMA')
        ->and(collect($h->issues)->where('kode', 'KOORDINAT_LUAR_WILAYAH'))->toHaveCount(3)
        ->and($h->ringkasan['baris'])->toBe(30);
});
