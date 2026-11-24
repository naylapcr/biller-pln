<?php

namespace App\Http\Controllers;

use App\Enums\JenisMeter;
use App\Http\Controllers\Concerns\PilihPeriode;
use App\Services\LaporanKinerja;
use App\Services\ParameterSkor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Dashboard Koordinator Admin & Super Admin: 4 kartu KPI, Top/Bottom 5, dan tren. */
class DashboardController extends Controller
{
    use PilihPeriode;

    public function __invoke(Request $request, LaporanKinerja $laporan, ParameterSkor $parameter): Response
    {
        $filter = $request->validate([
            'hari_baca' => ['nullable', 'string', 'size:1', 'regex:/^[A-Z]$/'],
            'jenis_meter' => ['nullable', Rule::enum(JenisMeter::class)],
        ]);
        $periode = $this->periode($request);

        return Inertia::render('Dashboard/Index', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'filters' => (object) $filter,
            'jenisMeterOptions' => JenisMeter::options(),
            'ringkasan' => $periode ? $laporan->ringkasan($periode, $filter['hari_baca'] ?? null, $filter['jenis_meter'] ?? null) : null,
            'top' => $periode ? $laporan->leaderboard($periode, 'top') : [],
            'bottom' => $periode ? $laporan->leaderboard($periode, 'bottom') : [],
            'tren' => $periode ? $laporan->tren($periode) : [],
            'parameterKosong' => $periode ? $parameter->kosongWajib($periode) : [],
        ]);
    }
}
