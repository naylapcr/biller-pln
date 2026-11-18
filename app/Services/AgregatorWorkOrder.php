<?php

namespace App\Services;

use App\Enums\ModulType;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi ulang tabel agregat work_orders satu periode dari tabel rincian.
 *  - pascabayar: target = Σ JMLPLG, realisasi = Σ TERBACA, eviden = Σ JML FOTO BERHASIL (Tunggal + Ganda)
 *  - prabayar  : target = jumlah pemeriksaan, realisasi = KDBACA normal, eviden = koordinat valid
 */
class AgregatorWorkOrder
{
    public function __construct(private readonly ParameterSkor $parameter) {}

    public function bangun(Period $periode): void
    {
        $normal = (array) ($this->parameter->untuk($periode)['kdbaca_normal'] ?? []);
        $sekarang = now();

        DB::transaction(function () use ($periode, $normal, $sekarang) {
            DB::table('work_orders')->where('period_id', $periode->id)->delete();

            $pasca = DB::table('reading_logs')
                ->where('period_id', $periode->id)->whereNotNull('biller_id')
                ->groupBy('biller_id')
                ->select('biller_id')
                ->selectRaw('SUM(jml_plg) as target_wo, SUM(terbaca) as realisasi, SUM(foto_berhasil) as eviden, COALESCE(SUM(durasi_detik), 0) as durasi_detik')
                ->get()
                ->map(fn ($r) => (array) $r + ['modul_type' => ModulType::Pascabayar->value]);

            $kdNormal = DB::table('prepaid_inspections as x')->selectRaw('COUNT(*)')->whereColumn('x.biller_id', 'p.biller_id')
                ->where('x.period_id', $periode->id)->whereIn('x.kd_baca', $normal ?: ['__tidak_ada__']);
            $pra = DB::table('prepaid_inspections as p')
                ->where('p.period_id', $periode->id)->whereNotNull('p.biller_id')
                ->groupBy('p.biller_id')
                ->select('p.biller_id')
                ->selectRaw('COUNT(*) as target_wo')
                ->selectSub($kdNormal, 'realisasi')
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
