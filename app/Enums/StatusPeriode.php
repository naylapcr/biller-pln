<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusPeriode: string
{
    use HasOptions;

    case Terbuka = 'terbuka';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Ditutup => 'Ditutup',
        };
    }
}
