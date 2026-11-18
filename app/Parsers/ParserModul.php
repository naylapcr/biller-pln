<?php

namespace App\Parsers;

use App\Enums\Modul;

/**
 * Kontrak parser per modul (PRD bagian 5): detect() mengenali sheet dari judul dan header,
 * parse() menghasilkan baris staging, validate() menambah issue lintas baris.
 * Deteksi memakai judul dan header, bukan nama sheet, karena nama sheet bisa diubah pengguna.
 */
abstract class ParserModul
{
    abstract public function modul(): Modul;

    /** Judul sheet (huruf besar), contoh "REKAP WAKTU PEMBACAAN METER". */
    abstract public function judul(): string;

    /**
     * Kolom wajib dalam urutan yang diharapkan (nama baku huruf besar).
     *
     * @return list<string>
     */
    abstract public function kolomWajib(): array;

    /**
     * @param  iterable<int, list<mixed>>  $baris  nomor baris => nilai sel, mulai baris setelah header
     * @param  array<string, int>  $kolom  nama kolom baku => indeks
     */
    abstract public function parse(iterable $baris, array $kolom, KonteksParsing $ctx, HasilParsing $hasil): void;

    /** Pemeriksaan lintas baris (total, duplikat). */
    public function validate(HasilParsing $hasil, KonteksParsing $ctx): void {}

    public function detect(string $judul, array $header): bool
    {
        if (Nilai::baku($judul) !== $this->judul()) {
            return false;
        }
        $ada = array_map(fn ($h) => Nilai::baku($h), array_filter($header, fn ($h) => ! Nilai::kosong($h)));

        return count(array_intersect($this->kolomWajib(), $ada)) >= min(3, count($this->kolomWajib()));
    }

    /** Ambil nilai sel berdasarkan nama kolom. */
    protected static function sel(array $baris, array $kolom, string $nama): mixed
    {
        return isset($kolom[$nama]) ? ($baris[$kolom[$nama]] ?? null) : null;
    }

    protected static function barisKosong(array $baris): bool
    {
        foreach ($baris as $v) {
            if (! Nilai::kosong($v)) {
                return false;
            }
        }

        return true;
    }

    protected static function footer(mixed $v): bool
    {
        return is_string($v) && str_starts_with(Nilai::baku($v), 'DIAKSES OLEH');
    }
}
