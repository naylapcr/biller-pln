<?php

namespace App\Parsers;

use App\Models\Period;
use App\Services\ParameterSkor;
use App\Services\PemetaanPetugas;

/** Data yang dibutuhkan parser selain isi berkas. */
class KonteksParsing
{
    /**
     * @param  array{lintang_min: float, lintang_max: float, bujur_min: float, bujur_max: float}  $wilayah
     */
    public function __construct(
        public readonly string $periode,
        public readonly string $unitup,
        public readonly PemetaanPetugas $petugas,
        public readonly array $wilayah,
        public readonly float $toleransiKwh = 1.0,
    ) {}

    public static function untuk(string $periode): self
    {
        return new self(
            $periode,
            (string) config('sipermon.unitup'),
            PemetaanPetugas::muat(),
            config('sipermon.wilayah'),
            (float) (($p = Period::kode($periode)) ? app(ParameterSkor::class)->untuk($p)['toleransi_kwh'] : config('sipermon.parameter.toleransi_kwh')),
        );
    }

    public function dalamWilayah(?float $lintang, ?float $bujur): bool
    {
        return $lintang !== null && $bujur !== null
            && $lintang >= $this->wilayah['lintang_min'] && $lintang <= $this->wilayah['lintang_max']
            && $bujur >= $this->wilayah['bujur_min'] && $bujur <= $this->wilayah['bujur_max'];
    }
}
