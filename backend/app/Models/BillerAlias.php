<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Login ACMT tambahan untuk satu biller, contoh login pengganti seperti 18111.ilmenhadi. */
class BillerAlias extends Model
{
    protected $fillable = ['biller_id', 'login_acmt', 'berlaku_mulai'];

    protected function casts(): array
    {
        return ['berlaku_mulai' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(fn (BillerAlias $a) => $a->login_acmt = strtolower(trim($a->login_acmt)));
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }
}
