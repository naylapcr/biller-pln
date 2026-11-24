<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'username_nip',
        'nama',
        'password',
        'role',
        'biller_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Baris master biller milik akun ini (wajib untuk peran biller). */
    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if (($role instanceof Role ? $role : Role::tryFrom($role)) === $this->role) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin, Role::SuperAdmin);
    }
}
