<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusAbsensi: string
{
    use HasOptions;

    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
