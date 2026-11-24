<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TingkatIssue: string
{
    use HasOptions;

    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'Error',
            self::Warning => 'Warning',
            self::Info => 'Info',
        };
    }
}
