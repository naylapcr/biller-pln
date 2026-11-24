<?php

namespace App\Http\Middleware;

use App\Enums\StatusBatch;
use App\Models\Period;
use App\Models\UploadBatch;
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
        $user = $request->user()?->loadMissing('biller:id,nama,login_acmt,kode_petugas_acmt');

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
                    'biller' => $user->biller ? [
                        'id' => $user->biller->id,
                        'login_acmt' => $user->biller->login_acmt,
                        'kode_petugas' => $user->biller->kode_petugas_acmt,
                    ] : null,
                ] : null,
            ],
            // Angka kecil di menu: batch yang menunggu commit.
            'navBadges' => fn () => $user?->isAdmin() ? (object) [
                'unggah' => UploadBatch::query()->where('status', StatusBatch::SiapCommit->value)
                    ->whereIn('period_id', Period::query()->terbuka()->select('id'))->count(),
            ] : (object) [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
