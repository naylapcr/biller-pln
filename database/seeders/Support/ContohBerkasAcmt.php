<?php

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Membuat berkas .xlsx berformat ekspor FSM ACMT (DATA_REKAP_WBM) berisi data sintetis:
 * 7 baris metadata, header di baris 8, data, GRAND TOTAL, dan footer "Diakses oleh …".
 * Dipakai seeder (data demo) dan tes, sehingga keduanya melewati jalur parsing yang sama
 * dengan berkas asli. Tidak memuat data pelanggan sungguhan.
 */
class ContohBerkasAcmt
{
    private Spreadsheet $buku;

    /**
     * @param  list<array{0: string, 1: string}>  $petugas  [login ACMT, kode petugas], contoh ['18111.AKMAL', 'AWA']
     */
    public function __construct(
        private readonly array $petugas,
        private readonly string $blth = '202611',
        private readonly string $unitup = '18111',
        int $seed = 202611,
    ) {
        mt_srand($seed);
        $this->buku = new Spreadsheet;
        $this->buku->removeSheetByIndex(0);
    }

    /**
     * Sheet PASCA TUNGGAL. Baris bisa diberikan langsung; bila null dibuat acak untuk hari A–G dan J.
     *
     * @param  list<array{hari: string, login: string, kode: string, jml: int, terbaca: int, foto: int, mulai?: CarbonImmutable, detik?: int}>|null  $baris
     * @param  array{grand_total?: array<int, int>, judul?: string, blth?: string}  $opsi
     */
    public function tunggal(?array $baris = null, array $opsi = []): self
    {
        return $this->pasca('PASCA TUNGGAL', $opsi['judul'] ?? 'REKAP WAKTU PEMBACAAN METER', 'Hari Baca - ', $baris ?? $this->barisPasca(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J']), $opsi, true);
    }

    /** Sheet PASCA GANDAINDUSTRI (label hari "Hari Baca - Hari Baca - A", % BACA berupa angka). */
    public function ganda(?array $baris = null, array $opsi = []): self
    {
        $baris ??= array_map(fn ($p) => ['hari' => 'A', 'login' => $p[0], 'kode' => $p[1], 'jml' => 1, 'terbaca' => 1, 'foto' => 1, 'detik' => 0],
            array_slice($this->petugas, 0, 2));

        return $this->pasca('PASCA GANDAINDUSTRI', $opsi['judul'] ?? 'REKAP WAKTU PEMBACAAN METER GANDA', 'Hari Baca - Hari Baca - ', $baris, $opsi, false);
    }

    /**
     * Sheet BACA ULANG PASCA.
     *
     * @param  list<array{idpel: string, login: string, kode: string, hari?: string, daya: int, tgl: CarbonImmutable, stan: float, tgl_bu?: CarbonImmutable|null, stan_bu?: float|null, ket?: string}>|null  $baris
     */
    public function bacaUlang(?array $baris = null): self
    {
        $baris ??= $this->barisBacaUlang();
        $ws = $this->sheet('BACA ULANG PASCA', 'REKAP BACA ULANG', "UNITUP - {$this->unitup} BLTH - {$this->blth} JENIS - SEMUA");
        $ws->fromArray(['NO', 'UNITUP', 'IDPEL', 'BLTH', 'TARIF', 'DAYA', 'KODE RBM', 'TGLBACA', 'STAN METER', 'PEMKWH',
            'TGLBACA BU', 'STAN METER BU', 'PEMKWH BU', 'PETUGAS', 'KETERANGAN'], null, 'A8');

        $r = 9;
        foreach ($baris as $i => $b) {
            $belum = ($b['ket'] ?? 'SUDAH BACA') === 'BELUM BACA';
            $ws->fromArray([
                $i + 1, (int) $this->unitup, (float) $b['idpel'], (int) $this->blth, 'R1', $b['daya'],
                'AJA'.$b['kode'].($b['hari'] ?? 'A'),
                $b['tgl']->format('d M Y H.i'), $b['stan'], mt_rand(50, 400),
                $belum ? null : $b['tgl_bu']->format('d M Y H.i'),
                $belum ? null : $b['stan_bu'], $belum ? null : mt_rand(0, 50),
                $b['login'], $belum ? 'BELUM BACA' : 'SUDAH BACA',
            ], null, "A{$r}", true);
            $r++;
        }
        $ws->setCellValue('A'.($r + 1), 'Diakses oleh , Nov 16, 2026, 1:32 PM, via FSM - ACMT');

        return $this;
    }

    /** Sheet PRABAYAR (34 kolom), $perPetugas pemeriksaan per petugas. */
    public function prabayar(int $perPetugas = 5, array $kdbaca = ['Z', 'Z', 'Z', 'Z', '1']): self
    {
        $ws = $this->sheet('PRABAYAR', 'MONITORING FISIK METER PRABAYAR', "UNITUP - {$this->unitup} BLTH - {$this->blth}");
        $ws->fromArray(['NO', 'IDPEL', 'NAMA', 'ALAMAT', 'NO METER', 'TARIF', 'KDPT', 'DAYA', 'KDRBM', 'LANGKAH', 'SISIPAN',
            'KD KELOMPOK', 'TGLBACA', 'KDBACA', 'PETUGAS', 'TEGANGAN', 'ARUS', 'COSPHI', 'TARIF INDEX', 'PWR LIMIT',
            'KWH KUMULATIF', 'INDIKATOR', 'KWH SISA', 'STATUS TEMPER', 'TUTUP METER', 'SEGEL', 'LCD', 'KEYPAD',
            'JML TERMINAL', 'INDI TEMPER', 'RELAY', 'KOORDINAT X', 'KOORDINAT Y', 'AKURASI'], null, 'A8');

        $r = 9;
        $no = 1;
        $awal = CarbonImmutable::create((int) substr($this->blth, 0, 4), (int) substr($this->blth, 4, 2), 1, 8, 0, 0, 'Asia/Jakarta')->subMonth()->addDays(4);
        foreach ($this->petugas as $p => [$login]) {
            for ($i = 0; $i < $perPetugas; $i++) {
                $ws->fromArray([
                    $no, (float) ('1811'.str_pad((string) ($p * 1000 + $i + 1), 8, '0', STR_PAD_LEFT)), 'PELANGGAN CONTOH', 'JL CONTOH',
                    (float) ('5600'.str_pad((string) ($p * 1000 + $i), 7, '0', STR_PAD_LEFT)), 'R1T', null, 1300, 'ABAAC'.chr(65 + $i % 10).'A',
                    $i, 0, chr(65 + $i % 11), ExcelDate::PHPToExcel($awal->addDays($i % 15)->addMinutes($i * 7)),
                    $kdbaca[$i % count($kdbaca)], $login, 220, '0.4', '0.7', 10, 3740, 10000 + $i, 0, mt_rand(1000, 90000),
                    'Tidak Nyala', 0, 'Ada', 'Normal', 'Normal', 5, 'Tidak Nyala', 'Tutup',
                    // Satu dari tiap 10 pemeriksaan sengaja di luar kotak wilayah.
                    $i % 10 === 9 ? 106444303 : 101444303 + $i * 37, 5159556 + $i * 11, null,
                ], null, "A{$r}", true);
                $r++;
                $no++;
            }
        }
        $ws->setCellValue('A'.($r + 1), 'Diakses oleh -, Nov 16, 2026, 1:33 PM, via FSM - ACMT');

        return $this;
    }

    public function simpan(string $path): string
    {
        @mkdir(dirname($path), 0775, true);
        (new Xlsx($this->buku))->save($path);

        return $path;
    }

    // ---------------------------------------------------------------------------------------------

    private function pasca(string $nama, string $judul, string $labelHari, array $baris, array $opsi, bool $persenTeks): self
    {
        $ws = $this->sheet($nama, $judul, 'UNITUP - '.$this->unitup.' BLTH - '.($opsi['blth'] ?? $this->blth));
        $ws->fromArray(['KDPETUGAS', 'KODE_RBM', 'JMLPLG', 'TERBACA', 'BLMBACA', '% BACA', 'JML FOTO BERHASIL', 'TGL MULAI', 'TGL SELESAI', 'WAKTU'], null, 'A8');

        $r = 9;
        $total = [0, 0, 0, 0];
        foreach (collect($baris)->groupBy('hari') as $hari => $grup) {
            $ws->setCellValue("A{$r}", $labelHari.$hari);
            $r++;
            $sub = [0, 0, 0, 0];
            foreach ($grup as $b) {
                $mulai = $b['mulai'] ?? CarbonImmutable::create(2026, 10, 23, 8, 0, 0, 'Asia/Jakarta')->addDays(ord($hari) - 65);
                $detik = $b['detik'] ?? mt_rand(3600, 4 * 86400);
                $selesai = $mulai->addSeconds($detik);
                $waktu = $detik >= 86400
                    ? sprintf('%d - %02d:%02d:%02d', intdiv($detik, 86400), intdiv($detik % 86400, 3600), intdiv($detik % 3600, 60), $detik % 60)
                    : $detik / 86400;
                $ws->fromArray([
                    "Petugas - {$b['login']} - {$b['kode']}", $b['rbm'] ?? 'AJA'.$b['kode'].$hari, $b['jml'], $b['terbaca'], $b['jml'] - $b['terbaca'],
                    $persenTeks ? number_format($b['terbaca'] / max(1, $b['jml']) * 100, 1, '.', '') : round($b['terbaca'] / max(1, $b['jml']) * 100, 1),
                    $b['foto'], ExcelDate::PHPToExcel($mulai), ExcelDate::PHPToExcel($selesai), $waktu,
                ], null, "A{$r}", true);
                $r++;
                $sub = [$sub[0] + $b['jml'], $sub[1] + $b['terbaca'], $sub[2] + $b['jml'] - $b['terbaca'], $sub[3] + $b['foto']];
            }
            $ws->fromArray(['TOTAL GROUP', null, ...array_slice($sub, 0, 3), null, $sub[3]], null, "A{$r}", true);
            $r++;
            $total = array_map(fn ($a, $b) => $a + $b, $total, $sub);
        }
        $total = $opsi['grand_total'] ?? $total;
        $ws->fromArray(['GRAND TOTAL', null, $total[0], $total[1], $total[2], null, $total[3]], null, "A{$r}", true);
        $ws->setCellValue('A'.($r + 2), 'Diakses oleh 9114243ZY, Nov 16, 2026, 12:33 PM, via FSM - ACMT');

        return $this;
    }

    private function sheet(string $nama, string $judul, string $meta): Worksheet
    {
        $ws = $this->buku->createSheet();
        $ws->setTitle($nama);
        $ws->setCellValue('A1', 'RIAU DAN KEPRI');
        $ws->setCellValue('A2', 'PEKANBARU');
        $ws->setCellValue('A3', 'PEKANBARU KOTA BARAT');
        $ws->setCellValue('A5', $judul);
        $ws->setCellValue('A6', $meta);

        return $ws;
    }

    /** @return list<array<string, mixed>> */
    private function barisPasca(array $hari): array
    {
        $baris = [];
        foreach ($hari as $h) {
            foreach ($this->petugas as [$login, $kode]) {
                if ($h === 'J' && mt_rand(0, 1) === 0) {
                    continue;
                }
                $jml = $h === 'J' ? mt_rand(1, 5) : mt_rand(150, 320);
                $baris[] = ['hari' => $h, 'login' => $login, 'kode' => $kode, 'jml' => $jml, 'terbaca' => $jml, 'foto' => max(0, $jml - mt_rand(0, 8))];
            }
        }

        return $baris;
    }

    /** @return list<array<string, mixed>> */
    private function barisBacaUlang(): array
    {
        $baris = [];
        foreach ($this->petugas as $p => [$login, $kode]) {
            $jumlah = mt_rand(5, 20);
            for ($i = 0; $i < $jumlah; $i++) {
                $tgl = CarbonImmutable::create(2026, 10, 23 + $i % 7, 9 + $i % 6, 10 * ($i % 6), 0, 'Asia/Jakarta');
                $stan = (float) mt_rand(1000, 90000);
                $jam = mt_rand(3, 30);
                $jenis = mt_rand(1, 10);
                $baris[] = [
                    'idpel' => '1811'.str_pad((string) (500000 + $p * 100 + $i), 8, '0', STR_PAD_LEFT),
                    'login' => $login, 'kode' => $kode, 'hari' => chr(65 + $i % 7), 'daya' => 1300, 'tgl' => $tgl, 'stan' => $stan,
                    'tgl_bu' => $tgl->addHours($jam),
                    // Sebagian wajar, sebagian stan turun, sebagian kenaikan melebihi batas fisik.
                    'stan_bu' => match (true) {
                        $jenis <= 5 => $stan + round($jam * 1.3 * 0.5),
                        $jenis <= 7 => $stan - mt_rand(10, 500),
                        default => $stan + $jam * 1.3 * 3 + 50,
                    },
                    'ket' => $i === $jumlah - 1 && $p % 4 === 0 ? 'BELUM BACA' : 'SUDAH BACA',
                ];
            }
        }

        return $baris;
    }
}
