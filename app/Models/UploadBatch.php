<?php

namespace App\Models;

use App\Enums\Modul;
use App\Enums\StatusBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu modul dari satu berkas unggahan. Berkas dengan beberapa sheet menghasilkan
 * beberapa batch dengan unggahan_id yang sama, dan tiap batch di-commit sendiri.
 */
class UploadBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'unggahan_id', 'period_id', 'modul', 'modul_diminta', 'sheet', 'file_name', 'storage_path', 'checksum_sha256',
        'status', 'ringkasan', 'diunggah_oleh', 'committed_at', 'superseded_by',
    ];

    protected function casts(): array
    {
        return [
            'modul' => Modul::class,
            'modul_diminta' => Modul::class,
            'status' => StatusBatch::class,
            'ringkasan' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    public function scopeCommitted(Builder $query): void
    {
        $query->where('status', StatusBatch::Committed->value);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(UploadRowIssue::class, 'batch_id');
    }

    public function stagingRows(): HasMany
    {
        return $this->hasMany(StagingRow::class, 'batch_id');
    }

    /** Batch lain dari berkas yang sama. */
    public function saudara(): HasMany
    {
        return $this->hasMany(self::class, 'unggahan_id', 'unggahan_id');
    }
}
