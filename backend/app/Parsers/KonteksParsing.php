<?php

namespace App\Parsers;

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
    ) {}

    public static function untuk(string $periode): self
    {
        return new self(
            $periode,
            (string) config('sipermon.unitup'),
            PemetaanPetugas::muat(),
            config('sipermon.wilayah'),
        );
    }

    public function dalamWilayah(?float $lintang, ?float $bujur): bool
    {
        return $lintang !== null && $bujur !== null
            && $lintang >= $this->wilayah['lintang_min'] && $lintang <= $this->wilayah['lintang_max']
            && $bujur >= $this->wilayah['bujur_min'] && $bujur <= $this->wilayah['bujur_max'];
    }
}
