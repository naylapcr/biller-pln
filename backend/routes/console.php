<?php

use App\Enums\Modul;
use App\Jobs\PurgeStagingJob;
use App\Models\Period;
use App\Models\User;
use App\Services\LayananPeriode;
use App\Services\PengelolaBatch;
use App\Services\PenghitungSkor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Hitung ulang skor satu periode.
 *   php artisan sipermon:hitung 202611
 */
Artisan::command('sipermon:hitung {kode : Kode periode BLTH, contoh 202611}', function (string $kode) {
    $periode = Period::kode($kode);
    if (! $periode) {
        $this->error("Periode {$kode} tidak ditemukan.");

        return 1;
    }
    $skor = app(PenghitungSkor::class)->hitung($periode, ! $periode->terbuka());
    $this->info("Skor {$skor->count()} biller untuk periode {$kode} dihitung.");

    return 0;
})->purpose('Hitung ulang skor biller untuk satu periode');

/*
 * Unggah berkas ACMT dari baris perintah (berguna untuk impor awal dan pengujian).
 *   php artisan sipermon:unggah "DATA REKAP WBM.xlsx" 202611 --commit
 */
Artisan::command('sipermon:unggah {berkas} {kode} {--modul= : Kosongkan untuk deteksi otomatis} {--commit : Commit batch yang lolos validasi} {--oleh= : username_nip pengunggah}', function (string $berkas, string $kode) {
    if (! is_file($berkas)) {
        $this->error("Berkas {$berkas} tidak ditemukan.");

        return 1;
    }
    $oleh = $this->option('oleh')
        ? User::where('username_nip', $this->option('oleh'))->firstOrFail()
        : User::where('role', 'super_admin')->orderBy('id')->firstOrFail();
    $periode = Period::kode($kode) ?? app(LayananPeriode::class)->buka($kode, $oleh);
    $pengelola = app(PengelolaBatch::class);

    $batch = $pengelola->unggah(new UploadedFile($berkas, basename($berkas), null, null, true), $periode, $this->option('modul') ? Modul::from($this->option('modul')) : null, $oleh);
    $batch->refresh();
    if ($batch->status->value === 'diproses') {
        $pengelola->proses($batch);
    }

    foreach ($batch->saudara()->get() as $b) {
        $r = $b->ringkasan['issues'] ?? [];
        $this->line(sprintf('%-22s %-12s baris=%-6s error=%s warning=%s info=%s',
            $b->modul?->label() ?? '-', $b->status->value, $b->ringkasan['baris'] ?? '-', $r['error'] ?? 0, $r['warning'] ?? 0, $r['info'] ?? 0));
        if ($this->option('commit') && $b->status->value === 'siap_commit') {
            $pengelola->commit($b, $oleh);
            $this->info('  → di-commit');
        }
    }

    return 0;
})->purpose('Unggah dan (opsional) commit berkas rekap ACMT');

// Hari kerja tanpa absensi menjadi alpa setelah harinya lewat, jadi skor periode terbuka
// dihitung ulang tiap hari pukul 00.15 WIB.
Schedule::call(function () {
    foreach (Period::query()->terbuka()->get() as $periode) {
        app(PenghitungSkor::class)->hitung($periode);
    }
})->dailyAt('00:15')->timezone('Asia/Jakarta')->name('hitung-ulang-absensi')->withoutOverlapping();

// Hapus staging lama tiap hari pukul 02.00 WIB.
Schedule::job(new PurgeStagingJob)
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->name('purge-staging')
    ->withoutOverlapping();
