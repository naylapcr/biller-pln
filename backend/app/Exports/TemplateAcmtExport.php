<?php

namespace App\Exports;

use App\Enums\Modul;
use App\Parsers\PenguraiBerkas;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template format kolom wajib (04.5c): satu sheet per modul dengan letak judul, metadata, dan
 * header yang sama seperti ekspor ACMT asli.
 */
class TemplateAcmtExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly string $blth) {}

    public function sheets(): array
    {
        $pengurai = new PenguraiBerkas;

        return array_map(function (Modul $m) use ($pengurai) {
            $parser = $pengurai->parserUntuk($m);

            return new TemplateAcmtSheet($m->label(), $parser->judul(), $parser->kolomWajib(), $this->blth);
        }, Modul::tersediaSemua());
    }
}
