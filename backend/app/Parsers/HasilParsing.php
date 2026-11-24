<?php

namespace App\Parsers;

use App\Enums\Modul;
use App\Enums\TingkatIssue;

/** Hasil parsing satu sheet: baris staging, issue per baris, dan ringkasan. */
class HasilParsing
{
    /** @var list<array{row_number: int, data: array<string, mixed>}> */
    public array $baris = [];

    /** @var list<array{sheet: string, row_number: int|null, kode: string, tingkat: string, pesan: string, nilai: mixed}> */
    public array $issues = [];

    /** @var array<string, mixed> */
    public array $ringkasan = [];

    public function __construct(
        public readonly Modul $modul,
        public readonly string $sheet,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function tambahBaris(int $nomor, array $data): void
    {
        $this->baris[] = ['row_number' => $nomor, 'data' => $data];
    }

    public function issue(?int $baris, string $kode, TingkatIssue $tingkat, string $pesan, mixed $nilai = null): void
    {
        $this->issues[] = [
            'sheet' => $this->sheet,
            'row_number' => $baris,
            'kode' => $kode,
            'tingkat' => $tingkat->value,
            'pesan' => $pesan,
            'nilai' => $nilai,
        ];
    }

    public function error(?int $baris, string $kode, string $pesan, mixed $nilai = null): void
    {
        $this->issue($baris, $kode, TingkatIssue::Error, $pesan, $nilai);
    }

    public function warning(?int $baris, string $kode, string $pesan, mixed $nilai = null): void
    {
        $this->issue($baris, $kode, TingkatIssue::Warning, $pesan, $nilai);
    }

    public function info(?int $baris, string $kode, string $pesan, mixed $nilai = null): void
    {
        $this->issue($baris, $kode, TingkatIssue::Info, $pesan, $nilai);
    }

    public function jumlah(TingkatIssue $tingkat): int
    {
        return count(array_filter($this->issues, fn ($i) => $i['tingkat'] === $tingkat->value));
    }

    public function adaError(): bool
    {
        return $this->jumlah(TingkatIssue::Error) > 0;
    }

    /** @return array<string, int> jumlah issue per kode */
    public function jumlahPerKode(): array
    {
        $hasil = [];
        foreach ($this->issues as $i) {
            $hasil[$i['kode']] = ($hasil[$i['kode']] ?? 0) + 1;
        }
        ksort($hasil);

        return $hasil;
    }
}
