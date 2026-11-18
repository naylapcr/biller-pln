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

test('biller hanya melihat skor miliknya, dan leaderboard tanpa rincian denda orang lain', function () {
    $this->actingAs($this->akmal)->get('/saya/skor')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Saya/Skor')
            ->where('skor.biller.login_acmt', '18111.akmal')
            ->has('top.0', fn (Assert $baris) => $baris->hasAll(['biller_id', 'nama', 'kode_petugas', 'peringkat', 'final_score'])
                ->missing('total_denda')->missing('login_acmt')));
});

test('parameter URL tidak bisa dipakai biller untuk melihat biller lain', function () {
    $joni = Biller::where('login_acmt', '18111.joni')->value('id');

    $this->actingAs($this->akmal)->get("/saya/skor?biller_id={$joni}")
        ->assertInertia(fn (Assert $p) => $p->where('skor.biller.login_acmt', '18111.akmal'));
    $this->actingAs($this->akmal)->get("/biller?detail={$joni}")->assertForbidden();
});

test('biller tidak bisa membuka dashboard, unggah, parameter, atau audit (403)', function (string $url) {
    $this->actingAs($this->akmal)->get($url)->assertForbidden();
})->with(['/dashboard', '/biller', '/unggah', '/pemetaan', '/laporan', '/pengaturan/periode', '/pengaturan/audit', '/pengaturan/users']);

test('koordinator admin bisa unggah dan melihat rincian biller, tetapi tidak mengatur parameter atau audit', function () {
    $joni = Biller::where('login_acmt', '18111.joni')->value('id');

    $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Dashboard/Index')->has('top', 3));
    $this->actingAs($this->admin)->get("/biller?detail={$joni}")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('detail.skor.biller.login_acmt', '18111.joni'));
    $this->actingAs($this->admin)->get('/unggah')->assertOk();

    $this->actingAs($this->admin)->get('/pengaturan/periode')->assertForbidden();
    $this->actingAs($this->admin)->get('/pengaturan/audit')->assertForbidden();
    $this->actingAs($this->admin)->post('/pemetaan', ['login_acmt' => 'x', 'biller_id' => $joni])->assertForbidden();
});

test('semua halaman Super Admin tampil', function (string $url, string $komponen) {
    $this->actingAs(superAdmin())->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($komponen));
})->with([
    ['/dashboard', 'Dashboard/Index'],
    ['/biller', 'Biller/Index'],
    ['/unggah', 'Unggah/Index'],
    ['/pemetaan', 'Pemetaan/Index'],
    ['/laporan', 'Laporan/Index'],
    ['/pengaturan/users', 'Pengaturan/Users/Index'],
    ['/pengaturan/billers', 'Pengaturan/Billers/Index'],
    ['/pengaturan/periode', 'Pengaturan/Periode/Index'],
    ['/pengaturan/periode/202611', 'Pengaturan/Periode/Show'],
    ['/pengaturan/audit', 'Pengaturan/Audit/Index'],
]);

test('laporan peringkat bisa diunduh sebagai Excel dan PDF', function () {
    $this->actingAs($this->admin)->get('/laporan/202611.xlsx')->assertOk()->assertDownload('peringkat-biller-202611.xlsx');
    $this->actingAs($this->admin)->get('/laporan/202611.pdf')->assertOk()->assertDownload('peringkat-biller-202611.pdf');
});

test('audit log tidak bisa diubah atau dihapus', function () {
    $log = AuditLog::first();

    expect(fn () => $log->update(['aksi' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
