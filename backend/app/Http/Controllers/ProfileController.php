<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Services\Audit;
use App\Services\LayananAbsensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Profil Saya (01.5, 02.9, 03.7): identitas, sesi, ubah nama, ganti kata sandi, 3 aktivitas terakhir. */
class ProfileController extends Controller
{
    public function edit(Request $request, LayananAbsensi $absensi): Response
    {
        $user = $request->user()->load('biller');
        $loginTerakhir = AuditLog::query()->where('user_id', $user->id)->where('aksi', Audit::LOGIN)->latest('id')->first();
        $sisaMenit = null;
        if ($loginTerakhir?->created_at) {
            $sisaMenit = max(0, (int) config('session.lifetime') - (int) $loginTerakhir->created_at->diffInMinutes(now()));
        }
        $absenHariIni = $user->wajibAbsen() ? $absensi->hariIniMilik($user) : null;

        return Inertia::render('Umum/Profil', [
            'profil' => [
                'username_nip' => $user->username_nip,
                'nama' => $user->nama,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'biller' => $user->biller ? [
                    'nip' => $user->biller->nip,
                    'login_acmt' => $user->biller->login_acmt,
                    'kode_petugas' => $user->biller->kode_petugas_acmt,
                    'zona_wilayah' => $user->biller->zona_wilayah,
                ] : null,
                'absensi_hari_ini' => $user->wajibAbsen() ? ($absenHariIni ? LayananAbsensi::baris($absenHariIni) : ['status' => 'belum']) : null,
            ],
            'sesi' => [
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'sisa_menit' => $sisaMenit,
                'lama_sesi_jam' => intdiv((int) config('session.lifetime'), 60),
                'ip' => $loginTerakhir?->ip ?? $request->ip(),
                'perangkat' => $loginTerakhir?->user_agent ?? $request->userAgent(),
            ],
            'aktivitas' => AuditLog::query()->where('user_id', $user->id)->latest('id')->limit(3)->get()
                ->map(fn (AuditLog $l) => [
                    'id' => $l->id,
                    'aksi' => $l->aksi,
                    'objek' => trim(($l->objek_type ?? '').' '.($l->objek_id ?? '')),
                    'waktu' => $l->created_at?->toIso8601String(),
                    'ip' => $l->ip,
                ]),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $sebelum = ['nama' => $user->nama];
        $user->update($request->validated());
        Audit::catat(Audit::UPDATE_PROFILE, $user, $sebelum, ['nama' => $user->nama]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);
        Audit::catat(Audit::CHANGE_PASSWORD, $request->user());

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }
}
