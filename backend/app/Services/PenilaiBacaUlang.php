<?php

namespace App\Services;

use App\Enums\HasilBacaUlang;
use Carbon\CarbonInterface;

/**
 * Penilaian kasus Baca Ulang (PRD 6.2):
 *  - tidak_dinilai : BELUM BACA, atau stan, tanggal, atau daya tidak lengkap
 *  - salah_turun   : stan BU lebih kecil dari stan awal
 *  - sama          : stan tidak berubah; biller benar, pemakaian tinggi memang nyata
 *  - salah_lebih   : kenaikan melebihi batas fisik meter = daya (kVA) × jam di antara dua pembacaan, + toleransi
 *  - wajar         : naik dalam batas fisik
 * Contoh: pelanggan 1.300 VA dibaca ulang 12 jam kemudian → batas 15,6 kWh (+ toleransi 1 kWh).
 * Kasus sisipan (dibaca di luar jendela hari baca RBM) ditandai terpisah dan tidak memotong skor.
 */
class PenilaiBacaUlang
{
    /** @return array{hasil: HasilBacaUlang, batas_kwh: float|null} */
    public static function nilai(
        ?string $statusBu,
        ?float $stanAwal,
        ?float $stanBu,
        ?CarbonInterface $tglBaca,
        ?CarbonInterface $tglBacaBu,
        ?int $dayaVa,
        float $toleransiKwh = 1.0,
    ): array {
        if (strtoupper((string) $statusBu) !== 'SUDAH BACA' || $stanAwal === null || $stanBu === null
            || $tglBaca === null || $tglBacaBu === null || ! $dayaVa) {
            return ['hasil' => HasilBacaUlang::TidakDinilai, 'batas_kwh' => null];
        }

        $jam = ($tglBacaBu->getTimestamp() - $tglBaca->getTimestamp()) / 3600;
        $batas = round(max(0, $jam) * $dayaVa / 1000, 2);

        // Galat kecil agar pembulatan float tidak mengubah hasil (contoh 1015,6 − 1000).
        $hasil = match (true) {
            $stanBu < $stanAwal - 1e-6 => HasilBacaUlang::SalahTurun,
            abs($stanBu - $stanAwal) <= 1e-6 => HasilBacaUlang::Sama,
            $stanBu - $stanAwal > $batas + $toleransiKwh + 1e-6 => HasilBacaUlang::SalahLebih,
            default => HasilBacaUlang::Wajar,
        };

        return ['hasil' => $hasil, 'batas_kwh' => $batas];
    }
}
