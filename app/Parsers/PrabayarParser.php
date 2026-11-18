<?php

namespace App\Parsers;

use App\Enums\Modul;

/**
 * Monitoring fisik meter prabayar: satu baris per pemeriksaan (34 kolom).
 * Kolom yang tidak dipetakan disimpan utuh di raw.
 */
class PrabayarParser extends ParserModul
{
    public function modul(): Modul
    {
        return Modul::Prabayar;
    }

    public function judul(): string
    {
        return 'MONITORING FISIK METER PRABAYAR';
    }

    public function kolomWajib(): array
    {
        return ['NO', 'IDPEL', 'NO METER', 'TARIF', 'DAYA', 'KDRBM', 'KD KELOMPOK', 'TGLBACA', 'KDBACA', 'PETUGAS',
            'KWH SISA', 'STATUS TEMPER', 'SEGEL', 'LCD', 'KEYPAD', 'RELAY', 'KOORDINAT X', 'KOORDINAT Y'];
    }

    public function parse(iterable $baris, array $kolom, KonteksParsing $ctx, HasilParsing $hasil): void
    {
        $nama = array_flip($kolom);

        foreach ($baris as $nomor => $sel) {
            $no = self::sel($sel, $kolom, 'NO');
            if (self::footer($no)) {
                break;
            }
            if (self::barisKosong($sel) || ! is_numeric($no)) {
                continue;
            }

            try {
                [$lintang, $bujur] = Nilai::koordinat(self::sel($sel, $kolom, 'KOORDINAT X'), self::sel($sel, $kolom, 'KOORDINAT Y'));
                $data = [
                    'idpel' => Nilai::kode(self::sel($sel, $kolom, 'IDPEL'), 12),
                    'no_meter' => Nilai::kode(self::sel($sel, $kolom, 'NO METER')),
                    'tarif' => Nilai::teks(self::sel($sel, $kolom, 'TARIF')),
                    'daya_va' => Nilai::bulat(self::sel($sel, $kolom, 'DAYA')),
                    'kdrbm' => Nilai::teks(self::sel($sel, $kolom, 'KDRBM')),
                    'kd_kelompok' => Nilai::teks(self::sel($sel, $kolom, 'KD KELOMPOK')),
                    'tgl_baca' => Nilai::waktu(self::sel($sel, $kolom, 'TGLBACA'))?->toIso8601String(),
                    'kd_baca' => Nilai::teks(self::sel($sel, $kolom, 'KDBACA')),
                    'petugas_login' => strtolower((string) Nilai::teks(self::sel($sel, $kolom, 'PETUGAS'))),
                    'segel' => Nilai::teks(self::sel($sel, $kolom, 'SEGEL')),
                    'lcd' => Nilai::teks(self::sel($sel, $kolom, 'LCD')),
                    'keypad' => Nilai::teks(self::sel($sel, $kolom, 'KEYPAD')),
                    'relay' => Nilai::teks(self::sel($sel, $kolom, 'RELAY')),
                    'status_temper' => Nilai::teks(self::sel($sel, $kolom, 'STATUS TEMPER')),
                    'kwh_sisa' => Nilai::desimal(self::sel($sel, $kolom, 'KWH SISA')),
                    'latitude' => $lintang,
                    'longitude' => $bujur,
                ];
            } catch (NilaiTidakValid $e) {
                $hasil->error($nomor, 'TIPE_TIDAK_VALID', "Nilai tidak bisa diurai ({$e->getMessage()}).");

                continue;
            }

            if ($data['idpel'] === null || $data['petugas_login'] === '') {
                $hasil->error($nomor, 'TIPE_TIDAK_VALID', 'IDPEL dan PETUGAS wajib terisi.');

                continue;
            }

            $data['koordinat_valid'] = $ctx->dalamWilayah($lintang, $bujur);
            if (! $data['koordinat_valid'] && $lintang !== null && $bujur !== null) {
                $hasil->warning($nomor, 'KOORDINAT_LUAR_WILAYAH', sprintf('Koordinat %s, %s di luar kotak Pekanbaru.', $lintang, $bujur), [
                    'lintang' => $lintang, 'bujur' => $bujur,
                ]);
            }

            if ($ctx->petugas->billerId($data['petugas_login']) === null) {
                $hasil->warning($nomor, 'PETUGAS_TIDAK_DIKENAL', "Login {$data['petugas_login']} belum terpetakan ke biller; baris disimpan tanpa biller.", ['login' => $data['petugas_login']]);
            }

            // Simpan kolom lain apa adanya (NAMA, ALAMAT, TEGANGAN, ARUS, AKURASI, dll.).
            $raw = [];
            foreach ($sel as $i => $v) {
                if (isset($nama[$i]) && ! Nilai::kosong($v)) {
                    $raw[$nama[$i]] = $v;
                }
            }
            $data['raw'] = $raw;

            $hasil->tambahBaris($nomor, $data);
        }
    }

    public function validate(HasilParsing $hasil, KonteksParsing $ctx): void
    {
        $dilihat = [];
        foreach ($hasil->baris as $b) {
            $idpel = $b['data']['idpel'];
            if (isset($dilihat[$idpel])) {
                $hasil->error($b['row_number'], 'DUPLIKAT_BARIS', "IDPEL {$idpel} sudah ada di baris {$dilihat[$idpel]}.");
            }
            $dilihat[$idpel] ??= $b['row_number'];
        }

        $data = array_column($hasil->baris, 'data');
        $kdbaca = array_count_values(array_map(fn ($d) => (string) $d['kd_baca'], $data));
        arsort($kdbaca);
        $hasil->ringkasan = [
            'baris' => count($data),
            'idpel_unik' => count($dilihat),
            'login_unik' => count(array_unique(array_column($data, 'petugas_login'))),
            'kd_baca' => $kdbaca,
            'koordinat_valid' => count(array_filter($data, fn ($d) => $d['koordinat_valid'])),
            'akurasi_kosong' => count(array_filter($data, fn ($d) => ! isset($d['raw']['AKURASI']))),
            'temper_nyala' => count(array_filter($data, fn ($d) => strtoupper((string) $d['status_temper']) === 'NYALA')),
        ];
    }
}
