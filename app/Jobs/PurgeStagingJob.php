<?php

namespace App\Jobs;

use App\Enums\StatusBatch;
use App\Models\StagingRow;
use App\Models\UploadBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hapus staging_rows batch yang sudah commit (atau dibatalkan/digantikan), dan semua staging
 * yang berumur lebih dari 30 hari. Staging batch gagal disimpan sampai 30 hari untuk pratinjau.
 */
class PurgeStagingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $selesai = UploadBatch::query()
            ->whereIn('status', [StatusBatch::Committed->value, StatusBatch::Dibatalkan->value, StatusBatch::Digantikan->value])
            ->select('id');
        StagingRow::query()->whereIn('batch_id', $selesai)->delete();

        StagingRow::query()->where('created_at', '<', now()->subDays((int) config('sipermon.umur_staging_hari')))->delete();
    }
}
