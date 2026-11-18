<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Audit trail (Super Admin). */
class AuditController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $f = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'aksi' => ['nullable', 'string', 'max:40'],
        ]);

        return Inertia::render('Pengaturan/Audit/Index', [
            'logs' => AuditLog::query()
                ->with('user:id,nama,username_nip')
                ->when($f['dari'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
                ->when($f['sampai'] ?? null, fn ($q, $d) => $q->where('created_at', '<', now()->parse($d)->addDay()))
                ->when($f['aksi'] ?? null, fn ($q, $a) => $q->where('aksi', $a))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (AuditLog $l) => [
                    'id' => $l->id,
                    'waktu' => $l->created_at?->toIso8601String(),
                    'user' => $l->user?->nama,
                    'aksi' => $l->aksi,
                    'objek' => trim(($l->objek_type ?? '').' '.($l->objek_id ?? '')),
                    'sebelum' => $l->sebelum,
                    'sesudah' => $l->sesudah,
                    'ip' => $l->ip,
                ]),
            'filters' => (object) $f,
            'aksiOptions' => AuditLog::query()->distinct()->orderBy('aksi')->pluck('aksi'),
        ]);
    }
}
