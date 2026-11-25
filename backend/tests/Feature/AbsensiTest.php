<?php

use App\Enums\StatusAbsensi;
use App\Jobs\MarkAbsentJob;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Biller;
use App\Models\MonthlyScore;
use App\Models\User;
use App\Services\LayananAbsensi;
use App\Services\PengelolaBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
    // Kamis, 5 November 2026 pukul 09.00 WIB.
    $this->travelTo(CarbonImmutable::create(2026, 11, 5, 9, 0, 0, 'Asia/Jakarta'));
    buatBillers(petugasKecil());
    $this->akmal = Biller::where('login_acmt', '18111.akmal')->first();
    $this->userAkmal = User::factory()->biller($this->akmal)->create();
    $this->admin = User::factory()->create();
});

test('biller absen hadir sendiri untuk hari ini dengan lokasi browser', function () {
    $this->actingAs($this->userAkmal)->get('/saya/absensi')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Biller/AbsensiSaya')->where('hariIni.tanggal', '2026-11-05')->where('hariIni.absen', null));

    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir', 'lat' => 0.5123, 'lng' => 101.4456])
        ->assertSessionHas('success');

    $absen = Attendance::sole();
    expect($absen->user_id)->toBe($this->userAkmal->id)
        ->and($absen->tanggal->toDateString())->toBe('2026-11-05')
        ->and($absen->status)->toBe(StatusAbsensi::Hadir)
        ->and($absen->sumber)->toBe('mandiri')
        ->and($absen->lat)->toEqualWithDelta(0.5123, 0.00001)
        ->and($absen->absen_at)->not->toBeNull()
        ->and(AuditLog::where('aksi', 'ATTEND')->exists())->toBeTrue();
});

test('lokasi tidak diizinkan tetap diterima dengan lokasi kosong', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir'])->assertSessionHas('success');

    expect(Attendance::sole()->lat)->toBeNull();
});

test('absen kedua di hari yang sama ditolak 409', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir']);

    $this->actingAs($this->userAkmal)->postJson('/saya/absensi', ['status' => 'izin', 'keterangan' => 'Urusan keluarga'])->assertStatus(409);
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir'])->assertSessionHas('error');
    expect(Attendance::count())->toBe(1)->and(Attendance::sole()->status)->toBe(StatusAbsensi::Hadir);
});

test('izin dan sakit wajib keterangan; tidak bisa menandai diri alpa', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'sakit'])->assertSessionHasErrors('keterangan');
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'alpa'])->assertSessionHasErrors('status');
    expect(Attendance::count())->toBe(0);
});

test('koordinator juga absen sendiri; Super Admin tidak wajib absen', function () {
    $this->actingAs($this->admin)->get('/absensi/saya')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Koordinator/AbsensiSaya'));
    $this->actingAs($this->admin)->post('/absensi/saya', ['status' => 'hadir'])->assertSessionHas('success');

    expect(Attendance::where('user_id', $this->admin->id)->exists())->toBeTrue();
    $this->actingAs(superAdmin())->post('/absensi/saya', ['status' => 'hadir'])->assertForbidden();
});

test('absensi tidak memengaruhi skor', function () {
    $pengelola = app(PengelolaBatch::class);
    $batch = $pengelola->unggah(berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), bukaPeriode(), null, superAdmin());
    $pengelola->commit($batch->fresh(), superAdmin());
    $sebelum = MonthlyScore::where('biller_id', $this->akmal->id)->value('final_score');

    app(LayananAbsensi::class)->tandaiAlpa(LayananAbsensi::hariIni());

    expect(MonthlyScore::where('biller_id', $this->akmal->id)->value('final_score'))->toBe($sebelum);
});

test('MarkAbsentJob mencatat alpa untuk Biller dan Koordinator yang belum absen', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'izin', 'keterangan' => 'Urusan keluarga']);
    $this->travelTo(CarbonImmutable::create(2026, 11, 5, 23, 59, 0, 'Asia/Jakarta'));

    (new MarkAbsentJob)->handle(app(LayananAbsensi::class));

    expect(Attendance::where('user_id', $this->admin->id)->sole())->status->toBe(StatusAbsensi::Alpa)->sumber->toBe('sistem')
        ->and(Attendance::where('user_id', $this->userAkmal->id)->sole()->status)->toBe(StatusAbsensi::Izin)
        ->and(Attendance::where('user_id', superAdmin()->id)->exists())->toBeFalse();
});

test('absen pukul 23.30 WIB tersimpan pada tanggal WIB', function () {
    $this->travelTo(CarbonImmutable::create(2026, 11, 5, 23, 30, 0, 'Asia/Jakarta'));
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir']);

    $absen = Attendance::sole();
    expect($absen->tanggal->toDateString())->toBe('2026-11-05')
        ->and($absen->absen_at->timezone('Asia/Jakarta')->format('H:i'))->toBe('23:30');
});

test('koordinator membaca pantauan harian dan rekap tetapi tidak bisa mengoreksi', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir']);

    $this->actingAs($this->admin)->get('/absensi')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Koordinator/MonitoringAbsensi')->has('harian', 1)->has('rekap', 1)
            ->where('harian.0.status', 'hadir'));
    $this->actingAs($this->admin)->post('/pengaturan/koreksi-absensi', [
        'user_id' => $this->userAkmal->id, 'tanggal' => '2026-11-05', 'status' => 'sakit', 'alasan_diubah' => 'Surat dokter',
    ])->assertForbidden();
});

test('Super Admin mengoreksi absensi dengan alasan wajib dan tercatat CORRECT_ATT', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir']);

    $this->actingAs(superAdmin())->post('/pengaturan/koreksi-absensi', [
        'user_id' => $this->userAkmal->id, 'tanggal' => '2026-11-05', 'status' => 'sakit',
    ])->assertSessionHasErrors('alasan_diubah');

    $this->actingAs(superAdmin())->post('/pengaturan/koreksi-absensi', [
        'user_id' => $this->userAkmal->id, 'tanggal' => '2026-11-05', 'status' => 'sakit', 'alasan_diubah' => 'Surat dokter menyusul',
    ])->assertSessionHas('success');

    expect(Attendance::sole())->status->toBe(StatusAbsensi::Sakit)->alasan_diubah->toBe('Surat dokter menyusul')
        ->and(AuditLog::where('aksi', 'CORRECT_ATT')->exists())->toBeTrue();

    $this->actingAs(superAdmin())->post('/pengaturan/koreksi-absensi', [
        'user_id' => $this->userAkmal->id, 'tanggal' => '2026-11-06', 'status' => 'hadir', 'alasan_diubah' => 'Belum terjadi',
    ])->assertSessionHasErrors('tanggal');
});

test('biller tidak bisa membuka pantauan dan rekap absensi (403)', function () {
    $this->actingAs($this->userAkmal)->get('/absensi')->assertForbidden();
    $this->actingAs($this->userAkmal)->get('/pengaturan/koreksi-absensi')->assertForbidden();
});
