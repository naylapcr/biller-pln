<?php

namespace App\Http\Controllers;

use App\Enums\JenisMeter;
use App\Enums\Role;
use App\Http\Controllers\Concerns\PilihPeriode;
use App\Services\LaporanKinerja;
use App\Services\LayananPeriode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Koordinator Admin → Dashboard Monitoring (02.1). Super Admin → Dashboard Sistem (03.0).
 */
class DashboardController extends Controller
{
    use PilihPeriode;

    public function __invoke(Request $request, LaporanKinerja $laporan, LayananPeriode $layananPeriode): Response
    {
        $periode = $this->periode($request);

        if ($request->user()->role === Role::SuperAdmin) {
            return Inertia::render('SuperAdmin/DashboardSistem', [
                'periode' => $this->infoPeriode($periode),
                'periodeOptions' => $this->opsiPeriode(),
                'sistem' => $periode ? $laporan->sistem($periode, $layananPeriode) : null,

            ]);
        }

        $filter = $request->validate([
            'hari_baca' => ['nullable', 'string', 'size:1', Rule::in(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J'])],
            'jenis_meter' => ['nullable', Rule::enum(JenisMeter::class)],
        ]);

        return Inertia::render('Koordinator/Dashboard', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'filters' => (object) $filter,
            'jenisMeterOptions' => JenisMeter::options(),
            'ringkasan' => $periode ? $laporan->ringkasan($periode, $filter['hari_baca'] ?? null, $filter['jenis_meter'] ?? null) : null,
            'top' => $periode ? $laporan->leaderboard($periode, 'top') : [],
            'bottom' => $periode ? $laporan->leaderboard($periode, 'bottom') : [],
            'tren' => $periode ? $laporan->trenHarian($periode) : [],
        ]);
    }
}
