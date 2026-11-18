<?php

namespace App\Models;

use Database\Factories\BillerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Biller extends Model
{
    /** @use HasFactory<BillerFactory> */
    use HasFactory;

    protected $fillable = ['nip', 'nama', 'zona_wilayah', 'kode_petugas_acmt', 'login_acmt', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Login ACMT dan kode petugas selalu disimpan dalam bentuk baku.
        static::saving(function (Biller $b) {
            $b->login_acmt = strtolower(trim($b->login_acmt));
            $b->kode_petugas_acmt = strtoupper(trim($b->kode_petugas_acmt));
        });
    }

    public function scopeAktif(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(BillerAlias::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(MonthlyScore::class);
    }
}
