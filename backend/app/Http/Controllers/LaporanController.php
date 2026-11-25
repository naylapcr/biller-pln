<?php

namespace App\Http\Controllers;

use App\Exports\PeringkatExport;
use App\Http\Controllers\Concerns\PilihPeriode;
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
    use PilihPeriode;

    /** Laporan & Ekspor (02.8): status periode (sementara atau final), pratinjau peringkat, unduh XLSX/PDF. */
    public function index(Request $request, LaporanKinerja $laporan): InertiaResponse
    {
        $periode = $this->periode($request);

        return Inertia::render('Koordinator/Laporan', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'baris' => $periode ? $this->baris($periode, $laporan) : [],
            'dihitungAt' => $periode ? MonthlyScore::where('period_id', $periode->id)->max('dihitung_at') : null,
        ]);
    }

    public function unduh(Request $request, string $kode, string $format, LaporanKinerja $laporan): BinaryFileResponse|Response
    {
        $periode = Period::where('kode', $kode)->firstOrFail();
        $baris = $this->baris($periode, $laporan);
        Audit::catat(Audit::EXPORT_REPORT, $periode, null, ['format' => $format]);
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

    /** Super Admin hanya mengunduh arsip periode yang sudah ditutup (04.2). */
    public function unduhFinal(Request $request, string $kode, string $format, LaporanKinerja $laporan): BinaryFileResponse|Response
    {
        abort_if(Period::where('kode', $kode)->firstOrFail()->terbuka(), 409, 'Arsip tersedia setelah periode ditutup.');

        return $this->unduh($request, $kode, $format, $laporan);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function baris(Period $periode, LaporanKinerja $laporan): Collection
    {
        return $laporan->semuaBiller($periode)->filter(fn ($b) => $b['peringkat'] !== null)->values();
    }
}
