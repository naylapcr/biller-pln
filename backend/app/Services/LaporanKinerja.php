<?php

namespace App\Services;

use App\Enums\SumberKoreksi;
use App\Models\Biller;
use App\Models\Correction;
use App\Models\MonthlyScore;
use App\Models\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Data dashboard, leaderboard, dan rincian skor (PRD bagian 8). */
class LaporanKinerja
{
    public function __construct(private readonly ParameterSkor $parameter) {}

    /**
     * 4 kartu KPI: total WO, realisasi (%), rasio kecepatan, total denda.
     * Filter hari baca dan jenis meter hanya berlaku untuk angka pascabayar.
     */
    public function ringkasan(Period $periode, ?string $hariBaca = null, ?string $jenisMeter = null): array
    {
        $pasca = DB::table('reading_logs')->where('period_id', $periode->id)
            ->when($hariBaca, fn ($q) => $q->where('hari_baca', $hariBaca))
            ->when($jenisMeter, fn ($q) => $q->where('jenis_meter', $jenisMeter))
            ->selectRaw('COALESCE(SUM(jml_plg), 0) as target, COALESCE(SUM(terbaca), 0) as terbaca, COALESCE(SUM(foto_berhasil), 0) as foto, COALESCE(SUM(durasi_detik), 0) as durasi')
            ->first();

        $pakaiPra = $hariBaca === null && $jenisMeter === null;
        $pra = $pakaiPra
            ? DB::table('work_orders')->where('period_id', $periode->id)->where('modul_type', 'prabayar')
                ->selectRaw('COALESCE(SUM(target_wo), 0) as target, COALESCE(SUM(realisasi), 0) as realisasi')->first()
            : (object) ['target' => 0, 'realisasi' => 0];

        $target = (int) $pasca->target + (int) $pra->target;
        $realisasi = (int) $pasca->terbaca + (int) $pra->realisasi;
        $denda = MonthlyScore::query()->where('period_id', $periode->id);

        return [
            'total_wo' => $target,
            'realisasi' => $realisasi,
            'realisasi_persen' => $target > 0 ? round($realisasi / $target * 100, 2) : null,
            'pascabayar' => ['target' => (int) $pasca->target, 'terbaca' => (int) $pasca->terbaca, 'foto' => (int) $pasca->foto],
            'prabayar' => $pakaiPra ? ['target' => (int) $pra->target, 'normal' => (int) $pra->realisasi] : null,
            // Terbaca per jam selisih kalender (WAKTU ACMT bukan jam kerja).
            'rasio_kecepatan' => $pasca->durasi > 0 ? round($pasca->terbaca / ($pasca->durasi / 3600), 2) : null,
            'total_denda' => round((float) $denda->sum('total_denda'), 2),
            'kasus_denda' => Correction::query()->where('period_id', $periode->id)->whereNotNull('biller_id')->count(),
        ];
    }

    /**
     * Top / bottom N dari biller aktif yang punya data di periode ini.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(Period $periode, string $urut = 'top', ?int $limit = null, bool $denganDenda = true): Collection
    {
        $aturan = $this->parameter->untuk($periode);
        $limit ??= (int) ($urut === 'top' ? $aturan['jumlah_top'] : $aturan['jumlah_bottom']);

        return MonthlyScore::query()
            ->where('period_id', $periode->id)
            ->whereHas('biller', fn ($q) => $q->where('is_active', true))
            ->with('biller:id,nama,login_acmt,kode_petugas_acmt')
            ->orderBy('peringkat', $urut === 'top' ? 'asc' : 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (MonthlyScore $s) => $this->barisPeringkat($s, $denganDenda));
    }

    /** @return Collection<int, array<string, mixed>> semua biller aktif, berskor atau belum */
    public function semuaBiller(Period $periode, ?string $cari = null): Collection
    {
        $skor = MonthlyScore::query()->where('period_id', $periode->id)->get()->keyBy('biller_id');

        return Biller::query()
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$cari}%")
                ->orWhere('login_acmt', 'like', '%'.strtolower($cari).'%')
                ->orWhere('kode_petugas_acmt', 'like', '%'.strtoupper($cari).'%')))
            ->orderBy('nama')
            ->get()
            ->map(function (Biller $b) use ($skor) {
                $s = $skor->get($b->id);

                return [
                    'id' => $b->id,
                    'nama' => $b->nama,
                    'login_acmt' => $b->login_acmt,
                    'kode_petugas' => $b->kode_petugas_acmt,
                    'zona_wilayah' => $b->zona_wilayah,
                    'is_active' => $b->is_active,
                    'peringkat' => $s?->peringkat,
                    'final_score' => $s?->final_score,
                    'poin_tugas' => $s?->poin_tugas,
                    'poin_hari_j' => $s?->poin_hari_j,
                    'total_denda' => $s?->total_denda,
                    'kategori' => $s?->kategori,
                    'pascabayar' => $s?->poin_per_modul['pascabayar'] ?? null,
                    'prabayar' => $s?->poin_per_modul['prabayar'] ?? null,
                ];
            })
            ->sortBy(fn ($b) => $b['peringkat'] ?? PHP_INT_MAX)
            ->values();
    }

    /** Bentuk respons /billers/{id}/score dan /me/score (PRD bagian 8). */
    public function skorBiller(Biller $biller, Period $periode): array
    {
        $s = MonthlyScore::query()->where('period_id', $periode->id)->where('biller_id', $biller->id)->first();
        $kasus = Correction::query()->where('period_id', $periode->id)->where('biller_id', $biller->id)
            ->selectRaw('sumber, COUNT(*) as n')->groupBy('sumber')->pluck('n', 'sumber');
        $kerja = DB::table('work_orders')->where('period_id', $periode->id)->where('biller_id', $biller->id)->get()->keyBy('modul_type');

        return [
            'periode' => $periode->kode,
            'biller' => [
                'id' => $biller->id,
                'nama' => $biller->nama,
                'login_acmt' => $biller->login_acmt,
                'kode_petugas' => $biller->kode_petugas_acmt,
            ],
            'poin_per_modul' => $s?->poin_per_modul ?? (object) [],
            'bonus_hari_j' => $s?->poin_hari_j ?? 0,
            'denda' => [
                'baca_ulang' => ['kasus' => (int) ($kasus[SumberKoreksi::BacaUlang->value] ?? 0), 'poin' => $s?->denda_bu ?? 0],
                'ap2t' => ['kasus' => (int) ($kasus[SumberKoreksi::Ap2t->value] ?? 0), 'poin' => $s?->denda_ap2t ?? 0],
                'absensi' => ['alpa' => (int) ($s?->indikator['absensi']['alpa'] ?? 0), 'poin' => $s?->denda_absensi ?? 0],
            ],
            'absensi' => $s?->indikator['absensi'] ?? null,
            'poin_tugas' => $s?->poin_tugas,
            'final_score' => $s?->final_score,
            'peringkat' => $s?->peringkat,
            'jumlah_biller' => MonthlyScore::query()->where('period_id', $periode->id)->count(),
            'kategori' => $s?->kategori,
            'is_final' => $s?->is_final ?? false,
            'dihitung_at' => $s?->dihitung_at?->toIso8601String(),
            'indikator' => $s?->indikator,
            // Kelengkapan eviden per modul (foto / koordinat valid dibanding realisasi).
            'eviden' => collect($kerja)->map(fn ($w) => [
                'target' => (int) $w->target_wo,
                'realisasi' => (int) $w->realisasi,
                'eviden' => (int) $w->eviden,
                'persen' => $w->realisasi > 0 ? round($w->eviden / $w->realisasi * 100, 1) : null,
            ]),
            'aturan' => $s?->rules_snapshot ? array_intersect_key($s->rules_snapshot, array_flip(['w_stan', 'w_eviden', 'mode_agregasi', 'denda_bu_per_kasus'])) : null,
        ];
    }

    /** @return Collection<int, array<string, mixed>> rekam jejak denda */
    public function kasusDenda(Biller $biller, Period $periode): Collection
    {
        return Correction::query()
            ->where('period_id', $periode->id)->where('biller_id', $biller->id)
            ->with('rereadCase:id,tgl_baca,tgl_baca_bu,pemkwh_bu,batas_kwh,hasil,kode_rbm,daya_va')
            ->orderBy('idpel')
            ->get()
            ->map(fn (Correction $c) => [
                'id' => $c->id,
                'sumber' => $c->sumber->value,
                'sumber_label' => $c->sumber->label(),
                'idpel' => $c->idpel,
                'stan_awal' => $c->stan_awal,
                'stan_benar' => $c->stan_benar,
                'tingkat' => $c->tingkat,
                'denda_poin' => $c->denda_poin,
                'catatan' => $c->catatan,
                'kode_rbm' => $c->rereadCase?->kode_rbm,
                'daya_va' => $c->rereadCase?->daya_va,
                'tgl_baca' => $c->rereadCase?->tgl_baca?->toIso8601String(),
                'tgl_baca_bu' => $c->rereadCase?->tgl_baca_bu?->toIso8601String(),
                'batas_kwh' => $c->rereadCase?->batas_kwh,
            ]);
    }

    /** @return Collection<int, array{tanggal: string, terbaca: int}> pelanggan terbaca per tanggal mulai baca */
    public function tren(Period $periode): Collection
    {
        return DB::table('reading_logs')->where('period_id', $periode->id)->whereNotNull('mulai_at')
            ->get(['mulai_at', 'terbaca'])
            ->groupBy(fn ($r) => substr((string) $r->mulai_at, 0, 10))
            ->map(fn ($g, $tgl) => ['tanggal' => $tgl, 'terbaca' => (int) $g->sum('terbaca')])
            ->sortKeys()
            ->values();
    }

    private function barisPeringkat(MonthlyScore $s, bool $denganDenda): array
    {
        return [
            'biller_id' => $s->biller_id,
            'nama' => $s->biller->nama,
            'kode_petugas' => $s->biller->kode_petugas_acmt,
            'peringkat' => $s->peringkat,
            'final_score' => $s->final_score,
        ] + ($denganDenda ? [
            'login_acmt' => $s->biller->login_acmt,
            'poin_tugas' => $s->poin_tugas,
            'poin_hari_j' => $s->poin_hari_j,
            'total_denda' => $s->total_denda,
            'kategori' => $s->kategori,
        ] : []);
    }
}
