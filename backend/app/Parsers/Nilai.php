<?php

namespace App\Parsers;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Pengurai nilai sel ekspor ACMT. Setiap fungsi mengembalikan null bila sel kosong
 * dan melempar NilaiTidakValid bila sel berisi sesuatu yang tidak bisa diurai.
 */
final class Nilai
{
    public const ZONA = 'Asia/Jakarta';

    public static function kosong(mixed $v): bool
    {
        return $v === null || (is_string($v) && trim($v) === '');
    }

    public static function teks(mixed $v): ?string
    {
        if (self::kosong($v)) {
            return null;
        }

        return trim(is_float($v) ? self::angkaKeTeks($v) : (string) $v);
    }

    /** IDPEL / NO METER: angka panjang yang terbaca float ("181101389783.0") disimpan sebagai teks utuh. */
    public static function kode(mixed $v, ?int $panjang = null): ?string
    {
        if (self::kosong($v)) {
            return null;
        }
        $s = is_float($v) || is_int($v) ? self::angkaKeTeks((float) $v) : preg_replace('/\.0+$/', '', trim((string) $v));
        if (! preg_match('/^\d+$/', $s)) {
            throw new NilaiTidakValid("bukan angka: {$s}");
        }

        return $panjang ? str_pad($s, $panjang, '0', STR_PAD_LEFT) : $s;
    }

    /** Bilangan bulat; "100.0" (teks) maupun 100 (angka) diterima. */
    public static function bulat(mixed $v): ?int
    {
        $f = self::desimal($v);

        if ($f === null) {
            return null;
        }
        if (abs($f - round($f)) > 1e-9) {
            throw new NilaiTidakValid("bukan bilangan bulat: {$f}");
        }

        return (int) round($f);
    }

    public static function desimal(mixed $v): ?float
    {
        if (self::kosong($v)) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace(' ', '', trim((string) $v));
        if (! is_numeric($s)) {
            throw new NilaiTidakValid("bukan angka: {$s}");
        }

        return (float) $s;
    }

    /**
     * Tanggal-waktu WIB dari angka seri Excel atau teks. Format teks yang dikenal:
     * "23/10/2026 7:28:12" (rekap pascabayar) dan "26 Oct 2026 13.42" (Baca Ulang).
     */
    public static function waktu(mixed $v): ?CarbonImmutable
    {
        if (self::kosong($v)) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            $dt = ExcelDate::excelToDateTimeObject((float) $v, self::ZONA);

            // Bulatkan ke detik terdekat (angka seri Excel menyimpan pecahan detik).
            return CarbonImmutable::instance($dt)->addMilliseconds(500)->startOfSecond();
        }

        $s = preg_replace('/\s+/', ' ', trim((string) $v));
        $jam = '(?:\s+(\d{1,2})[.:](\d{2})(?:[.:](\d{2}))?)?';

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})'.$jam.'$/', $s, $m)) {
            [$hari, $bulan, $tahun] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2}) ([A-Za-z]{3,9})\.? (\d{4})'.$jam.'$/', $s, $m)) {
            $bulan = self::BULAN[strtolower(substr($m[2], 0, 3))] ?? null;
            [$hari, $tahun] = [(int) $m[1], (int) $m[3]];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})'.str_replace('\s+', '[ T]', $jam).'$/', $s, $m)) {
            [$tahun, $bulan, $hari] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            throw new NilaiTidakValid("tanggal tidak dikenali: {$s}");
        }

        [$h, $i, $d] = [(int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0)];
        if (! $bulan || ! checkdate($bulan, $hari, $tahun) || $h > 23 || $i > 59 || $d > 59) {
            throw new NilaiTidakValid("tanggal tidak valid: {$s}");
        }

        return CarbonImmutable::create($tahun, $bulan, $hari, $h, $i, $d, self::ZONA);
    }

    /** Nama bulan Inggris dan Indonesia (3 huruf pertama). */
    private const BULAN = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'mei' => 5, 'jun' => 6, 'jul' => 7,
        'aug' => 8, 'agu' => 8, 'agt' => 8, 'sep' => 9, 'oct' => 10, 'okt' => 10, 'nov' => 11, 'dec' => 12, 'des' => 12,
    ];

    /**
     * Durasi WAKTU dalam detik. Bentuknya "4 - 06:03:51" (hari - jam:menit:detik) untuk durasi
     * ≥ 1 hari, atau nilai waktu Excel (pecahan hari) / teks "16:44:28" untuk durasi < 1 hari.
     */
    public static function durasi(mixed $v): ?int
    {
        if (self::kosong($v)) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (int) round((float) $v * 86400);
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d+)\s*-\s*(\d{1,2}):(\d{2}):(\d{2})$/', $s, $m)) {
            return (int) $m[1] * 86400 + (int) $m[2] * 3600 + (int) $m[3] * 60 + (int) $m[4];
        }
        if (preg_match('/^(\d{1,3}):(\d{2}):(\d{2})$/', $s, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }
        if (is_numeric($s)) {
            return (int) round((float) $s * 86400);
        }

        throw new NilaiTidakValid("durasi tidak dikenali: {$s}");
    }

    /**
     * Koordinat prabayar tanpa titik desimal: bujur "101" + sisa digit (101444303 → 101,444303),
     * lintang "0." + digit (5159556 → 0,5159556). Nilai yang sudah desimal dibiarkan.
     *
     * @return array{0: float|null, 1: float|null} [lintang, bujur]
     */
    public static function koordinat(mixed $x, mixed $y): array
    {
        $bujur = self::kosong($x) ? null : self::normalBujur($x);
        $lintang = self::kosong($y) ? null : self::normalLintang($y);

        return [$lintang, $bujur];
    }

    private static function normalBujur(mixed $v): ?float
    {
        $s = is_float($v) || is_int($v) ? self::angkaKeTeks((float) $v) : trim((string) $v);
        if (str_contains($s, '.') && is_numeric($s)) {
            return round((float) $s, 7);
        }
        if (! preg_match('/^\d+$/', $s)) {
            throw new NilaiTidakValid("koordinat X tidak dikenali: {$s}");
        }
        if ((int) $s === 0) {
            return null;
        }

        return round((float) (substr($s, 0, 3).'.'.substr($s, 3)), 7);
    }

    private static function normalLintang(mixed $v): ?float
    {
        $s = is_float($v) || is_int($v) ? self::angkaKeTeks((float) $v) : trim((string) $v);
        if (str_contains($s, '.') && is_numeric($s)) {
            return round((float) $s, 7);
        }
        $negatif = str_starts_with($s, '-');
        $digit = ltrim($s, '-');
        if (! preg_match('/^\d+$/', $digit)) {
            throw new NilaiTidakValid("koordinat Y tidak dikenali: {$s}");
        }
        if ((int) $digit === 0) {
            return null;
        }

        return round(($negatif ? -1 : 1) * (float) ('0.'.$digit), 7);
    }

    private static function angkaKeTeks(float $f): string
    {
        return abs($f - round($f)) < 1e-9 ? sprintf('%.0f', $f) : rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
    }

    /** Judul atau header: huruf besar, spasi tunggal. */
    public static function baku(mixed $v): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $v)));
    }
}
