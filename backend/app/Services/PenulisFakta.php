<?php

namespace App\Services;

use App\Enums\HasilBacaUlang;
use App\Enums\Modul;
use App\Enums\SumberKoreksi;
use App\Models\UploadBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Menyalin baris hasil parsing ke tabel final (dan menghapusnya kembali saat rollback).
 * biller_id ditentukan saat tulis, sehingga alias yang ditambahkan setelah unggah ikut berlaku.
 */
class PenulisFakta
{
    private const POTONGAN = 500;

    /** @param  iterable<array<string, mixed>>  $baris  data baris (kolom 'data' staging) */
    public function tulis(UploadBatch $batch, iterable $baris): int
    {
        $petugas = PemetaanPetugas::muat();
        $sekarang = now();
        $dasar = fn (array $d) => [
            'period_id' => $batch->period_id,
            'biller_id' => $petugas->billerId($d['petugas_login']),
            'petugas_login' => $d['petugas_login'],
            'batch_id' => $batch->id,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ];

        [$tabel, $petakan] = match ($batch->modul) {
            Modul::PascabayarTunggal, Modul::PascabayarGanda => ['reading_logs', fn (array $d) => $dasar($d) + [
                'kode_petugas' => $d['kode_petugas'],
                'kode_rbm' => $d['kode_rbm'],
                'hari_baca' => $d['hari_baca'],
                'jenis_meter' => $d['jenis_meter'],
                'jml_plg' => $d['jml_plg'],
                'terbaca' => $d['terbaca'],
                'belum_baca' => $d['belum_baca'],
                'foto_berhasil' => $d['foto_berhasil'],
                'mulai_at' => self::waktu($d['mulai_at']),
                'selesai_at' => self::waktu($d['selesai_at']),
                'durasi_detik' => $d['durasi_detik'],
            ]],
            // Kasus Baca Ulang dibebankan ke pemilik KODE RBM (pembaca pertama), bukan ke kolom PETUGAS.
            Modul::BacaUlang => ['reread_cases', fn (array $d) => ['biller_id' => $petugas->pemilikRbm($d['kode_rbm'], $d['petugas_login'])] + $dasar($d) + [
                'idpel' => $d['idpel'],
                'tarif' => $d['tarif'],
                'daya_va' => $d['daya_va'],
                'kode_rbm' => $d['kode_rbm'],
                'hari_baca' => $d['hari_baca'],
                'tgl_baca' => self::waktu($d['tgl_baca']),
                'stan_awal' => $d['stan_awal'],
                'pemkwh_awal' => $d['pemkwh_awal'],
                'tgl_baca_bu' => self::waktu($d['tgl_baca_bu']),
                'stan_bu' => $d['stan_bu'],
                'pemkwh_bu' => $d['pemkwh_bu'],
                'status_bu' => $d['status_bu'],
                'batas_kwh' => $d['batas_kwh'],
                'hasil' => $d['hasil'],
                'sisipan' => false, // dinilai saat hitung skor dari jendela hari baca RBM
            ]],
            Modul::Prabayar => ['prepaid_inspections', fn (array $d) => $dasar($d) + [
                'idpel' => $d['idpel'],
                'no_meter' => $d['no_meter'],
                'tarif' => $d['tarif'],
                'daya_va' => $d['daya_va'],
                'kdrbm' => $d['kdrbm'],
                'kd_kelompok' => $d['kd_kelompok'],
                'tgl_baca' => self::waktu($d['tgl_baca']),
                'kd_baca' => $d['kd_baca'],
                'segel' => $d['segel'],
                'lcd' => $d['lcd'],
                'keypad' => $d['keypad'],
                'relay' => $d['relay'],
                'status_temper' => $d['status_temper'],
                'kwh_sisa' => $d['kwh_sisa'],
                'latitude' => $d['latitude'],
                'longitude' => $d['longitude'],
                'koordinat_valid' => $d['koordinat_valid'],
                'raw' => json_encode($d['raw'] ?? [], JSON_UNESCAPED_UNICODE),
            ]],
            default => throw new InvalidArgumentException("Modul {$batch->modul?->value} belum punya parser."),
        };

        $jumlah = 0;
        $buffer = [];
        foreach ($baris as $d) {
            $buffer[] = $petakan($d);
            if (count($buffer) >= self::POTONGAN) {
                DB::table($tabel)->insert($buffer);
                $jumlah += count($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            DB::table($tabel)->insert($buffer);
            $jumlah += count($buffer);
        }

        if ($batch->modul === Modul::BacaUlang) {
            $this->buatKoreksiBacaUlang($batch);
        }

        return $jumlah;
    }

    /** Kasus Baca Ulang terbukti salah menjadi baris corrections (sumber BU). */
    private function buatKoreksiBacaUlang(UploadBatch $batch): void
    {
        $sekarang = now();
        DB::table('reread_cases')
            ->where('batch_id', $batch->id)
            ->whereIn('hasil', [HasilBacaUlang::SalahTurun->value, HasilBacaUlang::SalahLebih->value])
            ->orderBy('id')
            ->chunk(self::POTONGAN, function ($kasus) use ($batch, $sekarang) {
                DB::table('corrections')->insert($kasus->map(fn ($k) => [
                    'biller_id' => $k->biller_id,
                    'period_id' => $batch->period_id,
                    'petugas_login' => $k->petugas_login,
                    'idpel' => $k->idpel,
                    'sumber' => SumberKoreksi::BacaUlang->value,
                    'stan_awal' => $k->stan_awal,
                    'stan_benar' => $k->stan_bu,
                    'penyebab' => 'biller',
                    'reread_case_id' => $k->id,
                    'batch_id' => $batch->id,
                    'catatan' => HasilBacaUlang::from($k->hasil)->label(),
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ])->all());
            });
    }

    /** Hapus semua baris fakta milik batch ini. */
    public function hapus(UploadBatch $batch): void
    {
        DB::table('corrections')->where('batch_id', $batch->id)->delete();
        foreach (['reread_cases', 'reading_logs', 'prepaid_inspections'] as $tabel) {
            DB::table($tabel)->where('batch_id', $batch->id)->delete();
        }
    }

    private static function waktu(?string $iso): ?string
    {
        return $iso === null ? null : CarbonImmutable::parse($iso)->setTimezone(config('app.timezone'))->toDateTimeString();
    }
}
