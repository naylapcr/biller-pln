<?php

namespace App\Services;

use App\Enums\HasilBacaUlang;
use Carbon\CarbonInterface;

/**
 * Aturan kasus Baca Ulang terbukti salah (usulan PRD 6.3, menunggu konfirmasi):
 *  - tidak_dinilai : BELUM BACA, atau stan, tanggal, atau daya tidak lengkap
 *  - salah_turun   : stan BU lebih kecil dari stan awal
 *  - salah_lebih   : kenaikan stan melebihi batas fisik meter = daya (kVA) × jam di antara dua pembacaan
 *  - wajar         : selain itu
 * Contoh: pelanggan 1.300 VA dibaca ulang 12 jam kemudian → batas 15,6 kWh.
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
    ): array {
        if (strtoupper((string) $statusBu) !== 'SUDAH BACA' || $stanAwal === null || $stanBu === null
            || $tglBaca === null || $tglBacaBu === null || ! $dayaVa) {
            return ['hasil' => HasilBacaUlang::TidakDinilai, 'batas_kwh' => null];
        }

        $jam = ($tglBacaBu->getTimestamp() - $tglBaca->getTimestamp()) / 3600;
        $batas = round(max(0, $jam) * $dayaVa / 1000, 2);

        // Toleransi kecil agar galat pembulatan float tidak mengubah hasil (contoh 1015,6 − 1000).
        $hasil = match (true) {
            $stanBu < $stanAwal => HasilBacaUlang::SalahTurun,
            $stanBu - $stanAwal > $batas + 1e-6 => HasilBacaUlang::SalahLebih,
            default => HasilBacaUlang::Wajar,
        };

        return ['hasil' => $hasil, 'batas_kwh' => $batas];
    }
}
