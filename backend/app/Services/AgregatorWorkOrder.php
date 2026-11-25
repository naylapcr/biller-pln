<?php

namespace App\Services;

use App\Enums\ModulType;
use App\Models\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi ulang tabel agregat work_orders satu periode dari tabel rincian.
 *  - pascabayar: target = Σ JMLPLG, realisasi = Σ TERBACA, eviden = Σ JML FOTO BERHASIL (Tunggal + Ganda)
 *  - prabayar  : target = jumlah pemeriksaan, realisasi = KDBACA normal dan dibaca sesuai jadwal,
 *                eviden = koordinat valid di wilayah Pekanbaru
 */
class AgregatorWorkOrder
{
    public function __construct(private readonly ParameterSkor $parameter) {}

    /**
     * Jadwal baca prabayar: tanggal prabayar_mulai–prabayar_selesai di bulan sebelum BLTH
     * (BLTH 202611 → 5–20 Oktober 2026), sampai jadwal per RBM tersedia.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function jadwalPrabayar(Period $periode, array $aturan): array
    {
        $bulan = $periode->awalBulan()->subMonthNoOverflow();
        $akhirBulan = $bulan->endOfMonth()->day;
        $mulai = max(1, min((int) $aturan['prabayar_mulai'], $akhirBulan));
        $selesai = max($mulai, min((int) $aturan['prabayar_selesai'], $akhirBulan));

        return [$bulan->setDay($mulai)->startOfDay(), $bulan->setDay($selesai)->endOfDay()];
    }

    public function bangun(Period $periode): void
    {
        $aturan = $this->parameter->untuk($periode);
        $normal = (array) ($aturan['kdbaca_normal'] ?? []);
        [$mulai, $selesai] = self::jadwalPrabayar($periode, $aturan);
        $sekarang = now();

        DB::transaction(function () use ($periode, $normal, $mulai, $selesai, $sekarang) {
            DB::table('work_orders')->where('period_id', $periode->id)->delete();

            $pasca = DB::table('reading_logs')
                ->where('period_id', $periode->id)->whereNotNull('biller_id')
                ->groupBy('biller_id')
                ->select('biller_id')
                ->selectRaw('SUM(jml_plg) as target_wo, SUM(terbaca) as realisasi, SUM(foto_berhasil) as eviden, COALESCE(SUM(durasi_detik), 0) as durasi_detik')
                ->get()
                ->map(fn ($r) => (array) $r + ['modul_type' => ModulType::Pascabayar->value]);

            $sesuai = DB::table('prepaid_inspections as x')->selectRaw('COUNT(*)')->whereColumn('x.biller_id', 'p.biller_id')
                ->where('x.period_id', $periode->id)->whereIn('x.kd_baca', $normal ?: ['__tidak_ada__'])
                ->whereBetween('x.tgl_baca', [$mulai->toDateTimeString(), $selesai->toDateTimeString()]);
            $pra = DB::table('prepaid_inspections as p')
                ->where('p.period_id', $periode->id)->whereNotNull('p.biller_id')
                ->groupBy('p.biller_id')
                ->select('p.biller_id')
                ->selectRaw('COUNT(*) as target_wo')
                ->selectSub($sesuai, 'realisasi')
                ->selectRaw('SUM(CASE WHEN p.koordinat_valid THEN 1 ELSE 0 END) as eviden')
                ->get()
                ->map(fn ($r) => (array) $r + ['modul_type' => ModulType::Prabayar->value, 'durasi_detik' => 0]);

            $baris = $pasca->concat($pra)->map(fn (array $r) => [
                'biller_id' => $r['biller_id'],
                'period_id' => $periode->id,
                'modul_type' => $r['modul_type'],
                'target_wo' => (int) $r['target_wo'],
                'realisasi' => (int) $r['realisasi'],
                'eviden' => (int) $r['eviden'],
                'durasi_detik' => (int) $r['durasi_detik'],
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);

            foreach ($baris->chunk(500) as $potongan) {
                DB::table('work_orders')->insert($potongan->values()->all());
            }
        });
    }
}
