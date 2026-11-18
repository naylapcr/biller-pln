<?php

namespace App\Parsers;

use App\Enums\JenisMeter;
use App\Enums\Modul;

class PascabayarTunggalParser extends PascabayarParser
{
    public function modul(): Modul
    {
        return Modul::PascabayarTunggal;
    }

    public function judul(): string
    {
        return 'REKAP WAKTU PEMBACAAN METER';
    }

    protected function jenisMeter(): JenisMeter
    {
        return JenisMeter::Tunggal;
    }
}
