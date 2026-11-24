<?php

namespace App\Services;

use App\Enums\HasilBacaUlang;
use App\Enums\ModulType;
use App\Enums\SumberKoreksi;
use App\Models\MonthlyScore;
use App\Models\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Skor bulanan biller (PRD 6.1). Selalu dihitung ulang penuh per periode dari tabel rincian,
 * sehingga hasilnya sama berapa kali pun dijalankan.
 *
 *   Skor Akhir = Agregat(Σ Poin_m) + Bonus_J − Denda_AP2T − Denda_BU − Denda_Absensi
 *   Poin_m     = StanValid_m × w_stan + Eviden_m × w_eviden
 *
 *   StanValid pascabayar = TERBACA (Tunggal + Ganda, semua hari baca) − kasus Baca Ulang terbukti salah
 *   Eviden pascabayar    = JML FOTO BERHASIL
 *   StanValid prabayar   = pemeriksaan dengan KDBACA di kdbaca_normal
 *   Eviden prabayar      = pemeriksaan dengan koordinat valid (usulan PRD, belum ditetapkan)
 *   Bonus Hari J         = clamp(bonus_dasar_j + terbaca_J × poin_per_plg_j, bonus_min_j, bonus_max_j), 0 bila terbaca_J = 0
 *   Denda BU             = kasus terbukti × denda_bu_per_kasus
 *   Denda AP2T           = Σ denda per kasus menurut tingkat
 *   Denda absensi        = hari alpa × denda_alpa_per_hari (lihat LayananAbsensi)
 *
 * Peringkat: final_score menurun; bila sama, total denda lebih kecil di atas, lalu StanValid lebih besar.
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

        $wo = DB::table('work_orders')->where('period_id', $periode->id)->get()->groupBy('biller_id');
        $hariJ = DB::table('reading_logs')->where('period_id', $periode->id)->where('hari_baca', 'J')->whereNotNull('biller_id')
            ->groupBy('biller_id')->selectRaw('biller_id, SUM(terbaca) as n')->pluck('n', 'biller_id');
        $salahBu = DB::table('reread_cases')->where('period_id', $periode->id)->whereNotNull('biller_id')
            ->whereIn('hasil', [HasilBacaUlang::SalahTurun->value, HasilBacaUlang::SalahLebih->value])
            ->groupBy('biller_id')->selectRaw('biller_id, COUNT(*) as n')->pluck('n', 'biller_id');
        $ap2t = DB::table('corrections')->where('period_id', $periode->id)->where('sumber', SumberKoreksi::Ap2t->value)
            ->whereNotNull('biller_id')->get(['biller_id', 'tingkat', 'denda_poin'])->groupBy('biller_id');

        // Denda per kasus BU mengikuti parameter periode ini.
        DB::table('corrections')->where('period_id', $periode->id)->where('sumber', SumberKoreksi::BacaUlang->value)
            ->update(['denda_poin' => (float) $aturan['denda_bu_per_kasus']]);

        $billerIds = $wo->keys()->merge($salahBu->keys())->merge($ap2t->keys())->unique()->values();
        $absensi = app(LayananAbsensi::class)->rekap($periode, $billerIds->all(), $aturan);
        $sekarang = now();

        $baris = $billerIds->map(function ($billerId) use ($wo, $hariJ, $salahBu, $ap2t, $absensi, $aturan) {
            $kerja = ($wo->get($billerId) ?? collect())->keyBy('modul_type');
            $kasusBu = (int) ($salahBu[$billerId] ?? 0);
            $modul = [];

            if ($p = $kerja->get(ModulType::Pascabayar->value)) {
                $modul['pascabayar'] = $this->poin(max(0, $p->realisasi - $kasusBu), $p->eviden, $aturan) + [
                    'terbaca' => (int) $p->realisasi, 'jml_plg' => (int) $p->target_wo, 'kasus_bu' => $kasusBu,
                ];
            }
            if ($p = $kerja->get(ModulType::Prabayar->value)) {
                $modul['prabayar'] = $this->poin($p->realisasi, $p->eviden, $aturan) + ['pemeriksaan' => (int) $p->target_wo];
            }

            $jumlahPoin = array_sum(array_column($modul, 'poin'));
            $poinTugas = $aturan['mode_agregasi'] === 'rata_rata_modul' && count($modul) > 0
                ? $jumlahPoin / count($modul)
                : $jumlahPoin;

            $terbacaJ = (int) ($hariJ[$billerId] ?? 0);
            $bonusJ = $this->bonusHariJ($terbacaJ, $aturan);
            $dendaBu = $kasusBu * (float) $aturan['denda_bu_per_kasus'];
            $dendaAp2t = ($ap2t->get($billerId) ?? collect())->sum(fn ($k) => $this->dendaAp2t($k, $aturan));
            $hadir = $absensi->get($billerId) ?? ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'hari_kerja' => 0];
            $dendaAbsensi = $hadir['alpa'] * (float) ($aturan['denda_alpa_per_hari'] ?? 0);

            $pasca = $kerja->get(ModulType::Pascabayar->value);
            $jam = $pasca && $pasca->durasi_detik > 0 ? $pasca->durasi_detik / 3600 : null;

            return [
                'biller_id' => (int) $billerId,
                'poin_tugas' => round($poinTugas, 2),
                'poin_per_modul' => $modul,
                'poin_hari_j' => $bonusJ,
                'denda_bu' => round($dendaBu, 2),
                'denda_ap2t' => round($dendaAp2t, 2),
                'denda_absensi' => round($dendaAbsensi, 2),
                'total_denda' => round($dendaBu + $dendaAp2t + $dendaAbsensi, 2),
                'final_score' => round($poinTugas + $bonusJ - $dendaBu - $dendaAp2t - $dendaAbsensi, 2),
                'stan_valid' => (int) array_sum(array_column($modul, 'stan_valid')),
                'indikator' => [
                    'terbaca_hari_j' => $terbacaJ,
                    'kasus_bu' => $kasusBu,
                    'kasus_ap2t' => ($ap2t->get($billerId) ?? collect())->count(),
                    'absensi' => $hadir,
                    // Ditampilkan, tidak masuk skor.
                    'rasio_kelalaian' => $pasca && $pasca->realisasi > 0 ? round($kasusBu / $pasca->realisasi, 4) : null,
                    // WAKTU di berkas ACMT adalah selisih kalender (termasuk malam), bukan jam kerja.
                    'rasio_kecepatan' => $jam ? round($pasca->realisasi / $jam, 2) : null,
                ],
            ];
        });

        // Peringkat: skor ↓, total denda ↑, StanValid ↓.
        $urut = $baris->sort(fn ($a, $b) => [$b['final_score'], $a['total_denda'], $b['stan_valid']]
            <=> [$a['final_score'], $b['total_denda'], $a['stan_valid']])->values();

        DB::transaction(function () use ($periode, $urut, $aturan, $final, $sekarang) {
            MonthlyScore::query()->where('period_id', $periode->id)->whereNotIn('biller_id', $urut->pluck('biller_id'))->delete();

            foreach ($urut as $i => $b) {
                MonthlyScore::updateOrCreate(
                    ['biller_id' => $b['biller_id'], 'period_id' => $periode->id],
                    $b + [
                        'peringkat' => $i + 1,
                        'kategori' => $b['final_score'] < (float) $aturan['ambang_evaluasi'] ? 'perlu_evaluasi' : 'stabil',
                        'rules_snapshot' => $aturan,
                        'is_final' => $final,
                        'dihitung_at' => $sekarang,
                    ],
                );
            }
        });

        return MonthlyScore::query()->where('period_id', $periode->id)->orderBy('peringkat')->get();
    }

    /** @return array{stan_valid: int, eviden: int, poin: float} */
    private function poin(int|string $stanValid, int|string $eviden, array $aturan): array
    {
        $sv = (int) $stanValid;
        $ev = (int) $eviden;

        return [
            'stan_valid' => $sv,
            'eviden' => $ev,
            'poin' => round($sv * (float) $aturan['w_stan'] + $ev * (float) $aturan['w_eviden'], 2),
        ];
    }

    /** Bonus Hari J. Selama bonus_dasar_j atau poin_per_plg_j belum ditetapkan, bonus = 0. */
    private function bonusHariJ(int $terbacaJ, array $aturan): float
    {
        if ($terbacaJ === 0 || $aturan['bonus_dasar_j'] === null || $aturan['poin_per_plg_j'] === null) {
            return 0.0;
        }
        $nilai = (float) $aturan['bonus_dasar_j'] + $terbacaJ * (float) $aturan['poin_per_plg_j'];

        return round(min(max($nilai, (float) $aturan['bonus_min_j']), (float) $aturan['bonus_max_j']), 2);
    }

    private function dendaAp2t(object $kasus, array $aturan): float
    {
        $tingkat = (array) ($aturan['denda_ap2t_tingkat']['tingkat'] ?? []);
        if ($kasus->tingkat !== null && isset($tingkat[$kasus->tingkat])) {
            return (float) $tingkat[$kasus->tingkat];
        }

        return (float) $kasus->denda_poin > 0 ? (float) $kasus->denda_poin : (float) ($aturan['denda_ap2t_tingkat']['min'] ?? 0);
    }
}
