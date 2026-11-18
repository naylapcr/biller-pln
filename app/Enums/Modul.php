<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Modul data yang diunggah. Satu batch = satu modul.
 * Modul tanpa parser (menunggu contoh berkas) belum bisa diunggah.
 */
enum Modul: string
{
    use HasOptions;

    case PascabayarTunggal = 'pascabayar_tunggal';
    case PascabayarGanda = 'pascabayar_ganda';
    case BacaUlang = 'baca_ulang';
    case Prabayar = 'prabayar';
    case KoreksiAp2t = 'koreksi_ap2t';
    case Invoice = 'invoice';
    case Pdil = 'pdil';

    public function label(): string
    {
        return match ($this) {
            self::PascabayarTunggal => 'Pascabayar Tunggal',
            self::PascabayarGanda => 'Pascabayar Ganda Industri',
            self::BacaUlang => 'Baca Ulang Pascabayar',
            self::Prabayar => 'Prabayar',
            self::KoreksiAp2t => 'Koreksi AP2T',
            self::Invoice => 'Invoice',
            self::Pdil => 'PDIL',
        };
    }

    /** Sudah punya parser (contoh berkas sudah diterima). */
    public function tersedia(): bool
    {
        return in_array($this, self::tersediaSemua(), true);
    }

    /** @return list<self> */
    public static function tersediaSemua(): array
    {
        return [self::PascabayarTunggal, self::PascabayarGanda, self::BacaUlang, self::Prabayar];
    }
}
