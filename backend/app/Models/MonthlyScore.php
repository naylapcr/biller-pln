<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyScore extends Model
{
    protected $fillable = [
        'biller_id', 'period_id', 'poin_tugas', 'poin_per_modul', 'poin_hari_j', 'denda_bu', 'denda_ap2t', 'denda_absensi',
        'total_denda', 'final_score', 'stan_valid', 'peringkat', 'kategori', 'indikator', 'rules_snapshot',
        'is_final', 'dihitung_at',
    ];

    protected function casts(): array
    {
        return [
            'poin_tugas' => 'float',
            'poin_per_modul' => 'array',
            'poin_hari_j' => 'float',
            'denda_bu' => 'float',
            'denda_ap2t' => 'float',
            'denda_absensi' => 'float',
            'total_denda' => 'float',
            'final_score' => 'float',
            'stan_valid' => 'integer',
            'peringkat' => 'integer',
            'indikator' => 'array',
            'rules_snapshot' => 'array',
            'is_final' => 'boolean',
            'dihitung_at' => 'datetime',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
