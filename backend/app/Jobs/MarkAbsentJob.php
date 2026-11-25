<?php

namespace App\Jobs;

use App\Services\LayananAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Harian 23.59 WIB: catat alpa (sumber = sistem) untuk Biller dan Koordinator aktif yang hari itu
 * tidak absen hadir, izin, atau sakit. Tanggal dihitung dalam WIB, bukan tanggal UTC.
 */
class MarkAbsentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $tanggal = null) {}

    public function handle(LayananAbsensi $absensi): void
    {
        $absensi->tandaiAlpa($this->tanggal
            ? CarbonImmutable::parse($this->tanggal, config('app.timezone'))->startOfDay()
            : LayananAbsensi::hariIni());
    }
}
