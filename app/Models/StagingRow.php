<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Baris hasil parsing yang menunggu commit. Dihapus setelah commit atau setelah 30 hari. */
class StagingRow extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'sheet', 'row_number', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
