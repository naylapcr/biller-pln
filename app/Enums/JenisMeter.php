<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JenisMeter: string
{
    use HasOptions;

    case Tunggal = 'tunggal';
    case GandaIndustri = 'ganda_industri';

    public function label(): string
    {
        return match ($this) {
            self::Tunggal => 'Tunggal',
            self::GandaIndustri => 'Ganda Industri',
        };
    }
}
