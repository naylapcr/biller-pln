<?php

namespace App\Parsers;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Membaca berkas .xlsx, .xls, atau .csv per potongan baris agar memori tetap kecil
 * (sheet Prabayar 10.000 baris × 34 kolom butuh ±350 MB bila dimuat utuh).
 * Nilai dikembalikan mentah: tanggal Excel tetap berupa angka seri.
 */
class PembacaBerkas
{
    /** @var list<array{nama: string, baris: int, kolom: string}>|null */
    private ?array $info = null;

    public function __construct(
        private readonly string $path,
        private readonly int $barisPerPotongan = 3000,
    ) {}

    /** @return list<array{nama: string, baris: int, kolom: string}> */
    public function daftarSheet(): array
    {
        return $this->info ??= array_map(fn (array $i) => [
            'nama' => (string) $i['worksheetName'],
            'baris' => (int) $i['totalRows'],
            'kolom' => (string) $i['lastColumnLetter'],
        ], $this->reader()->listWorksheetInfo($this->path));
    }

    /**
     * Baris sheet satu per satu, mulai baris $dari (1-based).
     *
     * @return Generator<int, list<mixed>> nomor baris => nilai sel
     */
    public function baris(string $sheet, int $dari = 1, ?int $sampai = null): Generator
    {
        $info = collect($this->daftarSheet())->firstWhere('nama', $sheet);
        if (! $info) {
            return;
        }
        $akhir = min($sampai ?? $info['baris'], $info['baris']);

        for ($mulai = $dari; $mulai <= $akhir; $mulai += $this->barisPerPotongan) {
            $selesai = min($mulai + $this->barisPerPotongan - 1, $akhir);

            $reader = $this->reader();
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            if (! $reader instanceof Csv) {
                $reader->setLoadSheetsOnly([$sheet]);
            }
            $reader->setReadFilter(new class($mulai, $selesai) implements IReadFilter
            {
                public function __construct(private int $a, private int $b) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row >= $this->a && $row <= $this->b;
                }
            });

            $buku = $reader->load($this->path);
            $ws = $buku->getSheet(0);
            $nilai = $ws->rangeToArray("A{$mulai}:{$info['kolom']}{$selesai}", null, false, false, false);
            $buku->disconnectWorksheets();
            unset($buku, $ws);

            foreach ($nilai as $i => $sel) {
                yield $mulai + $i => $sel;
            }
        }
    }

    private function reader(): IReader
    {
        return IOFactory::createReaderForFile($this->path);
    }
}
