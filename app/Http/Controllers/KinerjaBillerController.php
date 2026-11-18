<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Biller;
use App\Services\LaporanKinerja;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tabel semua biller dengan skor dan peringkat, serta drawer rincian skor satu biller. */
class KinerjaBillerController extends Controller
{
    use PilihPeriode;

    public function __invoke(Request $request, LaporanKinerja $laporan): Response
    {
        $request->validate(['cari' => ['nullable', 'string', 'max:60'], 'detail' => ['nullable', 'integer']]);
        $periode = $this->periode($request);
        $detail = $request->integer('detail') ?: null;

        return Inertia::render('Biller/Index', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'filters' => (object) $request->only(['cari']),
            'billers' => $periode ? $laporan->semuaBiller($periode, $request->string('cari')->toString() ?: null) : [],
            // Isi drawer, dimuat lewat partial reload saat baris diklik.
            'detail' => fn () => $periode && $detail && ($b = Biller::find($detail)) ? [
                'skor' => $laporan->skorBiller($b, $periode),
                'kasus' => $laporan->kasusDenda($b, $periode),
            ] : null,
        ]);
    }
}
