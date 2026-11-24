<?php

namespace App\Services;

use App\Models\Biller;
use App\Models\BillerAlias;

/**
 * Mencocokkan login ACMT ke biller. Pencocokan tidak peka huruf besar-kecil, tetapi tanpa
 * pencocokan mirip: 18111.deni dan 18111.denni adalah dua orang berbeda.
 */
class PemetaanPetugas
{
    /**
     * @param  array<string, int>  $login  login huruf kecil → biller_id (login utama dan alias)
     * @param  array<string, string>  $kode  kode petugas ACMT huruf besar → login utama
     */
    public function __construct(
        private readonly array $login = [],
        private readonly array $kode = [],
    ) {}

    public static function muat(): self
    {
        $billers = Biller::query()->get(['id', 'login_acmt', 'kode_petugas_acmt']);
        $login = $billers->mapWithKeys(fn (Biller $b) => [strtolower($b->login_acmt) => $b->id])->all();
        foreach (BillerAlias::query()->get(['biller_id', 'login_acmt']) as $a) {
            $login[strtolower($a->login_acmt)] = $a->biller_id;
        }

        return new self(
            $login,
            $billers->mapWithKeys(fn (Biller $b) => [strtoupper($b->kode_petugas_acmt) => strtolower($b->login_acmt)])->all(),
        );
    }

    public function billerId(?string $login): ?int
    {
        return $login === null ? null : ($this->login[strtolower(trim($login))] ?? null);
    }

    /** Login utama pemilik kode petugas (contoh AWA → 18111.akmal), null bila kode belum terdaftar. */
    public function loginDariKode(?string $kode): ?string
    {
        return $kode === null ? null : ($this->kode[strtoupper($kode)] ?? null);
    }
}
