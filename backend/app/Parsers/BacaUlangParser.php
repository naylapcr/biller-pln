<?php

namespace App\Parsers;

use App\Enums\HasilBacaUlang;
use App\Enums\Modul;
use App\Services\PenilaiBacaUlang;
use Carbon\CarbonImmutable;

/**
 * Rekap Baca Ulang Pascabayar: satu baris per pembacaan ulang, baris data ditandai NO berupa angka.
 * Setiap baris langsung dinilai (PenilaiBacaUlang) agar pratinjau menampilkan hasilnya.
 */
class BacaUlangParser extends ParserModul
{
    public function modul(): Modul
    {
        return Modul::BacaUlang;
    }

    public function judul(): string
    {
        return 'REKAP BACA ULANG';
    }

    public function kolomWajib(): array
    {
        return ['NO', 'UNITUP', 'IDPEL', 'TARIF', 'DAYA', 'KODE RBM', 'TGLBACA', 'STAN METER', 'PEMKWH',
            'TGLBACA BU', 'STAN METER BU', 'PEMKWH BU', 'PETUGAS', 'KETERANGAN'];
    }

    public function parse(iterable $baris, array $kolom, KonteksParsing $ctx, HasilParsing $hasil): void
    {
        foreach ($baris as $nomor => $sel) {
            $no = self::sel($sel, $kolom, 'NO');
            if (self::footer($no)) {
                break;
            }
            if (self::barisKosong($sel) || ! is_numeric($no)) {
                continue;
            }

            try {
                $tglBaca = Nilai::waktu(self::sel($sel, $kolom, 'TGLBACA'));
                $tglBu = Nilai::waktu(self::sel($sel, $kolom, 'TGLBACA BU'));
                $kodeRbm = strtoupper((string) Nilai::teks(self::sel($sel, $kolom, 'KODE RBM')));
                $data = [
                    'unitup' => Nilai::teks(self::sel($sel, $kolom, 'UNITUP')),
                    'blth' => Nilai::teks(self::sel($sel, $kolom, 'BLTH')),
                    'idpel' => Nilai::kode(self::sel($sel, $kolom, 'IDPEL'), 12),
                    'tarif' => Nilai::teks(self::sel($sel, $kolom, 'TARIF')),
                    'daya_va' => Nilai::bulat(self::sel($sel, $kolom, 'DAYA')),
                    'kode_rbm' => $kodeRbm ?: null,
                    'hari_baca' => strlen($kodeRbm) >= 7 ? $kodeRbm[6] : null,
                    'tgl_baca' => $tglBaca?->toIso8601String(),
                    'stan_awal' => Nilai::desimal(self::sel($sel, $kolom, 'STAN METER')),
                    'pemkwh_awal' => Nilai::desimal(self::sel($sel, $kolom, 'PEMKWH')),
                    'tgl_baca_bu' => $tglBu?->toIso8601String(),
                    'stan_bu' => Nilai::desimal(self::sel($sel, $kolom, 'STAN METER BU')),
                    'pemkwh_bu' => Nilai::desimal(self::sel($sel, $kolom, 'PEMKWH BU')),
                    'petugas_login' => strtolower((string) Nilai::teks(self::sel($sel, $kolom, 'PETUGAS'))),
                    'status_bu' => Nilai::baku(self::sel($sel, $kolom, 'KETERANGAN')),
                ];
            } catch (NilaiTidakValid $e) {
                $hasil->error($nomor, 'TIPE_TIDAK_VALID', "Nilai tidak bisa diurai ({$e->getMessage()}).");

                continue;
            }

            if ($data['idpel'] === null || $tglBaca === null || $data['petugas_login'] === '') {
                $hasil->error($nomor, 'TIPE_TIDAK_VALID', 'IDPEL, TGLBACA, dan PETUGAS wajib terisi.');

                continue;
            }
            if ($data['unitup'] !== null && $data['unitup'] !== $ctx->unitup) {
                $hasil->error($nomor, 'UNIT_MISMATCH', "UNITUP {$data['unitup']} bukan {$ctx->unitup}.");
            }
            if ($data['blth'] !== null && $data['blth'] !== $ctx->periode) {
                $hasil->error($nomor, 'PERIODE_MISMATCH', "BLTH {$data['blth']} berbeda dari periode {$ctx->periode}.");
            }

            $nilai = PenilaiBacaUlang::nilai($data['status_bu'], $data['stan_awal'], $data['stan_bu'], $tglBaca, $tglBu, $data['daya_va'], $ctx->toleransiKwh);
            $data['hasil'] = $nilai['hasil']->value;
            $data['batas_kwh'] = $nilai['batas_kwh'];

            if ($data['status_bu'] === 'BELUM BACA') {
                $hasil->info($nomor, 'BU_BELUM_BACA', "IDPEL {$data['idpel']} belum dibaca ulang; disimpan, tidak dinilai.");
            }

            if ($ctx->petugas->billerId($data['petugas_login']) === null) {
                $hasil->warning($nomor, 'PETUGAS_TIDAK_DIKENAL', "Login {$data['petugas_login']} belum terpetakan ke biller; baris disimpan tanpa biller.", ['login' => $data['petugas_login']]);
            } else {
                // Pemilik KODE RBM (dari kode petugas di dalamnya) berbeda dari PETUGAS di baris ini.
                $pemilik = $ctx->petugas->loginDariKode(substr($kodeRbm, 3, 3));
                if ($pemilik !== null && $ctx->petugas->billerId($pemilik) !== $ctx->petugas->billerId($data['petugas_login'])) {
                    $hasil->warning($nomor, 'RBM_TIDAK_COCOK', "KODE RBM {$kodeRbm} milik {$pemilik}, tetapi PETUGAS {$data['petugas_login']}. Kasus dibebankan ke pemilik KODE RBM.");
                }
            }

            unset($data['unitup'], $data['blth']);
            $hasil->tambahBaris($nomor, $data);
        }
    }

    public function validate(HasilParsing $hasil, KonteksParsing $ctx): void
    {
        $dilihat = [];
        foreach ($hasil->baris as $b) {
            $d = $b['data'];
            $kunci = $d['idpel'].'|'.$d['tgl_baca'].'|'.$d['tgl_baca_bu'];
            if (isset($dilihat[$kunci])) {
                $hasil->error($b['row_number'], 'DUPLIKAT_BARIS', "IDPEL {$d['idpel']} dengan TGLBACA dan TGLBACA BU yang sama sudah ada di baris {$dilihat[$kunci]}.");
            }
            $dilihat[$kunci] ??= $b['row_number'];
        }

        $data = array_column($hasil->baris, 'data');
        $hasilHitung = array_count_values(array_column($data, 'hasil'));
        $hasil->ringkasan = [
            'baris' => count($data),
            'idpel_unik' => count(array_unique(array_column($data, 'idpel'))),
            'sudah_baca' => count(array_filter($data, fn ($d) => $d['status_bu'] === 'SUDAH BACA')),
            'belum_baca' => count(array_filter($data, fn ($d) => $d['status_bu'] === 'BELUM BACA')),
            'hasil' => collect(HasilBacaUlang::cases())->mapWithKeys(fn ($h) => [$h->value => $hasilHitung[$h->value] ?? 0])->all(),
            'login_unik' => count(array_unique(array_column($data, 'petugas_login'))),
            'rentang_tgl_baca' => $this->rentang(array_column($data, 'tgl_baca')),
        ];
    }

    /** @param  list<string|null>  $tanggal */
    private function rentang(array $tanggal): ?array
    {
        $tanggal = array_filter($tanggal);
        if (! $tanggal) {
            return null;
        }
        sort($tanggal);

        return [CarbonImmutable::parse(reset($tanggal))->toDateString(), CarbonImmutable::parse(end($tanggal))->toDateString()];
    }
}
