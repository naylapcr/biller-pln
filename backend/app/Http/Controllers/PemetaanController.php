<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Biller;
use App\Models\BillerAlias;
use App\Services\LayananPemetaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemetaan login ACMT ke biller.
 *  - Koordinator (02.5): baca saja, tindak lanjut "Laporkan ke Super Admin".
 *  - Super Admin (03.3): petakan login ke biller lewat alias, lalu skor periode terbuka dihitung ulang.
 */
class PemetaanController extends Controller
{
    use PilihPeriode;

    public function __construct(private readonly LayananPemetaan $pemetaan) {}

    public function index(Request $request): Response
    {
        $periode = $this->periode($request);

        return Inertia::render('Koordinator/PemetaanPetugas', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'statistik' => $periode ? $this->pemetaan->statistik($periode) : null,
            'belum' => $periode ? $this->pemetaan->belumTerpetakan($periode) : [],
        ]);
    }

    public function alias(Request $request): Response
    {
        $periode = $this->periode($request);

        return Inertia::render('SuperAdmin/PemetaanAlias', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'belum' => $periode ? $this->pemetaan->belumTerpetakan($periode) : [],
            'billerOptions' => Biller::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'login_acmt', 'kode_petugas_acmt'])
                ->map(fn (Biller $b) => ['value' => $b->id, 'label' => "{$b->nama} · {$b->login_acmt} ({$b->kode_petugas_acmt})"]),
            'aliases' => BillerAlias::query()->with('biller:id,nama,kode_petugas_acmt,is_active')->latest('id')->get()
                ->map(fn (BillerAlias $a) => [
                    'id' => $a->id,
                    'login_acmt' => $a->login_acmt,
                    'biller' => $a->biller?->nama,
                    'kode_petugas' => $a->biller?->kode_petugas_acmt,
                    'berlaku_mulai' => $a->berlaku_mulai?->toDateString(),
                    'aktif' => (bool) $a->biller?->is_active,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['login_acmt' => strtolower(trim((string) $request->input('login_acmt')))]);
        $data = $request->validate([
            'login_acmt' => ['required', 'string', 'max:60',
                Rule::unique('biller_aliases', 'login_acmt'), Rule::unique('billers', 'login_acmt')],
            'biller_id' => ['required', 'integer', Rule::exists('billers', 'id')],
            'berlaku_mulai' => ['nullable', 'date'],
        ], ['login_acmt.unique' => 'Login ini sudah terpetakan.']);

        $alias = $this->pemetaan->tambahAlias(Biller::findOrFail($data['biller_id']), $data['login_acmt'], $data['berlaku_mulai'] ?? null, $request->user());

        return back()->with('success', "Login {$alias->login_acmt} dipetakan. Skor periode terbuka dihitung ulang.");
    }
}
