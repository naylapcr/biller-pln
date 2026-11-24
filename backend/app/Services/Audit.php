<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Jejak audit (PRD 9): login, unggah, commit, rollback, ubah parameter, alias baru,
 * tutup periode, dan CRUD akun, dengan nilai sebelum dan sesudah.
 */
class Audit
{
    public static function catat(string $aksi, Model|string|null $objek = null, ?array $sebelum = null, ?array $sesudah = null, ?int $userId = null): void
    {
        [$type, $id] = match (true) {
            $objek instanceof Model => [class_basename($objek), (string) $objek->getKey()],
            is_string($objek) => [$objek, null],
            default => [null, null],
        };

        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'aksi' => $aksi,
            'objek_type' => $type,
            'objek_id' => $id,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
