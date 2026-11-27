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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function jamWib(int $jam, int $menit = 0, int $hari = 5): CarbonImmutable
{
    return CarbonImmutable::create(2026, 11, $hari, $jam, $menit, 0, 'Asia/Jakarta');
}

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
    // Kamis, 5 November 2026 pukul 07.20 WIB (sebelum jam masuk 07.30).
    $this->travelTo(jamWib(7, 20));
    buatBillers(petugasKecil());
    $this->akmal = Biller::where('login_acmt', '18111.akmal')->first();
    $this->userAkmal = User::factory()->biller($this->akmal)->create();
    $this->admin = User::factory()->create();
    // Kantor contoh; titik absen "dekat" berjarak 0 m. Radius bawaan 100 m.
    config(['sipermon.parameter.kantor_lat' => 0.5123, 'sipermon.parameter.kantor_lng' => 101.4456]);
    $this->dekat = ['lat' => 0.5123, 'lng' => 101.4456, 'akurasi' => 12];
    $this->surat = fn () => UploadedFile::fake()->create('surat-dokter.pdf', 120, 'application/pdf');
});

test('biller absen masuk tepat waktu; IP dan perangkat tercatat', function () {
    $this->actingAs($this->userAkmal)->get('/saya/absensi')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Biller/AbsensiSaya')->where('hariIni.tanggal', '2026-11-05')->where('hariIni.absen', null)
            ->where('aturanAbsensi.kantor.radius', 100)->where('aturanAbsensi.jam_masuk', '07:30'));

    $this->actingAs($this->userAkmal)->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36')
        ->post('/saya/absensi', $this->dekat + ['status' => 'hadir'])
        ->assertSessionHas('success', 'Absensi hari ini tercatat: Hadir pukul 07.20 WIB.');

    $absen = Attendance::sole();
    expect($absen->user_id)->toBe($this->userAkmal->id)
        ->and($absen->tanggal->toDateString())->toBe('2026-11-05')
        ->and($absen->status)->toBe(StatusAbsensi::Hadir)
        ->and($absen->terlambat_menit)->toBe(0)
        ->and($absen->ip)->toBe('127.0.0.1')
        ->and($absen->perangkat)->toBe('Chrome · Android')
        ->and($absen->lat)->toEqualWithDelta(0.5123, 0.00001)
        ->and(AuditLog::where('aksi', 'ATTEND')->exists())->toBeTrue();
});

test('masuk lewat 07.30 tercatat terlambat tanpa toleransi', function () {
    $this->travelTo(jamWib(7, 45));
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir'])
        ->assertSessionHas('success', 'Absensi hari ini tercatat: Hadir pukul 07.45 WIB (terlambat 15 menit).');
    expect(Attendance::sole()->terlambat_menit)->toBe(15);

    $this->actingAs($this->userAkmal)->get('/saya/absensi')
        ->assertInertia(fn (Assert $p) => $p->where('ringkasan.hadir', 1)->where('ringkasan.terlambat', 1));
});

test('di luar radius 100 m, tanpa lokasi, atau GPS kabur ditolak dan jam tidak tercatat', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir'])->assertSessionHasErrors('lokasi');
    // 0,0014 derajat lintang = sekitar 156 m dari kantor.
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir', 'lat' => 0.5137, 'lng' => 101.4456])
        ->assertSessionHasErrors(['lokasi' => 'Belum di dalam radius kantor: Anda berada ±156 m dari kantor (batas 100 m). Absen tidak tersimpan.']);
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir', 'lat' => 0.5123, 'lng' => 101.4456, 'akurasi' => 900])
        ->assertSessionHasErrors('lokasi');
    expect(Attendance::count())->toBe(0);

    // 0,0008 derajat = sekitar 89 m, masih di dalam radius.
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'hadir', 'lat' => 0.5131, 'lng' => 101.4456])->assertSessionHas('success');
    expect(Attendance::sole()->jarak_m)->toEqualWithDelta(89.0, 1);
});

test('absen pulang: setelah masuk, sekali, dalam radius; pulang sebelum 16.30 tercatat pulang cepat', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi/pulang', $this->dekat)->assertSessionHasErrors('status');

    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir']);
    $this->travelTo(jamWib(16, 10));
    $this->actingAs($this->userAkmal)->post('/saya/absensi/pulang', ['lat' => 0.5137, 'lng' => 101.4456])->assertSessionHasErrors('lokasi');
    $this->actingAs($this->userAkmal)->post('/saya/absensi/pulang', $this->dekat)
        ->assertSessionHas('success', 'Absen pulang tercatat pukul 16.10 WIB (20 menit lebih awal dari jam pulang).');

    $absen = Attendance::sole();
    expect($absen->pulang_at->timezone('Asia/Jakarta')->format('H:i'))->toBe('16:10')
        ->and($absen->pulang_cepat_menit)->toBe(20)
        ->and($absen->pulang_jarak_m)->toBe(0.0);

    $this->actingAs($this->userAkmal)->postJson('/saya/absensi/pulang', $this->dekat)->assertStatus(409);
});

test('pulang setelah 16.30 tidak dihitung pulang cepat', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir']);
    $this->travelTo(jamWib(16, 45));
    $this->actingAs($this->userAkmal)->post('/saya/absensi/pulang', $this->dekat)->assertSessionHas('success', 'Absen pulang tercatat pukul 16.45 WIB.');
    expect(Attendance::sole()->pulang_cepat_menit)->toBe(0);
});

test('hadir ditolak bila lokasi kantor belum diatur; izin dan sakit tidak perlu lokasi', function () {
    config(['sipermon.parameter.kantor_lat' => null, 'sipermon.parameter.kantor_lng' => null]);

    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir'])
        ->assertSessionHasErrors(['lokasi' => 'Lokasi kantor belum diatur Super Admin (Periode & Parameter → Kehadiran & Lokasi Absen).']);
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'sakit', 'keterangan' => 'Demam', 'foto' => ($this->surat)()])->assertSessionHas('success');
    expect(Attendance::sole()->status)->toBe(StatusAbsensi::Sakit);
});

test('izin dan sakit wajib keterangan dan lampiran foto atau PDF; tidak bisa menandai diri alpa', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'sakit', 'foto' => ($this->surat)()])->assertSessionHasErrors('keterangan');
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'sakit', 'keterangan' => 'Demam'])
        ->assertSessionHasErrors(['foto' => 'Lampirkan foto atau PDF bukti (mis. surat dokter) untuk izin atau sakit.']);
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'izin', 'keterangan' => 'x', 'foto' => UploadedFile::fake()->create('a.docx', 10)])
        ->assertSessionHasErrors('foto');
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'alpa'])->assertSessionHasErrors('status');
    expect(Attendance::count())->toBe(0);
});

test('lampiran PDF tersimpan; hanya pemilik, Koordinator, dan Super Admin yang bisa melihat', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'sakit', 'keterangan' => 'Surat dokter', 'foto' => ($this->surat)()])
        ->assertSessionHas('success');

    $absen = Attendance::sole();
    expect($absen->foto_path)->toStartWith('absensi/2026-11/')->toEndWith('.pdf');
    Storage::disk('unggahan')->assertExists($absen->foto_path);

    $this->actingAs($this->userAkmal)->get('/saya/absensi')
        ->assertInertia(fn (Assert $p) => $p->where('hariIni.absen.foto_url', route('absensi.foto', $absen->id))->where('hariIni.absen.lampiran_pdf', true));
    $this->actingAs($this->userAkmal)->get(route('absensi.foto', $absen->id))->assertOk();
    $this->actingAs($this->admin)->get(route('absensi.foto', $absen->id))->assertOk();
    $lain = User::factory()->biller(Biller::where('login_acmt', '!=', '18111.akmal')->first())->create();
    $this->actingAs($lain)->get(route('absensi.foto', $absen->id))->assertForbidden();

    // Foto juga diterima.
    $this->actingAs($lain)->post('/saya/absensi', ['status' => 'izin', 'keterangan' => 'Keluarga', 'foto' => UploadedFile::fake()->image('surat.jpg', 600, 800)])
        ->assertSessionHas('success');
});

test('absen kedua di hari yang sama ditolak 409', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir']);

    $this->actingAs($this->userAkmal)->postJson('/saya/absensi', ['status' => 'izin', 'keterangan' => 'Urusan keluarga', 'foto' => ($this->surat)()])->assertStatus(409);
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir'])->assertSessionHas('error');
    expect(Attendance::count())->toBe(1)->and(Attendance::sole()->status)->toBe(StatusAbsensi::Hadir);
});

test('koordinator juga absen sendiri; Super Admin tidak wajib absen', function () {
    $this->actingAs($this->admin)->get('/absensi/saya')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Koordinator/AbsensiSaya'));
    $this->actingAs($this->admin)->post('/absensi/saya', $this->dekat + ['status' => 'hadir'])->assertSessionHas('success');
    $this->travelTo(jamWib(16, 31));
    $this->actingAs($this->admin)->post('/absensi/saya/pulang', $this->dekat)->assertSessionHas('success');

    expect(Attendance::where('user_id', $this->admin->id)->sole()->pulang_at)->not->toBeNull();
    $this->actingAs(superAdmin())->post('/absensi/saya', $this->dekat + ['status' => 'hadir'])->assertForbidden();
});

test('absensi bulan kerja (bulan sebelum BLTH) memengaruhi skor: terlambat, pulang cepat, dan tanpa absen pulang dipotong', function () {
    // BLTH 202611 -> bulan kerja Oktober 2026. 4 Oktober hari Minggu, tidak dihitung.
    $catat = fn (string $tgl, string $status, array $lain = []) => Attendance::create($lain + [
        'user_id' => $this->userAkmal->id, 'tanggal' => $tgl, 'status' => $status, 'sumber' => 'mandiri',
    ]);
    $normal = fn (string $tgl) => ['absen_at' => "{$tgl} 07:20:00", 'terlambat_menit' => 0, 'pulang_at' => "{$tgl} 16:40:00", 'pulang_cepat_menit' => 0];
    $catat('2026-10-01', 'hadir', $normal('2026-10-01'));                                        // 100
    $catat('2026-10-02', 'alpa');                                                                 // 0
    $catat('2026-10-03', 'sakit', ['foto_path' => 'absensi/2026-10/surat.pdf']);                   // 100
    $catat('2026-10-04', 'hadir', $normal('2026-10-04'));                                        // Minggu, tidak dihitung
    $catat('2026-10-05', 'izin');                                                                 // 50 (tanpa lampiran)
    $catat('2026-10-06', 'hadir', ['terlambat_menit' => 10] + $normal('2026-10-06'));            // 75 terlambat
    $catat('2026-10-07', 'hadir', ['absen_at' => '2026-10-07 07:15:00', 'terlambat_menit' => 0]); // 75 tanpa absen pulang
    $catat('2026-11-02', 'alpa'); // bulan lain, tidak dihitung untuk BLTH 202611

    $pengelola = app(PengelolaBatch::class);
    $batch = $pengelola->unggah(berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), bukaPeriode(), null, superAdmin());
    $pengelola->commit($batch->fresh(), superAdmin());

    // (100 + 0 + 100 + 50 + 75 + 75) ÷ 6 = 66,67.
    $s = MonthlyScore::where('biller_id', $this->akmal->id)->sole();
    expect($s->nilai_kehadiran)->toBe(66.67)
        ->and($s->kehadiran)->toMatchArray(['bulan' => '2026-10', 'hari' => 6, 'hadir' => 3, 'terlambat' => 1, 'tanpa_pulang' => 1, 'alpa' => 1, 'sakit' => 1, 'izin' => 1, 'berfoto' => 1])
        ->and($s->final_score)->toEqualWithDelta(min(100, 0.9 * $s->rata_rata_modul + 0.1 * 66.6667 + $s->bonus_hari_j), 0.011);

    // Biller tanpa catatan absensi: skor = rata-rata modul + bonus.
    $lain = MonthlyScore::where('biller_id', '!=', $this->akmal->id)->first();
    expect($lain->nilai_kehadiran)->toBeNull()
        ->and($lain->final_score)->toEqualWithDelta(min(100, $lain->rata_rata_modul + $lain->bonus_hari_j), 0.011);

    // Koreksi Super Admin (alpa -> hadir, tanpa jam) langsung menghitung ulang: (100 + 100 + 100 + 50 + 75 + 75) ÷ 6 = 83,33.
    $this->actingAs(superAdmin())->post('/pengaturan/koreksi-absensi', [
        'user_id' => $this->userAkmal->id, 'tanggal' => '2026-10-02', 'status' => 'hadir', 'alasan_diubah' => 'Lupa absen, ada surat tugas',
    ])->assertSessionHas('success');
    expect($s->fresh()->nilai_kehadiran)->toBe(83.33);
});

test('MarkAbsentJob mencatat alpa untuk Biller dan Koordinator yang belum absen', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', ['status' => 'izin', 'keterangan' => 'Urusan keluarga', 'foto' => ($this->surat)()]);
    $this->travelTo(jamWib(23, 59));

    (new MarkAbsentJob)->handle(app(LayananAbsensi::class));

    expect(Attendance::where('user_id', $this->admin->id)->sole())->status->toBe(StatusAbsensi::Alpa)->sumber->toBe('sistem')
        ->and(Attendance::where('user_id', $this->userAkmal->id)->sole()->status)->toBe(StatusAbsensi::Izin)
        ->and(Attendance::where('user_id', superAdmin()->id)->exists())->toBeFalse();
});

test('absen pukul 23.30 WIB tersimpan pada tanggal WIB', function () {
    $this->travelTo(jamWib(23, 30));
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir']);

    $absen = Attendance::sole();
    expect($absen->tanggal->toDateString())->toBe('2026-11-05')
        ->and($absen->absen_at->timezone('Asia/Jakarta')->format('H:i'))->toBe('23:30');
});

test('koordinator membaca pantauan harian (jam, IP) dan rekap tetapi tidak bisa mengoreksi', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir']);

    $this->actingAs($this->admin)->get('/absensi')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Koordinator/MonitoringAbsensi')->has('harian', 1)->has('rekap', 1)
            ->where('harian.0.status', 'hadir')->where('harian.0.ip', '127.0.0.1')->where('harian.0.terlambat_menit', 0));
    $this->actingAs($this->admin)->post('/pengaturan/koreksi-absensi', [
        'user_id' => $this->userAkmal->id, 'tanggal' => '2026-11-05', 'status' => 'sakit', 'alasan_diubah' => 'Surat dokter',
    ])->assertForbidden();
});

test('Super Admin mengoreksi absensi dengan alasan wajib dan tercatat CORRECT_ATT', function () {
    $this->actingAs($this->userAkmal)->post('/saya/absensi', $this->dekat + ['status' => 'hadir']);

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
