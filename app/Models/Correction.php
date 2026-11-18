<?php

namespace App\Models;

use App\Enums\SumberKoreksi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kasus denda: dari Baca Ulang yang terbukti salah (BU) atau dari Koreksi AP2T. */
class Correction extends Model
{
    protected $fillable = [
        'biller_id', 'period_id', 'petugas_login', 'idpel', 'sumber', 'stan_awal', 'stan_benar', 'tingkat',
        'denda_poin', 'reread_case_id', 'batch_id', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'sumber' => SumberKoreksi::class,
            'stan_awal' => 'float',
            'stan_benar' => 'float',
            'denda_poin' => 'float',
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
