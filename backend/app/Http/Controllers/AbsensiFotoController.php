<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Attendance;
use App\Services\LayananAbsensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Foto bukti izin/sakit. Hanya pemilik absensi, Koordinator Admin, dan Super Admin yang boleh melihat.
 * Di Supabase Storage dialihkan ke URL bertanda tangan berumur pendek; di disk lokal dialirkan langsung.
 */
class AbsensiFotoController extends Controller
{
    public function __invoke(Request $request, Attendance $attendance): Response
    {
        $user = $request->user();
        abort_unless(
            $attendance->user_id === $user->id || in_array($user->role, [Role::Admin, Role::SuperAdmin], true),
            403,
            'Anda tidak berhak melihat foto ini.',
        );
        abort_if($attendance->foto_path === null, 404);

        $disk = Storage::disk(LayananAbsensi::disk());
        abort_unless($disk->exists($attendance->foto_path), 404, 'Foto tidak ditemukan.');

        if (config('filesystems.disks.'.LayananAbsensi::disk().'.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($attendance->foto_path, now()->addMinutes((int) config('sipermon.umur_url_unduhan_menit'))));
        }

        return $disk->response($attendance->foto_path, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
