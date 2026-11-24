<?php

namespace App\Models;

use App\Enums\TingkatIssue;
use Illuminate\Database\Eloquent\Model;

class UploadRowIssue extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'sheet', 'row_number', 'kode', 'tingkat', 'pesan', 'nilai'];

    protected function casts(): array
    {
        return [
            'tingkat' => TingkatIssue::class,
            'nilai' => 'array',
        ];
    }
}
