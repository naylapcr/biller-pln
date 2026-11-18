<?php

namespace App\Http\Controllers;

use App\Enums\Modul;
use App\Enums\StatusBatch;
use App\Enums\TingkatIssue;
use App\Exceptions\KonflikBatch;
use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Period;
use App\Models\UploadBatch;
use App\Models\UploadRowIssue;
use App\Services\PengelolaBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unggah berkas ACMT/AP2T, pratinjau, commit, batal, dan rollback (PRD bagian 4 dan 8).
 * Konflik (batch tidak siap, periode ditutup) dibalas 409 untuk permintaan JSON,
 * atau pesan galat untuk halaman.
 */
class UnggahController extends Controller
{
    use PilihPeriode;

    public function __construct(private readonly PengelolaBatch $pengelola) {}

    public function index(Request $request): Response
    {
        $request->validate(['modul' => ['nullable', Rule::enum(Modul::class)]]);
        $periode = $this->periode($request);

        $batches = UploadBatch::query()
            ->when($periode, fn ($q) => $q->where('period_id', $periode->id))
            ->when($request->input('modul'), fn ($q, $m) => $q->where('modul', $m))
            ->with('pengunggah:id,nama', 'period:id,kode')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UploadBatch $b) => $this->ringkasBatch($b));

        return Inertia::render('Unggah/Index', [
            'periode' => $this->infoPeriode($periode),
            'periodeOptions' => $this->opsiPeriode(),
            'periodeTerbuka' => Period::query()->terbuka()->orderByDesc('kode')->get()->map(fn (Period $p) => ['value' => $p->kode, 'label' => $p->nama()]),
            'modulOptions' => Modul::options(Modul::tersediaSemua()),
            'modulMenunggu' => collect(Modul::cases())->reject(fn (Modul $m) => $m->tersedia())->map->label()->values(),
            'filters' => (object) $request->only(['modul']),
            'batches' => $batches,
            'maksKb' => (int) config('sipermon.maks_berkas_kb'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:'.config('sipermon.maks_berkas_kb')],
            'periode' => ['required', 'string', Rule::exists('periods', 'kode')],
            'modul' => ['nullable', Rule::enum(Modul::class)->only(Modul::tersediaSemua())],
        ], [
            'file.extensions' => 'Berkas harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran berkas maksimal 20 MB.',
        ]);

        try {
            $batch = $this->pengelola->unggah(
                $request->file('file'),
                Period::kode($data['periode']),
                isset($data['modul']) ? Modul::from($data['modul']) : null,
                $request->user(),
            );
        } catch (KonflikBatch $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('unggah.show', $batch)->with('success', 'Berkas diterima dan sedang diproses.');
    }

    public function show(Request $request, UploadBatch $batch): Response
    {
        $request->validate(['tingkat' => ['nullable', Rule::enum(TingkatIssue::class)]]);
        $batch->load('pengunggah:id,nama', 'period');
        $tingkat = $request->input('tingkat');

        // Baris staging (50 per halaman) dengan penanda issue per baris.
        $staging = $batch->stagingRows()->orderBy('row_number')->paginate(50, ['*'], 'page')->withQueryString();
        $issuePerBaris = UploadRowIssue::query()->where('batch_id', $batch->id)
            ->whereIn('row_number', $staging->getCollection()->pluck('row_number'))
            ->get(['row_number', 'kode', 'tingkat'])->groupBy('row_number');

        return Inertia::render('Unggah/Show', [
            'batch' => $this->ringkasBatch($batch) + [
                'ringkasan' => $batch->ringkasan,
                'storage_path' => $batch->storage_path,
                'checksum' => $batch->checksum_sha256,
                'periode_terbuka' => $batch->period->terbuka(),
                'bisa' => [
                    'commit' => $batch->status === StatusBatch::SiapCommit && $batch->period->terbuka(),
                    'batal' => in_array($batch->status, [StatusBatch::SiapCommit, StatusBatch::Gagal], true),
                    'rollback' => $batch->status === StatusBatch::Committed && $batch->period->terbuka(),
                ],
                'akan_menggantikan' => $batch->status === StatusBatch::SiapCommit && $batch->modul
                    ? UploadBatch::query()->committed()->where('period_id', $batch->period_id)->where('modul', $batch->modul->value)->value('file_name')
                    : null,
            ],
            'saudara' => UploadBatch::query()->where('unggahan_id', $batch->unggahan_id)->whereKeyNot($batch->id)
                ->with('pengunggah:id,nama', 'period:id,kode')->get()->map(fn ($b) => $this->ringkasBatch($b)),
            'issues' => UploadRowIssue::query()->where('batch_id', $batch->id)
                ->when($tingkat, fn ($q) => $q->where('tingkat', $tingkat))
                ->orderByRaw("CASE tingkat WHEN 'error' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->orderBy('row_number')
                ->paginate(50, ['*'], 'halaman_issue')
                ->withQueryString()
                ->through(fn (UploadRowIssue $i) => [
                    'id' => $i->id,
                    'sheet' => $i->sheet,
                    'row_number' => $i->row_number,
                    'kode' => $i->kode,
                    'tingkat' => $i->tingkat->value,
                    'pesan' => $i->pesan,
                ]),
            'staging' => $staging->through(fn ($r) => [
                'row_number' => $r->row_number,
                'data' => array_diff_key($r->data, ['raw' => true]),
                'issues' => ($issuePerBaris->get($r->row_number) ?? collect())->map(fn ($i) => ['kode' => $i->kode, 'tingkat' => $i->tingkat->value])->values(),
            ]),
            'filters' => (object) $request->only(['tingkat']),
        ]);
    }

    /** Dipanggil frontend setiap 2 detik selama batch diproses. */
    public function status(UploadBatch $batch): array
    {
        return [
            'batches' => UploadBatch::query()->where('unggahan_id', $batch->unggahan_id)->get()
                ->map(fn ($b) => ['id' => $b->id, 'status' => $b->status->value, 'modul' => $b->modul?->value]),
        ];
    }

    public function commit(Request $request, UploadBatch $batch): RedirectResponse
    {
        return $this->jalankan($request, function () use ($batch, $request) {
            $this->pengelola->commit($batch, $request->user());

            return "Batch {$batch->modul->label()} di-commit. Skor periode dihitung ulang.";
        });
    }

    public function destroy(Request $request, UploadBatch $batch): RedirectResponse
    {
        return $this->jalankan($request, function () use ($batch, $request) {
            $this->pengelola->batal($batch, $request->user());

            return 'Batch dibatalkan.';
        });
    }

    public function rollback(Request $request, UploadBatch $batch): RedirectResponse
    {
        return $this->jalankan($request, function () use ($batch, $request) {
            $pulih = $this->pengelola->rollback($batch, $request->user());

            return $pulih
                ? "Batch di-rollback. Data dari berkas {$pulih->file_name} dipulihkan."
                : 'Batch di-rollback. Modul ini kini tanpa data untuk periode tersebut.';
        });
    }

    private function jalankan(Request $request, callable $aksi): RedirectResponse
    {
        try {
            return back()->with('success', $aksi());
        } catch (KonflikBatch $e) {
            abort_if($request->expectsJson(), 409, $e->getMessage());

            return back()->with('error', $e->getMessage());
        }
    }

    private function ringkasBatch(UploadBatch $b): array
    {
        $issues = $b->ringkasan['issues'] ?? null;

        return [
            'id' => $b->id,
            'unggahan_id' => $b->unggahan_id,
            'periode' => $b->period?->kode,
            'modul' => $b->modul?->value,
            'modul_label' => $b->modul?->label() ?? 'Mendeteksi sheet…',
            'sheet' => $b->sheet,
            'file_name' => $b->file_name,
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'baris' => $b->ringkasan['baris'] ?? $b->ringkasan['baris_ditulis'] ?? null,
            'error' => $issues['error'] ?? 0,
            'warning' => $issues['warning'] ?? 0,
            'info' => $issues['info'] ?? 0,
            'pengunggah' => $b->pengunggah?->nama,
            'created_at' => $b->created_at?->toIso8601String(),
            'committed_at' => $b->committed_at?->toIso8601String(),
        ];
    }
}
