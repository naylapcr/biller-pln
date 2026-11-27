<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\MonthlyScore;
use App\Models\Period;
use App\Models\ScoringRule;
use App\Models\UploadBatch;
use App\Services\AgregatorWorkOrder;
use App\Services\LaporanKinerja;
use App\Services\LayananPeriode;
use App\Services\ParameterSkor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periode & Parameter (03.4, Super Admin): bobot pascabayar, bonus Hari J dan jadwal prabayar,
 * ambang kategori, dan gerbang tutup periode. Periode final tampil terkunci (04.2).
 */
class PeriodeController extends Controller
{
    public function __construct(
        private readonly LayananPeriode $layanan,
        private readonly ParameterSkor $parameter,
    ) {}

    public function index(Request $request, LaporanKinerja $laporan): Response
    {
        $kode = $request->string('periode')->toString();
        $periode = ($kode !== '' ? Period::kode($kode) : null) ?? Period::bawaan();
        $rules = $periode ? ScoringRule::query()->where('period_id', $periode->id)->with('pengubah:id,nama')->get()->keyBy('kunci') : collect();
        $aturan = $periode ? $this->parameter->untuk($periode) : null;

        return Inertia::render('SuperAdmin/PeriodeParameter', [
            'daftar' => Period::query()->orderByDesc('kode')->get()->map(fn (Period $p) => [
                'kode' => $p->kode,
                'nama' => $p->nama(),
                'terbuka' => $p->terbuka(),
            ]),
            'periode' => $periode ? [
                'kode' => $periode->kode,
                'nama' => $periode->nama(),
                'terbuka' => $periode->terbuka(),
                'ditutup_at' => $periode->ditutup_at?->toIso8601String(),
                'penutup' => $periode->penutup()->value('nama'),
                'jadwal_prabayar' => array_map(fn ($t) => $t->toDateString(), AgregatorWorkOrder::jadwalPrabayar($periode, $aturan)),
                'batch_committed' => UploadBatch::query()->committed()->where('period_id', $periode->id)->get()
                    ->map(fn (UploadBatch $b) => $b->modul?->label())->filter()->values(),
                'jumlah_skor' => MonthlyScore::query()->where('period_id', $periode->id)->count(),
            ] : null,
            'parameter' => $aturan,
            'bawaan' => ParameterSkor::bawaan(),
            'kesiapan' => $periode ? $this->layanan->kesiapan($periode) : [],
            'ringkasanFinal' => $periode && ! $periode->terbuka() ? [
                'rata' => round((float) MonthlyScore::query()->where('period_id', $periode->id)->avg('final_score'), 2),
                'top' => $laporan->leaderboard($periode, 'top', 3),
                'kategori' => MonthlyScore::query()->where('period_id', $periode->id)->selectRaw('kategori, COUNT(*) as n')->groupBy('kategori')->pluck('n', 'kategori'),
                'batch' => UploadBatch::query()->committed()->where('period_id', $periode->id)->count(),
            ] : null,
            'diubah' => $rules->filter(fn ($r) => $r->diubah_oleh)->map(fn ($r) => [
                'oleh' => $r->pengubah?->nama, 'waktu' => $r->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'regex:/^20\d{2}(0[1-9]|1[0-2])$/', Rule::unique('periods', 'kode')],
        ], ['kode.regex' => 'Kode periode berformat BLTH, contoh 202611.', 'kode.unique' => 'Periode ini sudah ada.']);

        $periode = $this->layanan->buka($data['kode'], $request->user());

        return to_route('pengaturan.periode.index', ['periode' => $periode->kode])
            ->with('success', "Periode {$periode->kode} dibuka. Parameter disalin dari periode sebelumnya.");
    }

    public function updateRules(Request $request, string $kode): RedirectResponse
    {
        $periode = Period::where('kode', $kode)->firstOrFail();
        $data = $request->validate([
            'w_realisasi' => ['required', 'numeric', 'between:0,100'],
            'w_eviden' => ['required', 'numeric', 'between:0,100'],
            'w_kecepatan' => ['required', 'numeric', 'between:0,100'],
            'jam_kerja_per_hari' => ['required', 'numeric', 'gt:0', 'max:24'],
            'sla_plg_per_jam' => ['required', 'numeric', 'gt:0'],
            'toleransi_kwh' => ['required', 'numeric', 'min:0'],
            'kdbaca_normal' => ['required', 'array', 'min:1'],
            'kdbaca_normal.*' => ['string', 'max:5'],
            'prabayar_mulai' => ['required', 'integer', 'between:1,31'],
            'prabayar_selesai' => ['required', 'integer', 'between:1,31', 'gte:prabayar_mulai'],
            'bobot_bonus_j' => ['required', 'numeric', 'min:0', 'max:10'],
            'bobot_kehadiran' => ['required', 'numeric', 'between:0,50'],
            'nilai_izin_tanpa_foto' => ['required', 'numeric', 'between:0,100'],
            'kantor_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:kantor_lng'],
            'kantor_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:kantor_lat'],
            'radius_absen_m' => ['required', 'integer', 'between:20,5000'],
            'nilai_terlambat' => ['required', 'numeric', 'between:0,100'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i', 'after:jam_masuk'],
            'ambang_grade_a' => ['required', 'numeric', 'between:0,100'],
            'ambang_kritis' => ['required', 'numeric', 'between:0,100', 'lt:ambang_grade_a'],
            'jumlah_top' => ['required', 'integer', 'between:1,25'],
            'jumlah_bottom' => ['required', 'integer', 'between:1,25'],
        ], ['ambang_kritis.lt' => 'Ambang Perlu Pendampingan harus lebih kecil dari ambang Baik.', 'jam_pulang.after' => 'Jam pulang harus setelah jam masuk.']);

        $bobot = (float) $data['w_realisasi'] + (float) $data['w_eviden'] + (float) $data['w_kecepatan'];
        if (abs($bobot - 100) > 0.01) {
            throw ValidationException::withMessages(['w_realisasi' => "Bobot realisasi + eviden + kecepatan harus 100% (sekarang {$bobot}%)."]);
        }

        $angka = fn ($v) => (float) $v;
        $data = array_merge($data, [
            'w_realisasi' => $angka($data['w_realisasi']),
            'w_eviden' => $angka($data['w_eviden']),
            'w_kecepatan' => $angka($data['w_kecepatan']),
            'jam_kerja_per_hari' => $angka($data['jam_kerja_per_hari']),
            'sla_plg_per_jam' => $angka($data['sla_plg_per_jam']),
            'toleransi_kwh' => $angka($data['toleransi_kwh']),
            'bobot_bonus_j' => $angka($data['bobot_bonus_j']),
            'bobot_kehadiran' => $angka($data['bobot_kehadiran']),
            'nilai_izin_tanpa_foto' => $angka($data['nilai_izin_tanpa_foto']),
            'kantor_lat' => isset($data['kantor_lat']) ? round((float) $data['kantor_lat'], 7) : null,
            'kantor_lng' => isset($data['kantor_lng']) ? round((float) $data['kantor_lng'], 7) : null,
            'radius_absen_m' => (int) $data['radius_absen_m'],
            'nilai_terlambat' => $angka($data['nilai_terlambat']),
            'ambang_grade_a' => $angka($data['ambang_grade_a']),
            'ambang_kritis' => $angka($data['ambang_kritis']),
            'prabayar_mulai' => (int) $data['prabayar_mulai'],
            'prabayar_selesai' => (int) $data['prabayar_selesai'],
            'jumlah_top' => (int) $data['jumlah_top'],
            'jumlah_bottom' => (int) $data['jumlah_bottom'],
            'kdbaca_normal' => array_values(array_unique(array_map(fn ($k) => strtoupper(trim($k)), $data['kdbaca_normal']))),
        ]);

        $this->parameter->ubah($periode, $data, $request->user());

        return back()->with('success', 'Parameter disimpan dan skor periode dihitung ulang.');
    }

    public function recalculate(Request $request, string $kode): RedirectResponse
    {
        $this->layanan->hitungUlang(Period::where('kode', $kode)->firstOrFail(), $request->user());

        return back()->with('success', 'Skor periode dihitung ulang.');
    }

    public function close(Request $request, string $kode): RedirectResponse
    {
        $data = $request->validate(['konfirmasi_kode' => ['required', 'string']], [
            'konfirmasi_kode.required' => "Ketik {$kode} untuk mengonfirmasi penutupan periode.",
        ]);
        $this->layanan->tutup(Period::where('kode', $kode)->firstOrFail(), $data['konfirmasi_kode'], $request->user());

        return back()->with('success', "Periode {$kode} ditutup. Skor final tersimpan dan data terkunci.");
    }
}
