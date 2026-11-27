<?php

namespace App\Services;

use App\Enums\HasilBacaUlang;
use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Enums\SumberKoreksi;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Biller;
use App\Models\BillerAlias;
use App\Models\MonthlyScore;
use App\Models\Period;
use App\Models\RereadCase;
use App\Models\UploadBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Data dashboard, leaderboard, rincian skor, dan histori WO (PRD bagian 8 dan 10.2). */
class LaporanKinerja
{
    public const KATEGORI = ['Baik', 'Cukup', 'Perlu Pendampingan'];

    public function __construct(
        private readonly ParameterSkor $parameter,
        private readonly LayananPemetaan $pemetaan,
    ) {}

    /**
     * Dashboard Monitoring (02.1): 4 kartu KPI, distribusi kategori, statistik absensi hari ini,
     * dan jumlah login belum terpetakan. Filter hari baca hanya untuk angka pascabayar.
     */
    public function ringkasan(Period $periode, ?string $hariBaca = null): array
    {
        $jumlah = fn (int $periodId) => DB::table('reading_logs')->where('period_id', $periodId)
            ->when($hariBaca, fn ($q) => $q->where('hari_baca', $hariBaca))
            ->selectRaw('COALESCE(SUM(jml_plg), 0) as jml_plg, COALESCE(SUM(terbaca), 0) as terbaca, COALESCE(SUM(foto_berhasil), 0) as foto')
            ->first();
        $pasca = $jumlah($periode->id);
        $lalu = ($sebelum = Period::query()->where('kode', '<', $periode->kode)->orderByDesc('kode')->first()) ? $jumlah($sebelum->id) : null;
        $persen = $pasca->jml_plg > 0 ? round($pasca->terbaca / $pasca->jml_plg * 100, 2) : null;
        $persenLalu = $lalu && $lalu->jml_plg > 0 ? round($lalu->terbaca / $lalu->jml_plg * 100, 2) : null;
        $aturan = $this->parameter->untuk($periode);
        $skor = MonthlyScore::query()->where('period_id', $periode->id)->get();
        $terbaca = (int) $pasca->terbaca;
        $totalTerbaca = (int) $skor->sum('terbaca');
        $kasusBu = RereadCase::query()->where('period_id', $periode->id)->where('sisipan', false)
            ->whereIn('hasil', [HasilBacaUlang::SalahTurun->value, HasilBacaUlang::SalahLebih->value])->count();
        $kasusAp2t = DB::table('corrections')->where('period_id', $periode->id)
            ->where('sumber', SumberKoreksi::Ap2t->value)->where('penyebab', 'biller')->count();

        return [
            'realisasi' => [
                'jml_plg' => (int) $pasca->jml_plg,
                'terbaca' => $terbaca,
                'persen' => $persen,
                'selisih' => $persen !== null && $persenLalu !== null ? round($persen - $persenLalu, 2) : null,
            ],
            'rata_skor' => [
                'nilai' => $skor->isNotEmpty() ? round($skor->avg('final_score'), 2) : null,
                'jumlah_biller' => $skor->count(),
            ],
            'kecepatan_eviden' => [
                'plg_per_jam' => $skor->whereNotNull('plg_per_jam')->isNotEmpty() ? round($skor->whereNotNull('plg_per_jam')->avg('plg_per_jam'), 1) : null,
                'eviden_persen' => $terbaca > 0 ? round($pasca->foto / $terbaca * 100, 2) : null,
                'standar' => (float) $aturan['sla_plg_per_jam'],
            ],
            'mutu' => [
                'kasus_bu' => $kasusBu,
                'kasus_ap2t' => $kasusAp2t,
                'rasio_bu' => $totalTerbaca > 0 ? round($kasusBu / $totalTerbaca, 4) : null,
                'rasio_ap2t' => $totalTerbaca > 0 ? round($kasusAp2t / $totalTerbaca, 4) : null,
            ],
            'distribusi_kategori' => $this->distribusi($skor),
            'absensi_hari_ini' => $this->absensiHariIni([Role::Biller]),
            'belum_terpetakan' => $this->pemetaan->belumTerpetakan($periode)->count(),
        ];
    }

    /** Dashboard Sistem Super Admin (03.0). */
    public function sistem(Period $periode, LayananPeriode $layananPeriode): array
    {
        $akun = User::query()->where('is_active', true)->selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role');
        $skor = MonthlyScore::query()->where('period_id', $periode->id)->get();
        $belum = $this->pemetaan->belumTerpetakan($periode);

        return [
            'akun_aktif' => collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => (int) ($akun[$r->value] ?? 0)]),
            'akun_terkunci' => User::query()->whereNotNull('terkunci_at')->count(),
            'master_biller' => Biller::query()->aktif()->count(),
            'jumlah_alias' => BillerAlias::query()->count(),
            'belum_terpetakan' => $belum->count(),
            'belum_baris' => (int) $belum->sum('baris'),
            'periode' => [
                'kode' => $periode->kode,
                'status' => $periode->status->value,
                'batch_committed' => UploadBatch::query()->committed()->where('period_id', $periode->id)->get()
                    ->map(fn (UploadBatch $b) => $b->modul?->label())->filter()->values(),
            ],
            'distribusi_kategori' => $this->distribusi($skor),
            'kesiapan_tutup' => $layananPeriode->kesiapan($periode),
            'tren_rata' => Period::query()->where('kode', '<=', $periode->kode)->orderByDesc('kode')->limit(4)->get()->reverse()
                ->map(fn (Period $p) => [
                    'periode' => $p->kode,
                    'label' => $p->awalBulan()->locale('id')->translatedFormat('M Y'),
                    'nilai' => ($r = MonthlyScore::query()->where('period_id', $p->id)->avg('final_score')) !== null ? round((float) $r, 2) : null,
                ])->values(),
            'audit_terbaru' => AuditLog::query()->with('user:id,nama')->latest('id')->limit(4)->get()->map(fn (AuditLog $l) => [
                'id' => $l->id, 'waktu' => $l->created_at?->toIso8601String(), 'user' => $l->user?->nama ?? 'Sistem',
                'aksi' => $l->aksi, 'objek' => trim(($l->objek_type ?? '').' '.($l->objek_id ?? '')),
            ]),
        ];
    }

    /**
     * Top / Bottom N dari biller aktif yang punya skor. Untuk Biller hanya nama, sektor, dan peringkat;
     * untuk Admin dengan skor, ringkasan modul, dan kategori.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(Period $periode, string $urut = 'top', ?int $limit = null, bool $lengkap = true): Collection
    {
        $aturan = $this->parameter->untuk($periode);
        $limit ??= (int) ($urut === 'top' ? $aturan['jumlah_top'] : $aturan['jumlah_bottom']);

        return MonthlyScore::query()
            ->where('period_id', $periode->id)
            ->whereHas('biller', fn ($q) => $q->where('is_active', true))
            ->with('biller:id,nama,nip,zona_wilayah,login_acmt,kode_petugas_acmt')
            ->orderBy('peringkat', $urut === 'top' ? 'asc' : 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (MonthlyScore $s) => [
                'biller_id' => $s->biller_id,
                'nama' => $s->biller->nama,
                'sektor' => $s->biller->zona_wilayah,
                'peringkat' => $s->peringkat,
            ] + ($lengkap ? [
                'nip' => $s->biller->nip,
                'login_acmt' => $s->biller->login_acmt,
                'final_score' => $s->final_score,
                'pascabayar' => $s->nilai_per_modul['pascabayar']['nilai'] ?? null,
                'prabayar' => $s->nilai_per_modul['prabayar']['nilai'] ?? null,
                'kehadiran' => $s->nilai_kehadiran,
                'bonus_hari_j' => $s->bonus_hari_j,
                'kategori' => $s->kategori,
                'rasio_bu' => $s->rasio_bu,
                'rasio_ap2t' => $s->rasio_ap2t,
                'realisasi' => $s->nilai_per_modul['pascabayar']['realisasi'] ?? null,
                'plg_per_jam' => $s->plg_per_jam,
            ] : []));
    }

    /** @return Collection<int, array<string, mixed>> semua biller, berskor atau belum, urut peringkat */
    public function semuaBiller(Period $periode, ?string $cari = null, bool $hanyaAktif = false): Collection
    {
        $skor = MonthlyScore::query()->where('period_id', $periode->id)->get()->keyBy('biller_id');
        $cari = $cari !== null ? trim($cari) : null;
        $rbm = $cari ? DB::table('reading_logs')->where('period_id', $periode->id)->whereNotNull('biller_id')
            ->where('kode_rbm', 'like', '%'.strtoupper($cari).'%')->distinct()->pluck('biller_id') : collect();

        return Biller::query()
            ->when($hanyaAktif, fn ($q) => $q->aktif())
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(nama) LIKE ?', ['%'.strtolower($cari).'%'])
                ->orWhereRaw('LOWER(nip) LIKE ?', ['%'.strtolower($cari).'%'])
                ->orWhere('login_acmt', 'like', '%'.strtolower($cari).'%')
                ->orWhere('kode_petugas_acmt', 'like', '%'.strtoupper($cari).'%')
                ->orWhereIn('id', $rbm)))
            ->orderBy('nama')
            ->get()
            ->map(function (Biller $b) use ($skor) {
                $s = $skor->get($b->id);

                return [
                    'id' => $b->id,
                    'nama' => $b->nama,
                    'nip' => $b->nip,
                    'login_acmt' => $b->login_acmt,
                    'kode_petugas' => $b->kode_petugas_acmt,
                    'zona_wilayah' => $b->zona_wilayah,
                    'is_active' => $b->is_active,
                    'peringkat' => $s?->peringkat,
                    'pascabayar' => $s?->nilai_per_modul['pascabayar']['nilai'] ?? null,
                    'prabayar' => $s?->nilai_per_modul['prabayar']['nilai'] ?? null,
                    'kehadiran' => $s?->nilai_kehadiran,
                    'bonus_hari_j' => $s?->bonus_hari_j,
                    'rasio_bu' => $s?->rasio_bu,
                    'rasio_ap2t' => $s?->rasio_ap2t,
                    'final_score' => $s?->final_score,
                    'kategori' => $s?->kategori,
                ];
            })
            ->sortBy(fn ($b) => $b['peringkat'] ?? PHP_INT_MAX)
            ->values();
    }

    /** Bentuk respons /billers/{id}/score dan /me/score (PRD bagian 8). */
    public function skorBiller(Biller $biller, Period $periode): array
    {
        $s = MonthlyScore::query()->where('period_id', $periode->id)->where('biller_id', $biller->id)->first();
        $modul = $s?->nilai_per_modul ?? [];
        $hariJ = $modul['pascabayar']['hari_j'] ?? null;

        return [
            'periode' => $periode->kode,
            'biller' => [
                'id' => $biller->id,
                'nama' => $biller->nama,
                'nip' => $biller->nip,
                'login_acmt' => $biller->login_acmt,
                'kode_petugas' => $biller->kode_petugas_acmt,
                'wilayah' => $biller->zona_wilayah,
            ],
            'nilai_per_modul' => (object) collect($modul)->map(fn ($m) => collect($m)->except('hari_j')->all())->all(),
            'jumlah_modul' => count($modul),
            'rata_rata_modul' => $s?->rata_rata_modul,
            'kehadiran' => $s?->kehadiran,
            'bonus_hari_j' => $hariJ ? [
                'terbaca' => $hariJ['terbaca'], 'jml_plg' => $hariJ['jml_plg'], 'persen_tuntas' => $hariJ['persen_tuntas'],
                'jml_plg_total' => $hariJ['jml_plg_total'] ?? $modul['pascabayar']['jml_plg'] ?? 0, 'nilai' => $s->bonus_hari_j,
            ] : ['terbaca' => 0, 'jml_plg' => 0, 'persen_tuntas' => null, 'jml_plg_total' => 0, 'nilai' => 0.0],
            'final_score' => $s?->final_score,
            'peringkat' => $s?->peringkat,
            'jumlah_biller' => MonthlyScore::query()->where('period_id', $periode->id)->count(),
            'kategori' => $s?->kategori,
            'is_final' => $s?->is_final ?? false,
            'dihitung_at' => $s?->dihitung_at?->toIso8601String(),
            'aturan' => $s?->rules_snapshot ? array_intersect_key($s->rules_snapshot, array_flip([
                'w_realisasi', 'w_eviden', 'w_kecepatan', 'sla_plg_per_jam', 'jam_kerja_per_hari', 'bobot_bonus_j', 'ambang_grade_a', 'ambang_kritis',
                'bobot_kehadiran', 'nilai_izin_tanpa_foto', 'nilai_terlambat', 'jam_masuk', 'jam_pulang',
            ])) : null,
            'jadwal_prabayar' => array_map(
                fn (CarbonImmutable $t) => $t->toDateString(),
                AgregatorWorkOrder::jadwalPrabayar($periode, $s?->rules_snapshot ?: $this->parameter->untuk($periode)),
            ),
        ];
    }

    /** @return Collection<int, array{periode: string, label: string, final_score: float|null, peringkat: int|null}> skor N periode terakhir */
    public function tren(Biller $biller, int $jumlah = 4, ?Period $sampai = null): Collection
    {
        $skor = MonthlyScore::query()->where('biller_id', $biller->id)->get()->keyBy('period_id');

        return Period::query()->when($sampai, fn ($q) => $q->where('kode', '<=', $sampai->kode))
            ->orderByDesc('kode')->limit($jumlah)->get()->reverse()->values()
            ->map(fn (Period $p) => [
                'periode' => $p->kode,
                'label' => $p->awalBulan()->locale('id')->translatedFormat('M Y'),
                'final_score' => $skor->get($p->id)?->final_score,
                'peringkat' => $skor->get($p->id)?->peringkat,
            ]);
    }

    /**
     * Rincian kesalahan: kasus Baca Ulang yang terbukti salah dan koreksi AP2T.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function kesalahan(Biller $biller, Period $periode): Collection
    {
        $bu = RereadCase::query()->where('period_id', $periode->id)->where('biller_id', $biller->id)
            ->whereIn('hasil', [HasilBacaUlang::SalahTurun->value, HasilBacaUlang::SalahLebih->value])
            ->orderBy('tgl_baca')->get()
            ->map(fn (RereadCase $r) => [
                'id' => 'bu-'.$r->id,
                'idpel' => $r->idpel,
                'kode_rbm' => $r->kode_rbm,
                'sumber' => 'Baca Ulang',
                'stan_awal' => $r->stan_awal,
                'stan_bu' => $r->stan_bu,
                'hasil' => $r->hasil->value,
                'hasil_label' => $r->hasil->label(),
                'dihitung' => ! $r->sisipan,
                'keterangan' => $r->sisipan
                    ? 'Sisipan: dibaca di luar jendela hari baca RBM, tidak memotong skor'
                    : ($r->hasil === HasilBacaUlang::SalahLebih ? 'Batas fisik '.number_format((float) $r->batas_kwh, 1, ',', '.').' kWh' : 'Stan BU lebih kecil dari stan awal'),
                'petugas_login' => $r->petugas_login,
                'tgl_baca' => $r->tgl_baca?->toIso8601String(),
                'tgl_baca_bu' => $r->tgl_baca_bu?->toIso8601String(),
            ]);
        $ap2t = DB::table('corrections')->where('period_id', $periode->id)->where('biller_id', $biller->id)
            ->where('sumber', SumberKoreksi::Ap2t->value)->orderBy('idpel')->get()
            ->map(fn ($c) => [
                'id' => 'ap2t-'.$c->id,
                'idpel' => $c->idpel,
                'kode_rbm' => null,
                'sumber' => 'AP2T',
                'stan_awal' => $c->stan_awal !== null ? (float) $c->stan_awal : null,
                'stan_bu' => $c->stan_benar !== null ? (float) $c->stan_benar : null,
                'hasil' => 'koreksi_'.$c->penyebab,
                'hasil_label' => $c->penyebab === 'biller' ? 'Salah biller' : 'Salah frontliner',
                'dihitung' => $c->penyebab === 'biller',
                'keterangan' => $c->catatan,
                'petugas_login' => $c->petugas_login,
                'tgl_baca' => null,
                'tgl_baca_bu' => null,
            ]);

        return $bu->concat($ap2t)->values();
    }

    /**
     * Histori WO (01.3): pascabayar per hari baca dan RBM, prabayar per tanggal.
     * Kecepatan per RBM = TERBACA ÷ (hari aktif RBM × jam kerja), sesuai 6.1.
     */
    public function historiWo(Biller $biller, Period $periode): array
    {
        $aturan = $this->parameter->untuk($periode);
        $jam = (float) $aturan['jam_kerja_per_hari'];
        [$mulai, $selesai] = AgregatorWorkOrder::jadwalPrabayar($periode, $aturan);
        $normal = (array) $aturan['kdbaca_normal'];
        $skor = MonthlyScore::query()->where('period_id', $periode->id)->where('biller_id', $biller->id)->first();

        $pasca = DB::table('reading_logs')->where('period_id', $periode->id)->where('biller_id', $biller->id)
            ->orderBy('hari_baca')->orderBy('kode_rbm')->get()
            ->map(function ($r) use ($jam) {
                $m = $r->mulai_at ? CarbonImmutable::parse($r->mulai_at) : null;
                $s = $r->selesai_at ? CarbonImmutable::parse($r->selesai_at) : null;
                $hari = $m && $s ? $m->startOfDay()->diffInDays($s->startOfDay()) + 1 : null;

                return [
                    'id' => $r->id,
                    'hari_baca' => $r->hari_baca,
                    'kode_rbm' => $r->kode_rbm,
                    'jenis_meter' => $r->jenis_meter,
                    'jml_plg' => (int) $r->jml_plg,
                    'terbaca' => (int) $r->terbaca,
                    'foto' => (int) $r->foto_berhasil,
                    'mulai_at' => $m?->toIso8601String(),
                    'selesai_at' => $s?->toIso8601String(),
                    'hari_aktif' => $hari,
                    'plg_per_jam' => $hari ? round($r->terbaca / ($hari * $jam), 1) : null,
                ];
            });

        $pra = DB::table('prepaid_inspections')->where('period_id', $periode->id)->where('biller_id', $biller->id)
            ->orderBy('tgl_baca')->orderBy('idpel')->get()
            ->map(function ($r) use ($mulai, $selesai, $normal) {
                $t = $r->tgl_baca ? CarbonImmutable::parse($r->tgl_baca) : null;
                $terjadwal = $t && $t->betweenIncluded($mulai, $selesai);

                return [
                    'id' => $r->id,
                    'tgl_baca' => $t?->toIso8601String(),
                    'kdrbm' => $r->kdrbm,
                    'idpel' => $r->idpel,
                    'no_meter' => $r->no_meter,
                    'kd_baca' => $r->kd_baca,
                    'latitude' => $r->latitude !== null ? (float) $r->latitude : null,
                    'longitude' => $r->longitude !== null ? (float) $r->longitude : null,
                    'koordinat_valid' => (bool) $r->koordinat_valid,
                    'status' => ! in_array($r->kd_baca, $normal, true) ? 'kode_tidak_normal' : ($terjadwal ? 'sesuai' : 'di_luar_jadwal'),
                ];
            });

        return [
            'pascabayar' => $pasca,
            'prabayar' => $pra,
            'jadwal_prabayar' => [$mulai->toDateString(), $selesai->toDateString()],
            'bonus_hari_j' => (float) ($skor?->bonus_hari_j ?? 0),
            'plg_per_jam' => $skor?->plg_per_jam,
            'standar' => (float) $aturan['sla_plg_per_jam'],
        ];
    }

    /** @return Collection<int, array{tanggal: string, terbaca: int}> pelanggan terbaca per tanggal mulai baca */
    public function trenHarian(Period $periode): Collection
    {
        return DB::table('reading_logs')->where('period_id', $periode->id)->whereNotNull('mulai_at')
            ->get(['mulai_at', 'terbaca'])
            ->groupBy(fn ($r) => substr((string) $r->mulai_at, 0, 10))
            ->map(fn ($g, $tgl) => ['tanggal' => $tgl, 'terbaca' => (int) $g->sum('terbaca')])
            ->sortKeys()
            ->values();
    }

    /**
     * Statistik absensi hari ini untuk peran tertentu.
     *
     * @param  list<Role>  $peran
     */
    public function absensiHariIni(array $peran): array
    {
        $ids = User::query()->where('is_active', true)->whereIn('role', array_map(fn (Role $r) => $r->value, $peran))->pluck('id');
        $status = Attendance::query()->whereIn('user_id', $ids)->whereDate('tanggal', LayananAbsensi::hariIni()->toDateString())
            ->pluck('status')->map(fn ($s) => $s instanceof StatusAbsensi ? $s->value : $s)->countBy();

        return [
            'total' => $ids->count(),
            'hadir' => (int) ($status['hadir'] ?? 0),
            'izin' => (int) ($status['izin'] ?? 0),
            'sakit' => (int) ($status['sakit'] ?? 0),
            'alpa' => (int) ($status['alpa'] ?? 0),
            'belum' => $ids->count() - $status->sum(),
        ];
    }

    /** @return array<string, int> */
    private function distribusi(Collection $skor): array
    {
        return collect(self::KATEGORI)->mapWithKeys(fn ($k) => [$k => $skor->where('kategori', $k)->count()])->all();
    }
}
