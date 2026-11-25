<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Batas gagal login berturut-turut sebelum akun terkunci (PRD 8). */
    public const MAKS_GAGAL_LOGIN = 5;

    protected $fillable = [
        'username_nip',
        'nama',
        'password',
        'role',
        'biller_id',
        'is_active',
        'gagal_login',
        'terkunci_at',
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
            'gagal_login' => 'integer',
            'terkunci_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Baris master biller milik akun ini (wajib untuk peran biller). */
    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function terkunci(): bool
    {
        return $this->terkunci_at !== null;
    }

    /** Biller dan Koordinator Admin wajib absen harian; Super Admin tidak. */
    public function wajibAbsen(): bool
    {
        return $this->hasRole(Role::Biller, Role::Admin);
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

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }
}
