<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang masih memakai kata sandi bawaan atau kata sandi dari Super Admin wajib menggantinya
 * sebelum memakai fitur lain. Halaman Profil (ganti kata sandi, foto) dan logout tetap bisa dibuka.
 */
class WajibGantiSandi
{
    private const BOLEH = ['profil.edit', 'profil.update', 'profil.password', 'profil.foto', 'profil.foto.hapus', 'pengguna.foto', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->wajib_ganti_sandi && ! $request->routeIs(...self::BOLEH)) {
            return redirect()->route('profil.edit')
                ->with('error', 'Demi keamanan, ganti kata sandi bawaan Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
