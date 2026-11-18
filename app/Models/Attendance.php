<?php

namespace App\Models;

use App\Enums\StatusAbsensi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Absensi harian biller: diisi sendiri (hadir/izin/sakit) atau dikoreksi Koordinator Admin. */
class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = ['biller_id', 'tanggal', 'status', 'keterangan', 'sumber', 'dicatat_oleh', 'batch_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'immutable_date',
            'status' => StatusAbsensi::class,
        ];
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Biller::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
