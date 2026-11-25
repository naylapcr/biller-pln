<?php

namespace App\Http\Controllers;

use App\Enums\Modul;
use App\Enums\StatusBatch;
use App\Enums\TingkatIssue;
use App\Exceptions\KonflikBatch;
use App\Exports\TemplateAcmtExport;
use App\Http\Controllers\Concerns\PilihPeriode;
use App\Models\Biller;
use App\Models\Period;
use App\Models\UploadBatch;
use App\Models\UploadRowIssue;
use App\Services\Audit;
use App\Services\PemetaanPetugas;
use App\Services\PengelolaBatch;
use App\Support\UnduhCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unggah berkas ACMT/AP2T, pratinjau, commit, batal, dan rollback (PRD bagian 4 dan 8).
 * Konflik (batch tidak siap, periode ditutup) dibalas 409 untuk permintaan JSON,
 * atau pesan galat untuk halaman.
 */
class UnggahController extends Controller
{
    use PilihPeriode;

    /** @var array<int, array<string, int>> urutan batch per periode */
    private array $urutan = [];

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
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UploadBatch $b) => $this->ringkasBatch($b));

        return Inertia::render('Koordinator/PortalUnggah', [
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
            'file' => ['required', 'file', 'extensions:xlsx,csv', 'max:'.config('sipermon.maks_berkas_kb')],
            'periode' => ['required', 'string', Rule::exists('periods', 'kode')],
            'modul' => ['nullable', Rule::enum(Modul::class)->only(Modul::tersediaSemua())],
        ], [
            'file.extensions' => 'Berkas harus .xlsx atau .csv.',
            'file.max' => 'Ukuran berkas maksimal 25 MB.',
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

        // Pratinjau baris staging (5 per halaman, 02.4) dengan penanda issue dan nama biller per baris.
        $staging = $batch->stagingRows()->orderBy('row_number')->paginate(5, ['*'], 'page')->withQueryString();
        $petugas = PemetaanPetugas::muat();
        $namaBiller = Biller::query()->pluck('nama', 'id');
        $issuePerBaris = UploadRowIssue::query()->where('batch_id', $batch->id)
            ->whereIn('row_number', $staging->getCollection()->pluck('row_number'))
            ->get(['row_number', 'kode', 'tingkat'])->groupBy('row_number');

        return Inertia::render('Koordinator/DetailBatch', [
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
                // Empat cek otomatis yang ditampilkan di kartu batch ditolak (04.1).
                'cek' => $this->cekOtomatis($batch),
                'login' => $this->hitungLogin($batch, $petugas),
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
                ->paginate(10, ['*'], 'halaman_issue')
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
                'biller' => $namaBiller[$petugas->billerId($r->data['petugas_login'] ?? null) ?? 0] ?? null,
                'issues' => ($issuePerBaris->get($r->row_number) ?? collect())->map(fn ($i) => ['kode' => $i->kode, 'tingkat' => $i->tingkat->value])->values(),
            ]),
            'filters' => (object) $request->only(['tingkat']),
        ]);
    }

    /**
     * Unduh berkas asli. Di Supabase Storage (disk s3) lewat URL bertanda tangan berumur 5 menit;
     * di disk lokal langsung dialirkan.
     */
    public function berkas(UploadBatch $batch): RedirectResponse|StreamedResponse
    {
        $disk = (string) config('sipermon.disk_unggahan');
        Audit::catat(Audit::DOWNLOAD_FILE, $batch, null, ['file' => $batch->file_name]);

        if (config("filesystems.disks.{$disk}.driver") === 's3') {
            return redirect()->away(Storage::disk($disk)->temporaryUrl(
                $batch->storage_path,
                now()->addMinutes((int) config('sipermon.umur_url_unduhan_menit')),
                ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($batch->file_name).'"'],
            ));
        }

        return Storage::disk($disk)->download($batch->storage_path, $batch->file_name);
    }

    /** Laporan error dan warning satu batch sebagai CSV (04.1). */
    public function issues(UploadBatch $batch): StreamedResponse
    {
        return UnduhCsv::kirim(
            "issue-{$this->nomor($batch)}.csv",
            ['SHEET', 'BARIS', 'TINGKAT', 'KODE', 'PESAN'],
            UploadRowIssue::query()->where('batch_id', $batch->id)->orderBy('id')->lazy()
                ->map(fn (UploadRowIssue $i) => [$i->sheet, $i->row_number, $i->tingkat->value, $i->kode, $i->pesan]),
        );
    }

    /** Template XLSX berisi judul dan header wajib setiap modul (04.5c). */
    public function template(Request $request): BinaryFileResponse
    {
        $kode = $this->periode($request)?->kode ?? now()->format('Ym');

        return Excel::download(new TemplateAcmtExport($kode), 'template-rekap-acmt.xlsx');
    }

    /** @return array{terpetakan: int, belum: list<string>} login petugas di staging menurut pemetaan saat ini */
    private function hitungLogin(UploadBatch $batch, PemetaanPetugas $petugas): array
    {
        $login = $batch->stagingRows()->toBase()->pluck('data')
            ->map(fn ($d) => strtolower((string) (json_decode($d, true)['petugas_login'] ?? '')))
            ->filter()->unique()->values();
        $belum = $login->filter(fn (string $l) => $petugas->billerId($l) === null)->values();

        return ['terpetakan' => $login->count() - $belum->count(), 'belum' => $belum->all()];
    }

    /** Nomor batch yang mudah dibaca: BATCH-{yymm}-{urutan unggah di periode}. */
    private function nomor(UploadBatch $b): string
    {
        $this->urutan[$b->period_id] ??= UploadBatch::query()->where('period_id', $b->period_id)
            ->orderBy('created_at')->orderBy('id')->pluck('id')->flip()->all();

        return sprintf('BATCH-%s-%02d', substr((string) $b->period?->kode, 2), ($this->urutan[$b->period_id][$b->id] ?? 0) + 1);
    }

    /** @return list<array{kunci: string, label: string, lolos: bool}> */
    private function cekOtomatis(UploadBatch $batch): array
    {
        if ($batch->status === StatusBatch::Diproses) {
            return [];
        }
        $kode = UploadRowIssue::query()->where('batch_id', $batch->id)->where('tingkat', TingkatIssue::Error->value)
            ->distinct()->pluck('kode')->all();
        $ada = fn (array $daftar) => count(array_intersect($daftar, $kode)) > 0;

        return [
            ['kunci' => 'grand_total', 'label' => 'Jumlah cocok dengan GRAND TOTAL', 'lolos' => ! $ada(['TOTAL_MISMATCH', 'TERBACA_LEBIH'])],
            ['kunci' => 'header', 'label' => 'Header dan kolom wajib lengkap', 'lolos' => ! $ada(['HEADER_MISMATCH', 'MISSING_REQUIRED_COLUMN', 'TIPE_TIDAK_VALID', 'GAGAL_PROSES'])],
            ['kunci' => 'sha256', 'label' => 'Checksum SHA-256 belum pernah di-commit', 'lolos' => ! $ada(['DUPLICATE_FILE', 'DUPLICATE_FILE_HASH'])],
            ['kunci' => 'periode_unit', 'label' => 'Periode dan UNITUP sesuai', 'lolos' => ! $ada(['PERIODE_MISMATCH', 'UNIT_MISMATCH'])],
        ];
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
            'nomor' => $this->nomor($b),
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
