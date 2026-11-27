<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Laporan peringkat (02.8): kolom sama dengan pratinjau di halaman Laporan & Ekspor. */
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
        return ['#', 'Nama', 'NIP', 'Login ACMT', 'Pascabayar', 'Prabayar', 'Kehadiran', 'Bonus J', 'Rasio BU', 'Rasio AP2T', 'Skor Akhir', 'Kategori'];
    }

    public function array(): array
    {
        return $this->baris->map(fn (array $b) => [
            $b['peringkat'],
            $b['nama'],
            $b['nip'],
            $b['login_acmt'],
            $b['pascabayar'],
            $b['prabayar'],
            $b['kehadiran'] ?? null,
            $b['bonus_hari_j'],
            $b['rasio_bu'],
            $b['rasio_ap2t'],
            $b['final_score'],
            $b['kategori'],
        ])->all();
    }

    public function columnFormats(): array
    {
        return ['E' => '0.00', 'F' => '0.00', 'G' => '0.00', 'H' => '0.00', 'I' => '0.00%', 'J' => '0.00%', 'K' => '0.00'];
    }
}
