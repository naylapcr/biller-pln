<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SumberKoreksi: string
{
    use HasOptions;

    case Ap2t = 'AP2T';
    case BacaUlang = 'BU';

    public function label(): string
    {
        return match ($this) {
            self::Ap2t => 'Koreksi AP2T',
            self::BacaUlang => 'Baca Ulang',
        };
    }
}
