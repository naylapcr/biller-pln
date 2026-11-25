<?php

namespace App\Models;

use App\Enums\HasilBacaUlang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pembacaan ulang (Baca Ulang Pascabayar) beserta hasil penilaiannya. */
class RereadCase extends Model
{
    protected $fillable = [
        'period_id', 'biller_id', 'petugas_login', 'idpel', 'tarif', 'daya_va', 'kode_rbm', 'hari_baca', 'tgl_baca',
        'stan_awal', 'pemkwh_awal', 'tgl_baca_bu', 'stan_bu', 'pemkwh_bu', 'status_bu', 'batas_kwh', 'sisipan', 'hasil', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'hasil' => HasilBacaUlang::class,
            'daya_va' => 'integer',
            'tgl_baca' => 'datetime',
            'tgl_baca_bu' => 'datetime',
            'stan_awal' => 'float',
            'pemkwh_awal' => 'float',
            'stan_bu' => 'float',
            'pemkwh_bu' => 'float',
            'batas_kwh' => 'float',
            'sisipan' => 'boolean',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }
}
