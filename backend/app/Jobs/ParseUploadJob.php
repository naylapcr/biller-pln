<?php

namespace App\Jobs;

use App\Enums\StatusBatch;
use App\Models\UploadBatch;
use App\Services\PengelolaBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Baca berkas per potongan, deteksi sheet, isi staging dan issue, set status siap_commit atau gagal. */
class ParseUploadJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public string $batchId) {}

    public function handle(PengelolaBatch $pengelola): void
    {
        $batch = UploadBatch::find($this->batchId);
        if ($batch && $batch->status === StatusBatch::Diproses) {
            $pengelola->proses($batch);
        }
    }

    public function failed(\Throwable $e): void
    {
        UploadBatch::whereKey($this->batchId)->where('status', StatusBatch::Diproses->value)
            ->update(['status' => StatusBatch::Gagal->value, 'ringkasan' => json_encode(['galat' => $e->getMessage()])]);
    }
}
