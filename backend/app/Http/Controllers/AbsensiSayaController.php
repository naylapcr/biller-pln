<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Services\LayananAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absensi Saya (01.2 Biller, 02.6 Koordinator). Absen sekali sehari atas nama akun yang login,
 * untuk hari ini menurut jam server (WIB). Tidak memengaruhi skor.
 */
class AbsensiSayaController extends Controller
{
    public function __construct(private readonly LayananAbsensi $absensi) {}

    public function index(Request $request): Response
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);
        $user = $request->user();
        $hariIni = LayananAbsensi::hariIni();
        $bulan = $request->filled('bulan') ? CarbonImmutable::createFromFormat('Y-m', $request->string('bulan')->toString())->startOfMonth() : $hariIni->startOfMonth();
        $absen = $this->absensi->hariIniMilik($user);

        return Inertia::render($user->role === Role::Biller ? 'Biller/AbsensiSaya' : 'Koordinator/AbsensiSaya', [
            'hariIni' => [
                'tanggal' => $hariIni->toDateString(),
                'jam_server' => now()->toIso8601String(),
                'hari_kerja' => LayananAbsensi::hariKerja($hariIni),
                'absen' => $absen ? LayananAbsensi::baris($absen) : null,
            ],
            'bulan' => $bulan->format('Y-m'),
            'ringkasan' => $this->absensi->ringkasan($user, $bulan),
            'riwayat' => $this->absensi->riwayat($user, $bulan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(StatusAbsensi::class)->except([StatusAbsensi::Alpa])],
            'keterangan' => ['nullable', 'string', 'max:300', Rule::requiredIf(fn () => in_array($request->input('status'), ['izin', 'sakit'], true))],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ], ['keterangan.required' => 'Tulis keterangan untuk izin atau sakit.']);

        $absen = $this->absensi->absenSendiri(
            $request->user(),
            StatusAbsensi::from($data['status']),
            $data['keterangan'] ?? null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );

        return back()->with('success', "Absensi hari ini tercatat: {$absen->status->label()} pukul {$absen->absen_at->timezone(config('app.timezone'))->format('H.i')} WIB.");
    }
}
