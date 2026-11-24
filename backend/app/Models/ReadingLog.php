<?php

namespace App\Models;

use App\Enums\JenisMeter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rekap pembacaan pascabayar per petugas per KODE_RBM (satu hari baca). */
class ReadingLog extends Model
{
    protected $fillable = [
        'period_id', 'biller_id', 'petugas_login', 'kode_petugas', 'kode_rbm', 'hari_baca', 'jenis_meter',
        'jml_plg', 'terbaca', 'belum_baca', 'foto_berhasil', 'mulai_at', 'selesai_at', 'durasi_detik', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'jenis_meter' => JenisMeter::class,
            'jml_plg' => 'integer',
            'terbaca' => 'integer',
            'belum_baca' => 'integer',
            'foto_berhasil' => 'integer',
            'durasi_detik' => 'integer',
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }
}
