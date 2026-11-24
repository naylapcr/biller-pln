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
    ) {}

    public function buka(string $kode, User $oleh): Period
    {
        $periode = DB::transaction(function () use ($kode) {
            $p = Period::create(['kode' => $kode, 'status' => StatusPeriode::Terbuka]);
            $this->parameter->siapkan($p);

            return $p;
        });
        Audit::catat('buka_periode', $periode, null, ['kode' => $kode], $oleh->id);

        return $periode;
    }

    /** @throws ValidationException */
    public function hitungUlang(Period $periode, User $oleh): void
    {
        if (! $periode->terbuka()) {
            throw ValidationException::withMessages(['periode' => "Periode {$periode->kode} sudah ditutup; skor final tidak dihitung ulang."]);
        }
        $this->skor->hitung($periode);
        Audit::catat('hitung_ulang', $periode, null, null, $oleh->id);
    }

    /**
     * Validasi parameter wajib, hitung skor final, lalu kunci periode dari unggah, commit,
     * rollback, dan ubah parameter.
     *
     * @throws ValidationException
     */
    public function tutup(Period $periode, User $oleh): void
    {
        if (! $periode->terbuka()) {
            throw ValidationException::withMessages(['periode' => "Periode {$periode->kode} sudah ditutup."]);
        }
        $kosong = $this->parameter->kosongWajib($periode);
        if ($kosong) {
            throw ValidationException::withMessages(['periode' => 'Parameter wajib belum diisi: '.implode(', ', $kosong).'.']);
        }
        $menunggu = UploadBatch::query()->where('period_id', $periode->id)
            ->whereIn('status', [StatusBatch::Diproses->value, StatusBatch::SiapCommit->value])->count();
        if ($menunggu > 0) {
            throw ValidationException::withMessages(['periode' => "Masih ada {$menunggu} batch yang diproses atau menunggu commit. Commit atau batalkan dulu."]);
        }

        $this->skor->hitung($periode, final: true);
        $periode->update(['status' => StatusPeriode::Ditutup, 'ditutup_at' => now(), 'ditutup_oleh' => $oleh->id]);
        Audit::catat('tutup_periode', $periode, ['status' => 'terbuka'], ['status' => 'ditutup'], $oleh->id);
    }
}
