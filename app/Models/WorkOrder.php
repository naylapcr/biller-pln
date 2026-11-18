<?php

namespace App\Models;

use App\Enums\ModulType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Agregat pekerjaan per biller, periode, dan jenis. Diisi ulang dari tabel rincian setiap commit. */
class WorkOrder extends Model
{
    protected $fillable = ['biller_id', 'period_id', 'modul_type', 'target_wo', 'realisasi', 'eviden', 'durasi_detik'];

    protected function casts(): array
    {
        return [
            'modul_type' => ModulType::class,
            'target_wo' => 'integer',
            'realisasi' => 'integer',
            'eviden' => 'integer',
            'durasi_detik' => 'integer',
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }
}
