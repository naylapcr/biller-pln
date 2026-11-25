<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit;
use App\Services\LayananAbsensi;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Koreksi Absensi (03.5, Super Admin): satu-satunya peran yang bisa mengubah absensi Biller dan
 * Koordinator. Alasan wajib dan tercatat di audit trail sebagai CORRECT_ATT.
 */
class KoreksiAbsensiController extends Controller
{
    public function __construct(private readonly LayananAbsensi $absensi) {}

    public function index(Request $request): Response
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $tanggal = $request->filled('tanggal')
            ? CarbonImmutable::parse($request->string('tanggal')->toString(), config('app.timezone'))
            : LayananAbsensi::hariIni();

        return Inertia::render('SuperAdmin/KoreksiAbsensi', [
            'tanggal' => $tanggal->toDateString(),
            'hariIni' => LayananAbsensi::hariIni()->toDateString(),
            'harian' => $this->absensi->harian($tanggal, [Role::Admin, Role::Biller]),
            'statusOptions' => StatusAbsensi::options(),
            'riwayat' => $this->riwayat(),
        ]);
    }

    /** Riwayat koreksi dari audit trail, lengkap dengan status sebelum dan sesudah. */
    private function riwayat(): Collection
    {
        $log = AuditLog::query()->where('aksi', Audit::CORRECT_ATT)->with('user:id,nama')->latest('id')->limit(30)->get();
        $petugas = User::query()->whereIn('username_nip', $log->map(fn (AuditLog $l) => $l->sesudah['user'] ?? null)->filter()->unique())
            ->get(['nama', 'username_nip', 'role'])->keyBy('username_nip');

        return $log->map(fn (AuditLog $l) => [
            'id' => $l->id,
            'tanggal' => $l->sesudah['tanggal'] ?? null,
            'petugas' => $petugas->get($l->sesudah['user'] ?? '')?->nama ?? ($l->sesudah['user'] ?? null),
            'peran' => $petugas->get($l->sesudah['user'] ?? '')?->role->label(),
            'sebelum' => ($l->sebelum['status'] ?? 'belum_absen') === 'belum_absen' ? 'belum' : $l->sebelum['status'],
            'status' => $l->sesudah['status'] ?? null,
            'alasan' => $l->sesudah['alasan'] ?? null,
            'oleh' => $l->user?->nama,
            'waktu' => $l->created_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(StatusAbsensi::class)],
            'alasan_diubah' => ['required', 'string', 'min:5', 'max:300'],
            'keterangan' => ['nullable', 'string', 'max:300'],
        ], ['alasan_diubah.required' => 'Alasan koreksi wajib diisi.']);

        $target = User::findOrFail($data['user_id']);
        $this->absensi->koreksi(
            $request->user(),
            $target,
            CarbonImmutable::parse($data['tanggal'], config('app.timezone')),
            StatusAbsensi::from($data['status']),
            $data['alasan_diubah'],
            $data['keterangan'] ?? null,
        );

        return back()->with('success', "Absensi {$target->nama} tanggal {$data['tanggal']} dikoreksi.");
    }
}
