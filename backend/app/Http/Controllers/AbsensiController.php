<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\LayananAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring Absensi (02.7) untuk Koordinator Admin, mode baca saja: pantauan harian dan rekap
 * bulanan semua biller. Koreksi hanya oleh Super Admin di Pengaturan → Koreksi Absensi.
 */
class AbsensiController extends Controller
{
    public function __construct(private readonly LayananAbsensi $absensi) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);
        $hariIni = LayananAbsensi::hariIni();
        $tanggal = $request->filled('tanggal') ? CarbonImmutable::parse($request->string('tanggal')->toString(), config('app.timezone')) : $hariIni;
        $bulan = $request->filled('bulan') ? CarbonImmutable::createFromFormat('Y-m', $request->string('bulan')->toString())->startOfMonth() : $tanggal->startOfMonth();

        return Inertia::render('Koordinator/MonitoringAbsensi', [
            'tanggal' => $tanggal->toDateString(),
            'hariIni' => $hariIni->toDateString(),
            'bulan' => $bulan->format('Y-m'),
            'harian' => $this->absensi->harian($tanggal, [Role::Biller]),
            'rekap' => $this->absensi->rekapBulanan($bulan, [Role::Biller]),
        ]);
    }
}
