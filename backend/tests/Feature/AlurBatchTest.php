<?php

use App\Enums\StatusBatch;
use App\Models\MonthlyScore;
use App\Models\ReadingLog;
use App\Models\StagingRow;
use App\Models\UploadBatch;
use App\Models\User;
use Database\Seeders\Support\ContohBerkasAcmt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('unggahan');
    buatBillers(petugasKecil());
    $this->periode = bukaPeriode();
    $this->admin = User::factory()->create();
});

/** Satu batch per modul untuk berkas berisi beberapa sheet. */
function unggah($test, $berkas, ?string $modul = null): UploadBatch
{
    $respons = $test->actingAs($test->admin)->post('/unggah', array_filter(['file' => $berkas, 'periode' => '202611', 'modul' => $modul]))
        ->assertSessionHasNoErrors()->assertRedirect();

    // Batch pertama dari unggahan ini ada di URL tujuan /unggah/{id}.
    return UploadBatch::findOrFail(basename(parse_url($respons->headers->get('Location'), PHP_URL_PATH)));
}

test('unggahan multi-sheet menghasilkan satu batch per modul yang siap di-commit', function () {
    $batch = unggah($this, berkasAcmt(fn (ContohBerkasAcmt $c) => $c->tunggal()->ganda()->bacaUlang()->prabayar(4), petugasKecil()));

    $semua = UploadBatch::query()->where('unggahan_id', $batch->unggahan_id)->get();
    expect($semua)->toHaveCount(4)
        ->and($semua->pluck('modul')->map->value->sort()->values()->all())->toBe(['baca_ulang', 'pascabayar_ganda', 'pascabayar_tunggal', 'prabayar'])
        ->and($semua->every(fn ($b) => $b->status === StatusBatch::SiapCommit))->toBeTrue()
        ->and(Storage::disk('unggahan')->exists($batch->storage_path))->toBeTrue()
        ->and($batch->checksum_sha256)->toHaveLength(64);
});

test('pratinjau menampilkan ringkasan, issue, dan baris staging', function () {
    $batch = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), 'pascabayar_tunggal');

    $this->actingAs($this->admin)->get("/unggah/{$batch->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Koordinator/DetailBatch')
            ->where('batch.status', 'siap_commit')
            ->where('batch.bisa.commit', true)
            ->has('staging.data')
            ->where('batch.ringkasan.login_unik', 3));
});

test('commit menulis ke tabel final, menghapus staging, dan menghitung skor', function () {
    $batch = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), 'pascabayar_tunggal');

    $this->actingAs($this->admin)->post("/unggah/{$batch->id}/commit")->assertSessionHas('success');

    expect($batch->fresh()->status)->toBe(StatusBatch::Committed)
        ->and(ReadingLog::where('batch_id', $batch->id)->count())->toBe($batch->ringkasan['baris'])
        ->and(StagingRow::where('batch_id', $batch->id)->count())->toBe(0)
        ->and(MonthlyScore::where('period_id', $this->periode->id)->count())->toBe(3);
});

test('commit ulang modul yang sama menggantikan batch lama tanpa melipatgandakan baris', function () {
    $pertama = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), 'pascabayar_tunggal');
    $this->actingAs($this->admin)->post("/unggah/{$pertama->id}/commit");
    expect(ReadingLog::count())->toBe($pertama->fresh()->ringkasan['baris']);

    $kedua = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil(), nama: 'revisi.xlsx', seed: 7), 'pascabayar_tunggal');
    $this->actingAs($this->admin)->post("/unggah/{$kedua->id}/commit");

    expect($pertama->fresh()->status)->toBe(StatusBatch::Digantikan)
        ->and($pertama->fresh()->superseded_by)->toBe($kedua->id)
        ->and(ReadingLog::count())->toBe($kedua->fresh()->ringkasan['baris'])
        ->and(ReadingLog::where('batch_id', $pertama->id)->count())->toBe(0);
});

test('rollback mengembalikan tabel final dan skor ke keadaan sebelum commit', function () {
    $pertama = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), 'pascabayar_tunggal');
    $this->actingAs($this->admin)->post("/unggah/{$pertama->id}/commit");
    $skorAwal = MonthlyScore::orderBy('biller_id')->pluck('final_score', 'biller_id')->all();
    $logAwal = ReadingLog::orderBy('id')->get(['kode_rbm', 'terbaca'])->toArray();

    $kedua = unggah($this, berkasAcmt(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 10, 'terbaca' => 10, 'foto' => 10],
    ]), petugasKecil(), nama: 'kecil.xlsx'), 'pascabayar_tunggal');
    $this->actingAs($this->admin)->post("/unggah/{$kedua->id}/commit");
    expect(ReadingLog::count())->toBe(1);

    $this->actingAs($this->admin)->post("/unggah/{$kedua->id}/rollback")->assertSessionHas('success');

    expect($kedua->fresh()->status)->toBe(StatusBatch::Dibatalkan)
        ->and($pertama->fresh()->status)->toBe(StatusBatch::Committed)
        ->and(ReadingLog::orderBy('id')->get(['kode_rbm', 'terbaca'])->toArray())->toBe($logAwal)
        ->and(MonthlyScore::orderBy('biller_id')->pluck('final_score', 'biller_id')->all())->toBe($skorAwal);
});

test('berkas yang sama diunggah dua kali ditolak DUPLICATE_FILE_HASH', function () {
    $isi = fn ($c) => $c->tunggal();
    $path = sys_get_temp_dir().'/sipermon-dup.xlsx';
    $isi(new ContohBerkasAcmt(petugasKecil()))->simpan($path);
    $berkas = fn () => new UploadedFile($path, 'dup.xlsx', null, null, true);

    $pertama = unggah($this, $berkas(), 'pascabayar_tunggal');
    $this->actingAs($this->admin)->post("/unggah/{$pertama->id}/commit");
    $kedua = unggah($this, $berkas(), 'pascabayar_tunggal');

    expect($kedua->status)->toBe(StatusBatch::Gagal)
        ->and($kedua->issues()->where('kode', 'DUPLICATE_FILE_HASH')->exists())->toBeTrue();
});

test('berkas dengan BLTH berbeda ditolak PERIODE_MISMATCH dan UNITUP lain ditolak UNIT_MISMATCH', function () {
    $blth = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil(), blth: '202610'), 'pascabayar_tunggal');
    $unit = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil(), unitup: '18112'), 'pascabayar_tunggal');

    expect($blth->status)->toBe(StatusBatch::Gagal)
        ->and($blth->issues()->where('kode', 'PERIODE_MISMATCH')->exists())->toBeTrue()
        ->and($unit->issues()->where('kode', 'UNIT_MISMATCH')->exists())->toBeTrue();

    $this->actingAs($this->admin)->post("/unggah/{$blth->id}/commit")->assertSessionHas('error');
    expect($blth->fresh()->status)->toBe(StatusBatch::Gagal);
});

test('jumlah hasil parsing yang berbeda dari GRAND TOTAL ditolak TOTAL_MISMATCH', function () {
    $batch = unggah($this, berkasAcmt(fn ($c) => $c->tunggal([
        ['hari' => 'A', 'login' => '18111.AKMAL', 'kode' => 'AWA', 'jml' => 10, 'terbaca' => 10, 'foto' => 9],
    ], ['grand_total' => [11, 10, 0, 9]]), petugasKecil()), 'pascabayar_tunggal');

    expect($batch->status)->toBe(StatusBatch::Gagal)
        ->and($batch->issues()->where('kode', 'TOTAL_MISMATCH')->value('pesan'))->toContain('JMLPLG');
});

test('commit ke periode yang sudah ditutup ditolak (409 untuk JSON)', function () {
    $batch = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), 'pascabayar_tunggal');
    $this->periode->update(['status' => 'ditutup']);

    $this->actingAs($this->admin)->postJson("/unggah/{$batch->id}/commit")->assertStatus(409);
    expect($batch->fresh()->status)->toBe(StatusBatch::SiapCommit);
});

test('batch yang belum di-commit bisa dibatalkan', function () {
    $batch = unggah($this, berkasAcmt(fn ($c) => $c->tunggal(), petugasKecil()), 'pascabayar_tunggal');

    $this->actingAs($this->admin)->delete("/unggah/{$batch->id}")->assertSessionHas('success');

    expect($batch->fresh()->status)->toBe(StatusBatch::Dibatalkan)
        ->and(StagingRow::where('batch_id', $batch->id)->count())->toBe(0);
});

test('berkas tanpa sheet yang dikenali gagal dengan HEADER_MISMATCH', function () {
    $batch = unggah($this, berkasAcmt(fn ($c) => $c->tunggal([], ['judul' => 'LAPORAN LAIN']), petugasKecil()));

    expect($batch->status)->toBe(StatusBatch::Gagal)
        ->and($batch->issues()->where('kode', 'HEADER_MISMATCH')->exists())->toBeTrue();
});

test('berkas selain xlsx dan csv ditolak', function () {
    $this->actingAs($this->admin)->post('/unggah', [
        'file' => UploadedFile::fake()->create('rekap.pdf', 10),
        'periode' => '202611',
    ])->assertSessionHasErrors('file');
});
