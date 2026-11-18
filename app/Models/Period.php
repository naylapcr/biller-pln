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
    protected $fillable = ['kode', 'status', 'absensi_mulai', 'absensi_selesai', 'ditutup_at', 'ditutup_oleh'];

    protected function casts(): array
    {
        return [
            'status' => StatusPeriode::class,
            'absensi_mulai' => 'immutable_date',
            'absensi_selesai' => 'immutable_date',
            'ditutup_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Rentang absensi bawaan: bulan kalender BLTH (202611 → 1–30 November 2026).
        static::creating(function (Period $p) {
            $awal = CarbonImmutable::createFromFormat('Ym', $p->kode)->startOfMonth();
            $p->absensi_mulai ??= $awal;
            $p->absensi_selesai ??= $awal->endOfMonth();
        });
    }

    /** Periode terbuka yang rentang absensinya mencakup tanggal ini. */
    public static function untukTanggal(CarbonImmutable $tanggal): ?self
    {
        return static::query()->terbuka()
            ->whereDate('absensi_mulai', '<=', $tanggal->toDateString())
            ->whereDate('absensi_selesai', '>=', $tanggal->toDateString())
            ->orderByDesc('kode')
            ->first();
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
        return CarbonImmutable::createFromFormat('Ym', $this->kode)->startOfMonth()->locale('id')->translatedFormat('F Y');
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
