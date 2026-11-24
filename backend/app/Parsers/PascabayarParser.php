<?php

namespace App\Parsers;

use App\Enums\JenisMeter;

/**
 * Rekap waktu pembacaan meter pascabayar (Tunggal dan Ganda Industri).
 * Satu baris "Petugas - 18111.AKMAL - AWA" per petugas per hari baca; hari baca diambil
 * dari baris grup "Hari Baca - X" di atasnya. Baris TOTAL GROUP dilewati, nilai GRAND TOTAL
 * dipakai untuk mencocokkan jumlah hasil parsing.
 */
abstract class PascabayarParser extends ParserModul
{
    private const KOLOM = ['KDPETUGAS', 'KODE_RBM', 'JMLPLG', 'TERBACA', 'BLMBACA', '% BACA', 'JML FOTO BERHASIL', 'TGL MULAI', 'TGL SELESAI', 'WAKTU'];

    abstract protected function jenisMeter(): JenisMeter;

    public function kolomWajib(): array
    {
        return self::KOLOM;
    }

    public function parse(iterable $baris, array $kolom, KonteksParsing $ctx, HasilParsing $hasil): void
    {
        $hari = null;
        $grandTotal = null;

        foreach ($baris as $nomor => $sel) {
            $label = self::sel($sel, $kolom, 'KDPETUGAS');
            if (self::footer($label)) {
                break;
            }
            if (self::barisKosong($sel)) {
                continue;
            }
            $teks = Nilai::baku($label);

            if (str_starts_with($teks, 'HARI BACA')) {
                // "Hari Baca - A" (Tunggal) dan "Hari Baca - Hari Baca - A" (Ganda): huruf setelah tanda hubung terakhir.
                $hari = strtoupper(trim(substr($teks, strrpos($teks, '-') + 1)));
                if (! preg_match('/^[A-Z]$/', $hari)) {
                    $hasil->error($nomor, 'TIPE_TIDAK_VALID', "Label hari baca \"{$label}\" tidak dikenali.");
                    $hari = null;
                }

                continue;
            }
            if ($teks === 'TOTAL GROUP') {
                continue;
            }
            if ($teks === 'GRAND TOTAL') {
                $grandTotal = $this->angkaTotal($sel, $kolom, $nomor, $hasil);

                continue;
            }
            if (! str_starts_with($teks, 'PETUGAS')) {
                $hasil->warning($nomor, 'BARIS_TIDAK_DIKENALI', "Baris \"{$label}\" dilewati karena bukan baris petugas.");

                continue;
            }

            $this->barisPetugas($nomor, $sel, $kolom, (string) $label, $hari, $ctx, $hasil);
        }

        $hasil->ringkasan['grand_total'] = $grandTotal;
    }

    private function barisPetugas(int $nomor, array $sel, array $kolom, string $label, ?string $hari, KonteksParsing $ctx, HasilParsing $hasil): void
    {
        if (! preg_match('/^Petugas\s*-\s*(\S+)\s*-\s*([A-Za-z0-9]+)\s*$/i', trim($label), $m)) {
            $hasil->error($nomor, 'TIPE_TIDAK_VALID', "Label petugas \"{$label}\" tidak bisa dipecah menjadi login dan kode petugas.");

            return;
        }
        [$login, $kodePetugas] = [strtolower($m[1]), strtoupper($m[2])];

        if ($hari === null) {
            $hasil->error($nomor, 'TIPE_TIDAK_VALID', 'Baris petugas tidak berada di bawah grup "Hari Baca".');

            return;
        }

        try {
            $data = [
                'petugas_login' => $login,
                'kode_petugas' => $kodePetugas,
                'kode_rbm' => strtoupper((string) Nilai::teks(self::sel($sel, $kolom, 'KODE_RBM'))),
                'hari_baca' => $hari,
                'jenis_meter' => $this->jenisMeter()->value,
                'jml_plg' => $this->wajib(Nilai::bulat(self::sel($sel, $kolom, 'JMLPLG')), 'JMLPLG'),
                'terbaca' => $this->wajib(Nilai::bulat(self::sel($sel, $kolom, 'TERBACA')), 'TERBACA'),
                'belum_baca' => Nilai::bulat(self::sel($sel, $kolom, 'BLMBACA')) ?? 0,
                'persen_baca' => Nilai::desimal(self::sel($sel, $kolom, '% BACA')),
                'foto_berhasil' => $this->wajib(Nilai::bulat(self::sel($sel, $kolom, 'JML FOTO BERHASIL')), 'JML FOTO BERHASIL'),
                'mulai_at' => Nilai::waktu(self::sel($sel, $kolom, 'TGL MULAI'))?->toIso8601String(),
                'selesai_at' => Nilai::waktu(self::sel($sel, $kolom, 'TGL SELESAI'))?->toIso8601String(),
                'durasi_detik' => Nilai::durasi(self::sel($sel, $kolom, 'WAKTU')),
            ];
        } catch (NilaiTidakValid $e) {
            $hasil->error($nomor, 'TIPE_TIDAK_VALID', "Nilai tidak bisa diurai ({$e->getMessage()}).");

            return;
        }

        if ($data['kode_rbm'] === '') {
            $hasil->error($nomor, 'TIPE_TIDAK_VALID', 'KODE_RBM kosong.');

            return;
        }
        if ($data['terbaca'] > $data['jml_plg'] || $data['foto_berhasil'] > $data['terbaca']) {
            $hasil->error($nomor, 'TERBACA_LEBIH', 'TERBACA melebihi JMLPLG, atau JML FOTO BERHASIL melebihi TERBACA.', [
                'jml_plg' => $data['jml_plg'], 'terbaca' => $data['terbaca'], 'foto_berhasil' => $data['foto_berhasil'],
            ]);
        }

        // Cek silang: KODE_RBM = 3 huruf unit + kode petugas + huruf hari baca, contoh AJAAWAA.
        $rbmPetugas = substr($data['kode_rbm'], 3, 3);
        $rbmHari = substr($data['kode_rbm'], 6, 1);
        if ($rbmPetugas !== $kodePetugas || $rbmHari !== $hari) {
            $hasil->warning($nomor, 'RBM_TIDAK_COCOK', "KODE_RBM {$data['kode_rbm']} tidak cocok dengan petugas {$kodePetugas} hari {$hari}.");
        }

        if ($ctx->petugas->billerId($login) === null) {
            $hasil->warning($nomor, 'PETUGAS_TIDAK_DIKENAL', "Login {$login} belum terpetakan ke biller; baris disimpan tanpa biller.", ['login' => $login]);
        }

        $hasil->tambahBaris($nomor, $data);
    }

    /** @return array{jml_plg: int|null, terbaca: int|null, belum_baca: int|null, foto_berhasil: int|null}|null */
    private function angkaTotal(array $sel, array $kolom, int $nomor, HasilParsing $hasil): ?array
    {
        try {
            return [
                'jml_plg' => Nilai::bulat(self::sel($sel, $kolom, 'JMLPLG')),
                'terbaca' => Nilai::bulat(self::sel($sel, $kolom, 'TERBACA')),
                'belum_baca' => Nilai::bulat(self::sel($sel, $kolom, 'BLMBACA')),
                'foto_berhasil' => Nilai::bulat(self::sel($sel, $kolom, 'JML FOTO BERHASIL')),
            ];
        } catch (NilaiTidakValid $e) {
            $hasil->error($nomor, 'TIPE_TIDAK_VALID', "Angka GRAND TOTAL tidak bisa diurai ({$e->getMessage()}).");

            return null;
        }
    }

    public function validate(HasilParsing $hasil, KonteksParsing $ctx): void
    {
        $baris = array_column($hasil->baris, 'data');
        $jumlah = [
            'jml_plg' => array_sum(array_column($baris, 'jml_plg')),
            'terbaca' => array_sum(array_column($baris, 'terbaca')),
            'foto_berhasil' => array_sum(array_column($baris, 'foto_berhasil')),
        ];

        $grand = $hasil->ringkasan['grand_total'] ?? null;
        if ($grand === null) {
            $hasil->error(null, 'TOTAL_MISMATCH', 'Baris GRAND TOTAL tidak ditemukan, jumlah hasil parsing tidak bisa dicocokkan.');
        } else {
            foreach (['jml_plg' => 'JMLPLG', 'terbaca' => 'TERBACA', 'foto_berhasil' => 'JML FOTO BERHASIL'] as $k => $label) {
                if ($grand[$k] !== null && $grand[$k] !== $jumlah[$k]) {
                    $hasil->error(null, 'TOTAL_MISMATCH', "Jumlah {$label} hasil parsing {$jumlah[$k]} berbeda dari GRAND TOTAL {$grand[$k]}.", [
                        'hasil_parsing' => $jumlah[$k], 'grand_total' => $grand[$k],
                    ]);
                }
            }
        }

        // Kunci unik tabel reading_logs: (periode, jenis meter, KODE_RBM, login).
        $dilihat = [];
        foreach ($hasil->baris as $b) {
            $kunci = $b['data']['kode_rbm'].'|'.$b['data']['petugas_login'];
            if (isset($dilihat[$kunci])) {
                $hasil->error($b['row_number'], 'DUPLIKAT_BARIS', "KODE_RBM {$b['data']['kode_rbm']} untuk {$b['data']['petugas_login']} sudah ada di baris {$dilihat[$kunci]}.");
            }
            $dilihat[$kunci] ??= $b['row_number'];
        }

        $hariJ = array_filter($baris, fn ($d) => $d['hari_baca'] === 'J');
        $hasil->ringkasan += [
            'baris' => count($baris),
            'login_unik' => count(array_unique(array_column($baris, 'petugas_login'))),
            'hari_baca' => array_values(array_unique(array_column($baris, 'hari_baca'))),
            'jumlah' => $jumlah,
            'hari_j' => ['baris' => count($hariJ), 'terbaca' => array_sum(array_column($hariJ, 'terbaca'))],
            'durasi_detik' => array_sum(array_column($baris, 'durasi_detik')),
        ];
    }

    private function wajib(?int $v, string $kolom): int
    {
        if ($v === null) {
            throw new NilaiTidakValid("{$kolom} kosong");
        }

        return $v;
    }
}
