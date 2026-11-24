<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Jenis pekerjaan di tabel agregat work_orders. */
enum ModulType: string
{
    use HasOptions;

    case Prabayar = 'prabayar';
    case Pascabayar = 'pascabayar';
    case Invoice = 'invoice';
    case Pdil = 'pdil';

    public function label(): string
    {
        return match ($this) {
            self::Prabayar => 'Prabayar',
            self::Pascabayar => 'Pascabayar',
            self::Invoice => 'Invoice',
            self::Pdil => 'PDIL',
        };
    }
}
