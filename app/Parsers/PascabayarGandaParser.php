<?php

namespace App\Parsers;

use App\Enums\JenisMeter;
use App\Enums\Modul;

class PascabayarGandaParser extends PascabayarParser
{
    public function modul(): Modul
    {
        return Modul::PascabayarGanda;
    }

    public function judul(): string
    {
        return 'REKAP WAKTU PEMBACAAN METER GANDA';
    }

    protected function jenisMeter(): JenisMeter
    {
        return JenisMeter::GandaIndustri;
    }
}
