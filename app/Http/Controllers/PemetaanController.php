<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Biller;
use App\Services\LayananPemetaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Login petugas ACMT yang belum terpetakan ke biller, dan pemetaannya (alias, khusus Super Admin). */
class PemetaanController extends Controller
{
    use PilihPeriode;

    public function index(Request $request, LayananPemetaan $pemetaan): Response
    {
        $periode = $this->periode($request);

        return Inertia::render('Pemetaan/Index', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'belum' => $periode ? $pemetaan->belumTerpetakan($periode) : [],
            'billerOptions' => Biller::query()->orderBy('nama')->get(['id', 'nama', 'login_acmt', 'kode_petugas_acmt'])
                ->map(fn (Biller $b) => ['value' => $b->id, 'label' => "{$b->nama} · {$b->login_acmt} ({$b->kode_petugas_acmt})"]),
            'bisaMemetakan' => $request->user()->hasRole('super_admin'),
        ]);
    }

    public function store(Request $request, LayananPemetaan $pemetaan): RedirectResponse
    {
        $data = $request->validate([
            'login_acmt' => ['required', 'string', 'max:60',
                Rule::unique('biller_aliases', 'login_acmt'), Rule::unique('billers', 'login_acmt')],
            'biller_id' => ['required', 'integer', Rule::exists('billers', 'id')],
            'berlaku_mulai' => ['nullable', 'date'],
        ], ['login_acmt.unique' => 'Login ini sudah terpetakan.']);

        $alias = $pemetaan->tambahAlias(Biller::findOrFail($data['biller_id']), strtolower($data['login_acmt']), $data['berlaku_mulai'] ?? null, $request->user());

        return back()->with('success', "Login {$alias->login_acmt} dipetakan. Skor periode terbuka dihitung ulang.");
    }
}
