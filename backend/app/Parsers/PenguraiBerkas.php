<?php

namespace App\Parsers;

use App\Enums\Modul;

/**
 * Mengenali sheet di berkas ekspor ACMT lalu mengurainya dengan parser modul yang cocok.
 *
 * Bentuk sheet ACMT: 7 baris metadata (judul di baris 5, "UNITUP - 18111 BLTH - 202611" di
 * baris 6), header kolom di baris 8, lalu data, dan footer "Diakses oleh … via FSM - ACMT".
 * Posisi baris tidak dipatok; judul, metadata, dan header dicari di 15 baris pertama.
 */
class PenguraiBerkas
{
    private const BARIS_KEPALA = 15;

    /** @var list<ParserModul> */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new PascabayarTunggalParser,
            new PascabayarGandaParser,
            new BacaUlangParser,
            new PrabayarParser,
        ];
    }

    public function parserUntuk(Modul $modul): ?ParserModul
    {
        foreach ($this->parsers as $p) {
            if ($p->modul() === $modul) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Sheet yang dikenali beserta modulnya.
     *
     * @return list<array{sheet: string, modul: Modul}>
     */
    public function deteksi(PembacaBerkas $berkas): array
    {
        $hasil = [];
        foreach ($berkas->daftarSheet() as $info) {
            $kepala = $this->kepala($berkas, $info['nama']);
            foreach ($this->parsers as $p) {
                if ($kepala['judul'] !== null && $p->detect($kepala['judul'], $kepala['header'] ?? [])) {
                    $hasil[] = ['sheet' => $info['nama'], 'modul' => $p->modul()];
                    break;
                }
            }
        }

        return $hasil;
    }

    public function urai(PembacaBerkas $berkas, string $sheet, Modul $modul, KonteksParsing $ctx): HasilParsing
    {
        $parser = $this->parserUntuk($modul);
        $hasil = new HasilParsing($modul, $sheet);
        $kepala = $this->kepala($berkas, $sheet);

        // Metadata: UNITUP dan BLTH.
        if ($kepala['unitup'] === null || $kepala['blth'] === null) {
            $hasil->error(null, 'HEADER_MISMATCH', 'Baris metadata "UNITUP - … BLTH - …" tidak ditemukan.');
        } else {
            if ($kepala['unitup'] !== $ctx->unitup) {
                $hasil->error($kepala['baris_meta'], 'UNIT_MISMATCH', "UNITUP {$kepala['unitup']} bukan {$ctx->unitup}.");
            }
            if ($kepala['blth'] !== $ctx->periode) {
                $hasil->error($kepala['baris_meta'], 'PERIODE_MISMATCH', "BLTH berkas {$kepala['blth']} berbeda dari periode yang dipilih {$ctx->periode}.");
            }
        }
        $hasil->ringkasan['metadata'] = ['unitup' => $kepala['unitup'], 'blth' => $kepala['blth'], 'judul' => $kepala['judul']];

        // Header: semua kolom wajib ada, dalam urutan yang sama.
        $kolom = [];
        foreach ($kepala['header'] ?? [] as $i => $h) {
            if (! Nilai::kosong($h)) {
                $kolom[Nilai::baku($h)] ??= $i;
            }
        }
        $hilang = array_values(array_diff($parser->kolomWajib(), array_keys($kolom)));
        $posisi = array_map(fn ($k) => $kolom[$k], array_intersect($parser->kolomWajib(), array_keys($kolom)));
        $urut = $posisi;
        sort($urut);
        if ($kepala['baris_header'] === null || $hilang) {
            $hasil->error($kepala['baris_header'], 'MISSING_REQUIRED_COLUMN', 'Kolom wajib tidak ditemukan: '.implode(', ', $hilang ?: $parser->kolomWajib()).'.');

            return $hasil;
        }
        if ($posisi !== $urut) {
            $hasil->error($kepala['baris_header'], 'MISSING_REQUIRED_COLUMN', 'Urutan kolom berbeda dari format ekspor ACMT.');

            return $hasil;
        }

        $parser->parse($berkas->baris($sheet, $kepala['baris_header'] + 1), $kolom, $ctx, $hasil);
        $parser->validate($hasil, $ctx);
        $hasil->ringkasan['metadata'] = ['unitup' => $kepala['unitup'], 'blth' => $kepala['blth'], 'judul' => $kepala['judul']];

        return $hasil;
    }

    /**
     * @return array{judul: string|null, unitup: string|null, blth: string|null, baris_meta: int|null, header: list<mixed>|null, baris_header: int|null}
     */
    private function kepala(PembacaBerkas $berkas, string $sheet): array
    {
        $hasil = ['judul' => null, 'unitup' => null, 'blth' => null, 'baris_meta' => null, 'header' => null, 'baris_header' => null];
        $judulDikenal = array_map(fn (ParserModul $p) => $p->judul(), $this->parsers);

        foreach ($berkas->baris($sheet, 1, self::BARIS_KEPALA) as $nomor => $sel) {
            $isi = array_values(array_filter($sel, fn ($v) => ! Nilai::kosong($v)));
            if (! $isi) {
                continue;
            }
            $pertama = Nilai::baku($isi[0]);

            if ($hasil['judul'] === null && in_array($pertama, $judulDikenal, true)) {
                $hasil['judul'] = $pertama;

                continue;
            }
            if (preg_match('/UNITUP\s*-\s*(\d+).*BLTH\s*-\s*(\d{6})/i', implode(' ', array_map('strval', $isi)), $m)) {
                [$hasil['unitup'], $hasil['blth'], $hasil['baris_meta']] = [$m[1], $m[2], $nomor];

                continue;
            }
            // Header = baris pertama setelah judul yang berisi banyak teks.
            if ($hasil['judul'] !== null && $hasil['baris_header'] === null && count($isi) >= 3
                && count(array_filter($isi, 'is_string')) === count($isi)) {
                $hasil['header'] = $sel;
                $hasil['baris_header'] = $nomor;
                break;
            }
        }

        return $hasil;
    }
}
