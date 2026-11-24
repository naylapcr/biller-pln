<?php

namespace App\Http\Controllers;

use App\Enums\StatusAbsensi;
use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Attendance;
use App\Models\Period;
use App\Services\LayananAbsensi;
use App\Services\ParameterSkor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Biller mengisi absensi hariannya sendiri dan melihat rekap kehadirannya. */
class AbsensiSayaController extends Controller
{
    use PilihPeriode;

    public function __construct(private readonly LayananAbsensi $absensi) {}

    public function index(Request $request, ParameterSkor $parameter): Response
    {
        $biller = $request->user()->biller;
        abort_unless($biller, 403, 'Akun ini belum ditautkan ke data biller. Hubungi Super Admin.');

        $hariIni = LayananAbsensi::hariIni();
        $periode = $request->filled('periode') ? $this->periode($request) : (Period::untukTanggal($hariIni) ?? $this->periode($request));
        $aturan = $periode ? $parameter->untuk($periode) : null;
        $absenHariIni = Attendance::query()->where('biller_id', $biller->id)->whereDate('tanggal', $hariIni->toDateString())->first();

        return Inertia::render('Saya/Absensi', [
            'hariIni' => [
                'tanggal' => $hariIni->toDateString(),
                'hari_kerja' => $periode ? in_array($hariIni->toDateString(), $this->absensi->hariKerja($periode, $aturan), true) : true,
                'absen' => $absenHariIni ? [
                    'status' => $absenHariIni->status->value,
                    'keterangan' => $absenHariIni->keterangan,
                    'sumber' => $absenHariIni->sumber,
                    'waktu' => $absenHariIni->updated_at?->toIso8601String(),
                ] : null,
                'periode' => Period::untukTanggal($hariIni)?->kode,
            ],
            'periode' => $periode ? $this->infoPeriode($periode) + [
                'absensi_mulai' => $periode->absensi_mulai?->toDateString(),
                'absensi_selesai' => $periode->absensi_selesai?->toDateString(),
            ] : null,
            'periodeOptions' => $this->opsiPeriode(),
            'harian' => $periode ? $this->absensi->harian($periode, $biller) : [],
            'rekap' => $periode ? $this->absensi->rekap($periode, [$biller->id], $aturan)->get($biller->id) : null,
            'dendaPerAlpa' => (float) ($aturan['denda_alpa_per_hari'] ?? 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(StatusAbsensi::class)->except([StatusAbsensi::Alpa])],
            'keterangan' => ['nullable', 'string', 'max:300'],
        ]);

        $absen = $this->absensi->absenSendiri($request->user(), StatusAbsensi::from($data['status']), $data['keterangan'] ?? null);

        return back()->with('success', "Absensi hari ini tercatat: {$absen->status->label()}.");
    }
}
