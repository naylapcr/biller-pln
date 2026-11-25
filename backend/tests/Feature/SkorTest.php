<?php

use App\Models\Biller;
use App\Models\MonthlyScore;
use App\Models\RereadCase;
use App\Services\LayananPeriode;
use App\Services\ParameterSkor;
use App\Services\PengelolaBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('unggahan');
    buatBillers(petugasKecil());
    $this->periode = bukaPeriode();
});

function commitBerkas(callable $isi): void
{
    $pengelola = app(PengelolaBatch::class);
    $batch = $pengelola->unggah(berkasAcmt($isi, petugasKecil()), bukaPeriode(), null, superAdmin());
    foreach ($batch->saudara()->get() as $b) {
        $pengelola->commit($b, superAdmin());
    }
}

function skor(string $login): MonthlyScore
{
    return MonthlyScore::where('biller_id', Biller::where('login_acmt', strtolower($login))->value('id'))->firstOrFail();
}

function jam(int $hari, int $jam, int $menit = 0): CarbonImmutable
{
    return CarbonImmutable::create(2026, $hari >= 23 ? 10 : 11, $hari, $jam, $menit, 0, 'Asia/Jakarta');
}

/**
 * Akmal: Hari A 98/100 terbaca, 88 foto (26 Okt, satu hari); Hari J 2/2 (1 Nov) → 2 hari aktif.
 *        1 kasus Baca Ulang salah_turun di RBM-nya sendiri.
 * Joni : Hari A 80/80, 80 foto (26 Okt, satu hari); Baca Ulang naik dalam batas (wajar).
 */
function dataSkor(): callable
{
    return fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 100, 'terbaca' => 98, 'foto' => 88, 'mulai' => jam(26, 8), 'detik' => 8 * 3600],
        ['hari' => 'J', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 2, 'terbaca' => 2, 'foto' => 2, 'mulai' => jam(1, 8, 35), 'detik' => 3600],
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 80, 'terbaca' => 80, 'foto' => 80, 'mulai' => jam(26, 8), 'detik' => 8 * 3600],
    ])->bacaUlang([
        ['idpel' => '181100000001', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'daya' => 1300, 'tgl' => jam(26, 10), 'stan' => 500, 'tgl_bu' => jam(26, 12), 'stan_bu' => 400],
        ['idpel' => '181100000002', 'login' => '18111.joni', 'kode' => 'ARA', 'daya' => 1300, 'tgl' => jam(26, 10), 'stan' => 500, 'tgl_bu' => jam(26, 12), 'stan_bu' => 501],
    ]);
}

test('skor dihitung sesuai rumus PRD 6.1 dengan parameter bawaan', function () {
    commitBerkas(dataSkor());

    // Akmal: R = 100/102, E = 90/100, K = (100 ÷ (2 hari × 6 jam)) ÷ 33,3 = 25.
    // Dasar = 0,4×98,04 + 0,3×90 + 0,3×25 = 73,72; × (1 − 1/100) = 72,98; + bonus J 5 × 2/2 = 77,98.
    $akmal = skor('18111.akmal');
    $pasca = $akmal->nilai_per_modul['pascabayar'];
    expect($pasca['realisasi'])->toEqualWithDelta(98.04, 0.01)
        ->and($pasca['eviden'])->toEqualWithDelta(90.0, 0.01)
        ->and($pasca['kecepatan'])->toMatchArray(['terbaca' => 100, 'hari_aktif' => 2])
        ->and($akmal->nilai_kecepatan)->toEqualWithDelta(25.0, 0.01)
        ->and($akmal->nilai_pasca_dasar)->toEqualWithDelta(73.72, 0.01)
        ->and($akmal->rasio_bu)->toEqualWithDelta(0.01, 0.0001)
        ->and($pasca['nilai'])->toEqualWithDelta(72.98, 0.01)
        ->and($akmal->bonus_hari_j)->toBe(5.0)
        ->and($akmal->final_score)->toEqualWithDelta(77.98, 0.01)
        ->and($akmal->kategori)->toBe('Perlu Evaluasi')
        // Joni: 0,4×100 + 0,3×100 + 0,3×40 = 82, tanpa kasus Baca Ulang dan tanpa Hari J.
        ->and(skor('18111.joni')->final_score)->toBe(82.0)
        ->and(skor('18111.joni')->peringkat)->toBe(1)
        ->and($akmal->peringkat)->toBe(2)
        ->and($akmal->rules_snapshot['w_realisasi'])->toBe(40);
});

test('mengubah w_kecepatan atau bobot_bonus_j langsung mengubah final_score periode terbuka', function () {
    commitBerkas(dataSkor());

    app(ParameterSkor::class)->ubah($this->periode, ['bobot_bonus_j' => 10], superAdmin());
    expect(skor('18111.akmal')->final_score)->toEqualWithDelta(82.98, 0.01)
        ->and(skor('18111.joni')->final_score)->toBe(82.0);

    app(ParameterSkor::class)->ubah($this->periode, ['w_eviden' => 20, 'w_kecepatan' => 40], superAdmin());
    expect(skor('18111.joni')->final_score)->toBe(76.0); // 40 + 20 + 0,4×40
});

test('kasus Baca Ulang dibebankan ke pemilik KODE RBM, bukan ke kolom PETUGAS', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
    ])->bacaUlang([
        // Dibaca ulang oleh joni di RBM milik Akmal (AJAAWAA).
        ['idpel' => '181100000003', 'login' => '18111.joni', 'kode' => 'AWA', 'daya' => 1300, 'tgl' => jam(26, 8, 30), 'stan' => 500, 'tgl_bu' => jam(26, 12), 'stan_bu' => 400],
    ]));

    expect(RereadCase::first()->biller_id)->toBe(Biller::where('login_acmt', '18111.akmal')->value('id'))
        ->and(skor('18111.akmal')->rasio_bu)->toEqualWithDelta(0.01, 0.0001)
        ->and(skor('18111.joni')->rasio_bu)->toBe(0.0);
});

test('kasus sisipan dan stan sama tidak memotong skor', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
    ])->bacaUlang([
        // Dibaca 30 Okt, di luar jendela RBM hari A (26 Okt) → sisipan.
        ['idpel' => '181100000004', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'daya' => 1300, 'tgl' => jam(30, 8), 'stan' => 500, 'tgl_bu' => jam(30, 9), 'stan_bu' => 400],
        ['idpel' => '181100000005', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'daya' => 1300, 'tgl' => jam(26, 9), 'stan' => 700, 'tgl_bu' => jam(26, 10), 'stan_bu' => 700],
    ]));

    expect(RereadCase::where('idpel', '181100000004')->first())->sisipan->toBeTrue()->hasil->value->toBe('salah_turun')
        ->and(RereadCase::where('idpel', '181100000005')->first()->hasil->value)->toBe('sama')
        ->and(skor('18111.akmal')->rasio_bu)->toBe(0.0)
        ->and(skor('18111.akmal')->nilai_per_modul['pascabayar']['baca_ulang']['sisipan_dikecualikan'])->toBe(1);
});

test('biller yang hanya punya data pascabayar dibagi 1 modul; dengan prabayar dibagi 2', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
    ])->prabayar(4, ['Z']));

    $s = skor('18111.akmal');
    $pasca = $s->nilai_per_modul['pascabayar']['nilai'];
    $pra = $s->nilai_per_modul['prabayar']['nilai'];
    expect($s->nilai_per_modul)->toHaveCount(2)
        ->and($s->rata_rata_modul)->toEqualWithDelta(($pasca + $pra) / 2, 0.011)
        ->and($s->final_score)->toEqualWithDelta($s->rata_rata_modul, 0.011);
});

test('seri diputus oleh rasio Baca Ulang lebih kecil, lalu terbaca lebih banyak', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 200, 'terbaca' => 200, 'foto' => 200, 'mulai' => jam(26, 8), 'detik' => 3600],
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 200, 'terbaca' => 200, 'foto' => 200, 'mulai' => jam(26, 8), 'detik' => 3600],
        ['hari' => 'A', 'login' => '18111.deni', 'kode' => 'ALA', 'jml' => 240, 'terbaca' => 240, 'foto' => 240, 'mulai' => jam(26, 8), 'detik' => 3600],
    ]));

    expect(MonthlyScore::pluck('final_score')->unique()->all())->toBe([100.0])
        ->and(MonthlyScore::orderBy('peringkat')->with('biller')->get()->pluck('biller.login_acmt')->first())->toBe('18111.deni');
});

test('kategori mengikuti ambang Grade A dan Kritis', function () {
    $aturan = ParameterSkor::bawaan();

    expect(App\Services\PenghitungSkor::kategori(104.6, $aturan))->toBe('Grade A')
        ->and(App\Services\PenghitungSkor::kategori(85.0, $aturan))->toBe('Grade A')
        ->and(App\Services\PenghitungSkor::kategori(84.9, $aturan))->toBe('Perlu Evaluasi')
        ->and(App\Services\PenghitungSkor::kategori(64.9, $aturan))->toBe('Kritis');
});

test('tutup periode wajib mengetik kode periode; setelah ditutup skor final dan parameter terkunci', function () {
    commitBerkas(dataSkor());
    $layanan = app(LayananPeriode::class);

    expect(fn () => $layanan->tutup($this->periode, '202612', superAdmin()))->toThrow(ValidationException::class);

    $layanan->tutup($this->periode->fresh(), '202611', superAdmin());

    expect($this->periode->fresh()->terbuka())->toBeFalse()
        ->and(MonthlyScore::where('is_final', true)->count())->toBe(2)
        ->and(fn () => app(ParameterSkor::class)->ubah($this->periode->fresh(), ['w_realisasi' => 50], superAdmin()))->toThrow(ValidationException::class);
});

test('tutup periode tanpa konfirmasi_kode yang cocok ditolak 422', function () {
    commitBerkas(dataSkor());

    $this->actingAs(superAdmin())->postJson('/pengaturan/periode/202611/close', ['konfirmasi_kode' => '2026'])
        ->assertStatus(422)->assertJsonValidationErrors('konfirmasi_kode');
    expect($this->periode->fresh()->terbuka())->toBeTrue();
});

test('periode baru menyalin parameter dari periode sebelumnya', function () {
    app(ParameterSkor::class)->ubah($this->periode, ['bobot_bonus_j' => 4, 'ambang_grade_a' => 90], superAdmin());

    $baru = bukaPeriode('202612');

    expect(app(ParameterSkor::class)->untuk($baru))->toMatchArray(['bobot_bonus_j' => 4, 'ambang_grade_a' => 90]);
});

test('alias untuk petugas yang belum terpetakan mengisi biller_id dan memicu hitung ulang', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
    ])->bacaUlang([
        // Kode petugas ATA belum terdaftar dan login ilmenhadi belum terpetakan.
        ['idpel' => '181100000009', 'login' => '18111.ilmenhadi', 'kode' => 'ATA', 'daya' => 1300, 'tgl' => jam(26, 9), 'stan' => 500, 'tgl_bu' => jam(26, 10), 'stan_bu' => 400],
    ]));
    expect(skor('18111.joni')->rasio_bu)->toBe(0.0);

    $this->actingAs(superAdmin())->post('/pengaturan/pemetaan', [
        'login_acmt' => '18111.ilmenhadi',
        'biller_id' => Biller::where('login_acmt', '18111.joni')->value('id'),
    ])->assertSessionHas('success');

    expect(skor('18111.joni')->rasio_bu)->toEqualWithDelta(0.01, 0.0001)
        ->and(DB::table('reread_cases')->whereNull('biller_id')->count())->toBe(0);
});

test('alias petugas verifikasi tidak memindahkan kasus dari pemilik KODE RBM', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 100, 'terbaca' => 100, 'foto' => 100, 'mulai' => jam(26, 8), 'detik' => 3600],
    ])->bacaUlang([
        ['idpel' => '181100000010', 'login' => '18111.ilmenhadi', 'kode' => 'AWA', 'daya' => 1300, 'tgl' => jam(26, 9), 'stan' => 500, 'tgl_bu' => jam(26, 10), 'stan_bu' => 400],
    ]));

    $this->actingAs(superAdmin())->post('/pengaturan/pemetaan', [
        'login_acmt' => '18111.ilmenhadi',
        'biller_id' => Biller::where('login_acmt', '18111.joni')->value('id'),
    ])->assertSessionHas('success');

    expect(RereadCase::sole()->biller_id)->toBe(Biller::where('login_acmt', '18111.akmal')->value('id'))
        ->and(skor('18111.joni')->rasio_bu)->toBe(0.0);
});
