<?php

namespace App\Models;

use App\Enums\StatusAbsensi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absensi harian Biller dan Koordinator Admin (PRD 4). sumber = mandiri (diisi sendiri) atau
 * sistem (alpa otomatis oleh MarkAbsentJob). Koreksi hanya oleh Super Admin dengan alasan wajib.
 */
class Attendance extends Model
{
    protected $table = 'attendances';

    protected $fillable = [
        'user_id', 'tanggal', 'status', 'absen_at', 'terlambat_menit', 'lat', 'lng', 'jarak_m', 'akurasi_m', 'ip', 'perangkat',
        'pulang_at', 'pulang_cepat_menit', 'pulang_lat', 'pulang_lng', 'pulang_jarak_m', 'pulang_ip', 'keterangan', 'foto_path', 'sumber', 'dikoreksi_oleh', 'alasan_diubah',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'immutable_date',
            'status' => StatusAbsensi::class,
            'absen_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'jarak_m' => 'float',
            'akurasi_m' => 'float',
            'terlambat_menit' => 'integer',
            'pulang_at' => 'datetime',
            'pulang_cepat_menit' => 'integer',
            'pulang_lat' => 'float',
            'pulang_lng' => 'float',
            'pulang_jarak_m' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pengoreksi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }
}
