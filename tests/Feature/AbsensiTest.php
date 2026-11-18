<?php

use App\Enums\StatusAbsensi;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Biller;
use App\Models\MonthlyScore;
use App\Models\User;
use App\Services\LayananAbsensi;
use App\Services\ParameterSkor;
use App\Services\PengelolaBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
    // Kamis, 5 November 2026. Rentang absensi periode 202611 = 1–30 November.
    $this->travelTo(CarbonImmutable::create(2026, 11, 5, 9, 0, 0, 'Asia/Jakarta'));
    buatBillers(petugasKecil());
    $this->periode = bukaPeriode();

    $pengelola = app(PengelolaBatch::class);
    $batch = $pengelola->unggah(berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), $this->periode, null, superAdmin());
    $pengelola->commit($batch->fresh(), superAdmin());

    $this->akmal = Biller::where('login_acmt', '18111.akmal')->first();
    $this->userAkmal = User::factory()->biller($this->akmal)->create();
});

test('periode baru memakai bulan BLTH sebagai rentang absensi', function () {
    expect($this->periode->absensi_mulai->toDateString())->toBe('2026-11-01')
        ->and($this->periode->absensi_selesai->toDateString())->toBe('2026-11-30');
});

test('biller mengisi absensi hari ini sendiri', function () {
    $this->actingAs($this->userAkmal)->get('/saya/absensi')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Saya/Absensi')->where('hariIni.tanggal', '2026-11-05')->where('hariIni.absen', null));

    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir'])->assertSessionHas('success');

    $absen = Attendance::sole();
    expect($absen->biller_id)->toBe($this->akmal->id)
        ->and($absen->tanggal->toDateString())->toBe('2026-11-05')
        ->and($absen->status)->toBe(StatusAbsensi::Hadir)
        ->and($absen->sumber)->toBe('biller')
        ->and(AuditLog::where('aksi', 'absen')->exists())->toBeTrue();

    // Mengubah absensi hari yang sama memperbarui baris yang ada.
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'izin', 'keterangan' => 'Urusan keluarga']);
    expect(Attendance::count())->toBe(1)->and(Attendance::sole()->status)->toBe(StatusAbsensi::Izin);
});

test('izin dan sakit wajib keterangan; biller tidak bisa menandai dirinya alpa', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'sakit'])->assertSessionHasErrors('keterangan');
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'alpa'])->assertSessionHasErrors('status');
    expect(Attendance::count())->toBe(0);
});

test('hari kerja yang sudah lewat tanpa absensi dihitung alpa; hari ini dan hari libur tidak', function () {
    // 1 Nov Minggu (bukan hari kerja), 2–4 Nov Senin–Rabu, 5 Nov hari ini.
    Attendance::create(['biller_id' => $this->akmal->id, 'tanggal' => '2026-11-02', 'status' => 'hadir']);

    $rekap = app(LayananAbsensi::class)->rekap($this->periode, [$this->akmal->id])->get($this->akmal->id);

    expect($rekap)->toMatchArray(['hadir' => 1, 'alpa' => 2, 'alpa_tanpa_absen' => 2, 'hari_kerja' => 3]);

    app(ParameterSkor::class)->ubah($this->periode, ['absensi_hari_libur' => ['2026-11-03']], superAdmin());
    expect(app(LayananAbsensi::class)->rekap($this->periode, [$this->akmal->id])->get($this->akmal->id)['alpa'])->toBe(1);
});

test('denda alpa per hari mengurangi skor akhir', function () {
    $skorAwal = MonthlyScore::where('biller_id', $this->akmal->id)->value('final_score');

    app(ParameterSkor::class)->ubah($this->periode, ['denda_alpa_per_hari' => 25], superAdmin());

    // 2, 3, 4 November tanpa absensi = 3 hari alpa.
    $skor = MonthlyScore::where('biller_id', $this->akmal->id)->first();
    expect($skor->denda_absensi)->toBe(75.0)
        ->and($skor->final_score)->toBe($skorAwal - 75)
        ->and($skor->indikator['absensi']['alpa'])->toBe(3);
});

test('koordinator admin bisa mengoreksi absensi dan absensi itu tidak bisa ditimpa biller', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/absensi')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Absensi/Index')->has('billers', 3));
    $this->actingAs($admin)->put("/absensi/{$this->akmal->id}", ['tanggal' => '2026-11-05', 'status' => 'sakit', 'keterangan' => 'Surat dokter'])
        ->assertSessionHas('success');

    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir'])->assertSessionHasErrors('status');
    expect(Attendance::sole()->sumber)->toBe('admin');

    // Tanggal yang akan datang ditolak; status kosong menghapus catatan.
    $this->actingAs($admin)->put("/absensi/{$this->akmal->id}", ['tanggal' => '2026-11-06', 'status' => 'hadir'])->assertSessionHasErrors('tanggal');
    $this->actingAs($admin)->put("/absensi/{$this->akmal->id}", ['tanggal' => '2026-11-05', 'status' => null]);
    expect(Attendance::count())->toBe(0);
});

test('biller tidak bisa membuka rekap absensi semua biller', function () {
    $this->actingAs($this->userAkmal)->get('/absensi')->assertForbidden();
    $this->actingAs($this->userAkmal)->put("/absensi/{$this->akmal->id}", ['tanggal' => '2026-11-05', 'status' => 'hadir'])->assertForbidden();
});
