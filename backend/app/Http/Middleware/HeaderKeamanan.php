<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons web:
 *  - Halaman milik pengguna yang login tidak disimpan di cache browser (no-store), sehingga setelah logout
 *    tombol Back atau riwayat browser tidak bisa menampilkan data lama.
 *  - Halaman tidak boleh dimuat di dalam iframe situs lain (clickjacking), jenis berkas tidak ditebak browser,
 *    dan alamat lengkap tidak dikirim ke situs lain lewat Referer.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() || $request->routeIs('login', 'logout')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
