<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Role: string
{
    use HasOptions;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Biller = 'biller';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Koordinator Admin',
            self::Biller => 'Biller',
        };
    }

    /** Nama route tujuan setelah login. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Biller => 'saya.skor',
            default => 'dashboard',
        };
    }
}
