<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pemeriksaan fisik meter prabayar. Kolom yang tidak dipetakan disimpan utuh di raw. */
class PrepaidInspection extends Model
{
    protected $fillable = [
        'period_id', 'biller_id', 'petugas_login', 'idpel', 'no_meter', 'tarif', 'daya_va', 'kdrbm', 'kd_kelompok',
        'tgl_baca', 'kd_baca', 'segel', 'lcd', 'keypad', 'relay', 'status_temper', 'kwh_sisa', 'latitude',
        'longitude', 'koordinat_valid', 'raw', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'daya_va' => 'integer',
            'tgl_baca' => 'datetime',
            'kwh_sisa' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'koordinat_valid' => 'boolean',
            'raw' => 'array',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }
}
