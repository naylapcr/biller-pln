<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Biller;
use App\Services\LaporanKinerja;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Direktori Biller (02.2): tabel semua biller dan drawer profil biller (skor, modul, tren, histori WO). */
class DirektoriBillerController extends Controller
{
    use PilihPeriode;

    public function __invoke(Request $request, LaporanKinerja $laporan): Response
    {
        $request->validate(['cari' => ['nullable', 'string', 'max:60'], 'detail' => ['nullable', 'integer']]);
        $periode = $this->periode($request);
        $detail = $request->integer('detail') ?: null;

        return Inertia::render('Koordinator/DirektoriBiller', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'filters' => (object) $request->only(['cari']),
            'billers' => $periode ? $laporan->semuaBiller($periode, $request->string('cari')->toString() ?: null) : [],
            // Isi drawer, dimuat lewat partial reload saat baris diklik.
            'detail' => fn () => $periode && $detail && ($b = Biller::find($detail)) ? [
                'skor' => $laporan->skorBiller($b, $periode),
                'tren' => $laporan->tren($b, 4, $periode),
                'kesalahan' => $laporan->kesalahan($b, $periode),
                'histori' => $laporan->historiWo($b, $periode)['pascabayar']->take(8)->values(),
            ] : null,
        ]);
    }
}
