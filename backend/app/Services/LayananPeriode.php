<?php

namespace App\Services;

use App\Enums\StatusBatch;
use App\Enums\StatusPeriode;
use App\Models\Period;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Buka, hitung ulang, dan tutup periode penilaian (BLTH). */
class LayananPeriode
{
    public function __construct(
        private readonly ParameterSkor $parameter,
        private readonly PenghitungSkor $skor,
        private readonly LayananPemetaan $pemetaan,
    ) {}

    public function buka(string $kode, User $oleh): Period
    {
        $periode = DB::transaction(function () use ($kode) {
            $p = Period::create(['kode' => $kode, 'status' => StatusPeriode::Terbuka]);
            $this->parameter->siapkan($p);

            return $p;
        });
        Audit::catat(Audit::OPEN_PERIOD, $periode, null, ['kode' => $kode], $oleh->id);

        return $periode;
    }

    /** @throws ValidationException */
    public function hitungUlang(Period $periode, User $oleh): void
    {
        if (! $periode->terbuka()) {
            throw ValidationException::withMessages(['periode' => "Periode {$periode->kode} sudah ditutup; skor final tidak dihitung ulang."]);
        }
        $this->skor->hitung($periode);
        Audit::catat(Audit::RECALCULATE, $periode, null, null, $oleh->id);
    }

    /**
     * Gerbang tutup periode: daftar syarat beserta statusnya. Login ACMT yang belum terpetakan
     * hanya peringatan, tidak memblokir.
     *
     * @return list<array{kunci: string, label: string, lolos: bool, blokir: bool, pesan: string|null}>
     */
    public function kesiapan(Period $periode): array
    {
        $masalah = $this->parameter->masalah($periode);
        $menunggu = UploadBatch::query()->where('period_id', $periode->id)
            ->whereIn('status', [StatusBatch::Diproses->value, StatusBatch::SiapCommit->value])->count();
        $committed = UploadBatch::query()->committed()->where('period_id', $periode->id)->count();
        $belum = $this->pemetaan->belumTerpetakan($periode)->count();

        return [
            ['kunci' => 'parameter', 'label' => 'Bobot bonus Hari J dan ambang kategori valid', 'lolos' => ! $masalah, 'blokir' => true, 'pesan' => $masalah[0] ?? null],
            ['kunci' => 'batch', 'label' => 'Tidak ada batch yang diproses atau menunggu commit', 'lolos' => $menunggu === 0, 'blokir' => true,
                'pesan' => $menunggu ? "{$menunggu} batch masih diproses atau menunggu commit." : null],
            ['kunci' => 'data', 'label' => 'Minimal satu batch committed', 'lolos' => $committed > 0, 'blokir' => true,
                'pesan' => $committed ? null : 'Belum ada data yang di-commit di periode ini.'],
            ['kunci' => 'pemetaan', 'label' => 'Semua login ACMT terpetakan', 'lolos' => $belum === 0, 'blokir' => false,
                'pesan' => $belum ? "{$belum} login belum terpetakan (peringatan, tidak memblokir)." : null],
        ];
    }

    /**
     * Tutup periode: konfirmasi_kode harus sama dengan kode periode, lalu hitung skor final dan kunci
     * periode dari unggah, commit, rollback, dan ubah parameter.
     *
     * @throws ValidationException
     */
    public function tutup(Period $periode, string $konfirmasiKode, User $oleh): void
    {
        if (! $periode->terbuka()) {
            throw ValidationException::withMessages(['periode' => "Periode {$periode->kode} sudah ditutup."]);
        }
        if (trim($konfirmasiKode) !== $periode->kode) {
            throw ValidationException::withMessages(['konfirmasi_kode' => "Ketik {$periode->kode} untuk mengonfirmasi penutupan periode."]);
        }
        $gagal = collect($this->kesiapan($periode))->first(fn ($s) => $s['blokir'] && ! $s['lolos']);
        if ($gagal) {
            throw ValidationException::withMessages(['periode' => $gagal['pesan'] ?? $gagal['label']]);
        }

        $this->skor->hitung($periode, final: true);
        $periode->update(['status' => StatusPeriode::Ditutup, 'ditutup_at' => now(), 'ditutup_oleh' => $oleh->id]);
        Audit::catat(Audit::CLOSE_PERIOD, $periode, ['status' => 'terbuka'], ['status' => 'ditutup'], $oleh->id);
    }
}
