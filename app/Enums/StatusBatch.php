<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusBatch: string
{
    use HasOptions;

    case Diproses = 'diproses';
    case SiapCommit = 'siap_commit';
    case Gagal = 'gagal';
    case Committed = 'committed';
    case Dibatalkan = 'dibatalkan';
    case Digantikan = 'digantikan';

    public function label(): string
    {
        return match ($this) {
            self::Diproses => 'Diproses',
            self::SiapCommit => 'Siap commit',
            self::Gagal => 'Gagal validasi',
            self::Committed => 'Committed',
            self::Dibatalkan => 'Dibatalkan',
            self::Digantikan => 'Digantikan',
        };
    }
}
