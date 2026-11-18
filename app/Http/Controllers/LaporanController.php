<?php

namespace App\Http\Controllers;

use App\Exports\PeringkatExport;
use App\Models\MonthlyScore;
use App\Models\Period;
use App\Services\Audit;
use App\Services\LaporanKinerja;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ekspor laporan peringkat per periode (xlsx atau pdf). */
class LaporanController extends Controller
{
    public function index(): InertiaResponse
    {
        $periode = Period::query()->whereIn('id', MonthlyScore::select('period_id'))->orderByDesc('kode')->get()
            ->map(function (Period $p) {
                $skor = MonthlyScore::where('period_id', $p->id);

                return [
                    'kode' => $p->kode,
                    'nama' => $p->nama(),
                    'final' => ! $p->terbuka(),
                    'jumlah_biller' => (clone $skor)->count(),
                    'rata' => round((float) (clone $skor)->avg('final_score'), 2),
                    'total_denda' => round((float) (clone $skor)->sum('total_denda'), 2),
                    'dihitung_at' => (clone $skor)->max('dihitung_at'),
                ];
            });

        return Inertia::render('Laporan/Index', ['periode' => $periode]);
    }

    public function unduh(Request $request, string $kode, string $format, LaporanKinerja $laporan): BinaryFileResponse|Response
    {
        $periode = Period::where('kode', $kode)->firstOrFail();
        $baris = $this->baris($periode, $laporan);
        Audit::catat('ekspor_laporan', $periode, null, ['format' => $format]);
        $nama = "peringkat-biller-{$periode->kode}.{$format}";

        if ($format === 'xlsx') {
            return Excel::download(new PeringkatExport($baris, "Peringkat {$periode->kode}"), $nama);
        }

        return Pdf::loadView('laporan.peringkat', [
            'periode' => $periode,
            'baris' => $baris,
            'ringkasan' => $laporan->ringkasan($periode),
            'dicetak' => now(),
            'oleh' => $request->user()->nama,
        ])->setPaper('a4', 'landscape')->download($nama);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function baris(Period $periode, LaporanKinerja $laporan): Collection
    {
        return $laporan->semuaBiller($periode)->filter(fn ($b) => $b['peringkat'] !== null)->values();
    }
}
