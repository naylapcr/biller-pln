<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu parameter skor untuk satu periode. */
class ScoringRule extends Model
{
    protected $fillable = ['period_id', 'kunci', 'nilai', 'diubah_oleh'];

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
