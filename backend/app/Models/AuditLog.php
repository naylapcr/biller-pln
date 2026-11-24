<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Jejak audit. Tidak pernah diubah atau dihapus. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'aksi', 'objek_type', 'objek_id', 'sebelum', 'sesudah', 'ip'];

    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak boleh dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
