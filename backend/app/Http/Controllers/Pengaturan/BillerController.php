<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\Biller;
use App\Models\BillerAlias;
use App\Services\Audit;
use App\Services\LayananPemetaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Master biller beserta login ACMT, kode petugas, dan alias (Super Admin). */
class BillerController extends Controller
{
    public function __construct(private readonly LayananPemetaan $pemetaan) {}

    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        return Inertia::render('Pengaturan/Billers/Index', [
            'billers' => Biller::query()
                ->with(['aliases' => fn ($a) => $a->orderBy('login_acmt'), 'user:id,biller_id,username_nip'])
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama', 'like', "%{$q}%")
                    ->orWhere('login_acmt', 'like', '%'.strtolower($q).'%')
                    ->orWhere('kode_petugas_acmt', 'like', '%'.strtoupper($q).'%')))
                ->orderBy('nama')
                ->get()
                ->map(fn (Biller $b) => [
                    'id' => $b->id,
                    'nip' => $b->nip,
                    'nama' => $b->nama,
                    'zona_wilayah' => $b->zona_wilayah,
                    'kode_petugas_acmt' => $b->kode_petugas_acmt,
                    'login_acmt' => $b->login_acmt,
                    'is_active' => $b->is_active,
                    'akun' => $b->user?->username_nip,
                    'aliases' => $b->aliases->map(fn (BillerAlias $a) => [
                        'id' => $a->id, 'login_acmt' => $a->login_acmt, 'berlaku_mulai' => $a->berlaku_mulai?->toDateString(),
                    ]),
                ]),
            'filters' => (object) $request->only(['q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $biller = Biller::create($this->validasi($request));
        $this->pemetaan->isiBiller($biller->login_acmt, $biller->id);
        Audit::catat('buat_biller', $biller, null, $biller->only(['nama', 'login_acmt', 'kode_petugas_acmt']));

        return back()->with('success', "Biller {$biller->nama} ditambahkan.");
    }

    public function update(Request $request, Biller $biller): RedirectResponse
    {
        $sebelum = $biller->only(['nip', 'nama', 'zona_wilayah', 'login_acmt', 'kode_petugas_acmt', 'is_active']);
        $biller->update($this->validasi($request, $biller));

        if ($sebelum['login_acmt'] !== $biller->login_acmt) {
            $this->pemetaan->isiBiller($sebelum['login_acmt'], null);
            $this->pemetaan->isiBiller($biller->login_acmt, $biller->id);
        }
        Audit::catat('ubah_biller', $biller, $sebelum, $biller->only(array_keys($sebelum)));

        return back()->with('success', "Biller {$biller->nama} diperbarui.");
    }

    public function destroy(Biller $biller): RedirectResponse
    {
        if ($biller->user()->exists() || $biller->scores()->exists()) {
            return back()->with('error', 'Biller yang sudah punya akun atau skor tidak bisa dihapus. Nonaktifkan saja.');
        }
        $sebelum = $biller->only(['nama', 'login_acmt', 'kode_petugas_acmt']);
        $biller->delete();
        Audit::catat('hapus_biller', 'Biller', $sebelum, null);

        return back()->with('success', 'Biller dihapus.');
    }

    public function hapusAlias(BillerAlias $alias): RedirectResponse
    {
        $sebelum = $alias->only(['login_acmt', 'biller_id']);
        $alias->delete();
        $this->pemetaan->isiBiller($sebelum['login_acmt'], null);
        Audit::catat('hapus_alias', 'BillerAlias', $sebelum, null);

        return back()->with('success', "Alias {$sebelum['login_acmt']} dihapus.");
    }

    private function validasi(Request $request, ?Biller $biller = null): array
    {
        $request->merge([
            'login_acmt' => strtolower(trim((string) $request->input('login_acmt'))),
            'kode_petugas_acmt' => strtoupper(trim((string) $request->input('kode_petugas_acmt'))),
        ]);

        return $request->validate([
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('billers', 'nip')->ignore($biller?->id)],
            'nama' => ['required', 'string', 'max:100'],
            'zona_wilayah' => ['nullable', 'string', 'max:100'],
            'kode_petugas_acmt' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('billers', 'kode_petugas_acmt')->ignore($biller?->id)],
            'login_acmt' => ['required', 'string', 'max:60', Rule::unique('billers', 'login_acmt')->ignore($biller?->id), Rule::unique('biller_aliases', 'login_acmt')],
            'is_active' => ['boolean'],
        ]);
    }
}
