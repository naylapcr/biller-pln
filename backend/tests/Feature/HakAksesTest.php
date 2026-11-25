<?php

use App\Models\AuditLog;
use App\Models\Biller;
use App\Models\User;
use App\Services\PengelolaBatch;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
    buatBillers(petugasKecil());
    bukaPeriode();
    $pengelola = app(PengelolaBatch::class);
    $batch = $pengelola->unggah(berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), bukaPeriode(), null, superAdmin());
    $pengelola->commit($batch->fresh(), superAdmin());

    $this->akmal = User::factory()->biller(Biller::where('login_acmt', '18111.akmal')->first())->create();
    $this->admin = User::factory()->create();
});

test('biller hanya melihat skor miliknya, dan leaderboard hanya nama, sektor, dan peringkat', function () {
    $this->actingAs($this->akmal)->get('/saya/skor')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Biller/SkorSaya')->where('skor.biller.login_acmt', '18111.akmal'));

    $this->actingAs($this->akmal)->get('/saya/leaderboard')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Biller/Leaderboard')
            ->has('top.0', fn (Assert $baris) => $baris->hasAll(['biller_id', 'nama', 'sektor', 'peringkat'])
                ->missing('final_score')->missing('kategori')->missing('login_acmt')));
});

test('halaman Biller tampil', function (string $url, string $komponen) {
    $this->actingAs($this->akmal)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($komponen));
})->with([
    ['/saya/skor', 'Biller/SkorSaya'],
    ['/saya/absensi', 'Biller/AbsensiSaya'],
    ['/saya/histori-wo', 'Biller/HistoriWO'],
    ['/saya/leaderboard', 'Biller/Leaderboard'],
    ['/profil', 'Umum/Profil'],
]);

test('parameter URL tidak bisa dipakai biller untuk melihat biller lain', function () {
    $joni = Biller::where('login_acmt', '18111.joni')->value('id');

    $this->actingAs($this->akmal)->get("/saya/skor?biller_id={$joni}")
        ->assertInertia(fn (Assert $p) => $p->where('skor.biller.login_acmt', '18111.akmal'));
    $this->actingAs($this->akmal)->get("/biller?detail={$joni}")->assertForbidden();
});

test('biller tidak bisa membuka dashboard, unggah, parameter, atau audit (403)', function (string $url) {
    $this->actingAs($this->akmal)->get($url)->assertForbidden();
})->with(['/dashboard', '/biller', '/unggah', '/pemetaan', '/absensi', '/laporan', '/pengaturan/periode', '/pengaturan/audit', '/pengaturan/users']);

test('halaman Koordinator tampil', function (string $url, string $komponen) {
    $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($komponen));
})->with([
    ['/dashboard', 'Koordinator/Dashboard'],
    ['/biller', 'Koordinator/DirektoriBiller'],
    ['/unggah', 'Koordinator/PortalUnggah'],
    ['/pemetaan', 'Koordinator/PemetaanPetugas'],
    ['/absensi/saya', 'Koordinator/AbsensiSaya'],
    ['/absensi', 'Koordinator/MonitoringAbsensi'],
    ['/laporan', 'Koordinator/Laporan'],
    ['/profil', 'Umum/Profil'],
]);

test('koordinator melihat rincian biller tetapi tidak bisa mengatur parameter, alias, atau audit', function () {
    $joni = Biller::where('login_acmt', '18111.joni')->value('id');

    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('top', 3));
    $this->actingAs($this->admin)->get("/biller?detail={$joni}")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('detail.skor.biller.login_acmt', '18111.joni')->has('detail.tren'));

    $this->actingAs($this->admin)->get('/pengaturan/periode')->assertForbidden();
    $this->actingAs($this->admin)->get('/pengaturan/audit')->assertForbidden();
    $this->actingAs($this->admin)->post('/pengaturan/pemetaan', ['login_acmt' => 'x', 'biller_id' => $joni])->assertForbidden();
});

test('halaman Super Admin tampil', function (string $url, string $komponen) {
    $this->actingAs(superAdmin())->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($komponen));
})->with([
    ['/dashboard', 'SuperAdmin/DashboardSistem'],
    ['/pengaturan/users', 'SuperAdmin/ManajemenAkun'],
    ['/pengaturan/billers', 'SuperAdmin/MasterBiller'],
    ['/pengaturan/pemetaan', 'SuperAdmin/PemetaanAlias'],
    ['/pengaturan/periode', 'SuperAdmin/PeriodeParameter'],
    ['/pengaturan/koreksi-absensi', 'SuperAdmin/KoreksiAbsensi'],
    ['/pengaturan/audit', 'SuperAdmin/AuditTrail'],
    ['/profil', 'Umum/Profil'],
]);

test('Super Admin tidak membuka halaman operasional Koordinator (403)', function (string $url) {
    $this->actingAs(superAdmin())->get($url)->assertForbidden();
})->with(['/biller', '/unggah', '/pemetaan', '/absensi', '/absensi/saya', '/laporan']);

test('Super Admin yang memanggil unggah, commit, atau rollback menerima 403', function () {
    $batch = App\Models\UploadBatch::first();

    $this->actingAs(superAdmin())->post('/unggah', [])->assertForbidden();
    $this->actingAs(superAdmin())->post("/unggah/{$batch->id}/commit")->assertForbidden();
    $this->actingAs(superAdmin())->post("/unggah/{$batch->id}/rollback")->assertForbidden();
});

test('laporan peringkat bisa diunduh sebagai Excel dan PDF', function () {
    $this->actingAs($this->admin)->get('/laporan/202611.xlsx')->assertOk()->assertDownload('peringkat-biller-202611.xlsx');
    $this->actingAs($this->admin)->get('/laporan/202611.pdf')->assertOk()->assertDownload('peringkat-biller-202611.pdf');
});

test('audit log memakai kode aksi PRD dan tidak bisa diubah atau dihapus', function () {
    $log = AuditLog::first();

    expect(AuditLog::where('aksi', 'COMMIT_BATCH')->exists())->toBeTrue()
        ->and(fn () => $log->update(['aksi' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
