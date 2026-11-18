<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\StatusBatch;
use App\Http\Controllers\Controller;
use App\Models\MonthlyScore;
use App\Models\Period;
use App\Models\ScoringRule;
use App\Models\UploadBatch;
use App\Services\Audit;
use App\Services\LayananPeriode;
use App\Services\ParameterSkor;
use App\Services\PenghitungSkor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Periode (BLTH): buka, parameter skor, hitung ulang, dan tutup (Super Admin). */
class PeriodeController extends Controller
{
    public function __construct(
        private readonly LayananPeriode $layanan,
        private readonly ParameterSkor $parameter,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Pengaturan/Periode/Index', [
            'periode' => Period::query()->with('penutup:id,nama')->orderByDesc('kode')->get()->map(fn (Period $p) => [
                'kode' => $p->kode,
                'nama' => $p->nama(),
                'status' => $p->status->value,
                'terbuka' => $p->terbuka(),
                'ditutup_at' => $p->ditutup_at?->toIso8601String(),
                'penutup' => $p->penutup?->nama,
                'batch_committed' => UploadBatch::query()->committed()->where('period_id', $p->id)->pluck('modul')->map->label()->values(),
                'jumlah_skor' => MonthlyScore::query()->where('period_id', $p->id)->count(),
                'parameter_kosong' => $this->parameter->kosongWajib($p),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'regex:/^20\d{2}(0[1-9]|1[0-2])$/', Rule::unique('periods', 'kode')],
        ], ['kode.regex' => 'Kode periode berformat BLTH, contoh 202611.', 'kode.unique' => 'Periode ini sudah ada.']);

        $periode = $this->layanan->buka($data['kode'], $request->user());

        return to_route('pengaturan.periode.show', $periode->kode)->with('success', "Periode {$periode->kode} dibuka. Parameter disalin dari periode sebelumnya.");
    }

    public function show(string $kode): Response
    {
        $periode = Period::where('kode', $kode)->firstOrFail();
        $rules = ScoringRule::query()->where('period_id', $periode->id)->with('pengubah:id,nama')->get()->keyBy('kunci');

        return Inertia::render('Pengaturan/Periode/Show', [
            'periode' => [
                'kode' => $periode->kode,
                'nama' => $periode->nama(),
                'terbuka' => $periode->terbuka(),
                'ditutup_at' => $periode->ditutup_at?->toIso8601String(),
                'absensi_mulai' => $periode->absensi_mulai?->toDateString(),
                'absensi_selesai' => $periode->absensi_selesai?->toDateString(),
            ],
            'parameter' => $this->parameter->untuk($periode),
            'bawaan' => ParameterSkor::bawaan(),
            'wajib' => config('sipermon.parameter_wajib'),
            'kosong' => $this->parameter->kosongWajib($periode),
            'diubah' => $rules->filter(fn ($r) => $r->diubah_oleh)->map(fn ($r) => [
                'oleh' => $r->pengubah?->nama, 'waktu' => $r->updated_at?->toIso8601String(),
            ]),
            'batchMenunggu' => UploadBatch::query()->where('period_id', $periode->id)
                ->whereIn('status', [StatusBatch::Diproses->value, StatusBatch::SiapCommit->value])->count(),
        ]);
    }

    public function updateRules(Request $request, string $kode): RedirectResponse
    {
        $periode = Period::where('kode', $kode)->firstOrFail();
        $data = $request->validate([
            'w_stan' => ['required', 'numeric', 'min:0'],
            'w_eviden' => ['required', 'numeric', 'min:0'],
            'mode_agregasi' => ['required', Rule::in(['jumlah', 'rata_rata_modul'])],
            'bonus_min_j' => ['required', 'numeric', 'min:0'],
            'bonus_max_j' => ['required', 'numeric', 'gte:bonus_min_j'],
            'bonus_dasar_j' => ['nullable', 'numeric'],
            'poin_per_plg_j' => ['nullable', 'numeric'],
            'denda_bu_per_kasus' => ['required', 'numeric', 'min:0'],
            'denda_ap2t_tingkat' => ['required', 'array'],
            'denda_ap2t_tingkat.min' => ['required', 'numeric', 'min:0'],
            'denda_ap2t_tingkat.max' => ['required', 'numeric', 'gte:denda_ap2t_tingkat.min'],
            'denda_ap2t_tingkat.tingkat' => ['nullable', 'array'],
            'denda_ap2t_tingkat.tingkat.*' => ['numeric', 'min:0'],
            'kdbaca_normal' => ['required', 'array', 'min:1'],
            'kdbaca_normal.*' => ['string', 'max:5'],
            'denda_alpa_per_hari' => ['required', 'numeric', 'min:0'],
            'absensi_hari_kerja' => ['required', 'array', 'min:1'],
            'absensi_hari_kerja.*' => ['integer', 'between:1,7'],
            'absensi_hari_libur' => ['nullable', 'array'],
            'absensi_hari_libur.*' => ['date_format:Y-m-d'],
            'absensi_mulai' => ['required', 'date_format:Y-m-d'],
            'absensi_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:absensi_mulai'],
            'ambang_evaluasi' => ['required', 'numeric'],
            'jumlah_top' => ['required', 'integer', 'between:1,25'],
            'jumlah_bottom' => ['required', 'integer', 'between:1,25'],
        ]);

        $angka = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $data = array_merge($data, [
            'w_stan' => $angka($data['w_stan']),
            'w_eviden' => $angka($data['w_eviden']),
            'bonus_min_j' => $angka($data['bonus_min_j']),
            'bonus_max_j' => $angka($data['bonus_max_j']),
            'bonus_dasar_j' => $angka($data['bonus_dasar_j'] ?? null),
            'poin_per_plg_j' => $angka($data['poin_per_plg_j'] ?? null),
            'denda_bu_per_kasus' => $angka($data['denda_bu_per_kasus']),
            'ambang_evaluasi' => $angka($data['ambang_evaluasi']),
            'denda_alpa_per_hari' => $angka($data['denda_alpa_per_hari']),
            'absensi_hari_kerja' => array_values(array_unique(array_map('intval', $data['absensi_hari_kerja']))),
            'absensi_hari_libur' => array_values(array_unique($data['absensi_hari_libur'] ?? [])),
            'jumlah_top' => (int) $data['jumlah_top'],
            'jumlah_bottom' => (int) $data['jumlah_bottom'],
            'kdbaca_normal' => array_values(array_unique(array_map(fn ($k) => strtoupper(trim($k)), $data['kdbaca_normal']))),
            'denda_ap2t_tingkat' => [
                'min' => (float) $data['denda_ap2t_tingkat']['min'],
                'max' => (float) $data['denda_ap2t_tingkat']['max'],
                'tingkat' => (object) array_map('floatval', $data['denda_ap2t_tingkat']['tingkat'] ?? []),
            ],
        ]);

        // Rentang tanggal absensi disimpan di periode, bukan di scoring_rules.
        $rentang = ['absensi_mulai' => $data['absensi_mulai'], 'absensi_selesai' => $data['absensi_selesai']];
        unset($data['absensi_mulai'], $data['absensi_selesai']);
        $rentangLama = [
            'absensi_mulai' => $periode->absensi_mulai?->toDateString(),
            'absensi_selesai' => $periode->absensi_selesai?->toDateString(),
        ];
        if ($rentang !== $rentangLama) {
            if (! $periode->terbuka()) {
                return back()->with('error', "Periode {$periode->kode} sudah ditutup; rentang absensi terkunci.");
            }
            $periode->update($rentang);
            Audit::catat('ubah_rentang_absensi', $periode, $rentangLama, $rentang);
        }

        $this->parameter->ubah($periode, json_decode(json_encode($data), true), $request->user());
        if ($rentang !== $rentangLama) {
            app(PenghitungSkor::class)->hitung($periode);
        }

        return back()->with('success', 'Parameter disimpan dan skor periode dihitung ulang.');
    }

    public function recalculate(Request $request, string $kode): RedirectResponse
    {
        $this->layanan->hitungUlang(Period::where('kode', $kode)->firstOrFail(), $request->user());

        return back()->with('success', 'Skor periode dihitung ulang.');
    }

    public function close(Request $request, string $kode): RedirectResponse
    {
        try {
            $this->layanan->tutup(Period::where('kode', $kode)->firstOrFail(), $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Periode {$kode} ditutup. Skor final tersimpan dan data terkunci.");
    }
}
