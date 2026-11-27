<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Biller;
use App\Models\Period;
use App\Services\LayananAbsensi;
use App\Services\LayananPemetaan;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('biller:id,nama,nip,login_acmt,kode_petugas_acmt,zona_wilayah');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'username_nip' => $user->username_nip,
                    'nama' => $user->nama,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'foto_url' => $user->foto_path ? route('pengguna.foto', ['user' => $user->id, 'v' => substr(md5($user->foto_path), 0, 8)]) : null,
                    'wajib_ganti_sandi' => (bool) $user->wajib_ganti_sandi,
                    'biller' => $user->biller ? [
                        'id' => $user->biller->id,
                        'nip' => $user->biller->nip,
                        'login_acmt' => $user->biller->login_acmt,
                        'kode_petugas' => $user->biller->kode_petugas_acmt,
                        'zona_wilayah' => $user->biller->zona_wilayah,
                    ] : null,
                ] : null,
            ],
            // Kartu status sidebar (PRD 10.1): BLTH aktif + satu baris info khas peran.
            'kartuStatus' => fn () => $user ? $this->kartuStatus($user) : null,
            // Badge menu Pemetaan Petugas = login ACMT yang belum terpetakan di periode terbuka.
            'navBadges' => fn () => $user?->isAdmin() ? (object) [
                'pemetaan' => ($p = Period::query()->terbuka()->orderByDesc('kode')->first())
                    ? app(LayananPemetaan::class)->belumTerpetakan($p)->count() : 0,
            ] : (object) [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    private function kartuStatus($user): array
    {
        $periode = Period::query()->terbuka()->orderByDesc('kode')->first() ?? Period::query()->orderByDesc('kode')->first();
        $absen = $user->wajibAbsen() ? app(LayananAbsensi::class)->hariIniMilik($user) : null;

        return [
            'periode' => $periode ? ['kode' => $periode->kode, 'terbuka' => $periode->terbuka()] : null,
            'absensi' => $user->wajibAbsen() ? [
                'status' => $absen?->status->value ?? 'belum',
                'jam' => $absen?->absen_at?->toIso8601String(),
            ] : null,
            'wilayah' => $user->biller?->zona_wilayah,
            'biller_aktif' => $user->role === Role::Admin ? Biller::query()->aktif()->count() : null,
        ];
    }
}
