<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('biller');

        return Inertia::render('Profile/Edit', [
            'profil' => [
                'username_nip' => $user->username_nip,
                'nama' => $user->nama,
                'role_label' => $user->role->label(),
                'biller' => $user->biller ? [
                    'login_acmt' => $user->biller->login_acmt,
                    'kode_petugas' => $user->biller->kode_petugas_acmt,
                    'zona_wilayah' => $user->biller->zona_wilayah,
                ] : null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $sebelum = ['nama' => $user->nama];
        $user->update($request->validated());
        Audit::catat('ubah_profil', $user, $sebelum, ['nama' => $user->nama]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);
        Audit::catat('ubah_kata_sandi', $request->user());

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }
}
