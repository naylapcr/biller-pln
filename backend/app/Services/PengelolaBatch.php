<?php

namespace App\Services;

use App\Enums\Modul;
use App\Enums\StatusBatch;
use App\Enums\TingkatIssue;
use App\Exceptions\KonflikBatch;
use App\Jobs\ParseUploadJob;
use App\Models\Period;
use App\Models\StagingRow;
use App\Models\UploadBatch;
use App\Models\User;
use App\Parsers\HasilParsing;
use App\Parsers\KonteksParsing;
use App\Parsers\PembacaBerkas;
use App\Parsers\PenguraiBerkas;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Siklus batch unggahan (PRD bagian 4):
 * unggah → parse ke staging → validasi → pratinjau → commit atau batal → (rollback).
 * Data masuk tabel final dan skor hanya lewat commit batch yang lolos validasi.
 */
class PengelolaBatch
{
    public function __construct(
        private readonly PenguraiBerkas $pengurai,
        private readonly PenulisFakta $penulis,
        private readonly PenghitungSkor $skor,
    ) {}

    /** Langkah 1–2: simpan berkas asli, buat batch, jalankan parsing di antrean. */
    public function unggah(UploadedFile $berkas, Period $periode, ?Modul $modul, User $oleh): UploadBatch
    {
        if (! $periode->terbuka()) {
            throw new KonflikBatch("Periode {$periode->kode} sudah ditutup.");
        }

        $id = (string) Str::uuid();
        $ekstensi = strtolower($berkas->getClientOriginalExtension() ?: 'xlsx');
        $path = "{$periode->kode}/".($modul?->value ?? 'otomatis')."/{$id}.{$ekstensi}";
        Storage::disk($this->disk())->putFileAs(dirname($path), $berkas, basename($path));

        $batch = UploadBatch::create([
            'id' => $id,
            'unggahan_id' => $id,
            'period_id' => $periode->id,
            'modul' => $modul,
            'modul_diminta' => $modul,
            'file_name' => $berkas->getClientOriginalName(),
            'storage_path' => $path,
            'checksum_sha256' => hash_file('sha256', $berkas->getRealPath()),
            'status' => StatusBatch::Diproses,
            'diunggah_oleh' => $oleh->id,
        ]);

        Audit::catat(Audit::UPLOAD_FILE, $batch, null, ['periode' => $periode->kode, 'modul' => $modul?->value, 'file' => $batch->file_name], $oleh->id);
        ParseUploadJob::dispatch($batch->id);

        return $batch;
    }

    /** Langkah 3–4: parse setiap sheet yang dikenali ke staging dan validasi. Satu batch per modul. */
    public function proses(UploadBatch $pertama): void
    {
        $mulai = microtime(true);
        $pertama->loadMissing('period');

        try {
            $berkas = new PembacaBerkas($this->pathLokal($pertama), (int) config('sipermon.baris_per_potongan'));
            $deteksi = array_values(array_filter(
                $this->pengurai->deteksi($berkas),
                fn ($d) => $pertama->modul_diminta === null || $d['modul'] === $pertama->modul_diminta,
            ));

            if (! $deteksi) {
                $hasil = new HasilParsing($pertama->modul_diminta ?? Modul::PascabayarTunggal, '-');
                $hasil->error(null, 'HEADER_MISMATCH', $pertama->modul_diminta
                    ? "Tidak ada sheet {$pertama->modul_diminta->label()} di berkas ini (judul dan header tidak cocok)."
                    : 'Tidak ada sheet yang dikenali. Pastikan berkas adalah ekspor ACMT dengan judul dan header asli.');
                $this->simpanHasil($pertama, $hasil, $mulai);

                return;
            }

            $ctx = KonteksParsing::untuk($pertama->period->kode);
            foreach ($deteksi as $i => $d) {
                $batch = $i === 0 ? $pertama : $pertama->replicate(['id', 'modul', 'sheet', 'ringkasan', 'status']);
                $batch->modul = $d['modul'];
                $batch->sheet = $d['sheet'];
                $batch->status = StatusBatch::Diproses;
                $batch->save();

                $this->simpanHasil($batch, $this->pengurai->urai($berkas, $d['sheet'], $d['modul'], $ctx), $mulai);
            }
        } catch (Throwable $e) {
            report($e);
            $hasil = new HasilParsing($pertama->modul ?? Modul::PascabayarTunggal, $pertama->sheet ?? '-');
            $hasil->error(null, 'GAGAL_PROSES', 'Berkas gagal diproses: '.Str::limit($e->getMessage(), 300));
            $this->simpanHasil($pertama, $hasil, $mulai);
        }
    }

    private function simpanHasil(UploadBatch $batch, HasilParsing $hasil, float $mulai): void
    {
        // DUPLICATE_FILE_HASH: berkas yang sama sudah di-commit untuk modul ini.
        $kembar = $batch->modul === null ? null : UploadBatch::query()->committed()
            ->where('checksum_sha256', $batch->checksum_sha256)
            ->where('modul', $batch->modul->value)
            ->whereKeyNot($batch->id)
            ->first();
        if ($kembar) {
            $hasil->error(null, 'DUPLICATE_FILE_HASH', "Berkas yang sama sudah di-commit pada {$kembar->committed_at?->format('d/m/Y H:i')} ({$kembar->file_name}).");
        }

        DB::transaction(function () use ($batch, $hasil) {
            $batch->stagingRows()->delete();
            $batch->issues()->delete();

            $sekarang = now();
            foreach (array_chunk($hasil->baris, 500) as $potongan) {
                StagingRow::insert(array_map(fn ($b) => [
                    'batch_id' => $batch->id,
                    'sheet' => $hasil->sheet,
                    'row_number' => $b['row_number'],
                    'data' => json_encode($b['data'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $sekarang,
                ], $potongan));
            }
            foreach (array_chunk($hasil->issues, 500) as $potongan) {
                DB::table('upload_row_issues')->insert(array_map(fn ($i) => [
                    ...$i,
                    'nilai' => $i['nilai'] === null ? null : json_encode($i['nilai'], JSON_UNESCAPED_UNICODE),
                    'batch_id' => $batch->id,
                    'created_at' => $sekarang,
                ], $potongan));
            }

            $batch->update([
                'status' => $hasil->adaError() ? StatusBatch::Gagal : StatusBatch::SiapCommit,
                'ringkasan' => $hasil->ringkasan + [
                    'issues' => [
                        'error' => $hasil->jumlah(TingkatIssue::Error),
                        'warning' => $hasil->jumlah(TingkatIssue::Warning),
                        'info' => $hasil->jumlah(TingkatIssue::Info),
                        'per_kode' => $hasil->jumlahPerKode(),
                    ],
                ],
            ]);
        });

        Log::info('sipermon.batch.diproses', [
            'batch_id' => $batch->id,
            'modul' => $hasil->modul->value,
            'baris' => count($hasil->baris),
            'issues' => count($hasil->issues),
            'durasi_detik' => round(microtime(true) - $mulai, 2),
        ]);
    }

    /** Langkah 6–7: tulis ke tabel final dalam satu transaksi, gantikan batch lama, hitung ulang skor. */
    public function commit(UploadBatch $batch, User $oleh): UploadBatch
    {
        $batch->loadMissing('period');
        $this->pastikanPeriodeTerbuka($batch);
        if ($batch->status !== StatusBatch::SiapCommit) {
            throw new KonflikBatch("Batch berstatus {$batch->status->label()} tidak bisa di-commit.");
        }

        $lama = $this->dalamKunci($batch, function () use ($batch) {
            return DB::transaction(function () use ($batch) {
                $this->kunciDb($batch);
                $batch->refresh();
                if ($batch->status !== StatusBatch::SiapCommit) {
                    throw new KonflikBatch('Batch sudah diproses oleh permintaan lain.');
                }

                $lama = UploadBatch::query()->committed()
                    ->where('period_id', $batch->period_id)
                    ->where('modul', $batch->modul->value)
                    ->lockForUpdate()
                    ->first();
                if ($lama) {
                    $this->penulis->hapus($lama);
                    $lama->update(['status' => StatusBatch::Digantikan, 'superseded_by' => $batch->id]);
                }

                $jumlah = $this->penulis->tulis($batch, $this->dataStaging($batch));
                $batch->update([
                    'status' => StatusBatch::Committed,
                    'committed_at' => now(),
                    'ringkasan' => ($batch->ringkasan ?? []) + ['baris_ditulis' => $jumlah],
                ]);
                $batch->stagingRows()->delete();

                return $lama;
            });
        });

        $this->skor->hitung($batch->period);
        Audit::catat(Audit::COMMIT_BATCH, $batch, $lama ? ['batch_digantikan' => $lama->id] : null, [
            'periode' => $batch->period->kode, 'modul' => $batch->modul->value, 'baris' => $batch->ringkasan['baris_ditulis'] ?? null,
        ], $oleh->id);

        return $batch->refresh();
    }

    /** Batalkan batch yang belum di-commit. */
    public function batal(UploadBatch $batch, User $oleh): void
    {
        if (! in_array($batch->status, [StatusBatch::SiapCommit, StatusBatch::Gagal], true)) {
            throw new KonflikBatch("Batch berstatus {$batch->status->label()} tidak bisa dibatalkan.");
        }

        DB::transaction(function () use ($batch) {
            $batch->stagingRows()->delete();
            $batch->update(['status' => StatusBatch::Dibatalkan]);
        });
        Audit::catat(Audit::CANCEL_BATCH, $batch, null, ['modul' => $batch->modul?->value], $oleh->id);
    }

    /**
     * Hapus data batch yang sudah di-commit dan pulihkan batch yang digantikannya (bila ada)
     * dengan mengurai ulang berkas aslinya. Hanya selama periode terbuka.
     */
    public function rollback(UploadBatch $batch, User $oleh): ?UploadBatch
    {
        $batch->loadMissing('period');
        $this->pastikanPeriodeTerbuka($batch);
        if ($batch->status !== StatusBatch::Committed) {
            throw new KonflikBatch('Hanya batch yang sudah di-commit yang bisa di-rollback.');
        }

        $sebelumnya = UploadBatch::query()
            ->where('superseded_by', $batch->id)
            ->where('status', StatusBatch::Digantikan->value)
            ->first();

        // Urai ulang berkas batch sebelumnya di luar transaksi (bisa memakan waktu).
        $dataSebelumnya = null;
        if ($sebelumnya) {
            $hasil = $this->pengurai->urai(
                new PembacaBerkas($this->pathLokal($sebelumnya), (int) config('sipermon.baris_per_potongan')),
                $sebelumnya->sheet,
                $sebelumnya->modul,
                KonteksParsing::untuk($batch->period->kode),
            );
            $dataSebelumnya = array_column($hasil->baris, 'data');
        }

        $this->dalamKunci($batch, fn () => DB::transaction(function () use ($batch, $sebelumnya, $dataSebelumnya) {
            $this->kunciDb($batch);
            $this->penulis->hapus($batch);
            $batch->update(['status' => StatusBatch::Dibatalkan]);

            if ($sebelumnya) {
                $this->penulis->tulis($sebelumnya, $dataSebelumnya);
                $sebelumnya->update(['status' => StatusBatch::Committed, 'superseded_by' => null]);
            }
        }));

        $this->skor->hitung($batch->period);
        Audit::catat(Audit::ROLLBACK_BATCH, $batch, ['status' => 'committed'], [
            'status' => 'dibatalkan', 'batch_dipulihkan' => $sebelumnya?->id,
        ], $oleh->id);

        return $sebelumnya?->refresh();
    }

    /** @return \Generator<array<string, mixed>> */
    private function dataStaging(UploadBatch $batch): \Generator
    {
        foreach ($batch->stagingRows()->orderBy('row_number')->lazyById(1000) as $row) {
            yield $row->data;
        }
    }

    private function pastikanPeriodeTerbuka(UploadBatch $batch): void
    {
        if (! $batch->period->terbuka()) {
            throw new KonflikBatch("Periode {$batch->period->kode} sudah ditutup; data terkunci.");
        }
    }

    /** Hanya satu commit/rollback per (periode, modul) yang boleh berjalan. */
    private function dalamKunci(UploadBatch $batch, callable $fn): mixed
    {
        /** @var Lock $kunci */
        $kunci = Cache::lock("sipermon:commit:{$batch->period_id}:{$batch->modul->value}", 120);
        if (! $kunci->get()) {
            throw new KonflikBatch('Commit lain untuk periode dan modul ini sedang berjalan. Coba lagi sebentar.');
        }

        try {
            return $fn();
        } finally {
            $kunci->release();
        }
    }

    /** PostgreSQL: advisory lock transaksi sebagai pengaman tambahan antarserver. */
    private function kunciDb(UploadBatch $batch): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?, hashtext(?))', [$batch->period_id, $batch->modul->value]);
        }
    }

    private function disk(): string
    {
        return (string) config('sipermon.disk_unggahan');
    }

    /** Path lokal berkas asli; berkas di penyimpanan jarak jauh (Supabase/S3) diunduh ke folder sementara. */
    private function pathLokal(UploadBatch $batch): string
    {
        $disk = Storage::disk($this->disk());
        if (config("filesystems.disks.{$this->disk()}.driver") === 'local') {
            return $disk->path($batch->storage_path);
        }

        $tmp = storage_path('app/tmp/'.basename($batch->storage_path));
        if (! is_file($tmp)) {
            @mkdir(dirname($tmp), 0775, true);
            file_put_contents($tmp, $disk->readStream($batch->storage_path));
        }

        return $tmp;
    }
}
