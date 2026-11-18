<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Laporan peringkat: angka mentah tiap komponen skor. */
class PeringkatExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithTitle
{
    /** @param  Collection<int, array<string, mixed>>  $baris */
    public function __construct(private Collection $baris, private string $judul) {}

    public function title(): string
    {
        return $this->judul;
    }

    public function headings(): array
    {
        return [
            'Peringkat', 'Nama biller', 'Login ACMT', 'Kode petugas', 'Zona',
            'Pasca: terbaca', 'Pasca: kasus BU', 'Pasca: StanValid', 'Pasca: eviden (foto)', 'Pasca: poin',
            'Prabayar: pemeriksaan', 'Prabayar: StanValid', 'Prabayar: eviden', 'Prabayar: poin',
            'Poin tugas', 'Bonus Hari J', 'Total denda', 'Skor akhir', 'Kategori',
        ];
    }

    public function array(): array
    {
        return $this->baris->map(fn (array $b) => [
            $b['peringkat'],
            $b['nama'],
            $b['login_acmt'],
            $b['kode_petugas'],
            $b['zona_wilayah'],
            $b['pascabayar']['terbaca'] ?? null,
            $b['pascabayar']['kasus_bu'] ?? null,
            $b['pascabayar']['stan_valid'] ?? null,
            $b['pascabayar']['eviden'] ?? null,
            $b['pascabayar']['poin'] ?? null,
            $b['prabayar']['pemeriksaan'] ?? null,
            $b['prabayar']['stan_valid'] ?? null,
            $b['prabayar']['eviden'] ?? null,
            $b['prabayar']['poin'] ?? null,
            $b['poin_tugas'],
            $b['poin_hari_j'],
            $b['total_denda'],
            $b['final_score'],
            $b['kategori'] === 'perlu_evaluasi' ? 'Perlu evaluasi' : 'Stabil',
        ])->all();
    }

    public function columnFormats(): array
    {
        return ['J' => '#,##0.00', 'N' => '#,##0.00', 'O' => '#,##0.00', 'P' => '#,##0.00', 'Q' => '#,##0.00', 'R' => '#,##0.00'];
    }
}
