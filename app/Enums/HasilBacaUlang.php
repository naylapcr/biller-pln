<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HasilBacaUlang: string
{
    use HasOptions;

    case SalahTurun = 'salah_turun';
    case SalahLebih = 'salah_lebih';
    case Wajar = 'wajar';
    case TidakDinilai = 'tidak_dinilai';

    public function label(): string
    {
        return match ($this) {
            self::SalahTurun => 'Stan BU lebih kecil',
            self::SalahLebih => 'Kenaikan melebihi batas',
            self::Wajar => 'Wajar',
            self::TidakDinilai => 'Tidak dinilai',
        };
    }

    public function salah(): bool
    {
        return $this === self::SalahTurun || $this === self::SalahLebih;
    }
}
