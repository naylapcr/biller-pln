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
 * untuk hari ini menurut jam server (WIB). Hadir = absen masuk + absen pulang, keduanya dalam radius kantor;
 * izin/sakit wajib keterangan dan lampiran. Kehadiran biller ikut skor lewat parameter bobot_kehadiran.
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
            'aturanAbsensi' => LayananAbsensi::aturan(),
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
            'akurasi' => ['nullable', 'numeric', 'min:0'],
            'foto' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), ['izin', 'sakit'], true)),
                'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) config('sipermon.absensi.foto_maks_kb'),
            ],
        ], [
            'keterangan.required' => 'Tulis keterangan untuk izin atau sakit.',
            'foto.required' => 'Lampirkan foto atau PDF bukti (mis. surat dokter) untuk izin atau sakit.',
            'foto.mimes' => 'Lampiran harus foto (JPG, PNG, WEBP) atau PDF.',
            'foto.max' => 'Ukuran lampiran maksimal '.((int) config('sipermon.absensi.foto_maks_kb') / 1024).' MB.',
        ]);

        $absen = $this->absensi->absenSendiri(
            $request->user(),
            StatusAbsensi::from($data['status']),
            $data['keterangan'] ?? null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            isset($data['akurasi']) ? (float) $data['akurasi'] : null,
            $request->file('foto'),
        );

        $jam = $absen->absen_at->timezone(config('app.timezone'))->format('H.i');
        $telat = $absen->terlambat_menit > 0 ? " (terlambat {$absen->terlambat_menit} menit)" : '';

        return back()->with('success', "Absensi hari ini tercatat: {$absen->status->label()} pukul {$jam} WIB{$telat}.");
    }

    public function pulang(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'akurasi' => ['nullable', 'numeric', 'min:0'],
        ]);

        $absen = $this->absensi->absenPulang(
            $request->user(),
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            isset($data['akurasi']) ? (float) $data['akurasi'] : null,
        );
        $jam = $absen->pulang_at->timezone(config('app.timezone'))->format('H.i');
        $cepat = $absen->pulang_cepat_menit > 0 ? " ({$absen->pulang_cepat_menit} menit lebih awal dari jam pulang)" : '';

        return back()->with('success', "Absen pulang tercatat pukul {$jam} WIB{$cepat}.");
    }
}
