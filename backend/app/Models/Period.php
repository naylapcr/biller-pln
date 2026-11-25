<?php

namespace App\Models;

use App\Enums\StatusPeriode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Periode penilaian = BLTH (bulan tahun rekening), bukan bulan tanggal baca. */
class Period extends Model
{
    protected $fillable = ['kode', 'status', 'ditutup_at', 'ditutup_oleh'];

    protected function casts(): array
    {
        return [
            'status' => StatusPeriode::class,
            'ditutup_at' => 'datetime',
        ];
    }

    public function scopeTerbuka(Builder $query): void
    {
        $query->where('status', StatusPeriode::Terbuka->value);
    }

    public function terbuka(): bool
    {
        return $this->status === StatusPeriode::Terbuka;
    }

    /** "202611" → "November 2026". */
    public function nama(): string
    {
        return $this->awalBulan()->locale('id')->translatedFormat('F Y');
    }

    /** Tanggal 1 bulan BLTH, contoh 202611 → 1 November 2026. */
    public function awalBulan(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Ym', $this->kode, config('app.timezone'))->startOfMonth();
    }

    public static function kode(string $kode): ?self
    {
        return static::query()->where('kode', $kode)->first();
    }

    /** Periode yang ditampilkan bila pengguna tidak memilih: terbuka terbaru, atau terbaru. */
    public static function bawaan(): ?self
    {
        return static::query()->terbuka()->orderByDesc('kode')->first()
            ?? static::query()->orderByDesc('kode')->first();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(UploadBatch::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ScoringRule::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(MonthlyScore::class);
    }

    public function penutup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }
}
