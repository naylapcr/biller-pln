<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Skor bulanan satu biller (PRD 6.1). Skor akhir = rata-rata nilai modul + bonus Hari J, bisa > 100. */
class MonthlyScore extends Model
{
    protected $fillable = [
        'biller_id', 'period_id', 'nilai_per_modul', 'nilai_pasca_dasar', 'plg_per_jam', 'nilai_kecepatan', 'rasio_bu',
        'rasio_ap2t', 'rata_rata_modul', 'bonus_hari_j', 'final_score', 'terbaca', 'peringkat', 'kategori',
        'rules_snapshot', 'is_final', 'dihitung_at',
    ];

    protected function casts(): array
    {
        return [
            'nilai_per_modul' => 'array',
            'nilai_pasca_dasar' => 'float',
            'plg_per_jam' => 'float',
            'nilai_kecepatan' => 'float',
            'rasio_bu' => 'float',
            'rasio_ap2t' => 'float',
            'rata_rata_modul' => 'float',
            'bonus_hari_j' => 'float',
            'final_score' => 'float',
            'terbaca' => 'integer',
            'peringkat' => 'integer',
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
