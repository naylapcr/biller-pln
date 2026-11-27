<?php

namespace App\Services;

use App\Enums\HasilBacaUlang;
use App\Enums\ModulType;
use App\Enums\SumberKoreksi;
use App\Models\MonthlyScore;
use App\Models\Period;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Skor bulanan biller (PRD 6.1). Selalu dihitung ulang penuh per periode dari tabel rincian,
 * sehingga hasilnya sama berapa kali pun dijalankan.
 *
 *   Skor Akhir     = min(100; (1 − b_H) × RataModul + b_H × Nilai_Hadir + Bonus_J)
 *   RataModul      = (1/n) Σ Nilai_m                      n = modul yang punya data untuk biller itu
 *   Nilai_Hadir    = rata-rata nilai hari kerja tercatat di bulan kerja (bulan sebelum BLTH):
 *                    hadir tepat waktu 100 · hadir terlambat, pulang cepat, atau tanpa absen pulang = nilai_terlambat
 *                    · izin/sakit berlampiran 100 · izin/sakit tanpa lampiran (koreksi) = nilai_izin_tanpa_foto · alpa 0.
 *                    Biller tanpa catatan absensi di bulan itu: b_H tidak dipakai (skor = RataModul + Bonus_J).
 *   Nilai_pasca    = (w_R·R + w_E·E + w_K·K) × (1 − ρ_BU) × (1 − ρ_AP2T)
 *     R            = TERBACA ÷ JMLPLG × 100 (Hari A–G dan J, Tunggal + Ganda Industri)
 *     E            = JML FOTO BERHASIL ÷ TERBACA × 100
 *     K            = min(pelanggan per jam ÷ SLA; 1) × 100, pelanggan per jam = TERBACA ÷ (hari aktif × 6 jam)
 *     ρ_BU         = kasus Baca Ulang salah (bukan sisipan) ÷ TERBACA
 *     ρ_AP2T       = koreksi AP2T dengan penyebab biller ÷ TERBACA
 *   Nilai_pra      = (R + E) ÷ 2
 *     R            = KDBACA normal dan dibaca sesuai jadwal ÷ pemeriksaan × 100
 *     E            = koordinat valid ÷ pemeriksaan × 100
 *   Bonus_J        = min(bobot_bonus_j; TERBACA_J ÷ JMLPLG seluruh hari baca biller itu × 100)
 *                    Satu pelanggan Hari J bernilai sama dengan satu pelanggan biasa (5 dari 1.493 → +0,33),
 *                    sehingga tidak ada untungnya memindahkan bacaan ke Hari J; 0 bila tanpa pelanggan Hari J.
 *
 * Peringkat: final_score menurun; bila sama, rasio Baca Ulang lebih kecil di atas, lalu terbaca lebih banyak.
 */
class PenghitungSkor
{
    public function __construct(
        private readonly ParameterSkor $parameter,
        private readonly AgregatorWorkOrder $agregator,
    ) {}

    /** @return Collection<int, MonthlyScore> */
    public function hitung(Period $periode, bool $final = false): Collection
    {
        $aturan = $this->parameter->untuk($periode);
        $this->agregator->bangun($periode);
        $this->tandaiSisipan($periode);

        $pasca = DB::table('reading_logs')->where('period_id', $periode->id)->whereNotNull('biller_id')
            ->groupBy('biller_id')->select('biller_id')
            ->selectRaw('SUM(jml_plg) as jml_plg, SUM(terbaca) as terbaca, SUM(foto_berhasil) as foto')
            ->selectRaw("SUM(CASE WHEN hari_baca = 'J' THEN jml_plg ELSE 0 END) as jml_plg_j")
            ->selectRaw("SUM(CASE WHEN hari_baca = 'J' THEN terbaca ELSE 0 END) as terbaca_j")
            ->get()->keyBy('biller_id');
        $hariAktif = $this->hariAktif($periode);
        $pra = DB::table('work_orders')->where('period_id', $periode->id)->where('modul_type', ModulType::Prabayar->value)
            ->get()->keyBy('biller_id');
        $salahBu = DB::table('reread_cases')->where('period_id', $periode->id)->whereNotNull('biller_id')
            ->whereIn('hasil', [HasilBacaUlang::SalahTurun->value, HasilBacaUlang::SalahLebih->value])
            ->groupBy('biller_id')->select('biller_id')
            ->selectRaw('SUM(CASE WHEN sisipan THEN 0 ELSE 1 END) as dihitung, SUM(CASE WHEN sisipan THEN 1 ELSE 0 END) as sisipan')
            ->get()->keyBy('biller_id');
        $ap2t = DB::table('corrections')->where('period_id', $periode->id)->where('sumber', SumberKoreksi::Ap2t->value)
            ->where('penyebab', 'biller')->whereNotNull('biller_id')
            ->groupBy('biller_id')->selectRaw('biller_id, COUNT(*) as n')->pluck('n', 'biller_id');

        $billerIds = $pasca->keys()->merge($pra->keys())->unique()->values();
        $sekarang = now();
        $kehadiran = $this->kehadiran($periode, $aturan);
        $bobotHadir = (float) ($aturan['bobot_kehadiran'] ?? 0) / 100;

        $baris = $billerIds->map(function ($billerId) use ($pasca, $hariAktif, $pra, $salahBu, $ap2t, $aturan, $kehadiran, $bobotHadir) {
            $modul = [];
            $bonusJ = 0.0;
            $rasioBu = 0.0;
            $rasioAp2t = 0.0;
            $dasar = $plgPerJam = $nilaiK = null;
            $terbaca = 0;

            $p = $pasca->get($billerId);
            if ($p && (int) $p->jml_plg > 0) {
                $terbaca = (int) $p->terbaca;
                $hari = (int) ($hariAktif[$billerId] ?? 0);
                $R = $terbaca / (int) $p->jml_plg * 100;
                $E = $terbaca > 0 ? (int) $p->foto / $terbaca * 100 : 0.0;
                $plgPerJam = $hari > 0 ? $terbaca / ($hari * (float) $aturan['jam_kerja_per_hari']) : 0.0;
                $nilaiK = min($plgPerJam / (float) $aturan['sla_plg_per_jam'], 1) * 100;

                $wR = (float) $aturan['w_realisasi'] / 100;
                $wE = (float) $aturan['w_eviden'] / 100;
                $wK = (float) $aturan['w_kecepatan'] / 100;
                $dasar = $wR * $R + $wE * $E + $wK * $nilaiK;

                $bu = $salahBu->get($billerId);
                $kasusBu = (int) ($bu->dihitung ?? 0);
                $kasusAp2t = (int) ($ap2t[$billerId] ?? 0);
                $rasioBu = $terbaca > 0 ? $kasusBu / $terbaca : 0.0;
                $rasioAp2t = $terbaca > 0 ? $kasusAp2t / $terbaca : 0.0;
                $nilai = $dasar * (1 - $rasioBu) * (1 - $rasioAp2t);

                $jmlJ = (int) $p->jml_plg_j;
                $terbacaJ = (int) $p->terbaca_j;
                $bonusJ = $jmlJ > 0 ? min((float) $aturan['bobot_bonus_j'], $terbacaJ / (int) $p->jml_plg * 100) : 0.0;

                $modul['pascabayar'] = [
                    'jml_plg' => (int) $p->jml_plg,
                    'terbaca' => $terbaca,
                    'foto' => (int) $p->foto,
                    'realisasi' => round($R, 2),
                    'eviden' => round($E, 2),
                    'kecepatan' => [
                        'terbaca' => $terbaca,
                        'hari_aktif' => $hari,
                        'plg_per_jam' => round($plgPerJam, 2),
                        'nilai' => round($nilaiK, 2),
                    ],
                    'bobot' => ['realisasi' => $wR, 'eviden' => $wE, 'kecepatan' => $wK],
                    'nilai_dasar' => round($dasar, 2),
                    'baca_ulang' => ['kasus_salah' => $kasusBu, 'sisipan_dikecualikan' => (int) ($bu->sisipan ?? 0), 'rasio' => round($rasioBu, 4)],
                    'ap2t' => ['kasus_salah' => $kasusAp2t, 'rasio' => round($rasioAp2t, 4)],
                    'hari_j' => [
                        'jml_plg' => $jmlJ,
                        'terbaca' => $terbacaJ,
                        'persen_tuntas' => $jmlJ > 0 ? round($terbacaJ / $jmlJ * 100, 1) : null,
                        'jml_plg_total' => (int) $p->jml_plg,
                        'nilai' => round($bonusJ, 2),
                    ],
                    'nilai' => round($nilai, 2),
                    '_nilai' => $nilai,
                ];
            }

            $w = $pra->get($billerId);
            if ($w && (int) $w->target_wo > 0) {
                $R = (int) $w->realisasi / (int) $w->target_wo * 100;
                $E = (int) $w->eviden / (int) $w->target_wo * 100;
                $modul['prabayar'] = [
                    'pemeriksaan' => (int) $w->target_wo,
                    'normal_terjadwal' => (int) $w->realisasi,
                    'koordinat_valid' => (int) $w->eviden,
                    'realisasi' => round($R, 2),
                    'eviden' => round($E, 2),
                    'nilai' => round(($R + $E) / 2, 2),
                    '_nilai' => ($R + $E) / 2,
                ];
            }

            $rata = $modul ? array_sum(array_column($modul, '_nilai')) / count($modul) : 0.0;
            foreach ($modul as &$m) {
                unset($m['_nilai']);
            }
            unset($m);

            $hadir = $kehadiran[$billerId] ?? null;
            $dasarAkhir = $hadir !== null && $bobotHadir > 0
                ? (1 - $bobotHadir) * $rata + $bobotHadir * $hadir['nilai']
                : $rata;

            return [
                'biller_id' => (int) $billerId,
                'nilai_per_modul' => $modul,
                'nilai_pasca_dasar' => $dasar !== null ? round($dasar, 2) : null,
                'plg_per_jam' => $plgPerJam !== null ? round($plgPerJam, 2) : null,
                'nilai_kecepatan' => $nilaiK !== null ? round($nilaiK, 2) : null,
                'rasio_bu' => round($rasioBu, 4),
                'rasio_ap2t' => round($rasioAp2t, 4),
                'rata_rata_modul' => round($rata, 2),
                'nilai_kehadiran' => $hadir['nilai'] ?? null,
                'kehadiran' => $hadir,
                'bonus_hari_j' => round($bonusJ, 2),
                'final_score' => round(min(100, $dasarAkhir + $bonusJ), 2),
                'terbaca' => $terbaca,
            ];
        });

        // Peringkat: skor ↓, rasio Baca Ulang ↑, terbaca ↓.
        $urut = $baris->sort(fn ($a, $b) => [$b['final_score'], $a['rasio_bu'], $b['terbaca']]
            <=> [$a['final_score'], $b['rasio_bu'], $a['terbaca']])->values();

        DB::transaction(function () use ($periode, $urut, $aturan, $final, $sekarang) {
            MonthlyScore::query()->where('period_id', $periode->id)->whereNotIn('biller_id', $urut->pluck('biller_id'))->delete();

            foreach ($urut as $i => $b) {
                MonthlyScore::updateOrCreate(
                    ['biller_id' => $b['biller_id'], 'period_id' => $periode->id],
                    $b + [
                        'peringkat' => $i + 1,
                        'kategori' => self::kategori($b['final_score'], $aturan),
                        'rules_snapshot' => $aturan,
                        'is_final' => $final,
                        'dihitung_at' => $sekarang,
                    ],
                );
            }
        });

        return MonthlyScore::query()->where('period_id', $periode->id)->orderBy('peringkat')->get();
    }

    /** Baik ≥ ambang_grade_a, Perlu Pendampingan < ambang_kritis, selain itu Cukup. */
    public static function kategori(float $skor, array $aturan): string
    {
        return match (true) {
            $skor >= (float) $aturan['ambang_grade_a'] => 'Baik',
            $skor < (float) $aturan['ambang_kritis'] => 'Perlu Pendampingan',
            default => 'Cukup',
        };
    }

    /** Bulan kerja periode: bulan sebelum BLTH (BLTH 202611 → Oktober 2026), sama dengan jadwal prabayar. */
    public static function bulanKehadiran(Period $periode): CarbonImmutable
    {
        return $periode->awalBulan()->subMonthNoOverflow()->startOfMonth();
    }

    /**
     * Nilai kehadiran per biller dari absensi hari kerja di bulan kerja periode.
     *
     * @return array<int, array{bulan: string, hari: int, hadir: int, izin: int, sakit: int, alpa: int, berfoto: int, nilai: float}>
     */
    private function kehadiran(Period $periode, array $aturan): array
    {
        $bulan = self::bulanKehadiran($periode);
        $tanpaFoto = (float) ($aturan['nilai_izin_tanpa_foto'] ?? 50);
        $nilaiTelat = (float) ($aturan['nilai_terlambat'] ?? 75);
        $hariIni = LayananAbsensi::hariIni()->toDateString();
        $hasil = [];

        DB::table('attendances')->join('users', 'users.id', '=', 'attendances.user_id')
            ->whereNotNull('users.biller_id')
            ->whereDate('attendances.tanggal', '>=', $bulan->toDateString())
            ->whereDate('attendances.tanggal', '<=', $bulan->endOfMonth()->toDateString())
            ->orderBy('attendances.id')
            ->select(['attendances.id', 'users.biller_id', 'attendances.tanggal', 'attendances.status', 'attendances.foto_path',
                'attendances.absen_at', 'attendances.terlambat_menit', 'attendances.pulang_at', 'attendances.pulang_cepat_menit'])
            ->chunk(2000, function ($rows) use (&$hasil, $tanpaFoto, $nilaiTelat, $hariIni) {
                foreach ($rows as $r) {
                    $tanggal = CarbonImmutable::parse($r->tanggal, config('app.timezone'));
                    if (! LayananAbsensi::hariKerja($tanggal)) {
                        continue;
                    }
                    $h = &$hasil[$r->biller_id];
                    $h ??= ['hari' => 0, 'hadir' => 0, 'terlambat' => 0, 'pulang_cepat' => 0, 'tanpa_pulang' => 0,
                        'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'berfoto' => 0, '_poin' => 0.0];
                    $berfoto = $r->foto_path !== null;
                    $h['hari']++;
                    $h[$r->status] = ($h[$r->status] ?? 0) + 1;
                    $kurang = false;
                    if ($r->status === 'hadir') {
                        $telat = (int) $r->terlambat_menit > 0;
                        $cepat = (int) $r->pulang_cepat_menit > 0;
                        // Hari ini belum selesai, jadi belum absen pulang tidak dihitung. Hadir dari koreksi (tanpa jam masuk) dianggap penuh.
                        $tanpaPulang = $r->absen_at !== null && $r->pulang_at === null && $tanggal->toDateString() < $hariIni;
                        $h['terlambat'] += (int) $telat;
                        $h['pulang_cepat'] += (int) $cepat;
                        $h['tanpa_pulang'] += (int) $tanpaPulang;
                        $kurang = $telat || $cepat || $tanpaPulang;
                    }
                    $h['_poin'] += match ($r->status) {
                        'hadir' => $kurang ? $nilaiTelat : 100.0,
                        'izin', 'sakit' => $berfoto ? 100.0 : $tanpaFoto,
                        default => 0.0,
                    };
                    if ($berfoto && $r->status !== 'hadir') {
                        $h['berfoto']++;
                    }
                    unset($h);
                }
            });

        return array_map(fn (array $h) => ['bulan' => $bulan->format('Y-m')] + array_diff_key($h, ['_poin' => 0])
            + ['nilai' => round($h['_poin'] / $h['hari'], 2)], $hasil);
    }

    /**
     * Hari aktif per biller: semua tanggal dari TGL MULAI sampai TGL SELESAI di RBM biller itu,
     * digabung agar hari baca yang tumpang tindih tidak terhitung dua kali.
     *
     * @return array<int, int>
     */
    private function hariAktif(Period $periode): array
    {
        $hari = [];
        DB::table('reading_logs')->where('period_id', $periode->id)->whereNotNull('biller_id')
            ->whereNotNull('mulai_at')->whereNotNull('selesai_at')
            ->orderBy('id')->select(['id', 'biller_id', 'mulai_at', 'selesai_at'])
            ->chunk(1000, function ($rows) use (&$hari) {
                foreach ($rows as $r) {
                    $mulai = CarbonImmutable::parse($r->mulai_at)->startOfDay();
                    $selesai = CarbonImmutable::parse($r->selesai_at)->startOfDay();
                    if ($selesai->lessThan($mulai)) {
                        continue;
                    }
                    foreach (CarbonPeriod::create($mulai, $selesai) as $t) {
                        $hari[$r->biller_id][$t->toDateString()] = true;
                    }
                }
            });

        return array_map('count', $hari);
    }

    /**
     * Kasus Baca Ulang yang dibaca di luar jendela hari baca RBM-nya (jam mulai–selesai RBM di rekap
     * pascabayar, ± toleransi_sisipan_menit) ditandai sisipan dan dikecualikan dari rasio kesalahan.
     */
    private function tandaiSisipan(Period $periode): void
    {
        $toleransi = (int) config('sipermon.toleransi_sisipan_menit') * 60;
        $detik = fn (string $waktu): int => CarbonImmutable::parse($waktu)->getTimestamp();
        $jendela = DB::table('reading_logs')->where('period_id', $periode->id)
            ->whereNotNull('mulai_at')->whereNotNull('selesai_at')
            ->groupBy('kode_rbm')->selectRaw('kode_rbm, MIN(mulai_at) as mulai, MAX(selesai_at) as selesai')
            ->get()->mapWithKeys(fn ($r) => [strtoupper($r->kode_rbm) => [
                $detik($r->mulai) - $toleransi, $detik($r->selesai) + $toleransi,
            ]]);

        $ya = [];
        $tidak = [];
        DB::table('reread_cases')->where('period_id', $periode->id)->orderBy('id')
            ->select(['id', 'kode_rbm', 'tgl_baca', 'sisipan'])
            ->chunk(1000, function ($rows) use ($jendela, $detik, &$ya, &$tidak) {
                foreach ($rows as $r) {
                    $w = $r->kode_rbm ? $jendela->get(strtoupper($r->kode_rbm)) : null;
                    $tgl = $r->tgl_baca ? $detik($r->tgl_baca) : null;
                    $sisipan = $w !== null && $tgl !== null && ($tgl < $w[0] || $tgl > $w[1]);
                    if ($sisipan !== (bool) $r->sisipan) {
                        $sisipan ? $ya[] = $r->id : $tidak[] = $r->id;
                    }
                }
            });

        foreach (array_chunk($ya, 500) as $ids) {
            DB::table('reread_cases')->whereIn('id', $ids)->update(['sisipan' => true]);
        }
        foreach (array_chunk($tidak, 500) as $ids) {
            DB::table('reread_cases')->whereIn('id', $ids)->update(['sisipan' => false]);
        }
    }
}
