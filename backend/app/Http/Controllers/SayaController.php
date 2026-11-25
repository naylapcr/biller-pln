<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Biller;
use App\Services\LaporanKinerja;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Biller (hanya baca): Skor Saya, Histori WO, dan Leaderboard. Data selalu milik biller
 * dari sesi, tidak pernah dari parameter URL. Leaderboard hanya menampilkan nama, sektor, dan
 * peringkat biller lain (mode privasi).
 */
class SayaController extends Controller
{
    use PilihPeriode;

    public function __construct(private readonly LaporanKinerja $laporan) {}

    public function skor(Request $request): Response
    {
        $biller = $this->biller($request);
        $periode = $this->periode($request);

        return Inertia::render('Biller/SkorSaya', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'skor' => $periode ? $this->laporan->skorBiller($biller, $periode) : null,
            'tren' => $this->laporan->tren($biller, 4, $periode),
            'kesalahan' => $periode ? $this->laporan->kesalahan($biller, $periode) : [],
        ]);
    }

    public function historiWo(Request $request): Response
    {
        $biller = $this->biller($request);
        $periode = $this->periode($request);

        return Inertia::render('Biller/HistoriWO', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'histori' => $periode ? $this->laporan->historiWo($biller, $periode) : null,
        ]);
    }

    public function leaderboard(Request $request): Response
    {
        $biller = $this->biller($request);
        $periode = $this->periode($request);
        $skor = $periode ? $this->laporan->skorBiller($biller, $periode) : null;

        return Inertia::render('Biller/Leaderboard', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'posisi' => $skor ? [
                'peringkat' => $skor['peringkat'],
                'jumlah_biller' => $skor['jumlah_biller'],
                'final_score' => $skor['final_score'],
                'kategori' => $skor['kategori'],
            ] : null,
            'top' => $periode ? $this->laporan->leaderboard($periode, 'top', lengkap: false) : [],
            'bottom' => $periode ? $this->laporan->leaderboard($periode, 'bottom', lengkap: false) : [],
            'billerId' => $biller->id,
        ]);
    }

    private function biller(Request $request): Biller
    {
        $biller = $request->user()->biller;
        abort_unless($biller, 403, 'Akun ini belum ditautkan ke data biller. Hubungi Super Admin.');

        return $biller;
    }
}
