<?php

use App\Models\Biller;
use App\Models\MonthlyScore;
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

/** Akmal: 100 terbaca, 90 foto, 2 Hari J; 1 kasus Baca Ulang salah. Joni: 80 terbaca, 80 foto. */
function dataSkor(): callable
{
    $t = CarbonImmutable::create(2026, 10, 26, 8, 0, 0, 'Asia/Jakarta');

    return fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 100, 'terbaca' => 98, 'foto' => 88],
        ['hari' => 'J', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 2, 'terbaca' => 2, 'foto' => 2],
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 80, 'terbaca' => 80, 'foto' => 80],
    ])->bacaUlang([
        ['idpel' => '181100000001', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'daya' => 1300, 'tgl' => $t, 'stan' => 500, 'tgl_bu' => $t->addHours(2), 'stan_bu' => 400],
        ['idpel' => '181100000002', 'login' => '18111.joni', 'kode' => 'ARA', 'daya' => 1300, 'tgl' => $t, 'stan' => 500, 'tgl_bu' => $t->addHours(2), 'stan_bu' => 501],
    ]);
}

test('skor dihitung sesuai rumus PRD 6.1 dengan parameter bawaan', function () {
    commitBerkas(dataSkor());

    // Akmal: StanValid = 100 − 1 kasus = 99; Eviden = 90. Poin = 99×4 + 90×1,5 = 531. Denda BU = 10.
    $akmal = skor('18111.akmal');
    expect($akmal->poin_per_modul['pascabayar'])->toMatchArray(['stan_valid' => 99, 'eviden' => 90, 'poin' => 531])
        ->and($akmal->poin_hari_j)->toBe(0.0)          // bonus_dasar_j & poin_per_plg_j belum ditetapkan
        ->and($akmal->denda_bu)->toBe(10.0)
        ->and($akmal->final_score)->toBe(521.0)
        // Joni: 80×4 + 80×1,5 = 440; Baca Ulang wajar.
        ->and(skor('18111.joni')->final_score)->toBe(440.0)
        ->and($akmal->peringkat)->toBe(1)
        ->and($akmal->rules_snapshot['w_stan'])->toBe(4);
});

test('bonus Hari J dihitung setelah parameternya diisi, dengan batas min dan max', function () {
    commitBerkas(dataSkor());
    app(ParameterSkor::class)->ubah($this->periode, ['bonus_dasar_j' => 40, 'poin_per_plg_j' => 3], superAdmin());

    // clamp(40 + 2×3, 50, 150) = 50
    expect(skor('18111.akmal')->poin_hari_j)->toBe(50.0)
        ->and(skor('18111.joni')->poin_hari_j)->toBe(0.0); // tidak ada Hari J
});

test('mengubah denda_bu_per_kasus langsung mengubah final_score biller yang punya kasus', function () {
    commitBerkas(dataSkor());
    $sebelum = skor('18111.akmal')->final_score;

    app(ParameterSkor::class)->ubah($this->periode, ['denda_bu_per_kasus' => 25], superAdmin());

    expect(skor('18111.akmal')->final_score)->toBe($sebelum - 15)
        ->and(skor('18111.joni')->final_score)->toBe(440.0);
});

test('mode rata_rata_modul membagi jumlah poin dengan jumlah modul yang punya data', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 10, 'terbaca' => 10, 'foto' => 10],
    ])->prabayar(10, ['Z']));
    $jumlah = skor('18111.akmal')->poin_tugas;

    app(ParameterSkor::class)->ubah($this->periode, ['mode_agregasi' => 'rata_rata_modul'], superAdmin());

    expect(skor('18111.akmal')->poin_tugas)->toBe(round($jumlah / 2, 2));
});

test('seri diputus oleh denda lebih kecil, lalu StanValid lebih besar', function () {
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 10, 'terbaca' => 10, 'foto' => 0],
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 10, 'terbaca' => 10, 'foto' => 0],
        ['hari' => 'A', 'login' => '18111.deni', 'kode' => 'ALA', 'jml' => 12, 'terbaca' => 12, 'foto' => 0],
    ]));
    app(ParameterSkor::class)->ubah($this->periode, ['w_stan' => 0], superAdmin()); // semua skor 0

    expect(MonthlyScore::orderBy('peringkat')->with('biller')->get()->pluck('biller.login_acmt')->first())->toBe('18111.deni');
});

test('periode tidak bisa ditutup selama parameter wajib kosong; setelah lengkap skor menjadi final', function () {
    commitBerkas(dataSkor());
    $layanan = app(LayananPeriode::class);

    expect(fn () => $layanan->tutup($this->periode, superAdmin()))->toThrow(ValidationException::class);

    app(ParameterSkor::class)->ubah($this->periode, ['bonus_dasar_j' => 50, 'poin_per_plg_j' => 0], superAdmin());
    $layanan->tutup($this->periode->fresh(), superAdmin());

    expect($this->periode->fresh()->terbuka())->toBeFalse()
        ->and(MonthlyScore::where('is_final', true)->count())->toBe(2)
        ->and(fn () => app(ParameterSkor::class)->ubah($this->periode->fresh(), ['w_stan' => 5], superAdmin()))->toThrow(ValidationException::class);
});

test('periode baru menyalin parameter dari periode sebelumnya', function () {
    app(ParameterSkor::class)->ubah($this->periode, ['w_stan' => 5, 'bonus_dasar_j' => 60], superAdmin());

    $baru = bukaPeriode('202612');

    expect(app(ParameterSkor::class)->untuk($baru))->toMatchArray(['w_stan' => 5, 'bonus_dasar_j' => 60]);
});

test('alias untuk petugas yang belum terpetakan mengisi biller_id dan memicu hitung ulang', function () {
    $t = CarbonImmutable::create(2026, 10, 26, 8, 0, 0, 'Asia/Jakarta');
    commitBerkas(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.joni', 'kode' => 'ARA', 'jml' => 10, 'terbaca' => 10, 'foto' => 10],
    ])->bacaUlang([
        ['idpel' => '181100000009', 'login' => '18111.ilmenhadi', 'kode' => 'ATA', 'daya' => 1300, 'tgl' => $t, 'stan' => 500, 'tgl_bu' => $t->addHour(), 'stan_bu' => 400],
    ]));
    expect(skor('18111.joni')->denda_bu)->toBe(0.0);

    $this->actingAs(superAdmin())->post('/pemetaan', [
        'login_acmt' => '18111.ilmenhadi',
        'biller_id' => Biller::where('login_acmt', '18111.joni')->value('id'),
    ])->assertSessionHas('success');

    expect(skor('18111.joni')->denda_bu)->toBe(10.0)
        ->and(DB::table('reread_cases')->whereNull('biller_id')->count())->toBe(0);
});
