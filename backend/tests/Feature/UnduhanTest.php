<?php

use App\Models\Biller;
use App\Models\User;
use App\Parsers\PembacaBerkas;
use App\Parsers\PenguraiBerkas;
use App\Services\PengelolaBatch;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
    buatBillers(petugasKecil());
    $this->periode = bukaPeriode();
    $this->admin = User::factory()->create();
});

test('batch ditolak: kartu cek GRAND TOTAL gagal dan laporan error bisa diunduh sebagai CSV', function () {
    $berkas = berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil());
    $batch = app(PengelolaBatch::class)->unggah($berkas, $this->periode, null, $this->admin)->fresh();
    $batch->update(['status' => 'gagal']);
    $batch->issues()->create(['sheet' => 'Rekap', 'row_number' => 9, 'kode' => 'TOTAL_MISMATCH', 'tingkat' => 'error', 'pesan' => '=SUM(A1)', 'created_at' => now()]);

    $this->actingAs($this->admin)->get("/unggah/{$batch->id}")
        ->assertInertia(fn (Assert $p) => $p->where('batch.nomor', 'BATCH-2611-01')
            ->where('batch.cek.0.kunci', 'grand_total')->where('batch.cek.0.lolos', false)
            ->where('batch.login.terpetakan', 3)->has('staging.data', 5)->has('staging.data.0.biller'));

    $csv = $this->actingAs($this->admin)->get("/unggah/{$batch->id}/issues")->assertOk()->streamedContent();
    expect($csv)->toContain('TOTAL_MISMATCH')->toContain("'=SUM(A1)");

    $biller = User::factory()->biller(Biller::first())->create();
    $this->actingAs($biller)->get("/unggah/{$batch->id}/issues")->assertForbidden();
});

test('template XLSX dikenali parser untuk keempat modul', function () {
    $r = $this->actingAs($this->admin)->get('/unggah/template')->assertOk();
    $path = $r->baseResponse->getFile()->getPathname();
    $salinan = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    copy($path, $salinan);

    $modul = collect((new PenguraiBerkas)->deteksi(new PembacaBerkas($salinan)))->pluck('modul')->map->value->sort()->values()->all();
    expect($modul)->toBe(['baca_ulang', 'pascabayar_ganda', 'pascabayar_tunggal', 'prabayar']);
    @unlink($salinan);
});

test('audit trail bisa difilter per objek dan diekspor CSV oleh Super Admin saja', function () {
    $sa = superAdmin();
    $this->actingAs($sa)->post('/pengaturan/periode', ['kode' => '202612']);

    $this->actingAs($sa)->get('/pengaturan/audit?objek=Period')->assertInertia(fn (Assert $p) => $p->has('logs.data', 2)->where('logs.data.0.aksi', 'OPEN_PERIOD'));
    expect($this->actingAs($sa)->get('/pengaturan/audit/csv')->assertOk()->streamedContent())->toContain('OPEN_PERIOD');
    $this->actingAs($this->admin)->get('/pengaturan/audit/csv')->assertForbidden();
});

test('arsip laporan Super Admin hanya untuk periode yang sudah ditutup', function () {
    $sa = superAdmin();
    $this->actingAs($sa)->getJson('/pengaturan/periode/202611/laporan.xlsx')->assertStatus(409);
    $this->periode->update(['status' => 'ditutup', 'ditutup_at' => now()]);
    $this->actingAs($sa)->get('/pengaturan/periode/202611/laporan.xlsx')->assertOk();
    $this->actingAs($this->admin)->get('/pengaturan/periode/202611/laporan.xlsx')->assertForbidden();
});

test('riwayat koreksi menampilkan status sebelum dan sesudah', function () {
    $sa = superAdmin();
    $user = User::factory()->biller(Biller::first())->create();
    $tanggal = now()->toDateString();
    $this->actingAs($sa)->post('/pengaturan/koreksi-absensi', ['user_id' => $user->id, 'tanggal' => $tanggal, 'status' => 'hadir', 'alasan_diubah' => 'Kendala sinyal']);
    $this->actingAs($sa)->post('/pengaturan/koreksi-absensi', ['user_id' => $user->id, 'tanggal' => $tanggal, 'status' => 'sakit', 'alasan_diubah' => 'Surat dokter']);

    $this->actingAs($sa)->get('/pengaturan/koreksi-absensi')->assertInertia(fn (Assert $p) => $p->has('riwayat', 2)
        ->where('riwayat.0.sebelum', 'hadir')->where('riwayat.0.status', 'sakit')->where('riwayat.0.petugas', $user->nama)
        ->where('riwayat.1.sebelum', 'belum'));
});
