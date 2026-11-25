<?php

namespace App\Models;

use App\Enums\SumberKoreksi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kasus kesalahan: Baca Ulang terbukti salah (BU) atau Koreksi AP2T. Hanya penyebab = biller yang masuk rasio. */
class Correction extends Model
{
    protected $fillable = [
        'biller_id', 'period_id', 'petugas_login', 'idpel', 'sumber', 'penyebab', 'stan_awal', 'stan_benar',
        'reread_case_id', 'batch_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'sumber' => SumberKoreksi::class,
            'stan_awal' => 'float',
            'stan_benar' => 'float',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }

    public function rereadCase(): BelongsTo
    {
        return $this->belongsTo(RereadCase::class);
    }
}
