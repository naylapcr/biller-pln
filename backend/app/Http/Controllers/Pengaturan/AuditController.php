<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\UnduhCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Audit trail (Super Admin) dan ekspor CSV-nya. */
class AuditController extends Controller
{
    /** @return array<string, string|null> */
    private function filter(Request $request): array
    {
        return $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'aksi' => ['nullable', 'string', 'max:40'],
            'objek' => ['nullable', 'string', 'max:60'],
        ]);
    }

    /** @param  array<string, string|null>  $f */
    private function kueri(array $f): Builder
    {
        return AuditLog::query()
            ->with('user:id,nama,username_nip')
            ->when($f['dari'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($f['sampai'] ?? null, fn ($q, $d) => $q->where('created_at', '<', now()->parse($d)->addDay()))
            ->when($f['aksi'] ?? null, fn ($q, $a) => $q->where('aksi', $a))
            ->when($f['objek'] ?? null, fn ($q, $o) => $q->where('objek_type', $o))
            ->latest('id');
    }

    public function csv(Request $request): StreamedResponse
    {
        $json = fn (?array $v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        return UnduhCsv::kirim(
            'audit-trail-'.now()->format('Ymd-His').'.csv',
            ['WAKTU', 'PENGGUNA', 'AKSI', 'OBJEK', 'ID', 'SEBELUM', 'SESUDAH', 'IP', 'PERANGKAT'],
            $this->kueri($this->filter($request))->lazy(500)->map(fn (AuditLog $l) => [
                $l->created_at?->format('Y-m-d H:i:s'), $l->user?->nama ?? 'Sistem', $l->aksi, $l->objek_type, $l->objek_id,
                $json($l->sebelum), $json($l->sesudah), $l->ip, $l->user_agent,
            ]),
        );
    }

    public function index(Request $request): Response
    {
        $f = $this->filter($request);

        return Inertia::render('SuperAdmin/AuditTrail', [
            'logs' => $this->kueri($f)
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
                    'user_agent' => $l->user_agent,
                ]),
            'filters' => (object) $f,
            'aksiOptions' => AuditLog::query()->distinct()->orderBy('aksi')->pluck('aksi'),
            'objekOptions' => AuditLog::query()->whereNotNull('objek_type')->distinct()->orderBy('objek_type')->pluck('objek_type'),
        ]);
    }
}
