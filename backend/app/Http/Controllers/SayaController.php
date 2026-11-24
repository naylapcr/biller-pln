<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\MonthlyScore;
use App\Services\LaporanKinerja;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman biller (hanya baca). Data selalu milik biller dari sesi, tidak pernah dari parameter URL.
 * Leaderboard hanya menampilkan nama dan peringkat biller lain, tanpa rincian denda mereka.
 */
class SayaController extends Controller
{
    use PilihPeriode;

    public function __invoke(Request $request, LaporanKinerja $laporan): Response
    {
        $biller = $request->user()->biller;
        abort_unless($biller, 403, 'Akun ini belum ditautkan ke data biller. Hubungi Super Admin.');

        $periode = $this->periode($request);

        return Inertia::render('Saya/Skor', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'skor' => $periode ? $laporan->skorBiller($biller, $periode) : null,
            'kasus' => $periode ? $laporan->kasusDenda($biller, $periode) : [],
            'top' => $periode ? $laporan->leaderboard($periode, 'top', denganDenda: false) : [],
            'bottom' => $periode ? $laporan->leaderboard($periode, 'bottom', denganDenda: false) : [],
            'riwayat' => MonthlyScore::query()->where('biller_id', $biller->id)
                ->with('period')
                ->get()
                ->sortBy(fn (MonthlyScore $s) => $s->period->kode)
                ->values()
                ->map(fn (MonthlyScore $s) => [
                    'periode' => $s->period->kode,
                    'label' => $s->period->nama(),
                    'final_score' => $s->final_score,
                    'peringkat' => $s->peringkat,
                    'is_final' => $s->is_final,
                ]),
        ]);
    }
}
