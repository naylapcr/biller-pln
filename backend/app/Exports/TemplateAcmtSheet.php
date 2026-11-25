<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Satu sheet template: judul di baris 5, UNITUP dan BLTH di baris 6, header di baris 8. */
class TemplateAcmtSheet implements FromArray, WithTitle
{
    /** @param  list<string>  $kolom */
    public function __construct(private string $nama, private string $judul, private array $kolom, private string $blth) {}

    public function title(): string
    {
        return mb_substr($this->nama, 0, 31);
    }

    public function array(): array
    {
        return [
            ['PT PLN (PERSERO)'], ['ULP KOTA BARAT'], [''], [''],
            [$this->judul],
            ['UNITUP - '.config('sipermon.unitup').' BLTH - '.$this->blth],
            [''],
            $this->kolom,
        ];
    }
}
