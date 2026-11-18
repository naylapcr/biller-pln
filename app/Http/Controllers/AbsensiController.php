<?php

namespace App\Http\Controllers;

use App\Enums\StatusAbsensi;
use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Attendance;
use App\Models\Biller;
use App\Services\LayananAbsensi;
use App\Services\ParameterSkor;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Rekap absensi semua biller dan koreksi oleh Koordinator Admin / Super Admin. */
class AbsensiController extends Controller
{
    use PilihPeriode;

    public function __construct(private readonly LayananAbsensi $absensi) {}

    public function index(Request $request, ParameterSkor $parameter): Response
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $periode = $this->periode($request);
        $tanggal = $request->filled('tanggal') ? CarbonImmutable::parse($request->string('tanggal')->toString()) : LayananAbsensi::hariIni();
        $aturan = $periode ? $parameter->untuk($periode) : null;

        $billers = Biller::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'login_acmt', 'kode_petugas_acmt']);
        $rekap = $periode ? $this->absensi->rekap($periode, $billers->pluck('id'), $aturan) : collect();
        $tanggalIni = Attendance::query()->whereDate('tanggal', $tanggal->toDateString())->with('pencatat:id,nama')->get()->keyBy('biller_id');
        $hariKerja = $periode && in_array($tanggal->toDateString(), $this->absensi->hariKerja($periode, $aturan), true);

        return Inertia::render('Absensi/Index', [
            'periode' => $periode ? $this->infoPeriode($periode) + [
                'absensi_mulai' => $periode->absensi_mulai?->toDateString(),
                'absensi_selesai' => $periode->absensi_selesai?->toDateString(),
            ] : null,
            'periodeOptions' => $this->opsiPeriode(),
            'tanggal' => $tanggal->toDateString(),
            'hariIni' => LayananAbsensi::hariIni()->toDateString(),
            'tanggalHariKerja' => $hariKerja,
            'dendaPerAlpa' => (float) ($aturan['denda_alpa_per_hari'] ?? 0),
            'statusOptions' => StatusAbsensi::options(),
            'billers' => $billers->map(function (Biller $b) use ($rekap, $tanggalIni, $tanggal, $hariKerja) {
                $a = $tanggalIni->get($b->id);

                return [
                    'id' => $b->id,
                    'nama' => $b->nama,
                    'login_acmt' => $b->login_acmt,
                    'kode_petugas' => $b->kode_petugas_acmt,
                    'rekap' => $rekap->get($b->id),
                    'tanggal' => [
                        'status' => $a?->status->value ?? ($hariKerja && $tanggal->lessThan(LayananAbsensi::hariIni()) ? 'alpa' : null),
                        'tercatat' => $a !== null,
                        'keterangan' => $a?->keterangan,
                        'sumber' => $a?->sumber,
                        'pencatat' => $a?->pencatat?->nama,
                        'waktu' => $a?->updated_at?->toIso8601String(),
                    ],
                ];
            }),
        ]);
    }

    public function update(Request $request, Biller $biller): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(StatusAbsensi::class)],
            'keterangan' => ['nullable', 'string', 'max:300'],
        ]);

        $this->absensi->atur(
            $request->user(),
            $biller,
            CarbonImmutable::parse($data['tanggal']),
            isset($data['status']) ? StatusAbsensi::from($data['status']) : null,
            $data['keterangan'] ?? null,
        );

        return back()->with('success', "Absensi {$biller->nama} tanggal {$data['tanggal']} disimpan.");
    }
}
