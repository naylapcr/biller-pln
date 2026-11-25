<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Jejak audit (PRD 9). Setiap aksi tercatat dengan kode aksi, nilai sebelum dan sesudah, IP, dan perangkat.
 */
class Audit
{
    // Kode aksi dari PRD.
    public const LOGIN = 'LOGIN';
    public const UPDATE_RULE = 'UPDATE_RULE';
    public const COMMIT_BATCH = 'COMMIT_BATCH';
    public const ROLLBACK_BATCH = 'ROLLBACK_BATCH';
    public const ADD_ALIAS = 'ADD_ALIAS';
    public const CORRECT_ATT = 'CORRECT_ATT';
    public const CREATE_USER = 'CREATE_USER';
    public const UPDATE_USER = 'UPDATE_USER';
    public const UNLOCK_USER = 'UNLOCK_USER';
    public const CHANGE_PASSWORD = 'CHANGE_PASSWORD';
    public const CLOSE_PERIOD = 'CLOSE_PERIOD';

    // Aksi lain yang juga dicatat.
    public const UPLOAD_FILE = 'UPLOAD_FILE';
    public const CANCEL_BATCH = 'CANCEL_BATCH';
    public const OPEN_PERIOD = 'OPEN_PERIOD';
    public const RECALCULATE = 'RECALCULATE';
    public const DELETE_USER = 'DELETE_USER';
    public const LOCK_USER = 'LOCK_USER';
    public const CREATE_BILLER = 'CREATE_BILLER';
    public const UPDATE_BILLER = 'UPDATE_BILLER';
    public const DELETE_BILLER = 'DELETE_BILLER';
    public const DELETE_ALIAS = 'DELETE_ALIAS';
    public const ATTEND = 'ATTEND';
    public const UPDATE_PROFILE = 'UPDATE_PROFILE';
    public const EXPORT_REPORT = 'EXPORT_REPORT';
    public const DOWNLOAD_FILE = 'DOWNLOAD_FILE';

    public static function catat(string $aksi, Model|string|null $objek = null, ?array $sebelum = null, ?array $sesudah = null, ?int $userId = null): void
    {
        [$type, $id] = match (true) {
            $objek instanceof Model => [class_basename($objek), (string) $objek->getKey()],
            is_string($objek) => [$objek, null],
            default => [null, null],
        };
        $konsol = app()->runningInConsole() && ! app()->runningUnitTests();

        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'aksi' => $aksi,
            'objek_type' => $type,
            'objek_id' => $id,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'ip' => $konsol ? null : request()->ip(),
            'user_agent' => $konsol ? null : Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }
}
